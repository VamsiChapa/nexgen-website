<?php
/* ================================================================
   NEx-gEN — Public Enquiry Submission API
   URL:    /api/submit-enquiry.php
   Method: POST (JSON body or multipart form-data)

   Called from:
     - The enquiry form on index.html (website visitors)
     - Any future external form / campaign landing page

   Returns: JSON { success: bool, message: string, enquiry_number?: string }
   ================================================================ */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');          /* Adjust to your domain in production */
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

require_once dirname(__DIR__) . '/config/db.php';

/* ── Read input (JSON body OR form-encoded) ──────────────────── */
$raw = file_get_contents('php://input');
if ($raw && ($json = json_decode($raw, true)) !== null) {
    $input = $json;
} else {
    $input = $_POST;
}

function inp($key, $default = '') {
    global $input;
    return isset($input[$key]) ? trim((string)$input[$key]) : $default;
}

/* ── Validate required fields ────────────────────────────────── */
$name  = inp('name');
$phone = preg_replace('/\D/', '', inp('phone'));
if (!$name || strlen($name) < 2) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Please enter your name.']);
    exit;
}
if (strlen($phone) < 10) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Please enter a valid 10-digit phone number.']);
    exit;
}

/* ── Honeypot check (spam bots fill hidden field "website_url") ─ */
if (!empty($input['website_url'])) {
    /* Silently succeed so bots don't know they were caught */
    echo json_encode(['success' => true, 'message' => 'Thank you! We will contact you soon.']);
    exit;
}

/* ── Rate-limit: same phone within last 15 minutes ──────────── */
$recent = $pdo->prepare(
    "SELECT id FROM enquiries
     WHERE phone = ? AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE) LIMIT 1"
);
$recent->execute([$phone]);
if ($recent->fetchColumn()) {
    echo json_encode([
        'success' => false,
        'message' => 'We already received your enquiry. Our team will call you shortly!',
    ]);
    exit;
}

/* ── Collect optional fields ─────────────────────────────────── */
$email        = inp('email')    ?: null;
$prefBatch    = inp('preferred_batch') ?: null;
$message      = inp('message')  ?: null;

/* courses_interested: accept comma-string OR array */
$coursesRaw = $input['courses_interested'] ?? '';
if (is_array($coursesRaw)) {
    $courses = implode(',', array_filter(array_map('trim', $coursesRaw)));
} else {
    $courses = trim((string)$coursesRaw) ?: null;
}

/* ── Generate enquiry number ─────────────────────────────────── */
function generateEnquiryNumber($pdo) {
    $year = date('Y');
    $last = $pdo->query(
        "SELECT enquiry_number FROM enquiries
         WHERE enquiry_number LIKE 'ENQ{$year}%'
         ORDER BY id DESC LIMIT 1"
    )->fetchColumn();
    $seq = $last ? ((int)substr($last, -4) + 1) : 1;
    return 'ENQ' . $year . str_pad($seq, 4, '0', STR_PAD_LEFT);
}

/* ── Insert ──────────────────────────────────────────────────── */
try {
    $enqNo = generateEnquiryNumber($pdo);
    $pdo->prepare(
        'INSERT INTO enquiries
         (enquiry_number,name,phone,email,courses_interested,preferred_batch,
          source,message,status,enquiry_date)
         VALUES (?,?,?,?,?,?,\'website\',?,\'new\',CURDATE())'
    )->execute([$enqNo, $name, $phone, $email, $courses, $prefBatch, $message]);

    echo json_encode([
        'success'        => true,
        'message'        => 'Thank you, ' . htmlspecialchars($name) . '! We have received your enquiry and will contact you within 24 hours.',
        'enquiry_number' => $enqNo,
    ]);
} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Something went wrong. Please call us directly.',
    ]);
}
