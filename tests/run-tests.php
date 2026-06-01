<?php

/**
 * Lightweight test suite for the provably-fair verification logic in index.php.
 *
 * It loads index.php (suppressing its HTML output) to get the verification
 * functions, then exercises generateRoll, calculatePublicHash and the
 * verifyRollData dispatch with known fixtures.
 *
 * Run with: php tests/run-tests.php
 */

ob_start();
require __DIR__ . '/../index.php';
ob_end_clean();

$passed = 0;
$failed = 0;

function check(string $name, bool $cond): void
{
  global $passed, $failed;
  if ($cond) {
    $passed++;
    echo "  PASS  {$name}\n";
  } else {
    $failed++;
    echo "  FAIL  {$name}\n";
  }
}

// Documented sample payloads used as fixtures (mirrors the in-page samples).
$regular = [
  'server_seed' => 'c4ca4238a0b92382',
  'secret_salt' => '0dcc509a6f75849b',
  'public_hash' => 'dc883b29588c1204fcad00984aaa2404c2251f9a0e5300106eb39aaebcc0f493',
  'client_seed' => 'my_seed',
  'nonce'       => '4',
  'roll'        => '21752',
];

$battle = [
  'type'        => 'battle',
  'beacon'      => 'Tt5qAdTwoTeygDdghVlfEWtNJQkGYg5q',
  'client_seed' => '12354,abgd',
  'nonce'       => '9',
  'roll'        => '5415',
];

echo "Unit: core math\n";
check('generateRoll matches known regular fixture',
  generateRoll($regular['server_seed'], $regular['client_seed'], (int)$regular['nonce']) === 21752);
check('generateRoll matches known battle fixture',
  generateRoll($battle['beacon'], $battle['client_seed'], (int)$battle['nonce']) === 5415);
check('calculatePublicHash matches known fixture',
  calculatePublicHash($regular['server_seed'], $regular['secret_salt']) === $regular['public_hash']);

echo "Integration: verifyRollData dispatch\n";

$regularType = '';
$regularOut = verifyRollData(json_encode($regular), $regularType);
check('valid regular roll verifies',
  str_contains($regularOut, 'summary-ok'));
check('regular roll result states it was checked as a regular roll',
  str_contains($regularType, 'Checked as a') && str_contains($regularType, 'regular roll'));

$battleType = '';
$battleOut = verifyRollData(json_encode($battle), $battleType);
check('valid battle roll verifies',
  str_contains($battleOut, 'summary-ok'));
check('battle roll result states it was checked as a battle roll',
  str_contains($battleType, 'Checked as a') && str_contains($battleType, 'battle roll'));

$badRoll = $regular;
$badRoll['roll'] = '12345';
check('mismatched roll fails verification',
  str_contains(verifyRollData(json_encode($badRoll)), 'summary-fail'));

$badHash = $regular;
$badHash['public_hash'] = str_repeat('0', 64);
check('mismatched public hash fails verification',
  str_contains(verifyRollData(json_encode($badHash)), 'summary-fail'));

$unrevealed = $regular;
$unrevealed['server_seed'] = '*hidden*';
check('unrevealed regular seed shows warning',
  str_contains(verifyRollData(json_encode($unrevealed)), 'callout-warning'));

$noBeacon = $battle;
$noBeacon['beacon'] = '*';
check('ungenerated battle beacon shows warning',
  str_contains(verifyRollData(json_encode($noBeacon)), 'callout-warning'));

check('invalid JSON shows error',
  str_contains(verifyRollData('{ not valid json'), 'callout-error'));

// An unknown type only matters when the payload is ambiguous (no seed field to
// detect from); otherwise the detected type wins (see auto-detection below).
$unknown = ['type' => 'lottery', 'client_seed' => 'my_seed', 'nonce' => '4', 'roll' => '21752'];
check('unknown roll type with no seed fields shows error',
  str_contains(verifyRollData(json_encode($unknown)), 'callout-error'));

$regularMissingField = $regular;
unset($regularMissingField['public_hash']);
$regularMissingFieldOut = verifyRollData(json_encode($regularMissingField));
check('regular payload missing a required field shows error',
  str_contains($regularMissingFieldOut, 'callout-error'));
check('regular missing field error names the missing field',
  str_contains($regularMissingFieldOut, 'public_hash'));

$regularMissingRoll = $regular;
unset($regularMissingRoll['roll']);
$regularMissingRollOut = verifyRollData(json_encode($regularMissingRoll));
check('regular payload missing roll shows error',
  str_contains($regularMissingRollOut, 'callout-error'));
check('regular missing roll error names the roll field',
  str_contains($regularMissingRollOut, 'roll'));

$regularMissingMultiple = $regular;
unset($regularMissingMultiple['public_hash'], $regularMissingMultiple['nonce']);
$regularMissingMultipleOut = verifyRollData(json_encode($regularMissingMultiple));
check('regular missing multiple fields names each one',
  str_contains($regularMissingMultipleOut, 'public_hash') && str_contains($regularMissingMultipleOut, 'nonce'));

$battleMissingField = $battle;
unset($battleMissingField['beacon']);
$battleMissingFieldOut = verifyRollData(json_encode($battleMissingField));
check('battle payload missing a required field shows error',
  str_contains($battleMissingFieldOut, 'callout-error'));
check('battle missing field error names the missing field',
  str_contains($battleMissingFieldOut, 'beacon'));

