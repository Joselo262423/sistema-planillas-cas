<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'conexion.php';

$mensaje = '';
$error = '';

// 1. PROCESAR ACTUALIZACIÓN VISUAL DE TASAS AFP / ONP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'actualizar_tasas') {
    if (isset($_POST['tasas']) && is_array($_POST['tasas'])) {
        foreach ($_POST['tasas'] as $id => $val) {
            $id_tasa  = intval($id);
            $aporte   = floatval($val['aporte']);
            $comision = floatval($val['comision']);
            $seguro   = floatval($val['seguro']);
            $total    = round($aporte + $comision + $seguro, 2);

            // Actualizamos los 3 componentes y el total_porcentaje
            $stmt = $pdo->prepare("UPDATE comisiones_pensionarias 
                                   SET aporte_obligatorio = ?, comision_porcentaje = ?, prima_seguro = ?, total_porcentaje = ? 
                                   WHERE id = ?");
            $stmt->execute(array($aporte, $comision, $seguro, $total, $id_tasa));
        }
    }

    // Si también se actualizó la UIT del periodo abierto
    if (isset($_POST['uit_vigente'])) {
        $nuevaUit = floatval($_POST['uit_vigente']);
        if ($nuevaUit > 0) {
            $stmtUit = $pdo->prepare("UPDATE periodos SET uit_vigente = ? WHERE estado = 'abierto'");
            $stmtUit->execute(array($nuevaUit));
        }
    }

    $mensaje = "¡Tasas previsionales (AFP/ONP) y UIT actualizadas correctamente! Si tiene una planilla abierta, vuelva a pulsar 'Procesar / Recalcular Planilla' para aplicar los nuevos porcentajes.";
}

// 2. PROCESAR CREACIÓN DE NUEVO USUARIO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'guardar_usuario') {
    $nombre   = trim(isset($_POST['nombre_completo']) ? $_POST['nombre_completo'] : '');
    $usuario  = trim(isset($_POST['usuario']) ? $_POST['usuario'] : '');
    $password = trim(isset($_POST['password']) ? $_POST['password'] : '');
    $rol      = trim(isset($_POST['rol']) ? $_POST['rol'] : 'Especialista Planillas');

    if (!empty($nombre) && !empty($usuario) && !empty($password)) {
        $stmtChk = $pdo->prepare("SELECT id FROM usuarios WHERE usuario = ?");
        $stmtChk->execute(array($usuario));
        if ($stmtChk->fetch()) {
            $error = 'El nombre de usuario ingresado ya se encuentra registrado.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmtIns = $pdo->prepare("INSERT INTO usuarios (nombre_completo, usuario, password, rol, estado) VALUES (?, ?, ?, ?, 'activo')");
            $stmtIns->execute(array($nombre, $usuario, $hash, $rol));
            $mensaje = 'Usuario institucional creado con éxito.';
        }
    } else {
        $error = 'Por favor complete todos los campos obligatorios del usuario.';
    }
}

// Cargar listado de regímenes y comisiones
$comisiones = $pdo->query("SELECT * FROM comisiones_pensionarias ORDER BY id ASC")->fetchAll();

// Cargar periodo abierto para mostrar la UIT vigente
$periodoActivo = $pdo->query("SELECT * FROM periodos WHERE estado = 'abierto' LIMIT 1")->fetch();
$uitActual = ($periodoActivo && !empty($periodoActivo['uit_vigente'])) ? floatval($periodoActivo['uit_vigente']) : 5500.00;
$topeEssalud = round($uitActual * 0.45, 2);

