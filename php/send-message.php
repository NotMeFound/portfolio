<?php
/**
 * KARAN OLI PORTFOLIO - Contact Form Handler
 * Using PHPMailer for email delivery
 */

error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Rate limiting
$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
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

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$subject = trim($_POST['subject'] ?? '');
$message = trim($_POST['message'] ?? '');

$name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
$email = filter_var($email, FILTER_SANITIZE_EMAIL);
$subject = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');
$message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

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

function logAttempt($success, $error = '') {
    try {
        $logDir = __DIR__ . '/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $logFile = $logDir . '/contact_' . date('Y-m-d') . '.log';
        $entry = sprintf(
            "[%s] %s | IP: %s | From: %s | Subject: %s | %s%s\n",
            date('Y-m-d H:i:s'),
            $success ? '✅' : '❌',
            $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
            $_POST['email'] ?? 'Unknown',
            $_POST['subject'] ?? 'No Subject',
            $success ? 'Sent' : 'Failed',
            $error ? ' - ' . $error : ''
        );
        file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
    } catch (Exception $e) {
        // Silently fail
    }
}

function sendWithPHPMailer($name, $email, $subject, $message) {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'chhetrikaran.147@gmail.com';
        $mail->Password   = 'utlxvaiovxfxohaf'; // ← YOUR APP PASSWORD IS HERE
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('chhetrikaran.147@gmail.com', 'Karan Oli Portfolio');
        $mail->addAddress('chhetrikaran.147@gmail.com', 'Karan Oli');
        $mail->addReplyTo($email, $name);

        $mail->isHTML(true);
        $mail->Subject = "Portfolio Contact: " . $subject;

        $htmlBody = '
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background: #f7fafc; }
        .container { background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #06b6d4, #0891b2); color: #fff; padding: 30px; text-align: center; }
        .header h2 { margin: 0; font-size: 24px; }
        .content { padding: 30px; }
        .field { margin-bottom: 20px; }
        .label { font-weight: 600; color: #2d3748; font-size: 14px; text-transform: uppercase; letter-spacing: 0.05em; }
        .value { background: #f7fafc; padding: 12px 16px; border-radius: 8px; margin-top: 6px; border: 1px solid #e2e8f0; }
        .footer { background: #f7fafc; padding: 20px 30px; text-align: center; font-size: 13px; color: #718096; border-top: 1px solid #e2e8f0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>📬 New Contact Form Message</h2>
            <p>From Karan Oli Portfolio</p>
        </div>
        <div class="content">
            <div class="field">
                <div class="label">👤 Name</div>
                <div class="value">' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</div>
            </div>
            <div class="field">
                <div class="label">📧 Email</div>
                <div class="value"><a href="mailto:' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '</a></div>
            </div>
            <div class="field">
                <div class="label">📝 Subject</div>
                <div class="value">' . htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') . '</div>
            </div>
            <div class="field">
                <div class="label">💬 Message</div>
                <div class="value"><pre style="white-space:pre-wrap;">' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</pre></div>
            </div>
            <div class="field">
                <div class="label">🌐 IP Address</div>
                <div class="value">' . htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? 'Unknown', ENT_QUOTES, 'UTF-8') . '</div>
            </div>
            <div class="field">
                <div class="label">📅 Sent</div>
                <div class="value">' . date('F j, Y \a\t g:i A T') . '</div>
            </div>
        </div>
        <div class="footer">
            <p>This message was sent from your portfolio contact form.</p>
            <p>Reply directly to this email to respond to ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '.</p>
        </div>
    </div>
</body>
</html>';

        $plainText = "New Contact Form Message\n";
        $plainText .= str_repeat('─', 40) . "\n\n";
        $plainText .= "Name: $name\n";
        $plainText .= "Email: $email\n";
        $plainText .= "Subject: $subject\n\n";
        $plainText .= "Message:\n$message\n\n";
        $plainText .= "IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'Unknown') . "\n";
        $plainText .= "Sent: " . date('F j, Y \a\t g:i A T') . "\n";

        $mail->Body = $htmlBody;
        $mail->AltBody = $plainText;

        return $mail->send();
    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        return false;
    }
}

$mailSent = false;
$errorMessage = '';

try {
    $mailSent = sendWithPHPMailer($name, $email, $subject, $message);
} catch (Exception $e) {
    $errorMessage = $e->getMessage();
    $mailSent = false;
}

logAttempt($mailSent, $mailSent ? '' : $errorMessage);

if ($mailSent) {
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