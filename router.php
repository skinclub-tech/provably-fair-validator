<?php

// Router for the PHP built-in server. Its only job is to restrict access to
// llms.txt so that it is served exclusively from the canonical roll.skin.club
// host. Every other request is delegated to the default static handling
// (returning false), which includes executing index.php as the directory index.

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if ($path === '/llms.txt') {
  $host = strtolower(preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''));
  if ($host !== 'roll.skin.club') {
    http_response_code(404);
    exit;
  }
}

return false;
