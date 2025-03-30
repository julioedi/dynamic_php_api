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
] as $value) {
  require_once __DIR__ . "/$value.php";
}

class App{
  use Response;
  use DB;
  public $process_string_num = true;
  public $constants = [];
  public $dynamic_routes = [];
  public function __construct(){
    $this->rootPath();
    $this->get_env();
  }
  public $is_pre = true;



  /**---------------------------------------------------------------------------
  * check to load file if exist in route path
  ---------------------------------------------------------------------------*/
  private function exist_file_dir(string $path){
    $basePath =  __DIR__ . "/routes";
    $route = "{$basePath}$path";
    if (file_exists("$route.php")) {
      require_once "$route.php";
      return true;
    }
    elseif (is_dir($route) && file_exists("$route/index.php")) {
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
    $path = !$path ? REQUEST : $path;
    $exists = $this->exist_file_dir($path);
    if ($exists) {
      return;
    }
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

      // echo $path;
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
    return $uri;
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
$app->add_route("user/(?<id>\d+)","user/id");
$app->add_route("user/(?<slug>[\w.-]+)","user/id");
// $app->load_routes();
// $app->connect();
$app->createTable("posts");
// echo "<pre>";
// print_r($app);
