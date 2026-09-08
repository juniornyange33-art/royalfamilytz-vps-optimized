<?php
declare(strict_types=1);
$secureSession = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
if (PHP_VERSION_ID >= 70300) session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $secureSession, 'httponly' => true, 'samesite' => 'Lax']);
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/clickpesa.php';
require_once __DIR__ . '/google.php';
require_once __DIR__ . '/mailer.php';

const APP_NAME = 'Royal Family TZ';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = rtrim($path, '/') ?: '/';
$basePath = str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/index.php')));
$basePath = ($basePath === '.' || $basePath === '/') ? '' : rtrim($basePath, '/');
if ($basePath !== '' && ($path === $basePath || str_starts_with($path, $basePath . '/'))) { $path = substr($path, strlen($basePath)) ?: '/'; }
$user = $_SESSION['user'] ?? null;

if ($path === '/logout') { session_destroy(); header('Location: /'); exit; }
if ($path === '/verify-email') {
    try { 
        ensure_profile_columns(); 
        $token = (string)($_GET['token'] ?? ''); 
        $hash = hash('sha256', $token); 
        $stmt = db()->prepare('SELECT id, name, email FROM users WHERE email_verification_token = ? AND email_verification_expires_at > NOW() LIMIT 1'); 
        $stmt->execute([$hash]); 
        $account = $stmt->fetch(); 
        if (!$account) throw new RuntimeException('This verification link is invalid or has expired.'); 
        db()->prepare('UPDATE users SET email_verified_at = NOW(), email_verification_token = NULL, email_verification_expires_at = NULL WHERE id = ?')->execute([(int)$account['id']]); 
        if (mail_configured()) { 
            try { 
                send_email((string)$account['email'], 'Welcome to Royal Family TZ', "Hello {$account['name']},\n\nThank you for verifying your email and joining Royal Family TZ. Your account is now active.\n\nWelcome to the family,\nRoyal Family TZ"); 
            } catch (Throwable $welcomeError) {} 
        } 
        $_SESSION['flash'] = 'Email verified successfully. A welcome message has been sent to your email.'; 
    } catch (Throwable $e) { 
        $_SESSION['flash'] = $e->getMessage(); 
    } 
    header('Location: /login'); exit;
}

if ($path === '/api/clickpesa/webhook' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $payload = json_decode(file_get_contents('php://input'), true) ?: [];
    if (!cp_verify_checksum($payload)) { http_response_code(401); echo json_encode(['error' => 'Invalid checksum']); exit; }
    try {
        $data = $payload['data'] ?? []; $reference = $data['orderReference'] ?? '';
        if ($reference !== '') {
            $event = strtoupper(trim((string)($payload['event'] ?? ''))); 
            $providerStatus = strtoupper(trim((string)($data['status'] ?? ''))); 
            $status = ($event === 'PAYMENT RECEIVED' || ($event === '' && $providerStatus === 'SUCCESS')) ? 'paid' : (($event === 'PAYMENT FAILED' || ($event === '' && $providerStatus === 'FAILED')) ? 'failed' : null);
            if ($status) {
                db()->prepare('UPDATE transactions SET status = ?, provider_ref = COALESCE(provider_ref, ?), failure_message = ? WHERE order_reference = ?')->execute([$status, $data['id'] ?? null, $data['message'] ?? null, $reference]);
                if ($status === 'paid') { 
                    db()->prepare('UPDATE users u JOIN transactions t ON t.user_id = u.id SET u.membership_active = IF(t.type = \'subscription\', 1, u.membership_active), u.membership_id = IF(t.type = \'subscription\' AND u.membership_id IS NULL, CONCAT(\'RFTZ-\', LPAD(u.id, 6, \'0\')), u.membership_id) WHERE t.order_reference = ?')->execute([$reference]); 
                    try { 
                        db()->prepare('UPDATE trip_bookings b JOIN transactions t ON t.id = b.transaction_id SET b.status = \'paid\' WHERE t.order_reference = ?')->execute([$reference]); 
                        
                        // Send trip ticket email upon successful booking payment
                        $stmtBk = db()->prepare('SELECT b.id as booking_id, u.email, u.name as user_name, u.membership_tier, t_trip.title as trip_title, t_trip.destination, t_trip.trip_date FROM trip_bookings b JOIN transactions t ON t.id = b.transaction_id JOIN users u ON u.id = b.user_id JOIN trips t_trip ON t_trip.id = b.trip_id WHERE t.order_reference = ?');
                        $stmtBk->execute([$reference]);
                        $bk = $stmtBk->fetch();
                        if ($bk && mail_configured()) {
                            send_trip_ticket_email($bk);
                        }
                    } catch (Throwable $ignore) {} 
                }
                if ($status === 'failed') { try { db()->prepare('UPDATE trip_bookings b JOIN transactions t ON t.id = b.transaction_id SET b.status = \'cancelled\' WHERE t.order_reference = ?')->execute([$reference]); } catch (Throwable $ignore) {} }
            }
        }
        echo json_encode(['received' => true]);
    } catch (Throwable $e) { http_response_code(500); echo json_encode(['error' => 'Webhook processing failed']); }
    exit;
}

