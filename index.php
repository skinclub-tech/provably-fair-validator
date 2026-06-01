<?php
/*
Usage instruction:
1. Authenticate on Repl.com and Fork this Repl (blue button on top right)
2. Click "Run" on top of appeared page
2. Copy JSON data from the "Check Roll" page on skin.club
3. Enter the JSON in the form with label "Enter Your Roll Data"
4. Click the "Check!" button
5. See, whether the data is correct (it should!)

OR
1. Visit already running version of this Repl (https://skinclub-provably-fair-validator.replit.app/)

If you would like just to test, how does this page work, here is your sample data:
{
  "server_seed": "c4ca4238a0b92382",
  "secret_salt": "0dcc509a6f75849b",
  "public_hash": "dc883b29588c1204fcad00984aaa2404c2251f9a0e5300106eb39aaebcc0f493",
  "client_seed": "my_seed",
  "nonce": "4",
  "roll": "21752"
}
or, data for battles:
{
  "type": "battle",
  "beacon": "Tt5qAdTwoTeygDdghVlfEWtNJQkGYg5q",
  "client_seed": "12354,abgd",
  "nonce": "9",
  "roll": "5415"
}
*/

# ------------------------------------------------------------- #

define('ROLL_CHARS', 15);
define('ROLL_MAX', 100000);

if(PHP_INT_SIZE !== 8) {
  throw new Exception("Only 64-bit execution environment is supported");
}

# ------------------------------------------------------------- #

/**
 * You can prove uniformness of this random function here: https://l.skin.club/pf-charts
 */
function generateRoll(string $serverSeed, string $clientSeed, int $nonce): int
{
  $hash = hash_hmac('sha512', $serverSeed, "{$clientSeed}-{$nonce}");
  $subHash = substr($hash, 0, ROLL_CHARS);
  $roll = hexdec($subHash) % ROLL_MAX;

  // because we have [0; 99999] but need [1; 100000]
  return $roll + 1;
}

function calculatePublicHash(string $secret, string $salt): string
{
  return hash_hmac('sha256', $secret, $salt);
}

# ------------------------------------------------------------- #

function checkRequiredProps(object $obj, array $props): bool
{
  foreach($props as $prop) {
    if(!isset($obj->$prop) || $obj->$prop === '') {
      return false;
    }
  }

  return true;
}

# ------------------------------------------------------------- #

function callout(string $type, string $title, string $body): string
{
  $icons = [
    'error'   => '&#9888;',
    'warning' => '&#9888;',
    'info'    => '&#8505;',
  ];
  $icon = $icons[$type] ?? '&#8505;';
  return "<div class='callout callout-{$type}'>"
       . "<span class='callout-icon'>{$icon}</span>"
       . "<div class='callout-text'><strong>{$title}</strong>" . ($body ? "<span>{$body}</span>" : "") . "</div>"
       . "</div>";
}

