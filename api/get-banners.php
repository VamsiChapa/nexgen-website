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
    /* Use CURDATE() directly in SQL — avoids duplicate named-parameter
       issue when PDO::ATTR_EMULATE_PREPARES is false (MySQL driver) */
    $stmt = $pdo->query(
        "SELECT id, badge_text, title, title_span, subtitle,
                image_url, bg_color,
                btn1_text, btn1_link, btn2_text, btn2_link
         FROM   banners
         WHERE  is_active = 1
           AND  (display_from  IS NULL OR display_from  <= CURDATE())
           AND  (display_until IS NULL OR display_until >= CURDATE())
         ORDER  BY sort_order ASC, id ASC"
    );
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
    /* On DB error return empty — static slides will show as fallback */
    echo json_encode(['success' => true, 'banners' => []]);
}
