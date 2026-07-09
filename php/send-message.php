<?php
/**
 * KARAN OLI PORTFOLIO - Contact Form Handler
 * Clean version with no extra output
 */

// Turn off all error reporting to prevent JSON corruption
error_reporting(0);
ini_set('display_errors', 0);

// Set JSON header immediately
header('Content-Type: application/json; charset=utf-8');

// Start session for rate limiting
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

// Rate limiting - 5 messages per hour
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$rateKey = 'contact_' . md5($ip);

if (!isset($_SESSION['rate_limit'])) {
    $_SESSION['rate_limit'] = [];
}

$now = time();

if (!isset($_SESSION['rate_limit'][$rateKey])) {
    $_SESSION['rate_limit'][$rateKey] = ['count' => 1, 'first_attempt' => $now];
} else {
    $timeDiff = $now - $_SESSION['rate_limit'][$rateKey]['first_attempt'];
    if ($timeDiff > 3600) {
        $_SESSION['rate_limit'][$rateKey] = ['count' => 1, 'first_attempt' => $now];
    } elseif ($_SESSION['rate_limit'][$rateKey]['count'] >= 5) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error' => 'Too many messages. Please wait an hour.'
        ]);
        exit;
    } else {
        $_SESSION['rate_limit'][$rateKey]['count']++;
    }
}

// Get and sanitize inputs
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$subject = trim($_POST['subject'] ?? '');
$message = trim($_POST['message'] ?? '');

// Sanitize
$name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
$email = filter_var($email, FILTER_SANITIZE_EMAIL);
$subject = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');
$message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

// Validate
$errors = [];

if (strlen($name) < 2) {
    $errors[] = 'Name must be at least 2 characters.';
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Please enter a valid email address.';
}
if (strlen($subject) < 2) {
    $errors[] = 'Subject must be at least 2 characters.';
}
if (strlen($message) < 10) {
    $errors[] = 'Message must be at least 10 characters.';
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => implode(' ', $errors)]);
    exit;
}

// Send email
$to = 'chhetrikaran.147@gmail.com';
$from = 'noreply@' . $_SERVER['HTTP_HOST'];
$headers = "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/html; charset=UTF-8\r\n";
$headers .= "From: $from\r\n";
$headers .= "Reply-To: $email\r\n";

// Email body
$body = "
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #06b6d4; color: white; padding: 20px; border-radius: 8px 8px 0 0; }
        .content { background: #f9f9f9; padding: 20px; border: 1px solid #ddd; border-top: none; border-radius: 0 0 8px 8px; }
        .field { margin-bottom: 15px; }
        .label { font-weight: bold; color: #555; }
        .value { background: white; padding: 10px; border-radius: 4px; border: 1px solid #ddd; margin-top: 5px; }
        .footer { margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 12px; color: #999; text-align: center; }
    </style>
</head>
<body>
    <div class='header'>
        <h2>📬 New Contact Form Message</h2>
        <p>From Karan Oli Portfolio</p>
    </div>
    <div class='content'>
        <div class='field'>
            <div class='label'>Name</div>
            <div class='value'>$name</div>
        </div>
        <div class='field'>
            <div class='label'>Email</div>
            <div class='value'><a href='mailto:$email'>$email</a></div>
        </div>
        <div class='field'>
            <div class='label'>Subject</div>
            <div class='value'>$subject</div>
        </div>
        <div class='field'>
            <div class='label'>Message</div>
            <div class='value' style='white-space: pre-wrap;'>$message</div>
        </div>
        <div class='field'>
            <div class='label'>IP Address</div>
            <div class='value'>" . ($_SERVER['REMOTE_ADDR'] ?? 'Unknown') . "</div>
        </div>
        <div class='field'>
            <div class='label'>Sent</div>
            <div class='value'>" . date('F j, Y g:i A') . "</div>
        </div>
    </div>
    <div class='footer'>
        <p>This message was sent from your portfolio contact form.</p>
    </div>
</body>
</html>
";

// Try to send
$success = mail($to, "Portfolio Contact: $subject", $body, $headers);

// Log the attempt (optional)
try {
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    $logFile = $logDir . '/contact.log';
    $logEntry = date('Y-m-d H:i:s') . " | " . ($success ? '✅' : '❌') . " | $email | $subject\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
} catch (Exception $e) {
    // Silently fail logging
}

// Return response
if ($success) {
    echo json_encode([
        'success' => true,
        'message' => '✅ Message sent successfully! I\'ll get back to you within 24 hours.'
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to send message. Please email me directly at chhetrikaran.147@gmail.com'
    ]);
}