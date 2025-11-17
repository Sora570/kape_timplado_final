<?php
$host = "127.0.0.1";
$user = "root";
$pass = "";          // set if you have a MySQL password
$dbname = "kape_db";
$port = 3306;

// Try connecting with different methods
try {
    // First try standard connection
    $conn = new mysqli($host, $user, $pass, $dbname, $port);
    
    if ($conn->connect_error) {
        // Try with socket path
        $socket = "/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock";
        if (file_exists($socket)) {
            $conn = new mysqli($host, $user, $pass, $dbname, $port, $socket);
        }
    }
    
    if ($conn->connect_error) {
        throw new Exception("DB connection failed: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    http_response_code(500);
    die("DB connection failed: " . $e->getMessage());
}