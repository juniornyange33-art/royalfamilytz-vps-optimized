<?php
declare(strict_types=1);

function generate_ticket_png(array $info): string {
    $w = 820; $h = 380;
    $img = imagecreatetruecolor($w, $h);
    $bg = imagecolorallocate($img, 255, 255, 255);
    $accent = imagecolorallocate($img, 40, 87, 67);
    $muted = imagecolorallocate($img, 102, 115, 108);
    $black = imagecolorallocate($img, 20, 20, 20);
    imagefilledrectangle($img, 0, 0, $w, $h, $bg);

    // Left panel
    imagefilledrectangle($img, 20, 20, $w-220, $h-20, imagecolorallocate($img, 245, 248, 246));
    // Title
    imagestring($img, 5, 40, 30, 'Royal Family TZ - Trip Ticket', $accent);
    imagestring($img, 4, 40, 72, $info['trip_title'] ?? 'Trip', $black);
    imagestring($img, 3, 40, 110, 'Passenger: ' . ($info['name'] ?? 'Guest'), $black);
    imagestring($img, 3, 40, 138, 'Ticket: ' . ($info['ticket_id'] ?? ''), $black);
    imagestring($img, 3, 40, 166, 'Package: ' . ($info['package'] ?? ''), $muted);
    imagestring($img, 3, 40, 190, 'Destination: ' . ($info['destination'] ?? ''), $muted);
    imagestring($img, 3, 40, 214, 'Date: ' . ($info['date'] ?? ''), $muted);
    imagestring($img, 2, 40, $h-60, 'Order Ref: ' . ($info['order'] ?? ''), $muted);

    // QR (attempt to fetch from Google Charts)
    $qrData = 'TICKET|' . ($info['ticket_id'] ?? '') . '|' . ($info['order'] ?? '');
    $qrUrl = 'https://chart.googleapis.com/chart?chs=200x200&cht=qr&chl=' . rawurlencode($qrData);
    $qrRaw = @file_get_contents($qrUrl);
    if ($qrRaw !== false) {
        $qrImg = @imagecreatefromstring($qrRaw);
        if ($qrImg) imagecopyresampled($img, $qrImg, $w-190, 80, 0, 0, 160, 160, imagesx($qrImg), imagesy($qrImg));
        if (isset($qrImg) && is_resource($qrImg)) imagedestroy($qrImg);
    } else {
        // placeholder box
        imagerectangle($img, $w-200, 80, $w-40, 240, $muted);
        imagestring($img, 3, $w-180, 150, 'QR Unavailable', $muted);
    }

    ob_start(); imagepng($img); $data = ob_get_clean(); imagedestroy($img);
    return $data;
}

function generate_ticket_pdf(array $info): string {
    // If Imagick is available, render PNG then convert to PDF for higher fidelity
    if (class_exists('Imagick')) {
        try {
            $png = generate_ticket_png($info);
            $im = new Imagick();
            $im->readImageBlob($png);
            $im->setImageFormat('pdf');
            $pdf = $im->getImageBlob();
            $im->clear(); $im->destroy();
            return $pdf;
        } catch (Throwable $e) {
            // fallback to text PDF
        }
    }
    // Minimal single-page PDF generator with text layout (no images).
    $lines = [];
    $lines[] = 'ROYAL FAMILY TZ - TRIP TICKET';
    $lines[] = 'Trip: ' . ($info['trip_title'] ?? '');
    $lines[] = 'Passenger: ' . ($info['name'] ?? '');
    $lines[] = 'Ticket: ' . ($info['ticket_id'] ?? '');
    $lines[] = 'Package: ' . ($info['package'] ?? '');
    $lines[] = 'Destination: ' . ($info['destination'] ?? '');
    $lines[] = 'Date: ' . ($info['date'] ?? '');
    $lines[] = 'Order Ref: ' . ($info['order'] ?? '');

    $content = "BT /F1 18 Tf 50 740 Td (" . pdf_escape($lines[0]) . ") Tj ET\n";
    $y = 720;
    foreach (array_slice($lines, 1) as $i => $ln) {
        $content .= "BT /F1 12 Tf 50 {$y} Td (" . pdf_escape($ln) . ") Tj ET\n";
        $y -= 18;
    }

    // Build PDF objects
    $objs = [];
    $objs[] = "%PDF-1.4";
    $objData = [];
    // 1 - Catalog
    $objData[] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj";
    // 2 - Pages
    $objData[] = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj";
    // 3 - Page
    $objData[] = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj";
    // 4 - Font
    $objData[] = "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj";
    // 5 - Content stream
    $stream = $content;
    $len = strlen($stream);
    $objData[] = "5 0 obj\n<< /Length {$len} >>\nstream\n{$stream}\nendstream\nendobj";

    $pdf = implode("\n", $objs) . "\n";
    $offsets = [];
    $pos = strlen($pdf);
    foreach ($objData as $o) {
        $offsets[] = $pos;
        $pdf .= $o . "\n";
        $pos = strlen($pdf);
    }

    $xrefPos = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objData) + 1) . "\n0000000000 65535 f \n";
    foreach ($offsets as $off) {
        $pdf .= str_pad($off, 10, '0', STR_PAD_LEFT) . " 00000 n \n";
    }
    $pdf .= "trailer\n<< /Size " . (count($objData) + 1) . " /Root 1 0 R >>\nstartxref\n{$xrefPos}\n%%EOF\n";
    return $pdf;
}

function pdf_escape(string $s): string { return str_replace(['\\','(',')'], ['\\\\','\\(','\\)'], $s); }
