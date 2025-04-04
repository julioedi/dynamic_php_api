<?php

/**
 *
 */
trait SQLCalls
{
  public $post_per_page = 12;
  public $max_post_per_page = 100;
  public $numeric_db_keys = ["ID","created_by","updated_by","status","featured_id"];
  public $pagination_exclude = ["created","updated_by"];
  public $search_columns = ["title","slug"];



  /** --------------------------------------------------------------------------
  * @return array|null run the sql call, if dont work will return null
  *---------------------------------------------------------------------------*/
  private function query(string $sql):array|null{
    if (!$this->db) {
      if ($this->db !== 0) {
        return null;
      }else{
        $this->connect();
      }
    }
    try {
      $list = [];
      $data = $this->db->query($sql);
      if ($data->num_rows > 0) {
        while ($row = $data->fetch_array()) {
          $list[] = $row;
        }
      }
      return $list;
    } catch (\Exception $e) {
      echo $e;
      return null;
    }
  }

  /** --------------------------------------------------------------------------
  * @return array gets the data provided by a form body content in fetch/curl
  *---------------------------------------------------------------------------*/
  public function get_body():array{
    if (!empty($_POST)) {
      return $_POST;
    }
    $data = file_get_contents("php://input");
    if (empty($data)) {
      return $data;
    }
    try {
      $data = json_decode($data);
      return $data;
    } catch (\Exception $e) {
      return array();
    }

  }


  /** --------------------------------------------------------------------------
  * @return bool check if token match for Create,Update,Delete
  *---------------------------------------------------------------------------*/
  public function validateToken(string $token = ""):array|bool|null{
    // return array();
    return true;
  }


  private function get_table_elements(array|null $ids,string $tablename,bool|array $process_json_tables = true):array{
    // return $ids;
    if (empty($ids)) {
      return $ids;
    }
    $list = [];
    $preList = [];
    $process = null;
    if ($process_json_tables) {
      if (!empty($ids)) {
        if (is_array($process_json_tables)) {
          if (!empty($process_json_tables)) {
            $process = (in_array($tablename,$process_json_tables) && isset($this->tables[$tablename]));
          }
        }else{
          $process = isset($this->tables[$tablename]);
        }
      }
    }
    if (!$process) {
      return $ids;
    }

    $sql = $this->create_sql_string($tablename,array("LIMIT" => count($ids)),array(
      "by_column" => array(
        "name" => "ID",
        "value" =>  $ids,
      ),
    ));
    $data = $this->query($sql[0]);
    $colData = $this->tables[$tablename];
    if (is_array($colData["fields_in_child"] ?? null)) {
      $colData["fields_in"] = $colData["fields_in_child"];
    }
    foreach ($data as $key => &$value) {
      $prelist[$value["ID"]] = $this->process_row($data[$key],$colData,$tablename);
    }

    foreach ($ids as $key => &$value) {
      if (isset($prelist[$value])) {
        $value = $prelist[$value];
      }else{
        unset($prelist[$value]);
      }
    }
    // echo "<pre>";
    // print_r($colData);
    // die();

    // return $this->process_row($data[0],$extra);

    return $ids;
  }

  public function update_item(string $tablename,array $columns, array $where, string $operator ="OR"):array{
    if (!$this->validateToken()) {
      return array(
        "error" => "No enougth permissions",
        "updated" => 0,
        "data" => null,
      );
    }
    $colsString = [];
    $whereString = [];
    if (empty($columns)) {
      return array(
        "error" => "No columns",
        "updated" => 0,
        "data" => null,
      );
    }
    if (empty($where)) {
      return array(
        "error" => "No validate values",
        "updated" => 0,
        "data" => null,
      );
    }
    foreach ($columns as $column => $value) {
      if (is_object($value) || is_array($value)) {
        $value = serialize($value);
      }
      elseif (is_bool($value) || $value == null) {
        $value = $value ? "true" : "false";
      }
      if (preg_match("/password/",$column)) {
        $value = md5($value);
      }
      $value =  $this->scape($value);
      $colsString[] = "SET `$column` = '$value'";
    }


    foreach ($where as $column => $value) {
      if (is_object($value) || is_array($value) || is_bool($value) || $value == null) {
        unset($where[$column]);
        continue;
      }
      $value =  $this->scape($value);
      $whereString[] = "`$column` = '$value'";
    }

    if (empty($column)) {
      return array(
        "error" => "Invalid validate values",
        "updated" => 0,
        "data" => null,
      );
    }

    $sql = "UPDATE `{$this->db_prefix}$tablename`\n" . implode(", ",$colsString) . "\nWHERE " . implode("$operator ",$whereString) . ";";
    try {
      $data = $this->db->query($sql);
      return array(
        "error" => null,
        "updated" => $data,
        "data" => array(
          "updated" => $columns,
          "match_values" => $where,
        ),
      );
    } catch (\Exception $e) {
      return array(
        "error" => "Bad sql call",
        "updated" => 0,
        "data" => array(
          "sql" => $sql
        ),
      );
    }

    // -- SET ContactName = 'Alfred Schmidt', City = 'Frankfurt'
    // -- WHERE CustomerID = 1;";
  }


