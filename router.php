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

if ($path === '/replit-badge.svg') {
  // Proxy the "Run on Replit" badge through our own origin. Replit's CDN
  // returns the SVG to server-side requests but blocks browser-originated,
  // cross-origin fetches, so we fetch it here and stream it back same-origin.
  $ch = curl_init('https://replit.com/badge/github/skinclub-tech/provably-fair-validator');
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_CONNECTTIMEOUT => 3,
    CURLOPT_TIMEOUT => 5,
    CURLOPT_USERAGENT => 'provably-fair-validator badge proxy',
  ]);
  $body = curl_exec($ch);
  $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
  curl_close($ch);

  if ($body === false || $status !== 200) {
    http_response_code(502);
    exit;
  }

  header('Content-Type: image/svg+xml');
  header('Cache-Control: public, max-age=86400');
  echo $body;
  exit;
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
