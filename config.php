<?php
$servername = "sql309.infinityfree.com";
$username   = "if0_42053437";
$password   = "M5AfCcmVbk";
$dbname     = "fmrdb";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>