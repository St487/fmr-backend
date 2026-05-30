<?php
header("Access-Control-Allow-Origin: *");
header('Content-Type: application/json');
include 'config.php';

$data = json_decode(file_get_contents("php://input"), true);

$user_id = $data['user_id'] ?? null;
$first_name = trim($data['first_name'] ?? '');
$last_name = trim($data['last_name'] ?? '');
$address = trim($data['address'] ?? '');
$postal_code = trim($data['postal_code'] ?? '');
$state = trim($data['state'] ?? '');
$city = trim($data['city'] ?? '');

// Validation
if (!$user_id || empty($first_name) || empty($last_name) || empty($address) || empty($postal_code) || empty($state) || empty($city)) {
    echo json_encode(['status' => 'error', 'message' => 'Please complete all fields']);
    exit;
}

// Postal code validation
if (!preg_match('/^\d{5}$/', $postal_code)) {
    echo json_encode(['status' => 'error', 'message' => 'Postal code must be 5 digits']);
    exit;
}

// Optional profile picture
$profile_picture = null;
if (isset($_FILES['profile_picture'])) {
    $targetDir = "uploads/";
    $fileName = basename($_FILES["profile_picture"]["name"]);
    $targetFilePath = $targetDir . time() . "_" . $fileName;

    if (move_uploaded_file($_FILES["profile_picture"]["tmp_name"], $targetFilePath)) {
        $profile_picture = $targetFilePath;
    }
}

// Build query
if ($profile_picture) {
    $stmt = $conn->prepare("UPDATE user SET first_name=?, last_name=?, address=?, postal_code=?, state=?, city=?, profile_picture=?, updated_at=NOW() WHERE user_id=?");
    $stmt->bind_param("sssssssi", $first_name, $last_name, $address, $postal_code, $state, $city, $profile_picture, $user_id);
} else {
    $stmt = $conn->prepare("UPDATE user SET first_name=?, last_name=?, address=?, postal_code=?, state=?, city=?, updated_at=NOW() WHERE user_id=?");
    $stmt->bind_param("ssssssi", $first_name, $last_name, $address, $postal_code, $state, $city, $user_id);
}

// Execute
if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Profile updated successfully']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to update profile']);
}
?>