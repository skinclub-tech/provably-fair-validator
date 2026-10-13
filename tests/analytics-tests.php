<?php
ob_start();
require __DIR__ . '/../index.php';
ob_end_clean();

$battle = ['beacon' => 'Tt5qAdTwoTeygDdghVlfEWtNJQkGYg5q',
  'client_seed' => '12354,abgd', 'nonce' => '9', 'roll' => '5415'];
$cases = [
  [json_encode($battle), 'battle', 'verified'],
  [json_encode(array_replace($battle, ['roll' => '1'])), 'battle', 'mismatch'],
  [json_encode(array_replace($battle, ['beacon' => '***'])), 'battle', 'not_revealed'],
  [json_encode(['beacon' => 'seed']), 'battle', 'invalid_input'],
  ['not json', 'unknown', 'invalid_input'],
  ['{"type":"unknown"}', 'unknown', 'invalid_input'],
];
foreach ($cases as [$json, $type, $outcome]) {
  $detected = null;
  $analytics = null;
  verifyRollData($json, $detected, $analytics);
  if ($analytics !== ['roll_type' => $type, 'outcome' => $outcome]) {
    throw new RuntimeException("Incorrect analytics categories: {$type}/{$outcome}");
  }
}
echo "PASS: All verification analytics categories contain only allowed metadata.\n";
