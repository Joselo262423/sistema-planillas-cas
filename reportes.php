<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'conexion.php';

// Obtener todos los periodos para permitir consultar el actual o históricos
$periodosLista = $pdo->query("SELECT * FROM periodos ORDER BY anio DESC, mes DESC")->fetchAll();

// Determinar qué periodo mostrar (por defecto el abierto o el seleccionado por GET)
$id_periodo_sel = isset($_GET['id_periodo']) ? intval($_GET['id_periodo']) : 0;

if ($id_periodo_sel > 0) {
    $stmtPer = $pdo->prepare("SELECT * FROM periodos WHERE id = ?");
    $stmtPer->execute(array($id_periodo_sel));
    $periodoEvaluado = $stmtPer->fetch();
} else {
    $periodoEvaluado = $pdo->query("SELECT * FROM periodos WHERE estado = 'abierto' LIMIT 1")->fetch();
    if (!$periodoEvaluado && !empty($periodosLista)) {
        $periodoEvaluado = $periodosLista[0];
    }
}

$id_periodo = $periodoEvaluado ? intval($periodoEvaluado['id']) : 0;

// 1. CONSULTA PRINCIPAL: RESUMEN SIAF POR META Y ESTABLECIMIENTO
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
$stmtSiaf = $pdo->prepare($sqlSiaf);
$stmtSiaf->execute(array($id_periodo));
$filasSiaf = $stmtSiaf->fetchAll();

// Agrupar filas por Meta para calcular subtotales por cada Meta Presupuestal
$metasAgrupadas = array();
$granTotal = array(
    'cant' => 0, 'bruto' => 0, 'faltas' => 0, 'onp' => 0, 'afp' => 0,
    'cuarta' => 0, 'judicial' => 0, 'terceros' => 0, 'descuentos' => 0,
    'liquido' => 0, 'essalud' => 0
);

foreach ($filasSiaf as $f) {
    $m = !empty($f['meta']) ? $f['meta'] : 'S/M';
    if (!isset($metasAgrupadas[$m])) {
        $metasAgrupadas[$m] = array(
            'establecimientos' => array(),
            'subtotal' => array(
                'cant' => 0, 'bruto' => 0, 'faltas' => 0, 'onp' => 0, 'afp' => 0,
                'cuarta' => 0, 'judicial' => 0, 'terceros' => 0, 'descuentos' => 0,
                'liquido' => 0, 'essalud' => 0
            )
        );
    }
    $metasAgrupadas[$m]['establecimientos'][] = $f;

    // Sumar al subtotal de la Meta
    $metasAgrupadas[$m]['subtotal']['cant']       += intval($f['cant_servidores']);
    $metasAgrupadas[$m]['subtotal']['bruto']      += floatval($f['total_bruto']);
    $metasAgrupadas[$m]['subtotal']['faltas']     += floatval($f['desc_faltas_lic']);
    $metasAgrupadas[$m]['subtotal']['onp']        += floatval($f['desc_onp']);
    $metasAgrupadas[$m]['subtotal']['afp']        += floatval($f['desc_afp']);
    $metasAgrupadas[$m]['subtotal']['cuarta']     += floatval($f['desc_cuarta']);
    $metasAgrupadas[$m]['subtotal']['judicial']   += floatval($f['desc_judicial']);
    $metasAgrupadas[$m]['subtotal']['terceros']   += floatval($f['desc_terceros']);
    $metasAgrupadas[$m]['subtotal']['descuentos'] += floatval($f['total_descuentos']);
    $metasAgrupadas[$m]['subtotal']['liquido']    += floatval($f['total_liquido']);
    $metasAgrupadas[$m]['subtotal']['essalud']    += floatval($f['total_essalud']);

    // Sumar al Gran Total General
    $granTotal['cant']       += intval($f['cant_servidores']);
    $granTotal['bruto']      += floatval($f['total_bruto']);
    $granTotal['faltas']     += floatval($f['desc_faltas_lic']);
    $granTotal['onp']        += floatval($f['desc_onp']);
    $granTotal['afp']        += floatval($f['desc_afp']);
    $granTotal['cuarta']     += floatval($f['desc_cuarta']);
    $granTotal['judicial']   += floatval($f['desc_judicial']);
    $granTotal['terceros']   += floatval($f['desc_terceros']);
    $granTotal['descuentos'] += floatval($f['total_descuentos']);
    $granTotal['liquido']    += floatval($f['total_liquido']);
    $granTotal['essalud']    += floatval($f['total_essalud']);
}

