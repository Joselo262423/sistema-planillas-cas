<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'conexion.php';

$id_periodo = isset($_GET['id_periodo']) ? intval($_GET['id_periodo']) : 0;

if ($id_periodo > 0) {
    $stmtPer = $pdo->prepare("SELECT * FROM periodos WHERE id = ?");
    $stmtPer->execute(array($id_periodo));
    $periodo = $stmtPer->fetch();
} else {
    $periodo = $pdo->query("SELECT * FROM periodos WHERE estado = 'abierto' LIMIT 1")->fetch();
}

if (!$periodo) {
    die("No se encontró el periodo solicitado.");
}

$id_periodo = intval($periodo['id']);

// Consulta consolidada SIAF por Meta y Establecimiento
$sqlSiaf = "SELECT 
              meta,
              establecimiento,
              COUNT(*) AS cant_servidores,
              SUM(remuneracion_bruta + IFNULL(gratificacion, 0) + IFNULL(reintegro, 0)) AS total_bruto,
              SUM(desc_inasistencias_tardanzas + IFNULL(desc_licencia_sin_goce, 0)) AS desc_faltas_lic,
              SUM(CASE WHEN regimen_pensionario LIKE '%ONP%' THEN desc_pension ELSE 0 END) AS desc_onp,
              SUM(CASE WHEN regimen_pensionario NOT LIKE '%ONP%' AND desc_pension > 0 THEN desc_pension ELSE 0 END) AS desc_afp,
              SUM(desc_renta_cuarta) AS desc_cuarta,
              SUM(desc_judicial) AS desc_judicial,
              SUM(desc_terceros) AS desc_terceros,
              SUM(total_descuentos) AS total_descuentos,
              SUM(neto_a_pagar) AS total_liquido,
              SUM(essalud_patronal) AS total_essalud
            FROM planilla_cas_mensual
            WHERE id_periodo = ?
            GROUP BY meta, establecimiento
            ORDER BY meta ASC, establecimiento ASC";
$stmt = $pdo->prepare($sqlSiaf);
$stmt->execute(array($id_periodo));
$filas = $stmt->fetchAll();

// Agrupar por Meta
$metasAgrupadas = array();
foreach ($filas as $f) {
    $m = !empty($f['meta']) ? $f['meta'] : 'S/M';
    if (!isset($metasAgrupadas[$m])) {
        $metasAgrupadas[$m] = array(
            'establecimientos' => array(),
            'sub' => array('cant'=>0, 'bruto'=>0, 'faltas'=>0, 'onp'=>0, 'afp'=>0, 'cuarta'=>0, 'judicial'=>0, 'terceros'=>0, 'desc'=>0, 'liquido'=>0, 'essalud'=>0)
        );
    }
    $metasAgrupadas[$m]['establecimientos'][] = $f;
    $metasAgrupadas[$m]['sub']['cant']     += intval($f['cant_servidores']);
    $metasAgrupadas[$m]['sub']['bruto']    += floatval($f['total_bruto']);
    $metasAgrupadas[$m]['sub']['faltas']   += floatval($f['desc_faltas_lic']);
    $metasAgrupadas[$m]['sub']['onp']      += floatval($f['desc_onp']);
    $metasAgrupadas[$m]['sub']['afp']      += floatval($f['desc_afp']);
    $metasAgrupadas[$m]['sub']['cuarta']   += floatval($f['desc_cuarta']);
    $metasAgrupadas[$m]['sub']['judicial'] += floatval($f['desc_judicial']);
    $metasAgrupadas[$m]['sub']['terceros'] += floatval($f['desc_terceros']);
    $metasAgrupadas[$m]['sub']['desc']     += floatval($f['total_descuentos']);
    $metasAgrupadas[$m]['sub']['liquido']  += floatval($f['total_liquido']);
    $metasAgrupadas[$m]['sub']['essalud']  += floatval($f['total_essalud']);
}

