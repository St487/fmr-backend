<?php
header("Content-Type: application/json");
include "config.php";

$data = json_decode(file_get_contents("php://input"), true);

$user_id = $data['user_id'];
$current_password = $data['current_password'];

$query = $conn->prepare("SELECT password FROM user WHERE user_id = ?");
$query->bind_param("i", $user_id);
$query->execute();
$result = $query->get_result();

if ($row = $result->fetch_assoc()) {
    if (password_verify($current_password, $row['password'])) {
        echo json_encode([
            "status" => "success",
            "message" => "Password verified"
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Incorrect current password"
        ]);
    }
} else {
    echo json_encode([
        "status" => "error",
        "message" => "User not found"
    ]);
}
?>