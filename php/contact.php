<?php
/**
 * KARAN OLI PORTFOLIO — contact.php
 * Receives AJAX POST from contact form, validates, stores in MySQL,
 * and sends email notification to site owner.
 */

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

// ── Load config ───────────────────────────────────────────────
require_once __DIR__ . '/config.php';

// ── Sanitize & validate inputs ────────────────────────────────
$name    = trim(htmlspecialchars(strip_tags($_POST['name']    ?? ''), ENT_QUOTES, 'UTF-8'));
$email   = trim(filter_var($_POST['email']   ?? '', FILTER_SANITIZE_EMAIL));
$subject = trim(htmlspecialchars(strip_tags($_POST['subject'] ?? ''), ENT_QUOTES, 'UTF-8'));
$message = trim(htmlspecialchars(strip_tags($_POST['message'] ?? ''), ENT_QUOTES, 'UTF-8'));
$ip      = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
$user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

// Validation rules
$errors = [];
if (strlen($name) < 2)                         $errors[] = 'Name must be at least 2 characters.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
if (strlen($message) < 10)                     $errors[] = 'Message must be at least 10 characters.';
if (strlen($subject) > 200)                    $errors[] = 'Subject is too long.';

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => implode(' ', $errors)]);
    exit;
}

// ── Rate limiting: max 3 submissions per IP per hour ────
try {
    $pdo = get_pdo();

    $rateStmt = $pdo->prepare("
        SELECT COUNT(*) FROM contacts
        WHERE ip_address = ?
          AND submitted_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");
    $rateStmt->execute([$ip]);
    $count = (int) $rateStmt->fetchColumn();

    if ($count >= 3) {
        http_response_code(429);
        echo json_encode(['success' => false, 'error' => 'Too many submissions. Please wait an hour.']);
        exit;
    }

    // ── Insert into database ───────────────────────────────────
    $stmt = $pdo->prepare("
        INSERT INTO contacts (name, email, subject, message, ip_address, user_agent, submitted_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$name, $email, $subject, $message, $ip, $user_agent]);
    $insert_id = $pdo->lastInsertId();

    // ── Send email notification to owner ──────────────────────
    $email_sent = send_notification_email($name, $email, $subject, $message, $ip);

    if (!$email_sent) {
        error_log("Contact form: Email notification failed for ID: $insert_id");
    }

    echo json_encode([
        'success' => true,
        'message' => 'Message received. I will reply within 24 hours.',
        'id' => $insert_id
    ]);

} catch (PDOException $e) {
    error_log('[portfolio contact.php] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error. Please email me directly.']);
}

/**
 * Send email notification to site owner
 */
function send_notification_email($name, $email, $subject, $message, $ip): bool {
    $to = OWNER_EMAIL;
    $email_subject = "📬 New Contact Message from $name";

    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: " . $email . "\r\n";
    $headers .= "Reply-To: " . $email . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    $html_body = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; background: #0c0e11; color: #e2e8f0; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background: #12151a; border: 1px solid #232830; border-radius: 8px; padding: 30px; }
            .header { border-bottom: 2px solid #06b6d4; padding-bottom: 15px; margin-bottom: 20px; }
            .header h2 { color: #06b6d4; margin: 0; }
            .field { margin-bottom: 15px; }
            .field-label { color: #8b9ab0; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; }
            .field-value { color: #e2e8f0; font-size: 1rem; padding: 8px 0; }
            .message-box { background: #1a1f27; padding: 15px; border-radius: 6px; border-left: 3px solid #06b6d4; margin-top: 5px; }
            .footer { margin-top: 30px; padding-top: 15px; border-top: 1px solid #232830; font-size: 0.8rem; color: #4a5568; text-align: center; }
            .ip-info { color: #4a5568; font-size: 0.75rem; margin-top: 10px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>📬 New Contact Message</h2>
            </div>
            <div class='field'>
                <div class='field-label'>Name</div>
                <div class='field-value'>" . htmlspecialchars($name) . "</div>
            </div>
            <div class='field'>
                <div class='field-label'>Email</div>
                <div class='field-value'><a href='mailto:" . htmlspecialchars($email) . "' style='color:#06b6d4;'>" . htmlspecialchars($email) . "</a></div>
            </div>
            <div class='field'>
                <div class='field-label'>Subject</div>
                <div class='field-value'>" . htmlspecialchars($subject ?: '(No subject)') . "</div>
            </div>
            <div class='field'>
                <div class='field-label'>Message</div>
                <div class='message-box'>" . nl2br(htmlspecialchars($message)) . "</div>
            </div>
            <div class='ip-info'>🌐 IP: " . htmlspecialchars($ip) . "</div>
            <div class='footer'>
                Sent from your portfolio contact form<br>
                <a href='http://localhost/karan-portfolio/admin.php' style='color:#06b6d4;'>View in admin panel →</a>
            </div>
        </div>
    </body>
    </html>
    ";

    return mail($to, $email_subject, $html_body, $headers);
}