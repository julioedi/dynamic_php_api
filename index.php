<?php
function defineGlobals(array $data, App &$app = null){
  foreach ($data as $key => $name) {
    if (!defined($name)) {
      define($key,$name);
      if ($app) {
        $app->constants[$key] = $name;
      }
    }
  }
}

function __false(){
  return false;
}
function __true(){
  return true;
}
function __null(){
  return null;
}

foreach ([
  "response",
  "db",
  "sqlcalls",
  "encoder",
  "utils"
] as $value) {
  require_once __DIR__ . "/$value.php";
}

class App{
  use Encoder;
  use Response;
  use DB;
  use SQLCalls;
  use Utils;
  public $process_string_num = true;
  public $constants = [];
  public $dynamic_routes = [];
  public $is_pre = true;
  public function __construct(){
    $this->method = $_SERVER["REQUEST_METHOD"] ?? "GET";
    $this->rootPath();
    $this->get_env();
    $this->connect();

    //avoid direct dynamic files
    $file = ROUTES . "/" . REQUEST . ".php";
    if (!preg_match("/\/index$/i",REQUEST)) {
      $file = str_replace(["[","]"],"",$file);
      if (file_exists($file)) {
        require_once $file;
      }
    }
    $this->scanRoutes();
    $this->load_routes();
    $this->print();
  }

  public function clean_slug(string|int $slug): string {
    // Convert to string and trim whitespace from beginning and end
    $slug = trim((string) $slug);

    // Convert all whitespace characters (spaces, tabs, etc.) to dashes
    $slug = preg_replace('/\s+/', '-', $slug);

    // Replace all non-alphanumeric characters (except dash and underscore) with dashes
    $slug = preg_replace('/[^a-zA-Z0-9\-_]/', '-', $slug);

    // Replace multiple dashes or underscores with a single dash
    $slug = preg_replace('/[-_]+/', '-', $slug);

    // Trim dashes from beginning and end
    $slug = trim($slug, '-');

    // Convert to lowercase
    return mb_strtolower($slug);
  }

  public function exists_slug(string $tablename,string $slug):false|string{
    $slug = $this->clean_slug($slug);
    $exists = $this->get_element_by_slug($tablename,$slug);
    //slug dont exists
    if ( !$exists ) {
      return false;
    }

    $reg = "^{$slug}-[0-9]+$";
    $tablename = "{$this->db_prefix}$tablename";
    $sql = "SELECT * FROM `$tablename` WHERE `slug` REGEXP '$reg'";
    $rows = $this->get_query_rows($sql);
    if (empty($rows)) {
      return "$slug-0";
    }
    $newNum = 1;
    foreach ($rows as $row) {
      $num =  preg_replace("/^.*?-([0-9]+)/","$1",$row["slug"]);
      if ($num >= $newNum) {
        $newNum = $num + 1;
      }
    }
    return "$slug-{$newNum}";


  }


  public function compareArray(array $element,array $extras,string $key){
    $first = $element;
    if (is_array($extras[$key] ?? null)) {
      $first = array_merge($first,$extras[$key]);
      $first = array_unique($first);
    }elseif (is_string($extras[$key] ?? null)) {
      $first[] = $extras[$key];
    }
    return $first;
  }
  public $phpfileExtReg = "/\.php$/i";
  private function php_name_file($filedir):string|null{
    if (preg_match($this->phpfileExtReg,$filedir)) {
      return preg_replace($this->phpfileExtReg,"",$filedir);
    }
    return null;
  }

  private function name_to_regex($filedir, array $prevMatches = array()):false|array{
    $file = mb_strtolower($filedir);
    preg_match($this->dyanmicFileNamesReg,$filedir,$matches);

    //if filename is not like [first,second].php
    if (empty($matches)) {
      return false;
    }
    $type = $matches["type"] === "d" ? "[0-9]+" : "([A-Za-z][A-Za-z0-9_-]+|[A-Za-z])";
    $names = explode(",",$matches["name"]);
    $ret = array(
      "type" => $type,
      "names" => $names,
      "filedir" => $filedir,
      "regex" => array(),
    );
    if (count($names) > 1) {
      foreach ($names as $v) {
          // Dynamically create the regex pattern for each name
        if (!empty($prevMatches)) {
          foreach ($prevMatches as $prev) {
              $ret["regex"][] = "$prev/$v";
          }
        }else{
          $ret["regex"][] = $v;
        }
      }
    }
    elseif (count($names) === 1) {
      $reg = "(?<{$names[0]}>{$type})";
      if (!empty($prevMatches)) {
        foreach ($prevMatches as $prev) {
            $ret["regex"][] = "$prev/$reg";
        }
      }else{
        $ret["regex"][] = $reg;
      }
    }
    return $ret;

  }

