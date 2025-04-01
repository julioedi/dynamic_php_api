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
  "serialized" => array(
    "tags",
    "categories",
    "attached_images",
    "seo",
    "content"
  ),
];
