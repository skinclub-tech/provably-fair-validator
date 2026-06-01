<?php

define('ROLL_CHARS', 15);
define('ROLL_MAX', 100000);

if(PHP_INT_SIZE !== 8) {
  throw new Exception("Only 64-bit execution environment is supported");
}

/**
 * You can prove uniformness of this random function here: https://l.skin.club/pf-charts
 */
function generateRoll(string $serverSeed, string $clientSeed, int $nonce): int
{
  $steps = rollSteps($serverSeed, $clientSeed, $nonce);

  return $steps['roll'];
}

function calculatePublicHash(string $secret, string $salt): string
{
  return hash_hmac('sha256', $secret, $salt);
}

/**
 * Re-run the roll calculation but keep every intermediate value, so the UI can
 * walk the user through the math step by step.
 */
function rollSteps(string $seed, string $clientSeed, int $nonce): array
{
  $message = "{$clientSeed}-{$nonce}";
  $hash = hash_hmac('sha512', $seed, $message);
  $subHash = substr($hash, 0, ROLL_CHARS);
  $decimal = hexdec($subHash);
  $mod = $decimal % ROLL_MAX;

  return [
    'message' => $message,
    'hash'    => $hash,
    'subHash' => $subHash,
    'decimal' => $decimal,
    'mod'     => $mod,
    'roll'    => $mod + 1,
  ];
}

/**
 * Render a collapsed <details> spoiler that lists each calculation step.
 * $steps is an ordered list of [label, value, sublabel?, formula?, valueHtml?].
 *   - label     : short plain-language title for the step
 *   - value     : the raw result text
 *   - sublabel  : optional one-line explanation of what/why
 *   - formula   : optional function-call expression with values substituted in
 *   - valueHtml : optional pre-rendered HTML for the value (e.g. with a
 *                 highlighted slice); when present it is shown instead of the
 *                 escaped $value.
 */
function calculationSpoiler(array $steps): string
{
  $rows = '';
  $n = 1;
  foreach ($steps as $step) {
    $label    = $step[0];
    $value    = $step[1];
    $sublabel = $step[2] ?? '';
    $formula  = $step[3] ?? '';
    $valueHtml = $step[4] ?? null;

    $v = $valueHtml !== null ? $valueHtml : htmlspecialchars($value);

    $sublabelHtml = $sublabel !== ''
      ? "<div class='calc-sublabel'>" . htmlspecialchars($sublabel) . "</div>"
      : '';
    $formulaHtml = $formula !== ''
      ? "<div class='calc-formula'>" . htmlspecialchars($formula) . "</div>"
      : '';

    $rows .= "<div class='calc-step'>"
           . "<div class='calc-step-num'>{$n}</div>"
           . "<div class='calc-step-body'><div class='calc-label'>{$label}</div>"
           . $sublabelHtml
           . $formulaHtml
           . "<div class='calc-value-row'>"
           . "<div class='calc-value mono'>{$v}</div>"
           . "</div></div>"
           . "</div>";
    $n++;
  }

  return "<details class='calc-spoiler'>"
       . "<summary>How this roll was calculated</summary>"
       . "<div class='calc-steps'>{$rows}</div>"
       . "</details>";
}

function missingRequiredProps(object $obj, array $props): array
{
  $missing = [];
  foreach($props as $prop) {
    if(!isset($obj->$prop) || trim((string)$obj->$prop) === '') {
      $missing[] = $prop;
    }
  }

  return $missing;
}

function missingFieldsError(array $missing): string
{
  $names = implode(', ', array_map(fn($f) => "<code>{$f}</code>", $missing));
  $label = count($missing) === 1 ? 'field is' : 'fields are';
  return callout('error', 'Your input is invalid.', "The following {$label} missing or empty: {$names}. Copy the full JSON string from the site and paste it here.");
}

function hasProp(object $obj, string $prop): bool
{
  return isset($obj->$prop) && trim((string)$obj->$prop) !== '';
}

