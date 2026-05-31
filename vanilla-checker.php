<?php
/*
For more details on the logic and algorithm please read the guide: https://skin-club.medium.com/provably-fair-1bab9bf10e58

Usage instruction:
1. Authenticate on Repl.com and Fork this Repl (button in the page footer)
2. Deploy this Repl (green button on top right)
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
  echo $hash . "\n";

  $subHash = substr($hash, 0, ROLL_CHARS);
  echo $subHash . "\n";
  $roll = hexdec($subHash) % ROLL_MAX;
  echo hexdec($subHash) . "\n";
  echo $roll . "\n";

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

function checkRegularRoll($data): string
{
  $message = "";

  $req = ['server_seed', 'secret_salt', 'public_hash', 'client_seed', 'nonce', 'roll'];
  if (!is_object($data) || !checkRequiredProps($data, $req)) {
    return '<p class="error">Your input is invalid.<br> Try copy JSON string from the site and paste here.</p>';
  }
  if($data->server_seed[0] === '*' || $data->secret_salt[0] === '*') {
    return '<p class="warning">Server Seed seems to be not yet revealed.<br> It is impossible to verify roll right now.</p>';
  }
  
  // data seems to be valid and we can proceed
  $originalRoll = (int)$data->roll;
  $calculatedRoll = generateRoll($data->server_seed, $data->client_seed, $data->nonce);

  $message .= "<p class='info'>Original Roll is: <b>{$originalRoll}</b> <br> Calculated Roll is: <b>{$calculatedRoll}</b></p>";

  if ($originalRoll === $calculatedRoll) {
    $message .= "<p class='success'>And they are identical!</p>";
  }

  $originalPublicHash = $data->public_hash;
  $calculatedPublicHash = calculatePublicHash($data->server_seed, $data->secret_salt);

  $message .= "<p class='info'>Original Public Hash is:<br><b>{$originalPublicHash}</b> <br> Valid Public Hash (for this Server Seed and Salt) is:<br><b>{$calculatedPublicHash}</b></p>";

  if ($originalPublicHash === $calculatedPublicHash) {
    $message .= "<p class='success'>And they are identical!</p>";
  }
  
  return $message;
}

function checkBattleRoll($data): string
{
  $message = "";

  $req = ['beacon', 'client_seed', 'nonce', 'roll'];
  if (!is_object($data) || !checkRequiredProps($data, $req)) {
    return '<p class="error">Your input is invalid.<br> Try copy JSON string from the site and paste here.</p>';
  }
  if($data->beacon[0] === '*') {
    return '<p class="warning">Beacon seems to be not yet generated.<br> It is impossible to verify roll right now.</p>';
  }
  
  // data seems to be valid and we can proceed
  $originalRoll = (int)$data->roll;
  $calculatedRoll = generateRoll($data->beacon, $data->client_seed, $data->nonce);

  $message .= "<p class='info'>Original Roll is: <b>{$originalRoll}</b> <br> Calculated Roll is: <b>{$calculatedRoll}</b></p>";

  if ($originalRoll === $calculatedRoll) {
    $message .= "<p class='success'>And they are identical!</p>";
  }
  
  return $message;
}

$message = '';
if (!empty($_POST['roll_data'])) {
  $input = json_decode($_POST['roll_data']);
  if (!isset($input->type) || $input->type === 'regular') {
    $message = checkRegularRoll($input);
  } elseif ($input->type === 'battle') {
    $message = checkBattleRoll($input);
  } else {
    $message = "<p class='error'>Unknown roll type supplied.</p>";
  }
}

?>

<html>
<head>
  <title>Verify your Rolls</title>
  <style>
  body {
    margin: 0;
    font-family: -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial;
    font-size: 0.85rem;
    line-height: 1.3;
    color: #212529;
  }

  .messages p {padding: .75rem 1.25rem; margin: 0 0 0.9rem}
  .info {color: #383d41; background-color: #e2e3e5; border-color: #d6d8db}
  .error {color: #721c24; background-color: #f8d7da; border-color: #f5c6cb}
  .warning {color: #856404; background-color: #fff3cd; border-color: #ffeeba}
  .success {color: #155724; background-color: #d4edda; border-color: #c3e6cb; font-weight: bold}
  .success {margin-top: -0.9rem !important}

  .check-form {margin-bottom: 1rem;}
  </style>
</head>
<body>
  <div class="messages">
    <?= $message ?>
  </div>

  <div class="check-form">
    <form method="post" action="/">
      <label for="roll_data">Enter Your Roll Data:</label><br>
      <textarea id="roll_data" rows="10" cols="60" name="roll_data"><?= $_POST['roll_data'] ?? '' ?></textarea>
      <button type="submit">Check!</button>
    </form>
  </div>

  <div class="footer">
    <a href="https://replit.com/@skinclub/provably-fair-validator" target="_blank">Source Code</a>
  </div>
</body>
</html>
