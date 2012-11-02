<?php

class BaseObject {
  public $dbConn;
  public $id;

  protected $modelTable;
  protected $modelPlural;

  public function __construct($database, $user_id=Null) {
    $this->dbConn = $database;
  }
  public function __get($property) {
    // A property accessor exists
    if (method_exists($this, $property)) {
      return $this->$property();
    } elseif (property_exists($this, $property)) {
      return $this->$property;
    }
  }
  private function humanizeParameter($parameter) {
    // takes a parameter name like created_at
    // returns a human-friendly name like createdAt
    $paramParts = explode("_", $parameter);
    $newName = $paramParts[0];
    foreach (array_slice($paramParts, 1) as $part) {
      $newName .= ucfirst($part);
    }
    return $newName;
  }
  public function getInfo() {
    $info = $this->dbConn->queryFirstRow("SELECT * FROM `".$this->modelTable."` WHERE `id` = ".intval($this->id)." LIMIT 1");
    foreach ($info as $key=>$value) {
      $paramName = $this->humanizeParameter($key);
      if (is_numeric($value)) {
        $value = ( (int) $value == $value ? (int) $value : (float) $value);
      }
      $this->$paramName = $value;
    }
  }
  public function returnInfo($param) {
    if ($this->$param === Null) {
      $this->getInfo();
    }
    return $this->$param;
  }
 }
?>