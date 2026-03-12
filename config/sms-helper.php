<?php
/* ================================================================
   NEx-gEN — Messaging Helper
   Strategy: WhatsApp FIRST (via UltraMsg or Meta Cloud API) →
             fallback to SMS (Fast2SMS) if WhatsApp fails.

   WHY WHATSAPP FIRST?
   • WhatsApp messages are FREE on UltraMsg's entry plan (₹750/mo,
     unlimited messages) vs Fast2SMS ₹0.15–0.25 per SMS.
   • Almost everyone in India has WhatsApp.
   • If the number isn't on WhatsApp, UltraMsg returns a failure
     and we automatically send SMS instead.

   ── QUICK START ────────────────────────────────────────────────
   Option A — UltraMsg (easiest, ₹750/month unlimited):
     1. Register at https://ultramsg.com/
     2. Create instance → scan QR with WhatsApp Business phone
     3. Copy Instance ID + Token below.

   Option B — Meta Cloud API (FREE up to 1,000 conversations/mo):
     1. https://developers.facebook.com/docs/whatsapp/cloud-api
     2. Set WHATSAPP_PROVIDER = 'meta' below

   Option C — Disable WhatsApp, use SMS only:
     Set WHATSAPP_ENABLED = false below.
   ================================================================ */

/* ── Configuration ─────────────────────────────────────────────── */

/* ── MASTER SWITCH ────────────────────────────────────────────────
   Set to false to disable ALL outgoing alerts system-wide.
   Useful during testing or when you haven't set up a provider yet.
   Individual students can also be opted out in Admin → Students. */
define('ALERTS_ENABLED',      false);   /* ← SET TO true WHEN READY */

/* WhatsApp (set ALERTS_ENABLED = true AND these keys to use) */
define('WHATSAPP_ENABLED',    true);
define('WHATSAPP_PROVIDER',   'ultramsg');   /* 'ultramsg' or 'meta' */

/* UltraMsg settings (used when WHATSAPP_PROVIDER = 'ultramsg') */
define('ULTRAMSG_INSTANCE',   'YOUR_ULTRAMSG_INSTANCE_ID');   /* e.g. instance12345 */
define('ULTRAMSG_TOKEN',      'YOUR_ULTRAMSG_TOKEN_HERE');

/* Meta Cloud API settings (used when WHATSAPP_PROVIDER = 'meta') */
define('META_WA_PHONE_ID',    'YOUR_META_PHONE_NUMBER_ID');
define('META_WA_ACCESS_TOKEN','YOUR_META_ACCESS_TOKEN');

/* SMS fallback — Fast2SMS (used if WhatsApp fails or is disabled) */
define('SMS_ENABLED',         true);
define('FAST2SMS_API_KEY',    'YOUR_FAST2SMS_API_KEY_HERE');
define('FAST2SMS_SENDER',     'NEXGEN');  /* Apply sender ID in Fast2SMS dashboard */

/* Institute phone shown in messages */
define('INSTITUTE_PHONE',     '9876543210');  /* ← Replace with your real number */

/* ── Main send function ────────────────────────────────────────────
 * Tries WhatsApp first. Falls back to SMS on failure.
 *
 * @param  string    $phone          10-digit Indian mobile
 * @param  string    $message        Plain-text message
 * @param  string    $type           'absence'|'late'|'custom'|'test'
 * @param  int|null  $studentId      For sms_logs FK
 * @param  string    $recipientName  For sms_logs display
 * @return array     ['success'=>bool, 'channel'=>'whatsapp'|'sms'|'none', 'response'=>string]
 */
function sendMessage(
    string  $phone,
    string  $message,
    string  $type          = 'custom',
    ?int    $studentId     = null,
    string  $recipientName = ''
): array {
    /* Global master switch — set ALERTS_ENABLED=true in config to activate */
    if (!ALERTS_ENABLED) {
        error_log('[NExGEN] Alerts disabled (ALERTS_ENABLED=false). Message NOT sent to ' . $phone);
        return ['success' => false, 'channel' => 'none', 'response' => 'Alerts disabled in config'];
    }

    $phone = _sanitisePhone($phone);
    if (!$phone) {
        _logSms($studentId, $recipientName, $phone, $message, $type, 'failed', 'Invalid phone');
        return ['success' => false, 'channel' => 'none', 'response' => 'Invalid phone number'];
    }

    /* 1. Try WhatsApp */
    if (WHATSAPP_ENABLED) {
        $waResult = WHATSAPP_PROVIDER === 'meta'
            ? _sendWhatsAppMeta($phone, $message)
            : _sendWhatsAppUltraMsg($phone, $message);

        if ($waResult['success']) {
            _logSms($studentId, $recipientName, $phone, $message, $type, 'sent', 'WA:' . $waResult['response']);
            return ['success' => true, 'channel' => 'whatsapp', 'response' => $waResult['response']];
        }
        /* WhatsApp failed — fall through to SMS */
        error_log('[NExGEN] WhatsApp failed for ' . $phone . ': ' . $waResult['response'] . ' — trying SMS');
    }

    /* 2. Fallback: SMS */
    if (SMS_ENABLED) {
        $smsResult = _sendFast2SMS($phone, $message);
        _logSms($studentId, $recipientName, $phone, $message, $type,
                $smsResult['success'] ? 'sent' : 'failed',
                'SMS:' . $smsResult['response']);
        return [
            'success'  => $smsResult['success'],
            'channel'  => 'sms',
            'response' => $smsResult['response'],
        ];
    }

    _logSms($studentId, $recipientName, $phone, $message, $type, 'failed', 'All channels disabled');
    return ['success' => false, 'channel' => 'none', 'response' => 'All channels disabled'];
}

