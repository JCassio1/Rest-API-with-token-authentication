<!-- This file is required/used to test database connection -->

<!-- <?php

require_once('db.php');
require_once('../model/response.php');

  try{
    $writeDB = DB::connectWriteDB();
    $readDB =  DB::connectReadDB();

  }

  //Possible to have more than one catch with their exception type
  catch(PDOException $ex){
    $response = new response();
    $response->setHttpStatusCode(500);
    $response->setSucess(false);
    $response->addMessage("Database connection error");
    $response->send();
    exit; //good practice to exit script
  }

 ?> -->
