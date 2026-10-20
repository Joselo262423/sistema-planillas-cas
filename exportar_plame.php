<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'conexion.php';

$tipo = isset($_GET['tipo']) ? trim($_GET['tipo']) : 'rem'; // 'rem' o 'jor'

$stmtPeriodo = $pdo->query("SELECT * FROM periodos WHERE estado = 'abierto' LIMIT 1");
$periodoActivo = $stmtPeriodo->fetch();

if (!$periodoActivo) {
    die("No hay un periodo abierto para exportar.");
}

$rucUGEL = "20546549211"; // RUC representativo UGEL Ventanilla
$periodoFormato = str_replace('-', '', $periodoActivo['codigo']); // Ej: 202609

$sql = "SELECT p.*, t.dias_laborados 
        FROM planilla_cas_mensual p
        INNER JOIN trabajadores_cas t ON p.id_trabajador = t.id
        WHERE p.id_periodo = ?
        ORDER BY p.dni ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute(array($periodoActivo['id']));
$registros = $stmt->fetchAll();

if ($tipo === 'jor') {
    // ESTRUCTURA ARCHIVO .JOR (JORNADA LABORAL)
    // Formato: 0601 + AAAA + MM + RUC + .jor
    $nombreArchivo = "0601" . $periodoFormato . $rucUGEL . ".jor";
    header("Content-Type: text/plain; charset=iso-8859-1");
    header("Content-Disposition: attachment; filename=\"$nombreArchivo\"");

    foreach ($registros as $r) {
        $diasEfectivos = max(0, intval($r['dias_laborados']) - intval($r['dias_inasistencia']));
        $horasNormales = $diasEfectivos * 8;
        $minutosSobretiempo = 0;
        
        // Formato SUNAT: TipoDoc(01=DNI)|NumDoc|HorasOrdinarias|MinutosOrdinarios|HorasSobretiempo|MinutosSobretiempo|
        echo "01|" . $r['dni'] . "|" . $horasNormales . "|00|0|00|\r\n";
    }
    exit;
} else {
    // ESTRUCTURA ARCHIVO .REM (CONCEPTOS REMUNERATIVOS)
    // Formato: 0601 + AAAA + MM + RUC + .rem
    $nombreArchivo = "0601" . $periodoFormato . $rucUGEL . ".rem";
    header("Content-Type: text/plain; charset=iso-8859-1");
    header("Content-Disposition: attachment; filename=\"$nombreArchivo\"");

    foreach ($registros as $r) {
        $dni = $r['dni'];

        // Código 0121: Remuneración CAS
        echo "01|" . $dni . "|0121|" . number_format($r['remuneracion_bruta'], 2, '.', '') . "|0.00|\r\n";

        // Código 0705: Tardanzas / Inasistencias CAS
        if ($r['desc_inasistencias_tardanzas'] > 0) {
            echo "01|" . $dni . "|0705|" . number_format($r['desc_inasistencias_tardanzas'], 2, '.', '') . "|0.00|\r\n";
        }

        // Código 0605: Retención 4ta Categoría
        if ($r['desc_renta_cuarta'] > 0) {
            echo "01|" . $dni . "|0605|" . number_format($r['desc_renta_cuarta'], 2, '.', '') . "|0.00|\r\n";
        }

        // Código 0607: Retención Judicial
        if ($r['desc_judicial'] > 0) {
            echo "01|" . $dni . "|0607|" . number_format($r['desc_judicial'], 2, '.', '') . "|0.00|\r\n";
        }
    }
    exit;
}