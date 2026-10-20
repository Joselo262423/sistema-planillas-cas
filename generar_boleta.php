<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'conexion.php';

$id_planilla = isset($_GET['id']) ? intval($_GET['id']) : 0;

$sql = "SELECT p.*, per.anio, per.mes, per.codigo as codigo_periodo, per.nombre_mes,
               t.apellido_paterno, t.apellido_materno, t.nombres, t.num_contrato, 
               t.fecha_inicio, t.fecha_termino, t.cod_nexus, t.cod_airhsp, t.cuenta_ahorros,
               t.condicion_laboral
        FROM planilla_cas_mensual p
        INNER JOIN periodos per ON p.id_periodo = per.id
        INNER JOIN trabajadores_cas t ON p.id_trabajador = t.id
        WHERE p.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute(array($id_planilla));
$b = $stmt->fetch();

if (!$b) {
    die("No se encontró el registro de la boleta de pago.");
}

$apellidos = strtoupper($b['apellido_paterno'] . ' ' . $b['apellido_materno']);
$nombres = strtoupper($b['nombres']);
$periodoTexto = strtoupper($b['nombre_mes']) . ' - ' . $b['anio'];
$tardanzaFormato = sprintf('%02d:%02d', $b['horas_tardanza'], $b['minutos_tardanza']);

$gratificacion = isset($b['gratificacion']) ? floatval($b['gratificacion']) : 0.00;
$reintegro     = isset($b['reintegro']) ? floatval($b['reintegro']) : 0.00;
$totalIngresos = floatval($b['remuneracion_bruta']) + $gratificacion + $reintegro;