// 2. DESGLOSE DE AFP Y ONP POR META (PARA AFPNET Y PDT PLAME)
$sqlAfpMeta = "SELECT meta, regimen_pensionario, COUNT(*) as afiliados, SUM(desc_pension) as monto_pension
               FROM planilla_cas_mensual
               WHERE id_periodo = ? AND desc_pension > 0
               GROUP BY meta, regimen_pensionario
               ORDER BY meta ASC, regimen_pensionario ASC";
$stmtAM = $pdo->prepare($sqlAfpMeta);
$stmtAM->execute(array($id_periodo));
$desglosePensionesMeta = $stmtAM->fetchAll();

// 3. DETALLE NOMINAL DE JUDICIALES Y TERCEROS POR META (PARA TESORERÍA)
$sqlJudTerc = "SELECT p.meta, p.dni, p.nombre_completo, p.desc_judicial, p.num_expediente, 
                      t.beneficiario_judicial, t.cuenta_judicial, t.base_calculo_judicial,
                      p.desc_terceros, p.detalle_terceros
               FROM planilla_cas_mensual p
               INNER JOIN trabajadores_cas t ON p.id_trabajador = t.id
               WHERE p.id_periodo = ? AND (p.desc_judicial > 0 OR p.desc_terceros > 0)
               ORDER BY p.meta ASC, p.nombre_completo ASC";
