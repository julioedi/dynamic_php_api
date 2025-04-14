<?php
/**
 *
 */
trait Response{
  private $response = null;
  public $code = 200;
  public $error = null;
  public $request_regex = "";
  public $statusCodes = [
    100 => 'Continue',
    101 => 'Switching Protocols',
    200 => 'OK',
    201 => 'Created',
    202 => 'Accepted',
    203 => 'Non-Authoritative Information',
    204 => 'No Content',
    205 => 'Reset Content',
    206 => 'Partial Content',
    300 => 'Multiple Choices',
    301 => 'Moved Permanently',
    302 => 'Moved Temporarily',
    303 => 'See Other',
    304 => 'Not Modified',
    305 => 'Use Proxy',
    400 => 'Bad Request',
    401 => 'Unauthorized',
    402 => 'Payment Required',
    403 => 'Forbidden',
    404 => 'Not Found',
    405 => 'Method Not Allowed',
    406 => 'Not Acceptable',
    407 => 'Proxy Authentication Required',
    408 => 'Request Time-out',
    409 => 'Conflict',
    410 => 'Gone',
    411 => 'Length Required',
    412 => 'Precondition Failed',
    413 => 'Request Entity Too Large',
    414 => 'Request-URI Too Large',
    415 => 'Unsupported Media Type',
    500 => 'Internal Server Error',
    501 => 'Not Implemented',
    502 => 'Bad Gateway',
    503 => 'Service Unavailable',
    504 => 'Gateway Time-out',
    505 => 'HTTP Version not supported'
  ];
  public function http_response_code(string|null $code = NULL):void{
    if (!$code || !isset($this->statusCodes["$code"])) {
      $code = 200;
    }
  }
  public function getEndpoints(){
    $list = $this->tables;
    $endpoints = new stdClass();
    $endpoints->possible_endpoints = new stdClass();
    $endpoints->possible_querys = array(
      "s" => array(
        "type" => ["string"],
        "desc" => "Search element",
        "default" => "",
      ),
      "order_by" => array(
        "type" => ["string"],
        "desc" => "key of the object to order",
        "default" => "updated",
      ),
      "order" => array(
        "type" => ["string"],
        "desc" => array(
          "A-Z" => "Order elements by name",
          "ASC" => "Ascendant order",
          "DESC" => "Descendant order",
        ),
        "default" => "DESC",
      ),
      "fields_in" => array(
        "type" => ["string","array"],
        "desc" => "Only will get back this fields as object keys",
        "default" => "",
      ),
      "fields_out" => array(
        "type" => ["string","array"],
        "desc" => "if 'fields_in' is active, this option will be excluded",
        "default" => "",
      ),
    );
    foreach ($list as $key => $value) {
      $endpoints->possible_endpoints->$key = (object) array(
        "all" => $this->clean_route(HOME . "/$key"),
        "by_id" => $this->clean_route(HOME . "/$key/{id}"),
        "by_slug" => $this->clean_route(HOME . "/$key/{slug}"),
      );
    }
    return $endpoints;
  }

  public function print_json(array|object $json){

    $this->headers();
    $this->close_connection();
    $json = json_encode($json, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    // if (function_exists("gzencode")) {
    //   try {
    //     header("Content-Encoding: gzip");
    //     echo gzencode($json);
    //     die();
    //   } catch (\Exception $e) {
    //     echo $json;
    //     die();
    //   }
    //
    // }else{
      echo $json;
    // }
    die();
  }

  private function headers() {
    $code = $this->code;
    $message = $this->statusCodes["$code"];
    $protocol = $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.0';
    header($protocol . ' ' . $code . ' ' . $message);
    $GLOBALS['http_response_code'] = $code;

    // Content headers
    header('Content-Type: application/json; charset=utf-8');
    $name = empty(REQUEST) ? "empty" : preg_replace("/[^a-z0-9_-]/i", "_", REQUEST);
    header("Content-Disposition: inline; filename=\"$name.json\"");
    header('Color-Scheme: dark');

    // --- CORS Headers ---
    // Allow credentials like cookies
    header('Access-Control-Allow-Credentials: true');

    // Handle allowed origin
    if (isset($_SERVER['HTTP_ORIGIN'])) {
        $origin = $_SERVER['HTTP_ORIGIN'];

        // Optional: add an allowed origins whitelist
        $allowed_origins = ['http://localhost:3000'];

        if (in_array($origin, $allowed_origins)) {
            header("Access-Control-Allow-Origin: $origin");
        }

        // Set Access-Control-Max-Age to cache preflight response
        header('Access-Control-Max-Age: 86400'); // 1 day
    }

    // Optional: handle preflight OPTIONS request (move this outside if you call headers() inside every endpoint)
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        exit(0);
    }

    // Security cleanup headers
    foreach (["X-Powered-By", "Server", "Date"] as $value) {
        header_remove($value);
    }
}


  public function print_error(string|null $message = null, int $code = 404,mixed $response = null){
    if (empty($message)) {
      $message = $this->statusCodes[$code] ?? "Error";
    }
    $this->code = $code;
    $this->error = true;
    $this->error_message = $message;
    $this->response = $response;
    $this->print();
  }

  public function print(){
    $code = $this->code;
    if (empty(REQUEST)) {
      $this->response = $this->getEndpoints();
    }
    else if (!is_array($this->response) && !is_object($this->response) && !isset($this->error_message)) {
      $this->print_error("No restPoint available");
    }

    $json = array(
      "code" => $code,
      "request" => REQUEST,
      "data" => $this->response,
    );
    if (isset($this->error)) {
      $json["error"] = $this->error;
    }

    if (isset($this->error_message)) {
      $json["error_message"] = $this->error_message;
    }
    $this->print_json($json);
    die();
  }
}