if ($path === '/api/transaction-status' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $reference = trim((string)($_GET['reference'] ?? ''));
        if ($reference === '') throw new RuntimeException('Missing transaction reference.');
        $stmt = db()->prepare('SELECT t.status, t.type, t.amount, t.currency, t.order_reference, COALESCE(u.name, ?) AS user_name FROM transactions t LEFT JOIN users u ON u.id = t.user_id WHERE t.order_reference = ? AND (t.user_id = ? OR (? IS NULL AND t.user_id IS NULL)) LIMIT 1');
        $uid = $user['id'] ?? null;
        $stmt->execute(['Donor', $reference, $uid, $uid]);
        $tx = $stmt->fetch();
        if (!$tx) { http_response_code(404); echo json_encode(['error' => 'Transaction not found']); exit; }
        if ($tx['status'] === 'paid' || $tx['status'] === 'failed') { if (($_SESSION['active_payment']['reference'] ?? '') === $reference) unset($_SESSION['active_payment']); } 
        echo json_encode(['status' => $tx['status'], 'type' => $tx['type'], 'amount' => $tx['amount'], 'currency' => $tx['currency'], 'reference' => $tx['order_reference'], 'name' => $tx['user_name']]);
    } catch (Throwable $e) { http_response_code(400); echo json_encode(['error' => $e->getMessage()]); }
    exit;
}

if (in_array($path, ['/admin/report-users.csv', '/admin/report-transactions.csv'], true) && $user && $user['role'] === 'admin') {
    header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename=' . ($path === '/admin/report-users.csv' ? 'royalfamilytz-users.csv' : 'royalfamilytz-transactions.csv')); $out = fopen('php://output', 'w');
    if ($path === '/admin/report-users.csv') { fputcsv($out, ['User ID','Name','Email','Role','Membership ID','Tier','Membership Active','Created At']); foreach (db()->query('SELECT id, name, email, role, membership_id, membership_tier, membership_active, created_at FROM users ORDER BY created_at DESC') as $row) fputcsv($out, [$row['id'], $row['name'], $row['email'], $row['role'], $row['membership_id'] ?? '', $row['membership_tier'] ?? 'Standard', $row['membership_active'] ? 'Yes' : 'No', $row['created_at']]); }
    else { fputcsv($out, ['Transaction ID','Order Reference','User','Email','Type','Package/Tier','Amount','Currency','Method','Status','Created At']); foreach (db()->query('SELECT t.id, t.order_reference, COALESCE(u.name, \'Guest\') AS user_name, COALESCE(u.email, \'\') AS email, t.type, t.tier, t.amount, t.currency, t.method, t.status, t.created_at FROM transactions t LEFT JOIN users u ON u.id = t.user_id ORDER BY t.created_at DESC') as $row) fputcsv($out, [$row['id'], $row['order_reference'], $row['user_name'], $row['email'], $row['type'], $row['tier'], $row['amount'], $row['currency'], $row['method'], $row['status'], $row['created_at']]); }
    fclose($out); exit;
}

