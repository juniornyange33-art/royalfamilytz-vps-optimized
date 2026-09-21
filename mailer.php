<?php
// Safely load local-config.php ONLY if it exists
$config = [];
if (file_exists(__DIR__ . '/local-config.php')) {
    $config = require __DIR__ . '/local-config.php';
}
/**
 * Send an email via Resend API
 */
function sendResendEmail($to, $subject, $htmlBody, $attachments = []) {
    global $config;

    // Retrieve credentials
    $apiKey = getenv('RESEND_API_KEY') ?: ($config['RESEND_API_KEY'] ?? '');
    
    // MUST use verified domain for sending (Resend requirement)
    $from = 'Royal Family TZ <support@royalfamilytz.org>'; 

    if (empty($apiKey)) {
        error_log("Resend API Key is missing.");
        return false;
    }

    $payload = [
        'from' => $from,
        'to' => is_array($to) ? array_values($to) : [$to],
        'reply_to' => 'royalfamilytz.org@gmail.com', // Replies go directly to your Gmail!
        'subject' => $subject,
        'html' => $htmlBody
    ];

    if (!empty($attachments)) {
        $payload['attachments'] = $attachments;
    }

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ],
        CURLOPT_TIMEOUT => 15
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        error_log("Resend cURL Error: " . $curlError);
        return false;
    }

    return ($httpCode === 200 || $httpCode === 201);
}

/**
 * Route "Contact Us" submissions directly to your Gmail inbox
 */
function handleContactSubmission($senderName, $senderEmail, $message, $phone = 'N/A') {
    $subject = "New Contact Inquiry from " . htmlspecialchars($senderName);
    $body = "
        <h2>New Inquiry Received</h2>
        <p><strong>Name:</strong> " . htmlspecialchars($senderName) . "</p>
        <p><strong>Email:</strong> " . htmlspecialchars($senderEmail) . "</p>
        <p><strong>Phone:</strong> " . htmlspecialchars($phone) . "</p>
        <p><strong>Message:</strong><br>" . nl2br(htmlspecialchars($message)) . "</p>
    ";

    // Deliver straight to your personal Gmail inbox
    return sendResendEmail('royalfamilytz.org@gmail.com', $subject, $body);
}
