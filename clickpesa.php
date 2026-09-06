<?php
declare(strict_types=1);

function cp_config(string $key, string $default = ''): string { static $local = null; if ($local === null) { $file = __DIR__ . '/local-config.php'; $local = is_file($file) ? (require $file) : []; } return getenv($key) ?: (string)($local[$key] ?? $default); }
function cp_base(): string { return rtrim(cp_config('CLICKPESA_BASE_URL', 'https://api.clickpesa.com/third-parties'), '/'); }

function cp_canonicalize(mixed $value): mixed {
    if (!is_array($value)) return $value;
    if (array_is_list($value)) return array_map('cp_canonicalize', $value);
    ksort($value);
    foreach ($value as $key => $item) $value[$key] = cp_canonicalize($item);
    return $value;
}
function cp_checksum(array $payload): string {
    $without = $payload; unset($without['checksum'], $without['checksumMethod']);
    return hash_hmac('sha256', json_encode(cp_canonicalize($without), JSON_UNESCAPED_SLASHES), cp_config('CLICKPESA_CHECKSUM_KEY'));
}
function cp_with_checksum(array $payload): array {
    if (cp_config('CLICKPESA_CHECKSUM_KEY') !== '') $payload['checksum'] = cp_checksum($payload);
    return $payload;
}
function cp_verify_checksum(array $payload): bool {
    if (cp_config('CLICKPESA_CHECKSUM_KEY') === '') return true;
    return isset($payload['checksum']) && hash_equals((string)$payload['checksum'], cp_checksum($payload));
}
function cp_token(): string {
    static $token = null;
    if ($token) return $token;
    $ch = curl_init(cp_base() . '/generate-token');
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_HTTPHEADER => ['client-id: ' . cp_config('CLICKPESA_CLIENT_ID'), 'api-key: ' . cp_config('CLICKPESA_API_KEY')]]);
    $raw = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); $error = curl_error($ch); curl_close($ch);
    if ($raw === false || $code < 200 || $code >= 300) throw new RuntimeException('ClickPesa token request failed: ' . ($error ?: $raw));
    $data = json_decode($raw, true); $token = $data['token'] ?? null;
    if (!$token) throw new RuntimeException('ClickPesa did not return an authorization token.');
    return $token;
}
function cp_request(string $method, string $endpoint, ?array $payload = null): array {
    $ch = curl_init(cp_base() . '/' . ltrim($endpoint, '/'));
    $headers = ['Authorization: ' . cp_token(), 'Content-Type: application/json'];
    $options = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 45, CURLOPT_CUSTOMREQUEST => strtoupper($method), CURLOPT_HTTPHEADER => $headers];
    if ($payload !== null) $options[CURLOPT_POSTFIELDS] = json_encode(cp_with_checksum($payload), JSON_UNESCAPED_SLASHES);
    curl_setopt_array($ch, $options); $raw = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); $error = curl_error($ch); curl_close($ch);
    if ($raw === false || $code < 200 || $code >= 300) throw new RuntimeException('ClickPesa API error (' . $code . '): ' . ($error ?: $raw));
    return json_decode($raw, true) ?: [];
}
function cp_normalize_phone(string $phone): string {
    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    if (str_starts_with($digits, '255')) return $digits;
    if (str_starts_with($digits, '0')) return '255' . substr($digits, 1);
    return '255' . $digits;
}
function cp_start_payment(float $amount, string $method, string $phone, string $orderReference, string $name, string $email): array {
    if (cp_config('CLICKPESA_CLIENT_ID') === '' || cp_config('CLICKPESA_API_KEY') === '') throw new RuntimeException('ClickPesa credentials are not configured in the server environment.');
    if ($method === 'mobile') {
        $payload = ['amount' => (string)$amount, 'currency' => 'TZS', 'orderReference' => $orderReference, 'phoneNumber' => cp_normalize_phone($phone)];
        cp_request('POST', '/payments/preview-ussd-push-request', $payload);
        return cp_request('POST', '/payments/initiate-ussd-push-request', $payload);
    }
    if ($method === 'card') {
        return cp_request('POST', '/checkout-link/generate-checkout-url', ['totalPrice' => (string)$amount, 'orderReference' => $orderReference, 'orderCurrency' => 'TZS', 'customerName' => $name, 'customerEmail' => $email, 'customerPhone' => cp_normalize_phone($phone), 'description' => 'Royal Family TZ payment']);
    }
    throw new RuntimeException('Choose Mobile Money or Card.');
}
?>