if ($path === '/auth/google/start') { if (!google_enabled()) { $_SESSION['flash'] = 'Google sign-in is not configured.'; header('Location: /signup'); exit; } header('Location: ' . google_auth_url()); exit; }
if ($path === '/auth/google/callback') {
    try {
        if (!google_enabled()) throw new RuntimeException('Google sign-in is not configured yet.');
        if (empty($_GET['code'])) throw new RuntimeException('Google did not return an authorization code.');
        $expectedState = (string)($_SESSION['google_oauth_state'] ?? '');
        $returnedState = (string)($_GET['state'] ?? '');
        if ($expectedState === '' || $returnedState === '' || !hash_equals($expectedState, $returnedState)) throw new RuntimeException('Google sign-in session expired.');
        unset($_SESSION['google_oauth_state']);
        $profile = google_exchange_code((string)$_GET['code']);
        ensure_profile_columns();
        $stmt = db()->prepare('SELECT id, name, email, password_hash, membership_active, membership_tier, role, membership_id, profile_image FROM users WHERE google_id = ? OR email = ? LIMIT 1'); 
        $stmt->execute([$profile['sub'], strtolower($profile['email'])]); 
        $account = $stmt->fetch();
        if ($account) { 
            db()->prepare('UPDATE users SET google_id = ?, avatar_url = ?, name = ?, email_verified_at = COALESCE(email_verified_at, NOW()), email_verification_token = NULL, email_verification_expires_at = NULL WHERE id = ?')->execute([$profile['sub'], $profile['picture'] ?? null, $profile['name'] ?? $account['name'], $account['id']]); 
        } else { 
            $stmt = db()->prepare('INSERT INTO users (name,email,password_hash,google_id,avatar_url,email_verified_at) VALUES (?,?,?,?,?,NOW())'); 
            $stmt->execute([$profile['name'] ?? $profile['email'], strtolower($profile['email']), null, $profile['sub'], $profile['picture'] ?? null]); 
            $account = ['id'=>db()->lastInsertId(),'name'=>$profile['name'] ?? $profile['email'],'email'=>strtolower($profile['email']),'membership_active'=>0,'membership_tier'=>'Spring Green','role'=>'member','membership_id'=>null,'profile_image'=>null]; 
        }
        $_SESSION['user'] = ['id'=>(int)$account['id'],'name'=>$account['name'],'email'=>$account['email'],'membership'=>(bool)$account['membership_active'],'tier'=>$account['membership_tier'] ?? 'Spring Green','role'=>$account['role'],'membership_id'=>$account['membership_id'],'profile_image'=>$account['profile_image'] ?? null,'avatar_url'=>$profile['picture'] ?? null];
        header('Location: ' . app_base_path() . ($account['role'] === 'admin' ? '/admin' : '/dashboard')); exit;
    } catch (Throwable $e) { $_SESSION['flash'] = $e->getMessage(); header('Location: ' . app_base_path() . '/login'); exit; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'trip_book' && $user) {
        try { 
            ensure_trip_tables(); 
            $tripId = (int)($_POST['trip_id'] ?? 0); 
            $packageId = (int)($_POST['package_id'] ?? 0); 
            $guests = max(1, (int)($_POST['guests'] ?? 1)); 
            
            $stmt = db()->prepare('SELECT t.id as trip_id, t.title, t.destination, t.trip_date, p.name, p.price, p.capacity FROM trips t JOIN trip_packages p ON p.trip_id = t.id WHERE t.id = ? AND p.id = ? AND t.published = 1'); 
            $stmt->execute([$tripId, $packageId]); 
            $package = $stmt->fetch(); 
            if (!$package) throw new RuntimeException('That trip package is no longer available.'); 
            
            if ($package['capacity'] !== null) { 
                $used = db()->prepare("SELECT COALESCE(SUM(guests),0) FROM trip_bookings WHERE package_id = ? AND status IN ('pending','paid')"); 
                $used->execute([$packageId]); 
                if ((int)$used->fetchColumn() + $guests > (int)$package['capacity']) throw new RuntimeException('There are not enough places left in this package.'); 
            } 
            
            $amount = (float)$package['price'] * $guests; 
            $reference = 'TP' . date('ymdHis') . strtoupper(bin2hex(random_bytes(2))); 
            
            db()->prepare('INSERT INTO transactions (user_id, type, tier, amount, currency, method, status, order_reference) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')->execute([$user['id'], 'trip', $package['name'], $amount, 'TZS', trim($_POST['method'] ?? 'mobile'), 'pending', $reference]); 
            $transactionId = (int)db()->lastInsertId(); 
            
            db()->prepare('INSERT INTO trip_bookings (user_id, trip_id, package_id, transaction_id, guests, status) VALUES (?, ?, ?, ?, ?, ?)')->execute([$user['id'], $tripId, $packageId, $transactionId, $guests, 'pending']); 
            $bookingId = (int)db()->lastInsertId();

            $result = cp_start_payment($amount, trim($_POST['method'] ?? 'mobile'), trim($_POST['phone'] ?? ''), $reference, $user['name'], $user['email']); 
            db()->prepare('UPDATE transactions SET provider_ref = ?, channel = ? WHERE id = ?')->execute([$result['id'] ?? null, $result['channel'] ?? ($_POST['method'] ?? 'mobile'), $transactionId]); 
            
            $_SESSION['active_payment'] = ['reference' => $reference, 'type' => 'trip', 'name' => $user['name']]; 

            // Auto-send instant notification ticket email
            if (mail_configured()) {
                send_trip_ticket_email([
                    'booking_id' => $bookingId,
                    'email' => $user['email'],
                    'user_name' => $user['name'],
                    'membership_tier' => $user['tier'] ?? 'Spring Green',
                    'trip_title' => $package['title'],
                    'destination' => $package['destination'],
                    'trip_date' => $package['trip_date']
                ]);
            }

            if (!empty($result['checkoutLink'])) { header('Location: ' . $result['checkoutLink']); exit; } 
            $_SESSION['flash'] = 'Booking created! Ticket has been dispatched to your email. Please approve payment on your phone.'; 
        } catch (Throwable $e) { $_SESSION['flash'] = 'Trip booking could not start: ' . $e->getMessage(); } 
        header('Location: /trips'); exit;
    }

    if ($action === 'login') {
        try {
            $email = strtolower(trim($_POST['email'] ?? ''));
            $stmt = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $account = $stmt->fetch();
            if ($account && password_verify($_POST['password'] ?? '', (string)$account['password_hash'])) {
                $_SESSION['user'] = [
                    'id' => (int)$account['id'], 
                    'name' => $account['name'], 
                    'email' => $account['email'], 
                    'membership' => (bool)($account['membership_active'] ?? 0), 
                    'tier' => $account['membership_tier'] ?? 'Spring Green',
                    'role' => $account['role'] ?? 'member', 
                    'membership_id' => $account['membership_id'] ?? null, 
                    'profile_image' => $account['profile_image'] ?? null, 
                    'avatar_url' => $account['avatar_url'] ?? null
                ];
                header('Location: ' . app_base_path() . ($account['role'] === 'admin' ? '/admin' : '/dashboard')); exit;
            }
            $_SESSION['flash'] = 'Invalid email or password.';
        } catch (Throwable $e) { $_SESSION['flash'] = 'Login failed: ' . $e->getMessage(); }
        header('Location: ' . app_base_path() . '/login'); exit;
    }

    if ($action === 'signup') {
        try {
            ensure_profile_columns();
            $name = trim($_POST['name'] ?? ''); $email = strtolower(trim($_POST['email'] ?? '')); $password = $_POST['password'] ?? '';
            if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6) throw new RuntimeException('Invalid signup details.');
            $token = bin2hex(random_bytes(32));
            $stmt = db()->prepare('INSERT INTO users (name, email, password_hash, email_verification_token, email_verification_expires_at) VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))');
            $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), hash('sha256', $token)]);
            send_verification_email(['name' => $name, 'email' => $email], $token);
            $_SESSION['flash'] = 'Account created. Please check your email to verify.';
            header('Location: /login'); exit;
        } catch (Throwable $e) { $_SESSION['flash'] = $e->getMessage(); header('Location: /signup'); exit; }
    }

    if ($action === 'contact') { 
        try { 
            ensure_contact_columns(); 
            $name = trim($_POST['name'] ?? ''); 
            $email = trim($_POST['email'] ?? ''); 
            $message = trim($_POST['message'] ?? ''); 
            if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $message === '') throw new RuntimeException('Please fill all required inputs.'); 
            db()->prepare('INSERT INTO contact_messages (name, email, message, status) VALUES (?, ?, ?, \'new\')')->execute([$name, $email, $message]); 
            $_SESSION['flash'] = 'Thanks — your message was sent successfully.'; 
        } catch (Throwable $e) { $_SESSION['flash'] = $e->getMessage(); } 
        header('Location: /contact'); exit; 
    }

    if ($action === 'subscribe') {
        try {
            if (!$user) throw new RuntimeException('Please log in before subscribing.');
            $amount = (float)($_POST['amount'] ?? 0);
            $tier = $_POST['tier'] ?? 'Spring Green';
            $method = trim($_POST['method'] ?? 'mobile'); 
            $phone = trim($_POST['phone'] ?? ''); 
            $reference = 'RF' . date('ymdHis') . strtoupper(bin2hex(random_bytes(2)));
            
            db()->prepare('INSERT INTO transactions (user_id, type, tier, amount, currency, method, status, order_reference) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')->execute([$user['id'], 'subscription', $tier, $amount, 'TZS', $method, 'pending', $reference]);
            
            // Update User Tier
            db()->prepare('UPDATE users SET membership_tier = ? WHERE id = ?')->execute([$tier, $user['id']]);
            $_SESSION['user']['tier'] = $tier;

            $result = cp_start_payment($amount, $method, $phone, $reference, $user['name'], $user['email']);
            $_SESSION['active_payment'] = ['reference' => $reference, 'type' => 'subscription', 'name' => $user['name']];
            
            if (!empty($result['checkoutLink'])) { header('Location: ' . $result['checkoutLink']); exit; }
            $_SESSION['flash'] = 'Membership subscription request sent! Approve it on your mobile device.';
        } catch (Throwable $e) { $_SESSION['flash'] = $e->getMessage(); }
        header('Location: /members'); exit;
    }

    if ($action === 'donate') {
        try {
            $amount = (float)($_POST['amount'] ?? 0);
            $method = trim($_POST['method'] ?? 'mobile'); 
            $phone = trim($_POST['phone'] ?? ''); 
            $reference = 'RFD' . date('ymdHis') . strtoupper(bin2hex(random_bytes(2)));
            
            db()->prepare('INSERT INTO transactions (user_id, type, tier, amount, currency, method, status, order_reference) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')->execute([$user['id'] ?? null, 'donation', 'Donation', $amount, 'TZS', $method, 'pending', $reference]);
            $result = cp_start_payment($amount, $method, $phone, $reference, $user['name'] ?? 'Donor', $user['email'] ?? 'donor@royalfamilytz.org');
            
            $_SESSION['active_payment'] = ['reference' => $reference, 'type' => 'donation', 'name' => $user['name'] ?
