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
    try { ensure_profile_columns(); $token = (string)($_GET['token'] ?? ''); $hash = hash('sha256', $token); $stmt = db()->prepare('SELECT id, name, email FROM users WHERE email_verification_token = ? AND email_verification_expires_at > NOW() LIMIT 1'); $stmt->execute([$hash]); $account = $stmt->fetch(); if (!$account) throw new RuntimeException('This verification link is invalid or has expired.'); db()->prepare('UPDATE users SET email_verified_at = NOW(), email_verification_token = NULL, email_verification_expires_at = NULL WHERE id = ?')->execute([(int)$account['id']]); if (mail_configured()) { try { send_email((string)$account['email'], 'Welcome to Royal Family TZ', "Hello {$account['name']},\n\nThank you for verifying your email and joining Royal Family TZ. Your account is now active.\n\nWelcome to the family,\nRoyal Family TZ"); } catch (Throwable $welcomeError) {} } $_SESSION['flash'] = 'Email verified successfully. A welcome message has been sent to your email.'; } catch (Throwable $e) { $_SESSION['flash'] = $e->getMessage(); } header('Location: /login'); exit;
}
if ($path === '/api/clickpesa/webhook' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $payload = json_decode(file_get_contents('php://input'), true) ?: [];
    if (!cp_verify_checksum($payload)) { http_response_code(401); echo json_encode(['error' => 'Invalid checksum']); exit; }
    try {
        $data = $payload['data'] ?? []; $reference = $data['orderReference'] ?? '';
        if ($reference !== '') {
            $event = strtoupper(trim((string)($payload['event'] ?? ''))); $providerStatus = strtoupper(trim((string)($data['status'] ?? ''))); $status = ($event === 'PAYMENT RECEIVED' || ($event === '' && $providerStatus === 'SUCCESS')) ? 'paid' : (($event === 'PAYMENT FAILED' || ($event === '' && $providerStatus === 'FAILED')) ? 'failed' : null);
            if ($status) {
                db()->prepare('UPDATE transactions SET status = ?, provider_ref = COALESCE(provider_ref, ?), failure_message = ? WHERE order_reference = ?')->execute([$status, $data['id'] ?? null, $data['message'] ?? null, $reference]);
                if ($status === 'paid') { 
                    db()->prepare('UPDATE users u JOIN transactions t ON t.user_id = u.id SET u.membership_active = IF(t.type = \'subscription\', 1, u.membership_active), u.membership_tier = IF(t.type = \'subscription\', t.tier, u.membership_tier), u.membership_id = IF(t.type = \'subscription\' AND u.membership_id IS NULL, CONCAT(\'RFTZ-\', LPAD(u.id, 6, \'0\')), u.membership_id) WHERE t.order_reference = ?')->execute([$reference]); 
                    try { 
                        db()->prepare('UPDATE trip_bookings b JOIN transactions t ON t.id = b.transaction_id SET b.status = \'paid\' WHERE t.order_reference = ?')->execute([$reference]); 
                        send_trip_ticket_email($reference);
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
        if ($tx['status'] === 'paid' || $tx['status'] === 'failed') { if (($_SESSION['active_payment']['reference'] ?? '') === $reference) unset($_SESSION['active_payment']); } echo json_encode(['status' => $tx['status'], 'type' => $tx['type'], 'amount' => $tx['amount'], 'currency' => $tx['currency'], 'reference' => $tx['order_reference'], 'name' => $tx['user_name']]);
    } catch (Throwable $e) { http_response_code(400); echo json_encode(['error' => $e->getMessage()]); }
    exit;
}
if (in_array($path, ['/admin/report-users.csv', '/admin/report-transactions.csv'], true) && $user && $user['role'] === 'admin') {
    header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename=' . ($path === '/admin/report-users.csv' ? 'royalfamilytz-users.csv' : 'royalfamilytz-transactions.csv')); $out = fopen('php://output', 'w');
    if ($path === '/admin/report-users.csv') { fputcsv($out, ['User ID','Name','Email','Role','Membership Tier','Membership ID','Membership Active','Created At']); foreach (db()->query('SELECT id, name, email, role, membership_tier, membership_id, membership_active, created_at FROM users ORDER BY created_at DESC') as $row) fputcsv($out, [$row['id'], $row['name'], $row['email'], $row['role'], $row['membership_tier'] ?? 'None', $row['membership_id'] ?? '', $row['membership_active'] ? 'Yes' : 'No', $row['created_at']]); }
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
        $stmt = db()->prepare('SELECT id, name, email, membership_active, membership_tier, role, membership_id, profile_image FROM users WHERE google_id = ? OR email = ? LIMIT 1'); $stmt->execute([$profile['sub'], strtolower($profile['email'])]); $account = $stmt->fetch();
        if ($account) { db()->prepare('UPDATE users SET google_id = ?, avatar_url = ?, name = ?, email_verified_at = COALESCE(email_verified_at, NOW()) WHERE id = ?')->execute([$profile['sub'], $profile['picture'] ?? null, $profile['name'] ?? $account['name'], $account['id']]); }
        else { $stmt = db()->prepare('INSERT INTO users (name,email,password_hash,google_id,avatar_url,email_verified_at) VALUES (?,?,?,?,?,NOW())'); $stmt->execute([$profile['name'] ?? $profile['email'], strtolower($profile['email']), null, $profile['sub'], $profile['picture'] ?? null]); $account = ['id'=>db()->lastInsertId(),'name'=>$profile['name'] ?? $profile['email'],'email'=>strtolower($profile['email']),'membership_active'=>0,'membership_tier'=>null,'role'=>'member','membership_id'=>null,'profile_image'=>null]; }
        $_SESSION['user'] = ['id'=>(int)$account['id'],'name'=>$account['name'],'email'=>$account['email'],'membership'=>(bool)$account['membership_active'],'tier'=>$account['membership_tier'] ?? 'Standard','role'=>$account['role'],'membership_id'=>$account['membership_id'],'profile_image'=>$account['profile_image'] ?? null,'avatar_url'=>$profile['picture'] ?? null];
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
            $stmt = db()->prepare('SELECT t.title, p.name, p.price, p.capacity FROM trips t JOIN trip_packages p ON p.trip_id = t.id WHERE t.id = ? AND p.id = ? AND t.published = 1'); 
            $stmt->execute([$tripId, $packageId]); 
            $package = $stmt->fetch(); 
            if (!$package) throw new RuntimeException('That trip package is no longer available.'); 
            $amount = (float)$package['price'] * $guests; 
            $reference = 'TP' . date('ymdHis') . strtoupper(bin2hex(random_bytes(2))); 
            db()->prepare('INSERT INTO transactions (user_id, type, tier, amount, currency, method, status, order_reference) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')->execute([$user['id'], 'trip', $package['name'], $amount, 'TZS', trim($_POST['method'] ?? 'mobile'), 'pending', $reference]); 
            $transactionId = (int)db()->lastInsertId(); 
            db()->prepare('INSERT INTO trip_bookings (user_id, trip_id, package_id, transaction_id, guests, status) VALUES (?, ?, ?, ?, ?, ?)')->execute([$user['id'], $tripId, $packageId, $transactionId, $guests, 'pending']); 
            $result = cp_start_payment($amount, trim($_POST['method'] ?? 'mobile'), trim($_POST['phone'] ?? ''), $reference, $user['name'], $user['email']); 
            db()->prepare('UPDATE transactions SET provider_ref = ?, channel = ? WHERE id = ?')->execute([$result['id'] ?? null, $result['channel'] ?? ($_POST['method'] ?? 'mobile'), $transactionId]); 
            $_SESSION['active_payment'] = ['reference' => $reference, 'type' => 'trip', 'name' => $user['name']]; 
            if (!empty($result['checkoutLink'])) { header('Location: ' . $result['checkoutLink']); exit; } 
            $_SESSION['flash'] = 'Booking created. Complete payment to receive your ticket via email.'; 
        } catch (Throwable $e) { $_SESSION['flash'] = 'Trip booking failed: ' . $e->getMessage(); } 
        header('Location: /trips'); exit;
    }
    if ($action === 'login') {
        try {
            $email = strtolower(trim($_POST['email'] ?? ''));
            $stmt = db()->prepare('SELECT id, name, email, password_hash, membership_active, membership_tier, role, membership_id, profile_image, avatar_url, email_verified_at FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $account = $stmt->fetch();
            if ($account && password_verify($_POST['password'] ?? '', (string)$account['password_hash'])) {
                if ($account['role'] !== 'admin' && empty($account['email_verified_at'])) { $_SESSION['flash'] = 'Please verify your email address before logging in.'; header('Location: /login'); exit; }
                $_SESSION['user'] = ['id' => (int)$account['id'], 'name' => $account['name'], 'email' => $account['email'], 'membership' => (bool)$account['membership_active'], 'tier' => $account['membership_tier'] ?? 'Standard', 'role' => $account['role'], 'membership_id' => $account['membership_id'], 'profile_image' => $account['profile_image'] ?? null, 'avatar_url' => $account['avatar_url'] ?? null];
                header('Location: ' . app_base_path() . ($account['role'] === 'admin' ? '/admin' : '/dashboard')); exit;
            }
            $_SESSION['flash'] = 'Invalid email or password.';
        } catch (Throwable $e) { $_SESSION['flash'] = 'Database connection error.'; }
        header('Location: /login'); exit;
    }
    if ($action === 'signup') {
        try {
            ensure_profile_columns();
            $name = trim($_POST['name'] ?? ''); $email = strtolower(trim($_POST['email'] ?? '')); $password = $_POST['password'] ?? '';
            if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6) throw new RuntimeException('Please enter valid sign up details.');
            $token = bin2hex(random_bytes(32));
            $stmt = db()->prepare('INSERT INTO users (name, email, password_hash, email_verification_token, email_verification_expires_at) VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))');
            $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), hash('sha256', $token)]);
            if (mail_configured()) send_verification_email(['name' => $name, 'email' => $email], $token);
            $_SESSION['flash'] = 'Account created. Check your email to verify.';
            header('Location: /login'); exit;
        } catch (Throwable $e) { $_SESSION['flash'] = 'Error creating account.'; header('Location: /signup'); exit; }
    }
    if ($action === 'contact') {
        try {
            ensure_contact_columns();
            $name = trim($_POST['name'] ?? ''); $email = trim($_POST['email'] ?? ''); $message = trim($_POST['message'] ?? '');
            if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $message === '') throw new RuntimeException('Please fill out all contact fields.');
            db()->prepare('INSERT INTO contact_messages (name, email, message, status) VALUES (?, ?, ?, \'new\')')->execute([$name, $email, $message]);
            $_SESSION['flash'] = 'Thanks! Your message has been sent.';
        } catch (Throwable $e) { $_SESSION['flash'] = 'Message could not be sent.'; }
        header('Location: /contact'); exit;
    }
    if ($action === 'donate' || $action === 'subscribe') {
        try {
            if (!$user && $action === 'subscribe') throw new RuntimeException('Please log in before subscribing.');
            $amount = (float)($_POST['amount'] ?? 0);
            $method = trim($_POST['method'] ?? 'mobile'); 
            $phone = trim($_POST['phone'] ?? ''); 
            $tier = $_POST['tier'] ?? 'Donation'; 
            $type = $action === 'donate' ? 'donation' : 'subscription';
            $reference = 'RF' . date('ymdHis') . strtoupper(bin2hex(random_bytes(2)));
            db()->prepare('INSERT INTO transactions (user_id, type, tier, amount, currency, method, status, order_reference) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')->execute([$user['id'] ?? null, $type, $tier, $amount, 'TZS', $method, 'pending', $reference]);
            $result = cp_start_payment($amount, $method, $phone, $reference, $user['name'] ?? trim($_POST['name'] ?? 'Donor'), $user['email'] ?? trim($_POST['email'] ?? ''));
            db()->prepare('UPDATE transactions SET provider_ref = ?, channel = ? WHERE order_reference = ?')->execute([$result['id'] ?? null, $result['channel'] ?? $method, $reference]);
            $_SESSION['active_payment'] = ['reference' => $reference, 'type' => $type, 'name' => $user['name'] ?? 'Donor'];
            if (!empty($result['checkoutLink'])) { header('Location: ' . $result['checkoutLink']); exit; }
            $_SESSION['flash'] = 'Payment request sent. Please confirm on your mobile handset.';
        } catch (Throwable $e) { $_SESSION['flash'] = 'Payment start failed: ' . $e->getMessage(); }
        header('Location: /' . ($action === 'donate' ? 'donate' : 'members')); exit;
    }
}

