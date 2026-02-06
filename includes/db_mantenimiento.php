<?php
$host = 'localhost';
$db = 'db_mantenimiento';
$user = 'root';
$password = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    // Usaremos la variable $pdo en todo el sistema
    $pdo = new PDO($dsn, $user, $password, $options);
} catch (\PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
?>