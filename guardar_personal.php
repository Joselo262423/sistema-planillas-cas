<?php
require_once 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dni           = trim($_POST['dni'] ?? '');
    $ap_paterno    = trim($_POST['apellido_paterno'] ?? '');
    $ap_materno    = trim($_POST['apellido_materno'] ?? '');
    $nombres       = trim($_POST['nombres'] ?? '');
    $cargo         = trim($_POST['cargo'] ?? '');
    $cod_airhsp    = trim($_POST['cod_airhsp'] ?? '');
    $meta          = trim($_POST['meta'] ?? '0131');
    $remuneracion  = floatval($_POST['remuneracion_mensual'] ?? 0);
    $id_regimen    = intval($_POST['id_regimen'] ?? 1);

    if (!empty($dni) && !empty($ap_paterno) && !empty($nombres) && $remuneracion > 0) {
        $sql = "INSERT INTO trabajadores_cas 
                (dni, apellido_paterno, apellido_materno, nombres, cargo, cod_airhsp, meta, remuneracion_mensual, id_regimen, estado) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'activo')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$dni, $ap_paterno, $ap_materno, $nombres, $cargo, $cod_airhsp, $meta, $remuneracion, $id_regimen]);
    }
}

// Redireccionar al listado principal
header('Location: index.php');
exit;