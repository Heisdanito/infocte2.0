<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER["REQUEST_METHOD"] !== "OPTIONS") { http_response_code(200); exit(); }


session_start();
header("Content-Type: application/json");
require_once '..../../connection.php'; // adjust path

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
$student_id    = $input['student_id'] ?? null ;// 5262140032;
$student_email = $input['student_email'] ?? null;

//Basic validation
if (!$student_id || !$student_email) {
    echo json_encode([
        "status"  => "failed",
        "message" => "Missing student_id or student_email"
    ]);
    exit;
}



    if(isset($student_id)){  
                $sql = $conn->query("SELECT student_name, student_id, roles ,updated_at ,group_id  FROM students WHERE student_id = '$student_id' ");
                if(mysqli_num_rows($sql) > 0){
                    while($row = mysqli_fetch_assoc($sql)){
                        //$student_name = $row['student_id'];
                        $roles['role'] = $row['roles'];
                        $last_login  = $row['updated_at'];
                        $student_name = $row['student_name'];
                        $mygroup = $row['group_id'];

                        
                        $conn->query( 
                         "UPDATE students
                                SET updated_at = NOW()
                                WHERE student_id = '$student_id' "
                            );

                        $created_at = new DateTime($row['updated_at']);
                        $now = new DateTime();
                        $diff = $now->diff($created_at);
                    
                        if ($diff->y > 0) {
                            $timeAgo = $diff->y . " year" . ($diff->y > 1 ? "s" : "") . " ago";
                        } elseif ($diff->m > 0) {
                            $timeAgo = $diff->m . " month" . ($diff->m > 1 ? "s" : "") . " ago";
                        } elseif ($diff->d > 0) {
                            $timeAgo = $diff->d . " day" . ($diff->d > 1 ? "s" : "") . " ago";
                        } elseif ($diff->h > 0) {
                            $timeAgo = $diff->h . " hour" . ($diff->h > 1 ? "s" : "") . " ago";
                        } elseif ($diff->i > 0) {
                            $timeAgo = $diff->i . " minute" . ($diff->i > 1 ? "s" : "") . " ago";
                        } else {
                            $timeAgo = "just now";
                        }
                    

                        // Step 2: Check if this group already has an active QR session
                        $checkActive = $conn->query("SELECT * FROM qrcode 
                                                     WHERE created_by = '$student_id' 
                                                     AND is_active = 1 
                                                     AND group_id = '$mygroup'");
                    
                        if ($checkActive && mysqli_num_rows($checkActive) > 0) {
                            // Already has active session → return it
                            $row = mysqli_fetch_assoc($checkActive);
                            $_QRCode['qr_session'] = $row['session_code'];

                            
                            
                            echo json_encode([
                                "status" => "fetched",
                                "username" => "$student_name",
                                "lastlogin" => "$timeAgo",
                                "stu_id" => "$student_id",
                                "group_id" => "$mygroup",
                            ]);
                        
                        }else{
                            echo json_encode([
                                "status" => "fetched",
                                "username" => "$student_name",
                                "lastlogin" => "$timeAgo",
                                "stu_id" => "$student_id",
                                "group_id" => "$mygroup",
                                "code_b" => "none"
                            ]);
                        }

                    }


                
                }else{
                    echo "error";
                }
            }

    else{
    
    
    }


    


// try{
//         // Check against database
//     $stmt = $conn->prepare("SELECT * FROM students WHERE student_id = ? AND student_mail = ?");
//     $stmt->bind_param("ss", $student_id, $student_email);
//     $stmt->execute();
//     $result = $stmt->get_result();

//     if ($result && $result->num_rows > 0) {
//         // Valid login → store in session
//         $_SESSION['student_id']    = $student_id;
//         $_SESSION['student_email'] = $student_email;

//         echo json_encode([
//             "status"        => "success",
//             "student_id"    => $student_id,
//             "student_email" => $student_email,
//             "message"       => "Login successful"
//         ]);
//     } else {
//         echo json_encode([
//             "status"  => "failed",
//             "message" => "Invalid student_id or email"
//         ]);
//     }
// } catch (Exception $e) {
//     echo json_encode([
//         "status" => "failed",
//         "message" => $e->getMessage()
//     ]);
// }

// ?>
