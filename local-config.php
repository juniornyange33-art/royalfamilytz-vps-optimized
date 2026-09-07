<?php
declare(strict_types=1);

return [
    'APP_URL' => getenv('APP_URL') ?: 'https://royalfamilytz.org',

    // Database
    'DB_DRIVER' => getenv('DB_DRIVER') ?: 'mysql',
    'DB_HOST'   => getenv('DB_HOST') ?: 'mysql-1b6a03ce-juniornyange33-b2e7.aivencloud.com',
    'DB_PORT'   => getenv('DB_PORT') ?: '26708',
    'DB_NAME'   => getenv('DB_NAME') ?: 'defaultdb',
    'DB_USER'   => getenv('DB_USER') ?: 'avnadmin',
    'DB_PASS'   => getenv('DB_PASS') !== false ? (string)getenv('DB_PASS') : '',

    // Google OAuth (Pulled from Render Environment Variables)
    'GOOGLE_CLIENT_ID'     => getenv('GOOGLE_CLIENT_ID') ?: '',
    'GOOGLE_CLIENT_SECRET' => getenv('GOOGLE_CLIENT_SECRET') ?: '',

    // ClickPesa Payments (Pulled from Render Environment Variables)
    'CLICKPESA_CLIENT_ID' => getenv('CLICKPESA_CLIENT_ID') ?: '',
    'CLICKPESA_API_KEY'   => getenv('CLICKPESA_API_KEY') ?: '',
    'CLICKPESA_CHECKSUM'  => getenv('CLICKPESA_CHECKSUM') ?: '',

    // Gmail SMTP (Pulled from Render Environment Variables)
    'SMTP_HOST'       => getenv('SMTP_HOST') ?: 'smtp.gmail.com',
    'SMTP_PORT'       => (int)(getenv('SMTP_PORT') ?: 587),
    'SMTP_SECURITY'   => getenv('SMTP_SECURITY') ?: 'tls',
    'SMTP_USER'       => getenv('SMTP_USER') ?: 'royalfamilytz.org@gmail.com',
    'SMTP_PASS'       => getenv('SMTP_PASSWORD') ?: '',
    'SMTP_PASSWORD'   => getenv('SMTP_PASSWORD') ?: '',
    'MAIL_FROM'       => getenv('MAIL_FROM') ?: 'royalfamilytz.org@gmail.com',
    'MAIL_FROM_NAME'  => getenv('MAIL_FROM_NAME') ?: 'Royal Family TZ',
];