function checkRegularRoll($data, ?string &$detectedType = null): string
{
  $req = ['server_seed', 'secret_salt', 'public_hash', 'client_seed', 'nonce', 'roll'];
  if (!is_object($data)) {
    return callout('error', 'Your input is invalid.', 'Copy the full JSON string from the site and paste it here.');
  }
  $missing = missingRequiredProps($data, $req);
  if ($missing) {
    return missingFieldsError($missing);
  }
  if($data->server_seed[0] === '*' || $data->secret_salt[0] === '*') {
    return callout('warning', 'Server Seed is not yet revealed.', 'It is impossible to verify this roll right now &mdash; check back after the seed is revealed.');
  }

  $originalRoll = (int)$data->roll;
  $calculatedRoll = generateRoll($data->server_seed, $data->client_seed, $data->nonce);

  $originalPublicHash = $data->public_hash;
  $calculatedPublicHash = calculatePublicHash($data->server_seed, $data->secret_salt);

  $rollMatch = $originalRoll === $calculatedRoll;
  $hashMatch = $originalPublicHash === $calculatedPublicHash;

  $banner = ($rollMatch && $hashMatch)
    ? "<div class='summary summary-ok'><span class='summary-icon'>&#10003;</span><div><strong>Verified &mdash; everything checks out.</strong><span>Both the roll and the public hash match.</span></div></div>"
    : "<div class='summary summary-fail'><span class='summary-icon'>&#10007;</span><div><strong>Verification failed.</strong><span>One or more values did not match. See details below.</span></div></div>";

  $s = rollSteps($data->server_seed, $data->client_seed, (int)$data->nonce);
  $hashHl = "<span class='calc-hash-hl'>" . htmlspecialchars($s['subHash']) . "</span>" . htmlspecialchars(substr($s['hash'], ROLL_CHARS));
  $spoiler = calculationSpoiler([
    [
      'Hash the inputs',
      "key:     {$s['message']}\nmessage: {$data->server_seed}",
      'The client seed and nonce form the key; the server seed is the message.',
      "HMAC-SHA512(key: \"{$s['message']}\", message: {$data->server_seed})",
    ],
    [
      'Read the full digest',
      $s['hash'],
      'HMAC-SHA512 returns 128 hex characters — only the first ' . ROLL_CHARS . ' (highlighted) are used.',
      '',
      $hashHl,
    ],
    [
      'Take the first ' . ROLL_CHARS . ' characters',
      $s['subHash'],
      'This slice of the digest is what decides the roll.',
      'substr(digest, 0, ' . ROLL_CHARS . ')',
    ],
    [
      'Convert hex to a decimal number',
      "hexdec(\"{$s['subHash']}\") = {$s['decimal']}",
      'Read those ' . ROLL_CHARS . ' hex characters as a base-10 integer.',
    ],
    [
      'Scale into the roll range',
      "{$s['decimal']} mod " . ROLL_MAX . " = {$s['mod']}\n{$s['mod']} + 1 = {$s['roll']}",
      'Wrap the number into 0–' . (ROLL_MAX - 1) . ', then add 1 to land between 1 and ' . ROLL_MAX . '.',
    ],
    [
      'Hash the server seed for the public commitment',
      "key:     {$data->secret_salt}\nmessage: {$data->server_seed}",
      'HMAC-SHA256 over the server seed (keyed by the secret salt) produces the published hash.',
      "HMAC-SHA256(key: {$data->secret_salt}, message: {$data->server_seed})",
    ],
    [
      'Read the public hash',
      $calculatedPublicHash,
      'This should match the hash the website published before the roll.',
    ],
  ]);

  $detectedType = detectedTypeNote('regular');
  return $banner
       . comparisonCard('Roll number', (string)$originalRoll, (string)$calculatedRoll, $rollMatch)
       . comparisonCard('Public hash', $originalPublicHash, $calculatedPublicHash, $hashMatch, true)
       . $spoiler;
}

