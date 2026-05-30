<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

date_default_timezone_set("Asia/Kuala_Lumpur");

include "config.php";

// ============================
// GET INPUT
// ============================
$data = json_decode(file_get_contents("php://input"), true);

$email = trim($data['email'] ?? '');
$code  = trim($data['code'] ?? '');

if (empty($email) || empty($code)) {
    echo json_encode([
        "status" => "error",
        "message" => "Email and code required"
    ]);
    exit();
}

// ============================
// FIND LATEST CODE
// ============================
$stmt = $conn->prepare("
    SELECT code, expires_at 
    FROM verification_codes 
    WHERE email = ? 
    ORDER BY id DESC 
    LIMIT 1
");

$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

// ============================
// CHECK IF EXISTS
// ============================
if ($result->num_rows == 0) {
    echo json_encode([
        "status" => "error",
        "message" => "No verification code found"
    ]);
    exit();
}

$row = $result->fetch_assoc();

// ============================
// CHECK CODE MATCH
// ============================
if ($row['code'] != $code) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid code"
    ]);
    exit();
}

// ============================
// CHECK EXPIRY
// ============================
if (strtotime($row['expires_at']) < time()) {
    echo json_encode([
        "status" => "error",
        "message" => "Code expired"
    ]);
    exit();
}

// ============================
// SUCCESS
// ============================
echo json_encode([
    "status" => "success",
    "message" => "Code verified"
]);

$conn->close();
?>