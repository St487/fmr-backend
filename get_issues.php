<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");

include 'config.php';

$sql = "SELECT * FROM report";
$result = $conn->query($sql);

$issues = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {

        $issues[] = [
            "id" => (int)$row["report_id"],
            "title" => $row["title"],
            "description" => $row["description"],
            "category" => $row["issue_type"],
            "status" => $row["status"],
            "latitude" => (double)$row["latitude"],
            "longitude" => (double)$row["longitude"],
            "created_at" => $row["created_at"],
            "photo1" => $row["photo1"],
            "photo2" => $row["photo2"],
            "photo3" => $row["photo3"],
        ];
    }
}

echo json_encode([
    "status" => "success",
    "data" => $issues
]);