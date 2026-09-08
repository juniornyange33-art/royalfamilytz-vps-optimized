<?php
declare(strict_types=1);

// Load local .env file if it exists locally
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        if (str_contains($line, '=')) {
            list($key, $value) = explode('=', $line, 2);
            putenv(sprintf('%s=%s', trim($key), trim($value, '"\' ')));
        }
    }
}

return [
    'app_url' => getenv('APP_URL') ?: 'https://royalfamilytz.org',

    'database' => [
        'driver'   => getenv('DB_DRIVER') ?: 'mysql',
        'host'     => getenv('DB_HOST') ?: 'mysql-1b6a03ce-juniornyange33-b2e7.aivencloud.com',
        'port'     => getenv('DB_PORT') ?: '26708',
        'name'     => getenv('DB_NAME') ?: 'defaultdb',
        'user'     => getenv('DB_USER') ?: 'avnadmin',
        'password' => getenv('DB_PASS') !== false ? (string)getenv('DB_PASS') : '',
        'charset'  => 'utf8mb4',
    ],

    'google' => [
        'client_id'     => getenv('GOOGLE_CLIENT_ID') ?: '',
        'client_secret' => getenv('GOOGLE_CLIENT_SECRET') ?: '',
        'redirect_uri'  => (getenv('APP_URL') ?: 'https://royalfamilytz.org') . '/login/google/callback',
    ],

'clickpesa' => [
    'client_id' => getenv('CLICKPESA_CLIENT_ID') ?: 'IDr7ouxDK7Wo3rXkvErzZcITwIrelu8R',
    'api_key'   => getenv('CLICKPESA_API_KEY')   ?: 'SKONQwjwnSyrD1yEFg4CKBFmRC0a2FywBYQu0yvpSh',
    'checksum'  => getenv('CLICKPESA_CHECKSUM')  ?: 'CHKH9FLxY15h1DpFoaAaWrN2vKK1tOaVTGQ',
],

    'smtp' => [
        'host'       => getenv('SMTP_HOST') ?: 'smtp.gmail.com',
        'port'       => (int)(getenv('SMTP_PORT') ?: 587),
        'security'   => getenv('SMTP_SECURITY') ?: 'tls',
        'username'   => getenv('SMTP_USER') ?: 'royalfamilytz.org@gmail.com',
        'password'   => getenv('SMTP_PASSWORD') ?: '',
        'from_email' => getenv('MAIL_FROM') ?: 'juniornyange33@gmail.com',
        'from_name'  => getenv('MAIL_FROM_NAME') ?: 'Royal Family TZ',
    ],
'contact_email' => 'juniornyange33@ghmail.com',
];
