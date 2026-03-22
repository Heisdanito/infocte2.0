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

require_once './connection.php'; // adjust path

// ── Read JSON body ─────────────────────────────────────────────────────────────
$rawData = file_get_contents("php://input");
$data    = json_decode($rawData, true);

error_log("get-location-nearby received: " . print_r($data, true));

if (!$data) {
    echo json_encode(["status" => "failed", "message" => "Invalid JSON"]);
    exit;
}

// ── Extract fields ─────────────────────────────────────────────────────────────
$student_id = $data['student_id'] ?? null;
$latitude   = $data['latitude']   ?? null;
$longitude  = $data['longitude']  ?? null;

if (!$student_id || !$latitude || !$longitude) {
    echo json_encode([
        "status"  => "failed",
        "message" => "student_id, latitude and longitude are required"
    ]);
    exit;
}

$student_id = mysqli_real_escape_string($conn, $student_id);

// ── Get student group ──────────────────────────────────────────────────────────
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

// ── Haversine ──────────────────────────────────────────────────────────────────
function haversine($lat1, $lon1, $lat2, $lon2) {
    $R    = 6371000;
    $lat1 = floatval($lat1); $lon1 = floatval($lon1);
    $lat2 = floatval($lat2); $lon2 = floatval($lon2);
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a    = sin($dLat/2) * sin($dLat/2)
          + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
          * sin($dLon/2) * sin($dLon/2);
    return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

// ── Fetch all active sessions for student's group ──────────────────────────────
$sql_qr = mysqli_query($conn,
    "SELECT id, session_code, course_id, latitude, longitude, created_at
     FROM qrcode
     WHERE group_id = '$student_group_id'
     AND is_active = '1'"
);

if (!$sql_qr) {
    echo json_encode(["status" => "failed", "message" => "Query error: " . mysqli_error($conn)]);
    exit;
}

$nearby_sessions = [];

while ($row = mysqli_fetch_assoc($sql_qr)) {
    $dist = haversine(
        floatval($latitude),
        floatval($longitude),
        floatval($row['latitude']),
        floatval($row['longitude'])
    );

    error_log("Session " . $row['session_code'] . " distance: " . round($dist, 2) . "m");

    // Only include sessions within 500m
    if ($dist <= 500) {

        // Check if student already recorded attendance for this session
        $sc           = mysqli_real_escape_string($conn, $row['session_code']);
        $sql_att      = mysqli_query($conn,
            "SELECT id FROM attendance
             WHERE student_id = '$student_id'
             AND session_code = '$sc'"
        );
        $already_done = ($sql_att && mysqli_num_rows($sql_att) > 0);

        $nearby_sessions[] = [
            "session_code"    => $row['session_code'],
            "course_id"       => $row['course_id'],
            "distance_meters" => round($dist, 2),
            "created_at"      => $row['created_at'],
            "already_recorded"=> $already_done,
        ];
    }
}

// Sort by closest first
usort($nearby_sessions, fn($a, $b) => $a['distance_meters'] <=> $b['distance_meters']);

echo json_encode([
    "status"          => "success",
    "student_id"      => $student_id,
    "group_id"        => $student_group_id,
    "your_location"   => [
        "latitude"  => floatval($latitude),
        "longitude" => floatval($longitude),
    ],
    "nearby_count"    => count($nearby_sessions),
    "nearby_sessions" => $nearby_sessions,
]);

$conn->close();
?>