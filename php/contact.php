<?php
/**
 * KARAN OLI PORTFOLIO — contact.php
 * Receives AJAX POST from contact form, validates, and stores in MySQL.
 * Returns JSON response.
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

// Validation rules
$errors = [];
if (strlen($name) < 2)                         $errors[] = 'Name must be at least 2 characters.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
if (strlen($message) < 10)                     $errors[] = 'Message must be at least 10 characters.';

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => implode(' ', $errors)]);
    exit;
}

// ── Basic rate limiting: max 3 submissions per IP per hour ────
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
        echo json_encode(['success' => false, 'error' => 'Too many submissions. Please wait a while.']);
        exit;
    }

    // ── Insert into database ───────────────────────────────────
    $stmt = $pdo->prepare("
        INSERT INTO contacts (name, email, subject, message, ip_address, submitted_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$name, $email, $subject, $message, $ip]);

    // Optional: send notification email to site owner
    // mail(OWNER_EMAIL, 'New portfolio contact: ' . $subject, $message, 'From: ' . $email);

    echo json_encode(['success' => true, 'message' => 'Message received. I will reply within 24 hours.']);

} catch (PDOException $e) {
    // Log error server-side, never expose to client
    error_log('[portfolio contact.php] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error. Please email me directly.']);
}
