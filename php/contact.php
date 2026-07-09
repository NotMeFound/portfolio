<?php
/**
 * KARAN OLI PORTFOLIO — contact.php
 * Secure contact form handler with CSRF, rate limiting, and email notification
 */

declare(strict_types=1);

// Set security headers
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

// Load config
require_once __DIR__ . '/config.php';

// Start session for CSRF and rate limiting
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================
// CSRF PROTECTION
// ============================================
function verify_csrf_token($token): bool {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Security validation failed.']);
    exit;
}

// ============================================
// RATE LIMITING
// ============================================
function check_rate_limit($key, $max_attempts = 3, $time_window = 3600): bool {
    if (!isset($_SESSION['rate_limit'])) {
        $_SESSION['rate_limit'] = [];
    }
    
    $ip = get_client_ip();
    $key = $key . '_' . $ip;
    
    if (!isset($_SESSION['rate_limit'][$key])) {
        $_SESSION['rate_limit'][$key] = ['count' => 1, 'first_attempt' => time()];
        return true;
    }
    
    $data = &$_SESSION['rate_limit'][$key];
    $time_diff = time() - $data['first_attempt'];
    
    if ($time_diff > $time_window) {
        $data = ['count' => 1, 'first_attempt' => time()];
        return true;
    }
    
    if ($data['count'] >= $max_attempts) {
        return false;
    }
    
    $data['count']++;
    return true;
}

if (!check_rate_limit('contact', MAX_CONTACT_ATTEMPTS, CONTACT_WINDOW)) {
    http_response_code(429);
    echo json_encode([
        'success' => false, 
        'error' => 'Too many submissions. Please wait ' . ceil(CONTACT_WINDOW/60) . ' minutes.'
    ]);
    exit;
}

// ============================================
// INPUT VALIDATION & SANITIZATION
// ============================================
function sanitize_string(string $input): string {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

$name = sanitize_string($_POST['name'] ?? '');
$email = sanitize_string($_POST['email'] ?? '');
$subject = sanitize_string($_POST['subject'] ?? '');
$message = sanitize_string($_POST['message'] ?? '');
$ip = get_client_ip();
$user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

// Validate inputs
$errors = [];

if (strlen($name) < 2 || strlen($name) > 100) {
    $errors[] = 'Name must be between 2 and 100 characters.';
}

if (!validate_email($email)) {
    $errors[] = 'Please enter a valid email address.';
}
if (strlen($email) > 150) {
    $errors[] = 'Email is too long.';
}

if (strlen($subject) > 200) {
    $errors[] = 'Subject is too long.';
}

if (strlen($message) < 10 || strlen($message) > 5000) {
    $errors[] = 'Message must be between 10 and 5000 characters.';
}

// Check for malicious content
if (preg_match('/<script|<iframe|javascript:/i', $message)) {
    $errors[] = 'Invalid content detected.';
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => implode(' ', $errors)]);
    exit;
}

// ============================================
// DATABASE INSERT
// ============================================
try {
    $pdo = get_pdo();
    
    // Check for duplicate (same IP + email within 5 minutes)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM contacts 
        WHERE ip_address = ? 
        AND email = ? 
        AND submitted_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ");
    $stmt->execute([$ip, $email]);
    $duplicate = (int)$stmt->fetchColumn();
    
    if ($duplicate > 0) {
        http_response_code(429);
        echo json_encode(['success' => false, 'error' => 'Duplicate submission detected. Please wait a few minutes.']);
        exit;
    }
    
    // Insert into database
    $stmt = $pdo->prepare("
        INSERT INTO contacts (name, email, subject, message, ip_address, user_agent, submitted_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$name, $email, $subject, $message, $ip, $user_agent]);
    $insert_id = $pdo->lastInsertId();
    
    if (!$insert_id) {
        throw new Exception('Failed to save message.');
    }
    
    // Clear rate limit on success
    unset($_SESSION['rate_limit']['contact_' . $ip]);
    
    // ============================================
    // SEND EMAIL NOTIFICATION
    // ============================================
    $email_sent = send_notification_email($name, $email, $subject, $message, $ip);
    
    if (!$email_sent && ENVIRONMENT === 'production') {
        error_log("Contact form: Email notification failed for ID: $insert_id");
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Message sent successfully! I will reply within 24 hours.',
        'id' => $insert_id
    ]);
    
} catch (PDOException $e) {
    error_log('[portfolio contact.php] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error. Please try again later.']);
} catch (Exception $e) {
    error_log('[portfolio contact.php] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'An error occurred. Please try again.']);
}

// ============================================
// EMAIL NOTIFICATION FUNCTION
// ============================================
function send_notification_email($name, $email, $subject, $message, $ip): bool {
    $to = OWNER_EMAIL;
    $email_subject = "📬 New Contact Message from $name";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: " . $email . "\r\n";
    $headers .= "Reply-To: " . $email . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
    $headers .= "X-Priority: 1\r\n";
    $headers .= "X-MSMail-Priority: High\r\n";
    
    $timestamp = date('Y-m-d H:i:s');
    
    $html_body = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>New Contact Message</title>
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; background: #f4f6f8; padding: 20px; margin: 0; }
            .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; box-shadow: 0 4px 24px rgba(0,0,0,0.1); overflow: hidden; }
            .header { background: #06b6d4; padding: 30px 40px; color: white; }
            .header h2 { margin: 0; font-size: 24px; font-weight: 600; }
            .body { padding: 40px; }
            .field { margin-bottom: 24px; }
            .field-label { font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; margin-bottom: 6px; }
            .field-value { font-size: 16px; color: #1f2937; line-height: 1.6; padding: 8px 12px; background: #f9fafb; border-radius: 6px; }
            .message-box { background: #f9fafb; padding: 16px; border-radius: 8px; border-left: 4px solid #06b6d4; margin-top: 4px; }
            .message-box .field-value { background: transparent; padding: 0; }
            .footer { background: #f9fafb; padding: 20px 40px; text-align: center; font-size: 14px; color: #6b7280; border-top: 1px solid #e5e7eb; }
            .footer a { color: #06b6d4; text-decoration: none; }
            .badge { display: inline-block; background: #06b6d4; color: white; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>📬 New Contact Message</h2>
                <p style='margin: 8px 0 0; opacity: 0.8;'>Received: " . $timestamp . "</p>
            </div>
            <div class='body'>
                <div class='field'>
                    <div class='field-label'>From</div>
                    <div class='field-value'><strong>" . htmlspecialchars($name) . "</strong> &lt;<a href='mailto:" . htmlspecialchars($email) . "'>" . htmlspecialchars($email) . "</a>&gt;</div>
                </div>
                <div class='field'>
                    <div class='field-label'>Subject</div>
                    <div class='field-value'>" . htmlspecialchars($subject ?: '(No subject)') . "</div>
                </div>
                <div class='field'>
                    <div class='field-label'>Message</div>
                    <div class='message-box'>
                        <div class='field-value' style='white-space: pre-wrap;'>" . htmlspecialchars($message) . "</div>
                    </div>
                </div>
                <div style='display: flex; gap: 20px; margin-top: 20px; padding: 16px; background: #f3f4f6; border-radius: 8px; font-size: 14px; color: #6b7280;'>
                    <span>🌐 IP: " . htmlspecialchars($ip) . "</span>
                    <span>🕐 " . $timestamp . "</span>
                </div>
            </div>
            <div class='footer'>
                <p>Sent from your portfolio contact form</p>
                <p><a href='http://" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "/admin.php'>View in Admin Panel →</a></p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Use mail() or SMTP in production
    return mail($to, $email_subject, $html_body, $headers);
}