<?php
return [
  "cols" => array(
    "name" => "longText",
    "email" => "longText",
    "content" => "longText",
    "markers" => "longText",
    "follow" => "longText",
    "seo" => "longText",
    "password" => "longText",
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
