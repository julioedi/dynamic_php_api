<?php
$el = $this->getArgs();
$post = $this->get_element_by_slug("posts",$el["slug"] ?? 0);
if ($post) {
  $this->print_json($post);
  return;
}else{
  $this->print_error("Post not found");
}
