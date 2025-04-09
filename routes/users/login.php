<?php
$username = $_POST["username"] ?? $_GET["username"] ?? null;
$password = $_POST["password"] ?? $_GET["password"] ?? null;
$email = $_POST["email"] ?? $_GET["email"] ?? null;
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
    array(
      "name" => "password",
      "value" => md5($password),
    ),
  ),
));

$data = $this->get_query_rows($user[0]);
$err = $selector["name"] == "email" ? "email" : "username";
if (!$data) {
  $this->print_error("Wrong $err or password",403);
}

$base = $this->generateUserToken($selector["name"],$selector["value"],$password);

// $this->validateToken();
$this->print_json($base);
