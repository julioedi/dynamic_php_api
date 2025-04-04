<?php
return [
  "cols" => array(
    "excerpt" => "shortText",
    "content" => "longText",
    "tags" => "longText",
    "categories" => "longText",
    "type" => "code_0",
    "attached_images" => "longText",
    "seo" => "longText",
  ),
  //keys that must be serialized
  "json" => array(
    "tags",
    "categories",
    "attached_images",
    "seo",
    "content"
  ),
  "int" => array(
    "type",
  ),
  // "search_columns" => array(
  //   "content",
  //   "excerpt",
  // )
];
