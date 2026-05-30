<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
include 'config.php';

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Invalid request method."]);
    exit;
}

// Get and validate POST fields
$user_id      = isset($_POST['user_id']) && is_numeric($_POST['user_id']) ? (int)$_POST['user_id'] : null;
$title        = isset($_POST['title']) ? trim($_POST['title']) : null;
$description  = isset($_POST['description']) ? trim($_POST['description']) : null;
$issue_type   = isset($_POST['type']) ? trim($_POST['type']) : null;
$location_text= isset($_POST['address']) ? trim($_POST['address']) : null;
$latitude     = isset($_POST['latitude']) && is_numeric($_POST['latitude']) ? (float)$_POST['latitude'] : null;
$longitude    = isset($_POST['longitude']) && is_numeric($_POST['longitude']) ? (float)$_POST['longitude'] : null;

// Validate required fields
if (!$user_id || !$title || !$description || !$issue_type || !$location_text || $latitude === null || $longitude === null) {
    echo json_encode(["status" => "error", "message" => "All required fields must be filled."]);
    exit;
}

// Handle image uploads
$photos = ['photo1' => null, 'photo2' => null, 'photo3' => null];
if (!isset($_FILES['images']) || empty($_FILES['images']['name'][0])) {
    echo json_encode(["status" => "error", "message" => "At least one photo is required."]);
    exit;
}

$targetDir = "uploads/reports/";
if (!file_exists($targetDir)) {
    mkdir($targetDir, 0777, true);
}

foreach ($_FILES['images']['tmp_name'] as $key => $tmpName) {
    if ($key > 2) break; // Only allow 3 photos
    $originalName = $_FILES['images']['name'][$key];
    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
    $fileName = time() . '_' . uniqid() . '.' . $extension;
    $targetFilePath = $targetDir . $fileName;

    if (move_uploaded_file($tmpName, $targetFilePath)) {
        $photos["photo" . ($key + 1)] = $targetFilePath;
    }
}

// Ensure at least photo1 exists
if (!$photos['photo1']) {
    echo json_encode(["status" => "error", "message" => "Failed to upload the first photo."]);
    exit;
}


// Insert into database
$stmt = $conn->prepare("INSERT INTO report (user_id, updated_by, issue_type, title, description, location_text, latitude, longitude, photo1, photo2, photo3, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', NOW(), NOW())");
if (!$stmt) {
    echo json_encode(["status" => "error", "message" => "Prepare failed: " . $conn->error]);
    exit;
}

$updated_by = $user_id; // initially same as user
$stmt->bind_param(
    "iissssddsss",
    $user_id,
    $updated_by,
    $issue_type,
    $title,
    $description,
    $location_text,
    $latitude,
    $longitude,
    $photos['photo1'],
    $photos['photo2'],
    $photos['photo3']
);

if ($stmt->execute()) {
    echo json_encode(["status" => "success", "message" => "Report submitted successfully."]);
} else {
    echo json_encode(["status" => "error", "message" => "Failed to submit report: " . $stmt->error]);
}

$stmt->close();
$conn->close();
?>