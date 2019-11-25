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


 ?>
