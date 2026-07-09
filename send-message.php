<?php
/**
 * KARAN OLI PORTFOLIO — send-message.php
 * Handles contact form submissions and sends emails using PHP mail()
 * Returns JSON response with success/error status
 */

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

// ── Only allow POST requests ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

// ── Rate limiting using session ───────────────────────────────
session_start();

function checkRateLimit(): bool {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $key = 'contact_' . $ip;
    $window = 3600; // 1 hour
    $maxAttempts = 5;

    if (!isset($_SESSION['rate_limit'])) {
        $_SESSION['rate_limit'] = [];
    }

    if (!isset($_SESSION['rate_limit'][$key])) {
        $_SESSION['rate_limit'][$key] = ['count' => 1, 'first_attempt' => time()];
        return true;
    }

    $data = &$_SESSION['rate_limit'][$key];
    $timeDiff = time() - $data['first_attempt'];

    if ($timeDiff > $window) {
        $data = ['count' => 1, 'first_attempt' => time()];
        return true;
    }

    if ($data['count'] >= $maxAttempts) {
        return false;
    }

    $data['count']++;
    return true;
}

// ── Check rate limit ──────────────────────────────────────────
if (!checkRateLimit()) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'error' => 'Too many messages sent. Please wait an hour before sending again.'
    ]);
    exit;
}

// ── Sanitize and validate inputs ──────────────────────────────
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$subject = trim($_POST['subject'] ?? '');
$message = trim($_POST['message'] ?? '');

// ── Validation ─────────────────────────────────────────────────
$errors = [];

if (strlen($name) < 2) {
    $errors[] = 'Name must be at least 2 characters.';
}
if (strlen($name) > 100) {
    $errors[] = 'Name cannot exceed 100 characters.';
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Please enter a valid email address.';
}
if (strlen($email) > 100) {
    $errors[] = 'Email cannot exceed 100 characters.';
}

if (strlen($subject) < 2) {
    $errors[] = 'Subject must be at least 2 characters.';
}
if (strlen($subject) > 200) {
    $errors[] = 'Subject cannot exceed 200 characters.';
}

if (strlen($message) < 10) {
    $errors[] = 'Message must be at least 10 characters.';
}
if (strlen($message) > 5000) {
    $errors[] = 'Message cannot exceed 5000 characters.';
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => implode(' ', $errors)]);
    exit;
}

// ── Sanitize for email ────────────────────────────────────────
// Remove any newlines from headers to prevent header injection
$name = filter_var($name, FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
$subject = filter_var($subject, FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
$message = filter_var($message, FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);

// ── Email configuration ───────────────────────────────────────
$to = 'chhetrikaran.147@gmail.com'; // Recipient email
$from = 'noreply@karanoli.com'; // Sender email (change to your domain)

// ── Build email headers ──────────────────────────────────────
$headers = [
    'MIME-Version' => '1.0',
    'Content-Type' => 'text/html; charset=UTF-8',
    'From' => $from,
    'Reply-To' => $email,
    'X-Mailer' => 'PHP/' . phpversion(),
    'X-Contact-Form' => 'Portfolio Karan Oli'
];

// ── Build email body (HTML) ──────────────────────────────────
$emailBody = '
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #06b6d4; color: #fff; padding: 20px; border-radius: 8px 8px 0 0; }
        .content { background: #f9f9f9; padding: 20px; border-radius: 0 0 8px 8px; border: 1px solid #e0e0e0; border-top: none; }
        .field { margin-bottom: 15px; }
        .label { font-weight: bold; color: #555; }
        .value { background: #fff; padding: 10px; border-radius: 4px; border: 1px solid #e0e0e0; margin-top: 5px; }
        .footer { margin-top: 20px; padding-top: 20px; border-top: 1px solid #e0e0e0; font-size: 12px; color: #999; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h2>📬 New Contact Form Message</h2>
        <p>From your portfolio website</p>
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
            <div class="value" style="white-space: pre-wrap;">' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</div>
        </div>
        <div class="field">
            <div class="label">🌐 IP Address</div>
            <div class="value">' . htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? 'Unknown', ENT_QUOTES, 'UTF-8') . '</div>
        </div>
        <div class="field">
            <div class="label">📅 Sent</div>
            <div class="value">' . date('F j, Y \a\t g:i A') . '</div>
        </div>
    </div>
    <div class="footer">
        <p>This message was sent from your portfolio contact form.</p>
        <p>Reply directly to this email to respond to ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '.</p>
    </div>
</body>
</html>
';

// ── Build plain text alternative for email clients ──────────
$plainText = "New Contact Form Message\n";
$plainText .= "─────────────────────\n\n";
$plainText .= "Name: $name\n";
$plainText .= "Email: $email\n";
$plainText .= "Subject: $subject\n";
$plainText .= "Message:\n$message\n\n";
$plainText .= "─────────────────────\n";
$plainText .= "IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'Unknown') . "\n";
$plainText .= "Sent: " . date('F j, Y \a\t g:i A') . "\n";

// ── Send email with both HTML and plain text ─────────────────
$headersString = '';
foreach ($headers as $key => $value) {
    $headersString .= "$key: $value\r\n";
}

// Create a multipart/alternative message
$boundary = md5(time());
$headersString .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";

$emailBody = "
--$boundary
Content-Type: text/plain; charset=UTF-8
Content-Transfer-Encoding: 7bit

$plainText

--$boundary
Content-Type: text/html; charset=UTF-8
Content-Transfer-Encoding: 7bit

$emailBody
--$boundary--
";

// ── Send email ─────────────────────────────────────────────────
$mailSent = mail($to, "Portfolio Contact: $subject", $emailBody, $headersString);

// ── Log the attempt ────────────────────────────────────────────
$logEntry = sprintf(
    "[%s] %s | From: %s (%s) | Subject: %s | %s\n",
    date('Y-m-d H:i:s'),
    $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
    $email,
    $name,
    $subject,
    $mailSent ? '✅ Sent' : '❌ Failed'
);
file_put_contents(__DIR__ . '/contact_log.txt', $logEntry, FILE_APPEND);

// ── Return response ────────────────────────────────────────────
if ($mailSent) {
    echo json_encode([
        'success' => true,
        'message' => 'Your message has been sent successfully! I\'ll get back to you within 24 hours. 🎉'
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to send message. Please try again later or email me directly at chhetrikaran.147@gmail.com'
    ]);
    exit;
}