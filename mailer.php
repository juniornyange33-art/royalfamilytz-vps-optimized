<?php
declare(strict_types=1);

function mail_config(): array
{
    static $config = null;
    if ($config !== null) return $config;
    
    $app = require __DIR__ . '/config.php';
    $mail = $app['mail'] ?? [];

    // Fallback to environment variables if config is empty
    return [
        'smtp_host'     => $mail['smtp_host']     ?? $mail['SMTP_HOST']     ?? getenv('SMTP_HOST')     ?: 'smtp.gmail.com',
        'smtp_port'     => $mail['smtp_port']     ?? $mail['SMTP_PORT']     ?? getenv('SMTP_PORT')     ?: 587,
        'smtp_security' => $mail['smtp_security'] ?? $mail['SMTP_SECURITY'] ?? getenv('SMTP_SECURITY') ?: 'tls',
        'smtp_user'     => $mail['smtp_user']     ?? $mail['SMTP_USER']     ?? getenv('SMTP_USER')     ?: 'royalfamilytz.org@gmail.com',
        'smtp_password' => $mail['smtp_password'] ?? $mail['SMTP_PASS']     ?? $mail['SMTP_PASSWORD'] ?? getenv('SMTP_PASSWORD') ?: getenv('SMTP_PASS') ?: '',
        'from'          => $mail['from']          ?? $mail['MAIL_FROM']     ?? getenv('MAIL_FROM')     ?: 'royalfamilytz.org@gmail.com',
        'from_name'     => $mail['from_name']     ?? $mail['MAIL_FROM_NAME']?? getenv('MAIL_FROM_NAME')?: 'Royal Family TZ',
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
    $c = mail_config(); $host = (string)$c['smtp_host']; $port = (int)($c['smtp_port'] ?? 587); $secure = strtolower((string)($c['smtp_security'] ?? 'tls'));
    $transport = $secure === 'ssl' ? 'ssl://' . $host : 'tcp://' . $host;
    $socket = @stream_socket_client($transport . ':' . $port, $errno, $error, 20, STREAM_CLIENT_CONNECT);
    if (!$socket) throw new RuntimeException('SMTP connection failed: ' . $error);
    stream_set_timeout($socket, 20);
    try {
        smtp_read($socket); smtp_command($socket, 'EHLO localhost');
        if ($secure === 'tls') { smtp_command($socket, 'STARTTLS'); if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new RuntimeException('Could not start SMTP TLS.'); smtp_command($socket, 'EHLO localhost'); }
        smtp_command($socket, 'AUTH LOGIN'); smtp_command($socket, base64_encode((string)$c['smtp_user'])); smtp_command($socket, base64_encode((string)$c['smtp_password']));
        $from = (string)$c['from']; smtp_command($socket, 'MAIL FROM:<' . $from . '>'); smtp_command($socket, 'RCPT TO:<' . $to . '>'); smtp_command($socket, 'DATA', [3]);
        $headers = 'From: ' . ($c['from_name'] ?? 'Royal Family TZ') . ' <' . $from . ">\r\n"; $headers .= 'To: <' . $to . ">\r\n"; $headers .= 'Subject: ' . mb_encode_mimeheader($subject, 'UTF-8') . "\r\n"; $headers .= "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n";
        $safeBody = preg_replace('/\r?\n\./', "\n..", $body); fwrite($socket, $headers . "\r\n" . $safeBody . "\r\n.\r\n"); smtp_read($socket); smtp_command($socket, 'QUIT');
    } finally { fclose($socket); }
    return true;
}
?>
