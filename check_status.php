<?php
header("Content-Type: application/json");

include 'config.php';

if (!isset($_POST['user_id'])) {
    echo json_encode([
        "status" => "failed",
        "message" => "Missing user_id"
    ]);
    exit();
}

$user_id = $_POST['user_id'];

// Get all reports for this user
$sql = "SELECT report_id, issue_type,status, updated_at 
        FROM report 
        WHERE user_id = '$user_id'";

$result = $conn->query($sql);

$reports = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $reports[] = [
            "id" => $row['report_id'],  
            "issue_type" => $row['issue_type'],
            "status" => $row['status'],
            "updated_at" => $row['updated_at']
        ];
    }

    echo json_encode([
        "status" => "success",
        "issues" => $reports
    ]);
} else {
    echo json_encode([
        "status" => "success",
        "issues" => []
    ]);
}

$conn->close();
?>