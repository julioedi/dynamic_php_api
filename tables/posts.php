<?php
return [
  "cols" => array(
    "excerpt" => "shortText",
    "content" => "longText",
    "tags" => "longText",
    "categories" => "longText",
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
  // "search_columns" => array(
  //   "content",
  //   "excerpt",
  // )
];
