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

//Makes sure that sessionid exists in the header
if(array_key_exists("sessionid", $_GET)){

  $sessionid = $_GET['sessionid'];

  if($sessionid === '' || !is_numeric($sessionid)){
    $response = new response();
    $response->setHttpStatusCode(400);
    $response->setSucess(false);
    ($sessionid === '' ? $response->addMessage("Session ID cannot be blank") : false);
    (!is_numeric($sessionid) ? $response->addMessage("Session ID must be numeric") : false);
    $response->send();
    exit();
  }

  if(!isset($_SERVER['HTTP_AUTHORIZATION']) || strlen($_SERVER['HTTP_AUTHORIZATION']) < 1)
  {
    $response = new response();
    $response->setHttpStatusCode(401);
    $response->setSucess(false);
    (!isset($_SERVER['HTTP_AUTHORIZATION']) ? $response->addMessage("Access token is missing from the header") : false);
    (strlen($_SERVER['HTTP_AUTHORIZATION']) < 1 ? $response->addMessage("Access token cannot be blank") : false);
    $response->send();
    exit;
  }

  $accesstoken = $_SERVER['HTTP_AUTHORIZATION'];

  if($_SERVER['REQUEST_METHOD'] === 'DELETE') {

      try {
        $query = $writeDB->prepare('delete from sessions where id = :sessionid and acessToken = :accesstoken');
        $query->bindParam(':sessionid', $sessionid, PDO::PARAM_INT);
        $query->bindParam(':accesstoken', $accesstoken, PDO::PARAM_STR);
        $query->execute();

        $rowCount = $query->rowCount();

        if ($rowCount === 0) {
          $response = new response();
          $response->setHttpStatusCode(400);
          $response->setSucess(false);
          $response->addMessage("Failed to log out of this session via access token");
          $response->send();
          exit();
        }

        $returnData = array();
        $returnData['session_id'] = intval($sessionid);

        $response = new response();
        $response->setHttpStatusCode(200);
        $response->setSucess(true);
        $response->addMessage("Logged out successfully");
        $response->setData($returnData);
        $response->send();
        exit();
      }

      catch (PDOException $ex) {
        $response = new response();
        $response->setHttpStatusCode(500);
        $response->setSucess(false);
        $response->addMessage("There was an issue login out. Please try again");
        $response->send();
        exit();
      }
  }

  elseif ($_SERVER['REQUEST_METHOD'] === 'PATCH') {

    if ($_SERVER['CONTENT_TYPE'] !== 'application/json') {
      $response = new response();
      $response->setHttpStatusCode(400);
      $response->setSucess(false);
      $response->addMessage("Content type data not set to JSON");
      $response->send();
      exit();
    }

    $rawPatchData = file_get_contents('php://input');

    if (!$jsonData = json_decode($rawPatchData)) {
      $response = new response();
      $response->setHttpStatusCode(405);
      $response->setSucess(false);
      $response->addMessage("Request body is not valid JSON");
      $response->send();
      exit();
    }

    if (!isset($jsonData->refresh_token) || strlen($jsonData->refresh_token) < 1) {
      $response = new response();
      $response->setHttpStatusCode(405);
      $response->setSucess(false);
      (!isset($jsonData->refresh_token) ? $response->addMessage("Refresh token not supplied") : false);
      (strlen($jsonData->refresh_token) < 1 ? $response->addMessage("Refresh token cannot be blank") : false);
      $response->send();
      exit();
    }

    try{

      $refreshtoken = $jsonData->refresh_token;

      //Performing a join on table sessions and users
      $query = $writeDB->prepare('select sessions.id as sessionid, sessions.userId as userid, acessToken, refreshToken, useractive, loginattempts, acessTokenExpiry, refreshTokenExpiry from sessions, users where users.id = sessions.userId and sessions.id = :sessionid and sessions.acessToken = :accesstoken and sessions.refreshToken = :refreshtoken');
      $query->bindParam(':sessionid', $sessionid, PDO::PARAM_INT);
      $query->bindParam(':accesstoken', $accesstoken, PDO::PARAM_STR);
      $query->bindParam(':refreshtoken', $refreshtoken, PDO::PARAM_STR);
      $query->execute();

      $rowCount = $query->rowCount();

      if($rowCount === 0) {
        $response = new response();
        $response->setHttpStatusCode(401);
        $response->setSucess(false);
        $response->addMessage("Access token or refresh token is incorrect for session id");
        $response->send();
        exit();
      }

      $row = $query->fetch(PDO::FETCH_ASSOC);

      $returned_sessionid = $row['sessionid'];
      $returned_userid = $row['userid'];
      $returned_accesstoken = $row['acessToken'];
      $returned_refreshtoken = $row['refreshToken'];
      $returned_useractive = $row['useractive'];
      $returned_loginattempts = $row['loginattempts'];
      $returned_accesstokenexpiry = $row['acessTokenExpiry'];
      $returned_refreshtokenexpiry = $row['refreshTokenExpiry'];


      if ($returned_useractive !== 'Y') {
        $response = new response();
        $response->setHttpStatusCode(401);
        $response->setSucess(false);
        $response->addMessage("User account is not active");
        $response->send();
        exit();
      }

      if ($returned_loginattempts >= 3) {
        $response = new response();
        $response->setHttpStatusCode(401);
        $response->setSucess(false);
        $response->addMessage("Login account is currently locked");
        $response->send();
        exit();
      }

      if (strtotime($returned_refreshtokenexpiry) < time()) {
        $response = new response();
        $response->setHttpStatusCode(401);
        $response->setSucess(false);
        $response->addMessage("Refresh token has expired. Please login again");
        $response->send();
        exit();
      }

      $accesstoken = base64_encode(bin2hex(openssl_random_pseudo_bytes(24)).time());
      $refreshtoken = base64_encode(bin2hex(openssl_random_pseudo_bytes(24)).time());

      $access_token_expiry_seconds = 1200; //20 minutes
      $refresh_token_expiry_seconds = 1209600; //14 days

      $query = $writeDB->prepare('update sessions set acessToken = :accesstoken, acessTokenExpiry = date_add(NOW(), INTERVAL :accesstokenexpiryseconds SECOND), refreshToken = :refreshtoken, refreshTokenExpiry = date_add(NOW(), INTERVAL :refreshtokenexpiryseconds SECOND) where id = :sessionid and userId = :userid and acessToken = :returnedaccesstoken and refreshToken = :returnedrefreshtoken');
      $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
      $query->bindParam(':sessionid', $returned_sessionid, PDO::PARAM_INT);
      $query->bindParam(':accesstoken', $accesstoken, PDO::PARAM_STR);
      $query->bindParam(':accesstokenexpiryseconds', $access_token_expiry_seconds, PDO::PARAM_INT);
      $query->bindParam(':refreshtoken', $refreshtoken, PDO::PARAM_STR);
      $query->bindParam(':refreshtokenexpiryseconds', $refresh_token_expiry_seconds, PDO::PARAM_INT);
      $query->bindParam(':returnedaccesstoken', $returned_accesstoken, PDO::PARAM_STR);
      $query->bindParam(':returnedrefreshtoken', $returned_refreshtoken, PDO::PARAM_STR);
      $query->execute();

      $rowCount = $query->rowCount();

      if ($rowCount === 0) {
        $response = new response();
        $response->setHttpStatusCode(401);
        $response->setSucess(false);
        $response->addMessage("Access token could not be refreshed. Please login again");
        $response->send();
        exit();
      }

      $returnData = array();
      $returnData['session_id'] = $returned_sessionid;
      $returnData['access_token'] = $accesstoken;
      $returnData['access_token_expiry'] = $access_token_expiry_seconds;
      $returnData['refresh_token'] = $refreshtoken;
      $returnData['refresh_token_expiry'] = $refresh_token_expiry_seconds;

      $response = new response();
      $response->setHttpStatusCode(200);
      $response->setSucess(true);
      $response->addMessage("Token refreshed");
      $response->setData($returnData);
      $response->send();
      exit();
    }

    catch(PDOException $ex){
      $response = new response();
      $response->setHttpStatusCode(500);
      $response->setSucess(false);
      $response->addMessage("There was an issue refreshing access token. Please login again");
      $response->send();
      exit();
    }
  }

  else {
    $response = new response();
    $response->setHttpStatusCode(405);
    $response->setSucess(false);
    $response->addMessage("Request Method not allowed");
    $response->send();
    exit();
  }
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
    $response->addMessage("Content Type header not set to JSON #ErrorCode600");
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
