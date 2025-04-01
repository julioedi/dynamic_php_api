<?php
$el = $this->getArgs();
$post = $this->get_element_by_id("posts",$el["id"] ?? 0);
if ($post) {
  $this->print_json($post);
  return;
}else{
  $this->print_error("Post not found");
}
