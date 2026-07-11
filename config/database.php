<?php

$host = 'localhost';
$dbName = 'service_desk';
$username = 'root';
$password = 'root';

$pdo = new PDO(
    "mysql:host=$host;dbname=$dbName;charset=utf8mb4",
    $username,
    $password,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);
