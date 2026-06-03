<?php

function uploadToCloudinary($fileTmp, $cloud_name, $upload_preset) {

    $upload_url = "https://api.cloudinary.com/v1_1/$cloud_name/image/upload";

    $image_data = base64_encode(file_get_contents($fileTmp));

    $data = [
        "file" => "data:image/jpeg;base64," . $image_data,
        "upload_preset" => $upload_preset
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $upload_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}
?>