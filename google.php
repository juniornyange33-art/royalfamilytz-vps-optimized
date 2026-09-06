<?php
declare(strict_types=1);

function google_config(string $key, string $default = ''): string {
    static $local = null;
    if ($local === null) { $file = __DIR__ . '/local-config.php'; $local = is_file($file) ? (require $file) : []; }
    return getenv($key) ?: (string)($local[$key] ?? $default);
}
function google_enabled(): bool { return google_config('GOOGLE_CLIENT_ID') !== '' && google_config('GOOGLE_CLIENT_SECRET') !== ''; }
function google_redirect_uri(): string {
    $configured = rtrim(google_config('APP_URL', ''), '/');
    $configuredHost = strtolower((string)parse_url($configured, PHP_URL_HOST));
    $isLocalConfigured = $configuredHost === '' || in_array($configuredHost, ['localhost', '127.0.0.1', '::1'], true);
    $forwardedProto = strtolower(trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0] ?? ''));
    $scheme = $forwardedProto === 'https' || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $requestHost = (string)($_SERVER['HTTP_HOST'] ?? '');
    $requestHostOnly = strtolower((string)strtok($requestHost, ':'));
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    $dir = dirname($script);
    $basePath = ($dir === '.' || $dir === '/') ? '' : rtrim($dir, '/');
    if ($configured !== '' && !$isLocalConfigured) return $configured . '/auth/google/callback';
    if ($requestHost === '' || in_array($requestHostOnly, ['localhost', '127.0.0.1', '::1'], true)) {
        throw new RuntimeException('Google login requires a public HTTPS URL. Open the website using your Cloudflare Tunnel or live domain, not localhost.');
    }
    return $scheme . '://' . $requestHost . $basePath . '/auth/google/callback';
}
function google_auth_url(): string {
    $_SESSION['google_oauth_state'] = bin2hex(random_bytes(24));
    $_SESSION['google_oauth_redirect_uri'] = google_redirect_uri();
    return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
        'client_id' => google_config('GOOGLE_CLIENT_ID'), 'redirect_uri' => $_SESSION['google_oauth_redirect_uri'],
        'response_type' => 'code', 'scope' => 'openid email profile', 'access_type' => 'online',
        'prompt' => 'select_account', 'state' => $_SESSION['google_oauth_state'],
    ]);
}
function google_exchange_code(string $code): array {
    $redirectUri = (string)($_SESSION['google_oauth_redirect_uri'] ?? google_redirect_uri());
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_POSTFIELDS => http_build_query([
        'code'=>$code, 'client_id'=>google_config('GOOGLE_CLIENT_ID'), 'client_secret'=>google_config('GOOGLE_CLIENT_SECRET'), 'redirect_uri'=>$redirectUri, 'grant_type'=>'authorization_code'
    ]), CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded']]);
    $raw = curl_exec($ch); $status = curl_getinfo($ch, CURLINFO_HTTP_CODE); $curlError = curl_error($ch); curl_close($ch);
    $data = json_decode((string)$raw, true) ?: [];
    if ($status >= 300 || empty($data['access_token'])) {
        $reason = (string)($data['error_description'] ?? $data['error'] ?? $curlError ?? 'unknown token exchange error');
        throw new RuntimeException('Google authorization failed: ' . $reason . '. Check that APP_URL and Google Authorized redirect URI match exactly, and that Client ID and Secret belong to the same Google OAuth application.');
    }
    $ch = curl_init('https://openidconnect.googleapis.com/v1/userinfo'); curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$data['access_token']]]); $raw = curl_exec($ch); $status = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    $profile = json_decode((string)$raw, true) ?: [];
    if ($status >= 300 || empty($profile['sub']) || empty($profile['email'])) throw new RuntimeException('Google profile could not be read.');
    return $profile;
}
