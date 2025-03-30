<?php
/**
 *
 */
trait Response{
  private $response = null;
  public $code = 200;
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
  public function print(){
    $code = $this->code;
    if (!$this->response) {
      $code = 404;
      $this->response = array(
        "message" => "No restPoint available "
      );
    }
    $message = $this->statusCodes["$code"];
    $protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');
    header($protocol . ' ' . $code . ' ' . $message);
    $GLOBALS['http_response_code'] = $code;
    header('Content-Type: application/json; charset=utf-8');
    header("Content-Encoding: gzip");
    foreach ([
      "X-Powered-By",
      "Server",
      "Date"
    ] as $value) {
      header_remove($value);
    }
    echo gzencode(json_encode(array(
      "code" => $code,
      "data" => $this->response,
    )));
    if ($this->db) {
      $this->db->close();
      echo "closed connection";
    }
    die();
  }
}
