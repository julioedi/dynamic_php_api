<?php
function defineGlobals(array $data){
  foreach ($data as $key => $name) {
    if (!defined($name)) {
      define($key,$name);
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
class App{
  private $response = [];
  private $code = 404;
  public $process_string_num = true;
  public $constants = [];
  public $dynamic_routes = [];
  public function __construct(){
    $this->rootPath();
  }
  public $is_pre = true;



  /**---------------------------------------------------------------------------
  * check to load file if exist in route path
  ---------------------------------------------------------------------------*/
  private function exist_file_dir(string $path){
    if (file_exists("$path.php")) {
      require_once "$path.php";
      return true;
    }
    elseif (is_dir($path) && file_exists("$path/index.php")) {
        require_once "$path/index.php";
        return true;
    }
    return false;
  }

  /**---------------------------------------------------------------------------
  * load files inside routes folder
  ---------------------------------------------------------------------------*/
  public function load_routes($path = null):void {
    $basePath = !$path ? REQUEST : $path;
    $path = __DIR__ . "/" . $basePath;
    $exists = $this->exist_file_dir($path);
    if ($exists) {
      return;
    }
    $this->load_dynamic_routes();
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
  private function load_dynamic_routes():void{
    if (empty($this->dynamic_routes)) {
      return;
    }

    foreach ($this->dynamic_routes as $regex => $options) {
      if (!is_callable($options["callback"] ?? null)) {
        continue;
      }
      $regMethod = mb_strtolower($options["method"]);
      $currentMethod = mb_strtolower($_SERVER["REQUEST_METHOD"]);
      if ($regMethod != "all" && $regMethod != $currentMethod) {
        continue;
      }
      $reg = "#^" . $regex . "$#i";
      // echo json_encode([$reg]);
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
      $options["callback"]($args);
      // echo json_encode($args,JSON_PRETTY_PRINT);
    }
  }


  /**---------------------------------------------------------------------------
  * add a route based on regex
  ---------------------------------------------------------------------------*/
  public function add_route($regex = "", callable|null $callback = null, array $options = array()):void{
    if (isset($this->dynamic_routes[$regex])) return;
    $default_options = array(
      "method" => "ALL",
      "args" => true,
    );
    $options = array_merge($default_options,$options);
    $this->dynamic_routes[$regex] = array_merge(
      $options,
      array(
        "callback" => $callback ? $callback : "__false",
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
      "ABSPATH" => $this->clean_route(__DIR__),

      //current relative path to server
      "HOME"    => $this->clean_route(dirname($_SERVER["SCRIPT_NAME"])),
    );
    $this->constants = $data;

    defineGlobals($data);
    $request = $this->route_base();
    $this->constants["REQUEST"] = $request;
    defineGlobals(array(
      "REQUEST" => $request
    ));
  }


}
$app = new App();
$app->add_route("routes/(?<slug>\w+)",function($args){
  echo "perra madre";
});

/*
public function load_files($path = "loads"){

  // $dir = __DIR__ . "/$path"; // Define the base directory
  // $files = scandir($dir); // Get all files in the directory
  // $paths = [];
  // $pre = $this->is_pre ? true : false;
  // foreach ($files as $file) {
  //   if ($file == "." || $file == "..") {
  //     continue;
  //   }
  //   $filePath = $dir . '/' . $file; // Build the full file path
  //   echo "$filePath\n";
  //   if (is_dir($filePath)) {
  //     // If it's a directory, call load_routes recursively
  //     $this->load_routes($path . '/' . $file); // Pass the relative subdirectory
  //   } elseif (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
  //     // If it's a PHP file, require it
  //     require_once $filePath;
  //   }
  // }
}
*/

echo "<pre>";
// print_r($app->constants);
print_r($_SERVER);
print_r($app->dynamic_routes);
$app->load_routes();
echo "</pre>";
