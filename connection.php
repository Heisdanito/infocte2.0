<?php
$db_name = "infoctess";
$db_psw  = "";
$db_host = "localhost";
$db_user = "root";
$conn;
try{
    $conn = new mysqli($db_host, $db_user, $db_psw, $db_name);
}catch(mysqli_sql_exception){
    echo json_encode([
        "status" => "error",
        "message" => "Database connection failed: " . $conn->connect_error
    ]);
}
// Check connection
if ($conn->connect_error) {

    // Return JSON error instead of HTML


    echo json_encode([
        "status" => "error",
        "message" => "Database connection failed: " . $conn->connect_error
    ]);
    exit; // stop execution
}


?>