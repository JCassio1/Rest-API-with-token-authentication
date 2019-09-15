<?php

require_once('response.php');

$response = new Response();
$response->setSucess(true);
$response->setHttpStatusCode(200);
$response->addMessage("First Text Ok");
$response->addMessage("Second Text Ok");
$response->send();

 ?>
