<?php
declare(strict_types=1);

if (!function_exists('mail_config')) {
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
    function send_email(string $to, string $subject, string $body): bool
    {
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

        return false;
    }
}
