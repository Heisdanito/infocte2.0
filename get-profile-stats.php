<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
ini_set('display_errors', 0); ini_set('log_errors', 1); error_reporting(E_ALL);
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

$db_host = "mysql-291ab10a-heisdanito-7ee7.b.aivencloud.com";
$db_user = "avnadmin"; $db_psw = "AVNS_ZFYiFvpqdF-G5jN0vXu";
$db_name = "defaultdb"; $port = 21225;
$ca_path = __DIR__ . '/ca.pem';
if (!file_exists($ca_path)) { echo json_encode(["status"=>"error","message"=>"ca.pem not found"]); exit; }
try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $conn = new mysqli();
    $conn->ssl_set(NULL, NULL, $ca_path, NULL, NULL);
    $conn->real_connect($db_host, $db_user, $db_psw, $db_name, $port, NULL, MYSQLI_CLIENT_SSL);
} catch (Exception $e) { echo json_encode(["status"=>"error","message"=>$e->getMessage()]); exit; }

$data       = json_decode(file_get_contents("php://input"), true);
$student_id = $data['student_id'] ?? $_GET['student_id'] ?? null;
if (!$student_id) { echo json_encode(["status"=>"failed","message"=>"student_id required"]); exit; }
$student_id = mysqli_real_escape_string($conn, $student_id);

$sql_stu = mysqli_query($conn, "SELECT group_id FROM students WHERE student_id='$student_id' AND active='1'");
if (!$sql_stu || mysqli_num_rows($sql_stu) === 0) { echo json_encode(["status"=>"failed","message"=>"Student not found"]); exit; }
$student_group_id = mysqli_fetch_assoc($sql_stu)['group_id'];

$sql_total      = mysqli_query($conn, "SELECT COUNT(*) AS c FROM qrcode WHERE group_id='$student_group_id'");
$total_sessions = intval(mysqli_fetch_assoc($sql_total)['c']);

$sql_att  = mysqli_query($conn, "SELECT COUNT(*) AS c FROM attendance WHERE student_id='$student_id'");
$attended = intval(mysqli_fetch_assoc($sql_att)['c']);
$missed   = max(0, $total_sessions - $attended);
$pct      = $total_sessions > 0 ? round(($attended / $total_sessions) * 100, 1) : 0;

function calculateRating($p) {
    if ($p>=90) return 5.0; if ($p>=80) return 4.5; if ($p>=75) return 4.0;
    if ($p>=65) return 3.5; if ($p>=60) return 3.0; if ($p>=50) return 2.5;
    if ($p>=40) return 2.0; if ($p>=30) return 1.5; return 1.0;
}

$sql_c = mysqli_query($conn, "SELECT course_id, COUNT(*) AS att FROM attendance WHERE student_id='$student_id' GROUP BY course_id");
$courses = [];
if ($sql_c) {
    while ($row = mysqli_fetch_assoc($sql_c)) {
        $cid   = mysqli_real_escape_string($conn, $row['course_id']);
        $ct    = intval(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM qrcode WHERE course_id='$cid' AND group_id='$student_group_id'"))['c']);
        $ca    = intval($row['att']);
        $cpct  = $ct > 0 ? round(($ca / $ct) * 100, 1) : 0;
        $courses[] = ["course_id"=>$row['course_id'],"attended"=>$ca,"missed"=>max(0,$ct-$ca),"total_sessions"=>$ct,"percentage"=>$cpct,"rating"=>calculateRating($cpct)];
    }
}

echo json_encode(["status"=>"success","student_id"=>$student_id,"group_id"=>$student_group_id,
    "stats"=>["total_sessions"=>$total_sessions,"attended"=>$attended,"missed"=>$missed,"percentage"=>$pct,"rating"=>calculateRating($pct)],
    "courses"=>$courses]);
$conn->close();
?>
