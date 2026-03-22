<?php
$db_name = "if0_41221533_heis_infotess";
$db_psw  = "02omExnoeMFQQQX";
$db_host = "sql302.infinityfree.com";
$db_user = "if0_41221533";
$port = 3306;
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
}ele{
        echo 'connected'
    }


?>
