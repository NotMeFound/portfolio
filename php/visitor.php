<?php
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
session_start();

try {
    $pdo = get_pdo();
    if (empty($_SESSION['visited'])) {
        $stmt = $pdo->prepare("INSERT INTO visitors (ip_address) VALUES (?)");
        $stmt->execute([$_SERVER['REMOTE_ADDR']]);
        $_SESSION['visited'] = true;
    }
    $count = $pdo->query("SELECT COUNT(*) FROM visitors")->fetchColumn();
    echo json_encode(['success' => true, 'visitors' => (int)$count]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'visitors' => 0]);
}