// Generar QR dinámico
$qrData = "UGEL VENTANILLA | BOLETA CAS | DNI: " . $b['dni'] . " | PERIODO: " . $b['codigo_periodo'] . " | NETO: S/ " . number_format($b['neto_a_pagar'], 2);
$qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($qrData);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Boleta de Pago - <?php echo htmlspecialchars($b['dni']); ?> - <?php echo htmlspecialchars($b['codigo_periodo']); ?></title>
  <style>
    @page { size: A4 portrait; margin: 12mm 15mm; }
    body { font-family: Arial, Helvetica, sans-serif; font-size: 11px; color: #111; margin: 0; padding: 20px; background: #fff; }
    .header-table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
    .header-logo { width: 45%; vertical-align: top; }
    .header-title { width: 35%; text-align: center; vertical-align: middle; }
    .header-right { width: 20%; text-align: right; vertical-align: top; font-size: 11px; font-weight: bold; }
    
    .minedu-badge {
      display: inline-flex; align-items: center; border: 1px solid #777; background: #fff; height: 38px;
    }
    .minedu-red {
      background: #c8102e; color: #fff; font-weight: bold; font-size: 12px; padding: 0 10px; height: 100%;
      display: flex; align-items: center; justify-content: center; letter-spacing: 0.5px;
    }
    .minedu-gray {
      background: #5c5c5c; color: #fff; font-size: 9px; padding: 0 8px; height: 100%;
      display: flex; flex-direction: column; justify-content: center; line-height: 1.1; font-weight: bold;
    }
    
    .title-boleta { font-size: 18px; font-weight: 900; letter-spacing: 0.5px; margin: 0; }
    .title-mes { font-size: 15px; font-weight: 900; color: #c8102e; margin-top: 3px; }

    .sec-header {
      background: #dedede; font-weight: bold; padding: 4px 6px; font-size: 10px; border-bottom: 1px solid #333;
      display: flex; justify-content: space-between; margin-top: 6px;
    }
    
    .grid-info { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
    .grid-info td { padding: 3px 5px; font-size: 10px; vertical-align: top; border-bottom: 1px solid #f0f0f0; }
    .lbl { color: #222; font-weight: bold; font-size: 9px; display: block; text-transform: uppercase; }
    .val { font-size: 10.5px; font-weight: bold; color: #000; }
    .val-sub { font-size: 9.5px; font-weight: normal; color: #333; }

    /* Columnas de Conceptos */
    .conceptos-container { width: 100%; border-collapse: collapse; margin-top: 8px; border: 1px solid #999; }
    .col-header { background: #dcdcdc; font-weight: bold; padding: 4px 6px; font-size: 9.5px; border-bottom: 1px solid #999; }
    .tbl-con { width: 100%; border-collapse: collapse; min-height: 160px; }
    .tbl-con td { padding: 4px 6px; font-size: 10px; }
    .tbl-con tr:nth-child(even) { background-color: #fafafa; }
    .monto { text-align: right; font-family: "Courier New", monospace; font-weight: bold; font-size: 10.5px; }

    .tbl-totales { width: 100%; border-collapse: collapse; margin-top: 2px; }
    .tbl-totales td { padding: 4px 6px; font-size: 10px; font-weight: bold; background: #e8e8e8; border-top: 1px solid #999; }

    /* Caja de Resumen Final y QR */
    .footer-box { width: 100%; border-collapse: collapse; margin-top: 10px; }
    .box-resumen { background: #dedede; padding: 5px 8px; font-weight: bold; font-size: 11px; }
    .box-liquido { background: #d5e8db; padding: 6px 10px; font-weight: 900; font-size: 12px; }

    @media print {
      body { padding: 0; }
      .no-print { display: none !important; }
    }
  </style>
</head>
<body>

  <!-- Botones de Acción para Pantalla -->
  <div class="no-print" style="margin-bottom: 15px; text-align: right; background: #f1f5f9; padding: 10px 15px; border-radius: 8px;">
    <button onclick="window.print()" style="background: #008f5d; color: white; border: none; padding: 8px 16px; border-radius: 6px; font-weight: bold; cursor: pointer;">
      Imprimir Boleta (PDF)
    </button>
    <button onclick="window.close()" style="background: #64748b; color: white; border: none; padding: 8px 16px; border-radius: 6px; font-weight: bold; cursor: pointer; margin-left: 8px;">
      Cerrar
    </button>
  </div>

  <!-- Encabezado Oficial -->
  <table class="header-table">
    <tr>
      <td class="header-logo">
        <div style="font-size: 9px; color: #555; margin-bottom: 4px;">CÓDIGO MODULAR - SECUENCIAL<br><b><?php echo htmlspecialchars($b['dni']); ?>/46-<?php echo htmlspecialchars(!empty($b['cod_airhsp']) ? $b['cod_airhsp'] : '000564'); ?></b></div>
        <div class="minedu-badge">
          <div class="minedu-red">PERÚ</div>
          <div class="minedu-gray">Ministerio<br>de Educación</div>
        </div>
      </td>
      <td class="header-title">
        <div class="title-boleta">BOLETA DE PAGO</div>
        <div class="title-mes"><?php echo $periodoTexto; ?></div>
      </td>
      <td class="header-right">
        CALLAO<br>UGEL VENTANILLA
      </td>
    </tr>
  </table>

  <!-- DATOS PERSONALES -->
  <div class="sec-header">
    <span>DATOS PERSONALES</span>
    <span>TIPO DE PLANILLA: <b>CAS (D.L. 1057) ACTIVO</b></span>
  </div>

  <table class="grid-info">
    <tr>
      <td style="width: 40%;">
        <span class="lbl">APELLIDOS</span>
        <span class="val"><?php echo $apellidos; ?></span>
      </td>
      <td style="width: 35%;">
        <span class="lbl">NOMBRES</span>
        <span class="val"><?php echo $nombres; ?></span>
      </td>
      <td style="width: 25%;">
        <span class="lbl">D.N.I</span>
        <span class="val"><?php echo htmlspecialchars($b['dni']); ?></span>
      </td>
    </tr>
    <tr>
      <td>
        <span class="lbl">CENTRO DE TRABAJO</span>
        <span class="val-sub"><?php echo htmlspecialchars($b['establecimiento']); ?></span>
      </td>
      <td>
        <span class="lbl">CARGO</span>
        <span class="val-sub"><?php echo htmlspecialchars($b['cargo']); ?></span>
      </td>
      <td>
        <span class="lbl">CONDICIÓN LABORAL</span>
        <span class="val-sub"><?php echo htmlspecialchars(!empty($b['condicion_laboral']) ? $b['condicion_laboral'] : 'CAS Indeterminado / Determinado'); ?></span>
      </td>
    </tr>
    <tr>
      <td>
        <span class="lbl">RÉGIMEN LABORAL</span>
        <span class="val-sub">Decreto Legislativo N° 1057</span>
      </td>
      <td>
        <span class="lbl">VÍNCULO LABORAL</span>
        <span class="val-sub">INGRESO: <?php echo htmlspecialchars(!empty($b['fecha_inicio']) ? $b['fecha_inicio'] : '---'); ?></span>
      </td>
      <td>
        <span class="lbl">ENTIDAD BANCARIA / CUENTA</span>
        <span class="val-sub">BCO. NACION <?php echo htmlspecialchars($b['cuenta_ahorros']); ?></span>
      </td>
    </tr>
    <tr>
      <td>
        <span class="lbl">LEYENDA PERMANENTE / CONTRATO</span>
        <span class="val-sub"><?php echo htmlspecialchars(!empty($b['num_contrato']) ? $b['num_contrato'] : 'CONTRATO CAS'); ?></span>
      </td>
      <td>
        <span class="lbl">DOC. DE REFERENCIA / META</span>
        <span class="val-sub">META: <?php echo htmlspecialchars($b['meta']); ?></span>
      </td>
      <td>
        <span class="lbl">RÉGIMEN PENSIONARIO</span>
        <span class="val-sub"><?php echo htmlspecialchars($b['regimen_pensionario']); ?></span>
      </td>
    </tr>
    <tr>
      <td>
        <span class="lbl">JORNADA LABORAL</span>
        <span class="val-sub">48 HORAS SEMANALES</span>
      </td>
      <td>
      <span class="lbl">DÍAS / MENSUAL</span>
      <span class="val-sub"><?php echo isset($b['dias_pagados']) ? intval($b['dias_pagados']) : 30; ?> DÍAS</span>
      </td>
      <td>
        <span class="lbl">ESCALA / NIVEL</span>
        <span class="val-sub">ADMINISTRATIVO CAS</span>
      </td>
    </tr>
    <tr>
      <td>
        <span class="lbl">SITUACIÓN</span>
        <span class="val-sub">Habilitado</span>
      </td>
      <td>
        <span class="lbl">CÓDIGO AIRHSP</span>
        <span class="val-sub"><?php echo htmlspecialchars(!empty($b['cod_airhsp']) ? $b['cod_airhsp'] : '-'); ?></span>
      </td>
      <td>
        <span class="lbl">CÓDIGO PLAZA NEXUS</span>
        <span class="val-sub"><?php echo htmlspecialchars(!empty($b['cod_nexus']) ? $b['cod_nexus'] : '-'); ?></span>
      </td>
    </tr>
    <tr>
      <td>
        <span class="lbl">DÍAS DE INASISTENCIA</span>
        <span class="val-sub"><?php echo intval($b['dias_inasistencia']); ?></span>
      </td>
      <td>
        <span class="lbl">TARDANZA (HH:MM)</span>
        <span class="val-sub"><?php echo $tardanzaFormato; ?></span>
      </td>
      <td>
        <span class="lbl">DÍAS DE LICENCIA</span>
        <span class="val-sub">
          <?php 
            $licCon = isset($b['dias_lic_con_goce']) ? intval($b['dias_lic_con_goce']) : 0;
            $licSin = isset($b['dias_lic_sin_goce']) ? intval($b['dias_lic_sin_goce']) : 0;
            $totLic = $licCon + $licSin;
            if ($totLic > 0) {
                echo $totLic . " días" . ($licSin > 0 ? " (Sin Goce: $licSin)" : "");
            } else {
                echo "0";
            }
          ?>
        </span>
      </td>
    </tr>
  </table>

  <!-- DETALLE DE INGRESOS Y DESCUENTOS (SIN DUPLICADOS) -->
  <table class="conceptos-container">
    <tr>
      <td class="col-header" style="width: 50%; border-right: 1px solid #999;">INGRESOS</td>
      <td class="col-header" style="width: 50%;">DESCUENTOS</td>
    </tr>
    <tr>
      <!-- Columna Ingresos -->
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

      <!-- Columna Descuentos (Cada concepto una sola vez) -->
      <td style="vertical-align: top; padding: 0;">
        <table class="tbl-con">
          <?php if (isset($b['desc_licencia_sin_goce']) && floatval($b['desc_licencia_sin_goce']) > 0): ?>
          <tr>
            <td>Licencia Sin Goce de Haber (<?php echo intval($b['dias_lic_sin_goce']); ?> días)</td>
            <td class="monto">S/ <?php echo number_format($b['desc_licencia_sin_goce'], 2); ?></td>
          </tr>
          <?php endif; ?>

          <?php if (floatval($b['desc_inasistencias_tardanzas']) > 0): ?>
          <tr>
            <td>Tardanzas e Inasistencias</td>
            <td class="monto">S/ <?php echo number_format($b['desc_inasistencias_tardanzas'], 2); ?></td>
          </tr>
          <?php endif; ?>

          <?php if (floatval($b['desc_pension']) > 0): ?>
          <tr>
            <td>Aporte Previsional (<?php echo htmlspecialchars($b['regimen_pensionario']); ?>)</td>
            <td class="monto">S/ <?php echo number_format($b['desc_pension'], 2); ?></td>
          </tr>
          <?php endif; ?>

          <?php if (floatval($b['desc_renta_cuarta']) > 0): ?>
          <tr>
            <td>Retención Renta 4ta Categoría (8%)</td>
            <td class="monto">S/ <?php echo number_format($b['desc_renta_cuarta'], 2); ?></td>
          </tr>
          <?php endif; ?>

          <?php if (floatval($b['desc_judicial']) > 0): ?>
          <tr>
            <td>Retención Judicial / Alimentos<?php echo !empty($b['num_expediente']) ? ' (Exp: ' . htmlspecialchars($b['num_expediente']) . ')' : ''; ?></td>
            <td class="monto">S/ <?php echo number_format($b['desc_judicial'], 2); ?></td>
          </tr>
          <?php endif; ?>

          <?php if (floatval($b['desc_terceros']) > 0): ?>
          <tr>
            <td>Descuento Terceros: <?php echo htmlspecialchars($b['detalle_terceros']); ?></td>
            <td class="monto">S/ <?php echo number_format($b['desc_terceros'], 2); ?></td>
          </tr>
          <?php endif; ?>
        </table>
      </td>
    </tr>

    <!-- Totales -->
    <tr>
      <td style="padding: 0; border-right: 1px solid #999;">
        <table class="tbl-totales">
          <tr>
            <td>TOTAL INGRESOS</td>
            <td class="monto">S/ <?php echo number_format($totalIngresos, 2); ?></td>
          </tr>
        </table>
      </td>
      <td style="padding: 0;">
        <table class="tbl-totales">
          <tr>
            <td>TOTAL DESCUENTOS</td>
            <td class="monto">S/ <?php echo number_format($b['total_descuentos'], 2); ?></td>
          </tr>
        </table>
      </td>
    </tr>
  </table>

  <!-- TOTAL LÍQUIDO Y APORTES PATRONALES -->
  <table class="footer-box" style="margin-top: 10px;">
    <tr>
      <!-- Código QR Institucional -->
      <td style="width: 25%; text-align: center; vertical-align: top; border: 1px solid #999; padding: 6px;">
        <img src="<?php echo $qrUrl; ?>" alt="Código QR Boleta" style="width: 100px; height: 100px; display: block; margin: 0 auto;">
        <div style="font-size: 7.5px; color: #555; margin-top: 4px; line-height: 1.1;">
          La presentación de la boleta de pago electrónica puede ser verificada a través de la lectura del código QR.
        </div>
      </td>

      <!-- Cajas de Liquidación -->
      <td style="width: 75%; vertical-align: top; padding-left: 10px;">
        <table style="width: 100%; border-collapse: collapse;">
          <tr>
            <td class="box-liquido" style="width: 50%; border: 1px solid #008f5d;">
              TOTAL LÍQUIDO: <span style="font-size: 14px; float: right; font-family: 'Courier New', monospace;">S/ <?php echo number_format($b['neto_a_pagar'], 2); ?></span>
            </td>
            <td class="box-resumen" style="width: 50%; border: 1px solid #999; margin-left: 5px;">
              AFECTO A CARGAS SOCIALES: <span style="float: right; font-family: 'Courier New', monospace;">S/ <?php echo number_format($b['remuneracion_bruta'], 2); ?></span>
            </td>
          </tr>
          <tr>
            <td colspan="2" style="font-size: 8.5px; color: #444; padding-top: 4px;">
              <b>Aporte Empleador EsSalud (9%):</b> S/ <?php echo number_format($b['essalud_patronal'], 2); ?>
            </td>
          </tr>
          <tr>
            <td colspan="2" style="background: #f4f4f4; border: 1px solid #ccc; padding: 5px; margin-top: 6px; font-size: 8.5px; color: #555;">
              <b>MENSAJE:</b> Visite el portal web de la UGEL Ventanilla para verificar trámites de personal y resoluciones directorales: www.ugelventanilla.gob.pe
            </td>
          </tr>
        </table>
        
        <div style="margin-top: 15px; font-size: 8px; color: #666; font-style: italic; display: flex; align-items: center; gap: 8px;">
          <span>🌱 <i>Antes de imprimir esta boleta de pago, piense en su responsabilidad social y compromiso con el medio ambiente.</i></span>
        </div>
      </td>
    </tr>
  </table>

</body>
</html>