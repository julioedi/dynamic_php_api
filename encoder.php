<?php
/**
 *
 */
trait Encoder
{
  public function encode($string,) {
    $key = $this->env["ENCODE_PSW"] ?? "base_64";
    $encoded = '';
    for ($i = 0; $i < strlen($string); $i++) {
        $encoded .= chr(ord($string[$i]) ^ ord($key[$i % strlen($key)]));
    }
    return base64_encode($encoded);  // Base64 encoding to make the result readable
  }

  public function decode($encodedString, $key) {
    $decoded = base64_decode($encodedString);
    $decodedString = '';
    for ($i = 0; $i < strlen($decoded); $i++) {
        $decodedString .= chr(ord($decoded[$i]) ^ ord($key[$i % strlen($key)]));
    }
    return $decodedString;
  }

}