function checkBattleRoll($data, ?string &$detectedType = null): string
{
  $req = ['beacon', 'client_seed', 'nonce', 'roll'];
  if (!is_object($data)) {
    return callout('error', 'Your input is invalid.', 'Copy the full JSON string from the site and paste it here.');
  }
  $missing = missingRequiredProps($data, $req);
  if ($missing) {
    return missingFieldsError($missing);
  }
  if($data->beacon[0] === '*') {
    return callout('warning', 'Beacon is not yet generated.', 'It is impossible to verify this roll right now &mdash; check back once the beacon is available.');
  }

  $originalRoll = (int)$data->roll;
  $calculatedRoll = generateRoll($data->beacon, $data->client_seed, $data->nonce);
  $rollMatch = $originalRoll === $calculatedRoll;

  $banner = $rollMatch
    ? "<div class='summary summary-ok'><span class='summary-icon'>&#10003;</span><div><strong>Verified &mdash; the roll checks out.</strong><span>The recalculated roll matches the one from the battle.</span></div></div>"
    : "<div class='summary summary-fail'><span class='summary-icon'>&#10007;</span><div><strong>Verification failed.</strong><span>The recalculated roll did not match. See details below.</span></div></div>";

  $s = rollSteps($data->beacon, $data->client_seed, (int)$data->nonce);
  $hashHl = "<span class='calc-hash-hl'>" . htmlspecialchars($s['subHash']) . "</span>" . htmlspecialchars(substr($s['hash'], ROLL_CHARS));
  $spoiler = calculationSpoiler([
    [
      'Hash the inputs',
      "key:     {$s['message']}\nmessage: {$data->beacon}",
      'The client seed and nonce form the key; the beacon is the message.',
      "HMAC-SHA512(key: \"{$s['message']}\", message: {$data->beacon})",
    ],
    [
      'Read the full digest',
      $s['hash'],
      'HMAC-SHA512 returns 128 hex characters — only the first ' . ROLL_CHARS . ' (highlighted) are used.',
      '',
      $hashHl,
    ],
    [
      'Take the first ' . ROLL_CHARS . ' characters',
      $s['subHash'],
      'This slice of the digest is what decides the roll.',
      'substr(digest, 0, ' . ROLL_CHARS . ')',
    ],
    [
      'Convert hex to a decimal number',
      "hexdec(\"{$s['subHash']}\") = {$s['decimal']}",
      'Read those ' . ROLL_CHARS . ' hex characters as a base-10 integer.',
    ],
    [
      'Scale into the roll range',
      "{$s['decimal']} mod " . ROLL_MAX . " = {$s['mod']}\n{$s['mod']} + 1 = {$s['roll']}",
      'Wrap the number into 0–' . (ROLL_MAX - 1) . ', then add 1 to land between 1 and ' . ROLL_MAX . '.',
    ],
  ]);

  $detectedType = detectedTypeNote('battle');
  return $banner
       . comparisonCard('Roll number', (string)$originalRoll, (string)$calculatedRoll, $rollMatch)
       . $spoiler;
}

function detectedTypeNote(string $type): string
{
  $label = $type === 'battle' ? 'battle roll' : 'regular roll';
  return "<div class='detected-type'>Checked as a <strong>{$label}</strong></div>";
}

function callout(string $type, string $title, string $body): string
{
  $icons = [
    'error'   => '&#9888;',
    'warning' => '&#9888;',
    'info'    => '&#8505;',
  ];
  return "<div class='callout callout-{$type}'>"
       . "<span class='callout-icon'>" . ($icons[$type] ?? '&#8505;') . "</span>"
       . "<div class='callout-text'><strong>{$title}</strong>" . ($body ? "<span>{$body}</span>" : "") . "</div>"
       . "</div>";
}

function comparisonCard(string $title, string $original, string $calculated, bool $match, bool $mono = false): string
{
  $valClass = 'cmp-value mono';
  $o = htmlspecialchars($original);
  $c = htmlspecialchars($calculated);

  $verdict = $match
    ? "<div class='verdict verdict-ok'><span class='verdict-icon'>&#10003;</span> They are identical &mdash; this is provably fair</div>"
    : "<div class='verdict verdict-fail'><span class='verdict-icon'>&#10007;</span> They do not match &mdash; this result could not be verified</div>";

  return "<div class='cmp-card'>"
       . "<div class='cmp-title'>{$title}</div>"
       . "<div class='cmp-row'><span class='cmp-label'>Provided by site</span><span class='{$valClass}'>{$o}</span></div>"
       . "<div class='cmp-row'><span class='cmp-label'>Recalculated here</span><span class='{$valClass}'>{$c}</span></div>"
       . $verdict
       . "</div>";
}

