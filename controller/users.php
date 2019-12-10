<?php


require_once('db.php');
require_once('../model/response.php');

try{

    $writeDB = DB::connectWriteDB();
}

catch(PDOException $ex){
  error_log("connection Error: ".$ex, 0);
  $response = new response();
  $response->setHttpStatusCode(500);
  $response->setSucess(false);
  $response->addMessage("Database Connection Error");
  $response->send();
  exit();
}

if($_SERVER['REQUEST_METHOD'] !== 'POST'){
  $response = new response();
  $response->setHttpStatusCode(405);
  $response->setSucess(false);
  $response->addMessage("Request method not allowed");
  $response->send();
  exit();
}

if($_SERVER['CONTENT_TYPE'] !== 'application/json'){
  $response = new response();
  $response->setHttpStatusCode(405);
  $response->setSucess(false);
  $response->addMessage("Content type header not in JSON format");
  $response->send();
  exit();
}

$rawPostData = file_get_contents('php://input');

if(!$jsonData = json_decode($rawPostData)){
  $response = new response();
  $response->setHttpStatusCode(400);
  $response->setSucess(false);
  $response->addMessage("Request body data is not in JSON format");
  $response->send();
  exit();
}


if (!isset($jsonData->fullname) || !isset($jsonData->username) || !isset($jsonData->password)) {
  $response = new response();
  $response->setHttpStatusCode(400);
  $response->setSucess(false);
  (!isset($jsonData->fullname) ? $response->addMessage("Full name not supplied") : false);
  (!isset($jsonData->username) ? $response->addMessage("username not supplied") : false);
  (!isset($jsonData->password) ? $response->addMessage("password not supplied") : false);
  $response->send();
  exit();
}

if(strlen($jsonData->fullname) < 1 || strlen($jsonData->fullname) > 255 || strlen($jsonData->username) < 1 || strlen($jsonData->username) > 255 || strlen($jsonData->password) < 1 || strlen($jsonData->password) > 255){
  $response = new response();
  $response->setHttpStatusCode(400);
  $response->setSucess(false);
  (strlen($jsonData->fullname) < 1 ? $response->addMessage("Full name cannot be blank") : false);
  (strlen($jsonData->fullname) > 255 ? $response->addMessage("Full name cannot be greater than 255 characters") : false);
  (strlen($jsonData->username) < 1 ? $response->addMessage("Username cannot be blank") : false);
  (strlen($jsonData->username) > 255 ? $response->addMessage("Username cannot be greater than 255 characters") : false);
  (strlen($jsonData->password) < 1 ? $response->addMessage("Password cannot be blank") : false);
  (strlen($jsonData->password) > 255 ? $response->addMessage("Password cannot be greater than 255 characters") : false);
  $response->send();
  exit();
}

$fullname = trim($jsonData->fullname);
$username = trim($jsonData->username);
$password = $jsonData->password;



try{

  $query = $writeDB->prepare('select id from users where username = :username');
  $query->bindParam(':username', $username, PDO::PARAM_STR);
  $query->execute();

  $rowCount = $query->rowCount();

  if($rowCount !== 0) {
    $response = new response();
    $response->setHttpStatusCode(409);
    $response->setSucess(false);
    $response->addMessage("Username already exists");
    $response->send();
    exit();
  }

  //Hash given Password
  $hashed_password = password_hash($password, PASSWORD_DEFAULT); //Password_default always uses the latest version of php Hash

  $query = $writeDB->prepare('insert into users (fullname, username, password) values (:fullname, :username, :password)');
  $query->bindParam(':fullname', $fullname, PDO::PARAM_STR);
  $query->bindParam(':username', $username, PDO::PARAM_STR);
  $query->bindParam(':password', $hashed_password, PDO::PARAM_STR);
  $query->execute();

  $rowCount = $query->rowCount();

  if($rowCount === 0) {
    $response = new response();
    $response->setHttpStatusCode(500);
    $response->setSucess(false);
    $response->addMessage("There was an issue creating a user account - please try again");
    $response->send();
    exit();
  }

    $lastUserID = $writeDB->lastInsertId();

    $returnData = array();
    $returnData['user_id'] = $lastUserID;
    $returnData['fullname'] = $fullname;
    $returnData['username'] = $username;

    $response = new Response();
    $response->setHttpStatusCode(201);
    $response->setSucess(true);
    $response->addMessage("User created");
    $response->setData($returnData);
    $response->send();
    exit();
}

catch(PDOException $ex){
  error_log("Database query error: ".$ex, 0);
  $response = new response();
  $response->setHttpStatusCode(400);
  $response->setSucess(false);
  $response->addMessage("There was an issue creating a user account - Please try again");
  $response->send();
  exit();
}
