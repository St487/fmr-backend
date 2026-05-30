<?php
header("Content-Type: application/json");
include "config.php";

$data = json_decode(file_get_contents("php://input"), true);

$user_id = $data['user_id'];
$new_password = password_hash($data['new_password'], PASSWORD_DEFAULT);

$query = $conn->prepare("UPDATE user SET password = ? WHERE user_id = ?");
$query->bind_param("si", $new_password, $user_id);

if ($query->execute()) {
    echo json_encode([
        "status" => "success",
        "message" => "Password updated successfully"
    ]);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Failed to update password"
    ]);
}
?>