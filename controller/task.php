<?php

// To refresh access token, the sessions endpoint is used under 'PATCH'

  require_once('db.php');
  require_once('../model/task.php');
  require_once('../model/response.php');


  try{
    $writeDB = DB::connectWriteDB();
    $readDB =  DB::connectReadDB();
  }

  catch(PDOException $ex){
    error_log("Connection error -".$ex, 0);
    $response = new response();
    $response->setHttpStatusCode(500);
    $response->setSucess(false);
    $response->addMessage("Database Connection Error");
    $response->send();
    exit();
  }


  //----Beginning authentication setDescription

  if(!isset($_SERVER['HTTP_AUTHORIZATION']) || strlen($_SERVER['HTTP_AUTHORIZATION']) < 1) {
    $response = new response();
    $response->setHttpStatusCode(401);
    $response->setSucess(false);
    (!isset($_SERVER['HTTP_AUTHORIZATION']) ? $response->addMessage("Access token is missing from the header") : false);
    (strlen($_SERVER['HTTP_AUTHORIZATION']) < 1 ? $response->addMessage("Access token cannot be empty") : false);
    $response->send();
    exit();
  }

  $accesstoken = $_SERVER['HTTP_AUTHORIZATION'];

  try{
    $query = $writeDB->prepare('select userId, acessTokenExpiry, useractive, loginattempts from sessions, users where sessions.userId = users.id and acessToken = :accesstoken');
    $query->bindParam(':accesstoken', $accesstoken, PDO::PARAM_STR);
    $query->execute();

    $rowCount = $query->rowCount();

    if($rowCount === 0) {
      $response = new response();
      $response->setHttpStatusCode(401);
      $response->setSucess(false);
      $response->addMessage("Access token not valid");
      $response->send();
      exit();
    }

    $row = $query->fetch(PDO::FETCH_ASSOC);

    $returned_userid = $row['userId'];
    $returned_accesstokenexpiry = $row['acessTokenExpiry'];
    $returned_useractive = $row['useractive'];
    $returned_loginattempts = $row['loginattempts'];

    if($returned_useractive !== 'Y') {
      $response = new response();
      $response->setHttpStatusCode(401);
      $response->setSucess(false);
      $response->addMessage("User account not active");
      $response->send();
      exit();
    }

    if($returned_loginattempts >= 3){
      $response = new response();
      $response->setHttpStatusCode(401);
      $response->setSucess(false);
      $response->addMessage("User account currently locked out");
      $response->send();
      exit();
    }

    if(strtotime($returned_accesstokenexpiry) < time()){
      $response = new response();
      $response->setHttpStatusCode(401);
      $response->setSucess(false);
      $response->addMessage("Access token expired");
      $response->send();
      exit();
    }
  }

  catch(PDOException $ex){
    $response = new response();
    $response->setHttpStatusCode(401);
    $response->setSucess(false);
    $response->addMessage("There was an issue authenticating - please try again");
    $response->send();
    exit();
  }


  //----The end of script


  if(array_key_exists("taskid", $_GET)) {


    $taskid = $_GET['taskid'];

    if($taskid == ' ' || !is_numeric($taskid)){
      $response = new response();
      $response->setHttpStatusCode(400);
      $response->setSucess(false);
      $response->addMessage("Task ID not allowed to be blank and must be numeric");
      $response->send();
      exit();
    }


    if($_SERVER['REQUEST_METHOD'] === 'GET'){

      try{
        $query = $readDB->prepare('select id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed from tasks where id = :taskid and userId = :userid');
        $query->BindParam(':taskid', $taskid, PDO::PARAM_INT);
        $query->BindParam(':userid', $returned_userid, PDO::PARAM_INT);
        $query->execute();

        $rowCount = $query->rowCount();

        if($rowCount === 0){
          $response = new response();
          $response->setHttpStatusCode(404);
          $response->setSucess(false);
          $response->addMessage("Task not found");
          $response->send();
          exit();
        }

        //FETCH_ASSOC == get key values
        while($row = $query->fetch(PDO::FETCH_ASSOC)){
          $task = new task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed']);
          $taskArray[] = $task->returnTaskAsArray();
        }

        $returnData = array();
        $returnData['rows_returned'] = $rowCount;
        $returnData['tasks'] = $taskArray;

        $response = new response();
        $response->setHttpStatusCode(200);
        $response->setSucess(true);
        $response->toCache(true);
        $response->setData($returnData);
        $response->send();
        exit();

      }

      catch(PDOException $ex){
        $response = new response();
        $response->setHttpStatusCode(500);
        $response->setSucess(false);
        $response->addMessage($ex->getMessage());
        $response->send();
        exit;
      }

      catch(PDOException $ex){
        error_log("Database query -".$ex, 0);
        $response = new response();
        $response->setHttpStatusCode(500);
        $response->setSucess(false);
        $response->addMessage("Database Connection Error");
        $response->send();
        exit;
      }

    }

    elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {

      try{
        $query = $writeDB->prepare('Delete from tasks where id = :taskid and userId = userid');
        $query->BindParam(':taskid', $taskid, PDO::PARAM_INT);
        $query->BindParam(':userid', $returned_userid, PDO::PARAM_INT);
        $query->execute();

        $rowCount = $query->rowCount();

        if ($rowCount === 0){
          $response = new Response();
          $response->setHttpStatusCode(404);
          $response->setSucess(false);
          $response->addMessage("Task not found");
          $response->send();
          exit;
        }

        $response = new Response();
        $response->setHttpStatusCode(200);
        $response->setSucess(true);
        $response->addMessage("Task Deleted");
        $response->send();
        exit;
      }

      catch(PDOException $sex){
        $response = new Response();
        $response->setHttpStatusCode(500);
        $response->setSucess(false);
        $response->addMessage("Failed to deleted task");
        $response->send();
        exit;
      }
    }


  elseif ($_SERVER['REQUEST_METHOD'] === 'PATCH') {

    try{

      if($_SERVER['CONTENT_TYPE'] !== 'application/json') {
        $response = new response();
        $response->setHttpStatusCode(400);
        $response->setSucess(false);
        $response->addMessage("Content type header not set to JSON. #ErrorCode-200");
        $response->send();
        exit();
      }

      $rawPatchData = file_get_contents('php://input');

      if(!$jsonData = json_decode($rawPatchData)) {
        $response = new response();
        $response->setHttpStatusCode(400);
        $response->setSucess(false);
        $response->addMessage("Rest body data is not valid JSON. #ErrorCode-300");
        $response->send();
        exit();
      }

      $title_updated = false;
      $description_updated = false;
      $deadline_updated = false;
      $completed_updated = false;

      $queryFields = "";

      //Check which field were updated and passed in to the SQL HttpQueryString
      if(isset($jsonData->title)){
        $title_updated = true;
        $queryFields .= "title = :title, "; //the '.=' keeps data that already exists in the variable and appends new data
      }

      if(isset($jsonData->description)){
        $description_updated = true;
        $queryFields .= "description = :description, ";
      }

      if(isset($jsonData->deadline)){
        $deadline_updated = true;
        $queryFields .= "deadline = STR_TO_DATE(:deadline, '%d/%m/%Y %H:%i'), ";
      }

      if(isset($jsonData->completed)){
        $completed_updated = true;
        $queryFields .= "completed = :completed, ";
      }

      $queryFields = rtrim($queryFields, ", "); //Remove the last comma and space

      if($title_updated === false && $description_updated === false && $deadline_updated === false && $completed_updated === false){
        $response = new response();
        $response->setHttpStatusCode(400);
        $response->setSucess(false);
        $response->addMessage("No task field data has been provided");
        $response->send();
        exit();
      }

      $query = $writeDB->prepare('select id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed from tasks where id = :taskid and userId = :userid');
      $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
      $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
      $query->execute();

      $rowCount = $query->rowCount();

      if($rowCount === 0){
        $response = new response();
        $response->setHttpStatusCode(404);
        $response->setSucess(false);
        $response->addMessage("No tasks found to update");
        $response->send();
        exit();
      }

      while($row = $query->fetch(PDO::FETCH_ASSOC)) {
        $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed']);
      }

      $queryString = "update tasks set ".$queryFields." where id = :taskid and userId = :userid";
      $query = $writeDB->prepare($queryString);

      if($title_updated === true){
        $task->setTitle($jsonData->title);
        $up_title = $task->getTitle();
        $query->bindParam(':title', $up_title, PDO::PARAM_STR);
      }

      if($description_updated === true){
        $task->setDescription($jsonData->description);
        $up_description = $task->getDescription();
        $query->bindParam(':description', $up_description, PDO::PARAM_STR);
      }

      if($deadline_updated === true){
        $task->setDeadline($jsonData->deadline);
        $up_deadline = $task->getDeadline();
        $query->bindParam(':deadline', $up_deadline, PDO::PARAM_STR); //Even though its a date, it is passed as string
      }

      if($completed_updated === true){
        $task->setCompleted($jsonData->completed);
        $up_completed = $task->getCompleted();
        $query->bindParam(':completed', $up_completed, PDO::PARAM_STR);
      }

      $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
      $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
      $query->execute();

      $rowCount = $query->rowCount();

      if($rowCount === 0){
        $response = new response();
        $response->setHttpStatusCode(400);
        $response->setSucess(false);
        $response->addMessage("Task not updated");
        $response->send();
        exit();
      }

      //Retrieve updated task directly from Database
      $query = $writeDB->prepare('select id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed from tasks where id = :taskid and userId = :userid');
      $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
      $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
      $query->execute();

      $rowCount = $query->rowCount();

      if($rowCount === 0){
        $response = new response();
        $response->setHttpStatusCode(404);
        $response->setSucess(false);
        $response->addMessage("No task found after update #ErroCode-400");
        $response->send();
        exit();
      }


      $taskArray = array();

      while($row = $query->fetch(PDO::FETCH_ASSOC)){
        $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed']);
        $taskArray[] = $task->returnTaskAsArray();
      }

      $returnData = array();
      $returnData['rows_returned'] = $rowCount;
      $returnData['tasks'] = $taskArray;

      $response = new response();
      $response->setHttpStatusCode(200);
      $response->setSucess(true);
      $response->addMessage("Task updated");
      $response->setData($returnData);
      $response->send();
      exit();

    }

    //Task Exception is client issue because there is an error or incomplete in provided data
    catch(TaskException $ex){
        $response = new response();
        $response->setHttpStatusCode(400);
        $response->setSucess(false);
        $response->addMessage($ex->getMessage());
        $response->send();
        exit;
      }

    //PDOException is a server error
    catch(PDOException $ex){
        error_log("Database query error -".$ex, 0);
        $response = new Response();
        $response->setHttpStatusCode(500);
        $response->setSucess(false);
        $response->addMessage("Failed to get tasks");
        $response->send();
        exit;
      }
    }

    else {
      $response = new response();
      $response->setHttpStatusCode(405);
      $response->setSucess(false);
      $response->addMessage("Request Method Not Allowed");
      $response->send();
      exit();
    }
  }

