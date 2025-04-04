<?php

/**
 *
 */
trait DB
{
  /**
  * connection codes
  *-2 = Not minimal data for env file
  *-1 = no env file with mysql data
  * 0 = not started connection
  * 1 = connection successs
  * 2 = connection error
  */
  public $mysqlStatus = 0;
  public $db = null;
  public $db_error = null;
  public $env = null;
  public $charset = "utf8mb4";
  public $db_prefix = "fnb_";
  public $collation = "utf8mb4_general_ci";
  public $charset_collate = null;
  public $tables = array();
  public $db_optionals = array(
    "date"      => "datetime NOT NULL default CURRENT_TIMESTAMP",
    "longText"  => "text NOT NULL default ''",
    "shortText" => "varchar(20) NOT NULL default ''",
    "code_0"      => "int(11) NOT NULL default 0",
    "code_1"      => "int(11) NOT NULL default 1",
  );
  public $db_defaults = array(
      "ID"            => "bigint(20) unsigned NOT NULL auto_increment",
      "title"         => "text NOT NULL default ''",
      "slug"          => "varchar(200) NOT NULL default ''",
      "created"       => "datetime NOT NULL default CURRENT_TIMESTAMP",
      "created_by"    => "int(11) NOT NULL default 0",
      "updated"       => "datetime NOT NULL default CURRENT_TIMESTAMP",
      "updated_by"    => "int(11) NOT NULL default 0",
      "status"        => "int(11) NOT NULL default 1",
      "featured_id"   => "int(11) NOT NULL default 0",
  );
  public $db_stringDefaults = "";

