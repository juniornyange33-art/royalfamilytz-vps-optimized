<?php
declare(strict_types=1);

$smtpUser = getenv('SMTP_USER') ?: 'royalfamilytz.org@gmail.com';
$smtpPass = getenv('SMTP_PASSWORD') ?: getenv('SMTP_PASS') ?: '';

// Define global legacy constants if expected by functions
if (!defined('SMTP_USER')) define('SMTP_USER', $smtpUser);
if (!defined('SMTP_PASS')) define('SMTP_PASS', $smtpPass);
if (!defined('SMTP_HOST')) define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.gmail.com');
if (!defined('SMTP_PORT')) define('SMTP_PORT', (int)(getenv('SMTP_PORT') ?: 587));

return [
    'APP_URL' => trim(getenv('APP_URL') ?: 'https://royalfamilytz.org', '[]'),

    // Database
    'DB_DRIVER' => getenv('DB_DRIVER') ?: 'mysql',
    'DB_HOST'   => getenv('DB_HOST') ?: 'mysql-1b6a03ce-juniornyange33-b2e7.aivencloud.com',
    'DB_PORT'   => getenv('DB_PORT') ?: '26708',
    'DB_NAME'   => getenv('DB_NAME') ?: 'defaultdb',
    'DB_USER'   => getenv('DB_USER') ?: 'avnadmin',
    'DB_PASS'   => getenv('DB_PASS') !== false ? (string)getenv('DB_PASS') : '',

    // Google OAuth
    'GOOGLE_CLIENT_ID'     => getenv('GOOGLE_CLIENT_ID') ?: '',
    'GOOGLE_CLIENT_SECRET' => getenv('GOOGLE_CLIENT_SECRET') ?: '',

    // ClickPesa Payments
    'CLICKPESA_CLIENT_ID' => getenv('CLICKPESA_CLIENT_ID') ?: '',
    'CLICKPESA_API_KEY'   => getenv('CLICKPESA_API_KEY') ?: '',
    'CLICKPESA_CHECKSUM'  => getenv('CLICKPESA_CHECKSUM') ?: '',

    // Gmail SMTP (Provides both array structures)
    'SMTP_HOST'       => getenv('SMTP_HOST') ?: 'smtp.gmail.com',
    'SMTP_PORT'       => (int)(getenv('SMTP_PORT') ?: 587),
    'SMTP_SECURITY'   => getenv('SMTP_SECURITY') ?: 'tls',
    'SMTP_USER'       => $smtpUser,
    'SMTP_PASS'       => $smtpPass,
    'SMTP_PASSWORD'   => $smtpPass,
    'MAIL_FROM'       => getenv('MAIL_FROM') ?: $smtpUser,
    'MAIL_FROM_NAME'  => getenv('MAIL_FROM_NAME') ?: 'Royal Family TZ',

    'smtp' => [
        'host'       => getenv('SMTP_HOST') ?: 'smtp.gmail.com',
        'port'       => (int)(getenv('SMTP_PORT') ?: 587),
        'security'   => getenv('SMTP_SECURITY') ?: 'tls',
        'username'   => $smtpUser,
        'password'   => $smtpPass,
        'from_email' => getenv('MAIL_FROM') ?: $smtpUser,
        'from_name'  => getenv('MAIL_FROM_NAME') ?: 'Royal Family TZ',
    ]
];
