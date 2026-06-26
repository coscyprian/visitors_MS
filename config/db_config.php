<?php
// Taarifa za Database
$host = "localhost";
$user = "root";       // Badili kama unatumia username tofauti
$pass = "";           // Weka password kama ipo
$dbname = "visitors_db";

// Kutengeneza connection
$conn = new mysqli($host, $user, $pass, $dbname);

// Kuangalia kama imekubali
if ($conn->connect_error) {
    die("Database Connection failed: " . $conn->connect_error);
}

// Set charset iwe utf8 ili kusaidia herufi maalum
$conn->set_charset("utf8");
?>