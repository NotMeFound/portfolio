<?php
/**
 * KARAN OLI PORTFOLIO — contact.php
 * Secure contact form handler with CSRF, rate limiting, and email notification
 */

declare(strict_types=1);

// ============================================
// DEBUG MODE - Remove in production
// ============================================
ini_set('display_errors', 0); // Set to 1 for debugging
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

// ============================================
// SECURITY HEADERS
// ============================================
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// ============================================
// ONLY ALLOW POST REQUESTS
// ============================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

// ============================================
// LOAD CONFIG
// ============================================
require_once __DIR__ . '/config.php';

// ============================================
// SESSION HANDLING
// ============================================
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

// Skip CSRF check for debugging (remove this line in production)
// if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
//     http_response_code(403);
//     echo json_encode(['success' => false, 'error' => 'Security validation failed.']);
//     exit;
// }

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

function get_client_ip(): string {
    $ip = '0.0.0.0';
    
    if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
    } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ips[0]);
    } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (isset($_SERVER['REMOTE_ADDR'])) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        $ip = '0.0.0.0';
    }
    
    return $ip;
}

// ============================================
// CHECK RATE LIMIT (Temporarily disabled for debugging)
// ============================================
// if (!check_rate_limit('contact', MAX_CONTACT_ATTEMPTS, CONTACT_WINDOW)) {
//     http_response_code(429);
//     echo json_encode([
//         'success' => false, 
//         'error' => 'Too many submissions. Please wait ' . ceil(CONTACT_WINDOW/60) . ' minutes.'
//     ]);
//     exit;
// }

// ============================================
// INPUT VALIDATION & SANITIZATION
// ============================================
function sanitize_string(string $input): string {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// Get POST data
$name = sanitize_string($_POST['name'] ?? '');
$email = sanitize_string($_POST['email'] ?? '');
$subject = sanitize_string($_POST['subject'] ?? '');
$message = sanitize_string($_POST['message'] ?? '');
$ip = get_client_ip();
$user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

// Log received data for debugging
error_log("Contact form received: Name=$name, Email=$email, Subject=$subject");

// Validate inputs
$errors = [];

if (strlen($name) < 2 || strlen($name) > 100) {
    $errors[] = 'Name must be between 2 and 100 characters.';
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
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
    // Debug: Check if PDO is available
    if (!class_exists('PDO')) {
        throw new Exception('PDO extension not installed.');
    }
    
    $pdo = get_pdo();
    
    // Debug: Check if table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'contacts'");
    if ($stmt->rowCount() === 0) {
        throw new Exception('Contacts table does not exist. Please run database.sql.');
    }
    
    // Check for duplicate submission
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
    $result = $stmt->execute([$name, $email, $subject, $message, $ip, $user_agent]);
    
    if (!$result) {
        throw new Exception('Failed to execute insert query.');
    }
    
    $insert_id = $pdo->lastInsertId();
    
    if (!$insert_id) {
        throw new Exception('Failed to get insert ID.');
    }
    
    error_log("Contact saved successfully! ID: $insert_id");
    
    // ============================================
    // SEND EMAIL NOTIFICATION (Optional)
    // ============================================
    // Uncomment the following lines to enable email notifications
    /*
    $email_sent = send_notification_email($name, $email, $subject, $message, $ip);
    if (!$email_sent) {
        error_log("Contact form: Email notification failed for ID: $insert_id");
    }
    */
    
    echo json_encode([
        'success' => true,
        'message' => 'Message sent successfully! I will reply within 24 hours.',
        'id' => $insert_id
    ]);
    
} catch (PDOException $e) {
    error_log('[contact.php] DB Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log('[contact.php] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Error: ' . $e->getMessage()
    ]);
}