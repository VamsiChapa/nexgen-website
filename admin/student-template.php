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
    'phone',           /* optional — 10-digit mobile (siblings may share same number) */
    'course',          /* REQUIRED — e.g. PGDCA, DCA, MS OFFICE, PYTHON, TALLY PRIME */
    'batch_name',      /* REQUIRED — must match exactly: e.g. "8:00 AM – 9:00 AM" */
    'email',           /* optional */
    'date_of_birth',   /* optional — YYYY-MM-DD */
    'gender',          /* optional — male / female / other */
    'address',         /* optional */
    'enrollment_date', /* optional — YYYY-MM-DD (today if blank) */
    'parent_name',     /* optional */
    'parent_phone',    /* optional — 10-digit mobile (used for absence alerts) */
    'parent_email',    /* optional */
    'parent_relation', /* optional — Father / Mother / Guardian */
    'notes',           /* optional */
    /* NOTE: admission_number is auto-generated — do NOT include it */
]);

/* ── Three example rows (Row 3 shows siblings sharing parent_phone) ─ */
fputcsv($out, [
    'Ravi Kumar',
    '9876543210',
    'PGDCA',     /* from: MS OFFICE, PROG IN C, CORE JAVA, PYTHON, TALLY PRIME, WEB DESIGNING, MSO C, MSO TALLY, DCA, PGDCA, HAND WRITING */
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
    'MS OFFICE',
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
/* Sibling example — same parent_phone as above row (cron sends one combined SMS) */
fputcsv($out, [
    'Arun Devi',
    '',                 /* phone is optional */
    'PYTHON',
    '10:00 AM – 11:00 AM',
    '',
    '2005-02-10',
    'male',
    'Palasa, Srikakulam',
    '2026-03-11',
    'Lakshmi Devi',
    '9123400001',       /* same parent_phone as sibling Priya Devi above */
    '',
    'Mother',
    'Sibling of Priya Devi — shares parent phone',
]);

fclose($out);
