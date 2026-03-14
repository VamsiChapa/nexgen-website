<?php
/* ================================================================
   NEx-gEN — Page Hit Tracker
   URL:    /api/track.php?p=PAGE_PATH&r=REFERRER
   Method: GET (called by a 1x1 pixel image or JS beacon)

   Returns a 1×1 transparent GIF so it can be used as:
     <img src="/api/track.php?p=..." width="1" height="1" />
   ================================================================ */

/* Return the GIF immediately — no buffering, fast response */
header('Content-Type: image/gif');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

/* Tiny transparent 1×1 GIF (35 bytes) */
$GIF = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

/* ── Bot / crawler filter — skip logging, just serve the GIF ──── */
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
$bots = [
    'bot','crawl','spider','slurp','Googlebot','Bingbot','Yahoo! Slurp',
    'DuckDuckBot','Baidu','Sogou','Exabot','ia_archiver','MJ12bot',
    'AhrefsBot','SemrushBot','curl','wget','python','Java/',
    'Go-http-client','HeadlessChrome','PhantomJS','Prerender',
];
foreach ($bots as $b) {
    if (stripos($ua, $b) !== false) {
        echo $GIF; exit;
    }
}

/* ── Skip empty user-agents (likely automated pings) ─────────── */
if (strlen($ua) < 10) {
    echo $GIF; exit;
}

/* ── Sanitise page path (from query string, not server path) ──── */
$page = isset($_GET['p']) ? trim(urldecode($_GET['p'])) : '/';
$page = '/' . ltrim($page, '/');                  /* ensure leading slash */
$page = substr($page, 0, 255);                    /* hard cap */
$page = preg_replace('/[^a-zA-Z0-9\/_\-\.%=&?#]/', '', $page);

/* ── Sanitise referrer ────────────────────────────────────────── */
$ref = isset($_GET['r']) ? trim(urldecode($_GET['r'])) : '';
$ref = substr($ref, 0, 500);
$ref = $ref ?: null;

/* ── Resolve real IP (handles Cloudflare, load balancers) ─────── */
$ip = $_SERVER['HTTP_CF_CONNECTING_IP']           /* Cloudflare */
    ?? $_SERVER['HTTP_X_FORWARDED_FOR']           /* Reverse proxy */
    ?? $_SERVER['REMOTE_ADDR']
    ?? null;
if ($ip) {
    $ip = trim(explode(',', $ip)[0]);             /* first IP if list */
    $ip = filter_var($ip, FILTER_VALIDATE_IP) ? $ip : null;
}

/* ── Log to DB ────────────────────────────────────────────────── */
try {
    require_once dirname(__DIR__) . '/config/db.php';
    $pdo->prepare(
        'INSERT INTO page_hits (page_path, ip_address, user_agent, referrer)
         VALUES (?, ?, ?, ?)'
    )->execute([$page, $ip, substr($ua, 0, 512), $ref]);
} catch (\Exception $e) {
    /* Fail silently — never break the visitor's page */
}

echo $GIF;
