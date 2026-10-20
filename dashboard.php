<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'conexion.php';

// 1. Obtener el periodo activo (o el último registrado)
$periodoActivo = $pdo->query("SELECT * FROM periodos WHERE estado = 'abierto' LIMIT 1")->fetch();
if (!$periodoActivo) {
    $periodoActivo = $pdo->query("SELECT * FROM periodos ORDER BY anio DESC, mes DESC LIMIT 1")->fetch();
}
$id_periodo = $periodoActivo ? intval($periodoActivo['id']) : 0;
$esAbierto  = ($periodoActivo && $periodoActivo['estado'] === 'abierto');

// 2. Estadísticas de Personal Activo (Sexo, Condición, Incidencias)
$statsPersonal = $pdo->query("SELECT 
    COUNT(*) AS total_registrados,
    SUM(CASE WHEN estado = 'activo' THEN 1 ELSE 0 END) AS total_activos,
    SUM(CASE WHEN estado = 'activo' AND sexo = 'M' THEN 1 ELSE 0 END) AS hombres,
    SUM(CASE WHEN estado = 'activo' AND sexo = 'F' THEN 1 ELSE 0 END) AS mujeres,
    SUM(CASE WHEN estado = 'activo' AND condicion_laboral LIKE '%INDETERMINADO%' THEN 1 ELSE 0 END) AS indeterminados,
    SUM(CASE WHEN estado = 'activo' AND (condicion_laboral NOT LIKE '%INDETERMINADO%' OR condicion_laboral IS NULL) THEN 1 ELSE 0 END) AS determinados,
    SUM(CASE WHEN estado = 'activo' AND (dias_tardanza > 0 OR horas_tardanza > 0 OR minutos_tardanza > 0) THEN 1 ELSE 0 END) AS con_tardanzas,
    SUM(CASE WHEN estado = 'activo' AND (dias_lic_con_goce > 0 OR dias_lic_sin_goce > 0) THEN 1 ELSE 0 END) AS con_licencias,
    SUM(CASE WHEN estado = 'activo' AND monto_reintegro > 0 THEN 1 ELSE 0 END) AS con_reintegros,
    SUM(CASE WHEN estado = 'activo' AND (porcentaje_judicial > 0 OR monto_judicial > 0) THEN 1 ELSE 0 END) AS con_judiciales
  FROM trabajadores_cas")->fetch();

// 3. Totales Financieros del Periodo Actual
$totalesPlanilla = array(
    'procesados' => 0, 'bruto' => 0, 'descuentos' => 0, 
    'neto' => 0, 'essalud' => 0, 'onp' => 0, 'afp' => 0, 'judicial' => 0
);

if ($id_periodo > 0) {
    $stmtFin = $pdo->prepare("SELECT 
        COUNT(*) AS procesados,
        SUM(remuneracion_bruta + IFNULL(gratificacion, 0) + IFNULL(reintegro, 0)) AS bruto,
        SUM(total_descuentos) AS descuentos,
        SUM(neto_a_pagar) AS neto,
        SUM(essalud_patronal) AS essalud,
        SUM(CASE WHEN regimen_pensionario LIKE '%ONP%' THEN desc_pension ELSE 0 END) AS onp,
        SUM(CASE WHEN regimen_pensionario NOT LIKE '%ONP%' AND desc_pension > 0 THEN desc_pension ELSE 0 END) AS afp,
        SUM(desc_judicial) AS judicial
      FROM planilla_cas_mensual WHERE id_periodo = ?");
    $stmtFin->execute(array($id_periodo));
    $resFin = $stmtFin->fetch();
    if ($resFin && intval($resFin['procesados']) > 0) {
        $totalesPlanilla = $resFin;
    }
}
$costoTotalEntidad = floatval($totalesPlanilla['bruto']) + floatval($totalesPlanilla['essalud']);

// 4. Evolución Histórica del Gasto por Meses
$sqlEvolucion = "SELECT 
                    per.id, per.codigo, per.nombre_mes, per.anio,
                    IFNULL(SUM(p.remuneracion_bruta + IFNULL(p.gratificacion, 0) + IFNULL(p.reintegro, 0)), 0) AS total_bruto,
                    IFNULL(SUM(p.neto_a_pagar), 0) AS total_neto,
                    IFNULL(SUM(p.essalud_patronal), 0) AS total_essalud
                 FROM periodos per
                 LEFT JOIN planilla_cas_mensual p ON per.id = p.id_periodo
                 GROUP BY per.id, per.codigo, per.nombre_mes, per.anio
                 ORDER BY per.anio ASC, per.mes ASC
                 LIMIT 12";
$evolucionMeses = $pdo->query($sqlEvolucion)->fetchAll();

$labelsMeses  = array();
$dataBruto    = array();
$dataLiquido  = array();

foreach ($evolucionMeses as $em) {
    $labelsMeses[]  = substr($em['nombre_mes'], 0, 3) . ' ' . $em['anio'];
    $dataBruto[]    = round(floatval($em['total_bruto']), 2);
    $dataLiquido[]  = round(floatval($em['total_neto']), 2);
}

// Variación mes anterior
$variacionMonto = 0;
$variacionPorc  = 0;
$nMeses = count($dataBruto);
if ($nMeses >= 2) {
    $actualMes   = $dataBruto[$nMeses - 1];
    $anteriorMes = $dataBruto[$nMeses - 2];
    if ($anteriorMes > 0) {
        $variacionMonto = $actualMes - $anteriorMes;
        $variacionPorc  = round(($variacionMonto / $anteriorMes) * 100, 1);
    }
}

// 5. Gasto por Meta Presupuestal
$metasGasto = array();
if ($id_periodo > 0 && intval($totalesPlanilla['procesados']) > 0) {
    $stmtMetas = $pdo->prepare("SELECT meta, COUNT(*) AS servidores,
        SUM(remuneracion_bruta + IFNULL(gratificacion, 0) + IFNULL(reintegro, 0) + essalud_patronal) AS gasto_meta
      FROM planilla_cas_mensual WHERE id_periodo = ? GROUP BY meta ORDER BY gasto_meta DESC");
    $stmtMetas->execute(array($id_periodo));
    $metasGasto = $stmtMetas->fetchAll();
} else {
    $metasGasto = $pdo->query("SELECT meta, COUNT(*) AS servidores, SUM(remuneracion_mensual) AS gasto_meta
      FROM trabajadores_cas WHERE estado = 'activo' GROUP BY meta ORDER BY gasto_meta DESC")->fetchAll();
}

// 6. Alertas: Contratos Vencidos, Por Vencer y Cumpleaños del Mes
$hoy = new DateTime();
$hoy->setTime(0, 0, 0);
$mesActual = intval($hoy->format('m'));
$diaActual = intval($hoy->format('d'));

$nombresMeses = array(
    1=>'Enero', 2=>'Febrero', 3=>'Marzo', 4=>'Abril', 5=>'Mayo', 6=>'Junio',
    7=>'Julio', 8=>'Agosto', 9=>'Septiembre', 10=>'Octubre', 11=>'Noviembre', 12=>'Diciembre'
);
$nombreMesActual = $nombresMeses[$mesActual];

$personalActivoLista = $pdo->query("SELECT * FROM trabajadores_cas WHERE estado = 'activo' ORDER BY apellido_paterno ASC")->fetchAll();

$contratosVencidos  = array();
$contratosPorVencer = array();
$cumpleanosMes      = array();

foreach ($personalActivoLista as $p) {
    if (!empty($p['fecha_termino']) && $p['fecha_termino'] !== '0000-00-00') {
        $fTermino = new DateTime($p['fecha_termino']);
        $fTermino->setTime(0, 0, 0);
        $diff = $hoy->diff($fTermino);
        $diasRestantes = (int)$diff->format('%r%a');

        if ($diasRestantes < 0) {
            $p['dias_vencido'] = abs($diasRestantes);
            $contratosVencidos[] = $p;
        } elseif ($diasRestantes <= 15 || (intval($fTermino->format('m')) === $mesActual && intval($fTermino->format('Y')) === intval($hoy->format('Y')))) {
            $p['dias_restantes'] = $diasRestantes;
            $contratosPorVencer[] = $p;
        }
    }

    if (!empty($p['fecha_nacimiento']) && $p['fecha_nacimiento'] !== '0000-00-00') {
        $fNac = new DateTime($p['fecha_nacimiento']);
        if (intval($fNac->format('m')) === $mesActual) {
            $p['dia_cumple'] = intval($fNac->format('d'));
            $p['es_hoy']     = ($p['dia_cumple'] === $diaActual);
            $cumpleanosMes[] = $p;
        }
    }
}

usort($cumpleanosMes, function($a, $b) {
    return $a['dia_cumple'] - $b['dia_cumple'];
});
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard Institucional CAS - UGEL Ventanilla</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;600;700&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'Plus Jakarta Sans', sans-serif; }
    .font-mono { font-family: 'JetBrains Mono', monospace; }
  </style>
</head>
<body class="bg-[#f0f4f8] text-slate-700 min-h-screen flex antialiased">

  <!-- SIDEBAR IZQUIERDO FIJO -->
  <aside class="w-64 bg-[#0c1526] text-slate-300 flex flex-col flex-shrink-0 h-screen sticky top-0">
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

    <nav class="flex-1 px-4 py-6 space-y-1.5 text-sm font-semibold overflow-y-auto">
      <a href="dashboard.php" class="flex items-center gap-3.5 px-3.5 py-3 rounded-xl bg-gradient-to-r from-[#17243c] to-[#1c2c49] text-white font-bold shadow-md border border-slate-700/40">
        <svg class="w-5 h-5 text-[#00c982]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
        <span>Dashboard</span>
      </a>
      <a href="planillas.php" class="flex items-center gap-3.5 px-3.5 py-3 rounded-xl text-slate-400 hover:text-slate-100 hover:bg-white/5 transition-all">
        <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" /></svg>
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
        <span>Reportes SIAF</span>
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
  <main class="flex-1 p-6 lg:p-8 overflow-y-auto">
    
    <!-- Banner Superior -->
    <div class="bg-gradient-to-r from-[#0c1526] via-[#13223d] to-[#172a4a] rounded-3xl p-6 text-white shadow-lg mb-6 flex flex-col lg:flex-row lg:items-center justify-between gap-4">
      <div>
        <div class="flex items-center gap-2.5 mb-1.5">
          <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider <?php echo $esAbierto ? 'bg-[#00c982]/20 text-[#00c982] border border-[#00c982]/30' : 'bg-amber-400/20 text-amber-300'; ?>">
            <?php echo $esAbierto ? '● Periodo Activo (Abierto)' : '● Periodo Cerrado'; ?>
          </span>
          <span class="text-xs text-slate-400 font-mono">UGEL Ventanilla · D.L. 1057</span>
        </div>
        <h2 class="text-2xl font-extrabold tracking-tight">
          Dashboard General — <?php echo $periodoActivo ? htmlspecialchars($periodoActivo['nombre_mes'] . ' ' . $periodoActivo['anio']) : 'Sin Periodo'; ?>
        </h2>
      </div>

      <div class="flex flex-wrap items-center gap-2">
        <a href="planillas.php" class="px-4 py-2 bg-[#008f5d] hover:bg-[#00a86b] text-white rounded-xl font-bold text-xs shadow-md transition-all">
          Procesar Planilla
        </a>
        <a href="reportes.php" class="px-4 py-2 bg-white/10 hover:bg-white/20 text-white border border-white/15 rounded-xl font-bold text-xs transition-all">
          Compromiso SIAF
        </a>
        <a href="boletas_masivas.php" target="_blank" class="px-4 py-2 bg-white/10 hover:bg-white/20 text-white border border-white/15 rounded-xl font-bold text-xs transition-all">
          🖨️ Boletas
        </a>
      </div>
    </div>

    <!-- SECCIÓN 1: PANEL DE ALERTAS (CONTRATOS VENCIDOS, POR VENCER Y ONOMÁSTICOS) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
      
      <!-- 1. Contratos Vencidos -->
      <div class="bg-white rounded-2xl p-4 border-l-4 border-rose-500 shadow-sm flex flex-col justify-between">
        <div>
          <div class="flex items-center justify-between mb-2">
            <span class="text-[11px] font-extrabold text-rose-600 uppercase tracking-wider flex items-center gap-1.5">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
              Contratos Vencidos (Activos)
            </span>
            <span class="px-2 py-0.5 bg-rose-100 text-rose-700 rounded-full text-[10px] font-extrabold"><?php echo count($contratosVencidos); ?></span>
          </div>

          <?php if (empty($contratosVencidos)): ?>
            <p class="text-xs text-slate-400 py-3">✓ Ningún trabajador activo tiene contrato vencido.</p>
          <?php else: ?>
            <div class="space-y-1.5 max-h-36 overflow-y-auto pr-1">
              <?php foreach ($contratosVencidos as $cv): ?>
                <div class="p-2 bg-rose-50/70 border border-rose-200/70 rounded-xl flex items-center justify-between text-[11px]">
                  <div class="truncate pr-2">
                    <div class="font-bold text-slate-800 truncate"><?php echo htmlspecialchars($cv['apellido_paterno'] . ' ' . $cv['nombres']); ?></div>
                    <div class="text-[10px] text-rose-600 font-semibold">Venció: <?php echo $cv['fecha_termino']; ?> (hace <?php echo $cv['dias_vencido']; ?>d)</div>
                  </div>
                  <div class="flex items-center gap-1 flex-shrink-0">
                    <a href="historial_contratos.php?id=<?php echo $cv['id']; ?>" class="px-2 py-1 bg-white hover:bg-slate-100 text-indigo-600 border border-rose-200 rounded-lg font-bold text-[10px]">Adenda</a>
                    <a href="index.php?buscar=<?php echo urlencode($cv['dni']); ?>" class="px-2 py-1 bg-rose-600 hover:bg-rose-700 text-white rounded-lg font-bold text-[10px]">Ver</a>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
        <p class="text-[10px] text-slate-400 mt-2 pt-1.5 border-t border-slate-100">Evite pagos indebidos dando de baja o renovando adenda.</p>
      </div>

      <!-- 2. Contratos Por Vencer -->
      <div class="bg-white rounded-2xl p-4 border-l-4 border-amber-500 shadow-sm flex flex-col justify-between">
        <div>
          <div class="flex items-center justify-between mb-2">
            <span class="text-[11px] font-extrabold text-amber-600 uppercase tracking-wider flex items-center gap-1.5">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              Por Vencer (Próximos 15 días / Mes)
            </span>
            <span class="px-2 py-0.5 bg-amber-100 text-amber-800 rounded-full text-[10px] font-extrabold"><?php echo count($contratosPorVencer); ?></span>
          </div>

          <?php if (empty($contratosPorVencer)): ?>
            <p class="text-xs text-slate-400 py-3">✓ No hay contratos próximos a vencer este mes.</p>
          <?php else: ?>
            <div class="space-y-1.5 max-h-36 overflow-y-auto pr-1">
              <?php foreach ($contratosPorVencer as $cpv): ?>
                <div class="p-2 bg-amber-50/70 border border-amber-200/70 rounded-xl flex items-center justify-between text-[11px]">
                  <div class="truncate pr-2">
                    <div class="font-bold text-slate-800 truncate"><?php echo htmlspecialchars($cpv['apellido_paterno'] . ' ' . $cpv['nombres']); ?></div>
                    <div class="text-[10px] text-amber-700 font-semibold">
                      Termina: <b><?php echo $cpv['fecha_termino']; ?></b> 
                      (<?php echo ($cpv['dias_restantes'] === 0) ? '¡HOY!' : 'en ' . $cpv['dias_restantes'] . 'd'; ?>)
                    </div>
                  </div>
                  <a href="index.php?buscar=<?php echo urlencode($cpv['dni']); ?>" class="px-2 py-1 bg-amber-500 hover:bg-amber-600 text-white rounded-lg font-bold text-[10px] flex-shrink-0">
                    Días (<?php echo $cpv['dias_laborados']; ?>d)
                  </a>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
        <p class="text-[10px] text-slate-400 mt-2 pt-1.5 border-t border-slate-100">Si vence antes de fin de mes, ajuste sus días laborados.</p>
      </div>

      <!-- 3. Onomásticos del Mes -->
      <div class="bg-white rounded-2xl p-4 border-l-4 border-[#008f5d] shadow-sm flex flex-col justify-between">
        <div>
          <div class="flex items-center justify-between mb-2">
            <span class="text-[11px] font-extrabold text-[#008f5d] uppercase tracking-wider flex items-center gap-1.5">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 15.546c-.523 0-1.046.151-1.5.454a2.704 2.704 0 01-3 0 2.704 2.704 0 00-3 0 2.704 2.704 0 01-3 0 2.704 2.704 0 00-3 0 2.704 2.704 0 01-3 0 2.701 2.701 0 00-1.5-.454M9 6v2m3-2v2m3-2v2M9 3h.01M12 3h.01M15 3h.01M21 21v-7a2 2 0 00-2-2H5a2 2 0 00-2 2v7h18zm-3-9v-2a2 2 0 00-2-2H8a2 2 0 00-2 2v2h12z"/></svg>
              Onomásticos de <?php echo $nombreMesActual; ?>
            </span>
            <span class="px-2 py-0.5 bg-emerald-100 text-emerald-800 rounded-full text-[10px] font-extrabold"><?php echo count($cumpleanosMes); ?></span>
          </div>

          <?php if (empty($cumpleanosMes)): ?>
            <p class="text-xs text-slate-400 py-3">No hay cumpleaños registrados en <?php echo strtolower($nombreMesActual); ?>.</p>
          <?php else: ?>
            <div class="space-y-1.5 max-h-36 overflow-y-auto pr-1">
              <?php foreach ($cumpleanosMes as $cum): ?>
                <div class="p-2 <?php echo $cum['es_hoy'] ? 'bg-emerald-100/80 border border-emerald-300' : 'bg-slate-50 border border-slate-200/60'; ?> rounded-xl flex items-center justify-between text-[11px]">
                  <div class="truncate pr-2">
                    <div class="font-bold text-slate-800 truncate"><?php echo htmlspecialchars($cum['apellido_paterno'] . ' ' . $cum['nombres']); ?></div>
                    <div class="text-[10px] text-slate-500 truncate"><?php echo htmlspecialchars($cum['cargo']); ?></div>
                  </div>
                  <div class="flex-shrink-0">
                    <?php if ($cum['es_hoy']): ?>
                      <span class="px-2 py-0.5 bg-[#008f5d] text-white rounded-md text-[10px] font-extrabold animate-pulse">🎂 ¡HOY!</span>
                    <?php else: ?>
                      <span class="px-2 py-0.5 bg-white border border-slate-200 text-slate-700 rounded-md font-mono font-bold text-[10.5px]">
                        <?php echo sprintf('%02d/%02d', $cum['dia_cumple'], $mesActual); ?>
                      </span>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
        <p class="text-[10px] text-slate-400 mt-2 pt-1.5 border-t border-slate-100">Control de descanso por onomástico y saludo institucional.</p>
      </div>

    </div>

    <!-- SECCIÓN 2: TARJETAS KPI FINANCIERAS -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
      <div class="bg-white p-5 rounded-3xl border border-slate-200/80 shadow-sm">
        <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider block">1. Personal CAS Activo</span>
        <div class="flex items-baseline justify-between mt-1">
          <p class="text-2xl font-extrabold text-slate-800 font-mono"><?php echo intval($statsPersonal['total_activos']); ?></p>
          <span class="text-[11px] font-bold text-[#008f5d] bg-emerald-50 px-2 py-0.5 rounded-lg">De <?php echo intval($statsPersonal['total_registrados']); ?> reg.</span>
        </div>
        <div class="mt-2 pt-2 border-t border-slate-100 flex justify-between text-[11px] text-slate-500">
          <span>Hombres: <b><?php echo intval($statsPersonal['hombres']); ?></b></span>
          <span>Mujeres: <b><?php echo intval($statsPersonal['mujeres']); ?></b></span>
        </div>
      </div>

      <div class="bg-white p-5 rounded-3xl border border-slate-200/80 shadow-sm">
        <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider block">2. Total Bruto (2.3.2.8.1.1)</span>
        <div class="flex items-baseline justify-between mt-1">
          <p class="text-2xl font-extrabold text-slate-800 font-mono">S/ <?php echo number_format($totalesPlanilla['bruto'], 2); ?></p>
        </div>
        <div class="mt-2 pt-2 border-t border-slate-100 flex justify-between text-[11px]">
          <span class="text-slate-400">Variación vs. mes ant.:</span>
          <?php if ($variacionMonto > 0): ?>
            <span class="text-amber-600 font-bold font-mono">▲ +<?php echo $variacionPorc; ?>%</span>
          <?php elseif ($variacionMonto < 0): ?>
            <span class="text-emerald-600 font-bold font-mono">▼ <?php echo $variacionPorc; ?>%</span>
          <?php else: ?>
            <span class="text-slate-500 font-mono">0.0% (Sin cambio)</span>
          <?php endif; ?>
        </div>
      </div>

      <div class="bg-white p-5 rounded-3xl border border-slate-200/80 shadow-sm">
        <span class="text-[10px] font-extrabold text-emerald-600 uppercase tracking-wider block">3. Líquido a Pagar (Abono BN)</span>
        <p class="text-2xl font-extrabold text-[#008f5d] font-mono mt-1">S/ <?php echo number_format($totalesPlanilla['neto'], 2); ?></p>
        <div class="mt-2 pt-2 border-t border-slate-100 flex justify-between text-[11px] text-rose-600">
          <span>Total Descuentos:</span>
          <span class="font-mono font-bold">- S/ <?php echo number_format($totalesPlanilla['descuentos'], 2); ?></span>
        </div>
      </div>

      <div class="bg-white p-5 rounded-3xl border border-slate-200/80 shadow-sm">
        <span class="text-[10px] font-extrabold text-blue-600 uppercase tracking-wider block">4. Costo Total (Bruto + EsSalud)</span>
        <p class="text-2xl font-extrabold text-blue-700 font-mono mt-1">S/ <?php echo number_format($costoTotalEntidad, 2); ?></p>
        <div class="mt-2 pt-2 border-t border-slate-100 flex justify-between text-[11px] text-slate-500">
          <span>EsSalud (2.3.2.8.1.2):</span>
          <span class="font-mono font-bold text-blue-600">S/ <?php echo number_format($totalesPlanilla['essalud'], 2); ?></span>
        </div>
      </div>
    </div>

    <!-- SECCIÓN 3: GRÁFICOS INTERACTIVOS (CHART.JS) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
      
      <!-- Gráfico 1: Evolución del Gasto por Meses -->
      <div class="lg:col-span-2 bg-white rounded-3xl p-6 border border-slate-200/80 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
          <div>
            <h3 class="text-sm font-extrabold text-slate-800 uppercase tracking-wide">Evolución Mensual del Gasto de Planilla CAS</h3>
            <p class="text-[11px] text-slate-400">Comparativa histórica de Remuneración Bruta y Líquido a Pagar por Periodo</p>
          </div>
          <?php if ($variacionMonto != 0): ?>
            <span class="px-3 py-1 rounded-xl text-xs font-extrabold font-mono <?php echo ($variacionMonto > 0) ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200'; ?>">
              <?php echo ($variacionMonto > 0 ? 'Incremento: +S/ ' : 'Reducción: S/ ') . number_format($variacionMonto, 2) . ' (' . ($variacionMonto > 0 ? '+' : '') . $variacionPorc . '%)'; ?>
            </span>
          <?php endif; ?>
        </div>
        <div class="h-64">
          <canvas id="chartEvolucionGasto"></canvas>
        </div>
      </div>

      <!-- Gráfico 2: Distribución por Género (Hombres vs Mujeres) -->
      <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-sm flex flex-col justify-between">
        <div>
          <h3 class="text-sm font-extrabold text-slate-800 uppercase tracking-wide">Distribución por Sexo</h3>
          <p class="text-[11px] text-slate-400 mb-3">Proporción de Hombres y Mujeres en actividad</p>
          <div class="h-44 flex items-center justify-center">
            <canvas id="chartGenero"></canvas>
          </div>
        </div>

        <div class="mt-4 pt-4 border-t border-slate-100">
          <div class="flex justify-between text-xs font-bold text-slate-700 mb-1.5">
            <span>Indeterminados: <?php echo intval($statsPersonal['indeterminados']); ?></span>
            <span>Determinados: <?php echo intval($statsPersonal['determinados']); ?></span>
          </div>
          <?php 
            $totAct = max(1, intval($statsPersonal['total_activos']));
            $porcInd = round((intval($statsPersonal['indeterminados']) / $totAct) * 100);
          ?>
          <div class="w-full h-2.5 bg-amber-100 rounded-full overflow-hidden flex">
            <div class="h-full bg-[#008f5d]" style="width: <?php echo $porcInd; ?>%"></div>
          </div>
          <p class="text-[10px] text-slate-400 mt-1 text-center">Verde: Plazo Indeterminado (<?php echo $porcInd; ?>%) · Ámbar: Determinado</p>
        </div>
      </div>

    </div>

  </main>

  <!-- SCRIPTS DE GRÁFICOS (CHART.JS) -->
  <script>
    // Gráfico de Evolución Mensual
    const ctxEvolucion = document.getElementById('chartEvolucionGasto').getContext('2d');
    new Chart(ctxEvolucion, {
      type: 'bar',
      data: {
        labels: <?php echo json_encode($labelsMeses); ?>,
        datasets: [
          {
            label: 'Monto Bruto (S/)',
            data: <?php echo json_encode($dataBruto); ?>,
            backgroundColor: '#0c1526',
            borderRadius: 6,
            barPercentage: 0.65
          },
          {
            label: 'Líquido a Pagar (S/)',
            data: <?php echo json_encode($dataLiquido); ?>,
            backgroundColor: '#008f5d',
            borderRadius: 6,
            barPercentage: 0.65
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'top', labels: { font: { family: 'Plus Jakarta Sans', size: 11, weight: 'bold' } } }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              font: { family: 'JetBrains Mono', size: 10 },
              callback: function(value) { return 'S/ ' + value.toLocaleString('es-PE'); }
            }
          },
          x: {
            ticks: { font: { family: 'Plus Jakarta Sans', size: 11, weight: 'bold' } }
          }
        }
      }
    });

    // Gráfico Circular de Hombres vs Mujeres
    const ctxGenero = document.getElementById('chartGenero').getContext('2d');
    new Chart(ctxGenero, {
      type: 'doughnut',
      data: {
        labels: ['Hombres (Masculino)', 'Mujeres (Femenino)'],
        datasets: [{
          data: [<?php echo intval($statsPersonal['hombres']); ?>, <?php echo intval($statsPersonal['mujeres']); ?>],
          backgroundColor: ['#1e3a8a', '#00a86b'],
          borderWidth: 2,
          borderColor: '#ffffff',
          hoverOffset: 6
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '65%',
        plugins: {
          legend: { position: 'bottom', labels: { font: { family: 'Plus Jakarta Sans', size: 11, weight: 'bold' }, padding: 12 } }
        }
      }
    });
  </script>
</body>
</html>