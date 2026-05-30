<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");

include 'config.php';

// Get user_id (optional filter)
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;

$sql = "SELECT report_id, title, description, location_text, issue_type, status, created_at, 
               photo1, photo2, photo3, rejection_reason
        FROM report";

if ($user_id) {
    $sql .= " WHERE user_id = $user_id";
}

$sql .= " ORDER BY created_at DESC";

$result = $conn->query($sql);

$reports = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $reports[] = [
            "id" => $row['report_id'],
            "issue_type" => $row['issue_type'],
            "title" => $row['title'],
            "description" => $row['description'],
            "location" => $row['location_text'],
            "status" => $row['status'],
            "created_at" => $row['created_at'],
            "date" => date("M d, Y", strtotime($row['created_at'])),
            "rejection_reason" => $row['rejection_reason'] ?? "",

            "photos" => [
                $row['photo1'] ?? "",
                $row['photo2'] ?? "",
                $row['photo3'] ?? ""
            ]
        ];
    }
}

echo json_encode([
    "status" => "success",
    "data" => $reports
]);

$conn->close();
?>