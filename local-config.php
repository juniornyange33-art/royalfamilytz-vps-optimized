<?php
// Prevent direct browser access
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    header('HTTP/1.0 403 Forbidden');
    exit('Direct access not permitted.');
}

return [
    'APP_NAME' => 'Royal Family TZ',
    'APP_URL' => trim(getenv('APP_URL') ?: 'https://royalfamilytz.org', '/'),

    // Aiven Database Configuration
    'DB_DRIVER' => getenv('DB_DRIVER') ?: 'mysql',
    'DB_HOST' => getenv('DB_HOST') ?: 'mysql-1b6a03ce-juniornyange33-b2e7.aivencloud.com',
    'DB_PORT' => getenv('DB_PORT') ?: '26708',
    'DB_NAME' => getenv('DB_NAME') ?: 'defaultdb',
    'DB_USER' => getenv('DB_USER') ?: 'avnadmin',
    'DB_PASS' => getenv('DB_PASS') !== false ? (string)getenv('DB_PASS') : '',

    // Resend Email API Credentials
    'RESEND_API_KEY' => getenv('RESEND_API_KEY') ?: '',
    'MAIL_FROM' => 'Royal Family TZ <info@royalfamilytz.org>',
    'ADMIN_EMAIL' => 'royalfamilytz.org@gmail.com',

    // ClickPesa Payment Credentials
    'CLICKPESA_CLIENT_ID' => getenv('CLICKPESA_CLIENT_ID') ?: '',
    'CLICKPESA_API_KEY' => getenv('CLICKPESA_API_KEY') ?: '',
    'CLICKPESA_CHECKSUM' => getenv('CLICKPESA_CHECKSUM') ?: '',
];
