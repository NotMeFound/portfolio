<?php
/**
 * KARAN OLI PORTFOLIO — messages.php
 * AJAX polling endpoint for admin dashboard.
 * Returns new messages and unread count.
 */

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

// ── Secure: Only allow authenticated admin sessions ──────────
session_start();

if (empty($_SESSION['admin_logged_in'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// ── Load config ───────────────────────────────────────────────
require_once __DIR__ . '/config.php';

// ── Get parameters ────────────────────────────────────────────
$since_id = isset($_GET['since_id']) ? (int)$_GET['since_id'] : 0;
$limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 50) : 20;

try {
    $pdo = get_pdo();

    // ── Get unread count ──────────────────────────────────────
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM contacts WHERE is_read = 0");
    $stmt->execute();
    $unread_count = (int)$stmt->fetchColumn();

    // ── Get new messages since last check ─────────────────────
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

    // ── Format timestamps and add preview ─────────────────────
    foreach ($messages as &$msg) {
        $msg['submitted_at'] = date('M j, Y g:i A', strtotime($msg['submitted_at']));
        $msg['message_preview'] = strlen($msg['message']) > 80 
            ? substr($msg['message'], 0, 80) . '...' 
            : $msg['message'];
    }

    // ── Get the latest ID for next poll ──────────────────────
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
}
exit;