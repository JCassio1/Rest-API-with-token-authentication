<?php

require_once('db.php');
require_once('../model/response.php');

try{
  $writeDB = DB::connectWriteDB();
}

catch(PDOException $ex){
  error_log("Connection error".$ex, 0);
  $response = new response();
  $response->setHttpStatusCode(500);
  $response->setSucess(false);
  $response->addMessage("Database Connection Error");
  $response->send();
  exit();
}

if(array_key_exists("sessionId", $_GET)){


}

elseif(empty($_GET)){

  if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    $response = new response();
    $response->setHttpStatusCode(405);
    $response->setSucess(false);
    $response->addMessage("Request method not allowed #");
    $response->send();
    exit();
  }

  //Prevent brute-force attacks (limit to 1 attack per second by adding a delay)
  sleep(1);

  if($_SERVER['CONTENT_TYPE'] !== 'application/json'){
    $response = new response();
    $response->setHttpStatusCode(405);
    $response->setSucess(false);
    $response->addMessage("Content Type header not set to JSON");
    $response->send();
    exit();
  }

  $rawPostData = file_get_contents('php://input');

  if(!$jsonData = json_decode($rawPostData)){
    $response = new response();
    $response->setHttpStatusCode(400);
    $response->setSucess(false);
    $response->addMessage("Request body is not valid JSON");
    $response->send();
    exit();
  }

  if(!isset($jsonData->username) || !isset($jsonData->password)){
    $response = new response();
    $response->setHttpStatusCode(400);
    $response->setSucess(false);
    (!isset($jsonData->username) ? $response->addMessage("Username not supplied") : false);
    (!isset($jsonData->password) ? $response->addMessage("Password not supplied") : false);
    $response->send();
    exit();
  }

  if(strlen($jsonData->username) < 1 || strlen($jsonData->username) > 255 || strlen($jsonData->password) < 1 || strlen($jsonData->password) > 255){
    $response = new response();
    $response->setHttpStatusCode(400);
    $response->setSucess(false);
    (strlen($jsonData->username) < 1 ? $response->addMessage("Username cannot be blank") : false);
    (strlen($jsonData->username) > 255 ? $response->addMessage("Username must be less than 255 characters") : false);
    (strlen($jsonData->password) < 1 ? $response->addMessage("Password cannot be blank") : false);
    (strlen($jsonData->password) > 255 ? $response->addMessage("Password must be less than 255 characters") : false);
    $response->send();
    exit();
  }

  try{

    $username = $jsonData->username;
    $password = $jsonData->password;

    $query = $writeDB->prepare('select id, fullname, username, password, useractive, loginattempts from users where username = :username');
    $query->bindParam(':username', $username, PDO::PARAM_STR);
    $query->execute();

    $rowCount = $query->rowCount();

    if($rowCount === 0) {
      $response = new response();
      $response->setHttpStatusCode(401);
      $response->setSucess(false);
      $response->addMessage("Username or password is incorrect");
      $response->send();
      exit();
    }

    $row = $query->fetch(PDO::FETCH_ASSOC);
    $returned_id = $row['id'];
    $returned_fullname = $row['fullname'];
    $returned_username = $row['username'];
    $returned_password = $row['password'];
    $returned_useractive = $row['useractive'];
    $returned_loginattempts = $row['loginattempts'];

    if($returned_useractive !== 'Y'){
      $response = new response();
      $response->setHttpStatusCode(401);
      $response->setSucess(false);
      $response->addMessage("User account not active");
      $response->send();
      exit();
    }

    if($returned_loginattempts >= 3) {
      $response = new response();
      $response->setHttpStatusCode(401);
      $response->setSucess(false);
      $response->addMessage("User account is currently locked out");
      $response->send();
      exit();
    }

    if(!password_verify($password, $returned_password)){
      $query = $writeDB->prepare('update users set loginattempts = loginattempts+1 where id = :id');
      $query->bindParam(':id', $returned_id, PDO::PARAM_INT);
      $query->execute();

      $response = new response();
      $response->setHttpStatusCode(401);
      $response->setSucess(false);
      $response->addMessage("Username or password is incorrect");
      $response->send();
      exit();
    }

    //Generates random bytes to be used by access/refresh token and be passed in http header
    $accesstoken = base64_encode(bin2hex(openssl_random_pseudo_bytes(24)).time());
    $refreshtoken = base64_encode(bin2hex(openssl_random_pseudo_bytes(24)).time());

    $access_token_expiry_seconds = 1200; //20 minutes
    $refresh_token_expiry_seconds = 1209600; //14 days
  }

  catch(PDOException $ex){
    $response = new response();
    $response->setHttpStatusCode(500);
    $response->setSucess(false);
    $response->addMessage("There was an issue logging in");
    $response->send();
    exit();
  }

  try {
    //This creates a transaction to make sure all data modification is successful (ATOMIC)
    $writeDB->beginTransaction();
    $query = $writeDB->prepare('update users set loginattempts = 0 where id = :id');
    $query->bindParam(':id', $returned_id, PDO::PARAM_INT);
    $query->execute();

    $query = $writeDB->prepare('insert into sessions (userId, acessToken, acessTokenExpiry, refreshToken, refreshTokenExpiry) values (:userid, :accesstoken, date_add(NOW(), INTERVAL :accesstokenexpiryseconds SECOND), :refreshtoken, date_add(NOW(), INTERVAL :refreshtokenexpiryseconds SECOND))');
    $query->bindParam(':userid', $returned_id, PDO::PARAM_INT);
    $query->bindParam(':accesstoken', $accesstoken, PDO::PARAM_STR);
    $query->bindParam(':accesstokenexpiryseconds', $access_token_expiry_seconds, PDO::PARAM_INT);
    $query->bindParam(':refreshtoken', $refreshtoken, PDO::PARAM_STR);
    $query->bindParam(':refreshtokenexpiryseconds', $refresh_token_expiry_seconds, PDO::PARAM_INT);
    $query->execute();

    //Session id will also be used to logout of account
    $lastSessionID = $writeDB->lastInsertId();

    $writeDB->commit();

    $returnData = array();
    $returnData['session_id'] = intval($lastSessionID);
    $returnData['access_token'] = $accesstoken;
    $returnData['access_token_expires_in'] = $access_token_expiry_seconds; //should always be in $access_token_expiry_seconds
    $returnData['refresh_token'] = $refreshtoken;
    $returnData['refresh_token_expires_in'] = $refresh_token_expiry_seconds;

    $response = new response();
    $response->setHttpStatusCode(201);
    $response->setSucess(true);
    $response->setData($returnData);
    $response->send();
    exit();

  }

   catch(PDOException $ex) {
     $writeDB->rollBack();
     $response = new response();
     $response->setHttpStatusCode(500);
     $response->setSucess(false);
     $response->addMessage("There was an issue logging in - please try again");
     $response->send();
     exit();
  }
}

else{
  $response = new response();
  $response->setHttpStatusCode(404);
  $response->setSucess(false);
  $response->addMessage("Endpoint not valid");
  $response->send();
  exit();
}


 ?>