elseif(array_key_exists("completed", $_GET)){

  $completed = $_GET['completed'];

  if($completed !== 'Y' && $completed !== 'N'){
    $response = new Response();
    $response->setHttpStatusCode(400);
    $response->setSucess(false);
    $response->addMessage("Completed must be 'Y' or 'N'");
    $response->send();
    exit;
  }

  if($_SERVER['REQUEST_METHOD'] === 'GET') {

    try{
      $query = $readDB->prepare('select id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed from tasks where completed = :completed and userId = :userid'); //completed : is the placeholder
      $query->bindParam(':completed', $completed, PDO::PARAM_STR);
      $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
      $query->execute();

      $rowCount = $query->rowCount();

      $taskArray = array();

      while($row = $query->fetch(PDO::FETCH_ASSOC)){

        $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed']);
        $taskArray[] = $task->returnTaskAsArray();
      }

      $returnData = array();
      $returnData['rows_returned'] = $rowCount;
      $returnData['tasks'] = $taskArray;

      $response = new Response();
      $response->setHttpStatusCode(200);
      $response->setSucess(true);
      $response->toCache(true);
      $response->addMessage($returnData);
      $response->send();
      exit;
    }

    catch(TaskException $ex){
      $response = new Response();
      $response->setHttpStatusCode(500);
      $response->setSucess(false);
      $response->toCache(true);
      $response->setData($ex->getMessage());
      $response->send();
      exit;
    }

    catch(PDOException $ex){
      error_log("Database query error -".$ex, 0);
      $response = new Response();
      $response->setHttpStatusCode(500);
      $response->setSucess(false);
      $response->addMessage("Failed to get tasks");
      $response->send();
      exit;
    }

  }


  else {
    $response = new Response();
    $response->setHttpStatusCode(405);
    $response->setSucess(false);
    $response->addMessage("Request method not allowed!");
    $response->send();
    exit;
  }
}

