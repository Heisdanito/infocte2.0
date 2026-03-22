<?php
$db_host = "mysql-291ab10a-heisdanito-7ee7.b.aivencloud.com";
$db_user = "avnadmin";
$db_psw  = "AVNS_ZFYiFvpqdF-G5jN0vXu";
$db_name = "defaultdb";
$port    = 21225;

try {
    // SSL must be configured BEFORE connecting
    $conn = new mysqli();
    $conn->ssl_set(NULL, NULL, __DIR__ . '/ca.pem', NULL, NULL);
    $conn->real_connect($db_host, $db_user, $db_psw, $db_name, $port, NULL, MYSQLI_CLIENT_SSL);

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
