<?php
// $password = $this->get_password("@Test123456");
// $sql = "INSERT INTO `{$this->db_prefix}users` (title,slug,password,email) VALUES ('Julio','julio','$password','info@julioedi.com')";
// if (isset($this->tables["users"])) {
//   $name = "{$this->db_prefix}users";
//   $el = $this->query("SELECT * from `$name` LIMIT 1");
//   if (empty($el)) {
//     $this->db->query($sql);
//   }
// }
return [
  "cols" => array(
    "name" => "longText",
    "email" => "required",
    "user_roles" => "longText",
    "content" => "longText",
    "password" => "required",
  ),
  //keys that must be serialized
  "json" => array(
    "name",
    "content",
    "user_roles",
  ),
  // "search_columns" => array(
  //   "content",
  //   "name",
  // )
];
