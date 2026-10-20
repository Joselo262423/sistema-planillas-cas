<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'conexion.php';

// Listado de periodos registrados ordenados por año y mes descendente
$periodos = $pdo->query("SELECT * FROM periodos ORDER BY anio DESC, mes DESC")->fetchAll();

// Verificar si hay algún periodo actualmente abierto
$stmtAbierto = $pdo->query("SELECT * FROM periodos WHERE estado = 'abierto' LIMIT 1");
$periodoActivo = $stmtAbierto->fetch();

$totalPeriodos = count($periodos);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gestión de Periodos - UGEL Ventanilla</title>
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
    <nav class="flex-1 px-4 py-6 space-y-1.5 text-sm font-semibold">
	<a href="dashboard.php" class="flex items-center gap-3.5 px-3.5 py-3 rounded-xl text-slate-400 hover:text-slate-100 hover:bg-white/5 transition-all">
  	<svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 	1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
 	 <span>Dashboard</span>
	</a>
      <a href="planillas.php" class="flex items-center gap-3.5 px-3.5 py-3 rounded-xl text-slate-400 hover:text-slate-100 hover:bg-white/5 transition-all">
  <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" /></svg>
  <span>Planillas</span>
</a>

      <!-- Ítem Periodos Activo -->
      <a href="periodos.php" class="flex items-center gap-3.5 px-3.5 py-3 rounded-xl bg-gradient-to-r from-[#17243c] to-[#1c2c49] text-white font-bold shadow-md border border-slate-700/40">
        <svg class="w-5 h-5 text-[#00c982]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
        <span>Periodos</span>
      </a>

      <a href="index.php" class="flex items-center gap-3.5 px-3.5 py-3 rounded-xl text-slate-400 hover:text-slate-100 hover:bg-white/5 transition-all">
        <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
        <span>Personal CAS</span>
      </a>

      <a href="#" class="flex items-center gap-3.5 px-3.5 py-3 rounded-xl text-slate-400 hover:text-slate-100 hover:bg-white/5 transition-all">
        <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>
        <span>Calculadora DL 1057</span>
      </a>

      <a href="#" class="flex items-center gap-3.5 px-3.5 py-3 rounded-xl text-slate-400 hover:text-slate-100 hover:bg-white/5 transition-all">
        <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
        <span>Reportes</span>
      </a>

      <a href="#" class="flex items-center gap-3.5 px-3.5 py-3 rounded-xl text-slate-400 hover:text-slate-100 hover:bg-white/5 transition-all">
        <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
        <span>Configuración</span>
      </a>
    </nav>

    <!-- Usuario Activo -->
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
    
    <!-- Encabezado con Botón Abrir Periodo -->
    <div class="flex items-center justify-between mb-6">
      <div>
        <h2 class="text-3xl font-extrabold text-[#111827] tracking-tight">Periodos Mensuales</h2>
        <p class="text-sm text-slate-500 mt-1 font-medium">Control de apertura y cierre de planillas CAS - D.L. 1057</p>
      </div>
      <button onclick="abrirModalNuevoPeriodo()" class="flex items-center gap-2 bg-[#008f5d] hover:bg-[#007a4f] text-white px-5 py-2.5 rounded-xl font-bold text-sm transition-all shadow-[0_4px_14px_rgba(0,143,93,0.35)] active:scale-95">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" /></svg>
        Abrir Periodo
      </button>
    </div>

    <!-- Alertas de estado -->
    <?php if (isset($_GET['error']) && $_GET['error'] === 'existe_abierto'): ?>
	<?php if (isset($_GET['exito']) && $_GET['exito'] === 'reabierto'): ?>
      <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-2xl text-xs font-semibold flex items-center gap-2">
        <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        <span>¡El periodo ha sido reabierto exitosamente! Ahora se encuentra activo para procesar modificaciones y planillas.</span>
      </div>
    <?php endif; ?>
	<?php if (isset($_GET['exito']) && $_GET['exito'] === 'cerrado_limpio'): ?>
      <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-2xl text-xs font-semibold flex items-center gap-2">
        <svg class="w-5 h-5 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span><b>¡Periodo cerrado y archivado correctamente!</b> Se han reiniciado a cero los reintegros, tardanzas, inasistencias y licencias en las fichas de los trabajadores para iniciar el próximo mes en limpio.</span>
      </div>
    <?php endif; ?>
      <div class="mb-6 p-4 bg-amber-50 border border-amber-200 text-amber-800 rounded-2xl text-xs font-semibold flex items-center justify-between">
        <div class="flex items-center gap-2">
          <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
          <span>Ya existe un periodo abierto: <b><?php echo htmlspecialchars(isset($_GET['cod']) ? $_GET['cod'] : ''); ?></b>. Debe cerrar el periodo activo antes de abrir uno nuevo.</span>
        </div>
      </div>
    <?php endif; ?>

    <!-- Banner del Periodo Activo Actual -->
    <div class="mb-6 p-5 rounded-2xl bg-white border border-slate-200/80 shadow-[0_2px_12px_rgba(0,0,0,0.03)] flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div>
        <span class="inline-block px-2.5 py-0.5 bg-[#e6f7ef] text-[#008f5d] text-[10px] font-extrabold uppercase tracking-wider rounded-md mb-1.5">
          Periodo de Trabajo en Curso
        </span>
        <?php if ($periodoActivo): ?>
          <h3 class="text-xl font-bold text-slate-800">
            <?php echo htmlspecialchars($periodoActivo['nombre_mes'] . ' ' . $periodoActivo['anio']); ?> 
            <span class="font-mono text-sm font-semibold text-slate-400 ml-2">(<?php echo htmlspecialchars($periodoActivo['codigo']); ?>)</span>
          </h3>
          <p class="text-xs text-slate-500 mt-0.5">Las modificaciones en personal y descuentos se aplican directamente a este periodo.</p>
        <?php else: ?>
          <h3 class="text-xl font-bold text-slate-400">Ningún periodo abierto actualmente</h3>
          <p class="text-xs text-slate-400 mt-0.5">Haga clic en "+ Abrir Periodo" para procesar el mes siguiente.</p>
        <?php endif; ?>
      </div>

      <?php if ($periodoActivo): ?>
        <a href="acciones_periodo.php?accion=cerrar&id=<?php echo $periodoActivo['id']; ?>"
           onclick="return confirm('¿Está seguro de CERRAR el periodo <?php echo $periodoActivo['codigo']; ?>?\n\n1. La planilla actual quedará congelada en el historial.\n2. Los reintegros, tardanzas y licencias del mes se reiniciarán a 0.00 para el próximo periodo.');"
           class="inline-flex items-center gap-2 bg-[#0c1526] hover:bg-slate-800 text-white px-5 py-2.5 rounded-xl font-bold text-xs shadow-md transition-all active:scale-95">
          <svg class="w-4 h-4 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
          Cerrar Periodo Actual
        </a>
      <?php endif; ?>
    </div>

    <!-- Tabla Histórica de Periodos -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-[0_2px_12px_rgba(0,0,0,0.03)] overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left">
          <thead>
            <tr class="border-b border-slate-200 bg-white text-[11px] font-bold text-slate-400 uppercase tracking-wider">
              <th class="py-4 px-6">CÓDIGO</th>
              <th class="py-4 px-6">PERIODO / MES</th>
              <th class="py-4 px-6 text-center">DÍAS BASE</th>
              <th class="py-4 px-6 text-center">PERSONAL TOTAL</th>
              <th class="py-4 px-6 text-center">ESTADO</th>
              <th class="py-4 px-6 text-right">ACCIONES</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-xs">
            <?php if (empty($periodos)): ?>
              <tr>
                <td colspan="6" class="text-center py-10 text-slate-400 font-medium">No hay periodos registrados aún.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($periodos as $p): 
                $abierto = ($p['estado'] === 'abierto');
              ?>
              <tr class="hover:bg-slate-50/80 transition-colors">
                <td class="py-5 px-6 font-mono font-bold text-slate-700"><?php echo htmlspecialchars($p['codigo']); ?></td>
                <td class="py-5 px-6 font-bold text-slate-800">
                  <?php echo htmlspecialchars($p['nombre_mes'] . ' ' . $p['anio']); ?>
                  <div class="text-[10px] text-slate-400 font-normal mt-0.5">
                    <?php echo $abierto ? 'Abierto desde: ' . substr($p['fecha_apertura'], 0, 10) : 'Cerrado en: ' . substr($p['fecha_cierre'], 0, 10); ?>
                  </div>
                </td>
                <td class="py-5 px-6 text-center font-mono text-slate-600 font-semibold"><?php echo $p['dias_mes']; ?> días</td>
                <td class="py-5 px-6 text-center font-semibold text-slate-700">
                  <?php echo $abierto ? '<span class="text-slate-400 italic">En proceso</span>' : $p['total_personal'] . ' servidores'; ?>
                </td>
                <td class="py-5 px-6 text-center">
                  <?php if ($abierto): ?>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-extrabold bg-[#e6f7ef] text-[#008f5d] tracking-wide uppercase">
                      ABIERTO
                    </span>
                  <?php else: ?>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-extrabold bg-slate-100 text-slate-500 tracking-wide uppercase">
                      CERRADO
                    </span>
                  <?php endif; ?>
                </td>
                <td class="py-5 px-6 text-right space-x-2">
                  <?php if ($abierto): ?>
                    <a href="acciones_periodo.php?accion=cerrar&id=<?php echo $p['id']; ?>"
                       onclick="return confirm('¿Desea cerrar el periodo <?php echo $p['codigo']; ?>?');"
                       class="text-xs font-bold text-rose-600 hover:text-rose-800 bg-rose-50 hover:bg-rose-100 px-3 py-1.5 rounded-lg transition-colors inline-block">
                      Cerrar Periodo
                    </a>
                  <?php else: ?>
                    <a href="acciones_periodo.php?accion=reabrir&id=<?php echo $p['id']; ?>"
                       onclick="return confirm('¿Está seguro de REABRIR el periodo <?php echo $p['codigo']; ?>?\n\nRecuerde que no debe haber otro periodo abierto al mismo tiempo.');"
                       class="text-xs font-bold text-amber-600 hover:text-amber-800 bg-amber-50 hover:bg-amber-100 border border-amber-200/80 px-3 py-1.5 rounded-lg transition-colors inline-flex items-center gap-1.5">
                      <svg class="w-3.5 h-3.5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                      Reabrir Periodo
                    </a>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>

  <!-- MODAL: ABRIR NUEVO PERIODO -->
  <div id="modalNuevoPeriodo" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm hidden flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-3xl max-w-md w-full p-7 shadow-2xl border border-slate-100">
      
      <div class="flex items-center justify-between pb-3 border-b border-slate-100">
        <div>
          <h3 class="text-lg font-bold text-slate-800">Abrir Nuevo Periodo</h3>
          <p class="text-xs text-slate-400 font-medium">Habilitar cálculo para el siguiente mes</p>
        </div>
        <button onclick="cerrarModalNuevoPeriodo()" class="w-8 h-8 rounded-full flex items-center justify-center text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors text-2xl leading-none">&times;</button>
      </div>

      <form action="acciones_periodo.php" method="POST" class="mt-4 space-y-4 text-xs font-semibold">
        <input type="hidden" name="accion" value="abrir">

        <div>
          <label class="block text-slate-500 mb-1.5">Año Fiscal</label>
          <input type="number" name="anio" value="2026" min="2025" max="2035" required 
                 class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-[#008f5d]/30 focus:border-[#008f5d] outline-none font-mono">
        </div>

        <div>
          <label class="block text-slate-500 mb-1.5">Mes a Aperturar</label>
          <select name="mes" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-[#008f5d]/30 focus:border-[#008f5d] outline-none">
            <option value="1">01 - Enero</option>
            <option value="2">02 - Febrero</option>
            <option value="3">03 - Marzo</option>
            <option value="4">04 - Abril</option>
            <option value="5">05 - Mayo</option>
            <option value="6">06 - Junio</option>
            <option value="7">07 - Julio</option>
            <option value="8">08 - Agosto</option>
            <option value="9">09 - Septiembre</option>
            <option value="10" selected>10 - Octubre</option>
            <option value="11">11 - Noviembre</option>
            <option value="12">12 - Diciembre</option>
          </select>
        </div>

        <div>
          <label class="block text-slate-500 mb-1.5">Base de Días Mensuales</label>
          <input type="number" name="dias_mes" value="30" min="28" max="31" required 
                 class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-[#008f5d]/30 focus:border-[#008f5d] outline-none font-mono">
        </div>

        <div class="flex justify-end gap-2.5 pt-4 border-t border-slate-100">
          <button type="button" onclick="cerrarModalNuevoPeriodo()" class="px-5 py-2.5 text-slate-600 hover:bg-slate-100 rounded-xl font-semibold text-xs transition-colors">Cancelar</button>
          <button type="submit" class="px-6 py-2.5 bg-[#008f5d] hover:bg-[#007a4f] text-white rounded-xl shadow-md font-bold text-xs transition-all active:scale-95">Abrir Periodo</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    const modalPeriodo = document.getElementById('modalNuevoPeriodo');

    function abrirModalNuevoPeriodo() {
      modalPeriodo.classList.remove('hidden');
    }

    function cerrarModalNuevoPeriodo() {
      modalPeriodo.classList.add('hidden');
    }
  </script>
</body>
</html>