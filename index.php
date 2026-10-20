<?php
session_start();

// Validar sesión activa
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'conexion.php';

// Cargar regímenes y comisiones pensionarias
$regimenes = $pdo->query("SELECT * FROM comisiones_pensionarias ORDER BY id ASC")->fetchAll();

// Cargar listado de trabajadores CAS
$sql = "SELECT t.*, c.nombre_completo AS nombre_regimen, c.tipo_regimen
        FROM trabajadores_cas t
        LEFT JOIN comisiones_pensionarias c ON t.id_regimen = c.id
        ORDER BY t.apellido_paterno ASC, t.apellido_materno ASC";
$personal = $pdo->query($sql)->fetchAll();
$totalServidores = count($personal);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Personal CAS - UGEL Ventanilla</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'Plus Jakarta Sans', sans-serif; }
    .font-mono { font-family: 'JetBrains Mono', monospace; }
    .tab-btn.active {
      border-bottom: 2.5px solid #00a86b;
      color: #008f5d;
      font-weight: 700;
    }
    .page-btn.active {
      background-color: #008f5d;
      color: #ffffff;
      border-color: #008f5d;
    }
  </style>
</head>
<body class="bg-[#f0f4f8] text-slate-700 min-h-screen flex antialiased">

  <!-- SIDEBAR IZQUIERDO FIJO -->
  <aside class="w-64 bg-[#0c1526] text-slate-300 flex flex-col flex-shrink-0 h-screen sticky top-0">
    <!-- Logotipo Institucional -->
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
    <nav class="flex-1 px-4 py-6 space-y-1.5 text-sm font-semibold overflow-y-auto">
	<a href="dashboard.php" class="flex items-center gap-3.5 px-3.5 py-3 rounded-xl text-slate-400 hover:text-slate-100 hover:bg-white/5 transition-all">
 	 <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 	1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
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

      <a href="index.php" class="flex items-center gap-3.5 px-3.5 py-3 rounded-xl bg-gradient-to-r from-[#17243c] to-[#1c2c49] text-white font-bold shadow-md border border-slate-700/40">
        <svg class="w-5 h-5 text-[#00c982]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
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

    <!-- Usuario Activo y Salir -->
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
    <div class="flex items-center justify-between mb-5">
      <div>
        <h2 class="text-2xl font-extrabold text-[#111827] tracking-tight">Personal CAS</h2>
        <p class="text-xs text-slate-500 mt-0.5 font-medium">Registro y control de trabajadores bajo D.L. 1057</p>
      </div>

      <!-- Contenedor para ambos botones alineados -->
      <div class="flex items-center gap-2">
        <!-- Botón Carga Masiva -->
        <a href="importar_personal.php" class="flex items-center gap-2 bg-slate-800 hover:bg-slate-900 text-white px-4 py-2.5 rounded-xl font-bold text-xs transition-all shadow-sm">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
          Carga Masiva
        </a>

        <!-- Botón Nuevo Servidor -->
        <button onclick="abrirModalNuevo()" class="flex items-center gap-2 bg-[#008f5d] hover:bg-[#007a4f] text-white px-4 py-2.5 rounded-xl font-bold text-xs transition-all shadow-[0_4px_14px_rgba(0,143,93,0.35)] active:scale-95">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" /></svg>
          Nuevo Servidor
        </button>
      </div>
    </div>
