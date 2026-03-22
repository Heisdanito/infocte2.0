<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") { http_response_code(200); exit(); }



header("Content-Type: application/json");
session_start();
require_once __DIR__ . '/connection.php'; // adjust path

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        "status"  => "failed",
        "message" => "POST request only"
    ]);
    exit;
}

// Get JSON body
$input = json_decode(file_get_contents("php://input"), true);
$student_id    = $input['student_id'] ?? null;
$student_email = $input['student_email'] ?? null;

// Basic validation
if (!$student_id || !$student_email) {
    echo json_encode([
        "status"  => "failed",
        "message" => "Missing  student_id or student_email"
    ]);
    exit;
}

try{
        // Check against database
    $stmt = $conn->prepare("SELECT * FROM students WHERE student_id = ? ");
    $stmt->bind_param("s", $student_email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        // Valid login → store in session
        $_SESSION['student_id']    = $student_id;
        $_SESSION['student_email'] = $student_email;

        echo json_encode([
            "status"        => "success",
            "student_id"    => $student_id,
            "student_email" => $student_email,
            "message"       => "Login successful"
        ]);
    } else {
        echo json_encode([
            "status"  => "failed",
            "message" => "Invalid student_id or email logins"
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        "status" => "failed",
        "message" => $e->getMessage()
    ]);
}

?>
