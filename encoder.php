<?php
/**
 *
 */
trait Encoder
{
  public function encode(string|array $string) {
    if (is_array($string)) {
      $string = serialize($string);
    }
    $key = $this->env["ENCODE_PSW"] ?? "base_64";
    $encoded = '';
    for ($i = 0; $i < strlen($string); $i++) {
        $encoded .= chr(ord($string[$i]) ^ ord($key[$i % strlen($key)]));
    }
    return base64_encode($encoded);  // Base64 encoding to make the result readable
  }

  public function decode(string $encodedString) {
    $key = $this->env["ENCODE_PSW"] ?? "base_64";
    $decoded = base64_decode($encodedString);
    $decodedString = '';
    for ($i = 0; $i < strlen($decoded); $i++) {
        $decodedString .= chr(ord($decoded[$i]) ^ ord($key[$i % strlen($key)]));
    }
    $serialzied = $this->isSerialized($decodedString);
    if ($serialzied) {
      return unserialize($decodedString);
    }

    return $decodedString;
  }

  public function isSerialized($value) {
    // Check if it's a string and not empty
    if (!is_string($value) || empty($value)) {
        return false;
    }

    // Check if the value starts with a valid serialized string pattern (e.g., 'a:', 'O:', 'b:')
    if (preg_match('/^(a|O|s|b|i|d|N|r):/', $value)) {
        // Try to unserialize the value and check if it works
        $data = @unserialize($value);

        // Return true if the value is unserialized correctly
        return $data !== false || $value === 'b:0;';
    }

    return false;
}


}
