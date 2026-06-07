<?php

// Router for the PHP built-in server. It restricts access to llms.txt so that
// it is served exclusively from the canonical roll.skin.club host, and it
// generates a domain-locked robots.txt so only roll.skin.club is crawlable.
// Every other request is delegated to the default static handling (returning
// false), which includes executing index.php as the directory index.

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if ($path === '/llms.txt') {
  $host = strtolower(preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''));
  if ($host !== 'roll.skin.club') {
    http_response_code(404);
    exit;
  }
}

if ($path === '/robots.txt') {
  $host = strtolower(preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''));
  http_response_code(200);
  header('Content-Type: text/plain');
  if ($host === 'roll.skin.club') {
    echo "User-agent: *\nAllow: /\n";
  } else {
    echo "User-agent: *\nDisallow: /\n";
  }
  exit;
}

return false;
