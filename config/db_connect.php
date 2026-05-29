<?php
// Philippine Standard Time (UTC+8) for all date/time operations
date_default_timezone_set('Asia/Manila');

// Database connection parameters
$host = 'localhost';
$dbname = 'coffee_prince_library';
$username = 'root'; // Change to your database username if different
$password = ''; // Change to your database password if different

// Create connection
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    
    // Set PDO to throw exceptions on error
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Set default fetch mode to associative array
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Use utf8mb4 character set
    $pdo->exec("SET NAMES utf8mb4");

    // Align MySQL NOW() with Philippine time (host may default to UK/UTC)
    try {
        $pdo->exec("SET time_zone = '+08:00'");
    } catch (PDOException $tzException) {
        error_log('Could not set MySQL time_zone to +08:00: ' . $tzException->getMessage());
    }
} catch (PDOException $e) {
    // If connection fails, display error and exit
    die("Connection failed: " . $e->getMessage());
} 