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
      // echo $ee;
      return null;
    }
  }
  private function get_query_rows(string $sql,array $extras = array(), null|string $tablename = null){
    $list = $this->query($sql);
    if (!$list) {
      return null;
    }
    foreach ($list as &$value) {
      $value = $this->process_row($value,$extras ,$tablename);
    }
    return $list;
  }

  /** --------------------------------------------------------------------------
  * @return array gets the data provided by a form body content in fetch/curl
  *---------------------------------------------------------------------------*/
  public function get_body(): array {
    if (!empty($_POST)) {
        return $_POST;
    }

    $data = file_get_contents("php://input");
    try {
        $data = trim($data);
        if (empty($data)) {
            return [];
        }

        // Decode JSON into associative array
        $decoded = json_decode($data, true);

        // Check if JSON decoding succeeded
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [];
        }

        // Ensure it's an array
        return is_array($decoded) ? $decoded : [];
    } catch (\Exception $e) {
        return [];
    }
}



  /** --------------------------------------------------------------------------
  * @return bool check if token match for Create,Update,Delete
  *---------------------------------------------------------------------------*/
    public function validateToken(): ?array {
      if (isset($this->user_token)) {
        return $this->user_token;
      }
      if (!isset($_COOKIE["account_data"])) {
          return null;
      }

      $cookie = $_COOKIE["account_data"];
      $decoded = $this->decode($cookie);

      if (!is_array($decoded)) {
          return null;
      }

      // Optional: Check for required keys
      // if (!isset($decoded['user_id'])) return null;
      $this->user_token = $decoded;
      return $decoded;
  }

  public function processToken():void{
    $token = $this->validateToken();
    if (!$token) {
      $this->print_error(null,401);
      return;
    }
  }




  /** --------------------------------------------------------------------------
  * @return
  *---------------------------------------------------------------------------*/
  public function generateToken(array|string $data,string $key = ""):array|string{
    if (is_array($data)) {
      $data = serialize($data);
    }
    $data = $this->encode($data);
    if (empty($key)) {
      return $data;
    }
    return array(
      "key" => $key,
      "value" => $data,
    );
  }


  /** --------------------------------------------------------------------------
  * @return
  *---------------------------------------------------------------------------*/
  public function generateUserToken(string $key,string $value,string $password, int $days = 30):array{
    $data = array(
      "key" => $key,
      "value" => $value,
      "password" => $password,
    );
    $token = $this->encode($data,"account_data");
    // $this->addCookie("account_data",$token,$days);
    return array(
      "key" => "account_data",
      "token" => $token,
    );
  }

  public function deleteCookie(string $cookieName){
    if (isset($_COOKIE["$cookieName"])) {
      $days = time() - (3600 * 24 * 30); // (segs in hour) * (hours in day) * ( total days );
      setcookie($cookieName, "", $days);
    }
  }

  public function addCookie(string $name = "", string|array|int $value, $days = 30 ){
    $this->deleteCookie($name);
    $days = time() + (3600 * 24 * $days); // (segs in hour) * (hours in day) * ( total days );
    if (is_int($value)) {
      $value = (string) $value;
    }
    setcookie($name, $value, $days);
  }


  private function get_table_elements(array|null $ids,string $tablename,bool|array $process_json_tables = true):array{
    // return $ids;
    if (empty($ids)) {
      return $ids;
    }
    $list = [];
    $preList = [];
    $process = null;

    //check if default rendered tables got json elements
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

    //limit the search to max element ids selected
    $sql = $this->select_sql_string($tablename,array("LIMIT" => count($ids)),array(
      "by_column" => array(
        "name" => "ID",
        "value" =>  $ids,
      ),
    ));
    $data = $this->query($sql[0]);
    $colData = $this->tables[$tablename];

    //if is a child element row, will use default table fro process keys to fow
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
    return $ids;
  }



  private function process_rows(array $rows, array $extras = array(), null|string $tablename = null){
    foreach ($rows as &$row) {
      $row = $this->process_row($row,$extras,$tablename);
    }
    return $rows;
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
    $sql = $this->select_sql_string($tablename,array("LIMIT" => 1),array(
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
    $sql = $this->select_sql_string($tablename,array("LIMIT" => 1),array(
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

  private function check_ids(string|int|array $id):int|false|array{
    if (is_string($id) && !is_numeric($id)) {
      return false;
    }
    if (is_array($id)) {
      $str = [];
      foreach ($id as $value) {
        if (is_numeric($value)) {
          $str[] = $value;
        }
      }
      if (empty($str)) {
        return false;
      }
      return $str;
    }
    return (int) $id;
  }
  public function table_name(string $tablename){
    $preTablename = preg_replace("/[^a-z0-9_]/i","",$tablename);
    $realTablename = "{$this->db_prefix}$preTablename";
    return $preTablename;
  }
  /** --------------------------------------------------------------------------
  * Update elements
  *---------------------------------------------------------------------------*/
  public function validate_slug(string $tablename, string $slug){

    $slug = preg_replace("/\s+/","-",$slug);
    $slug = preg_replace("/[^a-z0-9_\-]/i","",$slug);
    $slug = mb_strtolower($slug);
    $post = $this->get_element_by_slug($tablename,$slug);
    if ($post) {
      // code...
    }

    $realTablename = $this->table_name($tablename);
    $query = "SELECT slug FROM `$realTablename` WHERE slug REGEXP '^$slug-[0-9]+$'";
  }



  /** --------------------------------------------------------------------------
  * Update elements
  *---------------------------------------------------------------------------*/
  private function insert_sql_row(string $tablename,string $title = "", string $slug = "", array $values = array()){
    $error = [];
    $title = trim($title);
    $slug = trim($slug);
    if (empty($title) && empty($slug)) {
      $this->print_error("Title or slug required",206);
      return;
    }
    if (empty($slug)) {
      $slug = "$title";
    }

    //create a friendly slug
    $slug = preg_replace("/\s+/","-",$slug);
    $slug = preg_replace("/[^a-z0-9_\-]/i","",$slug);
    $slug = mb_strtolower($slug);

    if (empty($title)) {
      $title = ucfirst(preg_replace("/(-|_)/"," ",$slug));
    }

    $stringvals = [];
    $keyvals = [];

    $keyvals = [];
    $stringvals = [];
    $processed = [];
    $preparetype = "ss";
    $exclude_keys = array_merge($this->db_defaults,[]);
    if (isset($exclude_keys["status"]) ) {
      unset($exclude_keys["status"]);
    }
    if (isset($exclude_keys["featured_id"]) ) {
      unset($exclude_keys["featured_id"]);
    }
    $exclude_keys = array_keys($exclude_keys);

    $exclude_keys = implode("|",$exclude_keys);

    $reg = "/^($exclude_keys)$/i";
    foreach (
      $values as
      $key //column name
      => $value //value to update will process based on element type
    ){
      $key = trim($key);
      //prevent custom update date;
      if (preg_match("/^($exclude_keys)$/i",$key)) {
        continue;
      }
      if ($value == null) {
        $value = "";
      }
      if (is_array($value)) {
        $value = serialize($value);
      }
      if (is_numeric($value)) {
        $value = (string) $value;
      }
      $processed[] = "`$value`";
      $stringvals[] = "?";
      $preparetype .="s";
      $keyvals[] = $key;
    }

    $preTablename = preg_replace("/[^a-z0-9_]/i","",$tablename);
    $realTablename = "{$this->db_prefix}$preTablename";

    $keyvals = !empty($keyvals) ? "," . implode(",",$keyvals) : "";
    $stringvals = !empty($stringvals) ? "," . implode(",",$stringvals) : "";

    $sql = "INSERT into `$realTablename` (title,slug$keyvals) VALUES (?,?$stringvals)";

    $stmt = $this->db->prepare($sql);

    // Check if the statement was prepared successfully
    if ($stmt === false) {
        die("Error preparing statement: " . $conn->error);
    }
    $stmt->bind_param($preparetype,$title,$slug,...$processed);  // This will unpack the array and pass its elements as parameters

    $processed = false;
    $error = null;
    try {
      $stmt->execute();
      $processed = true;
    } catch (\Exception $e) {
      $error = $stmt->error;
    }

    if ($error) {
      if (preg_match("/'slug'/i",$stmt->error)) {
        $this->print_error("Element with slug '$slug' already exists",409);
      }
      $this->print_error($stmt->error,404);
      return;
    }
    $stmt->close();

    if ($processed) {
      $post = $this->get_element_by_slug($tablename,$slug);
      if ($post) {
        $this->print_json(array(
          "data" => $post,
          "error" => null,
        ));
      }
      else{
        $this->code = 500;
        $this->print_json(array(
          "data" => null,
          "error" => "Error trying to get created element with slug \"$slug\"",
        ));
      }
    }
  }


  /** --------------------------------------------------------------------------
  * Update elements
  *---------------------------------------------------------------------------*/
  private function update_sql_row(string $tablename, string|int|array $id = 0,array $values = array()){

    //will not accept not numbers as id
    $id = $this->check_ids($id);
    if (!$id) {
      return array(
        "sql" => null,
        "values" => null,
      );
    }


    if (is_array($id)) {
      $where = "ID in (" . implode(",",$id) . ")";
    }else{
      $where = "ID = $id";
    }

    $stringvals = [];
    $keyvals = [];
    $preparetype = "";
    foreach (
      $values as
      $key //column name
      => $value //value to update will process based on element type
    ){
      $key = trim($key);
      //prevent custom update date;
      if (preg_match("/^(updated|updated_by)$/i",$key)) {
        continue;
      }
      if ($value == null) {
        $value = "";
      }
      if (is_array($value)) {
        $value = serialize($value);
      }
      if (is_numeric($value)) {
        $value = (string) $value;
      }
      $stringvals[] = $value;
      $preparetype .="s";
      $keyvals[] = "$key = ?";
    }

    //if no values to update, will not process the query string;
    if (empty($stringvals)) {
      return array(
        "sql" => null,
        "values" => null,
      );
    }


    $tablename = preg_replace("/[^a-z0-9_]/i","",$tablename);
    $tablename = "{$this->db_prefix}$tablename";
    $keyvals = implode(", ",$keyvals);

    $sql = "UPDATE `$tablename` SET $keyvals, `updated` = NOW() WHERE $where;";
    $stmt = $this->db->prepare($sql);

    // Check if the statement was prepared successfully
    if ($stmt === false) {
        die("Error preparing statement: " . $conn->error);
    }
    $stmt->bind_param($preparetype, ...$stringvals);  // This will unpack the array and pass its elements as parameters

    if ($stmt->execute()) {
      echo "Array stored successfully!";
    } else {
      echo "Error storing array: " . $stmt->error;
    }
    $stmt->close();



    return array(
      "sql" => $sql,
      "values" => $stringvals,
      "types" => $preparetype
    );
  }

  /** --------------------------------------------------------------------------
  * @return string[]
  *                 [0] Call rows SQL
  *                 [1] sql to get total rows with params
  *---------------------------------------------------------------------------*/
  private function __select_sql_string(string $tablename, array $params = array(), array $extras = array(), string $call = "SELECT"){
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

    return [
      $sql,
      $total,
    ];
  }

  private function by_column(array|string $by_column):string{
    if (is_string($by_column)) {
      return $by_column;
    }
    $single = [];
    $value = $by_column["value"] ?? null;
    $colName = $by_column["name"] ?? null;
    $iterator = $by_column["OR"] ?? true;
    $iterator = $iterator ? "OR" : "AND";
    if (is_string($colName) && $value) {
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
            $single[] = "$colName in ($value)";
          }
        }
      }else{
        $value = $this->scape($value);
        $value = $this->scape($value);
        $single[] = "`$colName` = '$value'";
      }
    }
    foreach ($by_column as $key => $value) {
      if (is_array($value)) {
        if (is_numeric($key)) {
          $single[] = $this->by_column($value);
        }
        continue;
      }
    }
    return implode(" $iterator ",$single);
  }

  /** --------------------------------------------------------------------------
  * @return string[]
  *                 [0] Call rows SQL
  *                 [1] sql to get total rows with params
  *---------------------------------------------------------------------------*/
  private function select_sql_string(string $tablename, array $params = array(), array $extras = array(), string $call = "SELECT"){
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
    $pre = $this->by_column($extras["by_column"] ?? "");
    if (!empty($pre)) {
      $where[] = $pre;
    }

    $by_col = !empty($where);

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
      $iterator = $extras["or"] ?? null;
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

    return [
      $sql,
      $total,
    ];
  }

  public function pagination(string|null $tablename = null, array $extras = array()){
    if (empty($tablename)) {
      $uriArgs = $this->getArgs();
      if (isset($uriArgs["page"])) {
        $tablename = $uriArgs["page"];
      }else{
        $base = preg_replace("/^.*?\/([a-z0-9\-_]+)/i","$1",REQUEST);
        $base = mb_strtolower($base);
        $tablename = $base;
      }
    }

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


    $sql = $this->select_sql_string($tablename,array(
      "limit" => $per_page,
      "offset" => $page > 1 ? ($page * $per_page) : null,
    ),$extras);

    $preTotal = $this->query($sql[1]);
    $total = 0;
    if ( !empty($preTotal) ) {
      $total = (int) $preTotal[0][0];
    }

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
      $list = [];
    }

    $this->print_json(array(
      "table" => $tablename,
      "current_page" => $page,
      "pages" => $pages,
      "per_page" => $per_page,
      "total" => $total,
      "list" => $list
    ));
  }
}
