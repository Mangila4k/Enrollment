<?php
// Simple QR code generator using Google Charts API (no external library needed)
$data = isset($_GET['data']) ? $_GET['data'] : '';

if(empty($data)) {
    $data = 'https://example.com';
}

// URL encode the data
$data = urlencode($data);

// Use Google Chart API to generate QR code
$qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=" . $data;

// Redirect to the QR code image
header('Content-Type: image/png');
readfile($qr_url);
?>