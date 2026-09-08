<?php
declare(strict_types=1);

function cp_config(string $key, string $default = ''): string {
    static $local = null;
    if ($local === null) {
        $file = __DIR__ . '/config.php';
        $local = is_file($file) ? (require $file) : [];
    }

    // Map legacy or config array keys to environment variables
    if ($key === 'CLICKPESA_CHECKSUM_KEY' || $key === 'CLICKPESA_CHECKSUM') {
        return getenv('CLICKPESA_CHECKSUM') ?: ($local['clickpesa']['checksum'] ?? $default);
    }
    if ($key === 'CLICKPESA_CLIENT_ID') {
        return getenv('CLICKPESA_CLIENT_ID') ?: ($local['clickpesa']['client_id'] ?? $default);
    }
    if ($key === 'CLICKPESA_API_KEY') {
        return getenv('CLICKPESA_API_KEY') ?: ($local['clickpesa']['api_key'] ?? $default);
    }

    return getenv($key) ?: ($local['clickpesa'][$key] ?? $default);
}

function cp_base(): string {
    return rtrim(getenv('CLICKPESA_BASE_URL') ?: 'https://api.clickpesa.com/third-parties', '/');
}

function cp_canonicalize($value) {
    if (!is_array($value)) return $value;
    if (array_is_list($value)) return array_map('cp_canonicalize', $value);
    ksort($value);
    foreach ($value as $key => $item) {
        $value[$key] = cp_canonicalize($item);
    }
    return $value;
}

function cp_checksum(array $payload): string {
    $without = $payload;
    unset($without['checksum'], $without['checksumMethod']);
    $secret = cp_config('CLICKPESA_CHECKSUM');
    return hash_hmac('sha256', json_encode(cp_canonicalize($without), JSON_UNESCAPED_SLASHES), $secret);
}

function cp_with_checksum(array $payload): array {
    $payload['checksum'] = cp_checksum($payload);
    return $payload;
}

function cp_token(): string {
    static $token = null;
    if ($token !== null) return $token;

    $sch = curl_init(cp_base() . '/generate-token');
    curl_setopt_array($sch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'client-id: ' . cp_config('CLICKPESA_CLIENT_ID'),
            'api-key: ' . cp_config('CLICKPESA_API_KEY')
        ]
    ]);

    $raw = curl_exec($sch);
    $code = curl_getinfo($sch, CURLINFO_HTTP_CODE);
    $error = curl_error($sch);
    curl_close($sch);

    if ($raw === false || $code < 200 || $code >= 300) {
        throw new RuntimeException("ClickPesa token request failed (HTTP {$code}): " . ($error ?: $raw));
    }

    $data = json_decode($raw, true);
    $token = $data['token'] ?? null;
    if (!$token) {
        throw new RuntimeException('ClickPesa did not return an authorization token.');
    }

    return $token;
}

function cp_request(string $method, string $endpoint, ?array $payload = null): array {
    $sch = curl_init(cp_base() . '/' . ltrim($endpoint, '/'));
    $headers = [
        'Authorization: ' . cp_token(),
        'Content-Type: application/json'
    ];

    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => $headers
    ];

    if ($payload !== null) {
        $payloadWithChecksum = cp_with_checksum($payload);
        $options[CURLOPT_POSTFIELDS] = json_encode($payloadWithChecksum, JSON_UNESCAPED_SLASHES);
    }

    curl_setopt_array($sch, $options);
    $raw = curl_exec($sch);
    $code = curl_getinfo($sch, CURLINFO_HTTP_CODE);
    $error = curl_error($sch);
    curl_close($sch);

    if ($raw === false || $code < 200 || $code >= 300) {
        throw new RuntimeException("ClickPesa API error ({$code}): " . ($error ?: $raw));
    }

    return json_decode($raw, true) ?: [];
}

function cp_normalize_phone(string $phone): string {
    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    if (str_starts_with($digits, '255')) return $digits;
    if (str_starts_with($digits, '0')) return '255' . substr($digits, 1);
    return '255' . $digits;
}

function cp_start_payment(float $amount, string $method, string $phone, string $orderReference, string $name, string $email): array {
    if (empty(cp_config('CLICKPESA_CLIENT_ID')) || empty(cp_config('CLICKPESA_API_KEY'))) {
        throw new RuntimeException('ClickPesa credentials are not configured in the server environment.');
    }

    if (in_array($method, ['mobile', 'mpesa', 'tigopesa', 'airtelmoney', 'halopesa'], true)) {
        $payload = [
            'amount'         => (string)$amount,
            'currency'       => 'TZS',
            'orderReference' => $orderReference,
            'phoneNumber'    => cp_normalize_phone($phone)
        ];
        return cp_request('POST', '/payments/initiate-ussd-push-request', $payload);
    }

    throw new RuntimeException('Unsupported payment method specified.');
}