/* Keep backward-compatible alias */
function sendSms(
    string $phone, string $message, string $type = 'custom',
    ?int $studentId = null, string $recipientName = ''
): array {
    $r = sendMessage($phone, $message, $type, $studentId, $recipientName);
    return ['success' => $r['success'], 'response' => $r['response']];
}

/* ── WhatsApp: UltraMsg ────────────────────────────────────────── */
function _sendWhatsAppUltraMsg(string $phone, string $message): array {
    $to  = '91' . $phone;   /* Add India country code */
    $url = 'https://api.ultramsg.com/' . ULTRAMSG_INSTANCE . '/messages/chat';

    $payload = http_build_query([
        'token'   => ULTRAMSG_TOKEN,
        'to'      => $to,
        'body'    => $message,
        'priority'=> 1,
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
    ]);
    $raw     = curl_exec($ch);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($curlErr) return ['success' => false, 'response' => $curlErr];

    $resp = json_decode($raw, true);
    /* UltraMsg returns {"sent":"true","id":"..."} on success */
    $ok = !empty($resp['sent']) && ($resp['sent'] === 'true' || $resp['sent'] === true);
    return ['success' => $ok, 'response' => $raw];
}

/* ── WhatsApp: Meta Cloud API ─────────────────────────────────── */
function _sendWhatsAppMeta(string $phone, string $message): array {
    $to  = '91' . $phone;
    $url = 'https://graph.facebook.com/v19.0/' . META_WA_PHONE_ID . '/messages';

    $body = json_encode([
        'messaging_product' => 'whatsapp',
        'to'                => $to,
        'type'              => 'text',
        'text'              => ['body' => $message],
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . META_WA_ACCESS_TOKEN,
        ],
    ]);
    $raw     = curl_exec($ch);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($curlErr) return ['success' => false, 'response' => $curlErr];

    $resp = json_decode($raw, true);
    $ok   = !empty($resp['messages'][0]['id']);
    return ['success' => $ok, 'response' => $raw];
}

/* ── SMS: Fast2SMS ─────────────────────────────────────────────── */
function _sendFast2SMS(string $phone, string $message): array {
    $payload = http_build_query([
        'authorization' => FAST2SMS_API_KEY,
        'sender_id'     => FAST2SMS_SENDER,
        'message'       => $message,
        'language'      => 'english',
        'route'         => 'q',
        'numbers'       => $phone,
    ]);

    $ch = curl_init('https://www.fast2sms.com/dev/bulkV2');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => ['cache-control: no-cache'],
    ]);
    $raw     = curl_exec($ch);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($curlErr) return ['success' => false, 'response' => $curlErr];

    $resp = json_decode($raw, true);
    $ok   = !empty($resp['return']) && $resp['return'] === true;
    return ['success' => $ok, 'response' => $raw];
}

/* ── Shared helpers ────────────────────────────────────────────── */
function _sanitisePhone(string $phone): string {
    $p = preg_replace('/\D/', '', $phone);
    if (strlen($p) === 11 && $p[0] === '0')           $p = substr($p, 1);
    if (strlen($p) === 12 && substr($p, 0, 2) === '91') $p = substr($p, 2);
    return strlen($p) === 10 ? $p : '';
}

function _logSms(
    ?int $studentId, string $recipientName, string $phone,
    string $message, string $type, string $status, string $providerResponse
): void {
    if ($studentId === null) return;
    global $pdo;
    if (!isset($pdo)) {
        $cfgPath = __DIR__ . '/db.php';
        if (file_exists($cfgPath)) require_once $cfgPath;
        if (!isset($pdo)) return;
    }
    try {
        $pdo->prepare(
            'INSERT INTO sms_logs
               (student_id, recipient_name, phone, message, type, status, provider_response)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([$studentId, $recipientName, $phone, $message, $type, $status, $providerResponse]);
    } catch (\PDOException $e) {
        error_log('[NExGEN] sms_logs insert failed: ' . $e->getMessage());
    }
}

/**
 * Build the absence message text.
 */
function buildAbsenceMessage(
    string $studentName,
    string $parentName,
    string $batchLabel,
    string $date
): string {
    return "Dear {$parentName}, your ward {$studentName} was ABSENT from the "
         . "{$batchLabel} batch today ({$date}) at NEx-gEN School of Computers, Srikakulam. "
         . "Please call: " . INSTITUTE_PHONE;
}