elseif(array_key_exists("page", $_GET)) {

  if($_SERVER['REQUEST_METHOD'] === 'GET') {

    // get page id from query string
    $page = $_GET['page'];

    //check to see if page id in query string is not empty and is number, if not return json error
    if($page == '' || !is_numeric($page)) {
      $response = new Response();
      $response->setHttpStatusCode(400);
      $response->setSuccess(false);
      $response->addMessage("Page number cannot be blank and must be numeric");
      $response->send();
      exit;
    }

    // set limit to 20 per page
    $limitPerPage = 20;

    // attempt to query the database
    try {
      // get total number of tasks
      // create db query
      $query = $readDB->prepare('select count(id) as totalNoOfTasks from tasks where userId = :userid');
      $query->BindParam(':userid', $returned_userid, PDO::PARAM_INT);
      $query->execute();

      // get row for count total
      $row = $query->fetch(PDO::FETCH_ASSOC);

      $tasksCount = intval($row['totalNoOfTasks']);

      // get number of pages required for total results use ceil to round up
      $numOfPages = ceil($tasksCount/$limitPerPage);

      // if no rows returned then always allow page 1 to show a successful response with 0 tasks
      if($numOfPages == 0){
        $numOfPages = 1;
      }

      // if passed in page number is greater than total number of pages available or page is 0 then 404 error - page not found
      if($page > $numOfPages || $page == 0) {
        $response = new Response();
        $response->setHttpStatusCode(404);
        $response->setSucess(false);
        $response->addMessage("Page not found");
        $response->send();
        exit;
      }

      // set offset based on current page, e.g. page 1 = offset 0, page 2 = offset 20
      $offset = ($page == 1 ?  0 : (20*($page-1)));

      // get rows for page
      // create db query
      $query = $readDB->prepare('select id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed from tasks where userId = :userid limit :pglimit OFFSET :offset');
      $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
      $query->bindParam(':pglimit', $limitPerPage, PDO::PARAM_INT);
      $query->bindParam(':offset', $offset, PDO::PARAM_INT);
      $query->execute();

      // get row count
      $rowCount = $query->rowCount();

      // create task array to store returned tasks
      $taskArray = array();

      // for each row returned
      while($row = $query->fetch(PDO::FETCH_ASSOC)) {
        // create new task object for each row
        $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed']);

        // create task and store in array for return in json data
        $taskArray[] = $task->returnTaskAsArray();
      }

      // bundle tasks and rows returned into an array to return in the json data
      $returnData = array();
      $returnData['rows_returned'] = $rowCount;
      $returnData['total_rows'] = $tasksCount;
      $returnData['total_pages'] = $numOfPages;
      // if passed in page less than total pages then return true
      ($page < $numOfPages ? $returnData['has_next_page'] = true : $returnData['has_next_page'] = false);
      // if passed in page greater than 1 then return true
      ($page > 1 ? $returnData['has_previous_page'] = true : $returnData['has_previous_page'] = false);
      $returnData['tasks'] = $taskArray;

      // set up response for successful return

      $response = new Response();
      $response->setHttpStatusCode(200);
      $response->setSucess(true);
      $response->toCache(true);
      $response->addMessage($returnData);
      $response->send();
      exit;
    }
    // if error with sql query return a json error
    catch(TaskException $ex) {
      $response = new Response();
      $response->setHttpStatusCode(500);
      $response->setSucess(false);
      $response->addMessage($ex->getMessage());
      $response->send();
      exit;
    }
    catch(PDOException $ex) {
      error_log("Database Query Error: ".$ex, 0);
      $response = new Response();
      $response->setHttpStatusCode(500);
      $response->setSucess(false);
      $response->addMessage("Failed to get tasks");
      $response->send();
      exit;
    }
  }
  // if any other request method apart from GET is used then return 405 method not allowed
  else {
    $response = new Response();
    $response->setHttpStatusCode(405);
    $response->setSucess(false);
    $response->addMessage("Request method not allowed");
    $response->send();
    exit;
  }
}

