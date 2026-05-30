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
$otp = rand(1000, 9999);
$expires_at = date("Y-m-d H:i:s", strtotime("+5 minutes"));

// DELETE OLD CODE
$stmt = $conn->prepare("DELETE FROM verification_codes WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();

// INSERT NEW CODE
$stmt = $conn->prepare("INSERT INTO verification_codes (email, code, expires_at) VALUES (?, ?, ?)");
$stmt->bind_param("sss", $email, $otp, $expires_at);
$stmt->execute();

// ============================
// SendGrid API KEY
// ============================
$apiKey = getenv("SENDGRID_API_KEY");

// ============================
// Email payload
// ============================
$payload = [
    "personalizations" => [[
        "to" => [[ "email" => $email ]]
    ]],
    "from" => [
        "email" => "fixmyroad.app.noreply@gmail.com"
    ],
    "subject" => "FixMyRoad Verification Code",
    "content" => [[
        "type" => "text/html",
        "value" => "
            <h3>Verification Code</h3>
            <p>Your verification code is:</p>
            <h1>{$otp}</h1>
            <p>Expires in 5 minutes.</p>
            <p>If you did not request this, please ignore.</p>
        "
    ]]
];

// ============================
// CURL REQUEST
// ============================
$ch = curl_init("https://api.sendgrid.com/v3/mail/send");

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $apiKey",
    "Content-Type: application/json"
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    echo json_encode([
        "status" => "error",
        "message" => curl_error($ch)
    ]);
    exit;
}

curl_close($ch);

// ============================
// SUCCESS RESPONSE
// ============================
$responseData = json_decode($response, true);

if ($httpCode == 202) {
    echo json_encode([
        "status" => "success",
        "message" => "OTP sent successfully"
    ]);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "SendGrid failed",
        "sendgrid_status" => $httpCode,
        "sendgrid_response" => $responseData,
        "raw_response" => $response
    ]);
}
?>