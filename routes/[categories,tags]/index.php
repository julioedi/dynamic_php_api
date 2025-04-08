<?php

$this->pagination("categories",array(
  "exclude" => array(
    "seo",
    "attached_images",
  ),
  "status" => 1,
  // "search_columns" => array(
  //   "content",
  //   "excerpt",
  // ),
));