  private function match_url(string $regex,$file):bool{
    if (preg_match("/^[a-z0-9_-]+$/",$regex)) {
      $regex = "(?<page>$regex)";
    }
    elseif (preg_match("/^([a-z0-9_\-]+)\/\(/",$regex)) {
      $regex = preg_replace("/^([a-z0-9_\-]+)/","(?<page>$1)",$regex);
    }
    $reg = "#^" . $regex . "$#i";
    if (!preg_match("#^" . $regex . "$#i",REQUEST)) {
      return false;
    }
    return $this->exist_file_dir($file,$reg);
  }

  public $dyanmicFileNamesReg = "/^\[(?<name>\w+(?:,\w+)*)\](?<type>\w|$)/i";

  private function scanRoutes($path = "",$prevMatches = []):bool{
    $def_route = !empty($path) ?  ROUTES . "/$path" : ROUTES;
    $def_route = preg_replace("/^\/+/","",$def_route);
    if (!is_dir($def_route)) {
      return false;
    }
    $dir = scandir($def_route);
    foreach ($dir as $filedir) {
      //prevent relative paths
      if ($filedir == "." || $filedir == ".." || $filedir == "index.php") continue;
      $base = "$def_route/$filedir";


      //recursive scan subdirectories
      if (is_dir($base)) {
        $dyanmic = $this->name_to_regex($filedir);
        //detect if file is for dynamic url
        if ($dyanmic) {
          $tmp = array_merge($prevMatches,$dyanmic["regex"]);
          //check for multiple params
          foreach ($dyanmic["regex"] as $tmpreg) {
            $tmpMatch = $prevMatches;
            $tmpMatch[] = $tmpreg;
            $exists = $this->match_url($tmpreg,"$path/$filedir");
          }
        }else{
          $exists = $this->match_url($filedir,"$path/$filedir");
          $tmp = array_merge($prevMatches,[$filedir]);
        }
        $exist = $this->scanRoutes("$path/$filedir",$tmp);

        if ($exist) {
          return true;
        }
        continue;
      }


      $fileName = $this->php_name_file($filedir);
      $dyanmic = $this->name_to_regex($fileName,$prevMatches);
      if ($dyanmic) {
        // $tmp = array_merge($prevMatches,$dyanmic["regex"]);
        //check for multiple params
        foreach ($dyanmic["regex"] as $tmpreg) {
          $tmpMatch = $prevMatches;
          $tmpMatch[] = $tmpreg;
          $exists = $this->match_url($tmpreg,"$path/$fileName");
        }
      }
      else{
        $exists = $this->match_url($filedir,"$path/$fileName");
        $tmp = array_merge($prevMatches,[$filedir]);
        return $exists;
      }
    }
    return false;
  }



  /**---------------------------------------------------------------------------
  * check to load file if exist in route path
  ---------------------------------------------------------------------------*/
  private function exist_file_dir(string $path, string $reg = " "){
    $basePath =  ROUTES;
    $route = "{$basePath}/$path";
    if (file_exists("$route.php")) {
      $this->code = 200;
      $this->response = array();
      $this->request_regex = $reg;
      require_once "$route.php";
      return true;
    }
    elseif (is_dir($route) && file_exists("$route/index.php")) {
        $this->code = 200;
        $this->response = array();
        $this->request_regex = $reg;
        require_once "$route/index.php";
        return true;
    }
    return false;
  }

  /**---------------------------------------------------------------------------
  * load files inside routes folder
  ---------------------------------------------------------------------------*/
  public function load_routes($path = null):void {

    $el = $this->load_dynamic_routes();
    if ($el) {
      return;
    }
    // $path = !$path ? REQUEST : $path;
    // $exists = $this->exist_file_dir($path);
    // if ($exists) {
    //   return;
    // }
    return;
  }

