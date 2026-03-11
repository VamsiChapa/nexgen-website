<?php
/* ================================================================
   NEx-gEN — Absent Student SMS / WhatsApp Cron Job
   Run every 1 HOUR via Hostinger Cron Jobs.

   HOSTINGER SETUP:
   hPanel → Advanced → Cron Jobs → Add Cron Job
     Command : /usr/bin/php /home/u214786104/public_html/cron/send-absent-sms.php
     Schedule: Every 1 Hour  (select from dropdown)

   HOW IT WORKS (no false alarms):
   ┌─────────────────────────────────────────────────────────────┐
   │ The cron runs every hour. It queries the batches table to   │
   │ find any batch whose start_time was 30–90 minutes ago.      │
   │                                                              │
   │ Example: Cron fires at 9:00 AM                              │
   │   → Finds batches that started between 7:30 and 8:30 AM     │
   │   → The 8:00–9:00 AM batch qualifies → send absence SMS     │
   │                                                              │
   │ This works for ALL batch slots automatically — no hardcoding.│
   └─────────────────────────────────────────────────────────────┘

   SKIP conditions (no false alarms):
   ✓ Skips if today is Sunday
   ✓ Skips if today is a public holiday
   ✓ Skips if student already has present/late attendance
   ✓ Skips if student is on approved leave
   ✓ Skips if absence SMS was already sent today (deduplication)
   ================================================================ */

require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/config/sms-helper.php';

date_default_timezone_set('Asia/Kolkata');

$now     = new DateTime();
$today   = $now->format('Y-m-d');
$dayOfWk = (int)$now->format('N');   /* 1=Mon … 7=Sun */

/* ── Log helper ───────────────────────────────────────────────────── */
$logFile = __DIR__ . '/cron.log';
function cronLog(string $msg): void {
    global $logFile;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    if (PHP_SAPI === 'cli') echo $line;
}

cronLog('=== Cron started ===');

/* ── 1. Skip Sunday ───────────────────────────────────────────────── */
if ($dayOfWk === 7) {
    cronLog('Sunday — nothing to do.');
    exit;
}

/* ── 2. Skip holidays ─────────────────────────────────────────────── */
$hol = $pdo->prepare('SELECT description FROM holidays WHERE holiday_date = ?');
$hol->execute([$today]);
if ($row = $hol->fetch()) {
    cronLog("Holiday: {$row['description']} — skipping.");
    exit;
}

/* ── 3. Find batches whose start was 30–90 min ago ───────────────────
   We use TIME arithmetic so it works for any slot in the DB.
   The send window starts 30 min after batch start (late-entry buffer)
   and closes 90 min after batch start (so each batch fires only once
   even if the cron runs slightly late). */
$activeBatches = $pdo->query(
    "SELECT * FROM batches
     WHERE is_active = 1
       AND start_time BETWEEN
           SUBTIME(TIME(NOW()), '01:30:00')   -- 90 min ago
           AND SUBTIME(TIME(NOW()), '00:30:00') -- 30 min ago
     ORDER BY start_time ASC"
)->fetchAll();

if (empty($activeBatches)) {
    cronLog('No batch in send window right now — nothing to do.');
    exit;
}

foreach ($activeBatches as $batch) {
    $batchId   = (int)$batch['id'];
    $batchName = $batch['name'];
    cronLog("Processing batch [{$batchId}] {$batchName}");

    /* Get all active students in this batch who have a phone number */
    $students = $pdo->prepare(
        "SELECT id, student_name, parent_name, parent_phone, phone
         FROM   students
         WHERE  batch_id = ? AND status = 'active'
           AND  (parent_phone IS NOT NULL OR phone IS NOT NULL)"
    );
    $students->execute([$batchId]);
    $list = $students->fetchAll();

    if (empty($list)) {
        cronLog("  No active students in this batch.");
        continue;
    }

    foreach ($list as $stu) {
        $sid  = (int)$stu['id'];
        $name = $stu['student_name'];

        /* Check 1: Already present / late today */
        $att = $pdo->prepare(
            "SELECT id FROM attendance
             WHERE student_id = ? AND attendance_date = ?
               AND status IN ('present','late')
             LIMIT 1"
        );
        $att->execute([$sid, $today]);
        if ($att->fetchColumn()) {
            cronLog("  SKIP [{$name}] — present/late today.");
            continue;
        }

        /* Check 2: On approved leave */
        $lv = $pdo->prepare(
            "SELECT id FROM student_leaves
             WHERE student_id = ? AND leave_date = ? AND approved = 1
             LIMIT 1"
        );
        $lv->execute([$sid, $today]);
        if ($lv->fetchColumn()) {
            cronLog("  SKIP [{$name}] — on approved leave.");
            continue;
        }

        /* Check 3: SMS/WA already sent today for this student (dedup) */
        $dup = $pdo->prepare(
            "SELECT id FROM sms_logs
             WHERE student_id = ? AND type = 'absence'
               AND DATE(sent_at) = ? AND status = 'sent'
             LIMIT 1"
        );
        $dup->execute([$sid, $today]);
        if ($dup->fetchColumn()) {
            cronLog("  SKIP [{$name}] — alert already sent today.");
            continue;
        }

        /* ── Send message ───────────────────────────────────────── */
        $recipientPhone = $stu['parent_phone'] ?: $stu['phone'];
        $recipientName  = $stu['parent_name']  ?: 'Parent/Guardian';

        $message = buildAbsenceMessage(
            $name,
            $recipientName,
            $batchName,
            date('d M Y', strtotime($today))
        );

        $result = sendMessage(
            $recipientPhone,
            $message,
            'absence',
            $sid,
            $recipientName
        );

        $channel = strtoupper($result['channel']);
        if ($result['success']) {
            cronLog("  SENT via {$channel} [{$name}] → {$recipientPhone}");
        } else {
            cronLog("  FAIL [{$name}] → {$recipientPhone} | " . substr($result['response'], 0, 100));
        }
    }
}

cronLog('=== Cron finished ===');
