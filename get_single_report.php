<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");

include 'config.php';

// Validate ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid or missing report ID"
    ]);
    exit;
}

$report_id = (int)$_GET['id'];

// Prepare query (SAFE)
$stmt = $conn->prepare("
    SELECT 
        report_id,
        title,
        description,
        issue_type,
        location_text,
        latitude,
        longitude,
        status,
        created_at,
        photo1,
        photo2,
        photo3
    FROM report
    WHERE report_id = ?
");

$stmt->bind_param("i", $report_id);
$stmt->execute();

$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {

    // Convert photos into array (remove empty ones)
    $photos = [];
    if (!empty($row['photo1'])) $photos[] = $row['photo1'];
    if (!empty($row['photo2'])) $photos[] = $row['photo2'];
    if (!empty($row['photo3'])) $photos[] = $row['photo3'];

    // Format response (MATCH YOUR FLUTTER)
    $report = [
        "id" => (int)$row['report_id'],
        "title" => $row['title'],
        "description" => $row['description'],
        "issue_type" => $row['issue_type'],
        "location_text" => $row['location_text'],
        "latitude" => $row['latitude'],
        "longitude" => $row['longitude'],
        "status" => $row['status'],
        "date" => date("M d, Y", strtotime($row['created_at'])),
        "photos" => $photos
    ];

    echo json_encode([
        "status" => "success",
        "data" => $report
    ]);

} else {
    echo json_encode([
        "status" => "error",
        "message" => "Report not found"
    ]);
}

$stmt->close();
$conn->close();
?>