$user = $_SESSION['user'] ?? null;
$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
$activePayment = $_SESSION['active_payment'] ?? null;
$public = ['/', '/about', '/contact', '/donate', '/blog', '/members', '/trips', '/login', '/signup'];
if (!$user && !in_array($path, $public, true)) { header('Location: /login'); exit; }

function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
function app_base_path(): string { $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/index.php')); $dir = str_replace('\\', '/', dirname($script)); return ($dir === '.' || $dir === '/') ? '' : rtrim($dir, '/'); }
function ensure_profile_columns(): void { 
    static $done = false; if ($done) return; 
    $columns = []; foreach (db()->query('SHOW COLUMNS FROM users')->fetchAll() as $column) $columns[(string)$column['Field']] = true; 
    if (!isset($columns['profile_image'])) db()->exec('ALTER TABLE users ADD COLUMN profile_image VARCHAR(255) NULL'); 
    if (!isset($columns['bio'])) db()->exec('ALTER TABLE users ADD COLUMN bio TEXT NULL'); 
    if (!isset($columns['membership_tier'])) db()->exec('ALTER TABLE users ADD COLUMN membership_tier VARCHAR(50) NULL DEFAULT "Spring Green"');
    $done = true; 
}
function public_app_url(string $path = ''): string { $local = is_file(__DIR__.'/local-config.php') ? (require __DIR__.'/local-config.php') : []; $base = rtrim((string)($local['APP_URL'] ?? getenv('APP_URL') ?: ''), '/'); if ($base === '') { $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http'; $base = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . app_base_path(); } return $base . '/' . ltrim($path, '/'); }
function send_verification_email(array $account, string $token): bool { $link = public_app_url('/verify-email?token=' . rawurlencode($token)); $body = "Hello {$account['name']},\n\nPlease verify your Royal Family TZ email: {$link}\n\nRoyal Family TZ"; return send_email((string)$account['email'], 'Verify your Royal Family TZ email', $body); }

function send_trip_ticket_email(string $orderRef): void {
    try {
        $stmt = db()->prepare("SELECT b.id AS ticket_id, u.name, u.email, u.membership_tier, u.membership_id, t.title AS trip_title, t.destination, t.trip_date, p.name AS pkg_name, tr.amount, tr.currency FROM trip_bookings b JOIN transactions tr ON tr.id = b.transaction_id JOIN users u ON u.id = b.user_id JOIN trips t ON t.id = b.trip_id JOIN trip_packages p ON p.id = b.package_id WHERE tr.order_reference = ? LIMIT 1");
        $stmt->execute([$orderRef]);
        $data = $stmt->fetch();
        if (!$data || !mail_configured()) return;
        
        $tier = $data['membership_tier'] ?? 'Spring Green';
$badge = ($tier === 'Gold Patron') ? 'GOLD 🥇' : (($tier === 'Silver Supporter') ? 'SILVER 🥈' : 'SPRING GREEN 🟢');
        
        $subject = "Your Trip Ticket - " . $data['trip_title'] . " [" . $badge . "]";
        $body = "Hello " . $data['name'] . ",\n\n"
              . "Thank you for your booking! Here is your official event/trip ticket:\n\n"
              . "--------------------------------------------------------\n"
              . "ROYAL FAMILY TZ - OFFICIAL TRIP TICKET\n"
              . "--------------------------------------------------------\n"
              . "Ticket ID: TKT-" . str_pad((string)$data['ticket_id'], 6, '0', STR_PAD_LEFT) . "\n"
              . "Passenger Name: " . $data['name'] . "\n"
              . "Membership Status: " . $tier . " (" . ($data['membership_id'] ?? 'RFTZ-MEMBER') . ")\n"
              . "Badge ID Tier: " . $badge . "\n\n"
              . "Trip Event: " . $data['trip_title'] . "\n"
              . "Destination: " . $data['destination'] . "\n"
              . "Date: " . ($data['trip_date'] ? date('F j, Y', strtotime($data['trip_date'])) : 'To be announced') . "\n"
              . "Package: " . $data['pkg_name'] . "\n"
              . "Amount Paid: " . number_format((float)$data['amount']) . " " . $data['currency'] . "\n"
              . "Payment Ref: " . $orderRef . "\n"
              . "--------------------------------------------------------\n\n"
              . "Please present this digital email ticket upon departure.\n\n"
              . "Safe travels,\n"
              . "Royal Family TZ Team";
              
        send_email((string)$data['email'], $subject, $body);
    } catch (Throwable $e) {}
}

function ensure_contact_columns(): void { static $done = false; if ($done) return; try { db()->exec("ALTER TABLE contact_messages ADD COLUMN status ENUM('new','replied') NOT NULL DEFAULT 'new'"); } catch (Throwable $e) {} $done = true; }
function ensure_trip_tables(): void { static $done = false; if ($done) return; db()->exec("CREATE TABLE IF NOT EXISTS trips (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, title VARCHAR(180) NOT NULL, slug VARCHAR(180) NOT NULL UNIQUE, description TEXT NOT NULL, destination VARCHAR(180) NOT NULL, trip_date DATE NULL, meeting_point VARCHAR(180) NULL, poster_image VARCHAR(255) NULL, published TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB"); $done = true; }
function logo_url(): string { return app_base_path() . '/assets/royal-family-logo.jpg'; }

function nav(string $current, ?array $user): void { $base=app_base_path(); $links=['/'=>'Home','/about'=>'About','/members'=>'Members','/trips'=>'Trips','/blog'=>'Blog','/contact'=>'Contact','/donate'=>'Donate']; echo '<header><div class="nav"><a class="brand" href="'.$base.'/"><img class="brand-logo" src="'.e(logo_url()).'" alt="Royal Family TZ logo"><span>Royal Family <small>Tanzania</small></span></a><input class="menu-checkbox" type="checkbox" id="mobile-menu"><label class="menu-toggle" for="mobile-menu" aria-label="Open menu">☰</label><nav data-mobile-nav>'; foreach($links as $href=>$label) echo '<a class="'.($current===$href?'active':'').'" href="'.e($base.$href).'">'.$label.'</a>'; if($user) echo '<a href="'.e($base.'/dashboard').'">Dashboard</a><a class="outline" href="'.e($base.'/logout').'">Log out</a>'; else echo '<a href="'.e($base.'/login').'">Log in</a>'; echo '</nav></div></header>'; }

function layout(string $title, string $content, string $path, ?array $user, ?string $flash=null): void { 
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="icon" href="'.e(logo_url()).'" type="image/png">
    <title>'.e($title).' | '.APP_NAME.'</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Fraunces:opsz,wght@9..144,600;9..144,700&display=swap" rel="stylesheet">
    <style>'.css().'</style></head><body>'; 
    nav($path,$user); 
    if($flash) echo '<div class="flash">'.e($flash).'</div>'; 
    echo '<main>'.$content.'</main>
    <footer><div><strong>Royal Family TZ</strong><p>Community, youth talent, and practical impact in Tanzania.</p></div><div><strong>Find us in Arusha</strong><p>Arusha, Tanzania</p><p>Phone: <a href="tel:0774002734">0774002734</a></p></div><div><a href="/about">About</a><a href="/contact">Contact</a><a href="/donate">Support us</a></div><p class="copyright">© '.date('Y').' Royal Family TZ</p></footer></body></html>'; 
}

function css(): string { return <<<'CSS'
:root{--ink:#17251f;--royal:#285743;--gold:#c9a54c;--spring:#00FF7F;--silver:#C0C0C0;--paper:#f7f3e8;--muted:#66736c}
*{box-sizing:border-box}
body{margin:0;background:var(--paper);color:var(--ink);font-family:'DM Sans',sans-serif;line-height:1.6}
h1,h2,h3{font-family:Fraunces,serif;line-height:1.1;margin:0 0 18px}
a{color:inherit;text-decoration:none}
.nav{max-width:1180px;margin:auto;padding:22px 28px;display:flex;align-items:center;justify-content:space-between}
.brand-logo{width:64px;height:64px;object-fit:contain;border-radius:12px;background:#fff;padding:4px}
.brand{display:flex;align-items:center;gap:10px;font-family:Fraunces;font-size:1.15rem;font-weight:700}
nav{display:flex;align-items:center;gap:20px}
nav a:hover,nav a.active{color:var(--royal);border-bottom:2px solid var(--gold)}
.btn{display:inline-block;background:var(--royal);color:#fff;border:0;border-radius:999px;padding:12px 22px;font-weight:700;cursor:pointer;text-align:center}
.btn.gold{background:var(--gold);color:var(--ink)}
.btn.spring{background:var(--spring);color:#05381e}
.btn.silver{background:var(--silver);color:#222}
.page{max-width:1080px;margin:40px auto;padding:0 28px}
.center{text-align:center}
.eyebrow{color:var(--gold);font-weight:700;letter-spacing:.13em;text-transform:uppercase;font-size:.76rem}

/* Donation Compact Grid Layout */
.donate-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:18px;margin-top:24px}
.donate-card{background:#fff;border:1px solid #e8dfcb;border-radius:14px;padding:20px;box-shadow:0 4px 12px rgba(0,0,0,0.04);display:flex;flex-direction:column;justify-content:space-between}
.donate-card h3{font-size:1.25rem;margin-bottom:8px}
.donate-card p{font-size:.9rem;color:var(--muted);margin-bottom:14px}

/* Form Styles & Standard Equal Inputs */
.form-card{max-width:560px;margin:30px auto;background:#fff;padding:28px;border-radius:16px;border:1px solid #e8dfcb}
.form{display:grid;gap:16px}
.form label{font-weight:700;font-size:.9rem;display:flex;flex-direction:column;gap:6px}
.form input[type="text"],.form input[type="email"],.form input[type="number"],.form input[type="password"],.form select,.form textarea{width:100%;padding:12px 14px;border:1px solid #c9c0b0;border-radius:8px;background:#fff;font-family:inherit;font-size:.95rem;box-sizing:border-box}
.form textarea{resize:vertical;min-height:120px}

/* Payment Gateway Toggle Grid */
.payment-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:10px;margin:10px 0}
.payment-option input[type="radio"]{display:none}
.payment-option label{display:flex;align-items:center;justify-content:center;padding:12px;border:2px solid #e8dfcb;border-radius:8px;cursor:pointer;background:#fff;font-weight:700;font-size:.85rem}
.payment-option input[type="radio"]:checked + label{border-color:var(--gold);background:#fffef0}

/* Professional Membership Categories Styling */
.membership-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:22px;margin-top:30px}
.member-card{background:#fff;border:2px solid #e8dfcb;border-radius:18px;padding:28px;text-align:center;box-shadow:0 8px 24px rgba(0,0,0,0.05);display:flex;flex-direction:column;justify-content:space-between}
.member-card.spring-card{border-color:var(--spring)}
.member-card.silver-card{border-color:var(--silver)}
.member-card.gold-card{border-color:var(--gold)}
.tier-badge{display:inline-block;padding:4px 12px;border-radius:20px;font-size:.8rem;font-weight:700;margin-bottom:12px;text-transform:uppercase}
.badge-spring{background:#00FF7F22;color:#007a3d;border:1px solid var(--spring)}
.badge-silver{background:#C0C0C033;color:#444;border:1px solid var(--silver)}
.badge-gold{background:#c9a54c22;color:#8a6d1c;border:1px solid var(--gold)}
.price-option{background:#fdfbf7;border:1px solid #ebd8b0;border-radius:10px;padding:12px;margin:8px 0;text-align:left;display:flex;justify-content:space-between;align-items:center}
.price-option strong{font-size:1.1rem;color:var(--royal)}

/* Responsive adjustments */
@media(max-width:850px){.membership-grid{grid-template-columns:1fr}.payment-grid{grid-template-columns:1fr}}
CSS; }

$content = '';
switch ($path) {
    case '/': 
        $content='<div class="page center"><h1>A Stronger Tanzania Starts With Us</h1><p class="eyebrow">Royal Family TZ Platform</p><a class="btn gold" href="/members">Become a Member</a></div>'; 
        break;

    case '/donate': 
        $content='<div class="page"><div class="center"><p class="eyebrow">Support Our Cause</p><h1>Make a Donation</h1></div>
        <div class="donate-grid">
            <article class="donate-card">
                <div>
                    <h3>Youth Empowerment</h3>
                    <p>Help us fund skill training, workshops, and sports equipment for young Tanzanians.</p>
                </div>
                <button class="btn gold" onclick="document.getElementById(\'amount\').value=10000">Donate 10,000 TZS</button>
            </article>
            <article class="donate-card">
                <div>
                    <h3>Community Outreach</h3>
                    <p>Support our charity visits, local community care, and family development events.</p>
                </div>
                <button class="btn gold" onclick="document.getElementById(\'amount\').value=25000">Donate 25,000 TZS</button>
            </article>
            <article class="donate-card">
                <div>
                    <h3>Talent & Culture</h3>
                    <p>Directly assist young artists, performers, and creators to develop their craft.</p>
                </div>
                <button class="btn gold" onclick="document.getElementById(\'amount\').value=50000">Donate 50,000 TZS</button>
            </article>
        </div>
        <div class="form-card" style="margin-top:40px;">
            <form class="form" method="post">
                <input type="hidden" name="action" value="donate">
                <label>Amount (TZS)<input id="amount" name="amount" type="number" min="1000" placeholder="e.g. 20000" required></label>
                <label>Mobile Number<input name="phone" placeholder="07XXXXXXXX" required></label>
                <label>Payment Method</label>
                <div class="payment-grid">
                    <div class="payment-option"><input type="radio" name="method" value="mpesa" id="m1" checked><label for="m1">M-Pesa</label></div>
                    <div class="payment-option"><input type="radio" name="method" value="tigopesa" id="m2"><label for="m2">Tigo Pesa</label></div>
                    <div class="payment-option"><input type="radio" name="method" value="airtelmoney" id="m3"><label for="m3">Airtel Money</label></div>
                    <div class="payment-option"><input type="radio" name="method" value="card" id="m4"><label for="m4">Card</label></div>
                </div>
                <button class="btn gold" type="submit">Complete Donation</button>
            </form>
        </div></div>'; 
        break;

    case '/contact': 
        $content='<div class="page"><div class="center"><p class="eyebrow">Get in touch</p><h1>Contact Us</h1></div>
        <div class="form-card">
            <form class="form" method="post">
                <input type="hidden" name="action" value="contact">
                <label>Full Name<input type="text" name="name" placeholder="Enter your full name" required></label>
                <label>Email Address<input type="email" name="email" placeholder="Enter your email" required></label>
                <label>Subject / Topic<input type="text" name="subject" placeholder="What is this regarding?" required></label>
                <label>Message<textarea name="message" placeholder="Type your message here..." required></textarea></label>
                <button class="btn" type="submit">Send Message</button>
            </form>
        </div></div>'; 
        break;

    case '/members': 
        $content='<div class="page"><div class="center"><p class="eyebrow">Join The Family</p><h1>Become A Member</h1><p>Choose a tier that matches your commitment level and unlock exclusive digital membership IDs.</p></div>
        <div class="membership-grid">
            <!-- Royal Family Member Tier -->
            <article class="member-card spring-card">
                <div>
                    <span class="tier-badge badge-spring">Spring Green ID 🟢</span>
                    <h2>Royal Family Member</h2>
                    <p>Basic tier access for active community participants.</p>
                    <div class="price-option">
                        <div><strong>2,000 TZS</strong><br><small>Monthly Subscription</small></div>
                        '.($user ? '<form method="post" style="margin:0"><input type="hidden" name="action" value="subscribe"><input type="hidden" name="tier" value="Royal Family Member (Monthly)"><input type="hidden" name="amount" value="2000"><button class="btn spring" type="submit">Join</button></form>' : '<a class="btn spring" href="/login">Join</a>').'
                    </div>
                    <div class="price-option">
                        <div><strong>12,000 TZS</strong><br><small>Yearly Subscription</small></div>
                        '.($user ? '<form method="post" style="margin:0"><input type="hidden" name="action" value="subscribe"><input type="hidden" name="tier" value="Royal Family Member (Yearly)"><input type="hidden" name="amount" value="12000"><button class="btn spring" type="submit">Join</button></form>' : '<a class="btn spring" href="/login">Join</a>').'
                    </div>
                </div>
            </article>

            <!-- Supporters Tier -->
            <article class="member-card silver-card">
                <div>
                    <span class="tier-badge badge-silver">Silver Membership ID 🥈</span>
                    <h2>Supporter</h2>
                    <p>Dedicated supporters making a continuous monthly or annual impact.</p>
                    <div class="price-option">
                        <div><strong>5,000 TZS</strong><br><small>Monthly Subscription</small></div>
                        '.($user ? '<form method="post" style="margin:0"><input type="hidden" name="action" value="subscribe"><input type="hidden" name="tier" value="Silver Supporter (Monthly)"><input type="hidden" name="amount" value="5000"><button class="btn silver" type="submit">Join</button></form>' : '<a class="btn silver" href="/login">Join</a>').'
                    </div>
                    <div class="price-option">
                        <div><strong>50,000 TZS</strong><br><small>Yearly Subscription</small></div>
                        '.($user ? '<form method="post" style="margin:0"><input type="hidden" name="action" value="subscribe"><input type="hidden" name="tier" value="Silver Supporter (Yearly)"><input type="hidden" name="amount" value="50000"><button class="btn silver" type="submit">Join</button></form>' : '<a class="btn silver" href="/login">Join</a>').'
                    </div>
                </div>
            </article>

            <!-- Patron Tier -->
            <article class="member-card gold-card">
                <div>
                    <span class="tier-badge badge-gold">Gold Patron ID 🥇</span>
                    <h2>Patron</h2>
                    <p>Highest status supporting major projects, trips, and development programs.</p>
                    <div class="price-option">
                        <div><strong>10,000 TZS</strong><br><small>Monthly Subscription</small></div>
                        '.($user ? '<form method="post" style="margin:0"><input type="hidden" name="action" value="subscribe"><input type="hidden" name="tier" value="Gold Patron (Monthly)"><input type="hidden" name="amount" value="10000"><button class="btn gold" type="submit">Join</button></form>' : '<a class="btn gold" href="/login">Join</a>').'
                    </div>
                    <div class="price-option">
                        <div><strong>50,000 TZS</strong><br><small>Yearly Subscription</small></div>
                        '.($user ? '<form method="post" style="margin:0"><input type="hidden" name="action" value="subscribe"><input type="hidden" name="tier" value="Gold Patron (Yearly)"><input type="hidden" name="amount" value="50000"><button class="btn gold" type="submit">Join</button></form>' : '<a class="btn gold" href="/login">Join</a>').'
                    </div>
                </div>
            </article>
        </div></div>'; 
        break;

    case '/trips': 
        $userTier = $user['tier'] ?? 'Spring Green';
        $content='<div class="page"><div class="center"><p class="eyebrow">Travel & Events</p><h1>Upcoming Community Trips</h1><p>Book tickets and receive instant email passes tied to your <strong>'.$userTier.'</strong> status.</p></div>
        <div class="form-card">
            <h3>Book Event Ticket</h3>
            '.($user ? '
            <form class="form" method="post">
                <input type="hidden" name="action" value="trip_book">
                <input type="hidden" name="trip_id" value="1">
                <input type="hidden" name="package_id" value="1">
                <label>Current Status Badge
                    <input type="text" value="'.e($userTier).'" disabled>
                </label>
                <label>Number of Guests
                    <input type="number" name="guests" min="1" value="1" required>
                </label>
                <label>Mobile Number
                    <input type="text" name="phone" placeholder="07XXXXXXXX" required>
                </label>
                <label>Payment Method
                    <select name="method">
                        <option value="mobile">Mobile Money (M-Pesa / Tigo / Airtel)</option>
                        <option value="card">Credit / Debit Card</option>
                    </select>
                </label>
                <button class="btn gold" type="submit">Pay & Issue Email Ticket</button>
            </form>' : '<div class="center"><p>Please log in to purchase trip tickets.</p><a class="btn" href="/login">Log In Now</a></div>').'
        </div></div>'; 
        break;

    default:
        $content='<div class="page center"><h1>Page Not Found</h1><a class="btn" href="/">Return Home</a></div>';
        break;
}

layout('Royal Family TZ', $content, $path,
