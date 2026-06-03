<?php
header("Access-Control-Allow-Origin: *");
header('Content-Type: application/json');
include 'config.php';

include 'cloudinary_upload.php';

$cloud_name = env('CLOUDINARY_CLOUD_NAME');
$upload_preset = "fmr_upload";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $user_id = $_POST['user_id'] ?? null;

    if (!$user_id) {
        echo json_encode([
            "status" => "error",
            "message" => "User ID missing"
        ]);
        exit;
    }

    // Get form fields
    $first_name = $_POST['first_name'] ?? "";
    $last_name = $_POST['last_name'] ?? "";
    $email = $_POST['email'] ?? "";
    $phone = $_POST['phone'] ?? "";
    $address = $_POST['address'] ?? "";
    $postal_code = $_POST['postal_code'] ?? "";
    $state = isset($_POST['state']) && $_POST['state'] !== '' ? $_POST['state'] : null;
    $city = isset($_POST['city']) && $_POST['city'] !== '' ? $_POST['city'] : null;

    // Handle image upload (optional)
    $profilePath = null;

    if (isset($_FILES['profile_image'])) {

        $result = uploadToCloudinary(
            $_FILES['profile_image']['tmp_name'],
            $cloud_name,
            $upload_preset
        );

        if (isset($result['secure_url'])) {
            $profilePath = $result['secure_url'];
        } else {
            error_log(json_encode($result));
        }
    }

    // Build SQL dynamically
    if ($profilePath) {
        $stmt = $conn->prepare("UPDATE user SET first_name=?, last_name=?, email=?, phone_no=?, address=?, postal_code=?, state=?, city=?, profile_picture=? WHERE user_id=?");
        $stmt->bind_param("sssssssssi", $first_name, $last_name, $email, $phone, $address, $postal_code, $state, $city, $profilePath, $user_id);
    } else {
        $stmt = $conn->prepare("UPDATE user SET first_name=?, last_name=?, email=?, phone_no=?, address=?, postal_code=?, state=?, city=? WHERE user_id=?");
        $stmt->bind_param("ssssssssi", $first_name, $last_name, $email, $phone, $address, $postal_code, $state, $city, $user_id);
    }

    if ($stmt->execute()) {
        echo json_encode([
            "status" => "success",
            "message" => $stmt->affected_rows > 0 
                ? "Profile updated successfully"
                : "No changes made",
            "first_name" => $first_name,
            "last_name" => $last_name,
            "email" => $email,
            "phone" => $phone,
            "address" => $address,
            "postal_code" => $postal_code,
            "state" => $state,
            "city" => $city,
            "profile_picture" => $profilePath
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Update failed"
        ]);
    }

}
?>