<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

date_default_timezone_set("Asia/Kuala_Lumpur");
include "config.php";
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/phpmailer/phpmailer/src/Exception.php';
require 'vendor/phpmailer/phpmailer/src/PHPMailer.php';
require 'vendor/phpmailer/phpmailer/src/SMTP.php';

// ============================
// INPUT
// ============================
$data = json_decode(file_get_contents("php://input"), true);
$email = trim($data['email'] ?? '');

if (empty($email)) {
    echo json_encode(["status" => "error", "message" => "Email required"]);
    exit();
}

// ============================
// CHECK USER EXISTS (IMPORTANT)
// ============================
$stmt = $conn->prepare("SELECT user_id FROM user WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows == 0) {
    echo json_encode([
        "status" => "error",
        "message" => "Email not found"
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
// SEND EMAIL
// ============================
$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'fixmyroad.app.noreply@gmail.com';
    $mail->Password = 'aned ubet uujd ejuf'; // app password
    $mail->SMTPSecure = 'tls';
    $mail->Port = 587;

    $mail->setFrom('fixmyroad.app.noreply@gmail.com', 'Fix My Road');
    $mail->addAddress($email);

    $mail->isHTML(true);
    $mail->Subject = 'Password Reset Code';
    $mail->Body = "
        <h3>Password Reset</h3>
        <p>Your verification code is:</p>
        <h1>$code</h1>
        <p>Expires in 5 minutes.</p>
    ";

    $mail->send();

    echo json_encode([
        "status" => "success",
        "message" => "Code sent"
    ]);

} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => $mail->ErrorInfo
    ]);
}

$conn->close();
?>