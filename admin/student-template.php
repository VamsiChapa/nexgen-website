<?php
/* ================================================================
   NEx-gEN — Download CSV Import Template for Students
   URL: /admin/student-template.php
   ================================================================ */
session_start();
if (empty($_SESSION['admin_logged_in'])) { header('Location: login.php'); exit; }

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="nexgen_students_import_template.csv"');
header('Cache-Control: no-cache, no-store, must-revalidate');

$out = fopen('php://output', 'w');

/* ── Header row (exactly what the importer expects) ─────────────── */
fputcsv($out, [
    'student_name',    /* REQUIRED — Full name */
    'phone',           /* REQUIRED — 10-digit mobile */
    'course',          /* REQUIRED — e.g. PGDCA, DCA, Python Programming */
    'batch_name',      /* REQUIRED — must match exactly: e.g. "8:00 AM – 9:00 AM" */
    'email',           /* optional */
    'date_of_birth',   /* optional — YYYY-MM-DD */
    'gender',          /* optional — male / female / other */
    'address',         /* optional */
    'enrollment_date', /* optional — YYYY-MM-DD (today if blank) */
    'parent_name',     /* optional */
    'parent_phone',    /* optional — 10-digit mobile */
    'parent_email',    /* optional */
    'parent_relation', /* optional — Father / Mother / Guardian */
    'notes',           /* optional */
]);

/* ── Two example rows ────────────────────────────────────────────── */
fputcsv($out, [
    'Ravi Kumar',
    '9876543210',
    'PGDCA',
    '8:00 AM – 9:00 AM',
    'ravi@email.com',
    '2000-06-15',
    'male',
    'Srikakulam, AP',
    '2026-03-11',
    'Suresh Kumar',
    '9876500001',
    '',
    'Father',
    '',
]);
fputcsv($out, [
    'Priya Devi',
    '9123456789',
    'DCA',
    '10:00 AM – 11:00 AM',
    '',
    '2003-09-20',
    'female',
    'Palasa, Srikakulam',
    '2026-03-11',
    'Lakshmi Devi',
    '9123400001',
    '',
    'Mother',
    'Scholarship student',
]);

fclose($out);
