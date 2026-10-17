<?php

/**
 * Checks the analytics metadata produced by verifyRollData(): every outcome
 * must carry a fixed reason code, and no value from the pasted JSON may leak.
 *
 * Run with: php tests/analytics-tests.php
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

/** Run a verification and return [analytics, html, thrown error message]. */
function analyticsFor(string $json): array
{
  $detected = null;
  $analytics = null;
  try {
    $html = verifyRollData($json, $detected, $analytics);
    return [$analytics, $html, null];
  } catch (Throwable $e) {
    return [$analytics, '', $e->getMessage()];
  }
}

function expectResult(string $name, string $json, string $type, string $outcome, string $reason, array $extra = []): array
{
  [$analytics, , $error] = analyticsFor($json);
  check("{$name}: no fatal error", $error === null);
  check("{$name}: {$type}/{$outcome}/{$reason}",
    ($analytics['roll_type'] ?? null) === $type
    && ($analytics['outcome'] ?? null) === $outcome
    && ($analytics['reason'] ?? null) === $reason);
  foreach ($extra as $key => $value) {
    check("{$name}: {$key} = " . var_export($value, true), array_key_exists($key, $analytics ?? []) && $analytics[$key] === $value);
  }
  return $analytics ?? [];
}

$regular = [
  'server_seed' => 'c4ca4238a0b92382',
  'secret_salt' => '0dcc509a6f75849b',
  'public_hash' => 'dc883b29588c1204fcad00984aaa2404c2251f9a0e5300106eb39aaebcc0f493',
  'client_seed' => 'my_seed',
  'nonce'       => '4',
  'roll'        => '21752',
];
$battle = [
  'beacon'      => 'Tt5qAdTwoTeygDdghVlfEWtNJQkGYg5q',
  'client_seed' => '12354,abgd',
  'nonce'       => '9',
  'roll'        => '5415',
];
$with = fn(array $base, array $changes) => json_encode(array_replace($base, $changes));
$without = function (array $base, array $keys) {
  foreach ($keys as $key) unset($base[$key]);
  return json_encode($base);
};

echo "Analytics: successful and unrevealed outcomes\n";
expectResult('regular verified', json_encode($regular), 'regular', 'verified', 'all_match');
expectResult('battle verified', json_encode($battle), 'battle', 'verified', 'all_match');
expectResult('decimal nonce keeps working', $with($battle, ['nonce' => '9.0']), 'battle', 'verified', 'all_match');
expectResult('regular seed hidden', $with($regular, ['server_seed' => '*hidden*']), 'regular', 'not_revealed', 'seed_not_revealed');
expectResult('battle beacon hidden', $with($battle, ['beacon' => '***']), 'battle', 'not_revealed', 'beacon_not_generated');

echo "Analytics: mismatch reasons and hints\n";
expectResult('roll mismatch', $with($regular, ['roll' => '1']), 'regular', 'mismatch', 'roll_mismatch',
  ['has_whitespace' => false, 'roll_not_integer' => false, 'hash_case_only' => false]);
expectResult('hash mismatch', $with($regular, ['public_hash' => str_repeat('0', 64)]), 'regular', 'mismatch', 'hash_mismatch',
  ['hash_case_only' => false]);
expectResult('roll and hash mismatch', $with($regular, ['roll' => '1', 'public_hash' => str_repeat('0', 64)]),
  'regular', 'mismatch', 'roll_and_hash_mismatch');
expectResult('uppercase hash', $with($regular, ['public_hash' => strtoupper($regular['public_hash'])]),
  'regular', 'mismatch', 'hash_mismatch', ['hash_case_only' => true, 'has_whitespace' => false]);
expectResult('hash with whitespace', $with($regular, ['public_hash' => $regular['public_hash'] . ' ']),
  'regular', 'mismatch', 'hash_mismatch', ['has_whitespace' => true, 'hash_case_only' => false]);
expectResult('formatted roll', $with($regular, ['roll' => '21,752']), 'regular', 'mismatch', 'roll_mismatch',
  ['roll_not_integer' => true]);
expectResult('battle mismatch', $with($battle, ['roll' => '1']), 'battle', 'mismatch', 'roll_mismatch',
  ['has_whitespace' => false, 'roll_not_integer' => false]);

echo "Analytics: invalid input reasons\n";
expectResult('truncated JSON', '{"server_seed": "abc"', 'unknown', 'invalid_input', 'invalid_json',
  ['starts_with_brace' => true, 'ends_with_brace' => false, 'has_smart_quotes' => false, 'length_bucket' => 'under_100']);
expectResult('smart quotes', "{\u{201C}server_seed\u{201D}: \u{201C}abc\u{201D}}", 'unknown', 'invalid_input', 'invalid_json',
  ['has_smart_quotes' => true, 'ends_with_brace' => true]);
expectResult('JSON array', '[1, 2]', 'unknown', 'invalid_input', 'not_object');
expectResult('both seed fields with unknown type', json_encode($regular + ['beacon' => 'x', 'type' => 'lottery']),
  'unknown', 'invalid_input', 'ambiguous_fields');
expectResult('no seed field with unknown type', json_encode(['type' => 'lottery', 'nonce' => '1']),
  'unknown', 'invalid_input', 'unknown_type');
expectResult('missing fields', $without($regular, ['public_hash', 'nonce']), 'regular', 'invalid_input', 'missing_fields',
  ['missing_fields' => 'public_hash,nonce']);
expectResult('battle missing roll', $without($battle, ['roll']), 'battle', 'invalid_input', 'missing_fields',
  ['missing_fields' => 'roll']);
expectResult('nested object seed', $with($regular, ['server_seed' => new stdClass()]), 'regular', 'invalid_input',
  'invalid_field_types', ['invalid_fields' => 'server_seed']);
expectResult('nested array fields', $with($battle, ['beacon' => ['x'], 'client_seed' => ['y']]), 'battle', 'invalid_input',
  'invalid_field_types', ['invalid_fields' => 'beacon,client_seed']);
expectResult('text nonce', $with($battle, ['nonce' => 'abc']), 'battle', 'invalid_input', 'invalid_nonce');
expectResult('out-of-range nonce', $with($battle, ['nonce' => '1e30']), 'battle', 'mismatch', 'roll_mismatch');

echo "Analytics: user-facing messages for previously fatal inputs\n";
[, $html] = analyticsFor($with($regular, ['server_seed' => new stdClass()]));
check('nested field error names the field', str_contains($html, 'callout-error') && str_contains($html, 'server_seed'));
[, $html] = analyticsFor($with($battle, ['nonce' => 'abc']));
check('text nonce error names nonce', str_contains($html, 'callout-error') && str_contains($html, 'nonce'));

echo "Analytics: privacy\n";
$privateValues = array_merge(array_values($regular), array_values($battle), [strtoupper($regular['public_hash'])]);
$payloads = [
  json_encode($regular), json_encode($battle), $with($regular, ['roll' => '1']),
  $with($regular, ['public_hash' => strtoupper($regular['public_hash'])]), '{"server_seed": "c4ca4238a0b92382"',
];
$leaks = [];
foreach ($payloads as $payload) {
  [$analytics] = analyticsFor($payload);
  foreach ($analytics as $key => $value) {
    if (!is_string($value) && !is_bool($value)) $leaks[] = "{$key} has a non-flat value";
    foreach ($privateValues as $private) {
      if (strlen($private) > 3 && is_string($value) && str_contains($value, $private)) $leaks[] = "{$key} contains input";
    }
  }
}
check('analytics contains only flat fixed values', $leaks === []);

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
