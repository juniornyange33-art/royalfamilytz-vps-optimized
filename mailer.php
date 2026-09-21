<?php
// Load local configuration
$config = require __DIR__ . '/local-config.php';

/**
 * Send an email via Resend API
 *
 * @param string|array $to Recipient email or array of emails
 * @param string $subject Email subject line
 * @param string $htmlBody HTML content of the email
 * @param array $attachments Optional array of attachments (e.g., PDF trip tickets)
 * @return bool True on success, False on failure
 */
function sendResendEmail($to, $subject, $htmlBody, $attachments = []) {
    global $config;

    $apiKey = $config['RESEND_API_KEY'];
    $from = $config['MAIL_FROM'];

    $payload = [
        'from' => $from,
        'to' => is_array($to) ? array_values($to) : [$to],
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

    if ($httpCode === 200 || $httpCode === 201) {
        return true;
    }

    error_log("Resend API Failed [HTTP {$httpCode}]: " . $response);
    return false;
}

/**
 * Handle "Contact Us" notifications sent to your Gmail inbox
 */
function handleContactSubmission($senderName, $senderEmail, $message, $phone = 'N/A') {
    global $config;

    $subject = "New Contact Inquiry from " . htmlspecialchars($senderName);
    $body = "
        <h2>New Message Received</h2>
        <p><strong>Name:</strong> " . htmlspecialchars($senderName) . "</p>
        <p><strong>Email:</strong> " . htmlspecialchars($senderEmail) . "</p>
        <p><strong>Phone:</strong> " . htmlspecialchars($phone) . "</p>
        <p><strong>Message:</strong><br>" . nl2br(htmlspecialchars($message)) . "</p>
    ";

    return sendResendEmail($config['ADMIN_EMAIL'], $subject, $body);
}

/**
 * Handle Trip Ticket Email Delivery after successful payment
 */
function sendTripTicketEmail($customerEmail, $customerName, $tripDetails, $pdfAttachmentBase64 = null) {
    $subject = "Your Trip Ticket Confirmation - Royal Family TZ";
    $body = "
        <h2>Trip Confirmation & Ticket</h2>
        <p>Hello " . htmlspecialchars($customerName) . ",</p>
        <p>Thank you for your payment! Your booking for <strong>" . htmlspecialchars($tripDetails['title']) . "</strong> has been confirmed.</p>
        <p><strong>Reference Code:</strong> " . htmlspecialchars($tripDetails['reference']) . "</p>
        <p><strong>Date & Time:</strong> " . htmlspecialchars($tripDetails['date']) . "</p>
        <p>Please find your official trip ticket attached below or in your dashboard.</p>
    ";

    $attachments = [];
    if ($pdfAttachmentBase64) {
        $attachments[] = [
            'filename' => 'Trip_Ticket_' . $tripDetails['reference'] . '.pdf',
            'content' => $pdfAttachmentBase64
        ];
    }

    return sendResendEmail($customerEmail, $subject, $body, $attachments);
}

