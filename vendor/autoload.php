<?php
// vendor/autoload.php - Manual autoloader for PHPMailer

// Define path to PHPMailer
$phpmailer_path = __DIR__ . '/phpmailer/phpmailer/src/';

// Check if PHPMailer exists
if (!file_exists($phpmailer_path . 'PHPMailer.php')) {
    die('PHPMailer not found. Please download it from https://github.com/PHPMailer/PHPMailer');
}

// Require PHPMailer files
require_once $phpmailer_path . 'PHPMailer.php';
require_once $phpmailer_path . 'SMTP.php';
require_once $phpmailer_path . 'Exception.php';

// Use PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
?>