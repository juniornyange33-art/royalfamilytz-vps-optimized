<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/db.php';
$config = require __DIR__ . '/config.php';

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($name) || empty($email) || empty($password)) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        try {
            $pdo = db();

            // Check if user exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = "An account with this email already exists.";
            } else {
                // Hash password
                $passwordHash = password_hash($password, PASSWORD_BCRYPT);
                $verificationToken = bin2hex(random_bytes(32));

                // Insert new user
                $insertStmt = $pdo->prepare("
                    INSERT INTO users (name, email, password_hash, role, email_verification_token, membership_active) 
                    VALUES (?, ?, ?, 'member', ?, 0)
                ");
                $insertStmt->execute([$name, $email, $passwordHash, $verificationToken]);

                // SMTP Mailer Check
                $smtpPassword = $config['smtp']['password'] ?? '';
                if (!empty($smtpPassword)) {
                    $to = $email;
                    $subject = "Verify Your Royal Family TZ Account";
                    $verifyUrl = $config['app_url'] . "/verify.php?token=" . $verificationToken;
                    $message = "Hello {$name},\n\nThank you for joining Royal Family TZ. Please verify your account by clicking the link below:\n\n{$verifyUrl}\n\nBest regards,\nRoyal Family TZ Team";
                    $headers = "From: " . $config['smtp']['from_email'] . "\r\n" .
                               "Reply-To: " . $config['smtp']['from_email'] . "\r\n" .
                               "X-Mailer: PHP/" . phpversion();

                    @mail($to, $subject, $message, $headers);
                    $success = "Account created successfully! Please check your email to verify your account.";
                } else {
                    $success = "Account created successfully!";
                }
            }
        } catch (Throwable $e) {
            $error = "Registration failed: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Your Account - Royal Family TZ</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8 bg-white p-8 rounded-xl shadow-md">
            <div>
                <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">Create your account</h2>
            </div>

            <?php if ($error): ?>
                <div class="p-4 bg-red-100 border-l-4 border-red-500 text-red-700 text-sm">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="p-4 bg-green-100 border-l-4 border-green-500 text-green-700 text-sm">
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <form class="mt-8 space-y-6" method="POST" action="/signup">
                <div class="rounded-md shadow-sm space-y-4">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700">Full name</label>
                        <input id="name" name="name" type="text" required class="appearance-none rounded relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" placeholder="Full name">
                    </div>
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                        <input id="email" name="email" type="email" required class="appearance-none rounded relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" placeholder="Email address">
                    </div>
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                        <input id="password" name="password" type="password" required class="appearance-none rounded relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" placeholder="Password">
                    </div>
                </div>

                <div>
                    <button type="submit" class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-teal-600 hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-teal-500">
                        Sign up
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
