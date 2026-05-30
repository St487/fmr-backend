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
// SEND EMAIL (SENDGRID API)
// ============================

$sendGridApiKey = getenv("SENDGRID_API_KEY");

$emailData = [
    "personalizations" => [[
        "to" => [[
            "email" => $email
        ]]
    ]],
    "from" => [
        "email" => "fixmyroad.app.noreply@gmail.com",
        "name" => "Fix My Road"
    ],
    "subject" => "Password Reset Code",
    "content" => [[
        "type" => "text/html",
        "value" => "
            <h3>Password Reset</h3>
            <p>Your verification code is:</p>
            <h1>{$code}</h1>
            <p>Expires in 5 minutes.</p>
        "
    ]]
];

$ch = curl_init();

curl_setopt_array($ch, [
    CURLOPT_URL => "https://api.sendgrid.com/v3/mail/send",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($emailData),
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer {$sendGridApiKey}",
        "Content-Type: application/json"
    ]
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);

curl_close($ch);

if ($curlError) {
    echo json_encode([
        "status" => "error",
        "message" => $curlError
    ]);
    exit();
}

if ($httpCode == 202) {
    echo json_encode([
        "status" => "success",
        "message" => "Code sent"
    ]);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "SendGrid failed",
        "http_code" => $httpCode,
        "response" => json_decode($response, true)
    ]);
}
?>