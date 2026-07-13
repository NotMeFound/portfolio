<?php
/**
 * KARAN OLI PORTFOLIO - Full SMTP Contact Form Handler
 */

// 1. Setup Headers for JSON response
header('Content-Type: application/json; charset=utf-8');

// 2. Load PHPMailer (Make sure you have the 'vendor' folder or the PHPMailer files)
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Adjust this path based on where your vendor/autoload.php is located
require __DIR__ . '/../vendor/autoload.php'; 

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

// 3. Sanitize Inputs
$name    = htmlspecialchars(trim($_POST['name'] ?? ''), ENT_QUOTES, 'UTF-8');
$email   = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
$subject = htmlspecialchars(trim($_POST['subject'] ?? ''), ENT_QUOTES, 'UTF-8');
$message = htmlspecialchars(trim($_POST['message'] ?? ''), ENT_QUOTES, 'UTF-8');

// 4. Validation
if (strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($message) < 10) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please fill all fields correctly.']);
    exit;
}

// 5. SMTP Configuration & Sending
$mail = new PHPMailer(true);

try {
    // Server settings
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'chhetrikaran.147@gmail.com'; // Your Gmail
    $mail->Password   = 'utlxvaiovxfxohaf';           // Your App Password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    // Recipients
    $mail->setFrom('chhetrikaran.147@gmail.com', 'Portfolio Contact');
    $mail->addAddress('chhetrikaran.147@gmail.com'); // Where you want to receive emails
    $mail->addReplyTo($email, $name);                // Allows you to hit 'Reply' to the user

    // Content
    $mail->isHTML(true);
    $mail->Subject = "New Portfolio Message: $subject";
    
    $mail->Body = "
    <div style='font-family: sans-serif; border: 1px solid #eee; padding: 20px;'>
        <h2 style='color: #0891b2;'>New Contact Form Entry</h2>
        <p><strong>Name:</strong> {$name}</p>
        <p><strong>Email:</strong> {$email}</p>
        <p><strong>Subject:</strong> {$subject}</p>
        <p><strong>Message:</strong></p>
        <div style='background: #f4f4f4; padding: 15px;'>".nl2br($message)."</div>
    </div>";

    $mail->send();

    echo json_encode([
        'success' => true,
        'message' => '✅ Message sent successfully! I will get back to you soon.'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => "Mailer Error: {$mail->ErrorInfo}"
    ]);
}