<?php
declare(strict_types=1);
// Usage: php scripts/simulate_clickpesa_webhook.php ORDER_REFERENCE [paid|failed] [https://royalfamilytz.org]
$ref = $argv[1] ?? ''; if (!$ref) { echo "Usage: php scripts/simulate_clickpesa_webhook.php ORDER_REFERENCE [paid|failed] [site_url]\n"; exit(1); }
$statusArg = strtolower($argv[2] ?? 'paid'); $site = rtrim($argv[3] ?? 'https://royalfamilytz.org', '/');
require_once __DIR__ . '/../clickpesa.php';

$event = $statusArg === 'failed' ? 'PAYMENT FAILED' : 'PAYMENT RECEIVED';
$providerStatus = $statusArg === 'failed' ? 'FAILED' : 'SUCCESS';
$payload = [
    'event' => $event,
    'data' => [
        'orderReference' => $ref,
        'status' => $providerStatus,
        'id' => 'SIM' . time(),
        'message' => $statusArg === 'failed' ? 'Simulated failure' : 'Simulated success'
    ]
];
$payloadWithChecksum = cp_with_checksum($payload);
$json = json_encode($payloadWithChecksum, JSON_UNESCAPED_SLASHES);

$ch = curl_init($site . '/api/clickpesa/webhook');
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$json, CURLOPT_HTTPHEADER=>['Content-Type: application/json']]);
$response = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);

echo "POST to {$site}/api/clickpesa/webhook\nHTTP {$code}\n";
if ($response !== false) echo "Response: {$response}\n"; if ($err) echo "Curl error: {$err}\n";