$stmtJT = $pdo->prepare($sqlJudTerc);
$stmtJT->execute(array($id_periodo));
$detalleJudTerc = $stmtJT->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Resumen SIAF por Meta y Establecimiento - UGEL Ventanilla</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;600;700&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'Plus Jakarta Sans', sans-serif; }
    .font-mono { font-family: 'JetBrains Mono', monospace; }
    @media print {
      aside, .no-print { display: none !important; }
      main { padding: 0 !important; }
      body { background: #fff !important; font-size: 10px !important; }
      .print-shadow-none { box-shadow: none !important; border: 1px solid #cbd5e1 !important; }
      @page { size: A4 landscape; margin: 8mm; }
    }
  </style>
</head>
<body class="bg-[#f0f4f8] text-slate-700 min-h-screen flex antialiased">

  <!-- SIDEBAR IZQUIERDO FIJO -->
  <aside class="w-64 bg-[#0c1526] text-slate-300 flex flex-col flex-shrink-0 h-screen sticky top-0">
    <div class="px-6 py-6 border-b border-slate-800/60 flex items-center gap-3.5">
      <div class="w-10 h-10 rounded-xl bg-[#008f5d]/20 border border-[#00a86b]/40 flex items-center justify-center text-[#00c982] shadow-inner">
        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5" /></svg>
      </div>
      <div>
        <h1 class="text-base font-extrabold text-white tracking-tight leading-tight">Planillas CAS</h1>
        <p class="text-[11px] text-slate-400 font-medium">D.L. 1057 - Perú</p>
      </div>
    </div>
<!-- Menú de Navegación -->
    <nav class="flex-1 px-4 py-6 space-y-1.5 text-sm font-semibold">
	<a href="dashboard.php" class="flex items-center gap-3.5 px-3.5 py-3 rounded-xl text-slate-400 hover:text-slate-100 hover:bg-white/5 transition-all">
  	<svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 		1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
 	 <span>Dashboard</span>
	</a>
      <a href="planillas.php" class="flex items-center gap-3.5 px-3.5 py-3 rounded-xl text-slate-400 hover:text-slate-100 hover:bg-white/5 transition-all"><svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" /></svg><span>Planillas</span></a>
      <a href="periodos.php" class="flex items-center gap-3.5 px-3.5 py-3 rounded-xl text-slate-400 hover:text-slate-100 hover:bg-white/5 transition-all"><svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg><span>Periodos</span></a>
      <a href="index.php" class="flex items-center gap-3.5 px-3.5 py-3 rounded-xl text-slate-400 hover:text-slate-100 hover:bg-white/5 transition-all"><svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg><span>Personal CAS</span></a>
      <a href="calculadora_cas.php" class="flex items-center gap-3.5 px-3.5 py-3 rounded-xl text-slate-400 hover:text-slate-100 hover:bg-white/5 transition-all"><svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg><span>Calculadora DL 1057</span></a>
      <a href="reportes.php" class="flex items-center gap-3.5 px-3.5 py-3 rounded-xl bg-gradient-to-r from-[#17243c] to-[#1c2c49] text-white font-bold shadow-md border border-slate-700/40"><svg class="w-5 h-5 text-[#00c982]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg><span>Reportes SIAF</span></a>
      <a href="configuracion.php" class="flex items-center gap-3.5 px-3.5 py-3 rounded-xl text-slate-400 hover:text-slate-100 hover:bg-white/5 transition-all"><svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg><span>Configuración</span></a>
    </nav>

    <div class="p-4 border-t border-slate-800/60 bg-[#0a1120] mt-auto">
      <div class="flex items-center justify-between">
        <div class="truncate pr-2">
          <p class="text-xs font-bold text-white truncate"><?php echo htmlspecialchars(isset($_SESSION['usuario_nombre']) ? $_SESSION['usuario_nombre'] : 'Especialista'); ?></p>
          <p class="text-[10px] text-[#00c982] font-semibold truncate"><?php echo htmlspecialchars(isset($_SESSION['usuario_rol']) ? $_SESSION['usuario_rol'] : 'CAS'); ?></p>
        </div>
        <a href="logout.php" class="p-2 text-slate-400 hover:text-rose-400 hover:bg-white/5 rounded-xl transition-all"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg></a>
      </div>
    </div>
  </aside>

  <!-- CONTENIDO PRINCIPAL -->
  <main class="flex-1 p-6 lg:p-8 overflow-y-auto">
    
    <!-- Encabezado y Controles -->
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
      <div>
        <span class="inline-block px-2.5 py-0.5 bg-[#e6f7ef] text-[#008f5d] text-[10px] font-extrabold uppercase tracking-wider rounded-md mb-1">
          Fase de Compromiso y Devengado · SIAF-SP
        </span>
        <h2 class="text-2xl font-extrabold text-[#111827] tracking-tight">Resumen Consolidado por Meta y Establecimiento</h2>
        <p class="text-xs text-slate-500 mt-0.5 font-medium">
          Periodo Evaluado: <b class="text-slate-800"><?php echo $periodoEvaluado ? htmlspecialchars($periodoEvaluado['nombre_mes'] . ' ' . $periodoEvaluado['anio'] . ' (' . $periodoEvaluado['codigo'] . ')') : 'Sin periodo'; ?></b>
        </p>
      </div>

      <div class="no-print flex flex-wrap items-center gap-2.5">
        <!-- Selector de Periodo -->
        <form method="GET" class="flex items-center gap-2 bg-white px-3 py-1.5 rounded-xl border border-slate-200 shadow-sm text-xs font-semibold">
          <span class="text-slate-400">Periodo:</span>
          <select name="id_periodo" onchange="this.form.submit()" class="bg-transparent font-bold text-slate-800 focus:outline-none cursor-pointer">
            <?php foreach ($periodosLista as $per): ?>
              <option value="<?php echo $per['id']; ?>" <?php echo ($per['id'] == $id_periodo) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($per['codigo'] . ' - ' . $per['nombre_mes'] . ' (' . strtoupper($per['estado']) . ')'); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </form>

        <!-- Exportar Cuadro SIAF a Excel -->
        <?php if ($id_periodo > 0 && !empty($filasSiaf)): ?>
          <a href="exportar_siaf_excel.php?id_periodo=<?php echo $id_periodo; ?>" class="px-4 py-2 bg-[#008f5d] hover:bg-[#007a4f] text-white rounded-xl font-bold text-xs shadow-sm transition-all flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            Exportar Cuadro SIAF (.xls)
          </a>
        <?php endif; ?>

        <button onclick="window.print()" class="px-4 py-2 bg-[#0c1526] hover:bg-slate-800 text-white rounded-xl font-bold text-xs shadow-sm transition-all flex items-center gap-2">
          <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
          Imprimir (Horizontal)
        </button>
      </div>
    </div>

    <!-- TARJETAS RESUMEN DE COMPROMISO PRESUPUESTAL -->
    <div class="grid grid-cols-2 lg:grid-cols-6 gap-3 mb-6">
      <div class="bg-white p-4 rounded-2xl border border-slate-200/80 print-shadow-none">
        <span class="text-[10px] font-extrabold text-slate-400 uppercase block">1. Compromiso Bruto</span>
        <p class="text-base font-extrabold text-slate-800 font-mono mt-1">S/ <?php echo number_format($granTotal['bruto'], 2); ?></p>
        <span class="text-[10px] text-slate-400">Específica 2.3.2.8.1.1</span>
      </div>
      <div class="bg-white p-4 rounded-2xl border border-slate-200/80 print-shadow-none">
        <span class="text-[10px] font-extrabold text-indigo-600 uppercase block">2. Retención ONP</span>
        <p class="text-base font-extrabold text-indigo-700 font-mono mt-1">S/ <?php echo number_format($granTotal['onp'], 2); ?></p>
        <span class="text-[10px] text-slate-400">PDT PLAME - SUNAT</span>
      </div>
      <div class="bg-white p-4 rounded-2xl border border-slate-200/80 print-shadow-none">
        <span class="text-[10px] font-extrabold text-purple-600 uppercase block">3. Retención AFP</span>
        <p class="text-base font-extrabold text-purple-700 font-mono mt-1">S/ <?php echo number_format($granTotal['afp'], 2); ?></p>
        <span class="text-[10px] text-slate-400">Planilla AFPnet</span>
      </div>
      <div class="bg-white p-4 rounded-2xl border border-slate-200/80 print-shadow-none">
        <span class="text-[10px] font-extrabold text-rose-600 uppercase block">4. Judic. + Terceros</span>
        <p class="text-base font-extrabold text-rose-700 font-mono mt-1">S/ <?php echo number_format($granTotal['judicial'] + $granTotal['terceros'], 2); ?></p>
        <span class="text-[10px] text-slate-400">Jud: <?php echo number_format($granTotal['judicial'], 2); ?> | Terc: <?php echo number_format($granTotal['terceros'], 2); ?></span>
      </div>
      <div class="bg-white p-4 rounded-2xl border border-slate-200/80 print-shadow-none">
        <span class="text-[10px] font-extrabold text-emerald-600 uppercase block">5. Líquido (Abono BN)</span>
        <p class="text-base font-extrabold text-emerald-700 font-mono mt-1">S/ <?php echo number_format($granTotal['liquido'], 2); ?></p>
        <span class="text-[10px] text-slate-400">Giro MCPP Web</span>
      </div>
      <div class="bg-white p-4 rounded-2xl border border-slate-200/80 print-shadow-none">
        <span class="text-[10px] font-extrabold text-blue-600 uppercase block">6. Aporte EsSalud (9%)</span>
        <p class="text-base font-extrabold text-blue-700 font-mono mt-1">S/ <?php echo number_format($granTotal['essalud'], 2); ?></p>
        <span class="text-[10px] text-slate-400">Específica 2.3.2.8.1.2</span>
      </div>
    </div>

    <!-- TABLA MATRIZ SIAF: POR META Y ESTABLECIMIENTO CON DESCUENTOS SEPARADOS -->
    <div class="bg-white rounded-3xl border border-slate-200/80 shadow-[0_2px_12px_rgba(0,0,0,0.03)] overflow-hidden mb-8 print-shadow-none">
      <div class="p-5 border-b border-slate-100 flex items-center justify-between">
        <div>
          <h3 class="text-sm font-extrabold text-slate-800 uppercase tracking-wide flex items-center gap-2">
            <span class="w-2.5 h-2.5 rounded-full bg-[#008f5d]"></span>
            Cuadro Maestro de Compromiso Presupuestal por Meta y Establecimiento
          </h3>
          <p class="text-[11px] text-slate-400 mt-0.5">Incluye subtotales por cada Meta Presupuestal y desglose de retenciones (ONP, AFP, Judicial, Terceros, 4ta Categoría y Faltas).</p>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-[11px]">
          <thead>
            <tr class="bg-[#0c1526] text-white font-bold uppercase text-[10px] tracking-wider">
              <th class="py-3 px-3 border-r border-slate-700">META</th>
              <th class="py-3 px-3 border-r border-slate-700">ESTABLECIMIENTO / SEDE</th>
              <th class="py-3 px-2 text-center border-r border-slate-700">CANT.</th>
              <th class="py-3 px-3 text-right bg-slate-800 border-r border-slate-700">MONTO BRUTO<br><span class="text-[9px] text-slate-300 font-normal">(2.3.2.8.1.1)</span></th>
              <th class="py-3 px-2.5 text-right bg-rose-950/80 border-r border-slate-700">INAS./TARD.<br>LIC. S/G</th>
              <th class="py-3 px-2.5 text-right bg-indigo-950/80 border-r border-slate-700">ONP<br>(13%)</th>
              <th class="py-3 px-2.5 text-right bg-purple-950/80 border-r border-slate-700">AFP<br>(TOTAL)</th>
              <th class="py-3 px-2.5 text-right bg-amber-950/80 border-r border-slate-700">RENTA<br>4TA CAT.</th>
              <th class="py-3 px-2.5 text-right bg-rose-900/80 border-r border-slate-700">DESC.<br>JUDICIAL</th>
              <th class="py-3 px-2.5 text-right bg-orange-950/80 border-r border-slate-700">DESC.<br>TERCEROS</th>
              <th class="py-3 px-3 text-right bg-rose-800 border-r border-slate-700">TOTAL<br>DESC.</th>
              <th class="py-3 px-3 text-right bg-[#007a4f] border-r border-slate-700">MONTO<br>LÍQUIDO</th>
              <th class="py-3 px-3 text-right bg-blue-900 border-r border-slate-700">ESSALUD (9%)<br><span class="text-[9px] text-blue-200 font-normal">(2.3.2.8.1.2)</span></th>
              <th class="py-3 px-3 text-right bg-slate-900">TOTAL GASTO<br>(BRUTO+ESSALUD)</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 font-medium">
            <?php if (empty($metasAgrupadas)): ?>
              <tr>
                <td colspan="14" class="text-center py-10 text-slate-400">
                  No hay datos procesados para este periodo. Vaya al módulo <b>Planillas</b> y pulse <b>Procesar / Recalcular Planilla</b>.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($metasAgrupadas as $codMeta => $datosMeta): 
                $sub = $datosMeta['subtotal'];
                $gastoMeta = $sub['bruto'] + $sub['essalud'];
              ?>
                <!-- Filas por cada Establecimiento dentro de la Meta -->
                <?php foreach ($datosMeta['establecimientos'] as $est): 
                  $gastoFila = floatval($est['total_bruto']) + floatval($est['total_essalud']);
                ?>
                <tr class="hover:bg-slate-50 transition-colors">
                  <td class="py-2.5 px-3 font-mono font-bold text-slate-800 border-r border-slate-100">Meta <?php echo htmlspecialchars($est['meta']); ?></td>
                  <td class="py-2.5 px-3 font-semibold text-slate-700 border-r border-slate-100"><?php echo htmlspecialchars($est['establecimiento']); ?></td>
                  <td class="py-2.5 px-2 text-center font-mono border-r border-slate-100"><?php echo intval($est['cant_servidores']); ?></td>
                  <td class="py-2.5 px-3 text-right font-mono font-bold text-slate-800 bg-slate-50/50 border-r border-slate-100"><?php echo number_format($est['total_bruto'], 2); ?></td>
                  <td class="py-2.5 px-2.5 text-right font-mono text-slate-600 border-r border-slate-100"><?php echo number_format($est['desc_faltas_lic'], 2); ?></td>
                  <td class="py-2.5 px-2.5 text-right font-mono text-indigo-700 font-semibold border-r border-slate-100"><?php echo number_format($est['desc_onp'], 2); ?></td>
                  <td class="py-2.5 px-2.5 text-right font-mono text-purple-700 font-semibold border-r border-slate-100"><?php echo number_format($est['desc_afp'], 2); ?></td>
                  <td class="py-2.5 px-2.5 text-right font-mono text-amber-700 border-r border-slate-100"><?php echo number_format($est['desc_cuarta'], 2); ?></td>
                  <td class="py-2.5 px-2.5 text-right font-mono text-rose-700 font-semibold border-r border-slate-100"><?php echo number_format($est['desc_judicial'], 2); ?></td>
                  <td class="py-2.5 px-2.5 text-right font-mono text-orange-700 font-semibold border-r border-slate-100"><?php echo number_format($est['desc_terceros'], 2); ?></td>
                  <td class="py-2.5 px-3 text-right font-mono font-bold text-rose-600 bg-rose-50/30 border-r border-slate-100"><?php echo number_format($est['total_descuentos'], 2); ?></td>
                  <td class="py-2.5 px-3 text-right font-mono font-extrabold text-emerald-700 bg-emerald-50/30 border-r border-slate-100"><?php echo number_format($est['total_liquido'], 2); ?></td>
                  <td class="py-2.5 px-3 text-right font-mono font-bold text-blue-700 bg-blue-50/30 border-r border-slate-100"><?php echo number_format($est['total_essalud'], 2); ?></td>
                  <td class="py-2.5 px-3 text-right font-mono font-bold text-slate-900 bg-slate-50"><?php echo number_format($gastoFila, 2); ?></td>
                </tr>
                <?php endforeach; ?>

                <!-- Fila de SUBTOTAL POR META (Para Compromiso SIAF directo) -->
                <tr class="bg-[#e6f7ef]/70 border-y-2 border-[#00a86b]/40 font-extrabold text-slate-900">
                  <td colspan="2" class="py-2.5 px-3 text-right uppercase tracking-wider text-[#007a4f] border-r border-emerald-200">
                    ► SUBTOTAL COMPROMISO META <?php echo htmlspecialchars($codMeta); ?>:
                  </td>
                  <td class="py-2.5 px-2 text-center font-mono border-r border-emerald-200"><?php echo $sub['cant']; ?></td>
                  <td class="py-2.5 px-3 text-right font-mono border-r border-emerald-200">S/ <?php echo number_format($sub['bruto'], 2); ?></td>
                  <td class="py-2.5 px-2.5 text-right font-mono border-r border-emerald-200"><?php echo number_format($sub['faltas'], 2); ?></td>
                  <td class="py-2.5 px-2.5 text-right font-mono text-indigo-800 border-r border-emerald-200"><?php echo number_format($sub['onp'], 2); ?></td>
                  <td class="py-2.5 px-2.5 text-right font-mono text-purple-800 border-r border-emerald-200"><?php echo number_format($sub['afp'], 2); ?></td>
                  <td class="py-2.5 px-2.5 text-right font-mono border-r border-emerald-200"><?php echo number_format($sub['cuarta'], 2); ?></td>
                  <td class="py-2.5 px-2.5 text-right font-mono text-rose-800 border-r border-emerald-200"><?php echo number_format($sub['judicial'], 2); ?></td>
                  <td class="py-2.5 px-2.5 text-right font-mono text-orange-800 border-r border-emerald-200"><?php echo number_format($sub['terceros'], 2); ?></td>
                  <td class="py-2.5 px-3 text-right font-mono text-rose-700 border-r border-emerald-200">S/ <?php echo number_format($sub['descuentos'], 2); ?></td>
                  <td class="py-2.5 px-3 text-right font-mono text-[#007a4f] border-r border-emerald-200">S/ <?php echo number_format($sub['liquido'], 2); ?></td>
                  <td class="py-2.5 px-3 text-right font-mono text-blue-800 border-r border-emerald-200">S/ <?php echo number_format($sub['essalud'], 2); ?></td>
                  <td class="py-2.5 px-3 text-right font-mono">S/ <?php echo number_format($gastoMeta, 2); ?></td>
                </tr>
              <?php endforeach; ?>

              <!-- GRAN TOTAL GENERAL DE PLANILLA -->
              <?php $granGastoTotal = $granTotal['bruto'] + $granTotal['essalud']; ?>
              <tr class="bg-[#0c1526] text-white font-extrabold text-xs">
                <td colspan="2" class="py-3.5 px-3 text-right uppercase tracking-wider border-r border-slate-700">
                  TOTAL GENERAL CONSOLIDADO SIAF (S/):
                </td>
                <td class="py-3.5 px-2 text-center font-mono border-r border-slate-700"><?php echo $granTotal['cant']; ?></td>
                <td class="py-3.5 px-3 text-right font-mono text-amber-300 border-r border-slate-700"><?php echo number_format($granTotal['bruto'], 2); ?></td>
                <td class="py-3.5 px-2.5 text-right font-mono border-r border-slate-700"><?php echo number_format($granTotal['faltas'], 2); ?></td>
                <td class="py-3.5 px-2.5 text-right font-mono text-indigo-300 border-r border-slate-700"><?php echo number_format($granTotal['onp'], 2); ?></td>
                <td class="py-3.5 px-2.5 text-right font-mono text-purple-300 border-r border-slate-700"><?php echo number_format($granTotal['afp'], 2); ?></td>
                <td class="py-3.5 px-2.5 text-right font-mono border-r border-slate-700"><?php echo number_format($granTotal['cuarta'], 2); ?></td>
                <td class="py-3.5 px-2.5 text-right font-mono text-rose-300 border-r border-slate-700"><?php echo number_format($granTotal['judicial'], 2); ?></td>
                <td class="py-3.5 px-2.5 text-right font-mono text-orange-300 border-r border-slate-700"><?php echo number_format($granTotal['terceros'], 2); ?></td>
                <td class="py-3.5 px-3 text-right font-mono text-rose-400 border-r border-slate-700"><?php echo number_format($granTotal['descuentos'], 2); ?></td>
                <td class="py-3.5 px-3 text-right font-mono text-[#00c982] border-r border-slate-700"><?php echo number_format($granTotal['liquido'], 2); ?></td>
                <td class="py-3.5 px-3 text-right font-mono text-blue-300 border-r border-slate-700"><?php echo number_format($granTotal['essalud'], 2); ?></td>
                <td class="py-3.5 px-3 text-right font-mono text-white"><?php echo number_format($granGastoTotal, 2); ?></td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ANEXOS COMPLEMENTARIOS PARA COMPROMISO: AFP/ONP POR META Y JUDICIALES/TERCEROS -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      
      <!-- Anexo 1: Desglose de AFP (Integra, Prima, Profuturo, Hábitat) y ONP por Meta -->
      <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-[0_2px_12px_rgba(0,0,0,0.03)] print-shadow-none">
        <h3 class="text-xs font-extrabold text-slate-800 uppercase tracking-wider mb-3 flex items-center gap-2">
          <span class="w-2.5 h-2.5 rounded-full bg-purple-600"></span>
          Anexo 1: Desglose de AFP y ONP por Meta Presupuestal
        </h3>
        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs border-collapse">
            <thead>
              <tr class="border-b border-slate-200 text-slate-400 font-bold uppercase text-[10px]">
                <th class="py-2.5 px-3">META</th>
                <th class="py-2.5 px-3">ADMINISTRADORA / RÉGIMEN</th>
                <th class="py-2.5 px-3 text-center">AFILIADOS</th>
                <th class="py-2.5 px-3 text-right">MONTO RETENIDO</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 font-medium">
              <?php if (empty($desglosePensionesMeta)): ?>
                <tr><td colspan="4" class="text-center py-6 text-slate-400">Sin retenciones previsionales en este periodo.</td></tr>
              <?php else: ?>
                <?php foreach ($desglosePensionesMeta as $dpm): ?>
                <tr class="hover:bg-slate-50">
                  <td class="py-2.5 px-3 font-mono font-bold text-slate-800">Meta <?php echo htmlspecialchars($dpm['meta']); ?></td>
                  <td class="py-2.5 px-3 font-semibold text-slate-700"><?php echo htmlspecialchars($dpm['regimen_pensionario']); ?></td>
                  <td class="py-2.5 px-3 text-center font-mono"><?php echo intval($dpm['afiliados']); ?></td>
                  <td class="py-2.5 px-3 text-right font-mono font-bold text-slate-800">S/ <?php echo number_format($dpm['monto_pension'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Anexo 2: Detalle Nominal de Retenciones Judiciales y Descuentos de Terceros por Meta -->
      <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-[0_2px_12px_rgba(0,0,0,0.03)] print-shadow-none">
        <h3 class="text-xs font-extrabold text-slate-800 uppercase tracking-wider mb-3 flex items-center gap-2">
          <span class="w-2.5 h-2.5 rounded-full bg-rose-600"></span>
          Anexo 2: Detalle de Descuentos Judiciales y Terceros por Meta
        </h3>
        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs border-collapse">
            <thead>
              <tr class="border-b border-slate-200 text-slate-400 font-bold uppercase text-[10px]">
                <th class="py-2.5 px-3">META</th>
                <th class="py-2.5 px-3">SERVIDOR / BENEFICIARIO O ENTIDAD</th>
                <th class="py-2.5 px-3 text-right">JUDICIAL</th>
                <th class="py-2.5 px-3 text-right">TERCEROS</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 font-medium">
              <?php if (empty($detalleJudTerc)): ?>
                <tr><td colspan="4" class="text-center py-6 text-slate-400">No hay descuentos judiciales ni de terceros en este periodo.</td></tr>
              <?php else: ?>
                <?php foreach ($detalleJudTerc as $jt): ?>
                <tr class="hover:bg-slate-50">
                  <td class="py-2.5 px-3 font-mono font-bold text-slate-800">Meta <?php echo htmlspecialchars($jt['meta']); ?></td>
                  <td class="py-2.5 px-3">
                    <div class="font-bold text-slate-800"><?php echo htmlspecialchars($jt['nombre_completo']); ?></div>
                    <?php if ($jt['desc_judicial'] > 0): ?>
 		 <div class="text-[10px] text-rose-600 font-semibold">
    		Beneficiaria: <?php echo htmlspecialchars($jt['beneficiario_judicial'] ?: 'No especificada'); ?> 
    		| Cta. BN: <span class="font-mono font-bold"><?php echo htmlspecialchars(!empty($jt['cuenta_judicial']) ? $jt['cuenta_judicial'] : 'Sin cuenta'); ?></span>
   		 | Exp: <?php echo htmlspecialchars($jt['num_expediente'] ?: '-'); ?>
 		 </div>
		<?php endif; ?>
                    <?php if ($jt['desc_terceros'] > 0): ?>
                      <div class="text-[10px] text-orange-600">Terc: <?php echo htmlspecialchars($jt['detalle_terceros'] ?: 'Convenio'); ?></div>
                    <?php endif; ?>
                  </td>
                  <td class="py-2.5 px-3 text-right font-mono font-bold text-rose-700">
                    <?php echo ($jt['desc_judicial'] > 0) ? 'S/ ' . number_format($jt['desc_judicial'], 2) : '-'; ?>
                  </td>
                  <td class="py-2.5 px-3 text-right font-mono font-bold text-orange-700">
                    <?php echo ($jt['desc_terceros'] > 0) ? 'S/ ' . number_format($jt['desc_terceros'], 2) : '-'; ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </main>
</body>
</html>