<?php
/**
 * api/hit-count.php
 * Returns the total page hit count from the page_hits table.
 * Public endpoint — no auth required (read-only aggregate).
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');

try {
    require_once __DIR__ . '/../config/db.php';
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 3]
    );
    $stmt = $pdo->query("SELECT COUNT(*) FROM `page_hits`");
    $hits = (int) $stmt->fetchColumn();
    echo json_encode(['hits' => $hits, 'ok' => true]);
} catch (Exception $e) {
    /* Fail silently — client falls back to static base number */
    echo json_encode(['hits' => 0, 'ok' => false]);
}