/**
 * Work out which kind of roll the payload is from the fields that are present,
 * so a missing or wrong "type" field doesn't matter:
 *   - server_seed (and no beacon) => regular
 *   - beacon (and no server_seed) => battle
 * Returns null when the data is ambiguous (both or neither field present).
 */
function detectRollType(object $input): ?string
{
  $hasServerSeed = hasProp($input, 'server_seed');
  $hasBeacon = hasProp($input, 'beacon');
  if ($hasServerSeed && !$hasBeacon) {
    return 'regular';
  }
  if ($hasBeacon && !$hasServerSeed) {
    return 'battle';
  }
  return null;
}

function verifyRollData(string $json, ?string &$detectedType = null): string
{
  $input = json_decode($json);
  if ($input === null) {
    return callout('error', 'That is not valid JSON.', 'Make sure you copied the entire block, including the curly braces { }.');
  }
  if (!is_object($input)) {
    return callout('error', 'Your input is invalid.', 'Copy the full JSON string from the site and paste it here.');
  }

  // Prefer the type inferred from the actual fields. Only fall back to the
  // declared "type" field when the payload is ambiguous.
  $type = detectRollType($input) ?? ($input->type ?? 'regular');

  if ($type === 'regular') {
    return checkRegularRoll($input, $detectedType);
  }
  if ($type === 'battle') {
    return checkBattleRoll($input, $detectedType);
  }
  return callout('error', 'Unknown roll type supplied.', 'The "type" field must be either "regular" or "battle".');
}

