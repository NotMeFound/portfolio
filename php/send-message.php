<?php
/**
 * KARAN OLI PORTFOLIO — send-message.php
 * Production-ready contact form handler with PHPMailer support
 * Returns JSON response with success/error status
 */

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

// ── Configuration ───────────────────────────────────────────────
define('CONTACT_EMAIL', 'chhetrikaran.147@gmail.com');
define('SITE_NAME', 'Karan Oli Portfolio');
define('MAILER_TYPE', 'php'); // 'php' for mail(), 'phpmailer' for PHPMailer

// ── Rate limiting configuration ────────────────────────────────
define('RATE_LIMIT_MAX', 5);
define('RATE_LIMIT_WINDOW', 3600); // 1 hour in seconds

// ── Only allow POST requests ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

// ── Start session for rate limiting ──────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Rate limiting function ─────────────────────────────────────
function checkRateLimit(): bool {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $key = 'contact_' . md5($ip);

    if (!isset($_SESSION['rate_limit'])) {
        $_SESSION['rate_limit'] = [];
    }

    $now = time();

    if (!isset($_SESSION['rate_limit'][$key])) {
        $_SESSION['rate_limit'][$key] = ['count' => 1, 'first_attempt' => $now];
        return true;
    }

    $data = &$_SESSION['rate_limit'][$key];
    $timeDiff = $now - $data['first_attempt'];

    if ($timeDiff > RATE_LIMIT_WINDOW) {
        $data = ['count' => 1, 'first_attempt' => $now];
        return true;
    }

    if ($data['count'] >= RATE_LIMIT_MAX) {
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
        'error' => sprintf(
            'Too many messages sent. Please wait %d minutes before sending again.',
            ceil(RATE_LIMIT_WINDOW / 60)
        )
    ]);
    exit;
}

// ── Get and sanitize inputs ──────────────────────────────────
$name = trim(htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES, 'UTF-8'));
$email = trim(filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL));
$subject = trim(htmlspecialchars($_POST['subject'] ?? '', ENT_QUOTES, 'UTF-8'));
$message = trim(htmlspecialchars($_POST['message'] ?? '', ENT_QUOTES, 'UTF-8'));

// ── Validation ─────────────────────────────────────────────────
$errors = [];

if (strlen($name) < 2 || strlen($name) > 100) {
    $errors[] = 'Name must be between 2 and 100 characters.';
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
    $errors[] = 'Please enter a valid email address.';
}

if (strlen($subject) < 2 || strlen($subject) > 200) {
    $errors[] = 'Subject must be between 2 and 200 characters.';
}

if (strlen($message) < 10 || strlen($message) > 5000) {
    $errors[] = 'Message must be between 10 and 5000 characters.';
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => implode(' ', $errors)]);
    exit;
}

// ── DEFINE LOG FUNCTION FIRST ──────────────────────────────────
function logAttempt(bool $success, string $error = ''): void {
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $logFile = $logDir . '/contact_' . date('Y-m-d') . '.log';
    $entry = sprintf(
        "[%s] %s | IP: %s | From: %s (%s) | Subject: %s | %s%s\n",
        date('Y-m-d H:i:s'),
        $success ? '✅' : '❌',
        $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
        $_POST['email'] ?? 'Unknown',
        $_POST['name'] ?? 'Unknown',
        $_POST['subject'] ?? 'No Subject',
        $success ? 'Sent' : 'Failed',
        $error ? ' - ' . $error : ''
    );
    file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
}

