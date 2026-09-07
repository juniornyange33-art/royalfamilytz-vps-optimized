<?php
declare(strict_types=1);

function mail_config(): array
{
    static $config = null;
    if ($config !== null) return $config;
    
    $app = require __DIR__ . '/config.php';
    $mail = $app['mail'] ?? [];

    return [
        'smtp_host'     => $mail['smtp_host']     ?? getenv('SMTP_HOST')     ?: 'smtp.gmail.com',
        'smtp_port'     => (int)($mail['smtp_port'] ?? getenv('SMTP_PORT')     ?: 465),
        'smtp_security' => $mail['smtp_security'] ?? getenv('SMTP_SECURITY') ?: 'ssl',
        'smtp_user'     => $mail['smtp_user']     ?? getenv('SMTP_USER')     ?: 'royalfamilytz.org@gmail.com',
        'smtp_password' => $mail['smtp_password'] ?? getenv('SMTP_PASSWORD') ?: getenv('SMTP_PASS') ?: '',
        'from'          => $mail['from']          ?? getenv('MAIL_FROM')     ?: 'royalfamilytz.org@gmail.com',
        'from_name'     => $mail['from_name']     ?? getenv('MAIL_FROM_NAME')?: 'Royal Family TZ',
    ];
}

function mail_configured(): bool
{
    $c = mail_config();
    return !empty($c['smtp_host']) && !empty($c['smtp_user']) && !empty($c['smtp_password']);
}

function smtp_read($socket): string
{
    $response = '';
    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        if (isset($line[3]) && $line[3] === ' ') break;
    }
    return $response;
}

function smtp_command($socket, string $command, array $accepted = [2, 3]): string
<?php
declare(strict_types=1);

function mail_config(): array
{
    static $config = null;
    if ($config !== null) return $config;
    
    $app = is_file(__DIR__ . '/config.php') ? require __DIR__ . '/config.php' : [];
    $mail = $app['mail'] ?? [];

    return [
        'smtp_host'     => $mail['smtp_host']     ?? getenv('SMTP_HOST')     ?: 'smtp.gmail.com',
        'smtp_port'     => (int)($mail['smtp_port'] ?? getenv('SMTP_PORT')     ?: 465),
        'smtp_security' => $mail['smtp_security'] ?? getenv('SMTP_SECURITY') ?: 'ssl',
        'smtp_user'     => $mail['smtp_user']     ?? getenv('SMTP_USER')     ?: '',
        'smtp_password' => $mail['smtp_password'] ?? getenv('SMTP_PASSWORD') ?: getenv('SMTP_PASS') ?: '',
        'from'          => $mail['from']          ?? getenv('MAIL_FROM')     ?: 'royalfamilytz.org@gmail.com',
        'from_name'     => $mail['from_name']     ?? getenv('MAIL_FROM_NAME')?: 'Royal Family TZ',
    ];
}

function mail_configured(): bool
{
    $resendKey = getenv('RESEND_API_KEY');
    if (!empty($resendKey)) {
        return true;
    }

    $c = mail_config();
    return !empty($c['smtp_host']) && !empty($c['smtp_user']) && !empty($c['smtp_password']);
}

function send_email(string $to, string $subject, string $body): bool
{
    // Mode 1: Resend HTTP API (Used on Render to bypass SMTP port blocking)
    $resendKey = getenv('RESEND_API_KEY');
    if (!empty($resendKey)) {
        $payload = json_encode([
            'from'    => 'Royal Family TZ <onboarding@resend.dev>',
            'to'      => [$to],
            'subject' => $subject,
            'text'    => $body,
        ]);

        $ch = curl_init('https://api.resend.com/emails');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $resendKey,
            'Content-Type: application/json',
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode >= 200 && $httpCode < 300;
    }

    // Mode 2: Direct SMTP Socket (For local dev or future VPS environment)
    if (!mail_configured()) {
        return false;
    }

    $c = mail_config();
    $host = (string)$c['smtp_host'];
    $port = (int)$c['smtp_port'];
    $secure = strtolower((string)$c['smtp_security']);

    $transport = ($secure === 'ssl' || $port === 465) ? 'ssl://' . $host : 'tcp://' . $host;

    $context = stream_context_create([
        'ssl' => [
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true,
        ]
    ]);

    $socket = @stream_socket_client(
        $transport . ':' . $port,
        $errno,
        $errstr,
        10,
        STREAM_CLIENT_CONNECT,
        $context
    );

    if (!$socket) {
        return false;
    }

    stream_set_timeout($socket, 10);

    try {
        smtp_read($socket);
        smtp_command($socket, 'EHLO localhost');

        if ($secure === 'tls' && $port !== 465) {
            smtp_command($socket, 'STARTTLS');
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                return false;
            }
            smtp_command($socket, 'EHLO localhost');
        }

        if (!empty($c['smtp_user'])) {
            smtp_command($socket, 'AUTH LOGIN');
            smtp_command($socket, base64_encode((string)$c['smtp_user']));
            smtp_command($socket, base64_encode((string)$c['smtp_password']));
        }

        $from = (string)$c['from'];
        $fromName = (string)$c['from_name'];

        smtp_command($socket, 'MAIL FROM:<' . $from . '>');
        smtp_command($socket, 'RCPT TO:<' . $to . '>');
        smtp_command($socket, 'DATA', [354]);

        $headers  = "From: {$fromName} <{$from}>\r\n";
        $headers .= "To: <{$to}>\r\n";
        $headers .= "Subject: " . mb_encode_mimeheader($subject, 'UTF-8') . "\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

        $safeBody = preg_replace('/^\./m', '..', $body);
        fwrite($socket, $headers . "\r\n" . $safeBody . "\r\n.\r\n");

        smtp_read($socket);
        smtp_command($socket, 'QUIT');
        return true;
    } catch (Throwable $e) {
        return false;
    } finally {
        if (is_resource($socket)) {
            fclose($socket);
        }
    }
}

function smtp_read($socket): string
{
    $response = '';
    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        if (isset($line[3]) && $line[3] === ' ') break;
    }
    return $response;
}

function smtp_command($socket, string $command, array $accepted = [2, 3]): string
{
    fwrite($socket, $command . "\r\n");
    $response = smtp_read($socket);
    $code = (int)substr($response, 0, 3);
    if (!in_array((int)floor($code / 100), $accepted, true)) {
        throw new RuntimeException('SMTP error: ' . trim($response));
    }
    return $response;
}
