<?php

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
        $query = $readDB->prepare('select id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed from tasks where id = :taskid');
        $query->BindParam(':taskid', $taskid, PDO::PARAM_INT);
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
        exit();
      }

      catch(PDOException $ex){
        error_log("Database query -".$ex, 0);
        $response = new response();
        $response->setHttpStatusCode(500);
        $response->setSucess(false);
        $response->addMessage("Database Connection Error");
        $response->send();
        exit();
      }

    }

    elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {

      try{
        $query = $writeDB->prepare('Delete from tasks where id = :taskid');
        $query->BindParam(':taskid', $taskid, PDO::PARAM_INT);
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
      // code...
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
      $query = $readDB->prepare('select id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed from tasks where completed = :completed'); //completed : is the placeholder
      $query->bindParam(':completed', $completed, PDO::PARAM_STR);
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
      $query = $readDB->prepare('SELECT count(id) as totalNoOfTasks from tasks');
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
      $query = $readDB->prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed from tasks limit :pglimit OFFSET :offset');
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

      $query = $readDB->prepare('select id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed from tasks');
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
