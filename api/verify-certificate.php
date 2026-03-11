<?php
/* ================================================================
   NEx-gEN — Certificate Verification API
   POST /api/verify-certificate.php
   Body params: certificate_number, student_name
   Returns JSON: { success, certificate? { … }, message? }
   ================================================================ */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

/* Only allow POST */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

/* Rate-limiting (simple session-based, 10 attempts per session) */
session_start();
if (!isset($_SESSION['cert_attempts'])) $_SESSION['cert_attempts'] = 0;
if ($_SESSION['cert_attempts'] >= 10) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many attempts. Please wait or contact us at +91 6301012437.']);
    exit;
}
$_SESSION['cert_attempts']++;

require_once '../config/db.php';

/* Sanitise inputs */
$certNumber  = trim($_POST['certificate_number'] ?? '');
$studentName = trim($_POST['student_name'] ?? '');

if ($certNumber === '' || $studentName === '') {
    echo json_encode(['success' => false, 'message' => 'Certificate number and name are required.']);
    exit;
}

/* Validate lengths */
if (strlen($certNumber) > 60 || strlen($studentName) > 100) {
    echo json_encode(['success' => false, 'message' => 'Invalid input length.']);
    exit;
}

try {
    $stmt = $pdo->prepare(
        'SELECT id, certificate_number, student_name, course_name, issue_date, certificate_url
         FROM certificates
         WHERE certificate_number = ?
           AND LOWER(TRIM(student_name)) = LOWER(TRIM(?))
           AND is_active = 1
         LIMIT 1'
    );
    $stmt->execute([$certNumber, $studentName]);
    $cert = $stmt->fetch();

    if ($cert) {
        /* Reset attempts on success */
        $_SESSION['cert_attempts'] = 0;

        /* Build public URL for certificate image */
        $certUrl = null;
        if (!empty($cert['certificate_url'])) {
            $u = $cert['certificate_url'];
            // If stored as relative path, make it absolute-ish for the frontend
            if (strpos($u, 'http') !== 0) {
                $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host  = $_SERVER['HTTP_HOST'];
                // strip leading slash if present, then prepend root
                $u = rtrim($proto . '://' . $host, '/') . '/' . ltrim($u, '/');
            }
            $certUrl = $u;
        }

        echo json_encode([
            'success'     => true,
            'certificate' => [
                'certificate_number' => $cert['certificate_number'],
                'student_name'       => $cert['student_name'],
                'course_name'        => $cert['course_name'],
                'issue_date'         => $cert['issue_date'],
                'certificate_url'    => $certUrl,
            ],
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Certificate not found. Please check your certificate number and name, or contact us at +91 6301012437.',
        ]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again later.']);
}
