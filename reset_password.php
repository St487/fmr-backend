<?php
include "config.php";

$data = json_decode(file_get_contents("php://input"), true);

$email = $data['email'] ?? '';
$password = $data['password'] ?? '';

if (empty($email) || empty($password)) {
    echo json_encode(["success" => false, "message" => "Missing data"]);
    exit;
}

// hash password
$hashed = password_hash($password, PASSWORD_BCRYPT);

// update user password
$stmt = $conn->prepare("UPDATE user SET password=? WHERE email=?");
$stmt->bind_param("ss", $hashed, $email);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "Password updated"]);
} else {
    echo json_encode(["success" => false, "message" => "Update failed"]);
}
?>