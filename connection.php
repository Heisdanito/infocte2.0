<?php
$db_host = "mysql-291ab10a-heisdanito-7ee7.b.aivencloud.com";
$db_user = "avnadmin";
$db_psw  = "AVNS_ZFYiFvpqdF-G5jN0vXu";
$db_name = "defaultdb";
$port    = 21225;

try {
    $conn = new mysqli($db_host, $db_user, $db_psw, $db_name, $port);

    // Aiven requires SSL
    $conn->ssl_set(NULL, NULL, '/app/ca.pem', NULL, NULL);

    if ($conn->connect_error) {
        echo json_encode([
            "status"  => "error",
            "message" => "Connection failed: " . $conn->connect_error
        ]);
        exit;
    }

} catch (mysqli_sql_exception $e) {
    echo json_encode([
        "status"  => "error",
        "message" => $e->getMessage()
    ]);
    exit;
}
?>