  private function process_row($row, array $extras = array(), null|string $tablename = null){
    $item = new stdClass();

    $tableData = array();
    if ($tablename && isset($this->tables[$tablename])) {
      $tableData = $this->tables[$tablename];
    }

    if (is_array($extras["int"] ?? null)) {
      $intkeys = array_merge($this->numeric_db_keys,$extras["int"]);
      $intkeys = array_unique($intkeys);
    }else{
      $intkeys = $this->numeric_db_keys;
    }

    //default tables params for int fields
    if ( is_array($tableData["int"] ?? null) ) {
      $intkeys = array_merge($tableData["int"],$intkeys);
      $intkeys = array_unique($intkeys);
    }


    $json_keys = array();
    if (is_array($extras["json"] ?? null)) {
      $json_keys = $extras["json"];
    }

    //default tables params for json_fields
    if ( is_array($tableData["json"] ?? null) ) {
      $json_keys = array_merge($tableData["json"],$json_keys);
      $json_keys = array_unique($json_keys);
    }


    $process_json_tables = $extras["process_json_tables"] ?? true;

    foreach ($row as $key => $value) {
      //exlude regular results
      if (is_numeric($key)) continue;
      //prevent direct access from api to passwords
      if (preg_match("/password/i",$key)) {
        continue;
      }


      //try
      if (in_array($key,$intkeys)) {
        try {
          $item->$key = empty($value) ? 0 : (int) $value;
        } catch (\Exception $e) {
          $item->$key = 0;
        }
        continue;
      }

      if ( in_array($key,$json_keys) ) {
        try {
          if (empty($value)) {
            $item->$key = null;
          }else{
            $item->$key = unserialize($value);
            if (!empty($item->$key) && $process_json_tables) {
              $item->$key = $this->get_table_elements($item->$key,$key,$process_json_tables);
            }
          }

        } catch (\Exception $e) {
          $item->$key = null;
        }
        continue;
      }

      $item->$key = $value;
    }

    $preRow = [];
    $fields_in = 0;
    if (isset($extras["fields_in"])) {
      foreach ($extras["fields_in"] as $key) {
        if (isset($item->$key)) {
          $fields_in++;
          $preRow[$key] = $item->$key;
        }
      }
    }

    if (empty($preRow)) {
      $preRow = (array) $item;
    }

    if ($fields_in == 0 && isset($extras["exclude"])) {
      foreach ($extras["exclude"] as $key) {
        try {
            unset($preRow[$key]);
        } catch (\Exception $e) {
            continue;
        }

      }
    }



    return $preRow;
  }


  /** --------------------------------------------------------------------------
  * @return array of columns to exclude form query
  *---------------------------------------------------------------------------*/
  private function exclude_fields(array $extras = array()){
    $exclude_keys = array();
    if (is_array($extras["exclude"] ?? null)) {
      $exclude_keys = $extras["exclude"];
    }

    $exclude_keys = $this->compareArray($exclude_keys,$_GET,"exclude");
    return $exclude_keys;
  }
  private function fields_in(array $params = array()){
    $fields_in = array();
    $fields_in = $this->compareArray($fields_in,$params,"fields_in");
    $fields_in = $this->compareArray($fields_in,$_GET,"fields_in");
    return $fields_in;
  }


  private function scape(string $string){
    if ($this->mysqlStatus == 0  || !$this->db) {
      return "";
    }
    return $this->db->real_escape_string($string);
  }



  public function getArgs(){
    if (empty($this->request_regex)) {
      return array();
    }
    preg_match($this->request_regex,REQUEST,$matches);
    foreach ($matches as $key => $value) {
      if (is_numeric($key)) {
        unset($matches[$key]);
      }
    }
    return $matches;
  }

  /** --------------------------------------------------------------------------
  * @return array|null
  *---------------------------------------------------------------------------*/
  private function get_element_by_id(string $tablename,string|int $id,array $extra = array()):array|null{
    $sql = $this->create_sql_string($tablename,array("LIMIT" => 1),array(
      "by_column" => array(
        "name" => "ID",
        "value" =>  $id,
      ),
    ));
    $data = $this->query($sql[0]);
    if (empty($data)) {
      return null;
    }
    return $this->process_row($data[0],$extra,$tablename);
  }


  /** --------------------------------------------------------------------------
  * @return array|null
  *---------------------------------------------------------------------------*/
  private function get_element_by_slug(string $tablename,string $slug,array $extra = array()):array|null{
    $sql = $this->create_sql_string($tablename,array("LIMIT" => 1),array(
      "by_column" => array(
        "name" => "slug",
        "value" =>  $slug,
      ),
    ));
    $data = $this->query($sql[0]);
    if (empty($data)) {
      return null;
    }
    return $this->process_row($data[0],$extra,$tablename);
  }



