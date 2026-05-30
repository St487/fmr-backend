<?php
header("Access-Control-Allow-Origin: *");
header('Content-Type: application/json');
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_picture'])) {

    $user_id = $_POST['user_id'] ?? null;

    if (!$user_id) {
        echo json_encode([
            "status" => "error",
            "message" => "User ID missing"
        ]);
        exit;
    }

    $targetDir = "uploads/profile_photo/";
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $fileName = time() . "_" . basename($_FILES["profile_picture"]["name"]);
    $targetFilePath = $targetDir . $fileName;

    if (move_uploaded_file($_FILES["profile_picture"]["tmp_name"], $targetFilePath)) {

        $stmt = $conn->prepare("UPDATE user SET profile_picture=? WHERE user_id=?");
        $stmt->bind_param("si", $targetFilePath, $user_id);

        if ($stmt->execute()) {
            echo json_encode([
                "status" => "success",
                "message" => "Profile picture updated",
                "profile_picture" => $targetFilePath
            ]);
        } else {
            echo json_encode([
                "status" => "error",
                "message" => "Database update failed"
            ]);
        }

    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Failed to upload image"
        ]);
    }

    exit;
}

$user_id = $_POST['user_id'] ?? null;

if (!$user_id) {
    $data = json_decode(file_get_contents("php://input"), true);
    $user_id = $data['user_id'] ?? null;
}

if (!$user_id) {
    echo json_encode([
        "status" => "error",
        "message" => "User ID missing"
    ]);
    exit;
}

$stmt = $conn->prepare("SELECT email, first_name, last_name, phone_no AS phone, address, postal_code, state, city, profile_picture FROM user WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $row = array_map(function($val) { return $val ?? ""; }, $row);

    echo json_encode([
        "status" => "success",
        "data" => $row
    ]);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "User not found"
    ]);
}
?>