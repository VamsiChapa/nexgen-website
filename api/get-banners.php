<?php
/* ================================================================
   NEx-gEN — Get Active Banners API
   GET /api/get-banners.php
   Returns JSON list of banners active today, ordered by sort_order
   ================================================================ */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300'); /* Cache 5 min */

require_once '../config/db.php';

try {
    $today = date('Y-m-d');

    $stmt = $pdo->prepare(
        "SELECT id, badge_text, title, title_span, subtitle,
                image_url, bg_color,
                btn1_text, btn1_link, btn2_text, btn2_link
         FROM   banners
         WHERE  is_active = 1
           AND  (display_from  IS NULL OR display_from  <= :today)
           AND  (display_until IS NULL OR display_until >= :today)
         ORDER  BY sort_order ASC, id ASC"
    );
    $stmt->execute([':today' => $today]);
    $banners = $stmt->fetchAll();

    /* Build absolute image URLs */
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host  = $_SERVER['HTTP_HOST'] ?? '';

    foreach ($banners as &$b) {
        if (!empty($b['image_url']) && strpos($b['image_url'], 'http') !== 0 && $host) {
            $b['image_url'] = $proto . '://' . $host . '/' . ltrim($b['image_url'], '/');
        }
    }
    unset($b);

    echo json_encode(['success' => true, 'banners' => $banners]);

} catch (PDOException $e) {
    /* On DB error just return empty — static slides will show */
    echo json_encode(['success' => true, 'banners' => []]);
}
