<?php
/* ================================================================
   NEx-gEN — Biometric Device HTTP Push Endpoint
   URL: /api/biometric-push.php

   Compatible with: ZKTeco ADMS (iClock) protocol
   Also works with: ESSL eTimetracklite, BioTime 8+ HTTP push

   HOW TO CONFIGURE YOUR DEVICE:
   1. Open ZKTeco device web panel (connect laptop to device via LAN)
   2. Go to: Communication → Cloud Server Settings
      - Server Address: yourdomain.com
      - Server Port: 443 (HTTPS) or 80
      - Server Path: /api/biometric-push.php
      - Enable: ON

   The device will POST attendance records to this endpoint.
   ================================================================ */

header('Content-Type: text/plain');
require_once '../config/db.php';

/* ── Security: simple token check ─────────────────────────────────
   Add ?token=YOUR_SECRET_TOKEN to the endpoint URL in device config.
   Set the token below. */
define('BIOMETRIC_TOKEN', 'YOUR_BIOMETRIC_SECRET_TOKEN_HERE');

$token = $_GET['token'] ?? $_POST['token'] ?? '';
if ($token !== BIOMETRIC_TOKEN) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

/* ── Parse incoming data ──────────────────────────────────────────
   ZKTeco sends data as POST body in this format:
   ATTLOG\t<user_id>\t<datetime>\t<verify_type>\n...

   Example line:
   ATTLOG	00042	2026-03-11 08:14:35	1	0	0
   Columns: type, user_id, datetime, verify_mode, in_out, work_code
*/
$rawBody = file_get_contents('php://input');

if (empty($rawBody)) {
    /* Also handle GET ping from device (device checks server alive) */
    echo 'OK';
    exit;
}

$lines    = explode("\n", trim($rawBody));
$inserted = 0;
$skipped  = 0;

$lookupStmt = $pdo->prepare(
    'SELECT id FROM students WHERE biometric_id = ? AND status = "active" LIMIT 1'
);
$insertStmt = $pdo->prepare(
    'INSERT INTO attendance (student_id, attendance_date, check_in_time, status, source)
     VALUES (?, ?, ?, \'present\', \'api\')
     ON DUPLICATE KEY UPDATE
       status        = IF(status=\'absent\',\'present\',status),
       check_in_time = COALESCE(VALUES(check_in_time), check_in_time),
       source        = \'api\',
       updated_at    = NOW()'
);

foreach ($lines as $line) {
    $line = trim($line);
    if (empty($line)) continue;

    /* Support both ZKTeco ATTLOG and plain CSV formats */
    if (stripos($line, 'ATTLOG') === 0) {
        $parts = preg_split('/\s+/', $line);
        /* ATTLOG  user_id  datetime  ... */
        $bioId   = $parts[1] ?? '';
        $dtStr   = ($parts[2] ?? '') . ' ' . ($parts[3] ?? '');
    } else {
        /* Plain CSV: user_id,date,time  OR  user_id,datetime */
        $parts   = str_contains($line, ',') ? explode(',', $line) : explode("\t", $line);
        $bioId   = trim($parts[0] ?? '');
        $dtStr   = trim($parts[1] ?? '') . ' ' . trim($parts[2] ?? '');
    }

    $bioId = trim($bioId);
    $dtStr = trim($dtStr);

    if (!$bioId || !$dtStr) { $skipped++; continue; }

    /* Parse datetime */
    $dt = date_create($dtStr);
    if (!$dt) { $skipped++; continue; }

    $attDate  = $dt->format('Y-m-d');
    $checkIn  = $dt->format('H:i:s');

    $lookupStmt->execute([$bioId]);
    $studentId = $lookupStmt->fetchColumn();
    if (!$studentId) { $skipped++; continue; }

    $insertStmt->execute([$studentId, $attDate, $checkIn]);
    $inserted++;
}

/* ZKTeco expects "OK" response to know the push succeeded */
echo 'OK';

/* Log to file for debugging */
$log = date('[Y-m-d H:i:s]') . " biometric-push: inserted={$inserted} skipped={$skipped}\n";
file_put_contents(dirname(__DIR__) . '/cron/biometric.log', $log, FILE_APPEND | LOCK_EX);
