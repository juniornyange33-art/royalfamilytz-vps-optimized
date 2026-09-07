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
{
    fwrite($socket, $command . "\r\n");
    $response = smtp_read($socket);
    $code = (int)substr($response, 0, 3);
    if (!in_array(intdiv($code, 100), $accepted, true)) throw new RuntimeException('SMTP error: ' . trim($response));
    return $response;
}
function send_email(string $to, string $subject, string $body): bool
{
    if (!mail_configured()) return false;

    $c = mail_config();
    $host = (string)$c['smtp_host'];
    $port = (int)($c['smtp_port'] ?? 465);
    $secure = strtolower((string)($c['smtp_security'] ?? 'ssl'));

    $transport = ($secure === 'ssl' || $port === 465) ? 'ssl://' . $host : 'tcp://' . $host;

    $context = stream_context_create([
        'ssl' => [
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true
        ]
    ]);

    $socket = @stream_socket_client(
        $transport . ':' . $port,
        $errno,
        $errstr,
        15,
        STREAM_CLIENT_CONNECT,
        $context
    );

    if (!$socket) {
        throw new RuntimeException('SMTP connection failed: ' . $errstr);
    }

    stream_set_timeout($socket, 15);

    try {
        smtp_read($socket);
        smtp_command($socket, 'EHLO localhost');

        if ($secure === 'tls' && $port !== 465) {
            smtp_command($socket, 'STARTTLS');
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('Could not start TLS encryption');
            }
            smtp_command($socket, 'EHLO localhost');
        }

        if (!empty($c['smtp_user'])) {
            smtp_command($socket, 'AUTH LOGIN');
            smtp_command($socket, base64_encode((string)$c['smtp_user']));
            smtp_command($socket, base64_encode((string)$c['smtp_password']));
        }

        $from = (string)($c['from'] ?? $c['smtp_user']);
        $fromName = (string)($c['from_name'] ?? 'Royal Family TZ');

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
    } finally {
        fclose($socket);
    }

    return true;
}
?>
