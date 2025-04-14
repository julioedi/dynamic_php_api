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


  /** --------------------------------------------------------------------------
  * @return array module to print elements and assing regex
  *---------------------------------------------------------------------------*/
  public function print_element_by_id(string $tablename = "", array $custom = array()){
    if (empty($tablename)) {
      $tableCode = explode("/",REQUEST);
      if ($tableCode[0] ?? null ) {
        $tablename = $tableCode[0];
      }
    }
    $this->print_element_by_($tablename,"id",$custom);
  }
  public function print_element_by_slug(string $tablename = "", array $custom = array()){
    $this->print_element_by_($tablename,"slug",$custom,false);
  }

  public function print_element_by_(string $tablename, string|null $code = null, array $custom = array(),bool|null $by_id = true):void{
    if (!$code) {
      if (!$by_id) {
        $code = "slug";
      }else{
        $code = "id";
      }
    }

    header("Content-Type: text/javascript; charset=utf-8");

    $function = $by_id ? "get_element_by_id" : "get_element_by_slug";
    if ($by_id) {
      //$type = $matches["type"] === "d" ? "[0-9]+" : "([A-Za-z][A-Za-z0-9_-]+|[A-Za-z])";
      $this->request_regex = "$tablename/(?<{$code}>\d+)";
    }else{
      $this->request_regex = "$tablename/(?<{$code}>([A-Za-z][A-Za-z0-9_-]+|[A-Za-z]))";
    }
    $this->request_regex = "#^" . $this->request_regex . "$#i";

    $args = array();
    if (is_array($custom["json"] ?? null)) {
      $array["json"] = $custom["json"];
    }


    $uriArgs = $this->getArgs();
    if (isset($uriArgs[$code])) {
      $id = $uriArgs[$code];
    }else{
      if ($by_id) {
        $id = 0;
      }else{
        $id = "";
      }
    }


    $token = $this->validateToken();
    $data = $this->get_body();
    switch ($this->method) {
      case "GET":
          $post = $this->$function($tablename,$id,$custom);
          if (!$post) {
            $this->print_error(null,404);
            return;
          }
          $this->print_json($post);
          return;
        break;
      case "DELETE":
          $this->processToken();
          $post = $this->get_element_by_id($tablename,$id,array(
            "fields_in" => ["ID"]
          ));
          if (!$post) {
            $this->print_error(null,404);
            return;
          }
          $val = $this->db->query("DELETE FROM `{$this->db_prefix}$tablename` where ID = $id");
          if (!$val) {
            $this->print_error("ID dont exists",404);
            return;
          }
          $this->print_json(array(
            "updated" => $id,
          ));
          return;
        break;
      case "INSERT":
          $this->processToken();
          $title = $data["title"] ?? null;
          $slug = $data["title"] ?? null;

          //only allowed text slug
          if (is_string($slug)) {
            $slug = $this->clean_slug($slug);
          }
          elseif (is_string($title)) {
            $slug = $this->clean_slug($title);
          }
          elseif (is_numeric($slug)) {
            $slug = (string) $slug;
          }
          else{
            $slug = null;
          }

          //only allowed text title
          if (!is_string($title) && !is_numeric($title) && $slug) {
            $title = preg_replace("/(-|_)/","",$slug);
            $title = ucfirst($title);
          }
          else{
            $title = null;
          }


          if (!$title) {
            $this->print_error("No title provided",400);
            return;
          }
          if (!$slug) {
            $this->print_error("No title and slug provided",400);
            return;
          }

          //slug must be unique
          $exists = $this->exists_slug($tablename,$slug);

          //error when slug is already registered in db
          if ($exists) {
            $this->print_json("No title and slug provided",409,array(
              "current_slug" => $slug,
              "sugested_slug" => $exists,
            ));
            return;
          }
          $this->insert_sql_row($tablename,$title,$slug,$data);
          return;
          //insert_sql_row
          break;

      default:
        $this->print_error(null,405);
        break;
    }
  }
}