  private function process_query_get($el = null){
    $data = $el ? $el : $_GET;
    foreach ($data as $key => &$value) {
      if (is_array($value)) {
        $value = $this->process_query_get($value);
        continue;
      }
      if (is_numeric($value)) {
        $value = (float) $value;
        continue;
      }
      if (preg_match("/^(null|false|true)$/i",$value)) {
        $value = mb_strtolower($value);
      }
      switch ($value) {
        case 'true':
          $value = true;
          break;
        case 'false':
          $value = false;
          break;
        case 'null':
          $value = null;
          break;

        default:
          // code...
          break;
      }

    }
    return $data;
  }

  private function load_dynamic_routes():bool{
    if (empty($this->dynamic_routes)) {
      return false;
    }

    foreach ($this->dynamic_routes as $regex => $options) {
      $path = ROUTES . "/" . ($options["callback"] ?? null) . ".php";

      $regMethod = mb_strtolower($options["method"]);
      $currentMethod = mb_strtolower($_SERVER["REQUEST_METHOD"]);
      if ($regMethod != "all" && $regMethod != $currentMethod) {
        continue;
      }
      $reg = "#^" . $regex . "$#i";
      preg_match_all($reg,REQUEST,$matches);
      if (empty($matches[0])) {
        continue;
      }

      if ($options["query_num"] ?? null) {
        $this->process_string_num = $options["query_num"];
      }
      $args = [
        "reg" => $reg,
        "restapi" => $matches[0][0],
        "params" => [],
        "query" => $this->process_query_get(),
      ];
      foreach ($matches as $key => $value) {
        if (is_numeric($key)) continue;
        $args["params"][$key] = $value[0];
      }
      $this->response = [];
      // $options["callback"]($args);
      require_once $path;
      return true;
    }
    return false;
  }


  /**---------------------------------------------------------------------------
  * add a route based on regex
  ---------------------------------------------------------------------------*/
  public function add_route($regex = "", string|null $callback = null, array $options = array()):void{
    if (isset($this->dynamic_routes[$regex])) return;
    $default_options = array(
      "method" => "ALL",
      "args" => true,
    );
    if (!$callback) {
      $callback = "index";
    }
    $path = ROUTES . "/" . ($callback ?? null);
    //make a replative path to
    if (!file_exists("$path.php")) {
      return;
    }
    $options = array_merge($default_options,$options);
    $this->dynamic_routes[$regex] = array_merge(
      $options,
      array(
        "callback" => $callback ? $callback : "index",
      )
    );
  }


  /**---------------------------------------------------------------------------
  * Define global consts
  ---------------------------------------------------------------------------*/
  private function clean_route(string $route):string{
    $route = preg_replace("/(\/|\\\\)+/i","/",$route);
    return $route;
  }

  private function route_base():string{
    $uri = $this->clean_route($_SERVER["REQUEST_URI"]);

    //remove query params
    $uri = preg_replace("/\?.*$/","",$uri);

    //remove last slash
    $uri = preg_replace("/\/$/","",$uri);
    $reg = str_replace("/","\\/",HOME);
    $reg = "/^$reg/";
    $uri = preg_replace($reg,"",$uri);
    $uri = preg_replace("/^\//","",$uri);
    return $uri;
  }



  public function RandomID($totalChars = 16,$length = 1,) {
   $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
   $res = [];
   for ($i=0; $i < $length; $i++) {
     $res[] = substr(str_shuffle($characters), 0, $totalChars);
   }
   return implode("-",$res);
  }

  /**---------------------------------------------------------------------------
  * Define global consts
  ---------------------------------------------------------------------------*/
  private function rootPath(){
    $data = array(
      //root apache folder where is located the script
      "ROOT"    => $this->clean_route($_SERVER["DOCUMENT_ROOT"] ?? $_SERVER["DOCUMENT_ROOT"]),

      //script located folder
      "APIPATH" => $this->clean_route(__DIR__),

      //current relative path to server
      "HOME"    => $this->clean_route(dirname($_SERVER["SCRIPT_NAME"])),
    );

    defineGlobals($data,$this);
    $request = $this->route_base();
    $routes = APIPATH . "/routes";
    defineGlobals(array(
      "REQUEST" => $request,
      "ROUTES" => $routes
    ),$this);
  }


}
$app = new App();