<!-- PANEL INTELIGENTE DE ALERTAS DE CONTRATOS Y CUMPLEAÑOS -->
    <?php if (!empty($contratosVencidos) || !empty($contratosPorVencer) || !empty($cumpleanosMes)): ?>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-5">
      
      <!-- 1. ALERTA ROJA: CONTRATOS VENCIDOS (RIESGO DE PAGO INDEBIDO) -->
      <div class="bg-white rounded-2xl p-4 border-l-4 border-rose-500 shadow-[0_2px_12px_rgba(0,0,0,0.03)] flex flex-col justify-between">
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
            <div class="space-y-2 max-h-36 overflow-y-auto pr-1">
              <?php foreach ($contratosVencidos as $cv): ?>
                <div class="p-2 bg-rose-50/70 border border-rose-200/70 rounded-xl flex items-center justify-between text-[11px]">
                  <div class="truncate pr-2">
                    <div class="font-bold text-slate-800 truncate"><?php echo htmlspecialchars($cv['apellido_paterno'] . ' ' . $cv['nombres']); ?></div>
                    <div class="text-[10px] text-rose-600 font-semibold">Venció: <?php echo $cv['fecha_termino']; ?> (hace <?php echo $cv['dias_vencido']; ?> días)</div>
                  </div>
                  <div class="flex items-center gap-1 flex-shrink-0">
                    <a href="historial_contratos.php?id=<?php echo $cv['id']; ?>" title="Renovar / Adenda" class="px-2 py-1 bg-white hover:bg-slate-100 text-indigo-600 border border-rose-200 rounded-lg font-bold text-[10px]">Adenda</a>
                    <button type="button" onclick='abrirModalEditar(<?php echo htmlspecialchars(json_encode($cv), ENT_QUOTES, "UTF-8"); ?>)' class="px-2 py-1 bg-rose-600 hover:bg-rose-700 text-white rounded-lg font-bold text-[10px]">Cesar</button>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
        <p class="text-[10px] text-slate-400 mt-2 pt-1.5 border-t border-slate-100">Cese al servidor o registre su adenda antes de procesar planilla.</p>
      </div>

      <!-- 2. ALERTA ÁMBAR: CONTRATOS POR VENCER EN EL MES -->
      <div class="bg-white rounded-2xl p-4 border-l-4 border-amber-500 shadow-[0_2px_12px_rgba(0,0,0,0.03)] flex flex-col justify-between">
        <div>
          <div class="flex items-center justify-between mb-2">
            <span class="text-[11px] font-extrabold text-amber-600 uppercase tracking-wider flex items-center gap-1.5">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              Por Vencer (Próximos 15 días / Mes)
            </span>
            <span class="px-2 py-0.5 bg-amber-100 text-amber-800 rounded-full text-[10px] font-extrabold"><?php echo count($contratosPorVencer); ?></span>
          </div>

          <?php if (empty($contratosPorVencer)): ?>
            <p class="text-xs text-slate-400 py-3">✓ No hay contratos próximos a vencer en este periodo.</p>
          <?php else: ?>
            <div class="space-y-2 max-h-36 overflow-y-auto pr-1">
              <?php foreach ($contratosPorVencer as $cpv): ?>
                <div class="p-2 bg-amber-50/70 border border-amber-200/70 rounded-xl flex items-center justify-between text-[11px]">
                  <div class="truncate pr-2">
                    <div class="font-bold text-slate-800 truncate"><?php echo htmlspecialchars($cpv['apellido_paterno'] . ' ' . $cpv['nombres']); ?></div>
                    <div class="text-[10px] text-amber-700 font-semibold">
                      Termina: <b><?php echo $cpv['fecha_termino']; ?></b> 
                      (<?php echo ($cpv['dias_restantes'] === 0) ? '¡Vence HOY!' : 'en ' . $cpv['dias_restantes'] . ' días'; ?>)
                    </div>
                  </div>
                  <button type="button" onclick='abrirModalEditar(<?php echo htmlspecialchars(json_encode($cpv), ENT_QUOTES, "UTF-8"); ?>)' 
                          class="px-2 py-1 bg-amber-500 hover:bg-amber-600 text-white rounded-lg font-bold text-[10px] flex-shrink-0">
                    Ver Días (<?php echo $cpv['dias_laborados']; ?>d)
                  </button>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
        <p class="text-[10px] text-slate-400 mt-2 pt-1.5 border-t border-slate-100">Si no renueva y vence antes del 30, ajuste sus días laborados.</p>
      </div>

      <!-- 3. TARJETA DE CUMPLEAÑOS / ONOMÁSTICOS DEL MES -->
      <div class="bg-white rounded-2xl p-4 border-l-4 border-[#008f5d] shadow-[0_2px_12px_rgba(0,0,0,0.03)] flex flex-col justify-between">
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
                    <div class="text-[10px] text-slate-500"><?php echo htmlspecialchars($cum['cargo']); ?></div>
                  </div>
                  <div class="text-right flex-shrink-0">
                    <?php if ($cum['es_hoy']): ?>
                      <span class="px-2 py-0.5 bg-[#008f5d] text-white rounded-md text-[10px] font-extrabold animate-pulse">🎂 ¡HOY! (<?php echo sprintf('%02d/%02d', $cum['dia_cumple'], $mesActual); ?>)</span>
                    <?php else: ?>
                      <span class="px-2 py-0.5 bg-white border border-slate-200 text-slate-700 rounded-md font-mono font-bold text-[10.5px]">
                        Día <?php echo sprintf('%02d/%02d', $cum['dia_cumple'], $mesActual); ?>
                      </span>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
        <p class="text-[10px] text-slate-400 mt-2 pt-1.5 border-t border-slate-100">Recordatorio para descanso por onomástico o saludo institucional.</p>
      </div>

    </div>
    <?php endif; ?>
    <!-- Barra Superior: Buscador + Selector de Filas por Página + Contador -->
    <div class="bg-white rounded-2xl p-2.5 border border-slate-200/80 shadow-[0_2px_12px_rgba(0,0,0,0.03)] flex flex-wrap items-center justify-between gap-3 mb-4">
      <div class="flex items-center gap-2.5 pl-2.5 flex-1 min-w-[240px]">
        <svg class="w-4 h-4 text-slate-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
        <input type="text" id="buscador" oninput="alBuscar()" placeholder="Buscar por apellidos, nombres, DNI o cargo..." 
               class="w-full bg-transparent text-xs font-medium text-slate-700 placeholder-slate-400 focus:outline-none">
      </div>

      <div class="flex items-center gap-3">
        <!-- Selector 10 / 20 / 30 / 50 -->
        <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 bg-slate-50 px-3 py-1.5 rounded-xl border border-slate-200/70">
          <span>Mostrar:</span>
          <select id="selectPorPagina" onchange="cambiarPorPagina()" class="bg-transparent font-bold text-slate-800 focus:outline-none cursor-pointer">
            <option value="10" selected>10</option>
            <option value="20">20</option>
            <option value="30">30</option>
            <option value="50">50</option>
          </select>
          <span>filas</span>
        </div>

        <div class="bg-slate-100 text-slate-600 px-3 py-1.5 rounded-xl text-xs font-bold">
          <span id="contadorRegistros"><?php echo $totalServidores; ?></span> servidores registrados
        </div>
      </div>
    </div>

    <!-- Tabla Optimizada y Compacta -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-[0_2px_12px_rgba(0,0,0,0.03)] overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse" id="tablaPersonal">
          <thead>
            <tr class="border-b border-slate-200 bg-slate-50/60 text-[10.5px] font-extrabold text-slate-400 uppercase tracking-wider">
              <th class="py-3 px-4 w-24">DNI</th>
              <th class="py-3 px-4 w-64">APELLIDOS Y NOMBRES</th>
              <th class="py-3 px-4">CARGO / UNIDAD</th>
              <th class="py-3 px-4 text-right w-32">REMUNERACIÓN</th>
              <th class="py-3 px-4 text-center w-40">AFILIACIÓN</th>
              <th class="py-3 px-3 text-center w-24">ESTADO</th>
              <th class="py-3 px-4 text-center w-28">ACCIONES</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-xs" id="cuerpoTabla">
            <?php if (empty($personal)): ?>
              <tr id="filaVacia">
                <td colspan="7" class="text-center py-10 text-slate-400 font-medium">No hay servidores CAS registrados en el sistema.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($personal as $row): 
                $apellidos = trim($row['apellido_paterno'] . ' ' . $row['apellido_materno']);
                $nombres   = trim($row['nombres']);
                $regimen   = !empty($row['nombre_regimen']) ? $row['nombre_regimen'] : (!empty($row['tipo_regimen']) ? $row['tipo_regimen'] : 'ONP');
                $esActivo  = (strtolower($row['estado']) === 'activo');
              ?>
              <tr class="fila-servidor hover:bg-slate-50/90 transition-colors">
                <!-- DNI -->
                <td class="py-3 px-4 font-mono text-slate-600 font-semibold whitespace-nowrap">
                  <?php echo htmlspecialchars($row['dni']); ?>
                </td>

                <!-- Apellidos y Nombres ordenados -->
                <td class="py-3 px-4">
                  <div class="font-extrabold text-slate-800 uppercase leading-tight">
                    <?php echo htmlspecialchars($apellidos); ?>
                  </div>
                  <div class="text-[11px] font-semibold text-slate-500 uppercase mt-0.5">
                    <?php echo htmlspecialchars($nombres); ?>
                  </div>
                </td>

                <!-- Cargo y Meta -->
                <td class="py-3 px-4 max-w-xs">
                  <div class="font-bold text-slate-700 uppercase leading-snug truncate" title="<?php echo htmlspecialchars($row['cargo']); ?>">
                    <?php echo htmlspecialchars($row['cargo']); ?>
                  </div>
                  <div class="text-[10.5px] text-slate-400 mt-0.5 font-medium truncate">
                    Meta <?php echo htmlspecialchars(!empty($row['meta']) ? $row['meta'] : '0131'); ?> · <?php echo htmlspecialchars(!empty($row['establecimiento']) ? $row['establecimiento'] : 'SEDE UGEL VENTANILLA'); ?>
                  </div>
                </td>

                <!-- Remuneración -->
                <td class="py-3 px-4 text-right font-mono font-bold text-slate-800 whitespace-nowrap">
                  S/ <?php echo number_format($row['remuneracion_mensual'], 2); ?>
                </td>

                <!-- Afiliación -->
                <td class="py-3 px-4 text-center whitespace-nowrap">
                  <span class="inline-block bg-[#f1f5f9] text-slate-700 px-2.5 py-1 rounded-lg text-[10.5px] font-semibold border border-slate-200/60">
                    <?php echo htmlspecialchars($regimen); ?>
                  </span>
                </td>

                <!-- Estado -->
                <td class="py-3 px-3 text-center whitespace-nowrap">
                  <?php if ($esActivo): ?>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-[#e6f7ef] text-[#008f5d] tracking-wide uppercase">
                      ACTIVO
                    </span>
                  <?php else: ?>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-rose-50 text-rose-600 tracking-wide uppercase">
                      <?php echo htmlspecialchars($row['estado']); ?>
                    </span>
                  <?php endif; ?>
                </td>

                <!-- Acciones alienadas en una sola fila sin desbordarse -->
                <td class="py-3 px-4 text-center whitespace-nowrap">
                  <div class="inline-flex items-center justify-center gap-1">
                    <a href="historial_contratos.php?id=<?php echo $row['id']; ?>" 
                       title="Historial de Contratos / Adendas" 
                       class="p-1.5 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                      </svg>
                    </a>

                    <button type="button" 
                            onclick='abrirModalEditar(<?php echo htmlspecialchars(json_encode($row), ENT_QUOTES, "UTF-8"); ?>)'
                            class="p-1.5 text-slate-400 hover:text-emerald-700 hover:bg-emerald-50 rounded-lg transition-colors" 
                            title="Editar Ficha y Descuentos">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                    </button>

                    <a href="acciones_personal.php?accion=eliminar&id=<?php echo $row['id']; ?>" 
                       onclick="return confirm('¿Está seguro de eliminar a este trabajador?');"
                       class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors" 
                       title="Eliminar">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </a>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Pie de Tabla: Controles de Paginación -->
      <div class="px-5 py-3.5 bg-slate-50/70 border-t border-slate-200/80 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs">
        <div class="text-slate-500 font-medium" id="infoPaginacion">
          Mostrando 1 a 10 de <?php echo $totalServidores; ?> servidores
        </div>
        <div class="flex items-center gap-1.5" id="controlesPaginacion">
          <!-- Botones generados dinámicamente por JS -->
        </div>
      </div>
    </div>
  </main>

  <!-- MODAL INTEGRADO (PESTAÑAS 1, 2 Y 3) -->
  <div id="modalFormulario" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm hidden flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-3xl max-w-3xl w-full p-7 shadow-2xl border border-slate-100 max-h-[92vh] flex flex-col">
      
      <div class="flex items-center justify-between pb-4 border-b border-slate-100">
        <div>
          <h3 id="modalTitulo" class="text-lg font-bold text-slate-800">Ficha del Servidor CAS</h3>
          <p class="text-xs text-slate-400 font-medium">Régimen Especial de Contratación Administrativa de Servicios - D.L. 1057</p>
        </div>
        <button onclick="cerrarModal()" class="w-8 h-8 rounded-full flex items-center justify-center text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors text-2xl leading-none">&times;</button>
      </div>

      <!-- Barra de Pestañas -->
      <div class="flex border-b border-slate-200 mt-4 text-xs font-semibold">
        <button type="button" id="tabBtnPersonales" onclick="cambiarTab('personales')" class="tab-btn active px-4 py-3 text-slate-600 transition-all flex items-center gap-2">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
          1. Datos Personales
        </button>
        <button type="button" id="tabBtnLaborales" onclick="cambiarTab('laborales')" class="tab-btn px-4 py-3 text-slate-500 hover:text-slate-800 transition-all flex items-center gap-2">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
          2. Datos Laborales
        </button>
        <button type="button" id="tabBtnDescuentos" onclick="cambiarTab('descuentos')" class="tab-btn px-4 py-3 text-slate-500 hover:text-slate-800 transition-all flex items-center gap-2">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
          3. Descuentos, Licencias y Reintegros
        </button>
      </div>

      <!-- Formulario -->
      <form action="acciones_personal.php" method="POST" class="flex-1 overflow-y-auto mt-4 pr-1">
        <input type="hidden" name="accion" id="formAccion" value="guardar">
        <input type="hidden" name="id" id="formId" value="">

        <!-- SECCIÓN 1: DATOS PERSONALES -->
        <div id="seccionPersonales" class="grid grid-cols-2 gap-4 text-xs font-semibold">
          <div>
            <label class="block text-slate-500 mb-1.5">DNI *</label>
            <input type="text" name="dni" id="campoDni" maxlength="8" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-[#008f5d]/30 outline-none font-mono">
          </div>
          <div>
            <label class="block text-slate-500 mb-1.5">Sexo *</label>
            <select name="sexo" id="campoSexo" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-[#008f5d]/30 outline-none">
              <option value="M">Masculino</option>
              <option value="F">Femenino</option>
            </select>
          </div>
          <div>
            <label class="block text-slate-500 mb-1.5">Apellido Paterno *</label>
            <input type="text" name="apellido_paterno" id="campoPaterno" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-[#008f5d]/30 outline-none uppercase">
          </div>
          <div>
            <label class="block text-slate-500 mb-1.5">Apellido Materno *</label>
            <input type="text" name="apellido_materno" id="campoMaterno" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-[#008f5d]/30 outline-none uppercase">
          </div>
          <div class="col-span-2">
            <label class="block text-slate-500 mb-1.5">Nombres Completos *</label>
            <input type="text" name="nombres" id="campoNombres" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-[#008f5d]/30 outline-none uppercase">
          </div>
          <div>
            <label class="block text-slate-500 mb-1.5">Fecha de Nacimiento</label>
            <input type="date" name="fecha_nacimiento" id="campoNacimiento" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-[#008f5d]/30 outline-none">
          </div>
          <div class="flex items-end">
            <button type="button" onclick="cambiarTab('laborales')" class="w-full py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl transition-colors font-bold text-xs">
              Continuar a Datos Laborales →
            </button>
          </div>
        </div>

        <!-- SECCIÓN 2: DATOS LABORALES -->
        <div id="seccionLaborales" class="grid grid-cols-2 gap-4 text-xs font-semibold hidden">
          <div>
            <label class="block text-slate-500 mb-1.5">Número de Contrato</label>
            <input type="text" name="num_contrato" id="campoContrato" placeholder="Ej. 045-2026-UGEL-V" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-[#008f5d]/30 outline-none">
          </div>
          <div>
            <label class="block text-slate-500 mb-1.5">Establecimiento</label>
            <input type="text" name="establecimiento" id="campoEstablecimiento" value="SEDE UGEL VENTANILLA" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-[#008f5d]/30 outline-none">
          </div>
          <div>
            <label class="block text-slate-500 mb-1.5">Fecha de Inicio</label>
            <input type="date" name="fecha_inicio" id="campoFechaInicio" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-[#008f5d]/30 outline-none">
          </div>
          <div>
            <label class="block text-slate-500 mb-1.5">Fecha de Término</label>
            <input type="date" name="fecha_termino" id="campoFechaTermino" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-[#008f5d]/30 outline-none">
          </div>
          <div>
            <label class="block text-slate-500 mb-1.5">Cargo Funcional *</label>
            <input type="text" name="cargo" id="campoCargo" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-[#008f5d]/30 outline-none uppercase">
          </div>
          <div>
            <label class="block text-slate-500 mb-1.5">Condición Laboral *</label>
            <select name="condicion_laboral" id="campoCondicionLaboral" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-[#008f5d]/30 outline-none">
              <option value="CONTRATO A PLAZO DETERMINADO">Contrato a plazo determinado</option>
              <option value="CONTRATO A PLAZO INDETERMINADO">Contrato a plazo indeterminado</option>
            </select>
          </div>
          <div>
            <label class="block text-slate-500 mb-1.5">Meta Presupuestal</label>
            <input type="text" name="meta" id="campoMeta" value="0131" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-[#008f5d]/30 outline-none">
          </div>
          <div>
            <label class="block text-slate-500 mb-1.5">Código NEXUS</label>
            <input type="text" name="cod_nexus" id="campoNexus" placeholder="Ej. 11223344" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-[#008f5d]/30 outline-none font-mono">
          </div>
          <div>
            <label class="block text-slate-500 mb-1.5">Código AIRHSP</label>
            <input type="text" name="cod_airhsp" id="campoAirhsp" placeholder="Ej. 000564" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-[#008f5d]/30 outline-none font-mono">
          </div>
          <div>
            <label class="block text-slate-500 mb-1.5">Régimen Pensionario y Comisión *</label>
            <select name="id_regimen" id="campoRegimen" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-[#008f5d]/30 outline-none">
              <?php foreach ($regimenes as $reg): ?>
                <option value="<?php echo $reg['id']; ?>">
                  <?php echo htmlspecialchars($reg['nombre_completo']) . ' (' . number_format($reg['total_porcentaje'], 2) . '%)'; ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-slate-500 mb-1.5">Cuenta de Ahorros (BN)</label>
            <input type="text" name="cuenta_ahorros" id="campoCuenta" placeholder="04-000-000000" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-[#008f5d]/30 outline-none font-mono">
          </div>
          <div>
            <label class="block text-slate-500 mb-1.5">Días Laborados</label>
            <input type="number" name="dias_laborados" id="campoDias" value="30" min="1" max="31" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-[#008f5d]/30 outline-none">
          </div>
          <div>
            <label class="block text-slate-500 mb-1.5">Estado *</label>
            <select name="estado" id="campoEstado" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-[#008f5d]/30 outline-none">
              <option value="activo">Activo</option>
              <option value="inactivo">Inactivo</option>
              <option value="cesado">Cesado</option>
            </select>
          </div>
          <div>
            <label class="block text-slate-500 mb-1.5">Remuneración Mensual (S/) *</label>
            <input type="number" step="0.01" name="remuneracion_mensual" id="campoRemuneracion" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-[#008f5d]/30 outline-none font-mono font-bold text-slate-800 text-sm">
          </div>
        </div>

        <!-- SECCIÓN 3: DESCUENTOS, LICENCIAS Y REINTEGROS -->
        <div id="seccionDescuentos" class="space-y-4 text-xs font-semibold hidden">
          
          <!-- Reintegros y Pagos Pendientes del Periodo -->
          <div class="p-4 bg-emerald-50/60 border border-emerald-200 rounded-2xl space-y-2">
            <div class="flex items-center justify-between">
              <span class="font-bold text-emerald-800 uppercase tracking-wider text-[11px] flex items-center gap-1.5">
                <svg class="w-4 h-4 text-[#008f5d]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Reintegro / Pago Pendiente (Ingreso Adicional)
              </span>
              <label class="inline-flex items-center gap-1.5 cursor-pointer text-emerald-800 font-semibold text-[11px]">
                <input type="checkbox" name="reintegro_afecto" id="campoReintegroAfecto" value="1" checked class="rounded text-[#008f5d] focus:ring-[#008f5d]">
                <span>Afecto a ONP / AFP</span>
              </label>
            </div>
            <div class="grid grid-cols-3 gap-3 pt-1">
              <div>
                <label class="block text-slate-600 mb-1">Monto Reintegro (S/)</label>
                <input type="number" step="0.01" min="0" name="monto_reintegro" id="campoMontoReintegro" value="0.00" class="w-full px-3 py-2 bg-white border border-emerald-200 rounded-xl focus:ring-2 focus:ring-[#008f5d]/30 outline-none font-mono font-bold text-emerald-700">
              </div>
              <div class="col-span-2">
                <label class="block text-slate-600 mb-1">Concepto / Glosa (Saldrá en Boleta)</label>
                <input type="text" name="motivo_reintegro" id="campoMotivoReintegro" placeholder="Ej. Pago pendiente mes de septiembre" class="w-full px-3 py-2 bg-white border border-emerald-200 rounded-xl focus:ring-2 focus:ring-[#008f5d]/30 outline-none">
              </div>
            </div>
          </div>

          <!-- Licencias CAS -->
          <div class="p-4 bg-slate-50 border border-slate-200 rounded-2xl space-y-2">
            <div class="flex items-center justify-between">
              <span class="font-bold text-slate-700 uppercase tracking-wider text-[11px] flex items-center gap-1.5">
                <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Licencias del Servidor CAS
              </span>
              <span class="text-[11px] text-slate-400 font-normal">Sin goce descuenta 1/30 por día</span>
            </div>
            <div class="grid grid-cols-3 gap-3 pt-1">
              <div>
                <label class="block text-slate-500 mb-1">Días Licencia Con Goce</label>
                <input type="number" min="0" max="30" name="dias_licencia_con_goce" id="campoLicConGoce" value="0" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-[#008f5d]/30 outline-none">
              </div>
              <div>
                <label class="block text-slate-500 mb-1">Días Licencia Sin Goce</label>
                <input type="number" min="0" max="30" name="dias_licencia_sin_goce" id="campoLicSinGoce" value="0" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-[#008f5d]/30 outline-none">
              </div>
              <div>
                <label class="block text-slate-500 mb-1">Detalle / Resolución</label>
                <input type="text" name="motivo_licencia" id="campoMotivoLicencia" placeholder="Ej. Res. N° 124-2026" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-[#008f5d]/30 outline-none">
              </div>
            </div>
          </div>

          <!-- Inasistencias y Tardanzas -->
          <div class="p-4 bg-slate-50 border border-slate-200 rounded-2xl space-y-2">
            <div class="flex items-center justify-between">
              <span class="font-bold text-slate-700 uppercase tracking-wider text-[11px] flex items-center gap-1.5">
                <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Inasistencias y Tardanzas
              </span>
              <span class="text-[11px] text-slate-400 font-normal">Base 30 días / 8 hrs diarias</span>
            </div>
            <div class="grid grid-cols-3 gap-3 pt-1">
              <div>
                <label class="block text-slate-500 mb-1">Días Inasistencia</label>
                <input type="number" min="0" max="30" name="dias_tardanza" id="campoDiasTardanza" value="0" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-[#008f5d]/30 outline-none">
              </div>
              <div>
                <label class="block text-slate-500 mb-1">Horas Tardanza</label>
                <input type="number" min="0" max="240" name="horas_tardanza" id="campoHorasTardanza" value="0" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-[#008f5d]/30 outline-none">
              </div>
              <div>
                <label class="block text-slate-500 mb-1">Minutos Tardanza</label>
                <input type="number" min="0" max="59" name="minutos_tardanza" id="campoMinutosTardanza" value="0" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-[#008f5d]/30 outline-none">
              </div>
            </div>
          </div>

          <!-- Descuentos de Terceros -->
          <div class="p-4 bg-slate-50 border border-slate-200 rounded-2xl space-y-2">
            <span class="font-bold text-slate-700 uppercase tracking-wider text-[11px]">Descuentos de Terceros (Convenios / Entidades)</span>
            <div class="grid grid-cols-2 gap-3 pt-1">
              <div>
                <label class="block text-slate-500 mb-1">Entidad / Detalle</label>
                <input type="text" name="detalle_terceros" id="campoDetalleTerceros" placeholder="Ej. Cooperativa / Banco de la Nación" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-[#008f5d]/30 outline-none">
              </div>
              <div>
                <label class="block text-slate-500 mb-1">Importe Descuento (S/)</label>
                <input type="number" step="0.01" min="0" name="descuento_terceros" id="campoDescuentoTerceros" value="0.00" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-[#008f5d]/30 outline-none font-mono">
              </div>
            </div>
          </div>

          <!-- Descuentos Judiciales -->
          <div class="p-4 bg-slate-50 border border-slate-200 rounded-2xl space-y-2">
            <span class="font-bold text-slate-700 uppercase tracking-wider text-[11px]">Retenciones Judiciales / Alimentos</span>
            <div class="grid grid-cols-3 gap-3 pt-1">
              <div>
                <label class="block text-slate-500 mb-1">N° Expediente Judicial</label>
                <input type="text" name="num_expediente" id="campoExpediente" placeholder="Ej. 00124-2025-JP-FC" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-[#008f5d]/30 outline-none font-mono">
              </div>
              <div>
                <label class="block text-slate-500 mb-1">Beneficiario(a)</label>
                <input type="text" name="beneficiario_judicial" id="campoBeneficiario" placeholder="Apellidos y Nombres" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-[#008f5d]/30 outline-none uppercase">
              </div>
              <div>
                <label class="block text-slate-500 mb-1">N° Cuenta Beneficiaria (BN)</label>
                <input type="text" name="cuenta_judicial" id="campoCuentaJudicial" placeholder="04-000-000000" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-[#008f5d]/30 outline-none font-mono">
              </div>
              <div>
                <label class="block text-slate-500 mb-1">Base de Cálculo (Sentencia)</label>
                <select name="base_calculo_judicial" id="campoBaseJudicial" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-[#008f5d]/30 outline-none">
                  <option value="NETO_LEY">Menos Descuentos de Ley (AFP/ONP/4ta)</option>
                  <option value="BRUTO">Total Remuneración Bruta</option>
                </select>
              </div>
              <div>
                <label class="block text-slate-500 mb-1">Porcentaje Retención (%)</label>
                <input type="number" step="0.01" min="0" max="60" name="porcentaje_judicial" id="campoPorcentajeJudicial" placeholder="Ej. 30" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-[#008f5d]/30 outline-none font-mono">
              </div>
              <div>
                <label class="block text-slate-500 mb-1">O Monto Fijo Mensual (S/)</label>
                <input type="number" step="0.01" min="0" name="monto_judicial" id="campoMontoJudicial" value="0.00" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-[#008f5d]/30 outline-none font-mono">
              </div>
            </div>
          </div>

          <!-- Renta de Cuarta Categoría -->
          <div class="p-4 bg-slate-50 border border-slate-200 rounded-2xl space-y-2">
            <div class="flex items-center justify-between">
              <span class="font-bold text-slate-700 uppercase tracking-wider text-[11px]">Impuesto a la Renta de 4ta Categoría</span>
              <label class="inline-flex items-center gap-1.5 cursor-pointer text-slate-600 font-semibold">
                <input type="checkbox" name="renta_cuarta_aplica" id="campoRentaCuartaAplica" value="1" class="rounded text-[#008f5d] focus:ring-[#008f5d]">
                <span>Aplica Retención 8%</span>
              </label>
            </div>
            <div class="grid grid-cols-2 gap-3 pt-1">
              <div>
                <label class="inline-flex items-center gap-1.5 cursor-pointer text-slate-600 mb-1 font-semibold">
                  <input type="checkbox" name="suspension_cuarta" id="campoSuspensionCuarta" value="1" class="rounded text-[#008f5d] focus:ring-[#008f5d]">
                  <span>Suspensión (Form. 1609 SUNAT)</span>
                </label>
                <input type="text" name="num_suspension_1609" id="campoNumSuspension" placeholder="N° Constancia SUNAT" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl focus:ring-2 focus:ring-[#008f5d]/30 outline-none font-mono">
              </div>
              <div>
                <label class="block text-slate-500 mb-1">Monto Estimado 8% (S/)</label>
                <input type="number" step="0.01" min="0" name="renta_cuarta_monto" id="campoRentaCuartaMonto" value="0.00" readonly class="w-full px-3 py-2 bg-slate-100 border border-slate-200 rounded-xl outline-none font-mono font-bold text-slate-700">
              </div>
            </div>
          </div>

        </div>

        <!-- Botones de Acción -->
        <div class="flex justify-end gap-2.5 pt-5 border-t border-slate-100 mt-6">
          <button type="button" onclick="cerrarModal()" class="px-5 py-2.5 text-slate-600 hover:bg-slate-100 rounded-xl font-semibold text-xs transition-colors">Cancelar</button>
          <button type="submit" id="btnGuardar" class="px-6 py-2.5 bg-[#008f5d] hover:bg-[#007a4f] text-white rounded-xl shadow-md font-bold text-xs transition-all active:scale-95">Actualizar Cambios</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    const modal = document.getElementById('modalFormulario');

    // Variables de Paginación y Búsqueda
    let paginaActual = 1;
    let filasPorPagina = 10;
    const todasLasFilas = Array.from(document.querySelectorAll('.fila-servidor'));

    function renderizarTabla() {
      const q = document.getElementById('buscador').value.toLowerCase().trim();

      // 1. Filtrar las filas que coinciden con la búsqueda
      const filasFiltradas = todasLasFilas.filter(fila => {
        return fila.innerText.toLowerCase().includes(q);
      });

      const totalFiltrados = filasFiltradas.length;
      document.getElementById('contadorRegistros').innerText = totalFiltrados;

      // 2. Calcular total de páginas
      const totalPaginas = Math.ceil(totalFiltrados / filasPorPagina) || 1;
      if (paginaActual > totalPaginas) {
        paginaActual = totalPaginas;
      }

      const inicio = (paginaActual - 1) * filasPorPagina;
      const fin = inicio + filasPorPagina;

      // 3. Ocultar todas y mostrar solo las del rango de la página activa
      todasLasFilas.forEach(f => f.style.display = 'none');
      filasFiltradas.slice(inicio, fin).forEach(f => f.style.display = '');

      // 4. Actualizar texto informativo
      const mostrandoDesde = totalFiltrados === 0 ? 0 : inicio + 1;
      const mostrandoHasta = Math.min(fin, totalFiltrados);
      document.getElementById('infoPaginacion').innerText = 
        `Mostrando ${mostrandoDesde} a ${mostrandoHasta} de ${totalFiltrados} servidores`;

      // 5. Construir botones de paginación
      const contenedorBtns = document.getElementById('controlesPaginacion');
      contenedorBtns.innerHTML = '';

      // Botón Anterior
      const btnPrev = document.createElement('button');
      btnPrev.innerText = '← Anterior';
      btnPrev.disabled = (paginaActual === 1);
      btnPrev.className = `px-3 py-1.5 rounded-lg border text-xs font-bold transition-all ${paginaActual === 1 ? 'text-slate-300 border-slate-200 cursor-not-allowed' : 'text-slate-600 border-slate-200 bg-white hover:bg-slate-100'}`;
      btnPrev.onclick = () => { if (paginaActual > 1) { paginaActual--; renderizarTabla(); } };
      contenedorBtns.appendChild(btnPrev);

      // Botones numéricos
      for (let i = 1; i <= totalPaginas; i++) {
        const btnNum = document.createElement('button');
        btnNum.innerText = i;
        btnNum.className = `page-btn w-8 h-8 rounded-lg border border-slate-200 text-xs font-bold transition-all ${i === paginaActual ? 'active shadow-sm' : 'bg-white text-slate-600 hover:bg-slate-100'}`;
        btnNum.onclick = () => { paginaActual = i; renderizarTabla(); };
        contenedorBtns.appendChild(btnNum);
      }

      // Botón Siguiente
      const btnNext = document.createElement('button');
      btnNext.innerText = 'Siguiente →';
      btnNext.disabled = (paginaActual === totalPaginas);
      btnNext.className = `px-3 py-1.5 rounded-lg border text-xs font-bold transition-all ${paginaActual === totalPaginas ? 'text-slate-300 border-slate-200 cursor-not-allowed' : 'text-slate-600 border-slate-200 bg-white hover:bg-slate-100'}`;
      btnNext.onclick = () => { if (paginaActual < totalPaginas) { paginaActual++; renderizarTabla(); } };
      contenedorBtns.appendChild(btnNext);
    }

    function alBuscar() {
      paginaActual = 1;
      renderizarTabla();
    }

    function cambiarPorPagina() {
      filasPorPagina = parseInt(document.getElementById('selectPorPagina').value) || 10;
      paginaActual = 1;
      renderizarTabla();
    }

    // Inicializar paginación al cargar la página
    document.addEventListener('DOMContentLoaded', () => {
      if (todasLasFilas.length > 0) {
        renderizarTabla();
      }
    });

    function cambiarTab(tab) {
      const secciones = {
        'personales': document.getElementById('seccionPersonales'),
        'laborales': document.getElementById('seccionLaborales'),
        'descuentos': document.getElementById('seccionDescuentos')
      };

      const botones = {
        'personales': document.getElementById('tabBtnPersonales'),
        'laborales': document.getElementById('tabBtnLaborales'),
        'descuentos': document.getElementById('tabBtnDescuentos')
      };

      for (let key in secciones) {
        if (key === tab) {
          secciones[key].classList.remove('hidden');
          botones[key].classList.add('active');
        } else {
          secciones[key].classList.add('hidden');
          botones[key].classList.remove('active');
        }
      }
    }

    function abrirModalNuevo() {
      document.getElementById('modalTitulo').innerText = 'Nuevo Servidor CAS (D.L. 1057)';
      document.getElementById('formAccion').value = 'guardar';
      document.getElementById('formId').value = '';
      
      document.getElementById('campoDni').value = '';
      document.getElementById('campoSexo').value = 'M';
      document.getElementById('campoPaterno').value = '';
      document.getElementById('campoMaterno').value = '';
      document.getElementById('campoNombres').value = '';
      document.getElementById('campoNacimiento').value = '';

      document.getElementById('campoContrato').value = '';
      document.getElementById('campoEstablecimiento').value = 'SEDE UGEL VENTANILLA';
      document.getElementById('campoFechaInicio').value = '';
      document.getElementById('campoFechaTermino').value = '';
      document.getElementById('campoCargo').value = '';
      document.getElementById('campoCondicionLaboral').value = 'CONTRATO A PLAZO DETERMINADO';
      document.getElementById('campoMeta').value = '0131';
      document.getElementById('campoNexus').value = '';
      document.getElementById('campoAirhsp').value = '';
      document.getElementById('campoRegimen').value = 1;
      document.getElementById('campoCuenta').value = '';
      document.getElementById('campoDias').value = '30';
      document.getElementById('campoEstado').value = 'activo';
      document.getElementById('campoRemuneracion').value = '';

      document.getElementById('campoMontoReintegro').value = '0.00';
      document.getElementById('campoMotivoReintegro').value = '';
      document.getElementById('campoReintegroAfecto').checked = true;
      document.getElementById('campoLicConGoce').value = 0;
      document.getElementById('campoLicSinGoce').value = 0;
      document.getElementById('campoMotivoLicencia').value = '';
      document.getElementById('campoDiasTardanza').value = 0;
      document.getElementById('campoHorasTardanza').value = 0;
      document.getElementById('campoMinutosTardanza').value = 0;
      document.getElementById('campoDetalleTerceros').value = '';
      document.getElementById('campoDescuentoTerceros').value = '0.00';
      document.getElementById('campoExpediente').value = '';
      document.getElementById('campoBeneficiario').value = '';
      document.getElementById('campoPorcentajeJudicial').value = '';
      document.getElementById('campoMontoJudicial').value = '0.00';
      document.getElementById('campoRentaCuartaAplica').checked = false;
      document.getElementById('campoSuspensionCuarta').checked = false;
      document.getElementById('campoNumSuspension').value = '';
      document.getElementById('campoRentaCuartaMonto').value = '0.00';
      document.getElementById('campoCuentaJudicial').value = '';
      document.getElementById('campoBaseJudicial').value = 'NETO_LEY';
      document.getElementById('btnGuardar').innerText = 'Guardar Servidor';
      cambiarTab('personales');
      modal.classList.remove('hidden');
    }

    function abrirModalEditar(data) {
      document.getElementById('modalTitulo').innerText = 'Editar Servidor CAS: ' + (data.apellido_paterno || '') + ' ' + (data.nombres || '');
      document.getElementById('formAccion').value = 'editar';
      document.getElementById('formId').value = data.id;

      document.getElementById('campoDni').value = data.dni || '';
      document.getElementById('campoSexo').value = data.sexo || 'M';
      document.getElementById('campoPaterno').value = data.apellido_paterno || '';
      document.getElementById('campoMaterno').value = data.apellido_materno || '';
      document.getElementById('campoNombres').value = data.nombres || '';
      document.getElementById('campoNacimiento').value = data.fecha_nacimiento || '';

      document.getElementById('campoContrato').value = data.num_contrato || '';
      document.getElementById('campoEstablecimiento').value = data.establecimiento || 'SEDE UGEL VENTANILLA';
      document.getElementById('campoFechaInicio').value = data.fecha_inicio || '';
      document.getElementById('campoFechaTermino').value = data.fecha_termino || '';
      document.getElementById('campoCargo').value = data.cargo || '';
      document.getElementById('campoCondicionLaboral').value = data.condicion_laboral || 'CONTRATO A PLAZO DETERMINADO';
      document.getElementById('campoMeta').value = data.meta || '0131';
      document.getElementById('campoNexus').value = data.cod_nexus || '';
      document.getElementById('campoAirhsp').value = data.cod_airhsp || '';
      document.getElementById('campoRegimen').value = data.id_regimen || 1;
      document.getElementById('campoCuenta').value = data.cuenta_ahorros || '';
      document.getElementById('campoDias').value = data.dias_laborados || 30;
      document.getElementById('campoEstado').value = data.estado || 'activo';
      document.getElementById('campoRemuneracion').value = data.remuneracion_mensual || '';

      document.getElementById('campoMontoReintegro').value = data.monto_reintegro || '0.00';
      document.getElementById('campoMotivoReintegro').value = data.motivo_reintegro || '';
      document.getElementById('campoReintegroAfecto').checked = (data.reintegro_afecto === undefined || parseInt(data.reintegro_afecto) === 1);
      document.getElementById('campoLicConGoce').value = data.dias_lic_con_goce || 0;
      document.getElementById('campoLicSinGoce').value = data.dias_lic_sin_goce || 0;
      document.getElementById('campoMotivoLicencia').value = data.motivo_licencia || '';
      document.getElementById('campoDiasTardanza').value = data.dias_tardanza || 0;
      document.getElementById('campoHorasTardanza').value = data.horas_tardanza || 0;
      document.getElementById('campoMinutosTardanza').value = data.minutos_tardanza || 0;
      document.getElementById('campoDetalleTerceros').value = data.detalle_terceros || '';
      document.getElementById('campoDescuentoTerceros').value = data.descuento_terceros || '0.00';
      document.getElementById('campoExpediente').value = data.num_expediente || '';
      document.getElementById('campoBeneficiario').value = data.beneficiario_judicial || '';
      document.getElementById('campoPorcentajeJudicial').value = data.porcentaje_judicial || '';
      document.getElementById('campoMontoJudicial').value = data.monto_judicial || '0.00';
      
      document.getElementById('campoRentaCuartaAplica').checked = (parseInt(data.renta_cuarta_aplica) === 1);
      document.getElementById('campoSuspensionCuarta').checked = (parseInt(data.suspension_cuarta) === 1);
      document.getElementById('campoNumSuspension').value = data.num_suspension_1609 || '';
      document.getElementById('campoRentaCuartaMonto').value = data.renta_cuarta_monto || '0.00';
      document.getElementById('campoCuentaJudicial').value = data.cuenta_judicial || '';
      document.getElementById('campoBaseJudicial').value = data.base_calculo_judicial || 'NETO_LEY';
      document.getElementById('btnGuardar').innerText = 'Actualizar Cambios';
      cambiarTab('personales');
      modal.classList.remove('hidden');
    }

    function cerrarModal() {
      modal.classList.add('hidden');
    }

    document.getElementById('campoRemuneracion').addEventListener('input', calcularRentaCuarta);
    document.getElementById('campoRentaCuartaAplica').addEventListener('change', calcularRentaCuarta);
    document.getElementById('campoSuspensionCuarta').addEventListener('change', calcularRentaCuarta);

   function calcularRentaCuarta() {
      const rem = parseFloat(document.getElementById('campoRemuneracion').value) || 0;
      const aplica = document.getElementById('campoRentaCuartaAplica').checked;
      const tieneSuspension = document.getElementById('campoSuspensionCuarta').checked;
      
      if (aplica && !tieneSuspension) {
        document.getElementById('campoRentaCuartaMonto').value = (rem * 0.08).toFixed(2);
      } else {
        document.getElementById('campoRentaCuartaMonto').value = '0.00';
      }
    }

    document.addEventListener('DOMContentLoaded', () => {
      const params = new URLSearchParams(window.location.search);
      if (params.has('buscar')) {
        const valorBusqueda = params.get('buscar');
        const inputBuscador = document.getElementById('buscador');
        if (inputBuscador) {
          inputBuscador.value = valorBusqueda;
          alBuscar(); 
        }
      }
    });
  </script>
</body>
</html>