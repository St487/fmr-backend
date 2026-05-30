<?php
header('Content-Type: application/json');
include 'config.php';

$user_id   = isset($_POST['user_id']) ? $_POST['user_id'] : null;
$latitude  = isset($_POST['latitude']) ? floatval($_POST['latitude']) : null;
$longitude = isset($_POST['longitude']) ? floatval($_POST['longitude']) : null;

if (!$user_id || !$latitude || !$longitude) {
    echo json_encode(["status" => "error", "message" => "Missing parameters"]);
    exit;
}

// Radius in km
$radius = 5;

// Fetch reports near user's location (within $radius km)
$query = "SELECT report_id, issue_type, status, latitude, longitude, photo1,
         (6371 * acos(
              cos(radians(?)) * cos(radians(latitude)) *
              cos(radians(longitude) - radians(?)) +
              sin(radians(?)) * sin(radians(latitude))
          )) AS distance
          FROM report
          WHERE (6371 * acos(
              cos(radians(?)) * cos(radians(latitude)) *
              cos(radians(longitude) - radians(?)) +
              sin(radians(?)) * sin(radians(latitude))
          )) <= ?
          ORDER BY distance ASC";

$stmt = $conn->prepare($query);
$stmt->bind_param("ddddddi", $latitude, $longitude, $latitude, $latitude, $longitude, $latitude, $radius);
$stmt->execute();
$result = $stmt->get_result();

$issues = [];
while ($row = $result->fetch_assoc()) {
    // Calculate distance (optional)
    $latFrom = deg2rad($latitude);
    $lonFrom = deg2rad($longitude);
    $latTo = deg2rad($row['latitude']);
    $lonTo = deg2rad($row['longitude']);
    $earthRadius = 6371; // km

    $distance = acos(sin($latFrom) * sin($latTo) +
                     cos($latFrom) * cos($latTo) *
                     cos($lonTo - $lonFrom)) * $earthRadius;

    $issues[] = [
        "id" => $row['report_id'],
        "issue_type" => $row['issue_type'],
        "status" => $row['status'],
        "distance" => round($distance, 2) . " km",
        "icon" => $row['photo1'] ? $row['photo1'] : "default_icon.png"
    ];
}

echo json_encode(["status" => "success", "issues" => $issues]);
?>