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
$sql = "SELECT * FROM planilla_cas_mensual WHERE id_periodo = ? ORDER BY nombre_completo ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute(array($id_periodo));
$registros = $stmt->fetchAll();

$nombreArchivo = "PLANILLA_CAS_" . $periodoActivo['codigo'] . "_" . date('Ymd_His') . ".xls";

header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$nombreArchivo\"");
header("Pragma: no-cache");
header("Expires: 0");

echo "\xEF\xBB\xBF"; // UTF-8 BOM para caracteres en español (tildes, eñes)
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  th { background-color: #0c1526; color: #ffffff; font-weight: bold; border: 1px solid #333333; }
  td { border: 1px solid #cccccc; font-family: Arial, sans-serif; font-size: 10pt; }
  .text { mso-number-format:"\@"; }
  .num { mso-number-format:"\#\,\#\#0\.00"; text-align: right; }
  .center { text-align: center; }
  .bold { font-weight: bold; }
  .total-row { background-color: #e2e8f0; font-weight: bold; }
</style>
</head>
<body>
  <table>
    <tr>
      <td colspan="13" style="font-size: 14pt; font-weight: bold;">UGEL VENTANILLA - ÁREA DE RECURSOS HUMANOS / PLANILLAS</td>
    </tr>
    <tr>
      <td colspan="13" style="font-size: 12pt; font-weight: bold; color: #008f5d;">PLANILLA MENSUAL DE SERVIDORES CAS - D.L. 1057 (PERIODO: <?php echo htmlspecialchars($periodoActivo['nombre_mes'] . ' ' . $periodoActivo['anio']); ?>)</td>
    </tr>
    <tr></tr>
    <thead>
      <tr>
        <th>N°</th>
        <th>DNI</th>
        <th>APELLIDOS Y NOMBRES</th>
        <th>CARGO</th>
        <th>META</th>
        <th>RÉGIMEN PENS.</th>
        <th>REM. BRUTA</th>
	<th>GRATIFICACIÓN</th>
        <th>TARD./INAS.</th>
        <th>APORTE PENS.</th>
        <th>RENTA 4TA</th>
        <th>RET. JUDICIAL</th>
        <th>TERCEROS</th>
        <th>TOTAL DESC.</th>
        <th>NETO A PAGAR</th>
        <th>ESSALUD (9%)</th>
      </tr>
    </thead>
    <tbody>
      <?php 
      $i = 1;
      $totBruto = $totInas = $totPens = $totRenta = $totJud = $totTerc = $totDesc = $totNeto = $totEssalud = 0;
      foreach ($registros as $r): 
        $totBruto   += $r['remuneracion_bruta'];
        $totInas    += $r['desc_inasistencias_tardanzas'];
        $totPens    += $r['desc_pension'];
        $totRenta   += $r['desc_renta_cuarta'];
        $totJud     += $r['desc_judicial'];
        $totTerc    += $r['desc_terceros'];
        $totDesc    += $r['total_descuentos'];
        $totNeto    += $r['neto_a_pagar'];
        $totEssalud += $r['essalud_patronal'];
      ?>
      <tr>
        <td class="center"><?php echo $i++; ?></td>
        <td class="text center"><?php echo htmlspecialchars($r['dni']); ?></td>
        <td><?php echo htmlspecialchars($r['nombre_completo']); ?></td>
        <td><?php echo htmlspecialchars($r['cargo']); ?></td>
        <td class="text center"><?php echo htmlspecialchars($r['meta']); ?></td>
        <td class="center"><?php echo htmlspecialchars($r['regimen_pensionario']); ?></td>
        <td class="num"><?php echo number_format($r['remuneracion_bruta'], 2, '.', ''); ?></td>
	<td class="num bold"><?php echo number_format($r['gratificacion'], 2, '.', ''); ?></td>
        <td class="num"><?php echo number_format($r['desc_inasistencias_tardanzas'], 2, '.', ''); ?></td>
        <td class="num"><?php echo number_format($r['desc_pension'], 2, '.', ''); ?></td>
        <td class="num"><?php echo number_format($r['desc_renta_cuarta'], 2, '.', ''); ?></td>
        <td class="num"><?php echo number_format($r['desc_judicial'], 2, '.', ''); ?></td>
        <td class="num"><?php echo number_format($r['desc_terceros'], 2, '.', ''); ?></td>
        <td class="num"><?php echo number_format($r['total_descuentos'], 2, '.', ''); ?></td>
        <td class="num bold"><?php echo number_format($r['neto_a_pagar'], 2, '.', ''); ?></td>
        <td class="num"><?php echo number_format($r['essalud_patronal'], 2, '.', ''); ?></td>
      </tr>
      <?php endforeach; ?>
      <tr class="total-row">
        <td colspan="6" class="center bold">TOTALES GENERALES (S/)</td>
        <td class="num"><?php echo number_format($totBruto, 2, '.', ''); ?></td>
	<td class="num bold"><?php echo number_format($totGratificacion, 2, '.', ''); ?></td>
        <td class="num"><?php echo number_format($totInas, 2, '.', ''); ?></td>
        <td class="num"><?php echo number_format($totPens, 2, '.', ''); ?></td>
        <td class="num"><?php echo number_format($totRenta, 2, '.', ''); ?></td>
        <td class="num"><?php echo number_format($totJud, 2, '.', ''); ?></td>
        <td class="num"><?php echo number_format($totTerc, 2, '.', ''); ?></td>
        <td class="num"><?php echo number_format($totDesc, 2, '.', ''); ?></td>
        <td class="num bold"><?php echo number_format($totNeto, 2, '.', ''); ?></td>
        <td class="num"><?php echo number_format($totEssalud, 2, '.', ''); ?></td>
      </tr>
    </tbody>
  </table>
</body>
</html>