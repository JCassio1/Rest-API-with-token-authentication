<!--

This is used to test task

<?php

require_once('task.php');

try{
  $task = new Task(1, "Title goes here", "description goes here", "15/09/2019 11:40", "N");
  header('Content-type: application/json;charset=UTF-8'); //client should know what content will be received

  echo json_encode($task->returnTaskAsArray());
}

catch(TaskException $ex){
  echo "Error: ".$ex->getMessage();
}


 ?> -->
