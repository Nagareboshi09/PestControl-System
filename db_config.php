<?php
$dbname = 'macj_pest_control';
$user = 'root';
$pass = '';

// Connect to database
try {
    // Connect to the server
    $pdo = new PDO("mysql:host=localhost;dbname=$dbname", $user, $pass);
} catch(PDOException $e) {
    die("Could not connect to the database: " . $e->getMessage());
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);