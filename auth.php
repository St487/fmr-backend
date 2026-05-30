<?php
header("Access-Control-Allow-Origin: *");
header('Content-Type: application/json');
include 'config.php';

$data = json_decode(file_get_contents("php://input"), true);

$email = trim($data['email'] ?? '');
$password = trim($data['password'] ?? '');

// Validation
if (empty($email) || empty($password)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Please enter email and password'
    ]);
    exit;
}

// Check user exists
$stmt = $conn->prepare("SELECT user_id, password FROM user WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {

    // Verify password
    if (password_verify($password, $row['password'])) {

        echo json_encode([
            'status' => 'success',
            'message' => 'Login successful',
            'user_id' => $row['user_id']
        ]);

    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Wrong email or password'
        ]);
    }

} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Wrong email or password'
    ]);
}
?>