<?php
header('Content-Type: application/json');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/config.php';
require __DIR__ . '/../vendor/autoload.php'; 

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$name    = htmlspecialchars(trim($_POST['name'] ?? ''));
$email   = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
$subject = htmlspecialchars(trim($_POST['subject'] ?? ''));
$message = htmlspecialchars(trim($_POST['message'] ?? ''));

if (strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($message) < 10) {
    echo json_encode(['success' => false, 'error' => 'Please validate your inputs.']);
    exit;
}

try {
    $pdo = get_pdo();
    
    // 1. Save to Database
    $stmt = $pdo->prepare("INSERT INTO contacts (name, email, subject, message, ip_address) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$name, $email, $subject, $message, $_SERVER['REMOTE_ADDR']]);

    // 2. Send via SMTP
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'chhetrikaran.147@gmail.com'; 
    $mail->Password   = 'utlxvaiovxfxohaf'; // Your App Password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    $mail->setFrom('chhetrikaran.147@gmail.com', 'Karan Portfolio');
    $mail->addAddress(OWNER_EMAIL); 
    $mail->addReplyTo($email, $name);

    $mail->isHTML(true);
    $mail->Subject = "Contact Form: $subject";
    $mail->Body = "<h2>New Message</h2><p><b>From:</b> $name ($email)</p><p><b>Message:</b><br>$message</p>";

    $mail->send();
    echo json_encode(['success' => true, 'message' => '✅ Message sent successfully!']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Mail Error: ' . $mail->ErrorInfo]);
}