  public function update_env(string $key, string $value){
    $reg = "#($key)\=($value)#i";
    $lines = file($env, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  }
  public function get_env(){
    //if is already called .env, will return the data;
    if ($this->env) {
      return $this->env;
    }
    //check for .env file, if dont exists will not connect
    $env = APIPATH . "/.env";
    if (!file_exists($env)) {
      $this->mysqlStatus = -1;
      return;
    }

    $params = array();
    $lines = file($env, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Ignore lines that start with '#' (comments)
        if (strpos($line, '#') === 0) {
            continue;
        }
        // Split the line by the '=' sign to separate key and value
        list($key, $value) = explode('=', $line, 2);
        $params[trim($key)] = trim($value);
        putenv("$key=$value");
    }
    $this->env = $params;

    //create a random password if dont exists that will be use as param for tokens
    if (empty( $params["ENCODE_PSW"] ?? null )) {
      $content = file_get_contents($env);
      $id = $this->RandomID(24);
      $content .= "\rENCODE_PSW=$id";
      $this->encode_id = $id;
      file_put_contents($env, $content);
    }else{
      $this->encode_id = $params["ENCODE_PSW"];
    }
    return $params;
  }

  public function connect():void{
    if ($this->mysqlStatus !== 0) {
      return;
    }

    //get .env file params;
    $params = $this->get_env();

    if (  !isset($params["DB_HOST"]) || !isset($params["DB_USERNAME"]) || !isset($params["DB_PASSWORD"]) || !isset($params["DB_NAME"])) {
      $this->mysqlStatus = -2;
      return;
    }
    $host = $params['DB_HOST'];
    $username = $params['DB_USERNAME'];
    $password = $params['DB_PASSWORD'];
    $dbname = $params['DB_NAME'];
    if (isset($params["DB_PREFIX"])) {
      //prevent special characters and spaces
      $this->db_prefix = preg_replace("/[^a-z0-9_]/i","",$params["DB_PREFIX"]);
    }
    if (substr($this->db_prefix,-1) != "_") {
      $this->db_prefix = $this->db_prefix . "_";
    }


    //conntect to db and prevents print on error;
    try {
      $conn = new mysqli($host, $username, $password, $dbname);
      $this->mysqlStatus = 1;
      $this->charset = $conn->character_set_name();
      $this->collation = $conn->query("SHOW VARIABLES LIKE 'collation_database'")->fetch_assoc()['Value'];
      $this->db = $conn;

      //
      $tables = $conn->query("SHOW TABLES");
      if ($tables->num_rows > 0) {
        while ($row = $tables->fetch_array()) {
          $name = preg_replace($this->prefix_reg(),"",$row[0]);
          $this->tables[$name] = true; // Table names are in the first column
        }
      }
      $this->default_tables();
    } catch (\Exception $error) {
      $this->mysqlStatus = 2;
      $this->db_error = $error;
    }
  }

  public function prefix_reg(){
    return "#^" . $this->db_prefix . "#i";
  }

  /** --------------------------------------------------------------------------
  * @return string $sql of default columns for tables
  *---------------------------------------------------------------------------*/
  private function get_defaults( bool $custom = false){
    if ($custom) {
      return "ID bigint(20) unsigned NOT NULL auto_increment,\n";
    }
    if (!empty($this->stringDefaults)) {
      return $this->stringDefaults;
    }
    $out = '';
    foreach ($this->db_defaults as $key => $value) {
      $out .= "`$key` $value,\n";
    }
    // $out = implode("",$out);
    $this->stringDefaults = $out;
    return $out;

  }
  public function charset_collate(){
    return " CHARACTER SET {$this->charset} COLLATE {$this->collation}";
  }

  /** ----------------------------------------------------------------------------
  * Create a table based on default and parsed data
  * @param string $tableName
  *
  * codes
  *-1 = no mysql connection
  * 0 = table already exists
  * 1 = table created
  * 2 = error while creating tables
  *----------------------------------------------------------------------------*/
  public function createTable(string $tableName, array $preData = array()):int{
    if (!$this->db && $this->mysqlStatus === 0 ) {
      $this->connect();
    }
    if ($this->mysqlStatus !== 1) {
      return -1;
    }
    if (is_array($this->tables[$tableName] ?? null)) {
      return 0;
    }

    $custom = isset($preData['custom']);
    if (isset($this->tables[$tableName]) && !is_array($this->tables[$tableName]) ) {
      $this->tables[$tableName] = $preData;
      return 0;
    }

    $data = $preData['cols'] ?? array();

    $charset_collate = $this->charset_collate();
    $tableName = $this->db_prefix . $tableName;
    $sql = "CREATE TABLE IF NOT EXISTS `$tableName` ";
    $sql .= "(\n";

    $sql .= $this->get_defaults($custom);
    foreach ($data as $key => $value) {
      if (!is_string($value)) continue;
      if (is_numeric($value)) continue;
      if (!isset($this->db_optionals[$value])) continue;
      if (!$custom && isset($this->db_defaults[$key])) continue;
      $pre = $key . $value;
      if (preg_match("/(\"|\(|\)|\,)/",$pre)) continue;

      $sql .= "`$key` {$this->db_optionals[$value]},\n";

    }
    $sql .= "PRIMARY KEY  (ID)";
    if (!$custom) {
      $sql .= ",\nUNIQUE (slug)\n";//UNIQUE
    }else if(is_array($preData["unique"] ?? null)){
      foreach ($preData["unique"] as $value) {
        if (is_string($value) && isset($data[$value])) {
          $sql .= "UNIQUE ($value)\n";//UNIQUE
        }
      }
    }
    $sql .= ")";
    $sql .= $charset_collate . ";";
    // echo "<pre>";
    // print_r($sql);
    // die();
    try {
      $val = $this->db->query($sql);
      if ($val) {
        return 1;
      }else{
        return 2;
      }
    } catch (\Exception $e) {
      return 2;
    }

  }

  /** ----------------------------------------------------------------------------
  * Check if db is connected and inited
  *----------------------------------------------------------------------------*/
  public function is_connected():bool{
    if ( $this->mysqlStatus === 0 ) {
      $this->connect();
    }
    return $this->mysqlStatus === 1;
  }

  /** ----------------------------------------------------------------------------
  * SQl;
  *----------------------------------------------------------------------------*/
  public function getElementByID(string $table_name,string|int $id):object|null{
    if (!is_numeric($id) || !$this->is_connected()) {
      return null;
    }

  }

  /** ----------------------------------------------------------------------------
  * close connection to db before end the print json;
  *----------------------------------------------------------------------------*/
  public function close_connection():void{
    if (!$this->db) {
      return;
    }
    $this->db->close();
  }

  public function default_tables():void{
    $tablesPath = APIPATH . "/tables";
    if (!is_dir($tablesPath)) {
      return;
    }
    $tables = scandir($tablesPath);
    $reg = "/^(.*?)\.php$/i";
    foreach ($tables as $tableFile) {
      if (!preg_match($reg,$tableFile)) {
        continue;
      }
      $name = preg_replace($reg,"$1",$tableFile);
      $data = require "$tablesPath/$tableFile";
      $data = !is_array($data ?? "") ? array() : $data;
      if (is_array($data ?? "")) {
         $this->createTable($name,$data);
      }
      // code...
    }
  }
}
