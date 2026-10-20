<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'conexion.php';

// Obtener el periodo activo
$stmtPeriodo = $pdo->query("SELECT * FROM periodos WHERE estado = 'abierto' LIMIT 1");
$periodoActivo = $stmtPeriodo->fetch();

$mensaje = '';

// Procesar o recalcular la planilla del periodo actual
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'procesar' && $periodoActivo) {
    $id_periodo  = intval($periodoActivo['id']);
    $mesPeriodo  = intval($periodoActivo['mes']);
    $anioPeriodo = intval($periodoActivo['anio']);

    // 1. Base Imponible y Tope EsSalud CAS (45% de la UIT)
    $uitVigente = !empty($periodoActivo['uit_vigente']) ? floatval($periodoActivo['uit_vigente']) : 5500.00;
    $topeEssaludCAS = round($uitVigente * 0.45, 2); // S/ 2,475.00

    // 2. Cargar tabla maestra de comisiones SBS / AFP indexada por su ID
    $tasasDB = $pdo->query("SELECT * FROM comisiones_pensionarias")->fetchAll(PDO::FETCH_UNIQUE);

    // 3. Obtener todo el personal CAS activo
    $sqlPersonal = "SELECT * FROM trabajadores_cas WHERE estado = 'activo'";
    $personalActivo = $pdo->query($sqlPersonal)->fetchAll();

    foreach ($personalActivo as $p) {
        // A. Sueldo de Contrato vs. Remuneración Proporcional por Días Laborados
        $remContrato   = floatval($p['remuneracion_mensual']);
        $diasLaborados = isset($p['dias_laborados']) ? intval($p['dias_laborados']) : 30;
        if ($diasLaborados <= 0 || $diasLaborados > 30) {
            $diasLaborados = 30;
        }

        // Costo por día, hora y minuto (siempre sobre base comercial de 30 días del contrato)
        $costoDia    = $remContrato / 30;
        $costoHora   = $costoDia / 8;
        $costoMinuto = $costoHora / 60;

        // Remuneración Bruta Proporcional a los días laborados en el mes
        $remBruta = ($diasLaborados === 30) ? $remContrato : round($costoDia * $diasLaborados, 2);

        // B. Gratificación Progresiva CAS (D.S. N.° 142-2026-EF) en Julio (7) y Diciembre (12)
        $montoGratificacion = 0.00;
        if ($mesPeriodo === 7 || $mesPeriodo === 12) {
            switch ($anioPeriodo) {
                case 2026: $porcentajeBase = 0.10; break; // 10%
                case 2027: $porcentajeBase = 0.20; break; // 20%
                case 2028: $porcentajeBase = 0.30; break; // 30%
                case 2029: $porcentajeBase = 0.50; break; // 50%
                default:   $porcentajeBase = ($anioPeriodo >= 2030) ? 1.00 : 0.10; break;
            }

            $baseGratif     = $remContrato * $porcentajeBase;
            $fechaIngreso   = !empty($p['fecha_inicio']) ? new DateTime($p['fecha_inicio']) : new DateTime($anioPeriodo . '-01-01');
            $inicioSemestre = ($mesPeriodo === 7) ? new DateTime($anioPeriodo . '-01-01') : new DateTime($anioPeriodo . '-07-01');
            $finSemestre    = ($mesPeriodo === 7) ? new DateTime($anioPeriodo . '-06-30') : new DateTime($anioPeriodo . '-12-31');

            if ($fechaIngreso <= $inicioSemestre) {
                $mesesComputables = 6;
            } else {
                $intervalo = $fechaIngreso->diff($finSemestre);
                $mesesComputables = ($intervalo->y * 12) + $intervalo->m;
                if ($fechaIngreso->format('d') == '01') {
                    $mesesComputables += 1;
                }
                $mesesComputables = min(6, max(0, $mesesComputables));
            }

            if ($mesesComputables > 0) {
                $montoGratificacion = round(($baseGratif / 6) * $mesesComputables, 2);
            }
        }

        // C. Reintegros del Periodo
        $montoReintegro    = isset($p['monto_reintegro']) ? floatval($p['monto_reintegro']) : 0.00;
        $motivoReintegro   = !empty($p['motivo_reintegro']) ? trim($p['motivo_reintegro']) : '';
        $esReintegroAfecto = isset($p['reintegro_afecto']) ? intval($p['reintegro_afecto']) : 1;

        // Total de Ingresos del Periodo
        $totalIngresos = $remBruta + $montoGratificacion + $montoReintegro;

        // D. Inasistencias y Tardanzas
        $diasInas     = intval($p['dias_tardanza']);
        $horasTard    = intval($p['horas_tardanza']);
        $minTard      = intval($p['minutos_tardanza']);
        $descInasTard = round(($diasInas * $costoDia) + ($horasTard * $costoHora) + ($minTard * $costoMinuto), 2);

        // E. Licencias (Con Goce y Sin Goce)
        $diasLicConGoce = isset($p['dias_lic_con_goce']) ? intval($p['dias_lic_con_goce']) : 0;
        $diasLicSinGoce = isset($p['dias_lic_sin_goce']) ? intval($p['dias_lic_sin_goce']) : 0;
        $descLicSinGoce = round($diasLicSinGoce * $costoDia, 2);

        // F. Base Imponible Afecta a Ley (ONP/AFP)
        $baseParaPension = ($remBruta - $descInasTard - $descLicSinGoce) + ($esReintegroAfecto ? $montoReintegro : 0);
        $remAfecta       = max(0, $baseParaPension);

        // G. Aporte Pensionario (ONP o AFP Flujo/Mixta o Exonerado según tabla SBS)
        $idRegimen = intval($p['id_regimen']);
        if (isset($tasasDB[$idRegimen])) {
            $porcentajePension = floatval($tasasDB[$idRegimen]['total_porcentaje']) / 100;
            $nomRegimen = $tasasDB[$idRegimen]['nombre_completo'];
        } else {
            $porcentajePension = 0.13;
            $nomRegimen = 'ONP (LEY 19990)';
        }
        $descPension = round($remAfecta * $porcentajePension, 2);

        // H. Descuentos de Terceros
        $descTerceros = floatval($p['descuento_terceros']);

        // I. Renta de 4ta Categoría (8% - Es un descuento de Ley)
        $descRenta = 0.00;
        $ingresosEfectivosMes = max(0, ($remBruta - $descInasTard - $descLicSinGoce) + $montoGratificacion + $montoReintegro);
        if (intval($p['renta_cuarta_aplica']) === 1 && intval($p['suspension_cuarta']) === 0) {
            $descRenta = round($ingresosEfectivosMes * 0.08, 2);
        }

        // J. Descuento Judicial / Alimentos (Según modalidad de Sentencia Judicial)
        $descJudicial = 0.00;
        $baseCalcJud  = isset($p['base_calculo_judicial']) ? $p['base_calculo_judicial'] : 'NETO_LEY';
        $cuentaJud    = isset($p['cuenta_judicial']) ? trim($p['cuenta_judicial']) : '';

        if (floatval($p['porcentaje_judicial']) > 0) {
            $porcJud = floatval($p['porcentaje_judicial']) / 100;

            if ($baseCalcJud === 'BRUTO') {
                $baseParaJudicial = $ingresosEfectivosMes;
            } else {
                $baseParaJudicial = max(0, $ingresosEfectivosMes - $descPension - $descRenta);
            }

            $descJudicial = round($baseParaJudicial * $porcJud, 2);
        } elseif (floatval($p['monto_judicial']) > 0) {
            $descJudicial = floatval($p['monto_judicial']);
        }

        // K. Consolidado de Descuentos y Líquido a Pagar
        $totalDesc = $descInasTard + $descLicSinGoce + $descPension + $descTerceros + $descJudicial + $descRenta;
        $netoPagar = max(0, round($totalIngresos - $totalDesc, 2));

        // L. EsSalud Patronal (9% con tope legal del 45% UIT)
        $baseImponibleEssalud = min($remAfecta, $topeEssaludCAS);
        $essaludPatronal      = round($baseImponibleEssalud * 0.09, 2);

        $nombreCompleto = trim($p['apellido_paterno'] . ' ' . $p['apellido_materno'] . ', ' . $p['nombres']);

        // Guardar o actualizar registro en planilla_cas_mensual
        $sqlGuardar = "INSERT INTO planilla_cas_mensual 
            (id_periodo, id_trabajador, dni, nombre_completo, cargo, meta, establecimiento, regimen_pensionario, dias_pagados,
             remuneracion_bruta, gratificacion, reintegro, motivo_reintegro,
             desc_inasistencias_tardanzas, dias_inasistencia, horas_tardanza, minutos_tardanza,
             dias_lic_con_goce, dias_lic_sin_goce, desc_licencia_sin_goce,
             desc_pension, desc_terceros, detalle_terceros, desc_judicial, num_expediente, cuenta_judicial, desc_renta_cuarta,
             total_descuentos, neto_a_pagar, essalud_patronal)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
             nombre_completo = VALUES(nombre_completo),
             cargo = VALUES(cargo),
             meta = VALUES(meta),
             establecimiento = VALUES(establecimiento),
             regimen_pensionario = VALUES(regimen_pensionario),
             dias_pagados = VALUES(dias_pagados),
             remuneracion_bruta = VALUES(remuneracion_bruta),
             gratificacion = VALUES(gratificacion),
             reintegro = VALUES(reintegro),
             motivo_reintegro = VALUES(motivo_reintegro),
             desc_inasistencias_tardanzas = VALUES(desc_inasistencias_tardanzas),
             dias_inasistencia = VALUES(dias_inasistencia),
             horas_tardanza = VALUES(horas_tardanza),
             minutos_tardanza = VALUES(minutos_tardanza),
             dias_lic_con_goce = VALUES(dias_lic_con_goce),
             dias_lic_sin_goce = VALUES(dias_lic_sin_goce),
             desc_licencia_sin_goce = VALUES(desc_licencia_sin_goce),
             desc_pension = VALUES(desc_pension),
             desc_terceros = VALUES(desc_terceros),
             detalle_terceros = VALUES(detalle_terceros),
             desc_judicial = VALUES(desc_judicial),
             num_expediente = VALUES(num_expediente),
             cuenta_judicial = VALUES(cuenta_judicial),
             desc_renta_cuarta = VALUES(desc_renta_cuarta),
             total_descuentos = VALUES(total_descuentos),
             neto_a_pagar = VALUES(neto_a_pagar),
             essalud_patronal = VALUES(essalud_patronal)";
        
        $stmtG = $pdo->prepare($sqlGuardar);
        $stmtG->execute(array(
            $id_periodo, $p['id'], $p['dni'], $nombreCompleto, $p['cargo'], $p['meta'], $p['establecimiento'], $nomRegimen, $diasLaborados,
            $remBruta, $montoGratificacion, $montoReintegro, $motivoReintegro,
            $descInasTard, $diasInas, $horasTard, $minTard,
            $diasLicConGoce, $diasLicSinGoce, $descLicSinGoce,
            $descPension, $descTerceros, $p['detalle_terceros'], $descJudicial, $p['num_expediente'], $cuentaJud, $descRenta,
            $totalDesc, $netoPagar, $essaludPatronal
        ));
    }

    $mensaje = "¡Planilla procesada con éxito para el periodo " . $periodoActivo['codigo'] . "!";
}