  /** --------------------------------------------------------------------------
  * @return string[]
  *                 [0] Call rows SQL
  *                 [1] sql to get total rows with params
  *---------------------------------------------------------------------------*/
  private function create_sql_string(string $tablename, array $params = array(), array $extras = array()){
    $tablename = preg_replace("/[^a-z0-9_]/i","",$tablename);
    $from = "from `{$this->db_prefix}$tablename`";

    $where = [];
    $s = "";
    if (isset($params["s"])) {
      $s = $params["s"];
    }
    else if (isset($_GET["s"])){
      $s = $_GET["s"];
    }
    $by_col = false;
    if (is_array($extras["by_column"] ?? null)) {
      $value = $extras["by_column"]["value"] ?? null;
      $colName = $extras["by_column"]["name"] ?? null;
      if ( is_string($colName) && $value ) {
        $by_col = true;
        $colName = preg_replace("/[^a-z0-9_]/i","",$colName);
        if (is_array($value)) {
          $values_list = [];
          foreach ($value as $el) {
            if (is_string($el) || is_numeric($el)) {
              $values_list[] = !is_numeric($el) ? "`$el`" : $el;
            }
          }

          if (!empty($values_list)) {
            $value = $this->scape(implode(",",$values_list));
            if (!empty($value)) {
              $where[] = "$colName in ($value)";
            }
          }
        }else{
          $value = $this->scape($value);
          $where[] = "$colName='$value'";
        }
      }
    }

    if (!$by_col && !empty($s)) {
      $s = $this->scape($s);
      $s = addslashes($s);
      $s = trim($s);
      $sCols = $this->compareArray($this->search_columns,$extras,"search_columns");
      foreach ($sCols as $value) {
        $where[] = $this->scape($value) . " LIKE '%$s%'";
      }
    }

    if (!empty($where)) {
      $where = " WHERE " . implode(" OR ", $where);
    }else{
      $where = "";
    }

    $limit = "";
    if (is_int($params["limit"] ?? "null")) {
      $limit = "LIMIT {$params["limit"]}";
    }


    if (is_int($params["offset"] ?? "null")) {
      $limit .= " OFFSET {$params["offset"]}";
    }

    $total = "SELECT COUNT(*) $from{$where}";

    $sql = "SELECT * $from{$where} $limit";
    // echo $sql;
    //
    // die();
    return [
      $sql,
      $total,
    ];
  }

  public function pagination(string $tablename, array $extras = array()){
    $this->response = [$tablename];

    $per_page = $_GET["per_page"] ?? "{$this->post_per_page}";
    if (!is_numeric($per_page)) {
      $per_page = 12;
    }else{
      $per_page = (int) $per_page;
    }

    //Limit the max elements per page
    $per_page = $per_page > $this->max_post_per_page ? $this->max_post_per_page : $per_page;
    if ($per_page < 1) {
      $per_page = $this->post_per_page;
    }


    $page = $_GET["page"] ?? "1";
    if (!is_numeric($page)) {
      $page = 1;
    }else{
      $page = (int) $page;
    }

    //Limit the max elements per page
    if ($page < 1) {
      $page = 1;
    }


    $intkeys = $this->compareArray($this->numeric_db_keys,$extras,"int");
    $exclude_keys = $this->compareArray($this->pagination_exclude,$extras,"exclude");


    $json_keys = array();
    if (is_array($extras["json"] ?? null)) {
      $json_keys = $extras["json"];
    }
    $status = null;
    if (is_numeric($extras["status"] ?? null)) {
      $status = (int) $extras["status"];
    }



    $exclude_keys = $this->exclude_fields(array("exclude" => $exclude_keys));



    $parsed_keys = array(
      "int"       => $intkeys,
      "exclude"   => $exclude_keys,
      "json"      => $json_keys,
      "status"    => $status,
      "fields_in" => $this->fields_in($extras),
    );


    $sql = $this->create_sql_string($tablename,array(
      "limit" => $per_page,
      "offset" => $page > 1 ? ($page * $per_page) : null,
    ),$extras);

    $total = $this->query($sql[1]);
    $total = $total ? count($total) : 0;

    $list = [];
    $pages = ceil($total / $per_page );


    //prevents to select data if are more pages
    if ($page <= $pages) {
      $data = $this->query($sql[0]);
      if ($data) {
        foreach ($data as $row) {
          $list[] = $this->process_row($row,$parsed_keys,$tablename);
        }
      }

    }else{
      $list = ["muajaja"];
    }

    $this->response = array(
      "current_page" => $page,
      "pages" => $pages,
      "per_page" => $per_page,
      "total" => $total,
      "list" => $list
    );
  }
}
