<?php
/**
 * KARAN OLI PORTFOLIO — messages.php
 * Secure AJAX polling endpoint for admin inbox
 */

declare(strict_types=1);

// Set security headers
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Load config
require_once __DIR__ . '/config.php';

// ============================================
// SESSION SECURITY
// ============================================
session_start();

// Check authentication
if (empty($_SESSION['admin_logged_in'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Verify session integrity
if (isset($_SESSION['user_agent']) && $_SESSION['user_agent'] !== ($_SERVER['HTTP_USER_AGENT'] ?? '')) {
    session_destroy();
    http_response_code(403);
    echo json_encode(['error' => 'Session validation failed']);
    exit;
}

// Check session timeout
if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > SESSION_TIMEOUT)) {
    session_destroy();
    http_response_code(403);
    echo json_encode(['error' => 'Session expired']);
    exit;
}

// ============================================
// GET PARAMETERS
// ============================================
$since_id = isset($_GET['since_id']) ? (int)$_GET['since_id'] : 0;
$limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 50) : 20;

if ($since_id < 0) {
    $since_id = 0;
}

try {
    $pdo = get_pdo();
    
    // Get unread count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM contacts WHERE is_read = 0");
    $stmt->execute();
    $unread_count = (int)$stmt->fetchColumn();
    
    // Get messages
    if ($since_id > 0) {
        $stmt = $pdo->prepare("
            SELECT id, name, email, subject, message, is_read, submitted_at 
            FROM contacts 
            WHERE id > ? 
            ORDER BY id DESC
        ");
        $stmt->execute([$since_id]);
    } else {
        $stmt = $pdo->prepare("
            SELECT id, name, email, subject, message, is_read, submitted_at 
            FROM contacts 
            ORDER BY id DESC 
            LIMIT ?
        ");
        $stmt->execute([$limit]);
    }
    
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format messages for display
    foreach ($messages as &$msg) {
        $msg['submitted_at'] = date('M j, Y g:i A', strtotime($msg['submitted_at']));
        $msg['message_preview'] = strlen($msg['message']) > 80 
            ? substr($msg['message'], 0, 80) . '...' 
            : $msg['message'];
        // Escape output
        $msg['name'] = htmlspecialchars($msg['name']);
        $msg['email'] = htmlspecialchars($msg['email']);
        $msg['subject'] = htmlspecialchars($msg['subject']);
        $msg['message_preview'] = htmlspecialchars($msg['message_preview']);
    }
    
    // Get latest ID
    $latest_id = 0;
    if (!empty($messages)) {
        $latest_id = (int)$messages[0]['id'];
    }
    
    echo json_encode([
        'success' => true,
        'messages' => $messages,
        'unread_count' => $unread_count,
        'latest_id' => $latest_id,
        'count' => count($messages)
    ]);
    
} catch (PDOException $e) {
    error_log('[portfolio messages.php] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
} catch (Exception $e) {
    error_log('[portfolio messages.php] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
exit;