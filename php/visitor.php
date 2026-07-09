<?php
/**
 * KARAN OLI PORTFOLIO — visitor.php
 * Records unique daily visitors and returns counts.
 * Uses cookie-based tracking for uniqueness.
 */

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config.php';

// ── Helper: Get or create visitor ID cookie ──────────────────
function get_or_create_visitor_id(): string {
    if (isset($_COOKIE['visitor_id'])) {
        return $_COOKIE['visitor_id'];
    }

    $visitor_id = bin2hex(random_bytes(16));
    setcookie('visitor_id', $visitor_id, time() + (86400 * 365), '/', '', false, true);
    return $visitor_id;
}

try {
    $pdo = get_pdo();
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
    $visitor_id = get_or_create_visitor_id();
    $today = date('Y-m-d');

    // ── Check if this visitor already counted today ──────────
    $stmt = $pdo->prepare("
        SELECT id FROM visitors 
        WHERE visitor_id = ? AND DATE(visited_at) = ?
    ");
    $stmt->execute([$visitor_id, $today]);
    $existing = $stmt->fetch();

    if (!$existing) {
        // ── New unique visitor today ──────────────────────────
        $stmt = $pdo->prepare("
            INSERT INTO visitors (ip_address, user_agent, visitor_id, visited_at) 
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$ip, $user_agent, $visitor_id]);
    }

    // ── Get today's unique count ──────────────────────────────
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT visitor_id) 
        FROM visitors 
        WHERE DATE(visited_at) = ?
    ");
    $stmt->execute([$today]);
    $today_count = (int)$stmt->fetchColumn();

    // ── Get total unique count ─────────────────────────────────
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT visitor_id) 
        FROM visitors
    ");
    $stmt->execute();
    $total_count = (int)$stmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'today' => $today_count,
        'total' => $total_count,
        'date' => $today
    ]);

} catch (PDOException $e) {
    error_log('[portfolio visitor.php] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
exit;