$emptyField = $regular;
$emptyField['client_seed'] = '';
$emptyFieldOut = verifyRollData(json_encode($emptyField));
check('empty field value is treated as missing',
  str_contains($emptyFieldOut, 'callout-error'));
check('empty field error names the empty field',
  str_contains($emptyFieldOut, 'client_seed'));

$whitespaceField = $regular;
$whitespaceField['client_seed'] = '   ';
$whitespaceFieldOut = verifyRollData(json_encode($whitespaceField));
check('whitespace-only field value is treated as missing',
  str_contains($whitespaceFieldOut, 'callout-error'));
check('whitespace field error names the field',
  str_contains($whitespaceFieldOut, 'client_seed'));

echo "Unit: rollSteps intermediates\n";

$steps = rollSteps($regular['server_seed'], $regular['client_seed'], (int)$regular['nonce']);
check('rollSteps message is "client_seed-nonce"',
  $steps['message'] === 'my_seed-4');
check('rollSteps full digest matches hash_hmac(data=server_seed, key=message)',
  $steps['hash'] === hash_hmac('sha512', $regular['server_seed'], $steps['message']));
check('rollSteps subHash is the first 15 hex chars of the digest',
  $steps['subHash'] === substr($steps['hash'], 0, 15) && strlen($steps['subHash']) === 15);
check('rollSteps decimal is the base-10 value of subHash',
  $steps['decimal'] === hexdec($steps['subHash']));
check('rollSteps mod + 1 reproduces the known roll',
  $steps['mod'] + 1 === 21752 && $steps['roll'] === 21752);

echo "Integration: calculation steps spoiler\n";

check('regular result includes the collapsed calculation spoiler',
  str_contains($regularOut, "How this roll was calculated")
  && str_contains($regularOut, "calc-spoiler")
  && !str_contains($regularOut, "calc-spoiler' open"));
check('regular spoiler maps key to client_seed-nonce and message to server_seed',
  str_contains($regularOut, 'HMAC-SHA512(key: &quot;' . $steps['message'] . '&quot;, message: ' . $regular['server_seed'] . ')'));
check('regular spoiler public-hash step maps key to secret_salt and message to server_seed',
  str_contains($regularOut, 'HMAC-SHA256(key: ' . $regular['secret_salt'] . ', message: ' . $regular['server_seed'] . ')'));
// The full digest is shown with its first ROLL_CHARS highlighted via a <span>,
// so it is split in the raw HTML; strip tags to check the visible text content.
check('regular spoiler shows the reproducible full HMAC-SHA512 digest',
  str_contains(strip_tags($regularOut), $steps['hash']));
check('regular spoiler shows the resulting public hash digest',
  str_contains($regularOut, $regular['public_hash']));

$battleSteps = rollSteps($battle['beacon'], $battle['client_seed'], (int)$battle['nonce']);
check('battle result includes the calculation spoiler',
  str_contains($battleOut, "How this roll was calculated") && str_contains($battleOut, "calc-spoiler"));
check('battle spoiler maps key to client_seed-nonce and message to beacon',
  str_contains($battleOut, 'HMAC-SHA512(key: &quot;' . $battleSteps['message'] . '&quot;, message: ' . $battle['beacon'] . ')'));
check('battle spoiler omits the public-hash section',
  !str_contains($battleOut, 'public hash'));
// The battle breakdown highlights the first ROLL_CHARS of the digest via a
// <span> too, so assert the complete digest appears in its split/highlighted form.
check('battle spoiler shows the reproducible full HMAC-SHA512 digest (highlighted)',
  str_contains($battleOut,
    "<span class='calc-hash-hl'>" . substr($battleSteps['hash'], 0, 15) . "</span>" . substr($battleSteps['hash'], 15)));

echo "Integration: roll type auto-detection\n";

// Battle data (has beacon, no server_seed) pasted without a type field is
// auto-detected as a battle roll and verifies successfully.
$battleNoType = $battle;
unset($battleNoType['type']);
$battleNoTypeOut = verifyRollData(json_encode($battleNoType));
check('battle data without a type field verifies as battle',
  str_contains($battleNoTypeOut, 'summary-ok'));
check('battle data without a type field shows no warning or error',
  !str_contains($battleNoTypeOut, 'callout-'));

// Battle data mislabelled as a regular roll still auto-detects as battle.
$battleMislabelled = $battle;
$battleMislabelled['type'] = 'regular';
check('battle data mislabelled as regular still verifies',
  str_contains(verifyRollData(json_encode($battleMislabelled)), 'summary-ok'));

// Regular data (has server_seed, no beacon) mislabelled as a battle roll still
// auto-detects as regular and verifies successfully.
$regularAsBattle = $regular;
$regularAsBattle['type'] = 'battle';
$regularAsBattleOut = verifyRollData(json_encode($regularAsBattle));
check('regular data mislabelled as battle still verifies',
  str_contains($regularAsBattleOut, 'summary-ok'));
check('regular data mislabelled as battle shows no warning or error',
  !str_contains($regularAsBattleOut, 'callout-'));

// Regular data with an unknown type still verifies -- fields win over type.
$regularUnknownType = $regular;
$regularUnknownType['type'] = 'lottery';
check('regular data with an unknown type still verifies',
  str_contains(verifyRollData(json_encode($regularUnknownType)), 'summary-ok'));

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