// Cargar listado de usuarios
$usuarios = $pdo->query("SELECT id, nombre_completo, usuario, rol, estado, creado_en FROM usuarios ORDER BY id ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Configuración y Tasas SBS - UGEL Ventanilla</title>
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
 	 <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 		1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
  	<span>Dashboard</span>
	</a>
      <a href="planillas.php" class="flex items-center gap-3.5 px-3.5 py-3 rounded-xl text-slate-400 hover:text-slate-100 hover:bg-white/5 transition-all"><svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" /></svg><span>Planillas</span></a>
      <a href="periodos.php" class="flex items-center gap-3.5 px-3.5 py-3 rounded-xl text-slate-400 hover:text-slate-100 hover:bg-white/5 transition-all"><svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg><span>Periodos</span></a>
      <a href="index.php" class="flex items-center gap-3.5 px-3.5 py-3 rounded-xl text-slate-400 hover:text-slate-100 hover:bg-white/5 transition-all"><svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg><span>Personal CAS</span></a>
      <a href="calculadora_cas.php" class="flex items-center gap-3.5 px-3.5 py-3 rounded-xl text-slate-400 hover:text-slate-100 hover:bg-white/5 transition-all"><svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg><span>Calculadora DL 1057</span></a>
      <a href="reportes.php" class="flex items-center gap-3.5 px-3.5 py-3 rounded-xl text-slate-400 hover:text-slate-100 hover:bg-white/5 transition-all"><svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg><span>Reportes</span></a>
      <a href="configuracion.php" class="flex items-center gap-3.5 px-3.5 py-3 rounded-xl bg-gradient-to-r from-[#17243c] to-[#1c2c49] text-white font-bold shadow-md border border-slate-700/40"><svg class="w-5 h-5 text-[#00c982]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg><span>Configuración</span></a>
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
  <main class="flex-1 p-8 lg:p-10 overflow-y-auto">
    <div class="mb-6">
      <h2 class="text-3xl font-extrabold text-[#111827] tracking-tight">Configuración y Parámetros</h2>
      <p class="text-sm text-slate-500 mt-1 font-medium">Actualización de tasas previsionales SBS (AFP / ONP), valor UIT y usuarios del sistema</p>
    </div>

    <?php if (!empty($mensaje)): ?>
      <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-2xl text-xs font-semibold flex items-center gap-2">
        <svg class="w-5 h-5 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        <span><?php echo htmlspecialchars($mensaje); ?></span>
      </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
      <div class="mb-6 p-4 bg-rose-50 border border-rose-200 text-rose-700 rounded-2xl text-xs font-semibold flex items-center gap-2">
        <svg class="w-5 h-5 text-rose-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        <span><?php echo htmlspecialchars($error); ?></span>
      </div>
    <?php endif; ?>

    <!-- SECCIÓN 1: PANEL VISUAL DE TASAS AFP / ONP Y PARÁMETROS UIT -->
    <div class="bg-white rounded-3xl p-7 border border-slate-200/80 shadow-[0_2px_12px_rgba(0,0,0,0.03)] mb-8">
      <form method="POST">
        <input type="hidden" name="accion" value="actualizar_tasas">

        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-5 border-b border-slate-100 mb-5">
          <div>
            <span class="inline-block px-2.5 py-0.5 bg-[#e6f7ef] text-[#008f5d] text-[10px] font-extrabold uppercase tracking-wider rounded-md mb-1">
              Parámetros de Ley & SBS
            </span>
            <h3 class="text-lg font-extrabold text-slate-800">1. Tasas de Régimen Pensionario (AFP / ONP) y Valor UIT</h3>
            <p class="text-xs text-slate-400">Edite directamente los porcentajes cuando varíen según la SBS y presione Guardar.</p>
          </div>

          <!-- Parámetro UIT Vigente -->
          <div class="flex items-center gap-3 bg-slate-50 p-3 rounded-2xl border border-slate-200">
            <div>
              <label class="block text-[10px] font-extrabold text-slate-500 uppercase">Valor UIT Vigente (S/)</label>
              <input type="number" step="0.01" name="uit_vigente" value="<?php echo number_format($uitActual, 2, '.', ''); ?>" 
                     class="w-28 px-2.5 py-1 mt-0.5 bg-white border border-slate-300 rounded-lg font-mono text-xs font-bold text-slate-800 outline-none focus:border-[#008f5d]">
            </div>
            <div class="border-l border-slate-200 pl-3">
              <span class="block text-[10px] font-bold text-slate-400 uppercase">Tope EsSalud (45% UIT)</span>
              <span class="font-mono text-xs font-extrabold text-blue-600">S/ <?php echo number_format($topeEssalud, 2); ?></span>
            </div>
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead>
              <tr class="border-b border-slate-200 text-slate-400 font-bold uppercase text-[11px]">
                <th class="py-3 px-4">RÉGIMEN / ENTIDAD PREVISIONAL</th>
                <th class="py-3 px-4 text-center">TIPO COMISIÓN</th>
                <th class="py-3 px-4 text-center">APORTE OBLIG. (%)</th>
                <th class="py-3 px-4 text-center">COMISIÓNVARIABLE (%)</th>
                <th class="py-3 px-4 text-center">PRIMA SEGURO (%)</th>
                <th class="py-3 px-4 text-center">TOTAL DESCUENTO (%)</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 font-semibold text-slate-700">
              <?php foreach ($comisiones as $c): 
                $esExonerado = ($c['tipo_regimen'] === 'NINGUNO');
              ?>
              <tr class="hover:bg-slate-50/80 transition-colors <?php echo $esExonerado ? 'bg-amber-50/40' : ''; ?>">
                <td class="py-3.5 px-4 font-bold text-slate-800">
                  <?php echo htmlspecialchars($c['nombre_completo']); ?>
                </td>
                <td class="py-3.5 px-4 text-center">
                  <span class="px-2.5 py-0.5 rounded-md text-[10px] font-extrabold <?php echo $esExonerado ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600'; ?>">
                    <?php echo htmlspecialchars($c['comision_tipo']); ?>
                  </span>
                </td>
                <td class="py-2.5 px-4 text-center">
                  <input type="number" step="0.01" min="0" max="100" 
                         name="tasas[<?php echo $c['id']; ?>][aporte]" 
                         value="<?php echo number_format($c['aporte_obligatorio'], 2, '.', ''); ?>" 
                         oninput="recalcularFila(<?php echo $c['id']; ?>)"
                         id="aporte_<?php echo $c['id']; ?>"
                         class="w-24 text-center py-1.5 bg-slate-50 border border-slate-200 rounded-xl font-mono text-xs font-bold focus:bg-white focus:ring-2 focus:ring-[#008f5d]/30 outline-none">
                </td>
                <td class="py-2.5 px-4 text-center">
                  <input type="number" step="0.01" min="0" max="100" 
                         name="tasas[<?php echo $c['id']; ?>][comision]" 
                         value="<?php echo number_format($c['comision_porcentaje'], 2, '.', ''); ?>" 
                         oninput="recalcularFila(<?php echo $c['id']; ?>)"
                         id="comision_<?php echo $c['id']; ?>"
                         class="w-24 text-center py-1.5 bg-slate-50 border border-slate-200 rounded-xl font-mono text-xs font-bold focus:bg-white focus:ring-2 focus:ring-[#008f5d]/30 outline-none">
                </td>
                <td class="py-2.5 px-4 text-center">
                  <input type="number" step="0.01" min="0" max="100" 
                         name="tasas[<?php echo $c['id']; ?>][seguro]" 
                         value="<?php echo number_format($c['prima_seguro'], 2, '.', ''); ?>" 
                         oninput="recalcularFila(<?php echo $c['id']; ?>)"
                         id="seguro_<?php echo $c['id']; ?>"
                         class="w-24 text-center py-1.5 bg-slate-50 border border-slate-200 rounded-xl font-mono text-xs font-bold focus:bg-white focus:ring-2 focus:ring-[#008f5d]/30 outline-none">
                </td>
                <td class="py-2.5 px-4 text-center font-mono font-extrabold text-sm text-[#008f5d]">
                  <span id="total_<?php echo $c['id']; ?>"><?php echo number_format($c['total_porcentaje'], 2, '.', ''); ?></span>%
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div class="flex justify-end mt-6 pt-4 border-t border-slate-100">
          <button type="submit" class="px-6 py-3 bg-[#008f5d] hover:bg-[#007a4f] text-white rounded-xl font-bold text-xs shadow-[0_4px_14px_rgba(0,143,93,0.35)] transition-all active:scale-95 flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
            Guardar Nuevas Tasas y Parámetros
          </button>
        </div>
      </form>
    </div>

    <!-- SECCIÓN 2: GESTIÓN DE USUARIOS -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
      <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-[0_2px_12px_rgba(0,0,0,0.03)]">
        <h3 class="text-base font-extrabold text-slate-800 mb-4">2. Registrar Nuevo Usuario</h3>
        <form method="POST" class="space-y-4 text-xs font-semibold">
          <input type="hidden" name="accion" value="guardar_usuario">

          <div>
            <label class="block text-slate-500 mb-1.5">Nombres y Apellidos *</label>
            <input type="text" name="nombre_completo" required placeholder="Ej. Juan Pérez Ramos" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-[#008f5d]/30">
          </div>

          <div>
            <label class="block text-slate-500 mb-1.5">Usuario Institucional *</label>
            <input type="text" name="usuario" required placeholder="Ej. jperez" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-[#008f5d]/30">
          </div>

          <div>
            <label class="block text-slate-500 mb-1.5">Contraseña *</label>
            <input type="password" name="password" required placeholder="••••••••" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-[#008f5d]/30">
          </div>

          <div>
            <label class="block text-slate-500 mb-1.5">Rol en el Sistema *</label>
            <select name="rol" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-[#008f5d]/30">
              <option value="Especialista Planillas">Especialista en Planillas</option>
              <option value="Asistente de Planillas">Asistente de Planillas</option>
              <option value="Jefe de RRHH">Jefe de Recursos Humanos</option>
              <option value="Administrador CAS">Administrador del Sistema</option>
            </select>
          </div>

          <div class="pt-2">
            <button type="submit" class="w-full py-3 bg-[#0c1526] hover:bg-slate-800 text-white rounded-xl font-bold text-xs shadow-md transition-all active:scale-95">
              Crear Usuario
            </button>
          </div>
        </form>
      </div>

      <div class="lg:col-span-2 bg-white rounded-3xl p-6 border border-slate-200/80 shadow-[0_2px_12px_rgba(0,0,0,0.03)]">
        <h3 class="text-base font-extrabold text-slate-800 mb-4">Usuarios Activos en la UGEL Ventanilla</h3>
        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead>
              <tr class="border-b border-slate-200 text-slate-400 font-bold uppercase text-[11px]">
                <th class="py-3 px-4">USUARIO</th>
                <th class="py-3 px-4">NOMBRE COMPLETO</th>
                <th class="py-3 px-4 text-center">ROL ASIGNADO</th>
                <th class="py-3 px-4 text-center">ESTADO</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 font-medium">
              <?php foreach ($usuarios as $u): ?>
              <tr class="hover:bg-slate-50">
                <td class="py-3.5 px-4 font-mono font-bold text-slate-800"><?php echo htmlspecialchars($u['usuario']); ?></td>
                <td class="py-3.5 px-4 font-semibold text-slate-700"><?php echo htmlspecialchars($u['nombre_completo']); ?></td>
                <td class="py-3.5 px-4 text-center">
                  <span class="inline-block bg-slate-100 text-slate-700 px-2.5 py-1 rounded-md text-[10px] font-bold">
                    <?php echo htmlspecialchars($u['rol']); ?>
                  </span>
                </td>
                <td class="py-3.5 px-4 text-center">
                  <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-[#e6f7ef] text-[#008f5d] uppercase">
                    <?php echo htmlspecialchars($u['estado']); ?>
                  </span>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </main>

  <script>
    // Suma en vivo mientras escribes el nuevo porcentaje en la tabla
    function recalcularFila(id) {
      const a = parseFloat(document.getElementById('aporte_' + id).value) || 0;
      const c = parseFloat(document.getElementById('comision_' + id).value) || 0;
      const s = parseFloat(document.getElementById('seguro_' + id).value) || 0;
      const total = (a + c + s).toFixed(2);
      document.getElementById('total_' + id).innerText = total;
    }
  </script>
</body>
</html>