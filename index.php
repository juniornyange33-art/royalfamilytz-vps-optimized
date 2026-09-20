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

function tier_definitions(): array {
    return [
        ['key'=>'supporter', 'name'=>'Silver Member', 'monthly'=>5000,  'yearly'=>20000, 'badge'=>'Silver ID', 'badge_class'=>'badge-silver', 'badge_key'=>'silver', 'id_prefix'=>'SILVER', 'icon'=>'🥈', 'perk'=>'Membership ID card', 'text'=>'Get your official Royal Family membership ID card.'],
        ['key'=>'patron',    'name'=>'Gold Member',   'monthly'=>null,  'yearly'=>30000, 'badge'=>'Gold ID',   'badge_class'=>'badge-gold',   'badge_key'=>'gold',   'id_prefix'=>'GOLD',   'icon'=>'🥇', 'perk'=>'T-shirt + membership ID card', 'text'=>'Get an official Royal Family T-shirt plus your membership ID card.'],
    ];
}
function tier_by_key(array $tiers, string $key): ?array { foreach ($tiers as $t) if ($t['key'] === $key) return $t; return null; }
function tier_by_name(array $tiers, string $name): ?array { foreach ($tiers as $t) if ($t['name'] === $name) return $t; return null; }
// A stored transaction "tier" looks like "Patron (Yearly)" — strip the billing-cycle suffix to match a tier definition.
function tier_base_name(string $storedTier): string { return trim((string)preg_replace('/\s*\([^)]*\)\s*$/', '', $storedTier)); }
function ensure_membership_signups_table(): void {
    db()->exec("CREATE TABLE IF NOT EXISTS membership_signups (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        full_name VARCHAR(150) NOT NULL,
        email VARCHAR(150) NOT NULL,
        phone VARCHAR(30) NOT NULL,
        whatsapp VARCHAR(30) NULL,
        gender VARCHAR(20) NULL,
        city_region VARCHAR(120) NULL,
        tier_key VARCHAR(20) NULL,
        cycle VARCHAR(10) NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'new',
        membership_id VARCHAR(40) NULL,
        order_reference VARCHAR(40) NULL,
        access_token VARCHAR(64) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $columns = [];
    foreach (db()->query('SHOW COLUMNS FROM membership_signups')->fetchAll() as $column) $columns[(string)$column['Field']] = true;
    if (!isset($columns['gender'])) db()->exec('ALTER TABLE membership_signups ADD COLUMN gender VARCHAR(20) NULL');
    if (!isset($columns['city_region'])) db()->exec('ALTER TABLE membership_signups ADD COLUMN city_region VARCHAR(120) NULL');
}
function ensure_donor_sponsorships_table(): void {
    db()->exec("CREATE TABLE IF NOT EXISTS donor_sponsorships (
        id INT AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(150) NOT NULL,
        supporter_type VARCHAR(30) NOT NULL,
        contact_person VARCHAR(150) NULL,
        phone VARCHAR(30) NOT NULL,
        whatsapp VARCHAR(30) NULL,
        email VARCHAR(150) NOT NULL,
        location_address VARCHAR(255) NOT NULL,
        support_types VARCHAR(255) NOT NULL,
        support_type_other VARCHAR(150) NULL,
        support_areas VARCHAR(255) NOT NULL,
        support_area_other VARCHAR(150) NULL,
        amount DECIMAL(12,2) NULL,
        preferred_project VARCHAR(255) NULL,
        frequency VARCHAR(30) NULL,
        wants_partnership VARCHAR(10) NULL,
        partnership_type VARCHAR(150) NULL,
        partnership_expectation VARCHAR(255) NULL,
        wants_recognition VARCHAR(10) NULL,
        recognition_name VARCHAR(150) NULL,
        message TEXT NULL,
        is_financial TINYINT(1) NOT NULL DEFAULT 0,
        status VARCHAR(20) NOT NULL DEFAULT 'new',
        order_reference VARCHAR(40) NULL,
        access_token VARCHAR(64) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function ensure_membership_applications_table(): void {
    db()->exec("CREATE TABLE IF NOT EXISTS membership_applications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(150) NOT NULL,
        date_of_birth DATE NOT NULL,
        gender VARCHAR(20) NOT NULL,
        nationality VARCHAR(80) NOT NULL,
        nida_number VARCHAR(60) NULL,
        phone VARCHAR(30) NOT NULL,
        whatsapp VARCHAR(30) NULL,
        email VARCHAR(150) NOT NULL,
        city_region VARCHAR(120) NOT NULL,
        district_address VARCHAR(255) NULL,
        category VARCHAR(30) NOT NULL,
        occupation VARCHAR(150) NULL,
        workplace VARCHAR(150) NULL,
        skills VARCHAR(255) NULL,
        emergency_name VARCHAR(150) NULL,
        emergency_relationship VARCHAR(100) NULL,
        emergency_phone VARCHAR(30) NULL,
        emergency_city VARCHAR(120) NULL,
        agreed_constitution TINYINT(1) NOT NULL DEFAULT 0,
        status VARCHAR(20) NOT NULL DEFAULT 'pending_payment',
        membership_id VARCHAR(40) NULL,
        order_reference VARCHAR(40) NULL,
        access_token VARCHAR(64) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function membership_category_prefix(string $category): string {
    return match ($category) {
        'Founder Member' => 'FOUNDER',
        'Honorary Member' => 'HONORARY',
        default => 'ORDINARY',
    };
}
function ensure_registration_transaction_type(): void { static $done = false; if ($done) return; try { db()->exec("ALTER TABLE transactions MODIFY type ENUM('donation','subscription','trip','registration','membership_signup') NOT NULL"); } catch (Throwable $e) {} $done = true; }
function send_membership_confirmation_email(string $orderReference): void {
    try {
        ensure_membership_applications_table();
        $stmt = db()->prepare('SELECT * FROM membership_applications WHERE order_reference = ? LIMIT 1');
        $stmt->execute([$orderReference]);
        $app = $stmt->fetch();
        if (!$app || !mail_configured()) return;
        $body = "Hello {$app['full_name']},\n\n"
            . "Congratulations — your Royal Family Foundation membership registration is confirmed.\n\n"
            . "Membership category: {$app['category']}\n"
            . "Membership ID: {$app['membership_id']}\n"
            . "Registration fee (Tsh 5,000) and Annual fee (Tsh 20,000) received in full.\n\n"
            . "Please keep this email and your Membership ID for your records and for attending Organization meetings and activities.\n\n"
            . "\"Not Related by Blood, United by Dreams\"\nRoyal Family Foundation";
        send_email((string)$app['email'], 'Your Royal Family Foundation membership is confirmed', $body);
    } catch (Throwable $e) { /* Confirmation email is best-effort; registration/payment already succeeded. */ }
} 
function ensure_membership_badge_column(): void { static $done = false; if ($done) return; $columns = []; foreach (db()->query('SHOW COLUMNS FROM users')->fetchAll() as $column) $columns[(string)$column['Field']] = true; if (!isset($columns['membership_badge'])) db()->exec("ALTER TABLE users ADD COLUMN membership_badge VARCHAR(20) NULL"); $done = true; }
function ensure_ticket_column(): void { static $done = false; if ($done) return; $columns = []; foreach (db()->query('SHOW COLUMNS FROM trip_bookings')->fetchAll() as $column) $columns[(string)$column['Field']] = true; if (!isset($columns['ticket_sent_at'])) db()->exec('ALTER TABLE trip_bookings ADD COLUMN ticket_sent_at DATETIME NULL'); $done = true; }
function badge_display(?string $badgeKey): array {
    return match ($badgeKey) {
        'silver' => ['label' => 'Silver Member', 'icon' => '🥈', 'color' => '#8a94a6'],
        'gold'   => ['label' => 'Gold Member', 'icon' => '🥇', 'color' => '#c9a54c'],
        default  => ['label' => 'Member', 'icon' => '🎫', 'color' => '#285743'],
    };
}
function send_membership_signup_confirmation_email(string $orderReference): void {
    try {
        ensure_membership_signups_table();
        $stmt = db()->prepare('SELECT * FROM membership_signups WHERE order_reference = ? LIMIT 1');
        $stmt->execute([$orderReference]);
        $signup = $stmt->fetch();
        if (!$signup || !mail_configured()) return;
        $tier = tier_by_key(tier_definitions(), (string)$signup['tier_key']);
        $perk = $tier['perk'] ?? 'your membership benefits';
        $body = "Hello {$signup['full_name']},\n\n"
            . "Congratulations — your Royal Family TZ {$tier['name']} membership payment is confirmed.\n\n"
            . "Membership ID: {$signup['membership_id']}\n"
            . "You will receive: {$perk}\n\n"
            . "Please keep this email and your Membership ID for your records.\n\n"
            . "Welcome to the family,\nRoyal Family TZ";
        send_email((string)$signup['email'], 'Your Royal Family TZ membership is confirmed', $body);
    } catch (Throwable $e) { /* Confirmation email is best-effort; payment already succeeded. */ }
}
function send_trip_ticket_email(string $orderReference): void {
    try {
        ensure_ticket_column();
        $stmt = db()->prepare('SELECT b.id AS booking_id, b.guests, b.ticket_sent_at, u.name AS user_name, u.email AS user_email, u.membership_id, u.membership_badge, t.title AS trip_title, t.destination, t.trip_date, t.meeting_point, p.name AS package_name, tx.amount, tx.currency, tx.order_reference FROM trip_bookings b JOIN transactions tx ON tx.id = b.transaction_id JOIN users u ON u.id = b.user_id JOIN trips t ON t.id = b.trip_id JOIN trip_packages p ON p.id = b.package_id WHERE tx.order_reference = ? LIMIT 1');
        $stmt->execute([$orderReference]);
        $row = $stmt->fetch();
        if (!$row || !empty($row['ticket_sent_at']) || !mail_configured()) return;
        $badge = badge_display($row['membership_badge'] ?? null);
        $dateLine = $row['trip_date'] ? date('l, F j, Y', strtotime($row['trip_date'])) : 'Date to be confirmed';
        $body = "Hello {$row['user_name']},\n\n"
            . "Your ticket is confirmed for {$row['trip_title']} ({$row['destination']}).\n\n"
            . "Package: {$row['package_name']}\n"
            . "Guests: {$row['guests']}\n"
            . "Date: {$dateLine}\n"
            . "Meeting point: " . ($row['meeting_point'] ?: 'To be announced') . "\n"
            . "Amount paid: {$row['currency']} " . number_format((float)$row['amount'], 2) . "\n"
            . "Booking reference: {$row['order_reference']}\n\n"
            . "Membership status: {$badge['icon']} {$badge['label']}" . (!empty($row['membership_id']) ? " (ID: {$row['membership_id']})" : '') . "\n\n"
            . "Please keep this email as your ticket and present your membership ID at check-in.\n\n"
            . "See you there,\nRoyal Family TZ";
        if (send_email((string)$row['user_email'], 'Your Royal Family TZ ticket — ' . $row['trip_title'], $body)) {
            db()->prepare('UPDATE trip_bookings SET ticket_sent_at = NOW() WHERE id = ?')->execute([(int)$row['booking_id']]);
        }
    } catch (Throwable $e) { /* Ticket email is best-effort; booking/payment already succeeded. */ }
}
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = rtrim($path, '/') ?: '/';
$basePath = str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/index.php')));
$basePath = ($basePath === '.' || $basePath === '/') ? '' : rtrim($basePath, '/');
if ($basePath !== '' && ($path === $basePath || str_starts_with($path, $basePath . '/'))) { $path = substr($path, strlen($basePath)) ?: '/'; }
$user = $_SESSION['user'] ?? null;

if ($path === '/logout') { session_destroy(); header('Location: /'); exit; }
if ($path === '/verify-email') {
    try { ensure_profile_columns(); $token = (string)($_GET['token'] ?? ''); $hash = hash('sha256', $token); $stmt = db()->prepare('SELECT id, name, email FROM users WHERE email_verification_token = ? AND email_verification_expires_at > NOW() LIMIT 1'); $stmt->execute([$hash]); $account = $stmt->fetch(); if (!$account) throw new RuntimeException('This verification link is invalid or has expired.'); db()->prepare('UPDATE users SET email_verified_at = NOW(), email_verification_token = NULL, email_verification_expires_at = NULL WHERE id = ?')->execute([(int)$account['id']]); if (mail_configured()) { try { send_email((string)$account['email'], 'Welcome to Royal Family TZ', "Hello {$account['name']},\n\nThank you for verifying your email and joining Royal Family TZ. Your account is now active. You can sign in and explore events, trips, membership, and community updates.\n\nWelcome to the family,\nRoyal Family TZ"); } catch (Throwable $welcomeError) {} } $_SESSION['flash'] = 'Email verified successfully. A welcome message has been sent to your email.'; } catch (Throwable $e) { $_SESSION['flash'] = $e->getMessage(); } header('Location: /login'); exit;
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
                    $txStmt = db()->prepare('SELECT id, user_id, type, tier FROM transactions WHERE order_reference = ? LIMIT 1');
                    $txStmt->execute([$reference]);
                    $tx = $txStmt->fetch();
                    if ($tx && $tx['type'] === 'subscription' && $tx['user_id']) {
                        ensure_membership_badge_column();
                        $matchedTier = tier_by_name(tier_definitions(), tier_base_name((string)($tx['tier'] ?? '')));
                        $prefix = $matchedTier['id_prefix'] ?? 'RFTZ';
                        $badgeKey = $matchedTier['badge_key'] ?? null;
                        db()->prepare('UPDATE users SET membership_active = 1, membership_id = COALESCE(membership_id, CONCAT(?, \'-\', LPAD(id, 6, \'0\'))), membership_badge = ? WHERE id = ?')->execute([$prefix, $badgeKey, $tx['user_id']]);
                    }
                    try { db()->prepare('UPDATE trip_bookings b JOIN transactions t ON t.id = b.transaction_id SET b.status = \'paid\' WHERE t.order_reference = ?')->execute([$reference]); } catch (Throwable $ignore) {}
                    if ($tx && $tx['type'] === 'trip') send_trip_ticket_email($reference);
                    if ($tx && $tx['type'] === 'registration') {
                        ensure_membership_applications_table();
                        $appStmt = db()->prepare('SELECT id, category, membership_id FROM membership_applications WHERE order_reference = ? LIMIT 1');
                        $appStmt->execute([$reference]);
                        $app = $appStmt->fetch();
                        if ($app && empty($app['membership_id'])) {
                            $prefix = membership_category_prefix((string)$app['category']);
                            $membershipId = $prefix . '-' . str_pad((string)$app['id'], 6, '0', STR_PAD_LEFT);
                            db()->prepare("UPDATE membership_applications SET status = 'paid', membership_id = ? WHERE id = ?")->execute([$membershipId, $app['id']]);
                        }
                        send_membership_confirmation_email($reference);
                    }
                    if ($tx && $tx['type'] === 'membership_signup') {
                        ensure_membership_signups_table();
                        $signupStmt = db()->prepare('SELECT id, user_id, tier_key, membership_id FROM membership_signups WHERE order_reference = ? LIMIT 1');
                        $signupStmt->execute([$reference]);
                        $signup = $signupStmt->fetch();
                        if ($signup && empty($signup['membership_id'])) {
                            $matchedTier = tier_by_key(tier_definitions(), (string)$signup['tier_key']);
                            $prefix = $matchedTier['id_prefix'] ?? 'MEMBER';
                            $membershipId = $prefix . '-' . str_pad((string)$signup['id'], 6, '0', STR_PAD_LEFT);
                            db()->prepare("UPDATE membership_signups SET status = 'paid', membership_id = ? WHERE id = ?")->execute([$membershipId, $signup['id']]);
                            if (!empty($signup['user_id'])) {
                                ensure_membership_badge_column();
                                db()->prepare('UPDATE users SET membership_active = 1, membership_id = COALESCE(membership_id, ?), membership_badge = ? WHERE id = ?')->execute([$membershipId, $matchedTier['badge_key'] ?? null, $signup['user_id']]);
                            }
                        }
                        send_membership_signup_confirmation_email($reference);
                    }
                    if ($tx && $tx['type'] === 'donation') {
                        try { ensure_donor_sponsorships_table(); db()->prepare("UPDATE donor_sponsorships SET status = 'paid' WHERE order_reference = ?")->execute([$reference]); } catch (Throwable $ignore) {}
                    }
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
    if ($path === '/admin/report-users.csv') { fputcsv($out, ['User ID','Name','Email','Role','Membership ID','Membership Active','Created At']); foreach (db()->query('SELECT id, name, email, role, membership_id, membership_active, created_at FROM users ORDER BY created_at DESC') as $row) fputcsv($out, [$row['id'], $row['name'], $row['email'], $row['role'], $row['membership_id'] ?? '', $row['membership_active'] ? 'Yes' : 'No', $row['created_at']]); }
    else { fputcsv($out, ['Transaction ID','Order Reference','User','Email','Type','Package/Tier','Amount','Currency','Method','Status','Created At']); foreach (db()->query('SELECT t.id, t.order_reference, COALESCE(u.name, \'Guest\') AS user_name, COALESCE(u.email, \'\') AS email, t.type, t.tier, t.amount, t.currency, t.method, t.status, t.created_at FROM transactions t LEFT JOIN users u ON u.id = t.user_id ORDER BY t.created_at DESC') as $row) fputcsv($out, [$row['id'], $row['order_reference'], $row['user_name'], $row['email'], $row['type'], $row['tier'], $row['amount'], $row['currency'], $row['method'], $row['status'], $row['created_at']]); }
    fclose($out); exit;
}
if ($path === '/auth/google/start') { if (!google_enabled()) { $_SESSION['flash'] = 'Google sign-in is not configured. Add GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET to local-config.php.'; header('Location: /signup'); exit; } header('Location: ' . google_auth_url()); exit; }
if ($path === '/auth/google/callback') {
    try {
        if (!google_enabled()) throw new RuntimeException('Google sign-in is not configured yet.');
        if (empty($_GET['code'])) throw new RuntimeException('Google did not return an authorization code. Please try again.');
        $expectedState = (string)($_SESSION['google_oauth_state'] ?? '');
        $returnedState = (string)($_GET['state'] ?? '');
        if ($expectedState === '' || $returnedState === '' || !hash_equals($expectedState, $returnedState)) throw new RuntimeException('Google sign-in session expired. Please tap Continue with Google again.');
        unset($_SESSION['google_oauth_state']);
        $profile = google_exchange_code((string)$_GET['code']);
        ensure_profile_columns();
        $stmt = db()->prepare('SELECT id, name, email, password_hash, membership_active, role, membership_id, profile_image FROM users WHERE google_id = ? OR email = ? LIMIT 1'); $stmt->execute([$profile['sub'], strtolower($profile['email'])]); $account = $stmt->fetch();
        if ($account) { db()->prepare('UPDATE users SET google_id = ?, avatar_url = ?, name = ?, email_verified_at = COALESCE(email_verified_at, NOW()), email_verification_token = NULL, email_verification_expires_at = NULL WHERE id = ?')->execute([$profile['sub'], $profile['picture'] ?? null, $profile['name'] ?? $account['name'], $account['id']]); }
        else { $stmt = db()->prepare('INSERT INTO users (name,email,password_hash,google_id,avatar_url,email_verified_at) VALUES (?,?,?,?,?,NOW())'); $stmt->execute([$profile['name'] ?? $profile['email'], strtolower($profile['email']), null, $profile['sub'], $profile['picture'] ?? null]); $account = ['id'=>db()->lastInsertId(),'name'=>$profile['name'] ?? $profile['email'],'email'=>strtolower($profile['email']),'membership_active'=>0,'role'=>'member','membership_id'=>null,'profile_image'=>null]; }
        $_SESSION['user'] = ['id'=>(int)$account['id'],'name'=>$account['name'],'email'=>$account['email'],'membership'=>(bool)$account['membership_active'],'role'=>$account['role'],'membership_id'=>$account['membership_id'],'profile_image'=>$account['profile_image'] ?? null,'avatar_url'=>$profile['picture'] ?? null];
        header('Location: ' . app_base_path() . ($account['role'] === 'admin' ? '/admin' : '/dashboard')); exit;
    } catch (Throwable $e) { $_SESSION['flash'] = $e->getMessage(); header('Location: ' . app_base_path() . '/login'); exit; }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'event_create' && $user && $user['role'] === 'admin') {
        try { ensure_community_tables(); $title = trim($_POST['title'] ?? ''); $description = trim($_POST['description'] ?? ''); $date = trim($_POST['event_date'] ?? '') ?: null; $location = trim($_POST['location'] ?? '') ?: null; if ($title === '' || $description === '') throw new RuntimeException('Event title and description are required.'); db()->prepare('INSERT INTO events (title, description, event_date, location, published) VALUES (?, ?, ?, ?, 1)')->execute([$title, $description, $date ? str_replace('T', ' ', $date) . ':00' : null, $location]); $_SESSION['flash'] = 'Event published for members.'; } catch (Throwable $e) { $_SESSION['flash'] = 'Event could not be published: ' . $e->getMessage(); } header('Location: /admin'); exit;
    }
    if ($action === 'notification_create' && $user && $user['role'] === 'admin') {
        try { ensure_community_tables(); $title = trim($_POST['title'] ?? ''); $message = trim($_POST['message'] ?? ''); $audience = in_array($_POST['audience'] ?? 'all', ['all','members','admins'], true) ? $_POST['audience'] : 'all'; if ($title === '' || $message === '') throw new RuntimeException('Notification title and message are required.'); db()->prepare('INSERT INTO notifications (title, message, audience) VALUES (?, ?, ?)')->execute([$title, $message, $audience]); $roleFilter = $audience === 'all' ? '' : ' WHERE role = ' . db()->quote($audience === 'admins' ? 'admin' : 'member'); $recipients = db()->query('SELECT email FROM users' . $roleFilter . ' ORDER BY id ASC')->fetchAll(PDO::FETCH_COLUMN); $sent = 0; $emailWarning = ''; if (mail_configured()) { foreach ($recipients as $recipient) { try { if (send_email((string)$recipient, $title, $message)) $sent++; } catch (Throwable $mailError) { $emailWarning = $mailError->getMessage(); } } } else $emailWarning = 'SMTP is not configured'; $_SESSION['flash'] = 'Notification saved for dashboards. ' . ($sent ? $sent . ' email(s) sent.' : 'No email sent: ' . $emailWarning . '. Configure SMTP in local-config.php.'); } catch (Throwable $e) { $_SESSION['flash'] = 'Notification could not be sent: ' . $e->getMessage(); } header('Location: /admin'); exit;
    }
    if (($action === 'notification_update' || $action === 'notification_delete') && $user && $user['role'] === 'admin') {
        try { ensure_community_tables(); $id = (int)($_POST['notification_id'] ?? 0); if (!$id) throw new RuntimeException('Notification ID is required.'); if ($action === 'notification_delete') { db()->prepare('DELETE FROM notifications WHERE id = ?')->execute([$id]); $_SESSION['flash'] = 'Notification deleted.'; } else { $title = trim($_POST['title'] ?? ''); $message = trim($_POST['message'] ?? ''); $audience = in_array($_POST['audience'] ?? 'all', ['all','members','admins'], true) ? $_POST['audience'] : 'all'; if ($title === '' || $message === '') throw new RuntimeException('Notification title and message are required.'); db()->prepare('UPDATE notifications SET title = ?, message = ?, audience = ? WHERE id = ?')->execute([$title, $message, $audience, $id]); $_SESSION['flash'] = 'Notification updated.'; } } catch (Throwable $e) { $_SESSION['flash'] = 'Notification could not be changed: ' . $e->getMessage(); } header('Location: /admin'); exit;
    }
    if ($action === 'trip_create' && $user && $user['role'] === 'admin') {
        try { ensure_trip_tables(); $title = trim($_POST['title'] ?? ''); $description = trim($_POST['description'] ?? ''); $destination = trim($_POST['destination'] ?? ''); $date = trim($_POST['trip_date'] ?? '') ?: null; $meeting = trim($_POST['meeting_point'] ?? '') ?: null; if (!$title || !$description || !$destination) throw new RuntimeException('Trip title, description, and destination are required.'); $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $title), '-')); $posterName = save_trip_poster($_FILES['poster_image'] ?? null); $stmt = db()->prepare('INSERT INTO trips (title, slug, description, destination, trip_date, meeting_point, poster_image, published) VALUES (?, ?, ?, ?, ?, ?, ?, 1)'); $stmt->execute([$title, $slug . '-' . substr(bin2hex(random_bytes(3)), 0, 6), $description, $destination, $date, $meeting, $posterName]); $tripId = (int)db()->lastInsertId(); foreach (($_POST['package_name'] ?? []) as $i => $packageName) { $packageName = trim((string)$packageName); $packageDescription = trim((string)($_POST['package_description'][$i] ?? '')); $price = (float)($_POST['package_price'][$i] ?? 0); $capacity = (int)($_POST['package_capacity'][$i] ?? 0) ?: null; if ($packageName && $packageDescription && $price > 0) db()->prepare('INSERT INTO trip_packages (trip_id, name, description, price, capacity) VALUES (?, ?, ?, ?, ?)')->execute([$tripId, $packageName, $packageDescription, $price, $capacity]); } $_SESSION['flash'] = 'Trip and packages published.'; } catch (Throwable $e) { $_SESSION['flash'] = 'Trip could not be published: ' . $e->getMessage(); } header('Location: /admin'); exit;
    }
    if ($action === 'trip_update' && $user && $user['role'] === 'admin') {
        try { ensure_trip_tables(); $tripId = (int)($_POST['trip_id'] ?? 0); $title = trim($_POST['title'] ?? ''); $description = trim($_POST['description'] ?? ''); $destination = trim($_POST['destination'] ?? ''); $date = trim($_POST['trip_date'] ?? '') ?: null; $meeting = trim($_POST['meeting_point'] ?? '') ?: null; if (!$tripId || !$title || !$description || !$destination) throw new RuntimeException('Trip title, description, destination, and ID are required.'); $posterName = save_trip_poster($_FILES['poster_image'] ?? null); if ($posterName) db()->prepare('UPDATE trips SET title=?, description=?, destination=?, trip_date=?, meeting_point=?, poster_image=? WHERE id=?')->execute([$title,$description,$destination,$date,$meeting,$posterName,$tripId]); else db()->prepare('UPDATE trips SET title=?, description=?, destination=?, trip_date=?, meeting_point=? WHERE id=?')->execute([$title,$description,$destination,$date,$meeting,$tripId]); foreach (($_POST['package_name'] ?? []) as $i => $packageName) { $packageName = trim((string)$packageName); $packageDescription = trim((string)($_POST['package_description'][$i] ?? '')); $price = (float)($_POST['package_price'][$i] ?? 0); $capacity = (int)($_POST['package_capacity'][$i] ?? 0) ?: null; $packageId = (int)($_POST['package_id'][$i] ?? 0); if ($packageName && $packageDescription && $price > 0) { if ($packageId) db()->prepare('UPDATE trip_packages SET name=?, description=?, price=?, capacity=? WHERE id=? AND trip_id=?')->execute([$packageName,$packageDescription,$price,$capacity,$packageId,$tripId]); else db()->prepare('INSERT INTO trip_packages (trip_id,name,description,price,capacity) VALUES (?,?,?,?,?)')->execute([$tripId,$packageName,$packageDescription,$price,$capacity]); } } $_SESSION['flash'] = 'Trip details and packages updated.'; } catch (Throwable $e) { $_SESSION['flash'] = 'Trip could not be updated: ' . $e->getMessage(); } header('Location: /admin'); exit;
    }
    if ($action === 'trip_delete' && $user && $user['role'] === 'admin') {
        try { ensure_trip_tables(); db()->prepare('DELETE FROM trips WHERE id = ?')->execute([(int)($_POST['trip_id'] ?? 0)]); $_SESSION['flash'] = 'Trip deleted.'; } catch (Throwable $e) { $_SESSION['flash'] = 'Trip could not be deleted: ' . $e->getMessage(); } header('Location: /admin'); exit;
    }
    if ($action === 'trip_book' && $user) {
        try { ensure_trip_tables(); $tripId = (int)($_POST['trip_id'] ?? 0); $packageId = (int)($_POST['package_id'] ?? 0); $guests = max(1, (int)($_POST['guests'] ?? 1)); $stmt = db()->prepare('SELECT t.title, p.name, p.price, p.capacity FROM trips t JOIN trip_packages p ON p.trip_id = t.id WHERE t.id = ? AND p.id = ? AND t.published = 1'); $stmt->execute([$tripId, $packageId]); $package = $stmt->fetch(); if (!$package) throw new RuntimeException('That trip package is no longer available.'); if ($package['capacity'] !== null) { $used = db()->prepare("SELECT COALESCE(SUM(guests),0) FROM trip_bookings WHERE package_id = ? AND status IN ('pending','paid')"); $used->execute([$packageId]); if ((int)$used->fetchColumn() + $guests > (int)$package['capacity']) throw new RuntimeException('There are not enough places left in this package.'); } $amount = (float)$package['price'] * $guests; $reference = 'TP' . date('ymdHis') . strtoupper(bin2hex(random_bytes(2))); // 18 chars, within ClickPesa's 20-character limit
            db()->prepare('INSERT INTO transactions (user_id, type, tier, amount, currency, method, status, order_reference) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')->execute([$user['id'], 'trip', $package['name'], $amount, 'TZS', trim($_POST['method'] ?? 'mobile'), 'pending', $reference]); $transactionId = (int)db()->lastInsertId(); db()->prepare('INSERT INTO trip_bookings (user_id, trip_id, package_id, transaction_id, guests, status) VALUES (?, ?, ?, ?, ?, ?)')->execute([$user['id'], $tripId, $packageId, $transactionId, $guests, 'pending']); $result = cp_start_payment($amount, trim($_POST['method'] ?? 'mobile'), trim($_POST['phone'] ?? ''), $reference, $user['name'], $user['email']); db()->prepare('UPDATE transactions SET provider_ref = ?, channel = ? WHERE id = ?')->execute([$result['id'] ?? null, $result['channel'] ?? ($_POST['method'] ?? 'mobile'), $transactionId]); $_SESSION['active_payment'] = ['reference' => $reference, 'type' => 'trip', 'name' => $user['name']]; if (!empty($result['checkoutLink'])) { header('Location: ' . $result['checkoutLink']); exit; } $_SESSION['flash'] = 'Booking created. Check your phone and approve the payment.'; } catch (Throwable $e) { $_SESSION['flash'] = 'Trip booking could not start: ' . $e->getMessage(); } header('Location: /trips'); exit;
    }
    if ($action === 'profile_update' && $user) {
        try {
            ensure_profile_columns();
            $name = trim($_POST['name'] ?? $user['name']); $bio = trim($_POST['bio'] ?? ''); $imageName = null;
            if (!empty($_FILES['profile_image']['tmp_name'])) {
                $file = $_FILES['profile_image']; $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp']; $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
                if (!isset($allowed[$mime]) || $file['size'] > 4 * 1024 * 1024) throw new RuntimeException('Please upload a JPG, PNG, or WEBP image under 4 MB.');
                $imageName = 'profile-' . (int)$user['id'] . '-' . bin2hex(random_bytes(5)) . '.' . $allowed[$mime]; move_uploaded_file($file['tmp_name'], __DIR__ . '/uploads/profiles/' . $imageName);
                db()->prepare('UPDATE users SET name = ?, bio = ?, profile_image = ? WHERE id = ?')->execute([$name, $bio, $imageName, $user['id']]);
            } else db()->prepare('UPDATE users SET name = ?, bio = ? WHERE id = ?')->execute([$name, $bio, $user['id']]);
            $_SESSION['user']['name'] = $name; $_SESSION['user']['profile_image'] = $imageName ?: ($_SESSION['user']['profile_image'] ?? null); $_SESSION['flash'] = 'Profile updated successfully.';
        } catch (Throwable $e) { $_SESSION['flash'] = 'Profile update failed: ' . $e->getMessage(); }
        header('Location: /profile'); exit;
    }
    if ($action === 'login') {
        try {
            $email = strtolower(trim($_POST['email'] ?? ''));
            // Build the SELECT from columns that actually exist, so an older
            // XAMPP users table can still authenticate while migrations are pending.
            $available = [];
            foreach (db()->query('SHOW COLUMNS FROM users')->fetchAll() as $column) $available[(string)$column['Field']] = true;
            $required = ['id', 'name', 'email', 'password_hash'];
            foreach ($required as $column) if (!isset($available[$column])) throw new RuntimeException("The users table is missing the required column: {$column}. Import database.sql.");
            $optional = ['membership_active', 'role', 'membership_id', 'membership_badge', 'profile_image', 'avatar_url', 'email_verified_at'];
            $selectColumns = array_merge($required, array_values(array_filter($optional, fn($column) => isset($available[$column]))));
            $stmt = db()->prepare('SELECT ' . implode(', ', $selectColumns) . ' FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $account = $stmt->fetch();
            if ($account) {
                $account['membership_active'] = (int)($account['membership_active'] ?? 0);
                $account['role'] = (string)($account['role'] ?? 'member');
                $account['membership_id'] = $account['membership_id'] ?? null;
                $account['profile_image'] = $account['profile_image'] ?? null;
                $account['avatar_url'] = $account['avatar_url'] ?? null;
            }
            if ($account && password_verify($_POST['password'] ?? '', (string)$account['password_hash'])) {
                if ($account['role'] !== 'admin' && array_key_exists('email_verified_at', $available) && empty($account['email_verified_at'])) { $_SESSION['flash'] = 'Please verify your email address before logging in. Check your inbox for the verification link.'; header('Location: ' . app_base_path() . '/login'); exit; }
                $_SESSION['user'] = ['id' => (int)$account['id'], 'name' => $account['name'], 'email' => $account['email'], 'membership' => (bool)$account['membership_active'], 'role' => $account['role'], 'membership_id' => $account['membership_id'], 'membership_badge' => $account['membership_badge'] ?? null, 'profile_image' => $account['profile_image'] ?? null, 'avatar_url' => $account['avatar_url'] ?? null];
                header('Location: ' . app_base_path() . ($account['role'] === 'admin' ? '/admin' : '/dashboard')); exit;
            }
            $_SESSION['flash'] = 'Invalid email or password.';
        } catch (Throwable $e) { $_SESSION['flash'] = 'MySQL connection failed. Start MySQL in XAMPP, confirm the royalfamilytz database exists, and check local-config.php.'; }
        header('Location: ' . app_base_path() . '/login'); exit;
    }
    if ($action === 'signup') {
        try {
            ensure_profile_columns();
            $name = trim($_POST['name'] ?? ''); $email = strtolower(trim($_POST['email'] ?? '')); $password = $_POST['password'] ?? '';
            if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6) throw new RuntimeException('Please provide a valid name, email, and password of at least 6 characters.');
            $token = bin2hex(random_bytes(32));
            $stmt = db()->prepare('INSERT INTO users (name, email, password_hash, email_verification_token, email_verification_expires_at) VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))');
            $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), hash('sha256', $token)]);
            $account = ['name' => $name, 'email' => $email];
            if (!mail_configured()) throw new RuntimeException('Your account was created, but email verification cannot be sent until Gmail SMTP is configured in local-config.php.');
            if (!send_verification_email($account, $token)) throw new RuntimeException('Your account was created, but the verification email could not be sent. Check Gmail SMTP settings.');
            $_SESSION['flash'] = 'Account created. Check your email and click the verification link before logging in.';
            header('Location: /login'); exit;
        } catch (Throwable $e) { $_SESSION['flash'] = $e instanceof PDOException && $e->getCode() === '23000' ? 'That email is already registered.' : $e->getMessage(); header('Location: /signup'); exit; }
    }
    if ($action === 'contact') { try { ensure_contact_columns(); $name = trim($_POST['name'] ?? ''); $email = trim($_POST['email'] ?? ''); $message = trim($_POST['message'] ?? ''); if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $message === '') throw new RuntimeException('Please provide your name, a valid email, and a message.'); db()->prepare('INSERT INTO contact_messages (name, email, message, status) VALUES (?, ?, ?, \'new\')')->execute([$name, $email, $message]); if (mail_configured()) { try { $mail = mail_config(); send_email((string)$mail['from'], 'New Contact Us message from ' . $name, "You received a new message from {$name} ({$email}):\n\n{$message}\n\nOpen your admin dashboard to reply."); } catch (Throwable $mailError) {} } $_SESSION['flash'] = 'Thanks — your message was sent. We will get back to you soon.'; } catch (Throwable $e) { $_SESSION['flash'] = 'Unable to send your message: ' . $e->getMessage(); } header('Location: /contact'); exit; }
    if ($action === 'contact_reply' && $user && $user['role'] === 'admin') { try { ensure_contact_columns(); $id = (int)($_POST['contact_id'] ?? 0); $reply = trim($_POST['reply'] ?? ''); if (!$id || $reply === '') throw new RuntimeException('A reply message is required.'); $stmt = db()->prepare('SELECT name, email FROM contact_messages WHERE id = ? LIMIT 1'); $stmt->execute([$id]); $contact = $stmt->fetch(); if (!$contact) throw new RuntimeException('Contact message not found.'); if (!mail_configured()) throw new RuntimeException('SMTP is not configured in local-config.php.'); send_email((string)$contact['email'], 'Reply from Royal Family TZ', "Hello {$contact['name']},\n\n{$reply}\n\nRegards,\nRoyal Family TZ"); db()->prepare('UPDATE contact_messages SET status = \'replied\', replied_at = NOW() WHERE id = ?')->execute([$id]); $_SESSION['flash'] = 'Reply sent to ' . $contact['email'] . '.'; } catch (Throwable $e) { $_SESSION['flash'] = 'Reply could not be sent: ' . $e->getMessage(); } header('Location: /admin'); exit; }
    if ($action === 'apply_membership') {
        try {
            ensure_membership_applications_table();
            $fullName = trim($_POST['full_name'] ?? '');
            $dob = trim($_POST['date_of_birth'] ?? '');
            $gender = trim($_POST['gender'] ?? '');
            $nationality = trim($_POST['nationality'] ?? '');
            $nida = trim($_POST['nida_number'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $whatsapp = trim($_POST['whatsapp'] ?? '');
            $email = strtolower(trim($_POST['email'] ?? ''));
            $cityRegion = trim($_POST['city_region'] ?? '');
            $districtAddress = trim($_POST['district_address'] ?? '');
            $category = $_POST['category'] ?? '';
            $occupation = trim($_POST['occupation'] ?? '');
            $workplace = trim($_POST['workplace'] ?? '');
            $skills = trim($_POST['skills'] ?? '');
            $emergencyName = trim($_POST['emergency_name'] ?? '');
            $emergencyRelationship = trim($_POST['emergency_relationship'] ?? '');
            $emergencyPhone = trim($_POST['emergency_phone'] ?? '');
            $emergencyCity = trim($_POST['emergency_city'] ?? '');
            $agreed = !empty($_POST['agreed_constitution']);
            if ($fullName === '' || $dob === '' || $gender === '' || $nationality === '' || $phone === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $cityRegion === '') throw new RuntimeException('Please complete all required personal and contact fields.');
            if (!in_array($category, ['Founder Member', 'Ordinary Member', 'Honorary Member'], true)) throw new RuntimeException('Please choose a membership category.');
            if (!$agreed) throw new RuntimeException('You must agree to the Constitution & Principles to register.');
            $birthDate = DateTime::createFromFormat('Y-m-d', $dob);
            if (!$birthDate) throw new RuntimeException('Please provide a valid date of birth.');
            $age = $birthDate->diff(new DateTime('today'))->y;
            if ($age < 18) throw new RuntimeException('Membership is open to applicants 18 years and above.');
            $accessToken = bin2hex(random_bytes(16));
            db()->prepare('INSERT INTO membership_applications (full_name, date_of_birth, gender, nationality, nida_number, phone, whatsapp, email, city_region, district_address, category, occupation, workplace, skills, emergency_name, emergency_relationship, emergency_phone, emergency_city, agreed_constitution, access_token) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,?)')
                ->execute([$fullName, $dob, $gender, $nationality, $nida ?: null, $phone, $whatsapp ?: null, $email, $cityRegion, $districtAddress ?: null, $category, $occupation ?: null, $workplace ?: null, $skills ?: null, $emergencyName ?: null, $emergencyRelationship ?: null, $emergencyPhone ?: null, $emergencyCity ?: null, $accessToken]);
            $applicationId = (int)db()->lastInsertId();
            header('Location: /apply-membership/pay?id=' . $applicationId . '&token=' . $accessToken); exit;
        } catch (Throwable $e) { $_SESSION['flash'] = 'Registration could not be submitted: ' . $e->getMessage(); header('Location: /apply-membership'); exit; }
    }
    if ($action === 'pay_membership_fee') {
        try {
            ensure_membership_applications_table();
            $applicationId = (int)($_POST['id'] ?? 0);
            $token = (string)($_POST['token'] ?? '');
            $stmt = db()->prepare('SELECT * FROM membership_applications WHERE id = ? AND access_token = ? LIMIT 1');
            $stmt->execute([$applicationId, $token]);
            $app = $stmt->fetch();
            if (!$app) throw new RuntimeException('Registration not found. Please submit the form again.');
            if ($app['status'] === 'paid') throw new RuntimeException('This registration is already paid and confirmed.');
            $method = trim($_POST['method'] ?? 'mobile'); $phone = trim($_POST['phone'] ?? $app['phone']);
            $amount = 25000.0; // Tsh 5,000 registration fee + Tsh 20,000 annual fee, fixed for every category
            $reference = 'RG' . date('ymdHis') . strtoupper(bin2hex(random_bytes(2)));
            ensure_registration_transaction_type();
            db()->prepare('INSERT INTO transactions (user_id, type, tier, amount, currency, method, status, order_reference) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')->execute([$user['id'] ?? null, 'registration', $app['category'], $amount, 'TZS', $method, 'pending', $reference]);
            db()->prepare('UPDATE membership_applications SET order_reference = ? WHERE id = ?')->execute([$reference, $applicationId]);
            $result = cp_start_payment($amount, $method, $phone, $reference, $app['full_name'], $app['email']);
            db()->prepare('UPDATE transactions SET provider_ref = ?, channel = ? WHERE order_reference = ?')->execute([$result['id'] ?? null, $result['channel'] ?? $method, $reference]);
            $_SESSION['active_payment'] = ['reference' => $reference, 'type' => 'registration', 'name' => $app['full_name']];
            if (!empty($result['checkoutLink'])) { header('Location: ' . $result['checkoutLink']); exit; }
            $_SESSION['flash'] = 'Membership payment request sent. Approve it on your phone; we will confirm it automatically.';
            header('Location: /apply-membership/pay?id=' . $applicationId . '&token=' . $token); exit;
        } catch (Throwable $e) { $_SESSION['flash'] = 'Payment could not start: ' . $e->getMessage(); header('Location: /apply-membership/pay?id=' . (int)($_POST['id'] ?? 0) . '&token=' . urlencode((string)($_POST['token'] ?? ''))); exit; }
    }

    if ($action === 'membership_signup_start') {
        try {
            ensure_membership_signups_table();
            $fullName = trim($_POST['full_name'] ?? '');
            $email = strtolower(trim($_POST['email'] ?? ''));
            $phone = trim($_POST['phone'] ?? '');
            $whatsapp = trim($_POST['whatsapp'] ?? '');
            $gender = trim($_POST['gender'] ?? '');
            $cityRegion = trim($_POST['city_region'] ?? '');
            $agreedTerms = ($_POST['agreed_terms'] ?? '0') === '1';
            if ($fullName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $phone === '' || $gender === '' || $cityRegion === '') throw new RuntimeException('Please provide your full name, a valid email, phone, gender, and city/region.');
            if (!$agreedTerms) throw new RuntimeException('Please agree to the Foundation Membership Terms & Conditions to continue.');
            $accessToken = bin2hex(random_bytes(16));
            db()->prepare('INSERT INTO membership_signups (user_id, full_name, email, phone, whatsapp, gender, city_region, access_token) VALUES (?,?,?,?,?,?,?,?)')
                ->execute([$user['id'] ?? null, $fullName, $email, $phone, $whatsapp ?: null, $gender, $cityRegion, $accessToken]);
            $signupId = (int)db()->lastInsertId();
            header('Location: /membership-form/plans?id=' . $signupId . '&token=' . $accessToken); exit;
        } catch (Throwable $e) { $_SESSION['flash'] = 'Could not continue: ' . $e->getMessage(); header('Location: /membership-form'); exit; }
    }
    if ($action === 'membership_signup_pay') {
        try {
            ensure_membership_signups_table();
            $signupId = (int)($_POST['id'] ?? 0);
            $token = (string)($_POST['token'] ?? '');
            $stmt = db()->prepare('SELECT * FROM membership_signups WHERE id = ? AND access_token = ? LIMIT 1');
            $stmt->execute([$signupId, $token]);
            $signup = $stmt->fetch();
            if (!$signup) throw new RuntimeException('Your form session was not found. Please start again.');
            if ($signup['status'] === 'paid') throw new RuntimeException('This membership is already paid and confirmed.');
            $selectedTier = tier_by_key(tier_definitions(), (string)($_POST['tier_key'] ?? ''));
            if (!$selectedTier) throw new RuntimeException('Please choose a membership plan.');
            $cycle = ($_POST['cycle'] ?? 'monthly') === 'yearly' ? 'yearly' : 'monthly';
            if ($selectedTier[$cycle] === null) $cycle = 'yearly';
            $amount = (float)$selectedTier[$cycle];
            if (!$amount) throw new RuntimeException('That plan is not available.');
            $method = trim($_POST['method'] ?? 'mobile'); $phone = trim($_POST['phone'] ?? $signup['phone']);
            $reference = 'MS' . date('ymdHis') . strtoupper(bin2hex(random_bytes(2)));
            ensure_registration_transaction_type();
            $tierLabel = $selectedTier['name'] . ' (' . ($cycle === 'yearly' ? 'Yearly' : 'Monthly') . ')';
            db()->prepare('INSERT INTO transactions (user_id, type, tier, amount, currency, method, status, order_reference) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')->execute([$signup['user_id'], 'membership_signup', $tierLabel, $amount, 'TZS', $method, 'pending', $reference]);
            db()->prepare('UPDATE membership_signups SET tier_key = ?, cycle = ?, order_reference = ? WHERE id = ?')->execute([$selectedTier['key'], $cycle, $reference, $signupId]);
            $result = cp_start_payment($amount, $method, $phone, $reference, $signup['full_name'], $signup['email']);
            db()->prepare('UPDATE transactions SET provider_ref = ?, channel = ? WHERE order_reference = ?')->execute([$result['id'] ?? null, $result['channel'] ?? $method, $reference]);
            $_SESSION['active_payment'] = ['reference' => $reference, 'type' => 'membership_signup', 'name' => $signup['full_name']];
            if (!empty($result['checkoutLink'])) { header('Location: ' . $result['checkoutLink']); exit; }
            $_SESSION['flash'] = 'Membership payment request sent. Approve it on your phone; we will confirm it automatically.';
            header('Location: /membership-form/plans?id=' . $signupId . '&token=' . $token); exit;
        } catch (Throwable $e) { $_SESSION['flash'] = 'Payment could not start: ' . $e->getMessage(); header('Location: /membership-form/plans?id=' . (int)($_POST['id'] ?? 0) . '&token=' . urlencode((string)($_POST['token'] ?? ''))); exit; }
    }
    if ($action === 'donor_signup') {
        try {
            ensure_donor_sponsorships_table();
            $fullName = trim($_POST['full_name'] ?? '');
            $supporterType = $_POST['supporter_type'] ?? '';
            $contactPerson = trim($_POST['contact_person'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $whatsapp = trim($_POST['whatsapp'] ?? '');
            $email = strtolower(trim($_POST['email'] ?? ''));
            $location = trim($_POST['location_address'] ?? '');
            $supportTypes = array_filter((array)($_POST['support_type'] ?? []));
            $supportTypeOther = trim($_POST['support_type_other'] ?? '');
            $supportAreas = array_filter((array)($_POST['support_area'] ?? []));
            $supportAreaOther = trim($_POST['support_area_other'] ?? '');
            $amount = (float)($_POST['amount'] ?? 0);
            $preferredProject = trim($_POST['preferred_project'] ?? '');
            $frequency = trim($_POST['frequency'] ?? '');
            $wantsPartnership = $_POST['wants_partnership'] ?? '';
            $partnershipType = trim($_POST['partnership_type'] ?? '');
            $partnershipExpectation = trim($_POST['partnership_expectation'] ?? '');
            $wantsRecognition = $_POST['wants_recognition'] ?? '';
            $recognitionName = trim($_POST['recognition_name'] ?? '');
            $message = trim($_POST['message'] ?? '');
            $agreed = !empty($_POST['agreed']);
            $agreedTerms = ($_POST['agreed_terms'] ?? '0') === '1';
            if ($fullName === '' || $supporterType === '' || $phone === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $location === '') throw new RuntimeException('Please complete your name, supporter type, phone, email, and location.');
            if (!$agreedTerms) throw new RuntimeException('Please agree to the Donors & Sponsors Terms & Conditions to continue.');
            if (empty($supportTypes)) throw new RuntimeException('Please select at least one type of support.');
            if (empty($supportAreas)) throw new RuntimeException('Please select at least one area you would like to support.');
            if (!$agreed) throw new RuntimeException('Please confirm the declaration to submit the form.');
            $isFinancial = in_array('Financial Donation', $supportTypes, true) && $amount > 0;
            if (in_array('Financial Donation', $supportTypes, true) && $amount <= 0) throw new RuntimeException('Please enter the amount you would like to give.');
            $accessToken = bin2hex(random_bytes(16));
            db()->prepare('INSERT INTO donor_sponsorships (full_name, supporter_type, contact_person, phone, whatsapp, email, location_address, support_types, support_type_other, support_areas, support_area_other, amount, preferred_project, frequency, wants_partnership, partnership_type, partnership_expectation, wants_recognition, recognition_name, message, is_financial, access_token) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([$fullName, $supporterType, $contactPerson ?: null, $phone, $whatsapp ?: null, $email, $location, implode(', ', $supportTypes), $supportTypeOther ?: null, implode(', ', $supportAreas), $supportAreaOther ?: null, $amount ?: null, $preferredProject ?: null, $frequency ?: null, $wantsPartnership ?: null, $partnershipType ?: null, $partnershipExpectation ?: null, $wantsRecognition ?: null, $recognitionName ?: null, $message ?: null, $isFinancial ? 1 : 0, $accessToken]);
            $donorId = (int)db()->lastInsertId();
            if ($isFinancial) { header('Location: /support/pay?id=' . $donorId . '&token=' . $accessToken); exit; }
            header('Location: /donate?thanks=1'); exit;
        } catch (Throwable $e) { $_SESSION['flash'] = 'Could not submit your form: ' . $e->getMessage(); header('Location: /donate'); exit; }
    }
    if ($action === 'pay_donor_support') {
        try {
            ensure_donor_sponsorships_table();
            $donorId = (int)($_POST['id'] ?? 0);
            $token = (string)($_POST['token'] ?? '');
            $stmt = db()->prepare('SELECT * FROM donor_sponsorships WHERE id = ? AND access_token = ? LIMIT 1');
            $stmt->execute([$donorId, $token]);
            $donor = $stmt->fetch();
            if (!$donor) throw new RuntimeException('Your form session was not found. Please submit the form again.');
            if ($donor['status'] === 'paid') throw new RuntimeException('This contribution is already paid. Thank you!');
            $method = trim($_POST['method'] ?? 'mobile'); $phone = trim($_POST['phone'] ?? $donor['phone']);
            $amount = (float)$donor['amount'];
            if (!$amount) throw new RuntimeException('No amount was recorded for this contribution.');
            $reference = 'DN' . date('ymdHis') . strtoupper(bin2hex(random_bytes(2)));
            db()->prepare('INSERT INTO transactions (user_id, type, tier, amount, currency, method, status, order_reference) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')->execute([$user['id'] ?? null, 'donation', $donor['preferred_project'], $amount, 'TZS', $method, 'pending', $reference]);
            db()->prepare('UPDATE donor_sponsorships SET order_reference = ? WHERE id = ?')->execute([$reference, $donorId]);
            $result = cp_start_payment($amount, $method, $phone, $reference, $donor['full_name'], $donor['email']);
            db()->prepare('UPDATE transactions SET provider_ref = ?, channel = ? WHERE order_reference = ?')->execute([$result['id'] ?? null, $result['channel'] ?? $method, $reference]);
            $_SESSION['active_payment'] = ['reference' => $reference, 'type' => 'donation', 'name' => $donor['full_name']];
            if (!empty($result['checkoutLink'])) { header('Location: ' . $result['checkoutLink']); exit; }
            $_SESSION['flash'] = 'Payment request sent. Approve it on your phone; we will confirm it automatically.';
            header('Location: /support/pay?id=' . $donorId . '&token=' . $token); exit;
        } catch (Throwable $e) { $_SESSION['flash'] = 'Payment could not start: ' . $e->getMessage(); header('Location: /support/pay?id=' . (int)($_POST['id'] ?? 0) . '&token=' . urlencode((string)($_POST['token'] ?? ''))); exit; }
    }
}

$user = $_SESSION['user'] ?? null;
$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
$activePayment = $_SESSION['active_payment'] ?? null;
$public = ['/', '/about', '/contact', '/donate', '/blog', '/members', '/trips', '/login', '/signup'];
if (!$user && !in_array($path, $public, true)) { header('Location: /login'); exit; }
if ($path === '/admin' && (!$user || $user['role'] !== 'admin')) { header('Location: /login'); exit; }

$features = [['title'=>'Community','body'=>'Building a generous, connected community where every member can contribute and belong.'],['title'=>'Youth talent','body'=>'Creating pathways for young Tanzanians to develop their skills, confidence, and careers.'],['title'=>'Lasting impact','body'=>'Turning membership and donations into practical charity events and opportunity.']];
$tiers = tier_definitions();
$homeImages = ['community-01.jpg','community-04.jpg','community-06.jpg','community-08.jpg'];
$aboutImages = ['community-02.jpg','community-03.jpg','community-05.jpg','community-07.jpg'];
function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
function app_base_path(): string { $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/index.php')); $dir = str_replace('\\', '/', dirname($script)); return ($dir === '.' || $dir === '/') ? '' : rtrim($dir, '/'); }
function gallery(array $images, string $label, string $class=''): string { $base = app_base_path(); $html='<div class="gallery '.e($class).'" data-slideshow>'; foreach($images as $i=>$image) $html.='<figure class="slide '.($i===0?'is-active':'').'" data-slide><img src="'.e($base).'/assets/community/'.e($image). '" alt="'.e($label).'" loading="'.($i===0?'eager':'lazy').'" /></figure>'; $html.='<button class="slide-prev" type="button" aria-label="Previous photo" data-prev>‹</button><button class="slide-next" type="button" aria-label="Next photo" data-next>›</button><div class="dots">'; foreach($images as $i=>$image) $html.='<button type="button" class="dot '.($i===0?'active':'').'" aria-label="Show photo '.($i+1).'" data-dot="'.$i.'"></button>'; return $html.'</div></div>'; }
function ensure_profile_columns(): void { static $done = false; if ($done) return; $columns = []; foreach (db()->query('SHOW COLUMNS FROM users')->fetchAll() as $column) $columns[(string)$column['Field']] = true; if (!isset($columns['profile_image'])) db()->exec('ALTER TABLE users ADD COLUMN profile_image VARCHAR(255) NULL'); if (!isset($columns['bio'])) db()->exec('ALTER TABLE users ADD COLUMN bio TEXT NULL'); if (!isset($columns['email_verified_at'])) db()->exec('ALTER TABLE users ADD COLUMN email_verified_at DATETIME NULL'); if (!isset($columns['email_verification_token'])) db()->exec('ALTER TABLE users ADD COLUMN email_verification_token CHAR(64) NULL'); if (!isset($columns['email_verification_expires_at'])) db()->exec('ALTER TABLE users ADD COLUMN email_verification_expires_at DATETIME NULL'); $done = true; }
function public_app_url(string $path = ''): string { $local = is_file(__DIR__.'/local-config.php') ? (require __DIR__.'/local-config.php') : []; $base = rtrim((string)($local['APP_URL'] ?? getenv('APP_URL') ?: ''), '/'); if ($base === '') { $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http'; $base = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . app_base_path(); } return $base . '/' . ltrim($path, '/'); }
function send_verification_email(array $account, string $token): bool { $link = public_app_url('/verify-email?token=' . rawurlencode($token)); $body = "Hello {$account['name']},\n\nPlease verify your Royal Family TZ email address by opening this link:\n{$link}\n\nThis link expires in 24 hours. If you did not create this account, you can ignore this email.\n\nRoyal Family TZ"; return send_email((string)$account['email'], 'Verify your Royal Family TZ email', $body); }
function ensure_contact_columns(): void { static $done = false; if ($done) return; try { db()->exec("ALTER TABLE contact_messages ADD COLUMN status ENUM('new','replied') NOT NULL DEFAULT 'new'"); } catch (Throwable $e) {} try { db()->exec('ALTER TABLE contact_messages ADD COLUMN replied_at DATETIME NULL'); } catch (Throwable $e) {} $done = true; }
function ensure_community_tables(): void { static $done = false; if ($done) return; db()->exec("CREATE TABLE IF NOT EXISTS events (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, title VARCHAR(180) NOT NULL, description TEXT NOT NULL, event_date DATETIME NULL, location VARCHAR(180) NULL, published TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB"); db()->exec("CREATE TABLE IF NOT EXISTS notifications (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, title VARCHAR(180) NOT NULL, message TEXT NOT NULL, audience ENUM('all','members','admins') NOT NULL DEFAULT 'all', created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB"); $done = true; }
function ensure_trip_tables(): void { static $done = false; if ($done) return; try { db()->exec("ALTER TABLE transactions MODIFY type ENUM('donation','subscription','trip') NOT NULL"); } catch (Throwable $e) {} db()->exec("CREATE TABLE IF NOT EXISTS trips (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, title VARCHAR(180) NOT NULL, slug VARCHAR(180) NOT NULL UNIQUE, description TEXT NOT NULL, destination VARCHAR(180) NOT NULL, trip_date DATE NULL, meeting_point VARCHAR(180) NULL, poster_image VARCHAR(255) NULL, published TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB"); try { db()->exec('ALTER TABLE trips ADD COLUMN poster_image VARCHAR(255) NULL'); } catch (Throwable $e) {} db()->exec("CREATE TABLE IF NOT EXISTS trip_packages (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, trip_id INT UNSIGNED NOT NULL, name VARCHAR(120) NOT NULL, description TEXT NOT NULL, price DECIMAL(12,2) NOT NULL, capacity INT UNSIGNED NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, CONSTRAINT fk_trip_packages_trip FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE) ENGINE=InnoDB"); db()->exec("CREATE TABLE IF NOT EXISTS trip_bookings (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL, trip_id INT UNSIGNED NOT NULL, package_id INT UNSIGNED NOT NULL, transaction_id INT UNSIGNED NULL, guests INT UNSIGNED NOT NULL DEFAULT 1, status ENUM('pending','paid','cancelled') NOT NULL DEFAULT 'pending', created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, CONSTRAINT fk_trip_bookings_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, CONSTRAINT fk_trip_bookings_trip FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE, CONSTRAINT fk_trip_bookings_package FOREIGN KEY (package_id) REFERENCES trip_packages(id) ON DELETE CASCADE, CONSTRAINT fk_trip_bookings_transaction FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE SET NULL) ENGINE=InnoDB"); $done = true; }
function logo_url(): string { return app_base_path() . '/assets/royal-family-logo.jpg'; }
function profile_src(?array $user): string { if (!$user) return ''; $base = app_base_path(); if (!empty($user['profile_image'])) return $base.'/uploads/profiles/'.basename($user['profile_image']); return (string)($user['avatar_url'] ?? ''); }
function trip_poster_src(?string $poster): string { return $poster ? app_base_path().'/uploads/trips/'.basename($poster) : ''; }
function trip_poster_markup(?string $poster, string $title): string { $src = trip_poster_src($poster); if (!$src) return ''; $safeSrc = e($src); $safeTitle = e($title); return strtolower(pathinfo((string)$poster, PATHINFO_EXTENSION)) === 'pdf' ? '<iframe class="trip-poster trip-poster-pdf" src="'.$safeSrc.'" title="'.$safeTitle.' poster"></iframe><a class="muted" href="'.$safeSrc.'" target="_blank" rel="noopener">Open poster in a new tab</a>' : '<img class="trip-poster" src="'.$safeSrc.'" alt="'.$safeTitle.' poster" loading="lazy">'; }
function save_trip_poster(?array $file): ?string { if (!$file || empty($file['tmp_name'])) return null; if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || (int)($file['size'] ?? 0) > 8 * 1024 * 1024) throw new RuntimeException('Please upload a poster under 8 MB.'); $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','application/pdf'=>'pdf']; $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']); if (!isset($allowed[$mime])) throw new RuntimeException('Poster must be JPG, PNG, WEBP, or PDF.'); $dir = __DIR__.'/uploads/trips'; if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) throw new RuntimeException('Trip upload folder could not be created.'); $name = 'trip-'.date('YmdHis').'-'.bin2hex(random_bytes(5)).'.'.$allowed[$mime]; if (!move_uploaded_file($file['tmp_name'], $dir.'/'.$name)) throw new RuntimeException('Poster upload failed.'); return $name; }
function nav(string $current, ?array $user): void { $base=app_base_path(); $links=['/'=>'Home','/about'=>'About','/members'=>'Members','/trips'=>'Trips','/blog'=>'Blog','/contact'=>'Contact','/donate'=>'Donate']; echo '<header><div class="nav"><a class="brand" href="'.$base.'/"><img class="brand-logo" src="'.e(logo_url()).'" alt="Royal Family TZ logo"><span>Royal Family <small>Tanzania</small></span></a><input class="menu-checkbox" type="checkbox" id="mobile-menu"><label class="menu-toggle" for="mobile-menu" aria-label="Open menu">☰</label><nav data-mobile-nav>'; foreach($links as $href=>$label) echo '<a class="'.($current===$href?'active':'').'" href="'.e($base.$href).'">'.$label.'</a>'; if($user) echo '<a href="'.e($base.'/dashboard').'">Dashboard</a><a class="outline" href="'.e($base.'/logout').'">Log out</a>'; else echo '<a href="'.e($base.'/login').'">Log in</a>'; echo '</nav></div></header>'; }
function layout(string $title, string $content, string $path, ?array $user, ?string $flash=null, string $description = "", string $ogImage = ""): void { global $activePayment; echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="description" content="'.e($description ?: 'Royal Family Tanzania - Empowering community, youth talent, and practical impact in Tanzania.').'">
    <meta name="keywords" content="Royal Family TZ, Tanzania, Community, Youth, Talent, Arusha, Impact, Donation, Charity">
    <meta property="og:title" content="'.e($title).' | '.APP_NAME.'">
    <meta property="og:description" content="'.e($description ?: 'Empowering community, youth talent, and practical impact in Tanzania.').'">
    <meta property="og:image" content="'.e($ogImage ?: public_app_url('assets/logo.png')).'">
    <meta property="og:url" content="'.e(public_app_url($path)).'">
    <meta property="og:type" content="website">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="icon" href="'.e(logo_url()).'" type="image/png">
<title>'.e($title).' | '.APP_NAME.'</title><link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Fraunces:opsz,wght@9..144,600;9..144,700&display=swap" rel="stylesheet"><style>'.css().'</style></head><body>'; nav($path,$user); if($flash) echo '<div class="flash">'.e($flash).'</div>'; echo '<main>'.$content.'</main><div class="success-modal" data-success-modal hidden><div class="success-card"><button type="button" class="success-close" data-success-close aria-label="Close">×</button><div class="success-mark">✓</div><h2 data-success-title>Congratulations!</h2><p data-success-message></p><button type="button" class="btn gold" data-success-close>Continue</button></div></div><footer><div><strong>Royal Family TZ</strong><p>Community, youth talent, and practical impact in Tanzania.</p></div><div><strong>Find us in Arusha</strong><p>Arusha, Tanzania</p><p>Phone: <a href="tel:0774002734">0774002734</a></p><p>Email: <a href="mailto:info@royalfamilytz.org">info@royalfamilytz.org</a></p></div><div><a href="/about">About</a><a href="/contact">Contact</a><a href="/donate">Support us</a></div><p class="copyright">© '.date('Y').' Royal Family TZ</p></footer><script>document.querySelectorAll("[data-slideshow]").forEach(function(box){var slides=[...box.querySelectorAll("[data-slide]")],dots=[...box.querySelectorAll("[data-dot]")],i=0;function show(n){i=(n+slides.length)%slides.length;slides.forEach((s,k)=>s.classList.toggle("is-active",k===i));dots.forEach((d,k)=>d.classList.toggle("active",k===i));}box.querySelector("[data-prev]").onclick=()=>show(i-1);box.querySelector("[data-next]").onclick=()=>show(i+1);dots.forEach(d=>d.onclick=()=>show(Number(d.dataset.dot)));setInterval(()=>show(i+1),5000);});var toggle=document.querySelector("[data-menu-toggle]"),mobileNav=document.querySelector("[data-mobile-nav]");if(toggle&&mobileNav){toggle.onclick=function(){var open=mobileNav.classList.toggle("is-open");toggle.setAttribute("aria-expanded",open?"true":"false");toggle.textContent=open?"×":"☰";};}var modal=document.querySelector("[data-success-modal]"),closeButtons=document.querySelectorAll("[data-success-close]");closeButtons.forEach(function(b){b.onclick=function(){if(modal)modal.hidden=true;}});var shownPaymentReference=null;function showSuccess(tx){if(!modal||shownPaymentReference===tx.reference)return;shownPaymentReference=tx.reference;var title=document.querySelector("[data-success-title]"),message=document.querySelector("[data-success-message]"),name=tx.name||"you";title.textContent="Congratulations, "+name+"!";message.textContent=tx.type==="donation"?"Thank you for donating. May God bless you for your generosity.":tx.type==="subscription"||tx.type==="membership_signup"?"Your membership payment was successful. May God bless you and welcome to Royal Family TZ.":tx.type==="registration"?"Your Royal Family Foundation registration is confirmed. Welcome to the family.":tx.type==="support"?"Thank you for your generous support! Our team will reach out to arrange the details.":"Your trip payment was successful. May God bless you.";modal.hidden=false;}var paymentRef='.(isset($activePayment['reference']) ? e((string)$activePayment['reference']) : '').';if(paymentRef){var tries=0;var timer=setInterval(function(){fetch("'.e(app_base_path()).'/api/transaction-status?reference="+encodeURIComponent(paymentRef),{credentials:"same-origin",cache:"no-store"}).then(r=>r.json()).then(function(tx){if(tx.status==="paid"){clearInterval(timer);showSuccess(tx);}else if(tx.status==="failed"){clearInterval(timer);}}).catch(function(){});if(++tries>60)clearInterval(timer);},3000);}if('.(!empty($_GET['thanks']) ? 'true' : 'false').'){showSuccess({type:"support",name:"",reference:"thanks-"+Date.now()});}</script></body></html>'; }
function css(): string { return <<<'CSS'
:root{--ink:#17251f;--royal:#285743;--gold:#c9a54c;--paper:#f7f3e8;--muted:#66736c}*{box-sizing:border-box}body{margin:0;background:var(--paper);color:var(--ink);font-family:'DM Sans',sans-serif;line-height:1.6}h1,h2,h3{font-family:Fraunces,serif;line-height:1.1;margin:0 0 18px}h1{font-size:clamp(2.5rem,6vw,5rem)}h2{font-size:clamp(2rem,4vw,3.2rem)}a{color:inherit;text-decoration:none}.nav{max-width:1180px;margin:auto;padding:22px 28px;display:flex;align-items:center;justify-content:space-between;gap:30px}.brand-logo{width:76px;height:76px;object-fit:contain;border-radius:14px;background:#fff;padding:4px;box-shadow:0 3px 12px #17382c22}.brand{display:flex;align-items:center;gap:10px;font-family:Fraunces;font-size:1.15rem;font-weight:700}.brand small{display:block;font:500 .7rem 'DM Sans';letter-spacing:.14em;text-transform:uppercase;color:var(--gold)}.crest{display:grid;place-items:center;border:2px solid var(--gold);border-radius:50%;width:42px;height:42px;color:var(--gold);font:700 .9rem Fraunces}nav{display:flex;align-items:center;gap:20px;flex-wrap:wrap;font-size:.92rem}nav a{padding:7px 0;color:#495a51}nav a:hover,nav a.active{color:var(--royal);border-bottom:2px solid var(--gold)}.outline{border:1px solid var(--royal);border-radius:999px;padding:8px 15px!important}.hero{max-width:1180px;margin:40px auto 70px;padding:70px 28px;display:grid;grid-template-columns:1.15fr .85fr;gap:50px;align-items:center}.eyebrow{color:var(--gold);font-weight:700;letter-spacing:.13em;text-transform:uppercase;font-size:.76rem}.hero p,.lead{font-size:1.15rem;color:var(--muted);max-width:620px}.hero-art{min-height:380px;border-radius:24px;background:linear-gradient(145deg,#315e48,#15382c);display:grid;place-items:center;color:#e9d18a;font:700 5rem Fraunces;box-shadow:20px 20px 0 #e9ddc4}.google-icon{display:inline-flex;align-items:center;justify-content:center;width:1.35rem;height:1.35rem;margin-right:.45rem;border-radius:50%;background:#fff;color:#4285f4;font-weight:800;font-family:Arial,sans-serif}.btn{display:inline-block;background:var(--royal);color:#fff;border:0;border-radius:999px;padding:13px 22px;font-weight:700;cursor:pointer;margin:12px 8px 0 0}.btn.gold{background:var(--gold);color:var(--ink)}.section{max-width:1180px;margin:0 auto;padding:55px 28px}.center{text-align:center}.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:22px}.card,.form-card,.stat{background:#fffdf7;border:1px solid #e8dfcb;border-radius:18px;padding:26px;box-shadow:0 5px 20px #574a2810}.card h3{font-size:1.5rem}.card p,.muted{color:var(--muted)}.card .icon{color:var(--gold);font-size:2rem;margin-bottom:10px}.page{max-width:850px;margin:55px auto;padding:0 28px}.form-card{max-width:560px;margin:30px auto}.form{display:grid;gap:14px}.form label{font-weight:700;font-size:.9rem}.form input,.form textarea,.form select{width:100%;padding:12px 14px;border:1px solid #d9d1bf;border-radius:9px;background:#fff
.payment-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:15px;margin:15px 0}
.payment-option{position:relative}
.payment-option input[type="radio"]{position:absolute;opacity:0;width:0;height:0}
.payment-option label{display:flex;flex-direction:column;align-items:center;justify-content:center;padding:15px;border:2px solid #e8dfcb;border-radius:12px;background:#fff;cursor:pointer;transition:all .2s;text-align:center;height:100%;box-shadow:0 2px 5px rgba(0,0,0,0.05)}
.payment-option input[type="radio"]:checked + label{border-color:var(--gold);background:#fffef0;box-shadow:0 4px 12px #c9a54c22}
.payment-logo{height:40px;width:100%;object-fit:contain;margin-bottom:8px}
.payment-option span{font-size:.85rem;font-weight:700;color:var(--ink)}
@media(max-width:480px){.payment-grid{grid-template-columns:1fr}}
.tier-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:22px;align-items:stretch}
.tier-card{background:#fffdf7;border:1px solid #e8dfcb;border-radius:18px;padding:28px 24px;box-shadow:0 5px 20px #574a2810;display:flex;flex-direction:column;align-items:flex-start;text-align:left;height:100%}
.tier-icon{font-size:1.8rem;margin-bottom:6px}
.tier-card h3{font-size:1.35rem;margin-bottom:8px}
.badge-chip{display:inline-flex;align-items:center;gap:6px;padding:6px 14px;border-radius:999px;font-weight:700;font-size:.78rem;margin-bottom:14px;white-space:nowrap}
.badge-spring{background:#e4f9e1;color:#1f7a3d;border:1px solid #9be8a4}
.badge-silver{background:#eef0f2;color:#5b6470;border:1px solid #c7ccd1}
.badge-gold{background:#fbf1d8;color:#8a6d1d;border:1px solid #e9cf7f}
.tier-card p{margin-bottom:16px}
.plan-options{display:grid;grid-template-columns:1fr 1fr;gap:10px;width:100%;margin-top:auto}
.plan-btn{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px;padding:12px 8px;border:2px solid #e8dfcb;border-radius:12px;background:#fff;text-align:center;transition:all .2s}
.plan-btn:hover{border-color:var(--gold);background:#fffef0}
.plan-btn small{color:var(--muted);font-weight:700;font-size:.72rem;text-transform:uppercase;letter-spacing:.04em}
.plan-btn strong{font-family:Fraunces;font-size:1.05rem;color:var(--ink)}
.plan-summary{padding-bottom:18px;margin-bottom:18px;border-bottom:1px solid #e8dfcb}
.plan-summary h2{margin:6px 0}
.amount-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin:6px 0 14px}
.amount-chip{position:relative}
.amount-chip input[type="radio"]{position:absolute;opacity:0;width:0;height:0}
.amount-chip label{display:flex;align-items:center;justify-content:center;padding:14px 6px;border:2px solid #e8dfcb;border-radius:12px;background:#fff;cursor:pointer;font-weight:700;text-align:center;font-size:.92rem;height:100%}
.amount-chip input:checked+label{border-color:var(--gold);background:#fffef0;box-shadow:0 4px 12px #c9a54c22}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.form-row label{width:100%}
@media(max-width:760px){.tier-grid{grid-template-columns:1fr}.amount-grid{grid-template-columns:repeat(2,1fr)}.form-row{grid-template-columns:1fr}}
}.flash{max-width:1100px;margin:15px auto 0;padding:13px 18px;background:#e2f1e6;border-left:4px solid var(--royal);color:var(--royal)}.stats{display:grid;grid-template-columns:repeat(3,1fr);gap:18px;margin:25px 0}.stat strong{display:block;font:700 2rem Fraunces;color:var(--royal)}.stat span{color:var(--muted);font-size:.9rem}footer{margin-top:80px;padding:40px max(28px,calc((100% - 1124px)/2));background:#16382c;color:#e8efe8;display:grid;grid-template-columns:2fr 1fr 1fr;gap:30px}footer p{color:#b5c7bc}footer a{display:block;margin-bottom:6px;color:#d6e3d8}.copyright{align-self:end;text-align:right;font-size:.8rem}.gallery{position:relative;height:clamp(280px,42vw,520px);min-height:280px;border-radius:24px;overflow:hidden;background:#173c2d;box-shadow:20px 20px 0 #e9ddc4}.gallery .slide{display:none;margin:0;height:100%}.gallery .slide.is-active{display:block;animation:fade .45s ease}.gallery img{display:block;width:100%;height:100%;object-fit:cover}.about-gallery{margin:35px 0;height:clamp(300px,48vw,560px);min-height:300px}.about-gallery .slide,.about-gallery img{min-height:0}.menu-checkbox{position:absolute;opacity:0;pointer-events:none}.menu-toggle{display:none;border:1px solid var(--royal);background:#fff;border-radius:9px;padding:7px 11px;font-size:1.35rem;color:var(--royal);cursor:pointer}.success-modal{position:fixed;inset:0;z-index:20;background:#10271dcc;display:grid;place-items:center;padding:20px}.success-modal[hidden]{display:none}.success-card{position:relative;max-width:460px;width:100%;padding:36px 28px;text-align:center;background:#fffdf7;border-radius:22px;box-shadow:0 20px 70px #0005}.success-mark{width:66px;height:66px;margin:0 auto 18px;border-radius:50%;display:grid;place-items:center;background:#e2f1e6;color:var(--royal);font-size:2.5rem;font-weight:700}.success-close{position:absolute;right:14px;top:8px;border:0;background:none;color:var(--muted);font-size:2rem;cursor:pointer}.slide-prev,.slide-next{position:absolute;top:50%;transform:translateY(-50%);border:0;border-radius:50%;width:42px;height:42px;background:#ffffffd9;color:var(--royal);font-size:2rem;line-height:1;cursor:pointer}.slide-prev{left:16px}.slide-next{right:16px}.dots{position:absolute;bottom:16px;left:0;right:0;text-align:center}.dot{width:9px;height:9px;border:0;border-radius:50%;margin:0 4px;background:#fff8;cursor:pointer}.dot.active{background:var(--gold);transform:scale(1.35)}.google-btn{display:block;text-align:center;padding:13px 18px;border:1px solid #cfcfcf;border-radius:9px;background:#fff;color:#303030;font-weight:700}.or{text-align:center;color:var(--muted);font-size:.85rem;margin:16px 0}.dashboard-welcome{display:flex;align-items:center;gap:24px;margin-bottom:28px}.admin-action{margin:18px 0}.admin-action summary{list-style:none}.admin-action summary::-webkit-details-marker{display:none}.trip-admin-row{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 0;border-bottom:1px solid #e8dfcb}.trip-poster{display:block;width:100%;max-height:420px;min-height:180px;object-fit:cover;border-radius:14px;margin:-4px 0 22px;border:0}.trip-poster-pdf{height:520px;background:#fff}.trip-poster-link{display:block}.trip-admin-row small{color:var(--muted);margin-left:6px}.btn.danger{background:#a44135;color:#fff}.dashboard-avatar{width:96px;height:96px;flex:0 0 96px;border-radius:50%;object-fit:cover;border:4px solid #fff;box-shadow:0 4px 18px #17382c33}.dashboard-initial{display:grid;place-items:center;background:var(--royal);color:#fff;font:700 2.8rem Fraunces}.avatar-picker{position:relative;width:128px;height:128px;margin:5px auto 4px}.avatar-picker .profile-avatar,.avatar-picker .profile-placeholder{width:128px;height:128px;margin:0}.camera-button{position:absolute;right:-2px;bottom:2px;width:42px;height:42px;display:grid;place-items:center;border:3px solid #fff;border-radius:50%;background:var(--gold);color:var(--ink);font-size:1.2rem;cursor:pointer;box-shadow:0 3px 12px #0003}.camera-button:hover{background:var(--royal);color:#fff}.photo-hint{text-align:center;color:var(--muted);font-size:.85rem;margin:0 0 22px}.visually-hidden{position:absolute!important;width:1px!important;height:1px!important;padding:0!important;margin:-1px!important;overflow:hidden!important;clip:rect(0,0,0,0)!important;white-space:nowrap!important;border:0!important}.profile-avatar,.profile-placeholder{width:110px;height:110px;border-radius:50%;object-fit:cover;margin:0 auto 22px;display:block}.profile-placeholder{display:grid;place-items:center;background:var(--royal);color:#fff;font:700 3rem Fraunces}.membership-card{background:linear-gradient(135deg,#173c2d,#0f261c);border-radius:20px;padding:26px;color:#fff;box-shadow:0 10px 30px #0004;max-width:420px}.membership-card-top{margin-bottom:14px}.membership-card-body{display:flex;align-items:center;gap:16px}.membership-card-photo{width:64px;height:64px;flex:0 0 64px;border-radius:50%;object-fit:cover;border:3px solid var(--gold)}.membership-card-initial{display:grid;place-items:center;background:var(--gold);color:var(--ink);font:700 1.6rem Fraunces}.membership-card-body h3{margin:0;color:#fff}.membership-card-body p{margin:2px 0;color:#cfe0d5}.membership-card-id{font-family:monospace;letter-spacing:.05em;color:var(--gold);font-weight:700}.ticket-grid{display:grid;gap:16px}.ticket-card{display:flex;justify-content:space-between;align-items:center;gap:16px;background:#fffdf7;border:1px solid #e8dfcb;border-radius:16px;padding:20px;flex-wrap:wrap}.ticket-info{flex:1;min-width:200px}.ticket-qr{width:110px;height:110px;border-radius:8px;background:#fff;padding:6px;border:1px solid #e8dfcb}.terms-card{max-height:90vh;overflow:auto}.terms-body{max-height:280px;overflow:auto;text-align:left;font-size:.87rem;color:var(--ink);background:#faf6ea;border:1px solid #e8dfcb;border-radius:12px;padding:14px 16px;margin:14px 0}.terms-agree{display:flex;gap:10px;align-items:flex-start;font-weight:400;text-align:left;margin-bottom:14px}.terms-agree input{width:auto;margin-top:4px}@keyframes fade{from{opacity:.35}to{opacity:1}}@media(max-width:760px){.menu-toggle{display:block}.nav{padding:14px 18px;flex-wrap:wrap}.nav nav{display:none;width:100%;flex-direction:column;align-items:stretch;gap:3px;padding-top:8px}.nav nav.is-open{display:flex}.menu-checkbox:checked~nav[data-mobile-nav]{display:flex}.nav nav a{padding:10px 4px;border-bottom:1px solid #e8dfcb}.brand-logo{width:58px;height:58px}.hero{grid-template-columns:1fr;padding-top:25px}.hero-art,.gallery{min-height:280px}.gallery .slide,.gallery img,.about-gallery .slide,.about-gallery img{min-height:280px}.dashboard-welcome{align-items:flex-start}.grid,.stats,footer{grid-template-columns:1fr}nav{gap:11px;font-size:.8rem}.copyright{text-align:left}}
CSS; }

$events = []; $notifications = [];
if ($user) { try { ensure_community_tables(); $events = db()->query("SELECT title, description, event_date, location FROM events WHERE published = 1 ORDER BY event_date IS NULL, event_date ASC, created_at DESC LIMIT 6")->fetchAll(); $audienceSql = $user['role'] === 'admin' ? "('all','members','admins')" : "('all','members')"; $notifications = db()->query("SELECT title, message, created_at FROM notifications WHERE audience IN {$audienceSql} ORDER BY created_at DESC LIMIT 8")->fetchAll(); } catch (Throwable $e) {} }
$content='';
switch ($path) {
case '/': $content='<section class="hero"><div><p class="eyebrow">Community • purpose • possibility</p><h1>A stronger Tanzania starts with us.</h1><p>Royal Family TZ brings people together to support community action and help young talent grow.</p><a class="btn" href="/members">Become a member</a><a class="btn gold" href="/donate">Support the mission</a></div>'.gallery($homeImages,'Royal Family TZ community','hero-gallery').'</section><section class="section"><div class="center"><p class="eyebrow">What we believe</p><h2>Community with a clear purpose.</h2></div><div class="grid">'; foreach($features as $f) $content.='<article class="card"><div class="icon">✦</div><h3>'.e($f['title']).'</h3><p>'.e($f['body']).'</p></article>'; $content.='</div></section>'; break;
case '/about': $content='<div class="page"><p class="eyebrow">Our story</p><h1>About Royal Family TZ</h1><p class="lead">We are a Tanzanian community platform connecting people who believe that generosity, collaboration, and youth opportunity can change lives.</p>'.gallery($aboutImages,'Royal Family TZ community impact','about-gallery').'<div class="grid"><div class="card"><h3>Our Vision</h3><p>To become a trusted Tanzanian community foundation where generosity, youth talent, and practical opportunity create lasting transformation.</p></div><div class="card"><h3>Our Mission</h3><p>To create a dependable home for members, fund meaningful charity events, and build programs that let young Tanzanians discover and develop their talent.</p></div></div>'; break;
case '/members':
    $tierCards = '';
    foreach ($tiers as $t) {
        $priceRow = $t['monthly'] !== null
            ? '<div class="plan-options"><div class="plan-btn" style="pointer-events:none"><small>Monthly</small><strong>TZS '.number_format($t['monthly']).'</strong></div><div class="plan-btn" style="pointer-events:none"><small>Yearly</small><strong>TZS '.number_format($t['yearly']).'</strong></div></div>'
            : '<div class="plan-options"><div class="plan-btn" style="pointer-events:none;grid-column:1 / -1"><small>Yearly only</small><strong>TZS '.number_format($t['yearly']).'</strong></div></div>';
        $tierCards .= '<article class="tier-card"><div class="tier-icon">'.$t['icon'].'</div><h3>'.e($t['name']).'</h3><span class="badge-chip '.e($t['badge_class']).'">'.$t['icon'].' '.e($t['badge']).'</span><p class="muted">You get: '.e($t['perk']).'</p>'.$priceRow.'</article>';
    }
    $content='<div class="page members-page"><p class="eyebrow">Join the movement</p><h1>Become a member.</h1><p class="lead">Every membership helps fund community events and youth-talent opportunities.</p><div class="tier-grid">'.$tierCards.'</div><div style="text-align:center;margin-top:24px"><a class="btn gold" href="/membership-form">Become a member</a></div><div class="card" style="margin-top:32px"><h3>Official Foundation registration</h3><p class="muted">Prefer to register as a formal Royal Family Foundation member (Founder, Ordinary, or Honorary) under our Constitution? This is a one-time Tsh 5,000 registration fee plus a Tsh 20,000 annual fee.</p><a class="btn" href="/apply-membership">Start official registration</a></div></div>';
    break;
case '/membership-form':
    $content = '<div class="success-modal" id="membership-terms-modal"><div class="success-card terms-card"><h2>Foundation Membership Terms & Conditions</h2><div class="terms-body">'
        . '<p>By becoming a Royal Family TZ member, you agree to the following:</p>'
        . '<p>1. Membership fees (monthly or yearly, depending on your chosen tier) are used to fund community events, youth-talent programs, and Organization operations, and are non-refundable once payment is confirmed.</p>'
        . '<p>2. Your membership ID card (Silver tier) or T-shirt and ID card (Gold tier) will be issued once your payment has been confirmed by our payment provider.</p>'
        . '<p>3. Membership benefits are personal to you and may not be transferred to another person.</p>'
        . '<p>4. You agree to conduct yourself respectfully at all Royal Family TZ events and community activities.</p>'
        . '<p>5. Royal Family TZ may update membership benefits or pricing from time to time; continuing members will be notified of material changes.</p>'
        . '<p>6. The information you provide in this form is accurate to the best of your knowledge, and will be used solely for membership administration and communication.</p>'
        . '</div><label class="terms-agree"><input type="checkbox" id="membership-terms-agree">I have read and agree to the Foundation Membership Terms & Conditions.</label><button type="button" class="btn gold" id="membership-terms-continue" disabled style="width:100%">Continue</button></div></div>'
        . '<script>(function(){var agree=document.getElementById("membership-terms-agree"),cont=document.getElementById("membership-terms-continue"),modal=document.getElementById("membership-terms-modal");agree.addEventListener("change",function(){cont.disabled=!agree.checked;});cont.addEventListener("click",function(){modal.hidden=true;var field=document.getElementById("agreed_terms_field");if(field)field.value="1";});})();</script>'
        . '<div class="page"><p class="eyebrow">Royal Family TZ</p><h1>Become a member</h1><p class="lead">Tell us who you are, then choose your Silver or Gold membership plan.</p><div class="form-card"><form class="form" method="post"><input type="hidden" name="action" value="membership_signup_start"><input type="hidden" id="agreed_terms_field" name="agreed_terms" value="0"><label>Full name<input name="full_name" value="'.e($user['name'] ?? '').'" required></label><label>Email address<input type="email" name="email" value="'.e($user['email'] ?? '').'" required></label><div class="form-row"><label>Phone number<input name="phone" placeholder="0712345678" required></label><label>WhatsApp number<input name="whatsapp" placeholder="0712345678"></label></div><div class="form-row"><label>Gender<select name="gender" required><option value="">Select</option><option>Male</option><option>Female</option></select></label><label>City / region<input name="city_region" placeholder="e.g. Arusha" required></label></div><button class="btn gold" type="submit">Continue to plans</button></form></div></div>';
    break;
case '/membership-form/plans':
    ensure_membership_signups_table();
    $signupId = (int)($_GET['id'] ?? 0);
    $signupToken = (string)($_GET['token'] ?? '');
    $signupStmt = db()->prepare('SELECT * FROM membership_signups WHERE id = ? AND access_token = ? LIMIT 1');
    $signupStmt->execute([$signupId, $signupToken]);
    $signupRow = $signupStmt->fetch();
    if (!$signupRow) { $content = '<div class="page"><p class="eyebrow">Royal Family TZ</p><h1>Session not found.</h1><p class="lead">Please fill in the membership form again.</p><a class="btn" href="/membership-form">Back to form</a></div>'; break; }
    if ($signupRow['status'] === 'paid') {
        $paidTier = tier_by_key($tiers, (string)$signupRow['tier_key']);
        $content = '<div class="page"><p class="eyebrow">Royal Family TZ</p><h1>You are already a member!</h1><p class="lead">Thank you, '.e($signupRow['full_name']).'. Your '.e($paidTier['name'] ?? 'membership').' is confirmed.</p><p><strong>Membership ID:</strong> '.e($signupRow['membership_id']).'</p></div>';
        break;
    }
    $planCards = '';
    foreach ($tiers as $t) {
        $cycleOptions = $t['monthly'] !== null
            ? '<div class="plan-options"><button type="submit" name="cycle" value="monthly" class="plan-btn"><small>Monthly</small><strong>TZS '.number_format($t['monthly']).'</strong></button><button type="submit" name="cycle" value="yearly" class="plan-btn"><small>Yearly</small><strong>TZS '.number_format($t['yearly']).'</strong></button></div>'
            : '<div class="plan-options"><button type="submit" name="cycle" value="yearly" class="plan-btn" style="grid-column:1 / -1"><small>Yearly only</small><strong>TZS '.number_format($t['yearly']).'</strong></button></div>';
        $planCards .= '<article class="tier-card"><div class="tier-icon">'.$t['icon'].'</div><h3>'.e($t['name']).'</h3><span class="badge-chip '.e($t['badge_class']).'">'.$t['icon'].' '.e($t['badge']).'</span><p class="muted">You get: '.e($t['perk']).'</p><form method="post">'
            . '<input type="hidden" name="action" value="membership_signup_pay"><input type="hidden" name="id" value="'.$signupId.'"><input type="hidden" name="token" value="'.e($signupToken).'"><input type="hidden" name="tier_key" value="'.e($t['key']).'"><input type="hidden" name="phone" value="'.e($signupRow['phone']).'"><label style="font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:var(--muted)">Payment method<select name="method" style="width:100%;padding:10px;border:1px solid #d9d1bf;border-radius:9px;margin:6px 0 12px"><option value="mpesa">Vodacom M-Pesa</option><option value="tigopesa">Tigo Pesa / Mixx</option><option value="airtelmoney">Airtel Money</option><option value="halopesa">Halopesa</option><option value="card">Credit / Debit Card</option></select></label>'
            . $cycleOptions . '</form></article>';
    }
    $content = '<div class="page"><p class="eyebrow">Royal Family TZ</p><h1>Choose your plan, '.e($signupRow['full_name']).'</h1><p class="lead">Pick Silver or Gold, and monthly or yearly billing. You will confirm your mobile money payment next.</p><div class="tier-grid">'.$planCards.'</div></div>';
    break;
case '/apply-membership':
    $content = '<div class="page"><p class="eyebrow">Royal Family Foundation</p><h1>Official membership registration</h1><p class="lead">"Not Related by Blood, United by Dreams" — complete this form to register as a Founder, Ordinary, or Honorary member. A Tsh 5,000 registration fee and Tsh 20,000 annual fee apply to every category.</p><div class="form-card"><form class="form" method="post"><input type="hidden" name="action" value="apply_membership">'
        . '<h3>A. Personal information (must be 18 years or above)</h3>'
        . '<div class="form-row"><label>Full legal name (first, middle, surname)<input name="full_name" required></label><label>Date of birth<input type="date" name="date_of_birth" required></label></div>'
        . '<div class="form-row"><label>Gender<select name="gender" required><option value="">Select</option><option>Male</option><option>Female</option></select></label><label>Nationality<input name="nationality" required></label></div>'
        . '<label>NIDA / ID number<input name="nida_number"></label>'
        . '<h3>B. Contact details & location</h3>'
        . '<div class="form-row"><label>Phone number (primary)<input name="phone" placeholder="0712345678" required></label><label>WhatsApp number<input name="whatsapp" placeholder="0712345678"></label></div>'
        . '<div class="form-row"><label>Email address<input type="email" name="email" required></label><label>City / region of residence<input name="city_region" required></label></div>'
        . '<label>District & street / ward address<input name="district_address"></label>'
        . '<h3>C. Membership category</h3>'
        . '<div class="amount-grid"><div class="amount-chip"><input type="radio" name="category" value="Founder Member" id="cat-founder" required><label for="cat-founder">Founder Member</label></div><div class="amount-chip"><input type="radio" name="category" value="Ordinary Member" id="cat-ordinary"><label for="cat-ordinary">Ordinary Member</label></div><div class="amount-chip"><input type="radio" name="category" value="Honorary Member" id="cat-honorary"><label for="cat-honorary">Honorary Member</label></div></div>'
        . '<label>Current occupation / profession<input name="occupation"></label>'
        . '<label>Institution / workplace / business<input name="workplace"></label>'
        . '<label>Key skills / profession / talents (e.g. media, digital innovation, IT, leadership)<input name="skills"></label>'
        . '<h3>D. Emergency contact information</h3>'
        . '<div class="form-row"><label>Emergency contact person name<input name="emergency_name"></label><label>Relationship to applicant<input name="emergency_relationship"></label></div>'
        . '<div class="form-row"><label>Phone number<input name="emergency_phone"></label><label>City / residence<input name="emergency_city"></label></div>'
        . '<h3>E. Member declaration & commitment</h3>'
        . '<label style="display:flex;align-items:flex-start;gap:10px;font-weight:400"><input type="checkbox" name="agreed_constitution" value="1" required style="width:auto;margin-top:4px">I confirm I am 18 years of age or older and of sound mind, that all information provided is accurate, and I have read and agree to uphold the Vision, Mission, Objectives, and Constitution of the Royal Family Foundation, including the Registration Fee (Tsh 5,000) and Annual Fee (Tsh 20,000).</label>'
        . '<button class="btn gold" type="submit">Submit registration</button></form></div></div>';
    break;
case '/apply-membership/pay':
    ensure_membership_applications_table();
    $applicationId = (int)($_GET['id'] ?? 0);
    $accessToken = (string)($_GET['token'] ?? '');
    $appStmt = db()->prepare('SELECT * FROM membership_applications WHERE id = ? AND access_token = ? LIMIT 1');
    $appStmt->execute([$applicationId, $accessToken]);
    $application = $appStmt->fetch();
    if (!$application) { $content = '<div class="page"><p class="eyebrow">Royal Family Foundation</p><h1>Registration not found.</h1><p class="lead">Please submit the registration form again.</p><a class="btn" href="/apply-membership">Back to registration</a></div>'; break; }
    if ($application['status'] === 'paid') {
        $content = '<div class="page"><p class="eyebrow">Royal Family Foundation</p><h1>You are already a member!</h1><p class="lead">Thank you, '.e($application['full_name']).'. Your '.e($application['category']).' registration is confirmed.</p><p><strong>Membership ID:</strong> '.e($application['membership_id']).'</p></div>';
        break;
    }
    $content = '<div class="page"><p class="eyebrow">Royal Family Foundation</p><h1>Confirm and pay</h1><div class="form-card"><div class="plan-summary"><h2>'.e($application['full_name']).'</h2><p class="muted">Category: '.e($application['category']).'</p><p class="muted">Registration fee: TZS 5,000 &nbsp;+&nbsp; Annual fee: TZS 20,000 &nbsp;=&nbsp; <strong>Total TZS 25,000</strong></p></div><form class="form" method="post" data-payment-reference="'.e((string)($application['order_reference'] ?? '')).'"><input type="hidden" name="action" value="pay_membership_fee"><input type="hidden" name="id" value="'.(int)$application['id'].'"><input type="hidden" name="token" value="'.e($accessToken).'"><label>Mobile number<input name="phone" value="'.e($application['phone']).'" placeholder="0712345678" required></label><label>Choose payment method</label>
<div class="payment-grid">
    <div class="payment-option">
        <input type="radio" name="method" value="mpesa" id="m-mpesa" checked>
        <label for="m-mpesa">
            <img src="'.e(app_base_path()).'/assets/payments/mpesa.png" class="payment-logo" alt="M-Pesa">
            <span>Vodacom M-Pesa</span>
        </label>
    </div>
    <div class="payment-option">
        <input type="radio" name="method" value="tigopesa" id="m-tigo">
        <label for="m-tigo">
            <img src="'.e(app_base_path()).'/assets/payments/tigopesa.png" class="payment-logo" alt="Tigo Pesa">
            <span>Tigo Pesa / Mixx</span>
        </label>
    </div>
    <div class="payment-option">
        <input type="radio" name="method" value="airtelmoney" id="m-airtel">
        <label for="m-airtel">
            <img src="'.e(app_base_path()).'/assets/payments/airtelmoney.png" class="payment-logo" alt="Airtel Money">
            <span>Airtel Money</span>
        </label>
    </div>
    <div class="payment-option">
        <input type="radio" name="method" value="halopesa" id="m-halo">
        <label for="m-halo">
            <img src="'.e(app_base_path()).'/assets/payments/halopesa.png" class="payment-logo" alt="Halopesa">
            <span>Halopesa</span>
        </label>
    </div>
    <div class="payment-option" style="grid-column: 1 / -1">
        <input type="radio" name="method" value="card" id="m-card">
        <label for="m-card">
            <span>Credit / Debit Card</span>
            <small class="muted">Powered by ClickPesa</small>
        </label>
    </div>
</div><button class="btn gold" type="submit">Pay TZS 25,000</button></form></div></div>';
    break;
	case '/trips': $tripCards=''; try { ensure_trip_tables(); $tripRows=db()->query('SELECT id, title, description, destination, trip_date, meeting_point, poster_image FROM trips WHERE published = 1 ORDER BY trip_date IS NULL, trip_date ASC, created_at DESC')->fetchAll(); foreach($tripRows as $trip) { $ps=db()->prepare('SELECT id, name, description, price, capacity FROM trip_packages WHERE trip_id = ? ORDER BY price ASC'); $ps->execute([$trip['id']]); $packages=$ps->fetchAll(); $packageHtml=''; foreach($packages as $package) $packageHtml.='<article class="card"><h3>'.e($package['name']).'</h3><h2>TZS '.number_format((float)$package['price']).'</h2><p>'.e($package['description']).'</p><p class="muted">'.($package['capacity']?'Limited places: '.e((string)$package['capacity']):'Open places').'</p>'.($user?'<form class="form" method="post"><input type="hidden" name="action" value="trip_book"><input type="hidden" name="trip_id" value="'.(int)$trip['id'].'"><input type="hidden" name="package_id" value="'.(int)$package['id'].'"><label>Guests<input type="number" name="guests" min="1" value="1" required></label><label>Mobile number<input name="phone" placeholder="0712345678" required></label><label>Payment method<select name="method"><option value="mobile">Mobile Money</option><option value="card">Card checkout</option></select></label><button class="btn gold" type="submit">Book and pay</button></form>':'<a class="btn" href="/login">Log in to book</a>').'</article>'; $poster = trip_poster_markup($trip['poster_image'] ?? null, (string)$trip['title']); $tripCards.='<section class="card">'.$poster.'<p class="eyebrow">'.e($trip['destination']).'</p><h2>'.e($trip['title']).'</h2><p>'.e($trip['description']).'</p><p class="muted">'.($trip['trip_date']?'Date: '.e(date('M j, Y', strtotime($trip['trip_date']))).' · ':'').e($trip['meeting_point'] ?? '').'</p><div class="grid">'.$packageHtml.'</div></section>'; } } catch (Throwable $e) { $tripCards='<div class="card"><p>Trips are not available yet. Please check the database setup.</p></div>'; } $content='<div class="page"><p class="eyebrow">Travel together</p><h1>Community trips</h1><p class="lead">Choose a trip package, enter your phone number, and pay securely through ClickPesa.</p>'.$tripCards.'</div>'; break;
case '/blog': $content='<div class="page"><p class="eyebrow">Stories and updates</p><h1>Blog & videos</h1><p class="lead">Ideas, events, and stories from the Royal Family TZ community.</p><div class="card youtube-card"><div class="youtube-mark">YouTube</div><h3>Watch Royal Family TZ</h3><p class="muted">Follow our community stories, charity activities, youth talent, and events on the official Royal Family TZ media channel.</p><a class="btn gold" href="https://youtube.com/@royalfamilytz-media?si=BcIVW6FbDE4sa9A3" target="_blank" rel="noopener noreferrer">Visit our YouTube channel</a><a class="btn" href="https://www.instagram.com/royalfamilytz?igsh=czZoaXV2OTIwbjVy" target="_blank" rel="noopener noreferrer">Follow us on Instagram</a><a class="btn" href="https://www.tiktok.com/@royalfamilytz?_r=1&amp;_t=ZS-98wVB63J0Sq" target="_blank" rel="noopener noreferrer">Follow us on TikTok</a></div><div class="card"><h3>Welcome to Royal Family TZ</h3><p class="muted">Our latest stories are coming soon. Check back for community news, charity events, and youth talent features.</p></div></div>'; break;
case '/contact': $content='<div class="page"><p class="eyebrow">We would love to hear from you</p><h1>Contact us</h1><div class="form-card"><form class="form" method="post"><input type="hidden" name="action" value="contact"><div class="form-row"><label>Name<input name="name" required></label><label>Email<input type="email" name="email" required></label></div><label>Message<textarea name="message" rows="5" required></textarea></label><button class="btn" type="submit">Send message</button></form></div></div>'; break;
case '/donate':
    $content = '<div class="success-modal" id="donor-terms-modal"><div class="success-card terms-card"><h2>Donors & Sponsors Terms & Conditions</h2><div class="terms-body">'
        . '<p>By submitting this form, you agree to the following:</p>'
        . '<p>1. Financial donations are used to support Royal Family Foundation charity events, youth-talent, and community programs, and are non-refundable once processed.</p>'
        . '<p>2. If you offer equipment, materials, professional skills, or another in-kind contribution, our team will contact you by phone or email to arrange collection or delivery details.</p>'
        . '<p>3. Any partnership or sponsorship arrangement discussed through this form is not binding until confirmed in writing by Royal Family Foundation.</p>'
        . '<p>4. If you choose to be recognized as a supporter, your name or organization name may be published on our website or at community events, using the recognition name you provide.</p>'
        . '<p>5. The information you provide is accurate to the best of your knowledge, and will be used solely to process your contribution and for related communication.</p>'
        . '</div><label class="terms-agree"><input type="checkbox" id="donor-terms-agree">I have read and agree to the Donors & Sponsors Terms & Conditions.</label><button type="button" class="btn gold" id="donor-terms-continue" disabled style="width:100%">Continue</button></div></div>'
        . '<script>(function(){var agree=document.getElementById("donor-terms-agree"),cont=document.getElementById("donor-terms-continue"),modal=document.getElementById("donor-terms-modal");agree.addEventListener("change",function(){cont.disabled=!agree.checked;});cont.addEventListener("click",function(){modal.hidden=true;var field=document.getElementById("donor_agreed_terms_field");if(field)field.value="1";});})();</script>'
        . '<div class="page"><p class="eyebrow">"Your presence can change a life."</p><h1>Donor & Sponsorship Registration</h1><p class="lead">Support Royal Family Foundation with a financial gift, equipment, expertise, or a partnership — tell us how you would like to help.</p><div class="form-card"><form class="form" method="post"><input type="hidden" name="action" value="donor_signup"><input type="hidden" id="donor_agreed_terms_field" name="agreed_terms" value="0">'
        . '<h3>A. Donor / sponsor information</h3>'
        . '<label>Full name / organization name<input name="full_name" required></label>'
        . '<label style="font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:var(--muted)">Type of supporter</label><div class="amount-grid"><div class="amount-chip"><input type="radio" name="supporter_type" value="Individual" id="sup-individual" required><label for="sup-individual">Individual</label></div><div class="amount-chip"><input type="radio" name="supporter_type" value="Company" id="sup-company"><label for="sup-company">Company</label></div><div class="amount-chip"><input type="radio" name="supporter_type" value="NGO / Organization" id="sup-ngo"><label for="sup-ngo">NGO / Organization</label></div><div class="amount-chip"><input type="radio" name="supporter_type" value="Institution" id="sup-institution"><label for="sup-institution">Institution</label></div></div>'
        . '<div class="form-row"><label>Contact person (if organization)<input name="contact_person"></label><label>Phone number<input name="phone" placeholder="0712345678" required></label></div>'
        . '<div class="form-row"><label>WhatsApp number<input name="whatsapp" placeholder="0712345678"></label><label>Email address<input type="email" name="email" required></label></div>'
        . '<label>Location / address<input name="location_address" required></label>'
        . '<h3>B. Type of support</h3><p class="muted">How would you like to support Royal Family Foundation?</p>'
        . '<div class="amount-grid">'
        . '<div class="amount-chip"><input type="checkbox" name="support_type[]" value="Financial Donation" id="st-financial"><label for="st-financial">Financial Donation</label></div>'
        . '<div class="amount-chip"><input type="checkbox" name="support_type[]" value="Equipment / Materials" id="st-equipment"><label for="st-equipment">Equipment / Materials</label></div>'
        . '<div class="amount-chip"><input type="checkbox" name="support_type[]" value="Event Sponsorship" id="st-event"><label for="st-event">Event Sponsorship</label></div>'
        . '<div class="amount-chip"><input type="checkbox" name="support_type[]" value="Project Sponsorship" id="st-project"><label for="st-project">Project Sponsorship</label></div>'
        . '<div class="amount-chip"><input type="checkbox" name="support_type[]" value="Professional Skills / Expertise" id="st-skills"><label for="st-skills">Skills / Expertise</label></div>'
        . '<div class="amount-chip"><input type="checkbox" name="support_type[]" value="In-kind Donation" id="st-inkind"><label for="st-inkind">In-kind Donation</label></div>'
        . '<div class="amount-chip"><input type="checkbox" name="support_type[]" value="Partnership" id="st-partnership"><label for="st-partnership">Partnership</label></div>'
        . '</div><label>Other type of support<input name="support_type_other" placeholder="Optional"></label>'
        . '<h3>C. Area you would like to support</h3>'
        . '<div class="amount-grid">'
        . '<div class="amount-chip"><input type="checkbox" name="support_area[]" value="Youth Empowerment" id="sa-youth"><label for="sa-youth">Youth Empowerment</label></div>'
        . '<div class="amount-chip"><input type="checkbox" name="support_area[]" value="Community Outreach" id="sa-outreach"><label for="sa-outreach">Community Outreach</label></div>'
        . '<div class="amount-chip"><input type="checkbox" name="support_area[]" value="Leadership Development" id="sa-leadership"><label for="sa-leadership">Leadership Development</label></div>'
        . '<div class="amount-chip"><input type="checkbox" name="support_area[]" value="Education & Skills Dev" id="sa-education"><label for="sa-education">Education & Skills Dev</label></div>'
        . '<div class="amount-chip"><input type="checkbox" name="support_area[]" value="Digital Innovation" id="sa-digital"><label for="sa-digital">Digital Innovation</label></div>'
        . '<div class="amount-chip"><input type="checkbox" name="support_area[]" value="Charity & Humanitarian" id="sa-charity"><label for="sa-charity">Charity & Humanitarian</label></div>'
        . '<div class="amount-chip"><input type="checkbox" name="support_area[]" value="Talent & Creativity" id="sa-talent"><label for="sa-talent">Talent & Creativity</label></div>'
        . '<div class="amount-chip"><input type="checkbox" name="support_area[]" value="Women & Girls Support" id="sa-women"><label for="sa-women">Women & Girls Support</label></div>'
        . '</div><label>Other area<input name="support_area_other" placeholder="Optional"></label>'
        . '<h3>D. Support details</h3>'
        . '<div class="form-row"><label>Amount / value of support, TZS (if applicable)<input type="number" name="amount" min="0" placeholder="e.g. 50000"></label><label>Preferred project / campaign to support<input name="preferred_project" placeholder="Optional"></label></div>'
        . '<label style="font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:var(--muted)">Would you like your support to be:</label><div class="amount-grid"><div class="amount-chip"><input type="radio" name="frequency" value="One-time" id="freq-one" checked><label for="freq-one">One-time</label></div><div class="amount-chip"><input type="radio" name="frequency" value="Monthly" id="freq-monthly"><label for="freq-monthly">Monthly</label></div><div class="amount-chip"><input type="radio" name="frequency" value="Quarterly" id="freq-quarterly"><label for="freq-quarterly">Quarterly</label></div><div class="amount-chip"><input type="radio" name="frequency" value="Annual" id="freq-annual"><label for="freq-annual">Annual</label></div><div class="amount-chip"><input type="radio" name="frequency" value="Project-based" id="freq-project"><label for="freq-project">Project-based</label></div></div>'
        . '<h3>E. Partnership & sponsorship</h3>'
        . '<label style="font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:var(--muted)">Are you interested in a long-term partnership with RFF?</label><div class="amount-grid"><div class="amount-chip"><input type="radio" name="wants_partnership" value="Yes" id="pp-yes"><label for="pp-yes">Yes</label></div><div class="amount-chip"><input type="radio" name="wants_partnership" value="No" id="pp-no"><label for="pp-no">No</label></div><div class="amount-chip"><input type="radio" name="wants_partnership" value="Maybe" id="pp-maybe"><label for="pp-maybe">Maybe</label></div></div>'
        . '<div class="form-row"><label>If yes, what type of partnership?<input name="partnership_type" placeholder="Optional"></label><label>What would you expect from the partnership?<input name="partnership_expectation" placeholder="Optional"></label></div>'
        . '<h3>F. Recognition</h3>'
        . '<label style="font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;color:var(--muted)">Would you like your name/organization to be recognized as a supporter?</label><div class="amount-grid"><div class="amount-chip"><input type="radio" name="wants_recognition" value="Yes" id="rec-yes"><label for="rec-yes">Yes</label></div><div class="amount-chip"><input type="radio" name="wants_recognition" value="No" id="rec-no"><label for="rec-no">No</label></div></div>'
        . '<label>Preferred recognition name<input name="recognition_name" placeholder="Optional"></label>'
        . '<h3>G. Additional information</h3>'
        . '<label>Please share any message, idea, or special request<textarea name="message" rows="3"></textarea></label>'
        . '<h3>H. Declaration</h3>'
        . '<label style="display:flex;align-items:flex-start;gap:10px;font-weight:400"><input type="checkbox" name="agreed" value="1" required style="width:auto;margin-top:4px">I confirm that the information provided in this form is accurate and that my support is intended to contribute to the objectives and community initiatives of Royal Family Foundation.</label>'
        . '<button class="btn gold" type="submit">Submit</button></form></div><p class="center muted" style="margin-top:20px">"Your presence can change a life."</p></div>';
    break;
case '/support/pay':
    ensure_donor_sponsorships_table();
    $donorId = (int)($_GET['id'] ?? 0);
    $donorToken = (string)($_GET['token'] ?? '');
    $donorStmt = db()->prepare('SELECT * FROM donor_sponsorships WHERE id = ? AND access_token = ? LIMIT 1');
    $donorStmt->execute([$donorId, $donorToken]);
    $donorRow = $donorStmt->fetch();
    if (!$donorRow) { $content = '<div class="page"><p class="eyebrow">Royal Family Foundation</p><h1>Submission not found.</h1><p class="lead">Please submit the support form again.</p><a class="btn" href="/donate">Back to form</a></div>'; break; }
    if ($donorRow['status'] === 'paid') { $content = '<div class="page"><p class="eyebrow">Royal Family Foundation</p><h1>Thank you!</h1><p class="lead">Your contribution of TZS '.number_format((float)$donorRow['amount']).' is confirmed. May God bless you for your generosity.</p></div>'; break; }
    $content = '<div class="page"><p class="eyebrow">Royal Family Foundation</p><h1>Confirm your payment</h1><div class="form-card"><div class="plan-summary"><h2>'.e($donorRow['full_name']).'</h2><p class="muted">Supporting: '.e($donorRow['preferred_project'] ?: $donorRow['support_areas']).'</p><p class="muted">Amount: <strong>TZS '.number_format((float)$donorRow['amount']).'</strong> ('.e($donorRow['frequency'] ?: 'One-time').')</p></div><form class="form" method="post"><input type="hidden" name="action" value="pay_donor_support"><input type="hidden" name="id" value="'.(int)$donorRow['id'].'"><input type="hidden" name="token" value="'.e($donorToken).'"><label>Mobile number<input name="phone" value="'.e($donorRow['phone']).'" placeholder="0712345678" required></label><label>Choose payment method</label>
<div class="payment-grid">
    <div class="payment-option">
        <input type="radio" name="method" value="mpesa" id="m-mpesa" checked>
        <label for="m-mpesa">
            <img src="'.e(app_base_path()).'/assets/payments/mpesa.png" class="payment-logo" alt="M-Pesa">
            <span>Vodacom M-Pesa</span>
        </label>
    </div>
    <div class="payment-option">
        <input type="radio" name="method" value="tigopesa" id="m-tigo">
        <label for="m-tigo">
            <img src="'.e(app_base_path()).'/assets/payments/tigopesa.png" class="payment-logo" alt="Tigo Pesa">
            <span>Tigo Pesa / Mixx</span>
        </label>
    </div>
    <div class="payment-option">
        <input type="radio" name="method" value="airtelmoney" id="m-airtel">
        <label for="m-airtel">
            <img src="'.e(app_base_path()).'/assets/payments/airtelmoney.png" class="payment-logo" alt="Airtel Money">
            <span>Airtel Money</span>
        </label>
    </div>
    <div class="payment-option">
        <input type="radio" name="method" value="halopesa" id="m-halo">
        <label for="m-halo">
            <img src="'.e(app_base_path()).'/assets/payments/halopesa.png" class="payment-logo" alt="Halopesa">
            <span>Halopesa</span>
        </label>
    </div>
    <div class="payment-option" style="grid-column: 1 / -1">
        <input type="radio" name="method" value="card" id="m-card">
        <label for="m-card">
            <span>Credit / Debit Card</span>
            <small class="muted">Powered by ClickPesa</small>
        </label>
    </div>
</div><button class="btn gold" type="submit">Pay TZS '.number_format((float)$donorRow['amount']).'</button></form></div></div>';
    break;
case '/login': $content='<div class="page"><div class="form-card"><p class="eyebrow">Welcome back</p><h2>Log in</h2><a class="google-btn" href="'.e(app_base_path()).'/auth/google/start"><span class="google-icon" aria-hidden="true">G</span> Continue with Google</a><div class="or">or use email</div><form class="form" method="post"><input type="hidden" name="action" value="login"><label>Email<input type="email" name="email" required></label><label>Password<input type="password" name="password" required></label><button class="btn" type="submit">Log in</button></form><p>New here? <a href="/signup"><u>Sign up</u></a></p></div></div>'; break;
case '/signup': $content='<div class="page"><div class="form-card"><p class="eyebrow">Start your journey</p><h2>Create your account</h2><a class="google-btn" href="'.e(app_base_path()).'/auth/google/start"><span class="google-icon" aria-hidden="true">G</span> Sign up with Google</a><div class="or">or create an account with email</div><form class="form" method="post" enctype="application/x-www-form-urlencoded"><input type="hidden" name="action" value="signup"><label>Full name<input name="name" required></label><label>Email<input type="email" name="email" required></label><label>Password<input type="password" name="password" minlength="6" required></label><button class="btn" type="submit">Sign up</button></form></div></div>'; break;
case '/dashboard': $dashAvatar = profile_src($user); $dashPhoto = $dashAvatar ? '<img class="dashboard-avatar" src="'.e($dashAvatar).'" alt="Profile photo of '.e($user['name']).'">' : '<div class="dashboard-avatar dashboard-initial">'.e(strtoupper(substr($user['name'],0,1))).'</div>'; $eventCards=''; foreach($events as $event) $eventCards.='<article class="card"><p class="eyebrow">Upcoming event</p><h3>'.e($event['title']).'</h3><p>'.e($event['description']).'</p><p class="muted">'.e($event['location'] ?? '').($event['event_date']?' · '.e(date('M j, Y g:i A', strtotime($event['event_date']))):'').'</p></article>'; $noticeCards=''; foreach($notifications as $notice) $noticeCards.='<article class="card"><p class="eyebrow">Notification</p><h3>'.e($notice['title']).'</h3><p>'.e($notice['message']).'</p><p class="muted">'.e(date('M j, Y', strtotime($notice['created_at']))).'</p></article>'; $dashBadge = badge_display($user['membership_badge'] ?? null); $membershipStat = $user['membership'] ? '<span class="badge-chip badge-'.e($user['membership_badge'] ?? '').'">'.$dashBadge['icon'].' '.e($dashBadge['label']).'</span>'.($user['membership_id']?'<br><small class="muted">'.e($user['membership_id']).'</small>':'') : '—'; $content='<div class="page"><div class="dashboard-welcome">'.$dashPhoto.'<div><p class="eyebrow">Member space</p><h1>Welcome, '.e($user['name']).'</h1><p class="muted">Your profile photo appears here after you add it from your profile page.</p></div></div><div class="stats"><div class="stat"><strong>'.$membershipStat.'</strong><span>Membership status</span></div><div class="stat"><strong>'.count($events).'</strong><span>Upcoming events</span></div><div class="stat"><strong>'.count($notifications).'</strong><span>Notifications</span></div></div><section class="section"><p class="eyebrow">Stay connected</p><h2>Events and updates</h2><div class="grid">'.($eventCards ?: '<article class="card"><p class="muted">No upcoming events yet.</p></article>').($noticeCards ?: '<article class="card"><p class="muted">No new notifications.</p></article>').'</div></section><div class="grid"><article class="card"><h3>Your profile</h3><p>'.e($user['email']).'</p><a class="btn" href="/profile">Edit profile</a></article><article class="card"><h3>Grow with us</h3><p>Activate your membership and join the next community experience.</p><a class="btn gold" href="/members">Choose a plan</a></article></div></div>'; break;
case '/profile':
    try {
        ensure_membership_badge_column();
        $freshStmt = db()->prepare('SELECT membership_active, membership_id, membership_badge FROM users WHERE id = ? LIMIT 1');
        $freshStmt->execute([$user['id']]);
        $fresh = $freshStmt->fetch();
        if ($fresh) { $user['membership'] = (bool)$fresh['membership_active']; $user['membership_id'] = $fresh['membership_id']; $user['membership_badge'] = $fresh['membership_badge']; $_SESSION['user'] = $user; }
    } catch (Throwable $e) {}
    $avatar = profile_src($user);
    $cardBadge = badge_display($user['membership_badge'] ?? null);
    $membershipCard = !empty($user['membership']) ? '<div class="membership-card"><div class="membership-card-top"><span class="badge-chip '.e('badge-' . ($user['membership_badge'] ?? '')).'">'.$cardBadge['icon'].' '.e($cardBadge['label']).'</span></div><div class="membership-card-body">'.($avatar ? '<img class="membership-card-photo" src="'.e($avatar).'" alt="Profile photo">' : '<div class="membership-card-photo membership-card-initial">'.e(strtoupper(substr($user['name'],0,1))).'</div>').'<div><h3>'.e($user['name']).'</h3><p>Royal Family TZ Member</p><p class="membership-card-id">'.e($user['membership_id'] ?: 'ID pending confirmation').'</p></div></div></div>' : '<div class="card"><p class="muted">You are not an active member yet.</p><a class="btn gold" href="/members">Become a member</a></div>';
    $ticketCards = '';
    try {
        ensure_ticket_column();
        $tStmt = db()->prepare("SELECT b.id, b.guests, t.title, t.destination, t.trip_date, tx.order_reference FROM trip_bookings b JOIN trips t ON t.id = b.trip_id LEFT JOIN transactions tx ON tx.id = b.transaction_id WHERE b.user_id = ? AND b.status = 'paid' ORDER BY t.trip_date DESC");
        $tStmt->execute([$user['id']]);
        foreach ($tStmt->fetchAll() as $ticket) {
            $qrData = 'RFTZ|' . ($user['membership_id'] ?: ('USER-' . $user['id'])) . '|' . $ticket['order_reference'];
            $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=' . rawurlencode($qrData);
            $ticketCards .= '<article class="ticket-card"><div class="ticket-info"><p class="eyebrow">'.e($ticket['destination']).'</p><h3>'.e($ticket['title']).'</h3><p class="muted">'.($ticket['trip_date'] ? e(date('M j, Y', strtotime($ticket['trip_date']))) : 'Date to be confirmed').' · '.e((string)$ticket['guests']).' guest(s)</p><p class="muted">Ref: '.e((string)$ticket['order_reference']).'</p><button class="btn" type="button" onclick="window.print()">Print / save as PDF</button></div><img class="ticket-qr" src="'.e($qrUrl).'" alt="Ticket QR code"></article>';
        }
    } catch (Throwable $e) {}
    $ticketsSection = $ticketCards ? '<section class="section"><p class="eyebrow">Your tickets</p><h2>Digital tickets</h2><div class="ticket-grid">'.$ticketCards.'</div></section>' : '';
    $content='<div class="page"><section class="section" style="margin-top:0"><p class="eyebrow">Member details</p><h2>Your membership card</h2>'.$membershipCard.'</section>'.$ticketsSection.'<div class="form-card"><p class="eyebrow">Member details</p><h2>Your profile</h2><div class="avatar-picker">'.($avatar?'<img class="profile-avatar" src="'.e($avatar).'" alt="Profile photo">':'<div class="profile-placeholder">'.e(strtoupper(substr($user['name'],0,1))).'</div>').'<label class="camera-button" for="profile_image" title="Add or change profile photo" aria-label="Add or change profile photo">&#128247;</label></div><p class="photo-hint">Tap the camera icon to add or change your photo.</p><form class="form" method="post" enctype="multipart/form-data"><input type="hidden" name="action" value="profile_update"><label>Display name<input name="name" value="'.e($user['name']).'" required></label><label>Email<input value="'.e($user['email']).'" disabled></label><label>Bio<textarea name="bio" rows="4" placeholder="Tell the community about yourself"></textarea></label><input id="profile_image" class="visually-hidden" type="file" name="profile_image" accept="image/jpeg,image/png,image/webp" onchange="this.form.submit()"><button class="btn" type="submit">Save profile</button></form></div></div>'; break;
case '/subscribe':
    if (!$user) { header('Location: /login'); exit; }
    $selectedTier = tier_by_key($tiers, (string)($_GET['tier'] ?? ''));
    $selectedCycle = ($_GET['cycle'] ?? 'monthly') === 'yearly' ? 'yearly' : 'monthly';
    if (!$selectedTier) {
        $content = '<div class="page"><p class="eyebrow">Membership</p><h1>Choose a plan first.</h1><p class="lead">Pick a membership level on the Members page, then come back here to pay.</p><a class="btn gold" href="/members">View membership options</a></div>';
        break;
    }
    $planAmount = (int)$selectedTier[$selectedCycle];
    $cycleLabel = $selectedCycle === 'yearly' ? 'year' : 'month';
    $content = '<div class="page"><p class="eyebrow">Membership</p><h1>Confirm your plan.</h1><div class="form-card"><div class="plan-summary"><span class="badge-chip '.e($selectedTier['badge_class']).'">'.$selectedTier['icon'].' '.e($selectedTier['badge']).'</span><h2>'.e($selectedTier['name']).'</h2><p class="muted">TZS '.number_format($planAmount).' / '.$cycleLabel.' — '.e($selectedTier['text']).'</p><a class="muted" href="/members">Change plan</a></div><form class="form" method="post"><input type="hidden" name="action" value="subscribe"><input type="hidden" name="tier_key" value="'.e($selectedTier['key']).'"><input type="hidden" name="cycle" value="'.e($selectedCycle).'"><label>Mobile number<input name="phone" placeholder="0712345678" required></label><label>Choose payment method</label>
<div class="payment-grid">
    <div class="payment-option">
        <input type="radio" name="method" value="mpesa" id="m-mpesa" checked>
        <label for="m-mpesa">
            <img src="'.e(app_base_path()).'/assets/payments/mpesa.png" class="payment-logo" alt="M-Pesa">
            <span>Vodacom M-Pesa</span>
        </label>
    </div>
    <div class="payment-option">
        <input type="radio" name="method" value="tigopesa" id="m-tigo">
        <label for="m-tigo">
            <img src="'.e(app_base_path()).'/assets/payments/tigopesa.png" class="payment-logo" alt="Tigo Pesa">
            <span>Tigo Pesa / Mixx</span>
        </label>
    </div>
    <div class="payment-option">
        <input type="radio" name="method" value="airtelmoney" id="m-airtel">
        <label for="m-airtel">
            <img src="'.e(app_base_path()).'/assets/payments/airtelmoney.png" class="payment-logo" alt="Airtel Money">
            <span>Airtel Money</span>
        </label>
    </div>
    <div class="payment-option">
        <input type="radio" name="method" value="halopesa" id="m-halo">
        <label for="m-halo">
            <img src="'.e(app_base_path()).'/assets/payments/halopesa.png" class="payment-logo" alt="Halopesa">
            <span>Halopesa</span>
        </label>
    </div>
    <div class="payment-option" style="grid-column: 1 / -1">
        <input type="radio" name="method" value="card" id="m-card">
        <label for="m-card">
            <span>Credit / Debit Card</span>
            <small class="muted">Powered by ClickPesa</small>
        </label>
    </div>
</div><button class="btn gold" type="submit">Pay TZS '.number_format($planAmount).' and join</button></form></div></div>';
    break;
case '/admin':
    $active = $revenue = $posts = 0; $clickReady = cp_config('CLICKPESA_CLIENT_ID') !== '' && cp_config('CLICKPESA_API_KEY') !== '';
    $tripAdmin = ''; try { ensure_community_tables(); ensure_trip_tables(); $active = (int)db()->query('SELECT COUNT(*) FROM users WHERE membership_active = 1')->fetchColumn(); $revenue = (float)db()->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE status = 'paid'")->fetchColumn(); $posts = (int)db()->query('SELECT COUNT(*) FROM blog_posts')->fetchColumn(); foreach (db()->query('SELECT id, title, destination, trip_date FROM trips ORDER BY created_at DESC') as $adminTrip) $tripAdmin .= '<div class="trip-admin-row"><span><strong>'.e($adminTrip['title']).'</strong><small>'.e($adminTrip['destination']).($adminTrip['trip_date']?' · '.e($adminTrip['trip_date']):'').'</small></span><span><a class="btn" href="/admin/trip-edit?id='.(int)$adminTrip['id'].'">Edit</a><form method="post" style="display:inline" onsubmit="return confirm(\'Delete this trip and its packages?\')"><input type="hidden" name="action" value="trip_delete"><input type="hidden" name="trip_id" value="'.(int)$adminTrip['id'].'"><button class="btn danger" type="submit">Delete</button></form></span></div>'; } catch (Throwable $e) {}
    $notificationAdmin = ''; try { ensure_community_tables(); foreach (db()->query('SELECT id, title, message, audience, created_at FROM notifications ORDER BY created_at DESC') as $notice) { $notificationAdmin .= '<div class="card" style="margin-top:1rem"><form class="form" method="post"><input type="hidden" name="action" value="notification_update"><input type="hidden" name="notification_id" value="'.(int)$notice['id'].'"><label>Title<input name="title" value="'.e($notice['title']).'" required></label><label>Message<textarea name="message" rows="3" required>'.e($notice['message']).'</textarea></label><label>Audience<select name="audience"><option value="all"'.($notice['audience']==='all'?' selected':'').'>Everyone</option><option value="members"'.($notice['audience']==='members'?' selected':'').'>Members</option><option value="admins"'.($notice['audience']==='admins'?' selected':'').'>Admins only</option></select></label><button class="btn gold" type="submit">Save changes</button></form><form method="post" style="margin-top:.5rem" onsubmit="return confirm(\'Delete this notification?\')"><input type="hidden" name="action" value="notification_delete"><input type="hidden" name="notification_id" value="'.(int)$notice['id'].'"><button class="btn danger" type="submit">Delete notification</button></form></div>'; } } catch (Throwable $e) {}
    $contactAdmin = ''; try { ensure_contact_columns(); foreach (db()->query('SELECT id, name, email, message, status, created_at FROM contact_messages ORDER BY created_at DESC') as $contact) { $contactAdmin .= '<div class="card" style="margin-top:1rem"><p><strong>'.e($contact['name']).'</strong> · <a href="mailto:'.e($contact['email']).'">'.e($contact['email']).'</a><br><small>'.e($contact['created_at']).' · '.e($contact['status']).'</small></p><p>'.nl2br(e($contact['message'])).'</p><form class="form" method="post"><input type="hidden" name="action" value="contact_reply"><input type="hidden" name="contact_id" value="'.(int)$contact['id'].'"><label>Reply to this person<textarea name="reply" rows="3" placeholder="Write your reply..." required></textarea></label><button class="btn gold" type="submit">Send reply by email</button></form></div>'; } } catch (Throwable $e) { $contactAdmin = '<p class="muted">Contact inbox unavailable. Import the contact migration or check the database.</p>'; }
    $content='<div class="page"><p class="eyebrow">Admin</p><h1>Dashboard</h1><p class="lead">Manage members, payments, stories, events, and notifications from one place.</p><div class="stats"><div class="stat"><strong>'.$active.'</strong><span>Active members</span></div><div class="stat"><strong>TZS '.number_format($revenue).'</strong><span>Paid revenue</span></div><div class="stat"><strong>'.$posts.'</strong><span>Blog posts</span></div></div><details class="admin-action"><summary class="btn">Publish an event</summary><div class="card"><h3>Publish an event</h3><form class="form" method="post"><input type="hidden" name="action" value="event_create"><label>Event title<input name="title" required></label><label>Description<textarea name="description" rows="3" required></textarea></label><label>Date and time<input type="datetime-local" name="event_date"></label><label>Location<input name="location"></label><button class="btn" type="submit">Publish event</button></form></div></details><details class="admin-action"><summary class="btn gold">Send a notification</summary><div class="card"><h3>Send a notification</h3><form class="form" method="post"><input type="hidden" name="action" value="notification_create"><label>Notification title<input name="title" required></label><label>Message<textarea name="message" rows="3" required></textarea></label><label>Audience<select name="audience"><option value="all">Everyone</option><option value="members">Members</option><option value="admins">Admins only</option></select></label><button class="btn gold" type="submit">Send notification</button></form></div></details><details class="admin-action"><summary class="btn">Create a trip</summary><div class="card"><h3>Create a trip</h3><form class="form" method="post" enctype="multipart/form-data"><input type="hidden" name="action" value="trip_create"><label>Trip poster or image<input type="file" name="poster_image" accept="image/jpeg,image/png,image/webp,application/pdf"><small class="muted">Upload a JPG, PNG, WEBP, or PDF poster up to 8 MB.</small></label><label>Trip title<input name="title" placeholder="Chemka Trip" required></label><label>Description<textarea name="description" rows="3" required></textarea></label><label>Destination<input name="destination" placeholder="Chemka Hot Springs" required></label><label>Trip date<input type="date" name="trip_date"></label><label>Meeting point<input name="meeting_point"></label><h3>Packages</h3>'.implode('', array_map(fn($i) => '<div class="card"><label>Package name<input name="package_name['.$i.']" placeholder="Day Pass"></label><label>Description<textarea name="package_description['.$i.']" rows="2"></textarea></label><label>Price (TZS)<input type="number" name="package_price['.$i.']" min="1"></label><label>Capacity<input type="number" name="package_capacity['.$i.']" min="1"></label></div>', [0,1,2])).'<button class="btn" type="submit">Publish trip</button></form><h3>Existing trips</h3>'.($tripAdmin ?: '<p class="muted">No trips yet.</p>').'</div><details class="admin-action"><summary class="btn">Contact inbox</summary><div class="card"><h3>Messages from Contact Us</h3><p class="muted">New website messages also alert the configured Gmail account. Reply from here to email the sender directly.</p>'. $contactAdmin . '</div></details><details class="admin-action"><summary class="btn gold">Manage notifications</summary><div class="card"><h3>Edit or delete notifications</h3>'. $notificationAdmin . '</div></details><div class="card"><h3>Payment integration</h3><p>'.($clickReady?'ClickPesa credentials are configured.':'ClickPesa credentials are not configured yet. Add them to the Apache/PHP environment before accepting payments.').'</p><p class="muted">Webhook URL: <code>/api/clickpesa/webhook</code></p></div><div class="grid"><article class="card"><h3>Members</h3><p>Review member status and profiles.</p><a class="btn" href="/admin/users">Open members</a></article><article class="card"><h3>Payments</h3><p>Review pending and completed transactions.</p><a class="btn" href="/admin/payments">Open payments</a></article><article class="card"><h3>Reports</h3><p>View users, membership IDs, transaction totals, and who paid.</p><a class="btn gold" href="/admin/reports">Open reports</a></article><article class="card"><h3>Trips</h3><p>Create Chemka trips and package bookings from the admin panel.</p><a class="btn" href="/trips">View trips</a></article><article class="card"><h3>Blog & images</h3><p>Publish stories and update the site imagery.</p><a class="btn" href="/admin/blog">Manage content</a></article></div></div>'; break;
case '/admin/trip-edit':
    $tripId = (int)($_GET['id'] ?? 0); $trip = null; $editPackages = []; try { ensure_trip_tables(); $stmt = db()->prepare('SELECT id,title,description,destination,trip_date,meeting_point,poster_image FROM trips WHERE id=?'); $stmt->execute([$tripId]); $trip = $stmt->fetch(); if ($trip) { $ps = db()->prepare('SELECT id,name,description,price,capacity FROM trip_packages WHERE trip_id=? ORDER BY price ASC'); $ps->execute([$tripId]); $editPackages = $ps->fetchAll(); } } catch (Throwable $e) {} if (!$trip) { $content='<div class="page"><div class="card"><h2>Trip not found</h2><p class="lead">This trip may have been deleted.</p><a class="btn" href="/admin">Back to admin</a></div></div>'; break; } $editPkgFields=''; foreach (array_pad($editPackages, 3, null) as $pkg) { $editPkgFields .= '<div class="card"><input type="hidden" name="package_id[]" value="'.(int)($pkg['id'] ?? 0).'"> <label>Package name<input name="package_name[]" value="'.e((string)($pkg['name'] ?? '')).'"></label><label>Description<textarea name="package_description[]" rows="2">'.e((string)($pkg['description'] ?? '')).'</textarea></label><label>Price (TZS)<input type="number" name="package_price[]" min="1" value="'.e((string)($pkg['price'] ?? '')).'"></label><label>Capacity<input type="number" name="package_capacity[]" min="1" value="'.e((string)($pkg['capacity'] ?? '')).'"></label></div>'; } $currentPoster = trip_poster_markup($trip['poster_image'] ?? null, (string)$trip['title']); $content='<div class="page"><p class="eyebrow">Admin · Trips</p><h1>Edit trip</h1><p class="lead">Update the trip details or correct package prices without deleting the trip.</p><div class="card">'.$currentPoster.'<form class="form" method="post" enctype="multipart/form-data"><input type="hidden" name="action" value="trip_update"><input type="hidden" name="trip_id" value="'.(int)$trip['id'].'"><label>Replace poster/image<input type="file" name="poster_image" accept="image/jpeg,image/png,image/webp,application/pdf"><small class="muted">Leave empty to keep the current poster.</small></label><label>Trip title<input name="title" value="'.e($trip['title']).'" required></label><label>Description<textarea name="description" rows="5" required>'.e($trip['description']).'</textarea></label><label>Destination<input name="destination" value="'.e($trip['destination']).'" required></label><label>Trip date<input type="date" name="trip_date" value="'.e((string)($trip['trip_date'] ?? '')).'"></label><label>Meeting point<input name="meeting_point" value="'.e((string)($trip['meeting_point'] ?? '')).'" ></label><h2>Packages</h2>'.$editPkgFields.'<button class="btn gold" type="submit">Save changes</button><a class="btn" href="/admin">Cancel</a></form></div></div>'; break;
case '/admin/reports/print':
    $userCount = $memberCount = 0; $paidTotal = $pendingTotal = 0.0; $members = []; $transactions = [];
    try { $userCount = (int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn(); $memberCount = (int)db()->query("SELECT COUNT(*) FROM users WHERE membership_active = 1")->fetchColumn(); $paidTotal = (float)db()->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE status = 'paid'")->fetchColumn(); $pendingTotal = (float)db()->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE status = 'pending'")->fetchColumn(); $members = db()->query('SELECT name,email,role,membership_id,membership_active,created_at FROM users ORDER BY created_at DESC')->fetchAll(); $transactions = db()->query("SELECT t.order_reference, COALESCE(u.name,'Guest') user_name, COALESCE(u.email,'') email, t.type, t.tier, t.amount, t.currency, t.status, t.created_at FROM transactions t LEFT JOIN users u ON u.id=t.user_id ORDER BY t.created_at DESC")->fetchAll(); } catch (Throwable $e) {}
    $memberHtml = ''; foreach ($members as $m) $memberHtml .= '<tr><td>'.e($m['name']).'</td><td>'.e($m['email']).'</td><td>'.e($m['role']).'</td><td>'.e($m['membership_id'] ?? '—').'</td><td>'.($m['membership_active']?'Active':'Inactive').'</td></tr>';
    $txHtml = ''; foreach ($transactions as $t) $txHtml .= '<tr><td>'.e($t['order_reference']).'</td><td>'.e($t['user_name']).'<br><small>'.e($t['email']).'</small></td><td>'.e($t['type']).'</td><td>'.e($t['tier'] ?? '—').'</td><td>'.e($t['currency'].' '.number_format((float)$t['amount'],2)).'</td><td>'.e($t['status']).'</td><td>'.e($t['created_at']).'</td></tr>';
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Royal Family TZ Report</title><style>@page{size:A4;margin:14mm}*{box-sizing:border-box}body{font-family:Arial,sans-serif;color:#17233c;margin:0;background:#fff}.toolbar{padding:14px;background:#17233c;text-align:right}.toolbar button{background:#c89b3c;color:#fff;border:0;padding:10px 18px;border-radius:5px;font-weight:bold;cursor:pointer}.report{max-width:1100px;margin:0 auto}.header{display:flex;align-items:center;gap:24px;padding:18px 0;border-bottom:4px solid #c89b3c}.header img{width:150px;height:120px;object-fit:contain;padding:6px;background:#fff;border-radius:10px}.header h1{margin:0;font-size:25px}.header p{margin:4px 0;color:#68738a}.summary{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin:18px 0}.metric{border:1px solid #ddd;border-top:4px solid #c89b3c;padding:12px;border-radius:5px}.metric strong{display:block;font-size:20px;color:#17233c}.metric span{font-size:11px;color:#68738a}.section{margin:20px 0}.section h2{font-size:16px;color:#17233c;border-bottom:2px solid #c89b3c;padding-bottom:6px}table{width:100%;border-collapse:collapse;font-size:10px}th{background:#17233c;color:#fff;text-align:left;padding:7px}td{border-bottom:1px solid #ddd;padding:7px;vertical-align:top}tr:nth-child(even){background:#f7f4ed}small{color:#68738a}.footer{margin-top:25px;padding-top:10px;border-top:1px solid #ddd;font-size:10px;color:#68738a}@media print{.toolbar{display:none}.report{max-width:none}.section{break-inside:avoid}table{break-inside:auto}tr{break-inside:avoid;break-after:auto}}</style></head><body><div class="toolbar"><button onclick="window.print()">Export / Save as PDF</button></div><div class="report"><div class="header"><img src="'.e(logo_url()).'" alt="Royal Family TZ logo"><div><h1>Royal Family TZ</h1><p>Members and transactions report</p><p>Generated '.e(date('d M Y, H:i')).'</p></div></div><div class="summary"><div class="metric"><strong>'.number_format($userCount).'</strong><span>Total users</span></div><div class="metric"><strong>'.number_format($memberCount).'</strong><span>Active memberships</span></div><div class="metric"><strong>TZS '.number_format($paidTotal,2).'</strong><span>Paid total</span></div><div class="metric"><strong>TZS '.number_format($pendingTotal,2).'</strong><span>Pending total</span></div></div><div class="section"><h2>Users and membership IDs</h2><table><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Membership ID</th><th>Status</th></tr></thead><tbody>'.$memberHtml.'</tbody></table></div><div class="section"><h2>Transactions and payers</h2><table><thead><tr><th>Reference</th><th>User</th><th>Type</th><th>Package/Tier</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead><tbody>'.$txHtml.'</tbody></table></div><div class="footer">Royal Family TZ · Community, youth talent, and practical impact in Tanzania.</div></div></body></html>'; exit;
case '/admin/reports':
    $userCount = $memberCount = $paidTotal = $pendingTotal = 0; $transactionRows = ''; $memberRows = ''; try { $userCount = (int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn(); $memberCount = (int)db()->query("SELECT COUNT(*) FROM users WHERE membership_active = 1")->fetchColumn(); $paidTotal = (float)db()->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE status = 'paid'")->fetchColumn(); $pendingTotal = (float)db()->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE status = 'pending'")->fetchColumn(); foreach (db()->query('SELECT name, email, role, membership_id, membership_active, created_at FROM users ORDER BY created_at DESC') as $member) $memberRows .= '<tr><td>'.e($member['name']).'</td><td>'.e($member['email']).'</td><td>'.e($member['role']).'</td><td>'.e($member['membership_id'] ?? '—').'</td><td>'.($member['membership_active']?'Active':'Inactive').'</td></tr>'; foreach (db()->query('SELECT t.order_reference, COALESCE(u.name, \'Guest\') AS user_name, COALESCE(u.email, \'\') AS email, t.type, t.tier, t.amount, t.currency, t.status, t.created_at FROM transactions t LEFT JOIN users u ON u.id=t.user_id ORDER BY t.created_at DESC') as $tx) $transactionRows .= '<tr><td>'.e($tx['order_reference']).'</td><td>'.e($tx['user_name']).'<br><small>'.e($tx['email']).'</small></td><td>'.e($tx['type']).'</td><td>'.e($tx['tier'] ?? '—').'</td><td>'.e($tx['currency'].' '.number_format((float)$tx['amount'],2)).'</td><td>'.e($tx['status']).'</td><td>'.e($tx['created_at']).'</td></tr>'; } catch (Throwable $e) { $memberRows='<tr><td colspan="5">Database unavailable.</td></tr>'; $transactionRows='<tr><td colspan="7">Database unavailable.</td></tr>'; } $content='<div class="page"><p class="eyebrow">Admin reporting</p><h1>Reports</h1><div class="stats"><div class="stat"><strong>'.$userCount.'</strong><span>Total users</span></div><div class="stat"><strong>'.$memberCount.'</strong><span>Active memberships</span></div><div class="stat"><strong>TZS '.number_format($paidTotal,2).'</strong><span>Paid transactions</span></div><div class="stat"><strong>TZS '.number_format($pendingTotal,2).'</strong><span>Pending transactions</span></div></div><div class="card"><h2>Downloads</h2><a class="btn gold" href="/admin/reports/print" target="_blank">Export branded PDF</a><a class="btn" href="/admin/report-users.csv">Download users CSV</a><a class="btn gold" href="/admin/report-transactions.csv">Download transactions CSV</a></div><div class="card"><h2>Users and membership IDs</h2><div style="overflow:auto"><table><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Membership ID</th><th>Status</th></tr></thead><tbody>'.$memberRows.'</tbody></table></div></div><div class="card"><h2>Who transacted</h2><div style="overflow:auto"><table><thead><tr><th>Reference</th><th>User</th><th>Type</th><th>Package/Tier</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead><tbody>'.$transactionRows.'</tbody></table></div></div></div>'; break;
	case '/admin/users':
    $rows = ''; try { foreach (db()->query('SELECT name, email, role, membership_active, membership_id, created_at FROM users ORDER BY created_at DESC') as $u) $rows .= '<tr><td>'.e($u['name']).'</td><td>'.e($u['email']).'</td><td>'.e($u['role']).'</td><td>'.($u['membership_active']?'Active':'Inactive').'</td><td>'.e($u['membership_id'] ?? '—').'</td></tr>'; } catch (Throwable $e) { $rows = '<tr><td colspan="5">Database unavailable.</td></tr>'; } $content='<div class="page"><p class="eyebrow">Admin workspace</p><h1>Members</h1><div class="card"><div style="overflow:auto"><table><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Membership ID</th></tr></thead><tbody>'.$rows.'</tbody></table></div></div></div>'; break;
case '/admin/payments':
    $rows = ''; try { foreach (db()->query('SELECT order_reference, type, amount, currency, method, status, channel, created_at FROM transactions ORDER BY created_at DESC') as $t) $rows .= '<tr><td>'.e($t['order_reference']).'</td><td>'.e($t['type']).'</td><td>'.e($t['currency'].' '.number_format((float)$t['amount'],2)).'</td><td>'.e($t['method']).'</td><td>'.e($t['status']).'</td><td>'.e($t['channel'] ?? '—').'</td></tr>'; } catch (Throwable $e) { $rows = '<tr><td colspan="6">Database unavailable.</td></tr>'; } $content='<div class="page"><p class="eyebrow">Admin workspace</p><h1>Payments</h1><div class="card"><div style="overflow:auto"><table><thead><tr><th>Reference</th><th>Type</th><th>Amount</th><th>Method</th><th>Status</th><th>Channel</th></tr></thead><tbody>'.$rows.'</tbody></table></div></div></div>'; break;
case '/admin/blog': case '/admin/images': $content='<div class="page"><p class="eyebrow">Admin workspace</p><h1>'.e(ucwords(str_replace(['/admin/','-'],' ', $path))).'</h1><div class="card"><p class="lead">This area is ready for content management. The member and payment records are connected to MySQL.</p><a class="btn" href="/admin">Back to dashboard</a></div></div>'; break;
default: http_response_code(404); $content='<div class="page"><h1>Page not found</h1><p class="lead">The page you requested does not exist.</p><a class="btn" href="/">Return home</a></div>'; }
layout(ucwords(trim(str_replace(['/','-'],' ',$path))) ?: 'Home', $content, $path, $user, $flash);
?>
