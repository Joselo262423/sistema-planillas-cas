<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'conexion.php';

$stmtPeriodo = $pdo->query("SELECT * FROM periodos WHERE estado = 'abierto' LIMIT 1");
$periodoActivo = $stmtPeriodo->fetch();

if (!$periodoActivo) {
    die("No hay un periodo abierto para exportar.");
}

$id_periodo = $periodoActivo['id'];
$periodoFormato = str_replace('-', '', $periodoActivo['codigo']); // Ej: 202609

// Consulta uniendo la planilla y los datos del trabajador (código AIRHSP)
$sql = "SELECT p.*, t.cod_airhsp
        FROM planilla_cas_mensual p
        INNER JOIN trabajadores_cas t ON p.id_trabajador = t.id
        WHERE p.id_periodo = ?
        ORDER BY p.dni ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute(array($id_periodo));
$registros = $stmt->fetchAll();

// Nombre del archivo de texto para MCPP
$nombreArchivo = "MCPP_CAS_" . $periodoFormato . ".txt";

header("Content-Type: text/plain; charset=iso-8859-1");
header("Content-Disposition: attachment; filename=\"$nombreArchivo\"");

foreach ($registros as $r) {
    $dni    = trim($r['dni']);
    $neto   = number_format($r['neto_a_pagar'], 2, '.', '');
    $airhsp = !empty($r['cod_airhsp']) ? trim($r['cod_airhsp']) : '000564';

    // Estructura oficial MCPP solicitada:
    // 2|numero dni|00|1|0131|HONORARIOS|neto a pagar|4|cod AIRHSP|
    $linea = "2|" . $dni . "|00|1|0131|HONORARIOS|" . $neto . "|4|" . $airhsp;

    echo $linea . "\r\n";
}
exit;