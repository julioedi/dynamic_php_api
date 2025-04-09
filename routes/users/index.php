<?php
// $this->validateToken();
$name = "hola";
$encode = $this->validateToken();

echo json_encode([$encode]);

$this->pagination("users",array(
  "exclude" => array(
    "seo",
    "content",
    "markers",
    "follow",
    "featured_id",
    "created_by",
    "updated",
    "email",
  ),
  "status" => 1,
  // "search_columns" => array(
  //   "content",
  //   "excerpt",
  // ),
));
