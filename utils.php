<?php

/**
 *
 */
trait Utils
{

  public $headers = array();
  public $raw_headers = array();
  /** --------------------------------------------------------------------------
  * @return array if headers are not processed, will process and return
  *---------------------------------------------------------------------------*/
  public function get_headers(bool $raw = false):array{
    if (empty($this->headers)) {
      $this->headers = (array) getallheaders();
      $this->raw_headers = $this->headers;
      foreach ($this->headers as $headerName => &$headerVal) {
        switch ($headerName) {
          case 'Accept':
            $headerVal = explode(";",$headerVal);
            break;
          case 'Accept-Encoding':
            $headerVal = preg_replace("/,\s+/",",",$headerVal);
            $headerVal = explode(",",$headerVal);
            break;
          case 'sec-ch-ua':
            $pre = preg_replace("/(,\s+|,\")/","&&",$headerVal);
            $pre = explode("&&",$pre);
            // $headerVal = $pre;
            $headerVal = array();
            foreach ($pre as $value) {
              $value = preg_replace("/(;\s+|;)/","&&",$value);
              $value = explode("&&",$value);
              $tmpkey = str_replace('"',"",$value[0]);
              $headerVal[$tmpkey] =  $value[1] ?? "";
              $headerVal[$tmpkey] = preg_replace("/[^0-9]/","",$headerVal[$tmpkey]);
              $headerVal[$tmpkey] = is_numeric($headerVal[$tmpkey]) ? (int) $headerVal[$tmpkey] : 0;
            }
            break;
          case 'sec-ch-ua-platform':
            $headerVal = str_replace('"',"",$headerVal);
            break;
          case 'User-Agent':
            preg_match_all("/(?<browser>\w+)\/(?<version>[0-9.]+)(\s+\((?<comments>.*?)\)|\s+|$)/",$headerVal,$matches);
            $tmp = array();
            foreach ($matches[0] as $key => $value) {
              $el = array(
                "version" => $matches["version"][$key],
                "comments"=> $matches["comments"][$key]
              );
              $tmp[$matches["browser"][$key]] = $el;
            }
            $headerVal = $tmp;

            break;

          default:
            // code...
            break;
        }
      }
    }
    return $raw ? $this->raw_headers : $this->headers;
  }
}
