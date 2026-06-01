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

check('valid regular roll verifies',
  str_contains(verifyRollData(json_encode($regular)), 'summary-ok'));

check('valid battle roll verifies',
  str_contains(verifyRollData(json_encode($battle)), 'summary-ok'));

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

$unknown = $regular;
$unknown['type'] = 'lottery';
check('unknown roll type shows error',
  str_contains(verifyRollData(json_encode($unknown)), 'callout-error'));

$regularMissingField = $regular;
unset($regularMissingField['public_hash']);
check('regular payload missing a required field shows error',
  str_contains(verifyRollData(json_encode($regularMissingField)), 'callout-error'));

$regularMissingRoll = $regular;
unset($regularMissingRoll['roll']);
check('regular payload missing roll shows error',
  str_contains(verifyRollData(json_encode($regularMissingRoll)), 'callout-error'));

$battleMissingField = $battle;
unset($battleMissingField['beacon']);
check('battle payload missing a required field shows error',
  str_contains(verifyRollData(json_encode($battleMissingField)), 'callout-error'));

$emptyField = $regular;
$emptyField['client_seed'] = '';
check('empty field value is treated as missing',
  str_contains(verifyRollData(json_encode($emptyField)), 'callout-error'));

$whitespaceField = $regular;
$whitespaceField['client_seed'] = '   ';
check('whitespace-only field value is treated as missing',
  str_contains(verifyRollData(json_encode($whitespaceField)), 'callout-error'));

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
