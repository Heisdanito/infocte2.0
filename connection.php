<?php
$db_name = "defaultdb";
$db_psw  = "<redacted>";
$db_host = "mysql-291ab10a-heisdanito-7ee7.b.aivencloud.com";
$db_user = "avnadmin";
$port = 21225;
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
}else{
        echo 'connected';
    }


?>
