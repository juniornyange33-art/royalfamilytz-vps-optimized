<?php
declare(strict_types=1);

if (!function_exists('mail_config')) {
    function mail_config(): array
    {
        static $config = null;
        if ($config !== null) return $config;
        
        $app = is_file(__DIR__ . '/config.php') ? require __DIR__ . '/config.php' : [];
        $mail = $app['smtp'] ?? [];

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
}

if (!function_exists('mail_configured')) {
    function mail_configured(): bool
    {
        $resendKey = getenv('RESEND_API_KEY');
        if (!empty($resendKey)) {
            return true;
        }

        $c = mail_config();
        return !empty($c['smtp_host']) && !empty($c['smtp_user']) && !empty($c['smtp_password']);
    }
}

if (!function_exists('send_email')) {
    /**
     * Send an email. Supports Resend API (if RESEND_API_KEY) or SMTP fallback.
     * $attachments is an array of ['name' => string, 'type' => mime-type, 'data' => raw-binary]
     */
    function send_email(string $to, string $subject, string $body, array $attachments = []): bool
    {
        $resendKey = getenv('RESEND_API_KEY');
        $from = mail_config()['from'] ?? 'royalfamilytz.org@gmail.com';
        $fromName = mail_config()['from_name'] ?? 'Royal Family TZ';
        if (!empty($resendKey)) {
            $payload = [
                'from'    => $fromName . ' <' . $from . '>',
                'to'      => [$to],
                'subject' => $subject,
                'text'    => $body,
            ];
            if (!empty($attachments)) {
                $payload['attachments'] = array_map(function($att) {
                    return ['type' => $att['type'] ?? 'application/octet-stream', 'name' => $att['name'], 'data' => base64_encode($att['data'])];
                }, $attachments);
            }

            $ch = curl_init('https://api.resend.com/emails');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $resendKey,
                'Content-Type: application/json',
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode < 200 || $httpCode >= 300) {
                error_log("Resend Mail Error (HTTP {$httpCode}): " . $response);
            }

            return $httpCode >= 200 && $httpCode < 300;
        }

        // Fallback: send via SMTP using basic socket (supports AUTH LOGIN)
        $cfg = mail_config();
        $host = $cfg['smtp_host'] ?? '';
        $port = (int)($cfg['smtp_port'] ?? 465);
        $secure = $cfg['smtp_security'] ?? 'ssl';
        $user = $cfg['smtp_user'] ?? '';
        $pass = $cfg['smtp_password'] ?? '';

        if (!$host || !$user || !$pass) return false;

        $errno = 0; $errstr = '';
        $transport = ($secure === 'ssl') ? 'ssl://' . $host : $host;
        $fp = stream_socket_client($transport . ':' . $port, $errno, $errstr, 15, STREAM_CLIENT_CONNECT);
        if (!$fp) { error_log("SMTP connect failed: {$errno} {$errstr}"); return false; }

        $read = fn() => fgets($fp, 515);
        $write = fn($s) => fwrite($fp, $s . "\r\n");

        $server = trim($read());
        // EHLO
        $write('EHLO ' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        while (($line = trim($read())) !== '') { if (substr($line, 0, 3) === '250') { if (strpos($line, 'STARTTLS') !== false) $hasStartTls = true; } }
        if ($secure === 'tls') {
            $write('STARTTLS'); $tlsResp = trim($read()); if (substr($tlsResp,0,3) !== '220') { fclose($fp); error_log('SMTP STARTTLS failed: ' . $tlsResp); return false; } stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $write('EHLO ' . ($_SERVER['HTTP_HOST'] ?? 'localhost')); while (($line = trim($read())) !== '') {}
        }

        $write('AUTH LOGIN'); $read(); $write(base64_encode($user)); $read(); $write(base64_encode($pass)); $authResp = trim($read()); if (substr($authResp,0,3) !== '235') { fclose($fp); error_log('SMTP auth failed: ' . $authResp); return false; }

        $bound = '==BOUND_' . md5(time()) . '==';
        // Headers
        $headers = [];
        $headers[] = 'From: ' . $fromName . ' <' . $from . '>';
        $headers[] = 'To: ' . $to;
        $headers[] = 'Subject: ' . $subject;
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: multipart/mixed; boundary="' . $bound . '"';

        $write('MAIL FROM:<' . $from . '>'); $read();
        $write('RCPT TO:<' . $to . '>'); $read();
        $write('DATA'); $read();

        $msg = implode("\r\n", $headers) . "\r\n\r\n";
        $msg .= "--{$bound}\r\n";
        $msg .= "Content-Type: text/plain; charset=utf-8\r\n\r\n";
        $msg .= $body . "\r\n\r\n";
        foreach ($attachments as $att) {
            $name = $att['name'] ?? 'attachment.bin';
            $type = $att['type'] ?? 'application/octet-stream';
            $data = base64_encode($att['data']);
            $msg .= "--{$bound}\r\n";
            $msg .= "Content-Type: {$type}; name=\"{$name}\"\r\n";
            $msg .= "Content-Transfer-Encoding: base64\r\n";
            $msg .= "Content-Disposition: attachment; filename=\"{$name}\"\r\n\r\n";
            $msg .= chunk_split($data, 76, "\r\n") . "\r\n\r\n";
        }
        $msg .= "--{$bound}--\r\n.";

        $write($msg);
        $sendResp = trim($read());
        $write('QUIT');
        fclose($fp);
        if (substr($sendResp, 0, 3) !== '250') { error_log('SMTP send failed: ' . $sendResp); return false; }
        return true;
    }
}
