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
    die("No hay periodo abierto.");
}

$sql = "SELECT p.*, per.anio, per.mes, per.codigo as codigo_periodo, per.nombre_mes,
               t.apellido_paterno, t.apellido_materno, t.nombres, t.num_contrato, 
               t.fecha_inicio, t.cod_nexus, t.cod_airhsp, t.cuenta_ahorros, t.condicion_laboral
        FROM planilla_cas_mensual p
        INNER JOIN periodos per ON p.id_periodo = per.id
        INNER JOIN trabajadores_cas t ON p.id_trabajador = t.id
        WHERE p.id_periodo = ?
        ORDER BY t.apellido_paterno ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute(array($periodoActivo['id']));
$boletas = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Boletas Masivas CAS - Periodo <?php echo htmlspecialchars($periodoActivo['codigo']); ?></title>
  <style>
    @page { size: A4 portrait; margin: 10mm 15mm; }
    body { font-family: Arial, Helvetica, sans-serif; font-size: 11px; color: #111; margin: 0; padding: 15px; background: #e2e8f0; }
    .hoja-boleta { 
      background: #fff; padding: 20px; max-width: 800px; margin: 0 auto 25px auto; 
      box-shadow: 0 4px 6px rgba(0,0,0,0.1); page-break-after: always;
    }
    .hoja-boleta:last-child { page-break-after: auto; }
    .header-table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
    .header-logo { width: 45%; vertical-align: top; }
    .header-title { width: 35%; text-align: center; vertical-align: middle; }
    .header-right { width: 20%; text-align: right; vertical-align: top; font-size: 11px; font-weight: bold; }
    .minedu-badge { display: inline-flex; align-items: center; border: 1px solid #777; background: #fff; height: 36px; }
    .minedu-red { background: #c8102e; color: #fff; font-weight: bold; font-size: 12px; padding: 0 10px; height: 100%; display: flex; align-items: center; }
    .minedu-gray { background: #5c5c5c; color: #fff; font-size: 9px; padding: 0 8px; height: 100%; display: flex; flex-direction: column; justify-content: center; font-weight: bold; }
    .title-boleta { font-size: 17px; font-weight: 900; margin: 0; }
    .title-mes { font-size: 14px; font-weight: 900; color: #c8102e; margin-top: 2px; }
    .sec-header { background: #dedede; font-weight: bold; padding: 4px 6px; font-size: 10px; border-bottom: 1px solid #333; display: flex; justify-content: space-between; margin-top: 5px; }
    .grid-info { width: 100%; border-collapse: collapse; margin-bottom: 5px; }
    .grid-info td { padding: 3px 5px; font-size: 10px; vertical-align: top; border-bottom: 1px solid #f0f0f0; }
    .lbl { color: #222; font-weight: bold; font-size: 8.5px; display: block; text-transform: uppercase; }
    .val { font-size: 10px; font-weight: bold; }
    .val-sub { font-size: 9px; color: #333; }
    .conceptos-container { width: 100%; border-collapse: collapse; margin-top: 6px; border: 1px solid #999; }
    .col-header { background: #dcdcdc; font-weight: bold; padding: 4px 6px; font-size: 9.5px; border-bottom: 1px solid #999; }
    .tbl-con { width: 100%; border-collapse: collapse; min-height: 140px; }
    .tbl-con td { padding: 4px 6px; font-size: 9.5px; }
    .monto { text-align: right; font-family: "Courier New", monospace; font-weight: bold; }
    .tbl-totales { width: 100%; border-collapse: collapse; }
    .tbl-totales td { padding: 4px 6px; font-size: 9.5px; font-weight: bold; background: #e8e8e8; border-top: 1px solid #999; }
    .footer-box { width: 100%; border-collapse: collapse; margin-top: 8px; }
    .box-resumen { background: #dedede; padding: 4px 6px; font-weight: bold; font-size: 10px; }
    .box-liquido { background: #d5e8db; padding: 5px 8px; font-weight: 900; font-size: 11px; }

    @media print {
      body { background: #fff; padding: 0; }
      .no-print { display: none !important; }
      .hoja-boleta { box-shadow: none; margin: 0; padding: 0; width: 100%; max-width: 100%; }
    }
  </style>
</head>
<body>

  <div class="no-print" style="position: sticky; top: 10px; margin-bottom: 20px; text-align: center; background: #0c1526; padding: 12px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 100;">
    <span style="color: #fff; font-weight: bold; margin-right: 20px; font-size: 13px;">Lote de Boletas: <?php echo count($boletas); ?> Servidores CAS</span>
    <button onclick="window.print()" style="background: #008f5d; color: white; border: none; padding: 8px 18px; border-radius: 8px; font-weight: bold; cursor: pointer;">Imprimir Todas / Guardar PDF</button>
    <button onclick="window.close()" style="background: #475569; color: white; border: none; padding: 8px 14px; border-radius: 8px; font-weight: bold; cursor: pointer; margin-left: 8px;">Cerrar</button>
  </div>

  <?php foreach ($boletas as $b): 
    $apellidos = strtoupper($b['apellido_paterno'] . ' ' . $b['apellido_materno']);
    $nombres = strtoupper($b['nombres']);
    $periodoTexto = strtoupper($b['nombre_mes']) . ' - ' . $b['anio'];
    $tardanzaFormato = sprintf('%02d:%02d', $b['horas_tardanza'], $b['minutos_tardanza']);
    
    $gratificacion = isset($b['gratificacion']) ? floatval($b['gratificacion']) : 0.00;
    $reintegro     = isset($b['reintegro']) ? floatval($b['reintegro']) : 0.00;
    $totalIngresos = floatval($b['remuneracion_bruta']) + $gratificacion + $reintegro;

    $qrData = "UGEL VENTANILLA | BOLETA CAS | DNI: " . $b['dni'] . " | PERIODO: " . $b['codigo_periodo'] . " | NETO: S/ " . number_format($b['neto_a_pagar'], 2);
    $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=110x110&data=" . urlencode($qrData);
  ?>
  <div class="hoja-boleta">
    <table class="header-table">
      <tr>
        <td class="header-logo">
          <div style="font-size: 8.5px; color: #555; margin-bottom: 3px;">CÓD. MODULAR - SEC.<br><b><?php echo htmlspecialchars($b['dni']); ?>/46-<?php echo htmlspecialchars(!empty($b['cod_airhsp']) ? $b['cod_airhsp'] : '000564'); ?></b></div>
          <div class="minedu-badge">
            <div class="minedu-red">PERÚ</div>
            <div class="minedu-gray">Ministerio<br>de Educación</div>
          </div>
        </td>
        <td class="header-title">
          <div class="title-boleta">BOLETA DE PAGO</div>
          <div class="title-mes"><?php echo $periodoTexto; ?></div>
        </td>
        <td class="header-right">CALLAO<br>UGEL VENTANILLA</td>
      </tr>
    </table>

    <div class="sec-header">
      <span>DATOS PERSONALES</span>
      <span>TIPO DE PLANILLA: <b>CAS (D.L. 1057) ACTIVO</b></span>
    </div>

    <table class="grid-info">
      <tr>
        <td style="width: 40%;"><span class="lbl">APELLIDOS</span><span class="val"><?php echo $apellidos; ?></span></td>
        <td style="width: 35%;"><span class="lbl">NOMBRES</span><span class="val"><?php echo $nombres; ?></span></td>
        <td style="width: 25%;"><span class="lbl">D.N.I</span><span class="val"><?php echo htmlspecialchars($b['dni']); ?></span></td>
      </tr>
      <tr>
        <td><span class="lbl">CENTRO DE TRABAJO</span><span class="val-sub"><?php echo htmlspecialchars($b['establecimiento']); ?></span></td>
        <td><span class="lbl">CARGO</span><span class="val-sub"><?php echo htmlspecialchars($b['cargo']); ?></span></td>
        <td><span class="lbl">CONDICIÓN LABORAL</span><span class="val-sub"><?php echo htmlspecialchars(!empty($b['condicion_laboral']) ? $b['condicion_laboral'] : 'CAS Indeterminado / Determinado'); ?></span></td>
      </tr>
      <tr>
        <td><span class="lbl">RÉGIMEN LABORAL</span><span class="val-sub">Decreto Legislativo N° 1057</span></td>
        <td><span class="lbl">VÍNCULO LABORAL</span><span class="val-sub">INGRESO: <?php echo htmlspecialchars(!empty($b['fecha_inicio']) ? $b['fecha_inicio'] : '---'); ?></span></td>
        <td><span class="lbl">ENTIDAD BANCARIA</span><span class="val-sub">BCO. NACION <?php echo htmlspecialchars($b['cuenta_ahorros']); ?></span></td>
      </tr>
      <tr>
        <td><span class="lbl">CONTRATO / LEYENDA</span><span class="val-sub"><?php echo htmlspecialchars(!empty($b['num_contrato']) ? $b['num_contrato'] : 'CAS'); ?></span></td>
        <td><span class="lbl">META PRESUPUESTAL</span><span class="val-sub">META: <?php echo htmlspecialchars($b['meta']); ?></span></td>
        <td><span class="lbl">RÉGIMEN PENSIONARIO</span><span class="val-sub"><?php echo htmlspecialchars($b['regimen_pensionario']); ?></span></td>
      </tr>
      <tr>
        <td><span class="lbl">JORNADA LABORAL</span><span class="val-sub">48 HORAS SEMANALES</span></td>
        <td>
    	 <span class="lbl">DÍAS / MENSUAL</span>
     	<span class="val-sub"><?php echo isset($b['dias_pagados']) ? intval($b['dias_pagados']) : 30; ?> DÍAS</span>
 	  </td>
        <td><span class="lbl">PLAZA NEXUS</span><span class="val-sub"><?php echo htmlspecialchars(!empty($b['cod_nexus']) ? $b['cod_nexus'] : '-'); ?></span></td>
      </tr>
      <tr>
        <td><span class="lbl">DÍAS INASISTENCIA</span><span class="val-sub"><?php echo intval($b['dias_inasistencia']); ?></span></td>
        <td><span class="lbl">TARDANZA (HH:MM)</span><span class="val-sub"><?php echo $tardanzaFormato; ?></span></td>
        <td>
          <span class="lbl">DÍAS LICENCIA</span>
          <span class="val-sub">
            <?php 
              $licCon = isset($b['dias_lic_con_goce']) ? intval($b['dias_lic_con_goce']) : 0;
              $licSin = isset($b['dias_lic_sin_goce']) ? intval($b['dias_lic_sin_goce']) : 0;
              $totLic = $licCon + $licSin;
              echo ($totLic > 0) ? $totLic . " días" . ($licSin > 0 ? " (Sin Goce: $licSin)" : "") : "0";
            ?>
          </span>
        </td>
      </tr>
    </table>

    <table class="conceptos-container">
      <tr>
        <td class="col-header" style="width: 50%; border-right: 1px solid #999;">INGRESOS</td>
        <td class="col-header" style="width: 50%;">DESCUENTOS</td>
      </tr>
      <tr>
        <td style="vertical-align: top; border-right: 1px solid #999; padding: 0;">
          <table class="tbl-con">
            <tr>
         <td>
           Remuneración Contrato CAS 
           <?php if (isset($b['dias_pagados']) && intval($b['dias_pagados']) < 30): ?>
             (<?php echo intval($b['dias_pagados']); ?> días)
          	 <?php endif; ?>
         	</td>
        	 <td class="monto">S/ <?php echo number_format($b['remuneracion_bruta'], 2); ?></td>
       		</tr>
            <?php if ($gratificacion > 0): ?>
            <tr>
              <td>Grat. <?php echo ($b['mes'] == 7) ? 'Fiestas Patrias' : 'Navidad'; ?> (D.S. 142-2026-EF)</td>
              <td class="monto">S/ <?php echo number_format($gratificacion, 2); ?></td>
            </tr>
            <?php endif; ?>
            <?php if ($reintegro > 0): ?>
            <tr>
              <td>Reintegro: <?php echo htmlspecialchars(!empty($b['motivo_reintegro']) ? $b['motivo_reintegro'] : 'Haberes'); ?></td>
              <td class="monto">S/ <?php echo number_format($reintegro, 2); ?></td>
            </tr>
            <?php endif; ?>
          </table>
        </td>
        <td style="vertical-align: top; padding: 0;">
          <table class="tbl-con">
            <?php if (isset($b['desc_licencia_sin_goce']) && floatval($b['desc_licencia_sin_goce']) > 0): ?>
            <tr><td>Licencia Sin Goce de Haber (<?php echo intval($b['dias_lic_sin_goce']); ?> días)</td><td class="monto">S/ <?php echo number_format($b['desc_licencia_sin_goce'], 2); ?></td></tr>
            <?php endif; ?>
            <?php if (floatval($b['desc_inasistencias_tardanzas']) > 0): ?>
            <tr><td>Tardanzas e Inasistencias</td><td class="monto">S/ <?php echo number_format($b['desc_inasistencias_tardanzas'], 2); ?></td></tr>
            <?php endif; ?>
            <?php if (floatval($b['desc_pension']) > 0): ?>
            <tr><td>Aporte Previsional (<?php echo htmlspecialchars($b['regimen_pensionario']); ?>)</td><td class="monto">S/ <?php echo number_format($b['desc_pension'], 2); ?></td></tr>
            <?php endif; ?>
            <?php if (floatval($b['desc_renta_cuarta']) > 0): ?>
            <tr><td>Retención Renta 4ta Categoría (8%)</td><td class="monto">S/ <?php echo number_format($b['desc_renta_cuarta'], 2); ?></td></tr>
            <?php endif; ?>
            <?php if (floatval($b['desc_judicial']) > 0): ?>
            <tr><td>Retención Judicial / Alimentos</td><td class="monto">S/ <?php echo number_format($b['desc_judicial'], 2); ?></td></tr>
            <?php endif; ?>
            <?php if (floatval($b['desc_terceros']) > 0): ?>
            <tr><td>Descuento Terceros: <?php echo htmlspecialchars($b['detalle_terceros']); ?></td><td class="monto">S/ <?php echo number_format($b['desc_terceros'], 2); ?></td></tr>
            <?php endif; ?>
          </table>
        </td>
      </tr>
      <tr>
        <td style="padding: 0; border-right: 1px solid #999;">
          <table class="tbl-totales">
            <tr><td>TOTAL INGRESOS</td><td class="monto">S/ <?php echo number_format($totalIngresos, 2); ?></td></tr>
          </table>
        </td>
        <td style="padding: 0;">
          <table class="tbl-totales">
            <tr><td>TOTAL DESCUENTOS</td><td class="monto">S/ <?php echo number_format($b['total_descuentos'], 2); ?></td></tr>
          </table>
        </td>
      </tr>
    </table>

    <table class="footer-box">
      <tr>
        <td style="width: 22%; text-align: center; vertical-align: top; border: 1px solid #999; padding: 5px;">
          <img src="<?php echo $qrUrl; ?>" alt="QR" style="width: 80px; height: 80px; margin: 0 auto; display: block;">
          <div style="font-size: 7px; color: #555; margin-top: 3px;">Verificación electrónica oficial.</div>
        </td>
        <td style="width: 78%; vertical-align: top; padding-left: 8px;">
          <table style="width: 100%; border-collapse: collapse;">
            <tr>
              <td class="box-liquido" style="width: 50%; border: 1px solid #008f5d;">
                TOTAL LÍQUIDO: <span style="float: right; font-family: 'Courier New', monospace; font-size: 13px;">S/ <?php echo number_format($b['neto_a_pagar'], 2); ?></span>
              </td>
              <td class="box-resumen" style="width: 50%; border: 1px solid #999;">
                AFECTO CARGAS: <span style="float: right; font-family: 'Courier New', monospace;">S/ <?php echo number_format($b['remuneracion_bruta'], 2); ?></span>
              </td>
            </tr>
            <tr>
              <td colspan="2" style="font-size: 8px; color: #444; padding-top: 4px;">
                <b>Aporte Empleador EsSalud (9%):</b> S/ <?php echo number_format($b['essalud_patronal'], 2); ?>
              </td>
            </tr>
          </table>
          <div style="margin-top: 10px; font-size: 7.5px; color: #666; font-style: italic;">
            🌱 <i>Antes de imprimir esta boleta, piense en su responsabilidad y compromiso con el medio ambiente.</i>
          </div>
        </td>
      </tr>
    </table>
  </div>
  <?php endforeach; ?>

</body>
</html>