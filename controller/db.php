<?php

//<!-- Remember that Code Igniter Framework can be used to help deal with other security issues -->

class DB {

  private static $writeDBConnection;
  private static $readDBConnection;

  public static function connectWriteDB(){

    //Since it is static and not an instance of the class then 'self::' is used
    if(self::$writeDBConnection === null)

      //PDO allows to quickly switch databases easily (Although queries can format can change)
      self::$writeDBConnection = new PDO('mysql:host=localhost;dbname=tasksdb;charset=utf8', 'root', '');
      self::$writeDBConnection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); //setting exception error mode
      self::$writeDBConnection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false); //not all db's can handle prepared statements, therefore, this attemps to emulate them (mySQL handles prepared statements natively, therefore, this is false)

      return self::$writeDBConnection;
  }

  public static function connectReadDB(){

    //Since it is static and not an instance of the class then 'self::' is used
    if(self::$readDBConnection === null)

      //PDO allows to quickly switch databases easily (Although queries can format can change)
      self::$readDBConnection = new PDO('mysql:host=localhost;dbname=tasksdb;charset=utf8', 'root', '');
      self::$readDBConnection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); //setting exception error mode
      self::$readDBConnection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false); //not all db's can handle prepared statements, therefore, this attemps to emulate them (mySQL handles prepared statements natively, therefore, this is false)

      return self::$readDBConnection;
  }

  //Note: If different servers were available to read and write then PDO would contain different values

}
 ?>
