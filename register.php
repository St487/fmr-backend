<?php
header("Access-Control-Allow-Origin: *");
header('Content-Type: application/json');

include 'config.php';

$data = json_decode(file_get_contents("php://input"), true);

$email = trim($data['email'] ?? '');
$phone_no = trim($data['phone_no'] ?? '');
$password = trim($data['password'] ?? '');
$verification_code = trim($data['verification_code'] ?? '');

// ============================
// VALIDATION
// ============================
if (empty($email) || empty($phone_no) || empty($password) || empty($verification_code)) {
    echo json_encode(['status' => 'error', 'message' => 'Please fill all fields']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid email']);
    exit;
}

// ============================
// CHECK VERIFICATION CODE
// ============================
$stmt = $conn->prepare("
    SELECT id FROM verification_codes 
    WHERE email = ? AND code = ? AND expires_at > NOW()
");
$stmt->bind_param("ss", $email, $verification_code);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid or expired verification code'
    ]);
    exit;
}

// ============================
// CHECK EMAIL EXIST
// ============================
$stmt = $conn->prepare("SELECT user_id FROM user WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Email already registered'
    ]);
    exit;
}

// ============================
// HASH PASSWORD
// ============================
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// ============================
// INSERT USER
// ============================
$stmt = $conn->prepare("
    INSERT INTO user (email, phone_no, password, created_at, updated_at) 
    VALUES (?, ?, ?, NOW(), NOW())
");
$stmt->bind_param("sss", $email, $phone_no, $hashedPassword);

if ($stmt->execute()) {
    $user_id = $conn->insert_id;

    // ============================
    // DELETE USED CODE (IMPORTANT)
    // ============================
    $stmt = $conn->prepare("DELETE FROM verification_codes WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();

    echo json_encode([
        'status' => 'success',
        'message' => 'Registration successful',
        'user_id' => $user_id,
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to register user'
    ]);
}
?>