elseif(empty($_GET)){

  if($_SERVER['REQUEST_METHOD'] === 'GET'){

    try{

      $query = $readDB->prepare('select id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed from tasks where userId = :userid');
      $query->BindParam(':userid', $returned_userid, PDO::PARAM_INT);
      $query->execute();

      $rowCount = $query->rowCount();

      $taskArray = array();

      while($row = $query->fetch(PDO::FETCH_ASSOC)){
        $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed']);
        $taskArray[] = $task->returnTaskAsArray();
      }

      $returnData = array();
      $returnData['rows_returned'] = $rowCount;
      $returnData['tasks'] = $taskArray;

      $response = new Response();
      $response->setHttpStatusCode(200);
      $response->setSucess(true);
      $response->toCache(true);
      $response->setData($returnData);
      $response->send();
      exit;
    }

    catch(TaskException $ex){
      $response = new Response();
      $response->setHttpStatusCode(500);
      $response->setSucess(false);
      $response->addMessage($ex->getMessage());
      $response->send();
      exit;
    }

    catch(PDOException $ex){
      error_log("Database query error -".$ex, 0);
      $response = new Response();
      $response->setHttpStatusCode(500);
      $response->setSucess(false);
      $response->addMessage("Failed to get tasks");
      $response->send();
      exit;
    }
  }

  elseif($_SERVER['REQUEST_METHOD'] === 'POST'){

    try{

      if($_SERVER['CONTENT_TYPE'] !== 'application/json') {
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSucess(false);
        $response->addMessage("Content type does not correspond to JSON Format");
        $response->send();
        exit;
      }

      $rawPOSTData = file_get_contents('php://input'); //it reads and inspects the CONTENT_TYPE

      if(!$jsonData = json_decode($rawPOSTData)){
        $response = new response();
        $response->setHttpStatusCode(400);
        $response->setSucess(false);
        $response->addMessage("Request content type does not correspond to JSON Format");
        $response->send();
        exit;
      }

      if(!isset($jsonData->title) || !isset($jsonData->completed)){ //isset verifies the existence of the json values keys
        $response = new response();
        $response->setHttpStatusCode(400);
        $response->setSucess(false);
        (!isset($jsonData->title) ? $response->addMessage("Title field is mandatory and must be provided") : false);
        (!isset($jsonData->completed) ? $response->addMessage("Completed field is mandatory and must be provided") : false);
        $response->send();
        exit;
      }

      $newTask = new Task(null, $jsonData->title, (isset($jsonData->description) ? $jsonData->description : null), (isset($jsonData->deadline) ? $jsonData->deadline : null), $jsonData->completed);

      $title = $newTask->getTitle();
      $description = $newTask->getDescription();
      $deadline = $newTask->getDeadline();
      $completed = $newTask->getCompleted();

      $query = $writeDB->prepare('insert into tasks (title, description, deadline, completed, userId) values (:title, :description, STR_TO_DATE(:deadline, \'%d/%m/%Y %H:%i\'), :completed, :userid)');

      $query->bindParam(':title', $title, PDO::PARAM_STR);
      $query->bindParam(':description', $description, PDO::PARAM_STR);
      $query->bindParam(':deadline', $deadline, PDO::PARAM_STR);
      $query->bindParam(':completed', $completed, PDO::PARAM_STR);
      $query->BindParam(':userid', $returned_userid, PDO::PARAM_INT);
      $query->execute();

      $rowCount = $query->rowCount();

      if($rowCount === 0){
        $response = new Response();
        $response->setHttpStatusCode(500);
        $response->setSucess(false);
        $response->addMessage("Failed to create tasks");
        $response->send();
        exit;
      }

      $lastTaskID = $writeDB->lastInsertId(); //Gets tasksID only from the current user session

      // TaskID is retrieved from the writeDB because it is asynchronous and the readDB might not have had the data available to retrieve yet
      $query = $writeDB->prepare('select id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed from tasks where id = :taskid and userId = :userid');
      $query->bindParam(':taskid', $lastTaskID, PDO::PARAM_INT);
      $query->BindParam(':userid', $returned_userid, PDO::PARAM_INT);
      $query->execute();

      $rowCount = $query->rowCount();

      if($rowCount === 0){
        $response = new Response();
        $response->setHttpStatusCode(500);
        $response->setSucess(false);
        $response->addMessage("Not able to retrieve task after creation");
        $response->send();
        exit;
      }

      $taskArray = array();

      while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
        $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed']);
      }

      $taskArray[] = $task->returnTaskAsArray();

      $returnData = array();
      $returnData['rows_returned'] = $rowCount;
      $returnData['tasks'] = $taskArray;

      $response = new response();
      $response->setHttpStatusCode(201); //something has been created
      $response->setSucess(true);
      $response->addMessage("Task created");
      $response->setData($returnData);
      $response->send();
      exit;

    }

     catch(TaskException $ex){
       $response = new response();
       $response->setHttpStatusCode(400);
       $response->setSucess(false);
       $response->addMessage($ex->getMessage());
       $response->send();
       exit;
     }
     catch(PDOException $ex){
       error_log("Database query error -".$ex, 0);
       $response = new Response();
       $response->setHttpStatusCode(500);
       $response->setSucess(false);
       $response->addMessage("Failed to insert task to database. Check Metadata for error. Error is: ".$ex);
       $response->send();
       exit;
     }

  }

  else{
    $response = new Response();
    $response->setHttpStatusCode(405);
    $response->setSucess(false);
    $response->addMessage("Request not allowed");
    $response->send();
    exit;
  }
}

else {
  $response = new Response();
  $response->setHttpStatusCode(404);
  $response->setSucess(false);
  $response->addMessage("Endpoint not found");
  $response->send();
  exit;
}


 ?>