$message = '';
$detectedType = '';
if (!empty($_POST['roll_data'])) {
  $message = verifyRollData($_POST['roll_data'], $detectedType);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#7B4FFF">
  <link rel="icon" href="data:image/svg+xml,%3Csvg%20xmlns='http://www.w3.org/2000/svg'%20viewBox='0%200%2064%2064'%3E%3Cdefs%3E%3ClinearGradient%20id='g'%20x1='0'%20y1='0'%20x2='1'%20y2='1'%3E%3Cstop%20offset='0'%20stop-color='%239B6FFF'/%3E%3Cstop%20offset='1'%20stop-color='%237B4FFF'/%3E%3C/linearGradient%3E%3C/defs%3E%3Crect%20width='64'%20height='64'%20rx='14'%20fill='%230d0b1a'/%3E%3Cpath%20d='M32%208l18%207v12c0%2011-7.5%2019-18%2023C21.5%2046%2014%2038%2014%2027V15z'%20fill='url(%23g)'/%3E%3Cpath%20d='M26%2032l5%205%209-11'%20fill='none'%20stroke='%230d0b1a'%20stroke-width='4'%20stroke-linecap='round'%20stroke-linejoin='round'/%3E%3C/svg%3E">
  <title>Provably Fair Validator &mdash; Verify your Rolls</title>
  <link rel="preload" href="assets/fonts/montserrat-latin.woff2" as="font" type="font/woff2" crossorigin>
  <style>
    @font-face {
      font-family: "Montserrat";
      font-style: normal;
      font-weight: 400 800;
      font-display: block;
      src: url("assets/fonts/montserrat-latin.woff2") format("woff2");
      unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
    }
    :root {
      --font: "Montserrat", "Helvetica Neue", Arial, sans-serif;
      --bg: #0d0b1a;
      --bg-2: #14112a;
      --panel: #1a1633;
      --panel-2: #221c40;
      --border: #322a5c;
      --border-soft: #272150;
      --text: #e7eaf6;
      --muted: #a7afd1;
      --brand: #7B4FFF;
      --brand-2: #9B6FFF;
      --brand-link: #b29bff;
      /* Darker gradient stops for surfaces that carry white text (AA-safe) */
      --brand-grad-1: #6A3DEB;
      --brand-grad-2: #7548F5;
      --ok: #34d399;
      --ok-bg: rgba(52, 211, 153, .12);
      --fail: #f87171;
      --fail-bg: rgba(248, 113, 113, .12);
      --warn: #fbbf24;
      --warn-bg: rgba(251, 191, 36, .12);
      --info: #60a5fa;
      --info-bg: rgba(96, 165, 250, .12);
      --radius: 14px;
      --shadow: 0 18px 50px rgba(0, 0, 0, .45);
    }

    * { box-sizing: border-box; }

    html { font-size: 15px; }

    body {
      margin: 0;
      min-height: 100vh;
      font-family: var(--font);
      -webkit-font-smoothing: antialiased;
      -moz-osx-font-smoothing: grayscale;
      color: var(--text);
      line-height: 1.5;
      background:
        radial-gradient(1100px 600px at 12% -10%, rgba(123, 79, 255, .26), transparent 60%),
        radial-gradient(900px 500px at 100% 0%, rgba(155, 111, 255, .20), transparent 55%),
        var(--bg);
      padding: 32px 18px 64px;
    }

    .wrap { max-width: 760px; margin: 0 auto; }

    /* Header */
    .site-header {
      display: flex; align-items: center; flex-wrap: wrap; gap: 32px;
      margin: 8px 0 34px; padding-bottom: 20px;
      border-bottom: 1px solid var(--border-soft);
    }
    .site-header a { display: inline-flex; align-items: center; line-height: 0; }
    .site-header img {
      height: 32px; width: auto; display: block;
      image-rendering: -webkit-optimize-contrast;
    }
    .site-header h1 {
      margin: 0;
      font-size: 1.5rem; line-height: 1.15; font-weight: 800;
      color: #f5f5fa;
    }

    /* Cards */
    .card {
      background: linear-gradient(180deg, var(--panel), var(--bg-2));
      border: 1px solid var(--border);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      padding: 28px;
      margin-bottom: 28px;
    }
    .card h2 {
      margin: 0 0 14px;
      font-size: 1.05rem; font-weight: 700;
    }
    .card p { margin: 0 0 14px; color: var(--muted); font-size: 14px; }

    details summary {
      cursor: pointer; list-style: none; padding: 10px 0;
      color: var(--brand-link); font-weight: 600; font-size: .92rem;
      display: flex; align-items: center; gap: 8px;
    }
    details summary::-webkit-details-marker { display: none; }
    details summary::before { content: "\25B8"; transition: transform .15s ease; }
    details[open] summary::before { transform: rotate(90deg); }
    .card dl { margin: 4px 0 0; display: grid; grid-template-columns: 1fr; gap: 10px; }
    .card dt { font-weight: 700; color: var(--text); font-size: .9rem; }
    .card dd { margin: 2px 0 0; color: var(--muted); font-size: 14px; }
    .card code {
      background: var(--panel-2); border: 1px solid var(--border);
      padding: 1px 6px; border-radius: 6px; font-size: .82rem; color: #cdd6ff;
    }

    /* Form */
    .field-label { font-weight: 600; }
    .label-row { display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 16px; }
    .sample-controls { display: flex; align-items: center; gap: 6px; }
    .btn-sample {
      appearance: none; cursor: pointer; border: none;
      background: #282546;
      clip-path: polygon(12px 0, calc(100% - 12px) 0, 100% 50%, calc(100% - 12px) 100%, 12px 100%, 0 50%);
      padding: 10px 20px; font-family: inherit; font-size: 11px; font-weight: 700;
      text-transform: uppercase;
      color: var(--text); transition: filter .15s ease;
    }
    .btn-sample:hover { filter: brightness(1.2); }
    textarea {
      width: 100%; min-height: 80px; resize: vertical; overflow-y: hidden;
      background: var(--bg); color: var(--text);
      border: 1px solid var(--border); border-radius: 12px;
      padding: 14px 16px; font-size: .9rem; line-height: 1.5;
      font-family: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, monospace;
      transition: border-color .15s ease, box-shadow .15s ease;
    }
    textarea:focus {
      outline: none; border-color: var(--brand);
      box-shadow: 0 0 0 4px rgba(123, 79, 255, .18);
    }

    .actions { display: flex; flex-wrap: wrap; gap: 14px; margin-top: 24px; align-items: center; }
    .btn {
      appearance: none; cursor: pointer; border: 1px solid transparent;
      padding: 11px 30px; border-radius: 11px; font-size: .92rem; font-weight: 700;
      font-family: inherit;
      transition: transform .08s ease, filter .15s ease, background .15s ease;
    }
    .btn:active { transform: translateY(1px); }
    .btn-primary {
      background: #5c49d0; color: #ffffff; box-shadow: none;
      padding: 11px 50px; margin-left: auto;
      font-size: 15px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em;
      clip-path: polygon(14px 0, calc(100% - 14px) 0, 100% 50%, calc(100% - 14px) 100%, 14px 100%, 0 50%);
    }
    .btn-primary:hover { filter: brightness(1.08); }
    .btn-ghost { background: var(--panel-2); color: var(--text); border-color: var(--border); }
    .btn-ghost:hover { background: #2c2656; border-color: var(--brand); }
    .spacer { flex: 1; }
    .sample-label { color: #6b6a8a; font-size: 12px; font-weight: 700; margin-right: 5px; }

    /* Results */
    .results { animation: fade .25s ease; }
    @keyframes fade { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }

    .result-header {
      display: flex; align-items: center; justify-content: space-between;
      gap: 12px; flex-wrap: wrap; margin-bottom: 14px;
    }
    .result-header h2 { margin: 0; }

    .detected-type {
      display: inline-block;
      padding: 6px 12px; border-radius: 12px;
      background: rgba(123, 79, 255, .12); border: 1px solid var(--border);
      color: var(--muted); font-size: 14px;
    }
    .detected-type strong { color: var(--brand-link); }

    .summary {
      display: flex; align-items: center; gap: 14px;
      padding: 16px 18px; border-radius: 12px; margin-bottom: 16px;
      border: 1px solid var(--border);
    }
    .summary div { display: flex; flex-direction: column; }
    .summary span:last-child { color: var(--muted); font-size: 14px; }
    .summary-icon { font-size: 1.4rem; width: 40px; height: 40px; flex: none; border-radius: 10px; display: grid; place-items: center; }
    .summary-ok { background: var(--ok-bg); border-color: rgba(52,211,153,.35); }
    .summary-ok .summary-icon { background: rgba(52,211,153,.18); color: var(--ok); }
    .summary-ok strong { color: var(--ok); }
    .summary-fail { background: var(--fail-bg); border-color: rgba(248,113,113,.35); }
    .summary-fail .summary-icon { background: rgba(248,113,113,.18); color: var(--fail); }
    .summary-fail strong { color: var(--fail); }

    .cmp-card { border: 1px solid var(--border); border-radius: 12px; padding: 16px; margin-bottom: 14px; background: var(--bg); }
    .cmp-title { font-weight: 700; font-size: 15px; text-transform: uppercase; letter-spacing: .05em; color: var(--muted); margin-bottom: 10px; }
    .cmp-row { display: flex; justify-content: space-between; gap: 14px; padding: 7px 0; border-bottom: 1px solid var(--border); }
    .cmp-row:last-of-type { border-bottom: 0; }
    .cmp-label { color: var(--muted); font-size: 13px; flex: none; }
    .cmp-value { font-weight: 700; text-align: right; word-break: break-all; font-size: 13px; }
    .cmp-value.mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-weight: 600; font-size: 13px; }
    .verdict { margin-top: 12px; padding: 9px 12px; border-radius: 9px; font-weight: 600; font-size: .9rem; display: flex; align-items: center; gap: 8px; }
    .verdict-icon { font-size: 1rem; }
    .verdict-ok { background: var(--ok-bg); color: var(--ok); }
    .verdict-fail { background: var(--fail-bg); color: var(--fail); }

    /* Calculation steps spoiler */
    .calc-spoiler { margin-top: 4px; border: 1px solid var(--border); border-radius: 12px; background: var(--bg); padding: 0 16px; }
    .calc-spoiler summary { color: var(--brand-link); }
    .field-glossary dl { padding: 4px 0 16px; }
    .calc-steps { display: flex; flex-direction: column; gap: 12px; padding: 4px 0 16px; }
    .calc-step { display: flex; gap: 12px; align-items: flex-start; }
    .calc-step-num {
      flex: none; width: 24px; height: 24px; border-radius: 50%;
      display: grid; place-items: center; margin-top: 1px;
      background: linear-gradient(135deg, var(--brand-grad-1), var(--brand-grad-2));
      color: #ffffff; font-weight: 800; font-size: .76rem;
    }
    .calc-step-body { flex: 1; min-width: 0; }
    .calc-label { color: var(--text); font-weight: 700; font-size: 14px; margin-bottom: 4px; }
    .calc-sublabel { color: var(--muted); font-size: 12.5px; line-height: 1.4; margin-bottom: 4px; }
    .calc-formula {
      font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
      font-style: italic; color: var(--muted); font-size: 12px;
      word-break: break-all; margin-bottom: 6px;
    }
    .calc-hash-hl { color: var(--brand-2); font-weight: 800; }
    .calc-value-row { display: flex; gap: 8px; align-items: flex-start; }
    .calc-value { font-weight: 700; word-break: break-all; flex: 1; min-width: 0; }
    .calc-value.mono {
      font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
      font-weight: 600; font-size: .82rem; white-space: pre-wrap; color: #cdd6ff;
    }
    /* Spoiler text reduction: 1px smaller than the same element outside a details */
    details summary { font-size: 12.8px; }
    details .card p { font-size: 13px; }
    details .card dt { font-size: 12.5px; }
    details .card dd { font-size: 13px; }
    details .card code { font-size: 11.3px; }
    details .cmp-label { font-size: 13px; }
    details .cmp-value { font-size: 14px; }
    details .calc-label { font-size: 13px; }
    details .calc-sublabel { font-size: 11.5px; }
    details .calc-formula { font-size: 11px; }
    details .calc-value { font-size: 14px; }
    details .calc-value.mono { font-size: 11.3px; }
    details .calc-step-num { font-size: 10.4px; }

    /* Callouts (errors / warnings) */
    .callout { display: flex; gap: 12px; padding: 14px 16px; border-radius: 12px; border: 1px solid var(--border); }
    .callout-icon { font-size: 1.2rem; flex: none; }
    .callout-text { display: flex; flex-direction: column; gap: 2px; }
    .callout-text span { color: var(--muted); font-size: 14px; }
    .callout-error { background: var(--fail-bg); } .callout-error .callout-icon, .callout-error strong { color: var(--fail); }
    .callout-warning { background: var(--warn-bg); } .callout-warning .callout-icon, .callout-warning strong { color: var(--warn); }
    .callout-info { background: var(--info-bg); } .callout-info .callout-icon, .callout-info strong { color: var(--info); }

    /* Footer */
    .footer { text-align: center; color: var(--muted); font-size: 14px; margin-top: 26px; }
    .footer a { color: var(--brand-link); text-decoration: none; }
    .footer a:hover { text-decoration: underline; }
    .footer .footer-links { display: flex; flex-direction: column; align-items: center; gap: 16px; }

    @media (max-width: 520px) {
      .site-header h1 { font-size: 1.25rem; }
      .cmp-row { flex-direction: column; gap: 2px; }
      .cmp-value { text-align: left; }
    }
  </style>
</head>
<body>
  <div class="wrap">

    <header class="site-header">
      <a href="https://skin.club" target="_blank" rel="noopener noreferrer">
        <img src="assets/skinclub-mark.svg" alt="SC" width="32" height="32">
      </a>
      <h1>Verify your Rolls</h1>
    </header>

    <?php if ($message): ?>
    <section class="card results" id="results">
      <div class="result-header">
        <h2>Verification result</h2>
        <?php if ($detectedType): ?><?= $detectedType ?><?php endif; ?>
      </div>
      <?= $message ?>
    </section>
    <?php endif; ?>

    <section class="card">
      <form method="post" action="/">
        <div class="label-row">
          <label class="field-label" for="roll_data">Paste your roll data</label>
          <div class="sample-controls">
            <span class="sample-label">Sample data:</span>
            <button type="button" class="btn-sample" onclick="loadSample('regular')">Regular</button>
            <button type="button" class="btn-sample" onclick="loadSample('battle')">Battle</button>
          </div>
        </div>
        <textarea id="roll_data" name="roll_data" placeholder='{
  "client_seed": "...",
  "server_seed": "...",
  "secret_salt": "...",
  "public_hash": "...",
  "nonce": "...",
  "roll": "...",
  "created_at": "..."
}'><?= htmlspecialchars($_POST['roll_data'] ?? '') ?></textarea>

        <div class="actions">
          <button type="submit" class="btn btn-primary">Check</button>
        </div>
      </form>
    </section>

    <section class="card">
      <h2>How to verify</h2>
      <p>Paste the JSON from the website&rsquo;s &ldquo;Check Roll&rdquo; page into the box above and hit Check.</p>
      <details class="calc-spoiler field-glossary">
        <summary>Here&rsquo;s what each field means</summary>
        <dl>
          <dt><code>server_seed</code> / <code>beacon</code></dt>
          <dd>The secret value (revealed afterwards) that drives the random roll.</dd>
          <dt><code>client_seed</code></dt>
          <dd>Your own seed, mixed in so neither side can predict the outcome alone.</dd>
          <dt><code>nonce</code></dt>
          <dd>A counter that makes every roll with the same seeds unique.</dd>
          <dt><code>public_hash</code></dt>
          <dd>A fingerprint published <em>before</em> the roll, proving the seed wasn&rsquo;t changed later.</dd>
          <dt><code>roll</code></dt>
          <dd>The result the site claims &mdash; we recalculate it to confirm.</dd>
        </dl>
      </details>
    </section>

    <div class="footer">
      <div class="footer-links">
        <a href="https://replit.com/github/skinclub-tech/provably-fair-validator" target="_blank" rel="noopener"><img src="https://replit.com/badge/github/skinclub-tech/provably-fair-validator" alt="Run on Replit"></a>
        <a href="https://github.com/skinclub-tech/provably-fair-validator" target="_blank" rel="noopener">Source Code</a>
      </div>
    </div>

  </div>

  <script>
    const SAMPLES = {
      regular: {
        client_seed: "my_seed",
        server_seed: "c4ca4238a0b92382",
        secret_salt: "0dcc509a6f75849b",
        public_hash: "dc883b29588c1204fcad00984aaa2404c2251f9a0e5300106eb39aaebcc0f493",
        nonce: "4",
        roll: "21752",
        created_at: "2024-01-01 00:00:00"
      },
      battle: {
        type: "battle",
        beacon: "Tt5qAdTwoTeygDdghVlfEWtNJQkGYg5q",
        client_seed: "12354,abgd",
        nonce: "9",
        roll: "5415"
      }
    };
    function autoSize(ta) {
      ta.style.height = 'auto';
      ta.style.height = ta.scrollHeight + 'px';
    }
    function loadSample(kind) {
      const ta = document.getElementById('roll_data');
      ta.value = JSON.stringify(SAMPLES[kind], null, 2);
      autoSize(ta);
      ta.focus();
    }
    document.addEventListener('DOMContentLoaded', () => {
      const ta = document.getElementById('roll_data');
      if (!ta) return;
      autoSize(ta);
      ta.addEventListener('input', () => autoSize(ta));
      ta.addEventListener('paste', () => {
        setTimeout(() => {
          try {
            ta.value = JSON.stringify(JSON.parse(ta.value), null, 2);
          } catch (e) {}
          autoSize(ta);
        }, 0);
      });
    });
  </script>
</body>
</html>
