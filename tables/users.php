<?php
// $password = md5("@Test123456");
// $sql = "INSERT INTO `{$this->db_prefix}users` (title,slug,password,email) VALUES ('Julio','julio','$password','info@julioedi.com')";
// if (isset($this->tables["users"])) {
//   $name = "{$this->db_prefix}users";
//   $el = $this->query("SELECT * from `$name` LIMIT 1");
//   if (empty($el)) {
//     $data = array(
//       "slug" => "julio",
//       "password" => "@Test123456",
//     );
//     $this->db->query($sql);
//   }
// }
return [
  "cols" => array(
    "name" => "longText",
    "email" => "required",
    "user_level" => "code_1",
    "content" => "longText",
    "markers" => "longText",
    "follow" => "longText",
    "seo" => "longText",
    "password" => "required",
  ),
  //keys that must be serialized
  "json" => array(
    "name",
    "content",
    "follow",
    "markers",
    "seo",
  ),
  // "search_columns" => array(
  //   "content",
  //   "name",
  // )
];
