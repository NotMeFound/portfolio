<?php
/**
 * KARAN OLI PORTFOLIO — visitor.php
 * Records each unique visit (per session) and returns the total visitor count.
 */

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config.php';

session_start();

try {
    $pdo = get_pdo();

    // Only insert once per PHP session (avoids counting page refreshes)
    if (empty($_SESSION['visit_logged'])) {
        $ip        = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
        $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

        $stmt = $pdo->prepare("
            INSERT INTO visitors (ip_address, user_agent, visited_at)
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$ip, $userAgent]);
        $_SESSION['visit_logged'] = true;
    }

    // Total count
    $total = (int) $pdo->query("SELECT COUNT(*) FROM visitors")->fetchColumn();

    echo json_encode(['success' => true, 'visitors' => $total]);

} catch (PDOException $e) {
    error_log('[portfolio visitor.php] DB error: ' . $e->getMessage());
    // Return a graceful fallback so the front-end still works
    echo json_encode(['success' => false, 'visitors' => 0]);
}
