<?php
/**
 * Royal Family TZ application configuration.
 */
declare(strict_types=1);

$localConfig = is_file(__DIR__ . '/local-config.php')
    ? (require __DIR__ . '/local-config.php')
    : [];

return [
    'app_name' => 'Royal Family TZ',
    'timezone' => 'Africa/Dar_es_Salaam',
    'database' => [
        'driver' => getenv('DB_DRIVER') ?: ($localConfig['DB_DRIVER'] ?? 'mysql'),
        'host'   => getenv('DB_HOST') ?: ($localConfig['DB_HOST'] ?? 'mysql-1b6a03ce-juniornyange33-b2e7.aivencloud.com'),
        'port'   => getenv('DB_PORT') ?: ($localConfig['DB_PORT'] ?? '26708'),
        'name'   => getenv('DB_NAME') ?: ($localConfig['DB_NAME'] ?? 'defaultdb'),
        'user'   => getenv('DB_USER') ?: ($localConfig['DB_USER'] ?? 'avnadmin'),
        'password' => getenv('DB_PASS') !== false && getenv('DB_PASS') !== ''
            ? (string)getenv('DB_PASS')
            : (string)($localConfig['DB_PASS'] ?? ''),
        'charset' => 'utf8mb4',
    ],
    'payments' => [
        'clickpesa_client_id' => getenv('CLICKPESA_CLIENT_ID') ?: ($localConfig['CLICKPESA_CLIENT_ID'] ?? ''),
        'clickpesa_api_key'   => getenv('CLICKPESA_API_KEY') ?: ($localConfig['CLICKPESA_API_KEY'] ?? ''),
        'stripe_secret_key'   => getenv('STRIPE_SECRET_KEY') ?: '',
        'paypal_client_id'    => getenv('PAYPAL_CLIENT_ID') ?: '',
    ],
    'mail' => [
        'from'          => getenv('MAIL_FROM') ?: ($localConfig['MAIL_FROM'] ?? 'hello@royalfamilytz.org'),
        'from_name'     => getenv('MAIL_FROM_NAME') ?: ($localConfig['MAIL_FROM_NAME'] ?? 'Royal Family TZ'),
        'smtp_host'     => getenv('SMTP_HOST') ?: ($localConfig['SMTP_HOST'] ?? ''),
        'smtp_port'     => getenv('SMTP_PORT') ?: ($localConfig['SMTP_PORT'] ?? '587'),
        'smtp_security' => getenv('SMTP_SECURITY') ?: ($localConfig['SMTP_SECURITY'] ?? 'tls'),
        'smtp_user'     => getenv('SMTP_USER') ?: ($localConfig['SMTP_USER'] ?? ''),
        'smtp_password' => getenv('SMTP_PASSWORD') !== false ? (string)getenv('SMTP_PASSWORD') : (string)($localConfig['SMTP_PASSWORD'] ?? ''),
    ],
];
?>
