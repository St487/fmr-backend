<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

date_default_timezone_set("Asia/Kuala_Lumpur");

// ============================
// LOAD PHPMailer
// ============================
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/phpmailer/phpmailer/src/Exception.php';
require 'vendor/phpmailer/phpmailer/src/PHPMailer.php';
require 'vendor/phpmailer/phpmailer/src/SMTP.php';

include 'config.php';

// ============================
// GET INPUT
// ============================
$data = json_decode(file_get_contents("php://input"), true);
$email = trim($data['email'] ?? '');

if (empty($email)) {
    echo json_encode(["status" => "error", "message" => "Email required"]);
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["status" => "error", "message" => "Invalid email"]);
    exit();
}

// ============================
// CHECK IF EMAIL ALREADY EXISTS
// ============================
$stmt = $conn->prepare("SELECT user_id FROM user WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    echo json_encode([
        "status" => "error",
        "message" => "Email already registered"
    ]);
    exit();
}

// ============================
// GENERATE CODE
// ============================
$code = rand(1000, 9999);
$expires_at = date("Y-m-d H:i:s", strtotime("+5 minutes"));

// DELETE OLD CODE
$stmt = $conn->prepare("DELETE FROM verification_codes WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();

// INSERT NEW CODE
$stmt = $conn->prepare("INSERT INTO verification_codes (email, code, expires_at) VALUES (?, ?, ?)");
$stmt->bind_param("sss", $email, $code, $expires_at);
$stmt->execute();

// ============================
// SEND EMAIL USING GMAIL SMTP
// ============================
$mail = new PHPMailer(true);

try {
    // SMTP CONFIG
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;

    $mail->Username   = 'fixmyroad.app.noreply@gmail.com';
    $mail->Password   = 'aned ubet uujd ejuf';

    $mail->SMTPSecure = 'tls';
    $mail->Port       = 587;

    // EMAIL DETAILS
    $mail->setFrom('fixmyroad.app.noreply@gmail.com', 'Fix My Road');
    $mail->addAddress($email);

    $mail->isHTML(true);
    $mail->Subject = 'Your Verification Code';

    $mail->Body = "
        <h3>Verification Code</h3>
        <p>Your code is:</p>
        <h1>$code</h1>
        <p>This code will expire in 5 minutes.</p>
    ";

    $mail->AltBody = "Your verification code is: $code";

    $mail->send();

    echo json_encode([
        "status" => "success",
        "message" => "Verification code sent"
    ]);

} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => "Mailer Error: " . $mail->ErrorInfo
    ]);
}

$conn->close();
?>