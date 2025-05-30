<?php

$token = $this->validateToken();
if ($token) {
  $username = $token["key"] == "slug" ? $token["value"] : null;
  $email = $token["key"] == "email" ? $token["value"] : null;
  $password = $token["password"];
}else{
  $username = $_POST["username"] ?? $_GET["username"] ?? null;
  $password = $_POST["password"] ?? $_GET["password"] ?? null;
  $email = $_POST["email"] ?? $_GET["email"] ?? null;
}
if (!$username && !$email) {
  $this->print_error("Username or email required",412);
}
if (!$password) {
  $this->print_error("Password required",412);
}

if ($username) {
  $username = mb_strtolower( trim($username));
  $selector = array(
    "name" => "slug",
    "value" => $username,
  );
}else{
  $email = mb_strtolower( trim($email));
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $this->print_error("Invalid email address",412);
  }
  $selector = array(
    "name" => "email",
    "value" => $email,
  );
}
$password = trim($password);
$user = $this->select_sql_string("users",array("limit" => 1),array(
  "by_column" => array(
    "OR" => false,
    $selector,
  ),
));


$data = $this->query($user[0]);
$err = $selector["name"] == "email" ? "email" : "username";

//avoid saying if email or password
if (!$data || !password_verify($password,$data[0]["password"] ?? "")) {
  $this->print_error("Wrong $err or password",403);
}

$data = $this->process_row($data[0],array(
  "exclude" => [
    "created",
    "created_by",
    "updated",
    "updated_by",
    "content",
  ],
),"users");

if ($data["status"] < 0 && $data["ID"] != 1 ) {
  $data = array();
  $this->print_error("Account disabled",500);
}

$base = $this->generateUserToken($selector["name"],$selector["value"],$password);
if ($data["ID"] == 1) {
  $data["user_roles"] = [0];
}
$base["data"] = $data;

$this->print_json($base);
