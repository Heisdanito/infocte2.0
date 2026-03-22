<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/connection.php';

// ── Read JSON body ─────────────────────────────────────────────────────────────
$rawData = file_get_contents("php://input");
$data    = json_decode($rawData, true);

error_log("get-profile-stats received: " . print_r($data, true));

if (!$data) {
    echo json_encode(["status" => "failed", "message" => "Invalid JSON received"]);
    exit;
}

$student_id = $data['student_id'] ?? null;

if (!$student_id) {
    echo json_encode(["status" => "failed", "message" => "student_id is required"]);
    exit;
}

$student_id = mysqli_real_escape_string($conn, $student_id);

// ══════════════════════════════════════════════════════════════════
// STEP 1 — Get student group
// ══════════════════════════════════════════════════════════════════
$sql_stu = mysqli_query($conn,
    "SELECT group_id, programme
     FROM students
     WHERE student_id = '$student_id'
     AND active = '1'"
);

if (!$sql_stu || mysqli_num_rows($sql_stu) === 0) {
    echo json_encode(["status" => "failed", "message" => "Student not found or inactive"]);
    exit;
}

$student          = mysqli_fetch_assoc($sql_stu);
$student_group_id = $student['group_id'];

// ══════════════════════════════════════════════════════════════════
// STEP 2 — Total sessions created for student's group
// ══════════════════════════════════════════════════════════════════
$sql_total = mysqli_query($conn,
    "SELECT COUNT(*) AS total_sessions
     FROM qrcode
     WHERE group_id = '$student_group_id'"
);

$total_sessions = 0;
if ($sql_total) {
    $row            = mysqli_fetch_assoc($sql_total);
    $total_sessions = intval($row['total_sessions']);
}

// ══════════════════════════════════════════════════════════════════
// STEP 3 — Sessions student attended
// ══════════════════════════════════════════════════════════════════
$sql_att = mysqli_query($conn,
    "SELECT COUNT(*) AS attended
     FROM attendance
     WHERE student_id = '$student_id'"
);

$attended = 0;
if ($sql_att) {
    $row      = mysqli_fetch_assoc($sql_att);
    $attended = intval($row['attended']);
}

// ══════════════════════════════════════════════════════════════════
// STEP 4 — Sessions missed = total - attended
// ══════════════════════════════════════════════════════════════════
$missed = max(0, $total_sessions - $attended);

// ══════════════════════════════════════════════════════════════════
// STEP 5 — Attendance percentage
// ══════════════════════════════════════════════════════════════════
$percentage = ($total_sessions > 0)
    ? round(($attended / $total_sessions) * 100, 1)
    : 0;

// ══════════════════════════════════════════════════════════════════
// STEP 6 — Rating (0.0 – 5.0) calculated from attendance %
//
//   >= 90%  → 5.0   (Excellent)
//   >= 80%  → 4.5
//   >= 75%  → 4.0   (Good — minimum threshold)
//   >= 65%  → 3.5
//   >= 60%  → 3.0   (Warning zone)
//   >= 50%  → 2.5
//   >= 40%  → 2.0
//   >= 30%  → 1.5
//    < 30%  → 1.0   (Critical)
// ══════════════════════════════════════════════════════════════════
function calculateRating($pct) {
    if ($pct >= 90) return 5.0;
    if ($pct >= 80) return 4.5;
    if ($pct >= 75) return 4.0;
    if ($pct >= 65) return 3.5;
    if ($pct >= 60) return 3.0;
    if ($pct >= 50) return 2.5;
    if ($pct >= 40) return 2.0;
    if ($pct >= 30) return 1.5;
    return 1.0;
}

$rating = calculateRating($percentage);

// ══════════════════════════════════════════════════════════════════
// STEP 7 — Per-course breakdown
// ══════════════════════════════════════════════════════════════════
$sql_courses = mysqli_query($conn,
    "SELECT
        a.course_id,
        COUNT(*) AS attended_count
     FROM attendance a
     WHERE a.student_id = '$student_id'
     GROUP BY a.course_id"
);

$courses = [];
if ($sql_courses) {
    while ($row = mysqli_fetch_assoc($sql_courses)) {
        $cid = mysqli_real_escape_string($conn, $row['course_id']);

        // Total sessions for this course in the student's group
        $sql_ctotal = mysqli_query($conn,
            "SELECT COUNT(*) AS total
             FROM qrcode
             WHERE course_id = '$cid'
             AND group_id = '$student_group_id'"
        );
        $course_total = 0;
        if ($sql_ctotal) {
            $ct           = mysqli_fetch_assoc($sql_ctotal);
            $course_total = intval($ct['total']);
        }

        $course_attended = intval($row['attended_count']);
        $course_missed   = max(0, $course_total - $course_attended);
        $course_pct      = ($course_total > 0)
            ? round(($course_attended / $course_total) * 100, 1)
            : 0;

        $courses[] = [
            "course_id"      => $row['course_id'],
            "attended"       => $course_attended,
            "missed"         => $course_missed,
            "total_sessions" => $course_total,
            "percentage"     => $course_pct,
            "rating"         => calculateRating($course_pct),
        ];
    }
}

// ══════════════════════════════════════════════════════════════════
// RESPONSE
// ══════════════════════════════════════════════════════════════════
echo json_encode([
    "status"     => "success",
    "student_id" => $student_id,
    "group_id"   => $student_group_id,
    "stats"      => [
        "total_sessions" => $total_sessions,
        "attended"       => $attended,
        "missed"         => $missed,
        "percentage"     => $percentage,
        "rating"         => $rating,
    ],
    "courses"    => $courses,
]);

$conn->close();
?>
