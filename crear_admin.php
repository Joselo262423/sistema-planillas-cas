<?php
require_once 'conexion.php';

$passHash = password_hash('admin123', PASSWORD_BCRYPT);

$stmt = $pdo->prepare("DELETE FROM usuarios WHERE usuario = 'admin'");
$stmt->execute();

$sql = "INSERT INTO usuarios (nombre_completo, usuario, password, rol, estado) 
        VALUES ('Administrador CAS', 'admin', ?, 'Administrador CAS', 'activo')";
$stmt = $pdo->prepare($sql);
$stmt->execute(array($passHash));

echo "<h2 style='color:green;'>¡Usuario admin restablecido con éxito!</h2>";
echo "<p>Usuario: <b>admin</b> | Clave: <b>admin123</b></p>";
echo "<a href='login.php'>Ir al Login</a>";