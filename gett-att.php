<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") { http_response_code(200); exit(); }

// ── Inline connection ──────────────────────────────────────────────────────
$db_host = "mysql-291ab10a-heisdanito-7ee7.b.aivencloud.com";
$db_user = "avnadmin";
$db_psw  = "AVNS_ZFYiFvpqdF-G5jN0vXu";
$db_name = "defaultdb";
$port    = 21225;
$ca_path = __DIR__ . '/ca.pem';

if (!file_exists($ca_path)) {
    echo json_encode(["status" => "error", "message" => "ca.pem not found at: $ca_path"]);
    exit;
}

try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $conn = new mysqli();
    $conn->ssl_set(NULL, NULL, $ca_path, NULL, NULL);
    $conn->real_connect($db_host, $db_user, $db_psw, $db_name, $port, NULL, MYSQLI_CLIENT_SSL);
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => "DB connect failed: " . $e->getMessage()]);
    exit;
}

// ── Read input ─────────────────────────────────────────────────────────────
$input      = json_decode(file_get_contents("php://input"), true);
$student_id = $input["student_id"] ?? null;

// Allow GET param for browser testing
if (!$student_id && isset($_GET["student_id"])) {
    $student_id = $_GET["student_id"];
}

if (!$student_id) {
    echo json_encode(["status" => "error", "message" => "student_id is required"]);
    exit;
}

$student_id = mysqli_real_escape_string($conn, $student_id);

// ════════════════════════════════════════════════════════════
// TASK 1 — ATTENDANCE PER COURSE
// ════════════════════════════════════════════════════════════
$sql = $conn->query(
    "SELECT course_id, COUNT(*) as attended_classes
     FROM attendance
     WHERE student_id = '$student_id'
     GROUP BY course_id"
);

$course_stats   = [];
$total_classes  = 0;
$total_attended = 0;

if ($sql && mysqli_num_rows($sql) > 0) {
    while ($row = mysqli_fetch_assoc($sql)) {
        $course_id      = $row['course_id'];
        $attended       = (int) $row['attended_classes'];
        $sql_total      = $conn->query("SELECT COUNT(*) as total_sessions FROM qrcode WHERE course_id = '$course_id'");
        $total_sessions = (int) mysqli_fetch_assoc($sql_total)['total_sessions'];
        $missed         = $total_sessions - $attended;
        $percentage     = $total_sessions > 0 ? round(($attended / $total_sessions) * 100, 2) : 0;

        $course_stats[] = [
            "course_id"      => $course_id,
            "total_sessions" => $total_sessions,
            "attended"       => $attended,
            "missed"         => $missed,
            "percentage"     => $percentage,
        ];

        $total_classes  += $total_sessions;
        $total_attended += $attended;
    }
}

$total_missed       = $total_classes - $total_attended;
$overall_percentage = $total_classes > 0 ? round(($total_attended / $total_classes) * 100, 2) : 0;

// ════════════════════════════════════════════════════════════
// TASK 2 — LAST 7 DAYS
// ════════════════════════════════════════════════════════════
$last7 = [];
for ($i = 6; $i >= 0; $i--) {
    $date    = date("Y-m-d", strtotime("-$i days"));
    $label   = date("D", strtotime("-$i days"));
    $sql_day = $conn->query("SELECT COUNT(*) as count FROM attendance WHERE student_id = '$student_id' AND DATE(created_at) = '$date'");
    $count   = (int) mysqli_fetch_assoc($sql_day)['count'];
    $last7[] = ["date" => $date, "day" => $label, "present" => $count > 0, "count" => $count];
}

// ════════════════════════════════════════════════════════════
// TASK 3 — ACTIVE SESSION
// ════════════════════════════════════════════════════════════
$now      = date("Y-m-d H:i:s");
$sql_sess = $conn->query(
    "SELECT id, session_code, created_at FROM qrcode
     WHERE is_active = 1
     AND created_at >= DATE_SUB('$now', INTERVAL 2 HOUR)
     ORDER BY created_at DESC LIMIT 1"
);

$session_active = false;
$session_info   = null;

if ($sql_sess && mysqli_num_rows($sql_sess) > 0) {
    $sess           = mysqli_fetch_assoc($sql_sess);
    $session_active = true;
    $session_info   = [
        "session_id"   => $sess["id"],
        "session_code" => $sess["session_code"],
        "created_at"   => $sess["created_at"],
    ];
}

// ════════════════════════════════════════════════════════════
// RESPONSE
// ════════════════════════════════════════════════════════════
echo json_encode([
    "status"     => "success",
    "overall"    => [
        "total_classes" => $total_classes,
        "attended"      => $total_attended,
        "missed"        => $total_missed,
        "percentage"    => $overall_percentage,
    ],
    "courses"    => $course_stats,
    "last_7_days"=> $last7,
    "session"    => ["active" => $session_active, "details" => $session_info],
]);

$conn->close();
exit;
?>
