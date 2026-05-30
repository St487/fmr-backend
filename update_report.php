<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Invalid request method."]);
    exit;
}

// Required fields
$report_id     = isset($_POST['report_id']) && is_numeric($_POST['report_id']) ? (int)$_POST['report_id'] : null;
$user_id       = isset($_POST['user_id']) && is_numeric($_POST['user_id']) ? (int)$_POST['user_id'] : null;
$title         = isset($_POST['title']) ? trim($_POST['title']) : null;
$description   = isset($_POST['description']) ? trim($_POST['description']) : null;
$issue_type    = isset($_POST['type']) ? trim($_POST['type']) : null;
$location_text = isset($_POST['address']) ? trim($_POST['address']) : null;
$latitude      = isset($_POST['latitude']) && is_numeric($_POST['latitude']) ? (float)$_POST['latitude'] : null;
$longitude     = isset($_POST['longitude']) && is_numeric($_POST['longitude']) ? (float)$_POST['longitude'] : null;

// Existing photos array
$existingPhotos = isset($_POST['existing_photos']) ? $_POST['existing_photos'] : [];
if (!is_array($existingPhotos)) $existingPhotos = [$existingPhotos];

// Check required fields
if (!$report_id || !$user_id || !$title || !$description || !$issue_type || !$location_text || $latitude === null || $longitude === null) {
    echo json_encode(["status" => "error", "message" => "All required fields must be filled."]);
    exit;
}

$checkStmt = $conn->prepare("SELECT photo1, photo2, photo3 FROM report WHERE report_id = ?");
$checkStmt->bind_param("i", $report_id);
$checkStmt->execute();
$currentData = $checkStmt->get_result()->fetch_assoc();
$checkStmt->close();

// Initialize slots with current DB values
$photoSlots = [
    'photo1' => $currentData['photo1'],
    'photo2' => $currentData['photo2'],
    'photo3' => $currentData['photo3']
];

// 2. Handle Deletions (The client should send a list of photos to KEEP)
// If a photo isn't in the 'existing_photos' array, we set that slot to null
foreach ($photoSlots as $slot => $path) {
    if ($path && !in_array($path, $existingPhotos)) {
        // Optional: unlink(realpath($path)); // Delete file from server
        $photoSlots[$slot] = null;
    }
}

// 3. Handle newly uploaded images (Fill ONLY the truly empty slots)
if (isset($_FILES['images'])) {
    $targetDir = "uploads/reports/";
    if (!file_exists($targetDir)) mkdir($targetDir, 0777, true);

    foreach ($_FILES['images']['tmp_name'] as $key => $tmpName) {
        // Find the next available null slot
        $emptySlot = array_search(null, $photoSlots);
        
        if ($emptySlot !== false) {
            $originalName = $_FILES['images']['name'][$key];
            $extension = pathinfo($originalName, PATHINFO_EXTENSION);
            $fileName = time() . '_' . uniqid() . '.' . $extension;
            $targetFilePath = $targetDir . $fileName;

            if (move_uploaded_file($tmpName, $targetFilePath)) {
                $photoSlots[$emptySlot] = $targetFilePath;
            }
        }
    }
}

// 3. Build update statement
$updateFields = "issue_type=?, title=?, description=?, location_text=?, latitude=?, longitude=?, updated_at=NOW(), updated_by=?, photo1=?, photo2=?, photo3=?";
$params = [
    $issue_type,
    $title,
    $description,
    $location_text,
    $latitude,
    $longitude,
    $user_id,
    $photoSlots['photo1'],
    $photoSlots['photo2'],
    $photoSlots['photo3']
];

$stmt = $conn->prepare("UPDATE report SET $updateFields WHERE report_id=?");
$params[] = $report_id;

// 4. Bind types dynamically
$types = '';
foreach ($params as $param) {
    if (is_int($param)) $types .= 'i';
    elseif (is_float($param)) $types .= 'd';
    else $types .= 's';
}
$stmt->bind_param($types, ...$params);

// 5. Execute and return response
if ($stmt->execute()) {
    echo json_encode(["status" => "success", "message" => "Report updated successfully."]);
} else {
    echo json_encode(["status" => "error", "message" => "Failed to update report: ".$stmt->error]);
}

$stmt->close();
$conn->close();
?>