// Cargar la nómina procesada del periodo actual
$registros = array();
$totales = array('bruto' => 0, 'descuentos' => 0, 'neto' => 0, 'essalud' => 0);

if ($periodoActivo) {
    $stmtCargar = $pdo->prepare("SELECT * FROM planilla_cas_mensual WHERE id_periodo = ? ORDER BY nombre_completo ASC");
    $stmtCargar->execute(array($periodoActivo['id']));
    $registros = $stmtCargar->fetchAll();

    foreach ($registros as $reg) {
        $ingresosServidor = floatval($reg['remuneracion_bruta']) + floatval(isset($reg['gratificacion']) ? $reg['gratificacion'] : 0) + floatval(isset($reg['reintegro']) ? $reg['reintegro'] : 0);
        $totales['bruto']      += $ingresosServidor;
        $totales['descuentos'] += $reg['total_descuentos'];
        $totales['neto']       += $reg['neto_a_pagar'];
        $totales['essalud']    += $reg['essalud_patronal'];
    }
}

// Verificar si hay trabajadores activos con contrato vencido respecto al periodo abierto
$vencidosEnPlanilla = array();
if ($periodoActivo) {
    $inicioPeriodo = $periodoActivo['codigo'] . '-01'; // Ej: 2026-10-01
    $stmtVenc = $pdo->prepare("SELECT dni, apellido_paterno, apellido_materno, nombres, fecha_termino 
                               FROM trabajadores_cas 
                               WHERE estado = 'activo' 
                                 AND fecha_termino IS NOT NULL 
                                 AND fecha_termino <> '0000-00-00' 
                                 AND fecha_termino < ?");
    $stmtVenc->execute(array($inicioPeriodo));
    $vencidosEnPlanilla = $stmtVenc->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Planilla CAS Mensual - UGEL Ventanilla</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'Plus Jakarta Sans', sans-serif; }
    .font-mono { font-family: 'JetBrains Mono', monospace; }
  </style>
</head>
<body class="bg-[#f0f4f8] text-slate-700 min-h-screen flex antialiased">

  <!-- SIDEBAR IZQUIERDO -->
  <aside class="w-64 bg-[#0c1526] text-slate-300 flex flex-col flex-shrink-0 min-h-screen">
    <div class="px-6 py-6 border-b border-slate-800/60 flex items-center gap-3.5">
      <div class="w-10 h-10 rounded-xl bg-[#008f5d]/20 border border-[#00a86b]/40 flex items-center justify-center text-[#00c982] shadow-inner">
        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5" />
        </svg>
      </div>
      <div>
        <h1 class="text-base font-extrabold text-white tracking-tight leading-tight">Planillas CAS</h1>
        <p class="text-[11px] text-slate-400 font-medium">D.L. 1057 - Perú</p>
      </div>
    </div>
<!-- Menú de Navegación -->
    <nav class="flex-1 px-4 py-6 space-y-1.5 text-sm font-semibold">
	<a href="dashboard.php" class="flex items-center gap-3.5 px-3.5 py-3 rounded-xl text-slate-400 hover:text-slate-100 hover:bg-white/5 transition-all">
 	 <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 	1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
 	 <span>Dashboard</span>
	</a>
      <a href="planillas.php" class="flex items-center gap-3.5 px-3.5 py-3 rounded-xl bg-gradient-to-r from-[#17243c] to-[#1c2c49] text-white font-bold shadow-md border border-slate-700/40">
        <svg class="w-5 h-5 text-[#00c982]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" /></svg>
        <span>Planillas</span>
      </a>
      <a href="periodos.php" class="flex items-center gap-3.5 px-3.5 py-3 rounded-xl text-slate-400 hover:text-slate-100 hover:bg-white/5 transition-all">
        <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
        <span>Periodos</span>
      </a>
      <a href="index.php" class="flex items-center gap-3.5 px-3.5 py-3 rounded-xl text-slate-400 hover:text-slate-100 hover:bg-white/5 transition-all">
        <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
        <span>Personal CAS</span>
      </a>
      <a href="calculadora_cas.php" class="flex items-center gap-3.5 px-3.5 py-3 rounded-xl text-slate-400 hover:text-slate-100 hover:bg-white/5 transition-all">
        <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>
        <span>Calculadora DL 1057</span>
      </a>
      <a href="reportes.php" class="flex items-center gap-3.5 px-3.5 py-3 rounded-xl text-slate-400 hover:text-slate-100 hover:bg-white/5 transition-all">
        <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
        <span>Reportes</span>
      </a>
      <a href="configuracion.php" class="flex items-center gap-3.5 px-3.5 py-3 rounded-xl text-slate-400 hover:text-slate-100 hover:bg-white/5 transition-all">
        <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
        <span>Configuración</span>
      </a>
    </nav>

    <div class="p-4 border-t border-slate-800/60 bg-[#0a1120] mt-auto">
      <div class="flex items-center justify-between">
        <div class="truncate pr-2">
          <p class="text-xs font-bold text-white truncate"><?php echo htmlspecialchars(isset($_SESSION['usuario_nombre']) ? $_SESSION['usuario_nombre'] : 'Especialista'); ?></p>
          <p class="text-[10px] text-[#00c982] font-semibold truncate"><?php echo htmlspecialchars(isset($_SESSION['usuario_rol']) ? $_SESSION['usuario_rol'] : 'CAS'); ?></p>
        </div>
        <a href="logout.php" title="Cerrar Sesión" class="p-2 text-slate-400 hover:text-rose-400 hover:bg-white/5 rounded-xl transition-all">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
        </a>
      </div>
    </div>
  </aside>

  <!-- CONTENIDO PRINCIPAL -->
  <main class="flex-1 p-8 lg:p-10 overflow-y-auto">
    <?php if (!empty($vencidosEnPlanilla)): ?>
      <div class="mb-6 p-4 bg-rose-50 border-l-4 border-rose-600 text-rose-900 rounded-2xl text-xs shadow-sm">
        <div class="font-extrabold uppercase tracking-wider text-rose-700 mb-1 flex items-center gap-2">
          <svg class="w-5 h-5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
          ¡Atención! Hay <?php echo count($vencidosEnPlanilla); ?> servidor(es) en estado ACTIVO cuyo contrato venció antes de este periodo:
        </div>
        <ul class="list-disc list-inside space-y-0.5 text-[11px] font-semibold text-rose-800 ml-1">
          <?php foreach ($vencidosEnPlanilla as $vp): ?>
            <li>
              <?php echo htmlspecialchars($vp['apellido_paterno'] . ' ' . $vp['apellido_materno'] . ', ' . $vp['nombres']); ?> 
              (DNI: <?php echo htmlspecialchars($vp['dni']); ?>) — <b>Fecha de término: <?php echo htmlspecialchars($vp['fecha_termino']); ?></b>
            </li>
          <?php endforeach; ?>
        </ul>
        <div class="mt-2">
          <a href="index.php" class="inline-block px-3 py-1 bg-rose-600 hover:bg-rose-700 text-white rounded-lg font-bold text-[11px]">
            Ir a Personal CAS para cesar o renovar adenda →
          </a>
        </div>
      </div>
    <?php endif; ?>

    <div class="flex items-center justify-between mb-6">
      <div>
        <h2 class="text-3xl font-extrabold text-[#111827] tracking-tight">Planilla Mensual CAS</h2>
        <p class="text-sm text-slate-500 mt-1 font-medium">
          <?php if ($periodoActivo): ?>
            Periodo Activo: <b class="text-emerald-700"><?php echo htmlspecialchars($periodoActivo['nombre_mes'] . ' ' . $periodoActivo['anio']); ?> (<?php echo htmlspecialchars($periodoActivo['codigo']); ?>)</b>
          <?php else: ?>
            <span class="text-rose-500 font-semibold">No hay ningún periodo abierto. Debe abrir uno en el menú Periodos.</span>
          <?php endif; ?>
        </p>
      </div>

      <?php if ($periodoActivo): ?>
        <form method="POST">
          <input type="hidden" name="accion" value="procesar">
          <button type="submit" class="flex items-center gap-2 bg-[#008f5d] hover:bg-[#007a4f] text-white px-5 py-2.5 rounded-xl font-bold text-sm transition-all shadow-[0_4px_14px_rgba(0,143,93,0.35)] active:scale-95">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            Procesar / Recalcular Planilla
          </button>
        </form>
      <?php endif; ?>
    </div>

    <?php if (!empty($mensaje)): ?>
      <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-2xl text-xs font-semibold flex items-center gap-2">
        <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        <span><?php echo htmlspecialchars($mensaje); ?></span>
      </div>
    <?php endif; ?>

    <!-- Resumen de Planilla (KPIs) -->
    <div class="grid grid-cols-4 gap-4 mb-6">
      <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-[0_2px_10px_rgba(0,0,0,0.02)]">
        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Ingresos Brutos</span>
        <p class="text-xl font-extrabold text-slate-800 font-mono mt-1">S/ <?php echo number_format($totales['bruto'], 2); ?></p>
      </div>
      <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-[0_2px_10px_rgba(0,0,0,0.02)]">
        <span class="text-xs font-bold text-rose-500 uppercase tracking-wider">Total Descuentos</span>
        <p class="text-xl font-extrabold text-rose-600 font-mono mt-1">S/ <?php echo number_format($totales['descuentos'], 2); ?></p>
      </div>
      <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-[0_2px_10px_rgba(0,0,0,0.02)]">
        <span class="text-xs font-bold text-emerald-600 uppercase tracking-wider">Líquido a Pagar</span>
        <p class="text-xl font-extrabold text-emerald-700 font-mono mt-1">S/ <?php echo number_format($totales['neto'], 2); ?></p>
      </div>
      <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-[0_2px_10px_rgba(0,0,0,0.02)]">
        <span class="text-xs font-bold text-blue-500 uppercase tracking-wider">EsSalud Patronal (9%)</span>
        <p class="text-xl font-extrabold text-blue-700 font-mono mt-1">S/ <?php echo number_format($totales['essalud'], 2); ?></p>
      </div>
    </div>

    <!-- Barra de Exportaciones -->
    <?php if ($periodoActivo && !empty($registros)): ?>
    <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-[0_2px_12px_rgba(0,0,0,0.03)] mb-6 flex flex-wrap items-center justify-between gap-3">
      <div class="flex items-center gap-2">
        <span class="text-xs font-bold text-slate-700 uppercase tracking-wider flex items-center gap-1.5">
          <svg class="w-4 h-4 text-[#008f5d]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
          Exportación y Emisiones:
        </span>
      </div>

      <div class="flex flex-wrap items-center gap-2">
        <a href="exportar_excel.php" class="inline-flex items-center gap-2 bg-[#008f5d] hover:bg-[#007a4f] text-white px-3.5 py-2 rounded-xl text-xs font-bold shadow-sm transition-all active:scale-95">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
          Exportar Excel (.xls)
        </a>
        <a href="boletas_masivas.php" target="_blank" class="inline-flex items-center gap-2 bg-[#0c1526] hover:bg-slate-800 text-white px-3.5 py-2 rounded-xl text-xs font-bold shadow-sm transition-all active:scale-95">
          <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
          Generar Boletas Masivas
        </a>
        <a href="exportar_plame.php?tipo=rem" class="inline-flex items-center gap-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 py-2 rounded-xl text-xs font-bold border border-slate-200 transition-all">
          <span>PLAME (.rem)</span>
        </a>
        <a href="exportar_plame.php?tipo=jor" class="inline-flex items-center gap-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 py-2 rounded-xl text-xs font-bold border border-slate-200 transition-all">
          <span>PLAME (.jor)</span>
        </a>
        <a href="exportar_mcpp.php" class="inline-flex items-center gap-1.5 bg-blue-50 hover:bg-blue-100 text-blue-700 px-3 py-2 rounded-xl text-xs font-bold border border-blue-200 transition-all">
          <span>MCPP Web (.txt)</span>
        </a>
      </div>
    </div>
    <?php endif; ?>

    <!-- Tabla Detallada con Generación de Boletas -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-[0_2px_12px_rgba(0,0,0,0.03)] overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead>
            <tr class="border-b border-slate-200 bg-white text-[11px] font-bold text-slate-400 uppercase tracking-wider">
              <th class="py-4 px-6">DNI</th>
              <th class="py-4 px-6">SERVIDOR CAS</th>
              <th class="py-4 px-4 text-center">RÉGIMEN / COMISIÓN</th>
              <th class="py-4 px-4 text-right">INGRESOS</th>
              <th class="py-4 px-4 text-right">DESC. LEY</th>
              <th class="py-4 px-4 text-right">JUD / TERC</th>
              <th class="py-4 px-4 text-right">NETO A PAGAR</th>
              <th class="py-4 px-6 text-center">BOLETA OFICIAL</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 font-medium">
            <?php if (empty($registros)): ?>
              <tr>
                <td colspan="8" class="text-center py-10 text-slate-400">
                  No se ha procesado la nómina para este periodo. Haga clic en <b>Procesar / Recalcular Planilla</b>.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($registros as $row): 
                $ingresosFila = floatval($row['remuneracion_bruta']) + floatval(isset($row['gratificacion']) ? $row['gratificacion'] : 0) + floatval(isset($row['reintegro']) ? $row['reintegro'] : 0);
                $descLey = $row['desc_pension'] + $row['desc_inasistencias_tardanzas'] + $row['desc_licencia_sin_goce'] + $row['desc_renta_cuarta'];
                $descOtros = $row['desc_judicial'] + $row['desc_terceros'];
              ?>
              <tr class="hover:bg-slate-50/80 transition-colors">
                <td class="py-4 px-6 font-mono text-slate-600 font-bold"><?php echo htmlspecialchars($row['dni']); ?></td>
                <td class="py-4 px-6">
                  <div class="font-bold text-slate-800 uppercase"><?php echo htmlspecialchars($row['nombre_completo']); ?></div>
                  <div class="text-[11px] text-slate-400 font-normal"><?php echo htmlspecialchars($row['cargo']); ?></div>
                </td>
                <td class="py-4 px-4 text-center">
                  <span class="inline-block bg-slate-100 text-slate-700 px-2.5 py-1 rounded-md text-[10px] font-bold">
                    <?php echo htmlspecialchars($row['regimen_pensionario']); ?>
                  </span>
                </td>
                <td class="py-4 px-4 text-right font-mono font-bold text-slate-700">
                  S/ <?php echo number_format($ingresosFila, 2); ?>
                  <?php if (isset($row['reintegro']) && floatval($row['reintegro']) > 0): ?>
                    <span class="block text-[10px] text-emerald-600 font-normal">+ Reintegro: S/ <?php echo number_format($row['reintegro'], 2); ?></span>
                  <?php endif; ?>
                </td>
                <td class="py-4 px-4 text-right font-mono text-rose-600">S/ <?php echo number_format($descLey, 2); ?></td>
                <td class="py-4 px-4 text-right font-mono text-rose-600">S/ <?php echo number_format($descOtros, 2); ?></td>
                <td class="py-4 px-4 text-right font-mono font-bold text-emerald-700">
                  S/ <?php echo number_format($row['neto_a_pagar'], 2); ?>
                </td>
                <td class="py-4 px-6 text-center">
                  <a href="generar_boleta.php?id=<?php echo $row['id']; ?>" target="_blank"
                     class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-[#008f5d] hover:bg-[#007a4f] text-white rounded-lg text-xs font-bold transition-all shadow-sm">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    Ver Boleta
                  </a>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</body>
</html>