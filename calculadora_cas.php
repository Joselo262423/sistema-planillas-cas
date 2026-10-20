<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'conexion.php';

// Cargar servidores CAS para pre-cargar datos rápidamente si se desea
$personal = $pdo->query("SELECT id, dni, apellido_paterno, apellido_materno, nombres, remuneracion_mensual, fecha_inicio, fecha_termino FROM trabajadores_cas ORDER BY apellido_paterno ASC")->fetchAll();

$resultado = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $remuneracion = floatval(isset($_POST['remuneracion']) ? $_POST['remuneracion'] : 0);
    $meses        = intval(isset($_POST['meses']) ? $_POST['meses'] : 0);
    $dias         = intval(isset($_POST['dias']) ? $_POST['dias'] : 0);
    $sistemaPens  = isset($_POST['sistema_pension']) ? $_POST['sistema_pension'] : 'ONP';
    $calcAguinaldo= isset($_POST['calc_aguinaldo']) ? 1 : 0;
    $mesesAguinaldo = intval(isset($_POST['meses_aguinaldo']) ? $_POST['meses_aguinaldo'] : 0);

    if ($remuneracion > 0) {
        // 1. Vacaciones Truncas
        $montoMeses = ($remuneracion / 12) * $meses;
        $montoDias  = ($remuneracion / 360) * $dias;
        $brutoVacaciones = round($montoMeses + $montoDias, 2);

        // Descuento previsional sobre vacaciones (ONP 13%, AFP aprox 12.8%)
        $tasa = ($sistemaPens === 'ONP') ? 0.13 : 0.128;
        $descPension = round($brutoVacaciones * $tasa, 2);
        $netoVacaciones = round($brutoVacaciones - $descPension, 2);

        // 2. Aguinaldo Proporcional (Base oficial S/ 300.00 entre 6 meses)
        $aguinaldo = 0.00;
        if ($calcAguinaldo && $mesesAguinaldo > 0) {
            $aguinaldo = round((300.00 / 6) * min(6, $mesesAguinaldo), 2);
        }

        $totalLiquido = round($netoVacaciones + $aguinaldo, 2);
        $essaludPatronal = round($brutoVacaciones * 0.09, 2);

        $resultado = array(
            'remuneracion' => $remuneracion,
            'meses'        => $meses,
            'dias'         => $dias,
            'bruto_vac'    => $brutoVacaciones,
            'desc_pension' => $descPension,
            'neto_vac'     => $netoVacaciones,
            'aguinaldo'    => $aguinaldo,
            'total_liquido'=> $totalLiquido,
            'essalud'      => $essaludPatronal,
            'sistema'      => $sistemaPens
        );
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Calculadora CAS D.L. 1057 - UGEL Ventanilla</title>
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
 	 <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 	1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
 	 <span>Dashboard</span>
	</a>
      <a href="planillas.php" class="flex items-center gap-3.5 px-3.5 py-3 rounded-xl text-slate-400 hover:text-slate-100 hover:bg-white/5 transition-all">
        <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" /></svg>
        <span>Planillas</span>
      </a>
      <a href="periodos.php" class="flex items-center gap-3.5 px-3.5 py-3 rounded-xl text-slate-400 hover:text-slate-100 hover:bg-white/5 transition-all">
        <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
        <span>Periodos</span>
      </a>
      <a href="index.php" class="flex items-center gap-3.5 px-3.5 py-3 rounded-xl text-slate-400 hover:text-slate-100 hover:bg-white/5 transition-all">
        <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
        <span>Personal CAS</span>
      </a>
      <a href="calculadora_cas.php" class="flex items-center gap-3.5 px-3.5 py-3 rounded-xl bg-gradient-to-r from-[#17243c] to-[#1c2c49] text-white font-bold shadow-md border border-slate-700/40">
        <svg class="w-5 h-5 text-[#00c982]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>
        <span>Calculadora DL 1057</span>
      </a>
      <a href="reportes.php" class="flex items-center gap-3.5 px-3.5 py-3 rounded-xl text-slate-400 hover:text-slate-100 hover:bg-white/5 transition-all">
        <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
        <span>Reportes</span>
      </a>
      <a href="configuracion.php" class="flex items-center gap-3.5 px-3.5 py-3 rounded-xl text-slate-400 hover:text-slate-100 hover:bg-white/5 transition-all">
        <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
        <span>Configuración</span>
      </a>
    </nav>

    <div class="p-4 border-t border-slate-800/60 bg-[#0a1120] mt-auto">
      <div class="flex items-center justify-between">
        <div class="truncate pr-2">
          <p class="text-xs font-bold text-white truncate"><?php echo htmlspecialchars(isset($_SESSION['usuario_nombre']) ? $_SESSION['usuario_nombre'] : 'Especialista'); ?></p>
          <p class="text-[10px] text-[#00c982] font-semibold truncate"><?php echo htmlspecialchars(isset($_SESSION['usuario_rol']) ? $_SESSION['usuario_rol'] : 'CAS'); ?></p>
        </div>
        <a href="logout.php" class="p-2 text-slate-400 hover:text-rose-400 hover:bg-white/5 rounded-xl transition-all">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
        </a>
      </div>
    </div>
  </aside>

  <!-- CONTENIDO PRINCIPAL -->
  <main class="flex-1 p-8 lg:p-10 overflow-y-auto">
    <div class="mb-8">
      <h2 class="text-3xl font-extrabold text-[#111827] tracking-tight">Calculadora Laboral D.L. 1057</h2>
      <p class="text-sm text-slate-500 mt-1 font-medium">Liquidación de vacaciones truncas y beneficios para personal CAS</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
      <!-- Formulario de Cálculo -->
      <div class="lg:col-span-2 bg-white rounded-3xl p-7 border border-slate-200/80 shadow-[0_2px_12px_rgba(0,0,0,0.03)]">
        <form method="POST" class="space-y-5 text-xs font-semibold">
          
          <div>
            <label class="block text-slate-500 mb-1.5">Cargar Servidor CAS (Opcional)</label>
            <select id="selectPersonal" onchange="autocompletarServidor()" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-[#008f5d]/30 outline-none">
              <option value="">-- Ingresar datos manualmente o seleccionar --</option>
              <?php foreach ($personal as $p): ?>
                <option value="<?php echo htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8'); ?>">
                  <?php echo htmlspecialchars($p['apellido_paterno'] . ' ' . $p['apellido_materno'] . ', ' . $p['nombres'] . ' (S/ ' . number_format($p['remuneracion_mensual'], 2) . ')'); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-slate-500 mb-1.5">Remuneración Mensual (S/) *</label>
              <input type="number" step="0.01" name="remuneracion" id="calcRemuneracion" required placeholder="Ej. 3200.00" 
                     value="<?php echo isset($_POST['remuneracion']) ? htmlspecialchars($_POST['remuneracion']) : ''; ?>"
                     class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl font-mono text-sm font-bold text-slate-800 outline-none focus:ring-2 focus:ring-[#008f5d]/30">
            </div>
            <div>
              <label class="block text-slate-500 mb-1.5">Régimen Pensionario *</label>
              <select name="sistema_pension" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-[#008f5d]/30">
                <option value="ONP" <?php echo (isset($_POST['sistema_pension']) && $_POST['sistema_pension'] === 'ONP') ? 'selected' : ''; ?>>ONP (13.00%)</option>
                <option value="AFP" <?php echo (isset($_POST['sistema_pension']) && $_POST['sistema_pension'] === 'AFP') ? 'selected' : ''; ?>>AFP (Aprox 12.80%)</option>
              </select>
            </div>
          </div>

          <div class="p-4 bg-slate-50 border border-slate-200 rounded-2xl space-y-3">
            <span class="text-xs font-bold text-slate-700 uppercase tracking-wider block">Tiempo Laborado para Vacaciones Truncas</span>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-slate-500 mb-1">Meses Cumplidos (1/12 cada uno)</label>
                <input type="number" min="0" max="11" name="meses" required placeholder="0" 
                       value="<?php echo isset($_POST['meses']) ? htmlspecialchars($_POST['meses']) : '0'; ?>"
                       class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl font-mono outline-none">
              </div>
              <div>
                <label class="block text-slate-500 mb-1">Días Computables (1/360 cada uno)</label>
                <input type="number" min="0" max="29" name="dias" required placeholder="0" 
                       value="<?php echo isset($_POST['dias']) ? htmlspecialchars($_POST['dias']) : '0'; ?>"
                       class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl font-mono outline-none">
              </div>
            </div>
          </div>

          <div class="p-4 bg-slate-50 border border-slate-200 rounded-2xl space-y-3">
            <div class="flex items-center justify-between">
              <span class="text-xs font-bold text-slate-700 uppercase tracking-wider">Aguinaldo Proporcional (Julio / Diciembre)</span>
              <label class="inline-flex items-center gap-1.5 cursor-pointer text-slate-600">
                <input type="checkbox" name="calc_aguinaldo" value="1" <?php echo isset($_POST['calc_aguinaldo']) ? 'checked' : ''; ?> class="rounded text-[#008f5d]">
                <span>Incluir en liquidación</span>
              </label>
            </div>
            <div>
              <label class="block text-slate-500 mb-1">Meses trabajados en el semestre (Base S/ 300.00)</label>
              <input type="number" min="0" max="6" name="meses_aguinaldo" 
                     value="<?php echo isset($_POST['meses_aguinaldo']) ? htmlspecialchars($_POST['meses_aguinaldo']) : '0'; ?>"
                     placeholder="Ej. 6 para semestre completo" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl font-mono outline-none">
            </div>
          </div>

          <div class="pt-2 flex justify-end">
            <button type="submit" class="px-6 py-3 bg-[#008f5d] hover:bg-[#007a4f] text-white rounded-xl font-bold text-xs shadow-md transition-all active:scale-95">
              Calcular Liquidación CAS
            </button>
          </div>
        </form>
      </div>

      <!-- Panel de Resultados -->
      <div class="bg-white rounded-3xl p-7 border border-slate-200/80 shadow-[0_2px_12px_rgba(0,0,0,0.03)] flex flex-col justify-between">
        <div>
          <h3 class="text-base font-extrabold text-slate-800 border-b border-slate-100 pb-3">Resumen de Liquidación</h3>
          
          <?php if ($resultado): ?>
            <div class="space-y-3 mt-4 text-xs font-semibold">
              <div class="flex justify-between py-1.5 border-b border-slate-100">
                <span class="text-slate-500">Vacaciones Truncas (Bruto):</span>
                <span class="font-mono text-slate-800 font-bold">S/ <?php echo number_format($resultado['bruto_vac'], 2); ?></span>
              </div>
              <div class="flex justify-between py-1.5 border-b border-slate-100 text-rose-600">
                <span>Retención <?php echo $resultado['sistema']; ?>:</span>
                <span class="font-mono font-bold">- S/ <?php echo number_format($resultado['desc_pension'], 2); ?></span>
              </div>
              <div class="flex justify-between py-1.5 border-b border-slate-100">
                <span class="text-slate-600">Vacaciones Truncas (Neto):</span>
                <span class="font-mono text-slate-800 font-bold">S/ <?php echo number_format($resultado['neto_vac'], 2); ?></span>
              </div>
              <?php if ($resultado['aguinaldo'] > 0): ?>
              <div class="flex justify-between py-1.5 border-b border-slate-100 text-emerald-700">
                <span>Aguinaldo Proporcional:</span>
                <span class="font-mono font-bold">+ S/ <?php echo number_format($resultado['aguinaldo'], 2); ?></span>
              </div>
              <?php endif; ?>
              <div class="flex justify-between py-1.5 border-b border-slate-100 text-blue-600">
                <span>EsSalud Patronal (9%):</span>
                <span class="font-mono font-bold">S/ <?php echo number_format($resultado['essalud'], 2); ?></span>
              </div>
            </div>

            <div class="mt-6 p-4 bg-[#e6f7ef] border border-[#00a86b]/30 rounded-2xl text-center">
              <span class="text-[11px] font-extrabold text-[#008f5d] uppercase tracking-wider block">Total Neto a Liquidar</span>
              <p class="text-3xl font-extrabold font-mono text-slate-900 mt-1">S/ <?php echo number_format($resultado['total_liquido'], 2); ?></p>
            </div>
          <?php else: ?>
            <div class="py-16 text-center text-slate-400 text-xs">
              <svg class="w-12 h-12 mx-auto mb-2 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
              Complete los datos en el formulario y presione "Calcular Liquidación CAS".
            </div>
          <?php endif; ?>
        </div>

        <div class="pt-4 border-t border-slate-100 text-[11px] text-slate-400">
          Base legal: D.L. 1057, D.S. 075-2008-PCM y Ley N° 29849.
        </div>
      </div>
    </div>
  </main>

  <script>
    function autocompletarServidor() {
      const val = document.getElementById('selectPersonal').value;
      if (!val) return;
      const data = JSON.parse(val);
      if (data.remuneracion_mensual) {
        document.getElementById('calcRemuneracion').value = data.remuneracion_mensual;
      }
    }
  </script>
</body>
</html>