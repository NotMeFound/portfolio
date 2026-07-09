<?php
/**
 * KARAN OLI PORTFOLIO — visitor.php
 * Secure unique daily visitor counter with cookie tracking
 */

declare(strict_types=1);

// Set security headers
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

require_once __DIR__ . '/config.php';

// ============================================
// SECURE COOKIE HANDLING
// ============================================
function get_or_create_visitor_id(): string {
    if (isset($_COOKIE['visitor_id'])) {
        // Validate cookie format
        if (preg_match('/^[a-f0-9]{32}$/', $_COOKIE['visitor_id'])) {
            return $_COOKIE['visitor_id'];
        }
    }
    
    // Generate new secure visitor ID
    $visitor_id = bin2hex(random_bytes(16));
    $secure = FORCE_HTTPS;
    $httponly = true;
    
    setcookie(
        'visitor_id',
        $visitor_id,
        time() + (86400 * 365), // 1 year
        '/',
        '',
        $secure,
        $httponly
    );
    
    return $visitor_id;
}

try {
    $pdo = get_pdo();
    $ip = get_client_ip();
    $user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
    $visitor_id = get_or_create_visitor_id();
    $today = date('Y-m-d');
    
    // ============================================
    // RECORD UNIQUE VISITOR
    // ============================================
    // Check if visitor already counted today
    $stmt = $pdo->prepare("
        SELECT id FROM visitors 
        WHERE visitor_id = ? AND DATE(visited_at) = ?
    ");
    $stmt->execute([$visitor_id, $today]);
    $existing = $stmt->fetch();
    
    if (!$existing) {
        // Insert new visitor
        $stmt = $pdo->prepare("
            INSERT INTO visitors (ip_address, user_agent, visitor_id, visited_at) 
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$ip, $user_agent, $visitor_id]);
    }
    
    // ============================================
    // GET COUNTS
    // ============================================
    // Today's unique visitors
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT visitor_id) 
        FROM visitors 
        WHERE DATE(visited_at) = ?
    ");
    $stmt->execute([$today]);
    $today_count = (int)$stmt->fetchColumn();
    
    // Total unique visitors
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
} catch (Exception $e) {
    error_log('[portfolio visitor.php] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
exit;