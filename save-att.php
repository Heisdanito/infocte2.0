<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
ini_set('display_errors', 0);
ini_set('log_errors', 1);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

require_once __DIR__ . '/connection.php';

$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    echo json_encode(["status" => "failed", "message" => "Invalid JSON"]);
    exit;
}

$student_id = $data['student_id'] ?? null;
$qrcode     = $data['qrcode']     ?? null;
$latitude   = $data['latitude']   ?? null;
$longitude  = $data['longitude']  ?? null;

if (!$student_id || !$qrcode || !$latitude || !$longitude) {
    echo json_encode(["status" => "failed", "message" => "student_id, qrcode, latitude and longitude are required"]);
    exit;
}

$student_id = mysqli_real_escape_string($conn, $student_id);
$qrcode     = mysqli_real_escape_string($conn, $qrcode);
$latitude   = mysqli_real_escape_string($conn, $latitude);
$longitude  = mysqli_real_escape_string($conn, $longitude);

$sql = mysqli_query($conn, "SELECT group_id, programme FROM students WHERE student_id = '$student_id' AND active = '1'");
if (!$sql || mysqli_num_rows($sql) === 0) {
    echo json_encode(["status" => "failed", "message" => "Student not found or inactive"]);
    exit;
}
$student          = mysqli_fetch_assoc($sql);
$student_group_id = $student['group_id'];
$student_prog     = $student['programme'];

$sql_group = mysqli_query($conn, "SELECT group_id FROM groups WHERE group_id = '$student_group_id' AND programme_id = '$student_prog'");
if (!$sql_group || mysqli_num_rows($sql_group) === 0) {
    echo json_encode(["status" => "failed", "message" => "Group and programme mismatch"]);
    exit;
}

$sql_qr = mysqli_query($conn, "SELECT * FROM qrcode WHERE group_id = '$student_group_id' AND is_active = '1' AND (session_code = '$qrcode' OR qrcode = '$qrcode')");
if (!$sql_qr || mysqli_num_rows($sql_qr) === 0) {
    echo json_encode(["status" => "failed", "message" => "No active session found for your group or session has expired"]);
    exit;
}
$qr           = mysqli_fetch_assoc($sql_qr);
$course_id    = $qr['course_id'];
$session_code = $qr['session_code'];
$qr_lat       = floatval($qr['latitude']);
$qr_lon       = floatval($qr['longitude']);

function haversine($lat1, $lon1, $lat2, $lon2) {
    $R    = 6371000;
    $dLat = deg2rad(floatval($lat2) - floatval($lat1));
    $dLon = deg2rad(floatval($lon2) - floatval($lon1));
    $a    = sin($dLat/2) * sin($dLat/2) + cos(deg2rad(floatval($lat1))) * cos(deg2rad(floatval($lat2))) * sin($dLon/2) * sin($dLon/2);
    return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

$distance = haversine(floatval($latitude), floatval($longitude), $qr_lat, $qr_lon);

if ($distance > 500) {
    echo json_encode(["status" => "failed", "message" => "You are too far from the class location.", "distance_meters" => round($distance, 2), "allowed_meters" => 500]);
    exit;
}

// Lock the row for this student + session to block any race condition or double tap
$conn->begin_transaction();

try {
    // Re-check inside transaction with a row lock — prevents duplicate insert
    // from two simultaneous requests hitting at the exact same millisecond
    $lock_check = $conn->query(
        "SELECT id FROM attendance
         WHERE student_id = '$student_id'
         AND session_code = '$session_code'
         LIMIT 1
         FOR UPDATE"
    );

    if ($lock_check && $lock_check->num_rows > 0) {
        $conn->rollback();
        echo json_encode([
            "status"  => "success",
            "message" => "Attendance already recorded for this session",
            "data"    => ["student_id" => $student_id, "session_code" => $session_code, "course_id" => $course_id, "distance_meters" => round($distance, 2)]
        ]);
        exit;
    }

    $stmt = $conn->prepare(
        "INSERT INTO attendance (student_id, group_id, course_id, session_code, qrcode, latitude, longitude, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
    );

    if (!$stmt) {
        $conn->rollback();
        echo json_encode(["status" => "failed", "message" => "Prepare error: " . $conn->error]);
        exit;
    }

    $stmt->bind_param("sssssss", $student_id, $student_group_id, $course_id, $session_code, $qrcode, $latitude, $longitude);

    if (!$stmt->execute()) {
        $conn->rollback();
        echo json_encode(["status" => "failed", "message" => "Insert failed: " . $stmt->error]);
        exit;
    }

    $conn->commit();
    $stmt->close();

    echo json_encode([
        "status"  => "success",
        "message" => "Attendance recorded successfully",
        "data"    => ["student_id" => $student_id, "group_id" => $student_group_id, "course_id" => $course_id, "session_code" => $session_code, "distance_meters" => round($distance, 2)]
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(["status" => "failed", "message" => "Transaction error: " . $e->getMessage()]);
}

$conn->close();
?>
