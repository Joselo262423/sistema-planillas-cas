<?php
// conexion.php adaptado para local y nube

$host = getenv('DB_HOST') ? getenv('DB_HOST') : '127.0.0.1';
$port = getenv('DB_PORT') ? getenv('DB_PORT') : '3306';
$db   = getenv('DB_NAME') ? getenv('DB_NAME') : 'ugel_planillas_cas';
$user = getenv('DB_USER') ? getenv('DB_USER') : 'root';
$pass = getenv('DB_PASS') ? getenv('DB_PASS') : 'root1234';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::ATTR_TIMEOUT            => 5,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("<h3>Error de conexión a la Base de Datos:</h3> " . $e->getMessage());
}