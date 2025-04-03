<?php
return [
  "cols" => array(
    "excerpt" => "shortText",
    "seo" => "longText",
  ),
  //keys that must be serialized
  "json" => array(
    "seo",
  ),
  "fields_in_child" => array(
    "ID",
    "title",
    "slug"
  ),
];
