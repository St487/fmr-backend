<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
include 'config.php';

$user_lat = isset($_GET['lat']) ? floatval($_GET['lat']) : null;
$user_lng = isset($_GET['lng']) ? floatval($_GET['lng']) : null;
$issueId = isset($_GET['id']) ? intval($_GET['id']) : null;

try {
    if ($issueId !== null) {
        // Fetch single issue by ID
        $stmt = $conn->prepare("SELECT report_id, user_id, updated_by, issue_type, title, description, 
                                       location_text, latitude, longitude, photo1, photo2, photo3, 
                                       status, created_at
                                FROM report
                                WHERE report_id=?");
        $stmt->bind_param("i", $issueId);
        $stmt->execute();
        $result = $stmt->get_result();
        $issue = $result->fetch_assoc();

        if ($issue) {
            // Calculate distance if lat/lng provided
            $distance = null;
            if ($user_lat !== null && $user_lng !== null) {
                $lat1 = deg2rad($user_lat);
                $lng1 = deg2rad($user_lng);
                $lat2 = deg2rad($issue['latitude']);
                $lng2 = deg2rad($issue['longitude']);

                $dlat = $lat2 - $lat1;
                $dlng = $lng2 - $lng1;

                $a = sin($dlat/2) * sin($dlat/2) +
                     cos($lat1) * cos($lat2) * sin($dlng/2) * sin($dlng/2);
                $c = 2 * atan2(sqrt($a), sqrt(1-$a));
                $distance = 6371 * $c; // km
            }
            $issue['distance'] = $distance !== null ? round($distance, 2) . " km" : null;

            echo json_encode(["status" => "success", "data" => $issue]);
        } else {
            echo json_encode(["status" => "error", "message" => "Issue not found"]);
        }

    } else {
        // Fetch all nearby issues
        $stmt = $conn->prepare("SELECT report_id, user_id, updated_by, issue_type, title, description, 
                                       location_text, latitude, longitude, photo1, photo2, photo3, 
                                       status, created_at
                                FROM report
                                WHERE status IN ('approved','in_progress')
                                ORDER BY created_at DESC");
        $stmt->execute();
        $result = $stmt->get_result();

        $issues = [];
        while ($row = $result->fetch_assoc()) {
            $distance = null;
            if ($user_lat !== null && $user_lng !== null) {
                $lat1 = deg2rad($user_lat);
                $lng1 = deg2rad($user_lng);
                $lat2 = deg2rad($row['latitude']);
                $lng2 = deg2rad($row['longitude']);

                $dlat = $lat2 - $lat1;
                $dlng = $lng2 - $lng1;

                $a = sin($dlat/2) * sin($dlat/2) +
                     cos($lat1) * cos($lat2) * sin($dlng/2) * sin($dlng/2);
                $c = 2 * atan2(sqrt($a), sqrt(1-$a));
                $distance = 6371 * $c; // km
            }

            $row['distance'] = $distance !== null ? round($distance, 2) . " km" : null;
            $issues[] = $row;
        }

        echo json_encode(["status" => "success", "data" => $issues]);
    }

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>