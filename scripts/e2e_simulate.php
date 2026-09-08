<?php
declare(strict_types=1);
// Usage: php scripts/e2e_simulate.php [reference] [site_url]
// Creates a test user, trip, package, transaction and booking then posts a signed webhook.

$ref = $argv[1] ?? ('TP' . date('ymdHis') . strtoupper(substr(bin2hex(random_bytes(2)),0,4)));
$site = rtrim($argv[2] ?? 'https://royalfamilytz.org', '/');

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../clickpesa.php';

echo "Using reference: {$ref}\n";

try {
    $pdo = db();
    $pdo->beginTransaction();

    // create or find test user
    $email = 'e2e-test+' . substr($ref, -6) . '@example.com';
    $name = 'E2E Tester ' . substr($ref, -4);
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user) {
        $userId = (int)$user['id'];
    } else {
        $pdo->prepare('INSERT INTO users (name,email,password_hash,created_at) VALUES (?, ?, ?, NOW())')->execute([$name, $email, null]);
        $userId = (int)$pdo->lastInsertId();
    }

    // create a dummy trip and package
    $pdo->prepare('INSERT INTO trips (title, slug, description, destination, published, created_at) VALUES (?, ?, ?, ?, 1, NOW())')
        ->execute(['E2E Trip ' . $ref, 'e2e-' . $ref, 'Generated for E2E test', 'Testville']);
    $tripId = (int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO trip_packages (trip_id, name, description, price, capacity) VALUES (?, ?, ?, ?, ?)')
        ->execute([$tripId, 'Standard', 'Standard package', 1000, 100]);
    $packageId = (int)$pdo->lastInsertId();

    // create transaction + booking
    $amount = 1000.0;
    $pdo->prepare('INSERT INTO transactions (user_id, type, tier, amount, currency, method, status, order_reference, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())')
        ->execute([$userId, 'trip', 'Standard', $amount, 'TZS', 'mobile', 'pending', $ref]);
    $txId = (int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO trip_bookings (user_id, trip_id, package_id, transaction_id, guests, status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())')
        ->execute([$userId, $tripId, $packageId, $txId, 1, 'pending']);

    $pdo->commit();

    echo "Created user={$userId} trip={$tripId} package={$packageId} tx={$txId}\n";

    // build webhook payload and post
    $payload = [
        'event' => 'PAYMENT RECEIVED',
        'data' => [
            'orderReference' => $ref,
            'status' => 'SUCCESS',
            'id' => 'SIM' . time(),
            'message' => 'E2E simulated success'
        ]
    ];
    $signed = cp_with_checksum($payload);
    $json = json_encode($signed, JSON_UNESCAPED_SLASHES);

    $ch = curl_init($site . '/api/clickpesa/webhook');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$json, CURLOPT_HTTPHEADER=>['Content-Type: application/json']]);
    $resp = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
    echo "Posted webhook to {$site}/api/clickpesa/webhook - HTTP {$code}\n";
    if ($resp !== false) echo "Response: {$resp}\n";
    if ($err) echo "Curl error: {$err}\n";

} catch (Throwable $e) {
    echo "E2E simulation failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "E2E simulation finished. Check transactions and trip_bookings tables and mailer logs.\n";
