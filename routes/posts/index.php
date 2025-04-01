<?php

$this->pagination("posts",array(
  "json" => array(
    "tags",
    "categories",
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