$nombreArchivo = "COMPROMISO_SIAF_CAS_" . $periodo['codigo'] . "_UGEL_VENTANILLA.xls";

header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$nombreArchivo\"");
header("Pragma: no-cache");
header("Expires: 0");

echo "\xEF\xBB\xBF";
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  th { background-color: #0c1526; color: #ffffff; font-weight: bold; border: 1px solid #333333; font-size: 9pt; }
  td { border: 1px solid #cccccc; font-family: Arial, sans-serif; font-size: 9pt; }
  .text { mso-number-format:"\@"; }
  .num { mso-number-format:"\#\,\#\#0\.00"; text-align: right; }
  .center { text-align: center; }
  .bold { font-weight: bold; }
  .sub-row { background-color: #d1fae5; font-weight: bold; }
  .total-row { background-color: #0c1526; color: #ffffff; font-weight: bold; }
</style>
</head>
<body>
  <table>
    <tr>
      <td colspan="14" style="font-size: 13pt; font-weight: bold;">UGEL VENTANILLA - RESUMEN CONSOLIDADO PARA COMPROMISO SIAF-SP (D.L. 1057 - CAS)</td>
    </tr>
    <tr>
      <td colspan="14" style="font-size: 10pt; font-weight: bold; color: #008f5d;">PERIODO: <?php echo htmlspecialchars(strtoupper($periodo['nombre_mes']) . ' ' . $periodo['anio'] . ' (' . $periodo['codigo'] . ')'); ?></td>
    </tr>
    <tr></tr>
    <thead>
      <tr>
        <th>META</th>
        <th>ESTABLECIMIENTO / SEDE</th>
        <th>CANT.</th>
        <th>MONTO BRUTO (2.3.2.8.1.1)</th>
        <th>INAS./TARD./LIC.</th>
        <th>ONP (13%)</th>
        <th>AFP (TOTAL)</th>
        <th>RENTA 4TA</th>
        <th>DESC. JUDICIAL</th>
        <th>DESC. TERCEROS</th>
        <th>TOTAL DESCUENTOS</th>
        <th>MONTO LÍQUIDO</th>
        <th>APORTE ESSALUD (2.3.2.8.1.2)</th>
        <th>GASTO TOTAL META</th>
      </tr>
    </thead>
    <tbody>
      <?php 
      $gt = array('cant'=>0, 'bruto'=>0, 'faltas'=>0, 'onp'=>0, 'afp'=>0, 'cuarta'=>0, 'judicial'=>0, 'terceros'=>0, 'desc'=>0, 'liquido'=>0, 'essalud'=>0);
      foreach ($metasAgrupadas as $codMeta => $datos): 
        $sub = $datos['sub'];
        foreach ($datos['establecimientos'] as $e):
          $gastoEst = floatval($e['total_bruto']) + floatval($e['total_essalud']);
      ?>
      <tr>
        <td class="text center bold"><?php echo htmlspecialchars($e['meta']); ?></td>
        <td><?php echo htmlspecialchars($e['establecimiento']); ?></td>
        <td class="center"><?php echo intval($e['cant_servidores']); ?></td>
        <td class="num"><?php echo number_format($e['total_bruto'], 2, '.', ''); ?></td>
        <td class="num"><?php echo number_format($e['desc_faltas_lic'], 2, '.', ''); ?></td>
        <td class="num"><?php echo number_format($e['desc_onp'], 2, '.', ''); ?></td>
        <td class="num"><?php echo number_format($e['desc_afp'], 2, '.', ''); ?></td>
        <td class="num"><?php echo number_format($e['desc_cuarta'], 2, '.', ''); ?></td>
        <td class="num"><?php echo number_format($e['desc_judicial'], 2, '.', ''); ?></td>
        <td class="num"><?php echo number_format($e['desc_terceros'], 2, '.', ''); ?></td>
        <td class="num bold"><?php echo number_format($e['total_descuentos'], 2, '.', ''); ?></td>
        <td class="num bold"><?php echo number_format($e['total_liquido'], 2, '.', ''); ?></td>
        <td class="num"><?php echo number_format($e['total_essalud'], 2, '.', ''); ?></td>
        <td class="num bold"><?php echo number_format($gastoEst, 2, '.', ''); ?></td>
      </tr>
      <?php endforeach; ?>
      <?php 
        $gastoSub = $sub['bruto'] + $sub['essalud'];
        $gt['cant']     += $sub['cant'];
        $gt['bruto']    += $sub['bruto'];
        $gt['faltas']   += $sub['faltas'];
        $gt['onp']      += $sub['onp'];
        $gt['afp']      += $sub['afp'];
        $gt['cuarta']   += $sub['cuarta'];
        $gt['judicial'] += $sub['judicial'];
        $gt['terceros'] += $sub['terceros'];
        $gt['desc']     += $sub['desc'];
        $gt['liquido']  += $sub['liquido'];
        $gt['essalud']  += $sub['essalud'];
      ?>
      <tr class="sub-row">
        <td colspan="2" class="text" style="text-align: right;">SUBTOTAL META <?php echo htmlspecialchars($codMeta); ?>:</td>
        <td class="center"><?php echo $sub['cant']; ?></td>
        <td class="num"><?php echo number_format($sub['bruto'], 2, '.', ''); ?></td>
        <td class="num"><?php echo number_format($sub['faltas'], 2, '.', ''); ?></td>
        <td class="num"><?php echo number_format($sub['onp'], 2, '.', ''); ?></td>
        <td class="num"><?php echo number_format($sub['afp'], 2, '.', ''); ?></td>
        <td class="num"><?php echo number_format($sub['cuarta'], 2, '.', ''); ?></td>
        <td class="num"><?php echo number_format($sub['judicial'], 2, '.', ''); ?></td>
        <td class="num"><?php echo number_format($sub['terceros'], 2, '.', ''); ?></td>
        <td class="num"><?php echo number_format($sub['desc'], 2, '.', ''); ?></td>
        <td class="num"><?php echo number_format($sub['liquido'], 2, '.', ''); ?></td>
        <td class="num"><?php echo number_format($sub['essalud'], 2, '.', ''); ?></td>
        <td class="num"><?php echo number_format($gastoSub, 2, '.', ''); ?></td>
      </tr>
      <?php endforeach; ?>
      <?php $granGasto = $gt['bruto'] + $gt['essalud']; ?>
      <tr class="total-row">
        <td colspan="2" style="text-align: right;">TOTAL GENERAL PLANILLA (S/):</td>
        <td class="center"><?php echo $gt['cant']; ?></td>
        <td class="num"><?php echo number_format($gt['bruto'], 2, '.', ''); ?></td>
        <td class="num"><?php echo number_format($gt['faltas'], 2, '.', ''); ?></td>
        <td class="num"><?php echo number_format($gt['onp'], 2, '.', ''); ?></td>
        <td class="num"><?php echo number_format($gt['afp'], 2, '.', ''); ?></td>
        <td class="num"><?php echo number_format($gt['cuarta'], 2, '.', ''); ?></td>
        <td class="num"><?php echo number_format($gt['judicial'], 2, '.', ''); ?></td>
        <td class="num"><?php echo number_format($gt['terceros'], 2, '.', ''); ?></td>
        <td class="num"><?php echo number_format($gt['desc'], 2, '.', ''); ?></td>
        <td class="num"><?php echo number_format($gt['liquido'], 2, '.', ''); ?></td>
        <td class="num"><?php echo number_format($gt['essalud'], 2, '.', ''); ?></td>
        <td class="num"><?php echo number_format($granGasto, 2, '.', ''); ?></td>
      </tr>
    </tbody>
  </table>
</body>
</html>