function comparisonCard(string $title, string $original, string $calculated, bool $match, bool $mono = false): string
{
  $valClass = $mono ? 'cmp-value mono' : 'cmp-value';
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

# ------------------------------------------------------------- #

function checkRegularRoll($data): string
{
  $req = ['server_seed', 'secret_salt', 'public_hash', 'client_seed', 'nonce', 'roll'];
  if (!is_object($data) || !checkRequiredProps($data, $req)) {
    return callout('error', 'Your input is invalid.', 'Copy the full JSON string from the site and paste it here.');
  }
  if($data->server_seed[0] === '*' || $data->secret_salt[0] === '*') {
    return callout('warning', 'Server Seed is not yet revealed.', 'It is impossible to verify this roll right now &mdash; check back after the seed is revealed.');
  }

  // data seems to be valid and we can proceed
  $originalRoll = (int)$data->roll;
  $calculatedRoll = generateRoll($data->server_seed, $data->client_seed, $data->nonce);

  $originalPublicHash = $data->public_hash;
  $calculatedPublicHash = calculatePublicHash($data->server_seed, $data->secret_salt);

  $rollMatch = $originalRoll === $calculatedRoll;
  $hashMatch = $originalPublicHash === $calculatedPublicHash;

  $banner = ($rollMatch && $hashMatch)
    ? "<div class='summary summary-ok'><span class='summary-icon'>&#10003;</span><div><strong>Verified &mdash; everything checks out.</strong><span>Both the roll and the public hash match.</span></div></div>"
    : "<div class='summary summary-fail'><span class='summary-icon'>&#10007;</span><div><strong>Verification failed.</strong><span>One or more values did not match. See details below.</span></div></div>";

  return $banner
       . comparisonCard('Roll number', (string)$originalRoll, (string)$calculatedRoll, $rollMatch)
       . comparisonCard('Public hash', $originalPublicHash, $calculatedPublicHash, $hashMatch, true);
}

function checkBattleRoll($data): string
{
  $req = ['beacon', 'client_seed', 'nonce', 'roll'];
  if (!is_object($data) || !checkRequiredProps($data, $req)) {
    return callout('error', 'Your input is invalid.', 'Copy the full JSON string from the site and paste it here.');
  }
  if($data->beacon[0] === '*') {
    return callout('warning', 'Beacon is not yet generated.', 'It is impossible to verify this roll right now &mdash; check back once the beacon is available.');
  }

  // data seems to be valid and we can proceed
  $originalRoll = (int)$data->roll;
  $calculatedRoll = generateRoll($data->beacon, $data->client_seed, $data->nonce);
  $rollMatch = $originalRoll === $calculatedRoll;

  $banner = $rollMatch
    ? "<div class='summary summary-ok'><span class='summary-icon'>&#10003;</span><div><strong>Verified &mdash; the roll checks out.</strong><span>The recalculated roll matches the one from the battle.</span></div></div>"
    : "<div class='summary summary-fail'><span class='summary-icon'>&#10007;</span><div><strong>Verification failed.</strong><span>The recalculated roll did not match. See details below.</span></div></div>";

  return $banner
       . comparisonCard('Roll number', (string)$originalRoll, (string)$calculatedRoll, $rollMatch);
}

$message = '';
if (!empty($_POST['roll_data'])) {
  $input = json_decode($_POST['roll_data']);
  if ($input === null) {
    $message = callout('error', 'That is not valid JSON.', 'Make sure you copied the entire block, including the curly braces { }.');
  } elseif (!isset($input->type) || $input->type === 'regular') {
    $message = checkRegularRoll($input);
  } elseif ($input->type === 'battle') {
    $message = checkBattleRoll($input);
  } else {
    $message = callout('error', 'Unknown roll type supplied.', 'The "type" field must be either "regular" or "battle".');
  }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Provably Fair Validator &mdash; Verify your Rolls</title>
  <style>
    :root {
      --bg: #0f1320;
      --bg-2: #151a2c;
      --panel: #1b2138;
      --panel-2: #212949;
      --border: #2c3457;
      --text: #e7eaf6;
      --muted: #9aa3c7;
      --brand: #6c8cff;
      --brand-2: #8a6cff;
      --ok: #34d399;
      --ok-bg: rgba(52, 211, 153, .12);
      --fail: #f87171;
      --fail-bg: rgba(248, 113, 113, .12);
      --warn: #fbbf24;
      --warn-bg: rgba(251, 191, 36, .12);
      --info: #60a5fa;
      --info-bg: rgba(96, 165, 250, .12);
      --radius: 16px;
      --shadow: 0 18px 50px rgba(0, 0, 0, .45);
    }

    * { box-sizing: border-box; }

    body {
      margin: 0;
      min-height: 100vh;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
      color: var(--text);
      line-height: 1.5;
      background:
        radial-gradient(1100px 600px at 12% -10%, rgba(108, 140, 255, .18), transparent 60%),
        radial-gradient(900px 500px at 100% 0%, rgba(138, 108, 255, .16), transparent 55%),
        var(--bg);
      padding: 32px 18px 64px;
    }

    .wrap { max-width: 760px; margin: 0 auto; }

    /* Header */
    .hero { text-align: center; margin-bottom: 26px; }
    .badge {
      display: inline-flex; align-items: center; gap: 8px;
      padding: 6px 14px; border-radius: 999px;
      background: rgba(108, 140, 255, .12);
      border: 1px solid var(--border);
      color: var(--brand);
      font-size: .78rem; font-weight: 600; letter-spacing: .03em;
      text-transform: uppercase;
    }
    .hero h1 {
      margin: 16px 0 8px;
      font-size: 2rem; line-height: 1.15; font-weight: 800;
      background: linear-gradient(90deg, #fff, #b9c4ff);
      -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent;
    }
    .hero p { margin: 0 auto; max-width: 540px; color: var(--muted); font-size: 1rem; }

    /* Cards */
    .card {
      background: linear-gradient(180deg, var(--panel), var(--bg-2));
      border: 1px solid var(--border);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      padding: 22px;
      margin-bottom: 20px;
    }
    .card h2 {
      margin: 0 0 14px;
      font-size: 1.05rem; font-weight: 700;
      display: flex; align-items: center; gap: 9px;
    }
    .card h2 .dot {
      width: 26px; height: 26px; border-radius: 8px; flex: none;
      display: grid; place-items: center;
      background: rgba(108, 140, 255, .15); color: var(--brand);
      font-size: .95rem;
    }

    /* Tips */
    .tips { list-style: none; margin: 0; padding: 0; counter-reset: step; }
    .tips li {
      position: relative; padding: 10px 0 10px 44px;
      border-bottom: 1px dashed var(--border); color: var(--muted);
    }
    .tips li:last-child { border-bottom: 0; padding-bottom: 0; }
    .tips li strong { color: var(--text); }
    .tips li::before {
      counter-increment: step; content: counter(step);
      position: absolute; left: 0; top: 9px;
      width: 28px; height: 28px; border-radius: 50%;
      display: grid; place-items: center;
      background: linear-gradient(135deg, var(--brand), var(--brand-2));
      color: #0b1020; font-weight: 800; font-size: .85rem;
    }

    .glossary { margin-top: 14px; border-top: 1px solid var(--border); padding-top: 6px; }
    details summary {
      cursor: pointer; list-style: none; padding: 10px 0;
      color: var(--brand); font-weight: 600; font-size: .92rem;
      display: flex; align-items: center; gap: 8px;
    }
    details summary::-webkit-details-marker { display: none; }
    details summary::before { content: "&#9656;"; transition: transform .15s ease; }
    details[open] summary::before { transform: rotate(90deg); }
    .glossary dl { margin: 4px 0 0; display: grid; grid-template-columns: 1fr; gap: 10px; }
    .glossary dt { font-weight: 700; color: var(--text); font-size: .9rem; }
    .glossary dd { margin: 2px 0 0; color: var(--muted); font-size: .88rem; }
    .glossary code {
      background: var(--panel-2); border: 1px solid var(--border);
      padding: 1px 6px; border-radius: 6px; font-size: .82rem; color: #cdd6ff;
    }

    /* Form */
    .field-label { display: block; font-weight: 600; margin-bottom: 8px; }
    .hint { color: var(--muted); font-weight: 400; font-size: .85rem; }
    textarea {
      width: 100%; min-height: 190px; resize: vertical;
      background: var(--bg); color: var(--text);
      border: 1px solid var(--border); border-radius: 12px;
      padding: 14px 16px; font-size: .9rem; line-height: 1.5;
      font-family: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, monospace;
      transition: border-color .15s ease, box-shadow .15s ease;
    }
    textarea:focus {
      outline: none; border-color: var(--brand);
      box-shadow: 0 0 0 4px rgba(108, 140, 255, .18);
    }

    .actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 14px; align-items: center; }
    .btn {
      appearance: none; cursor: pointer; border: 1px solid transparent;
      padding: 11px 20px; border-radius: 11px; font-size: .92rem; font-weight: 700;
      transition: transform .08s ease, filter .15s ease, background .15s ease;
    }
    .btn:active { transform: translateY(1px); }
    .btn-primary { background: linear-gradient(135deg, var(--brand), var(--brand-2)); color: #0b1020; }
    .btn-primary:hover { filter: brightness(1.08); }
    .btn-ghost { background: var(--panel-2); color: var(--text); border-color: var(--border); }
    .btn-ghost:hover { background: #283157; }
    .spacer { flex: 1; }
    .sample-label { color: var(--muted); font-size: .82rem; }

    /* Results */
    .results { animation: fade .25s ease; }
    @keyframes fade { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }

    .summary {
      display: flex; align-items: center; gap: 14px;
      padding: 16px 18px; border-radius: 12px; margin-bottom: 16px;
      border: 1px solid var(--border);
    }
    .summary div { display: flex; flex-direction: column; }
    .summary span:last-child { color: var(--muted); font-size: .9rem; }
    .summary-icon { font-size: 1.4rem; width: 40px; height: 40px; flex: none; border-radius: 10px; display: grid; place-items: center; }
    .summary-ok { background: var(--ok-bg); }
    .summary-ok .summary-icon { background: rgba(52,211,153,.18); color: var(--ok); }
    .summary-ok strong { color: var(--ok); }
    .summary-fail { background: var(--fail-bg); }
    .summary-fail .summary-icon { background: rgba(248,113,113,.18); color: var(--fail); }
    .summary-fail strong { color: var(--fail); }

    .cmp-card { border: 1px solid var(--border); border-radius: 12px; padding: 16px; margin-bottom: 14px; background: var(--bg); }
    .cmp-title { font-weight: 700; font-size: .82rem; text-transform: uppercase; letter-spacing: .05em; color: var(--muted); margin-bottom: 10px; }
    .cmp-row { display: flex; justify-content: space-between; gap: 14px; padding: 7px 0; border-bottom: 1px solid var(--border); }
    .cmp-row:last-of-type { border-bottom: 0; }
    .cmp-label { color: var(--muted); font-size: .88rem; flex: none; }
    .cmp-value { font-weight: 700; text-align: right; word-break: break-all; }
    .cmp-value.mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-weight: 600; font-size: .82rem; }
    .verdict { margin-top: 12px; padding: 9px 12px; border-radius: 9px; font-weight: 600; font-size: .9rem; display: flex; align-items: center; gap: 8px; }
    .verdict-icon { font-size: 1rem; }
    .verdict-ok { background: var(--ok-bg); color: var(--ok); }
    .verdict-fail { background: var(--fail-bg); color: var(--fail); }

    /* Callouts (errors / warnings) */
    .callout { display: flex; gap: 12px; padding: 14px 16px; border-radius: 12px; border: 1px solid var(--border); }
    .callout-icon { font-size: 1.2rem; flex: none; }
    .callout-text { display: flex; flex-direction: column; gap: 2px; }
    .callout-text span { color: var(--muted); font-size: .9rem; }
    .callout-error { background: var(--fail-bg); } .callout-error .callout-icon, .callout-error strong { color: var(--fail); }
    .callout-warning { background: var(--warn-bg); } .callout-warning .callout-icon, .callout-warning strong { color: var(--warn); }
    .callout-info { background: var(--info-bg); } .callout-info .callout-icon, .callout-info strong { color: var(--info); }

    /* Footer */
    .footer { text-align: center; color: var(--muted); font-size: .85rem; margin-top: 26px; }
    .footer a { color: var(--brand); text-decoration: none; }
    .footer a:hover { text-decoration: underline; }
    .footer .run-btn { display: inline-block; margin-bottom: 14px; }
    .footer .run-btn img { height: 34px; vertical-align: middle; }
    .footer .footer-links { display: flex; justify-content: center; gap: 16px; }

    @media (max-width: 520px) {
      .hero h1 { font-size: 1.6rem; }
      .cmp-row { flex-direction: column; gap: 2px; }
      .cmp-value { text-align: left; }
    }
  </style>
</head>
<body>
  <div class="wrap">

    <header class="hero">
      <span class="badge">&#128737; Provably Fair</span>
      <h1>Verify your Rolls</h1>
      <p>Paste the roll data from skin.club and we&rsquo;ll recalculate the result independently, so you can confirm it was fair.</p>
    </header>

    <?php if ($message): ?>
    <section class="card results" id="results">
      <h2><span class="dot">&#128202;</span> Verification result</h2>
      <?= $message ?>
    </section>
    <?php endif; ?>

    <section class="card">
      <h2><span class="dot">&#128161;</span> How to verify in 4 steps</h2>
      <ol class="tips">
        <li>Open the <strong>&ldquo;Check Roll&rdquo;</strong> (Fairness) page on skin.club for the drop you want to verify.</li>
        <li><strong>Copy the JSON</strong> block shown there &mdash; including the curly braces <code style="color:var(--muted)">{ }</code>.</li>
        <li><strong>Paste it</strong> into the box below. No need to clean it up.</li>
        <li>Hit <strong>Check!</strong> and read the result &mdash; green means provably fair.</li>
      </ol>

      <div class="glossary">
        <details>
          <summary>What do these fields mean?</summary>
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
      </div>
    </section>

    <section class="card">
      <h2><span class="dot">&#9989;</span> Check a roll</h2>
      <form method="post" action="/">
        <label class="field-label" for="roll_data">Enter your roll data <span class="hint">&mdash; paste the JSON from the site</span></label>
        <textarea id="roll_data" name="roll_data" placeholder='{
  "server_seed": "...",
  "secret_salt": "...",
  "public_hash": "...",
  "client_seed": "...",
  "nonce": "...",
  "roll": "..."
}'><?= htmlspecialchars($_POST['roll_data'] ?? '') ?></textarea>

        <div class="actions">
          <button type="submit" class="btn btn-primary">Check!</button>
          <span class="spacer"></span>
          <span class="sample-label">Try a sample:</span>
          <button type="button" class="btn btn-ghost" onclick="loadSample('regular')">Regular</button>
          <button type="button" class="btn btn-ghost" onclick="loadSample('battle')">Battle</button>
        </div>
      </form>
    </section>

    <div class="footer">
      <a class="run-btn" href="https://replit.com/github/skinclub-tech/provably-fair-validator" target="_blank" rel="noopener">
        <img src="https://replit.com/badge/github/skinclub-tech/provably-fair-validator" alt="Run on Replit">
      </a>
      <div class="footer-links">
        <a href="https://github.com/skinclub-tech/provably-fair-validator" target="_blank" rel="noopener">Source Code</a>
      </div>
    </div>

  </div>

  <script>
    const SAMPLES = {
      regular: {
        server_seed: "c4ca4238a0b92382",
        secret_salt: "0dcc509a6f75849b",
        public_hash: "dc883b29588c1204fcad00984aaa2404c2251f9a0e5300106eb39aaebcc0f493",
        client_seed: "my_seed",
        nonce: "4",
        roll: "21752"
      },
      battle: {
        type: "battle",
        beacon: "Tt5qAdTwoTeygDdghVlfEWtNJQkGYg5q",
        client_seed: "12354,abgd",
        nonce: "9",
        roll: "5415"
      }
    };
    function loadSample(kind) {
      const ta = document.getElementById('roll_data');
      ta.value = JSON.stringify(SAMPLES[kind], null, 2);
      ta.focus();
    }
  </script>
</body>
</html>
