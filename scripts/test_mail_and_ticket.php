<?php
declare(strict_types=1);
// Usage: php scripts/test_mail_and_ticket.php you@example.com
$to = $argv[1] ?? ''; if (!$to) { echo "Usage: php scripts/test_mail_and_ticket.php you@example.com\n"; exit(1); }
require_once __DIR__ . '/../mailer.php';
require_once __DIR__ . '/../lib/tickets.php';

$info = [
    'ticket_id' => 'TKT-000123',
    'name' => 'Test User',
    'trip_title' => 'Demo Trip',
    'destination' => 'Arusha',
    'date' => date('F j, Y', strtotime('+14 days')),
    'package' => 'Day Pass',
    'order' => 'TEST' . date('YmdHis'),
];

echo "Generating PDF ticket...\n";
$pdf = generate_ticket_pdf($info);
if (!$pdf) { echo "PDF generation failed.\n"; exit(2); }

echo "Attempting to send test email to {$to}...\n";
$subject = 'Test ticket from Royal Family TZ';
$body = "This is a test ticket email. If you receive the PDF attachment, mailing works.\n\nOrder: {$info['order']}\n";
$sent = send_email($to, $subject, $body, [['name'=>'test-ticket.pdf','type'=>'application/pdf','data'=>$pdf]]);

if ($sent) echo "Email sent successfully to {$to}.\n"; else echo "Email failed to send. Check mail configuration.\n";
