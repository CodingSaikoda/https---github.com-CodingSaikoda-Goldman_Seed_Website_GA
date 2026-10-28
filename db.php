<?php
// Database connection settings
$host = "localhost";
$username = "root";
$password = "";
$database = "ecommerce_website_ga";

// Connect to MySQL
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = new mysqli($host, $username, $password, $database);

// Check connection
// Set character encoding
$conn->set_charset("utf8mb4");
?>