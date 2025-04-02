<?php
$el = $this->getArgs();
if ($this->method === "GET") {
  $post = $this->get_element_by_id("posts",$el["id"] ?? 0,array(
    "json" => array(
      "tags",
      "categories",
      "content",
    )
  ));
  if (!$post) {
    $this->print_error("Post not found");
    return;
  }
  $this->print_json($post);
  return;
}

$token = $this->validateToken();
if (!$token) {
  $this->print_error(null,401);
  return;
}
