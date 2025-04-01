<?php

$this->pagination("posts",array(
  "json" => array(
    "tags",
    "categories",
    "content",
  ),
  "exclude" => array(
    "seo",
    "attached_images",
  ),
  "status" => 1,
  "search_columns" => array(
    "content",
    "excerpt",
  ),
));