// ── Send email using PHP mail() ──────────────────────────────
function sendWithPhpMail(string $to, string $from, string $name, string $email, string $subject, string $message): bool {
    // Build email headers
    $headers = [
        'MIME-Version' => '1.0',
        'Content-Type' => 'text/html; charset=UTF-8',
        'From' => $from,
        'Reply-To' => $email,
        'X-Mailer' => 'PHP/' . phpversion(),
        'X-Contact-Form' => SITE_NAME
    ];

    // Build HTML email body
    $htmlBody = '
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; line-height: 1.6; color: #1a202c; max-width: 600px; margin: 0 auto; padding: 20px; background: #f7fafc; }
        .container { background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #06b6d4, #0891b2); color: #fff; padding: 30px; text-align: center; }
        .header h2 { margin: 0; font-size: 24px; }
        .header p { margin: 8px 0 0; opacity: 0.9; }
        .content { padding: 30px; }
        .field { margin-bottom: 20px; }
        .label { font-weight: 600; color: #2d3748; font-size: 14px; text-transform: uppercase; letter-spacing: 0.05em; }
        .value { background: #f7fafc; padding: 12px 16px; border-radius: 8px; margin-top: 6px; border: 1px solid #e2e8f0; word-wrap: break-word; }
        .value pre { margin: 0; white-space: pre-wrap; font-family: inherit; }
        .footer { background: #f7fafc; padding: 20px 30px; text-align: center; font-size: 13px; color: #718096; border-top: 1px solid #e2e8f0; }
        .footer a { color: #06b6d4; text-decoration: none; }
        .badge { display: inline-block; background: #ebf8ff; color: #2b6cb0; padding: 4px 12px; border-radius: 9999px; font-size: 12px; font-weight: 600; margin-top: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>📬 New Contact Form Message</h2>
            <p>From ' . htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8') . '</p>
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
                <div class="value"><pre>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</pre></div>
            </div>
            <div class="field">
                <div class="label">🌐 IP Address</div>
                <div class="value">' . htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? 'Unknown', ENT_QUOTES, 'UTF-8') . ' <span class="badge">Logged for security</span></div>
            </div>
            <div class="field">
                <div class="label">📅 Sent</div>
                <div class="value">' . date('F j, Y \a\t g:i A T') . '</div>
            </div>
        </div>
        <div class="footer">
            <p>This message was sent from your portfolio contact form.</p>
            <p>Reply directly to this email to respond to ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '.</p>
            <p style="margin-top: 12px; font-size: 12px; color: #a0aec0;">📧 ' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '</p>
        </div>
    </div>
</body>
</html>
';

    // Build plain text alternative
    $plainText = "New Contact Form Message\n";
    $plainText .= str_repeat('─', 40) . "\n\n";
    $plainText .= "Name: $name\n";
    $plainText .= "Email: $email\n";
    $plainText .= "Subject: $subject\n\n";
    $plainText .= "Message:\n$message\n\n";
    $plainText .= str_repeat('─', 40) . "\n";
    $plainText .= "IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'Unknown') . "\n";
    $plainText .= "Sent: " . date('F j, Y \a\t g:i A T') . "\n";

    // Create multipart/alternative message
    $boundary = md5(uniqid(time(), true));
    $headersString = '';
    foreach ($headers as $key => $value) {
        $headersString .= "$key: $value\r\n";
    }
    $headersString .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";

    $fullBody = "
--$boundary
Content-Type: text/plain; charset=UTF-8
Content-Transfer-Encoding: 7bit

$plainText

--$boundary
Content-Type: text/html; charset=UTF-8
Content-Transfer-Encoding: 7bit

$htmlBody
--$boundary--
";

    return mail($to, "Portfolio Contact: $subject", $fullBody, $headersString);
}

// ── Send email using PHPMailer (optional) ─────────────────────
function sendWithPHPMailer(string $to, string $from, string $name, string $email, string $subject, string $message): bool {
    // Load PHPMailer if available
    if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
        require_once __DIR__ . '/../vendor/autoload.php';
    } else {
        error_log('PHPMailer not found. Please run: composer require phpmailer/phpmailer');
        return false;
    }
    
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'your-email@gmail.com';
        $mail->Password   = 'your-app-password';
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Recipients
        $mail->setFrom($from, SITE_NAME);
        $mail->addAddress($to);
        $mail->addReplyTo($email, $name);

        // Content
        $mail->isHTML(true);
        $mail->Subject = "Portfolio Contact: $subject";
        $mail->Body = "
            <h2>New Contact Form Message</h2>
            <p><strong>Name:</strong> " . htmlspecialchars($name) . "</p>
            <p><strong>Email:</strong> <a href='mailto:" . htmlspecialchars($email) . "'>" . htmlspecialchars($email) . "</a></p>
            <p><strong>Subject:</strong> " . htmlspecialchars($subject) . "</p>
            <p><strong>Message:</strong></p>
            <p>" . nl2br(htmlspecialchars($message)) . "</p>
            <hr>
            <p style='color: #666; font-size: 12px;'>Sent from " . SITE_NAME . " contact form</p>
        ";
        $mail->AltBody = "Name: $name\nEmail: $email\nSubject: $subject\n\nMessage:\n$message";

        return $mail->send();
    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        return false;
    }
}

// ── Choose mailer and send ──────────────────────────────────
$fromEmail = 'noreply@' . $_SERVER['HTTP_HOST'];
$mailSent = false;
$errorMessage = '';

try {
    if (MAILER_TYPE === 'phpmailer' && class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        $mailSent = sendWithPHPMailer(
            CONTACT_EMAIL,
            $fromEmail,
            $name,
            $email,
            $subject,
            $message
        );
    } else {
        $mailSent = sendWithPhpMail(
            CONTACT_EMAIL,
            $fromEmail,
            $name,
            $email,
            $subject,
            $message
        );
    }
} catch (Exception $e) {
    $errorMessage = $e->getMessage();
    $mailSent = false;
}

// ── Log result ─────────────────────────────────────────────────
logAttempt($mailSent, $mailSent ? '' : $errorMessage);

// ── Return response ────────────────────────────────────────────
if ($mailSent) {
    echo json_encode([
        'success' => true,
        'message' => '✅ Message sent successfully! I\'ll get back to you within 24 hours.'
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to send message. Please try again later or email me directly at ' . CONTACT_EMAIL
    ]);
}