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
    $label    = $step['label'];
    $value    = $step['value'];
    $sublabel = $step['sublabel'] ?? '';
    $formula  = $step['formula'] ?? '';
    $valueHtml = $step['valueHtml'] ?? null;

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
      'label'    => 'Hash the inputs',
      'value'    => "key:     {$s['message']}\nmessage: {$data->server_seed}",
      'sublabel' => 'The client seed and nonce form the key; the server seed is the message.',
      'formula'  => "HMAC-SHA512(key: \"{$s['message']}\", message: {$data->server_seed})",
    ],
    [
      'label'     => 'Read the full digest',
      'value'     => $s['hash'],
      'sublabel'  => 'HMAC-SHA512 returns 128 hex characters — only the first ' . ROLL_CHARS . ' (highlighted) are used.',
      'valueHtml' => $hashHl,
    ],
    [
      'label'    => 'Take the first ' . ROLL_CHARS . ' characters',
      'value'    => $s['subHash'],
      'sublabel' => 'This slice of the digest is what decides the roll.',
      'formula'  => 'substr(digest, 0, ' . ROLL_CHARS . ')',
    ],
    [
      'label'    => 'Convert hex to a decimal number',
      'value'    => "hexdec(\"{$s['subHash']}\") = {$s['decimal']}",
      'sublabel' => 'Read those ' . ROLL_CHARS . ' hex characters as a base-10 integer.',
    ],
    [
      'label'    => 'Scale into the roll range',
      'value'    => "{$s['decimal']} mod " . ROLL_MAX . " = {$s['mod']}\n{$s['mod']} + 1 = {$s['roll']}",
      'sublabel' => 'Wrap the number into 0–' . (ROLL_MAX - 1) . ', then add 1 to land between 1 and ' . ROLL_MAX . '.',
    ],
    [
      'label'    => 'Hash the server seed for the public commitment',
      'value'    => "key:     {$data->secret_salt}\nmessage: {$data->server_seed}",
      'sublabel' => 'HMAC-SHA256 over the server seed (keyed by the secret salt) produces the published hash.',
      'formula'  => "HMAC-SHA256(key: {$data->secret_salt}, message: {$data->server_seed})",
    ],
    [
      'label'    => 'Read the public hash',
      'value'    => $calculatedPublicHash,
      'sublabel' => 'This should match the hash the website published before the roll.',
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
      'label'    => 'Hash the inputs',
      'value'    => "key:     {$s['message']}\nmessage: {$data->beacon}",
      'sublabel' => 'The client seed and nonce form the key; the beacon is the message.',
      'formula'  => "HMAC-SHA512(key: \"{$s['message']}\", message: {$data->beacon})",
    ],
    [
      'label'     => 'Read the full digest',
      'value'     => $s['hash'],
      'sublabel'  => 'HMAC-SHA512 returns 128 hex characters — only the first ' . ROLL_CHARS . ' (highlighted) are used.',
      'valueHtml' => $hashHl,
    ],
    [
      'label'    => 'Take the first ' . ROLL_CHARS . ' characters',
      'value'    => $s['subHash'],
      'sublabel' => 'This slice of the digest is what decides the roll.',
      'formula'  => 'substr(digest, 0, ' . ROLL_CHARS . ')',
    ],
    [
      'label'    => 'Convert hex to a decimal number',
      'value'    => "hexdec(\"{$s['subHash']}\") = {$s['decimal']}",
      'sublabel' => 'Read those ' . ROLL_CHARS . ' hex characters as a base-10 integer.',
    ],
    [
      'label'    => 'Scale into the roll range',
      'value'    => "{$s['decimal']} mod " . ROLL_MAX . " = {$s['mod']}\n{$s['mod']} + 1 = {$s['roll']}",
      'sublabel' => 'Wrap the number into 0–' . (ROLL_MAX - 1) . ', then add 1 to land between 1 and ' . ROLL_MAX . '.',
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
    'error'   => '&#10060;',
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
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <div class="wrap">

    <header class="site-header">
      <a href="https://skin.club" target="_blank" rel="noopener noreferrer">
        <img src="assets/skinclub-mark.svg" alt="SC" width="32" height="32">
      </a>
      <div class="site-header-text">
        <h1>Verify your Rolls</h1>
        <p class="site-subtitle">Open-source provably fair verification</p>
      </div>
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
      <form method="post" action="/" id="roll-form">
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

  <script src="assets/app.js" defer></script>
</body>
</html>
