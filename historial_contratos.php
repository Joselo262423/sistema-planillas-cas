<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}
require_once 'conexion.php';

$id_trabajador = isset($_GET['id']) ? intval($_GET['id']) : 0;

$stmtT = $pdo->prepare("SELECT * FROM trabajadores_cas WHERE id = ?");
$stmtT->execute(array($id_trabajador));
$trabajador = $stmtT->fetch();

if (!$trabajador) {
    die("Servidor CAS no encontrado.");
}

$mensaje = '';

// Procesar nuevo contrato o adenda
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nuevo_contrato'])) {
    $num_contrato = trim($_POST['num_contrato']);
    $cargo        = trim($_POST['cargo']);
    $meta         = trim($_POST['meta']);
    $establec     = trim($_POST['establecimiento']);
    $cod_nexus    = trim($_POST['cod_nexus']);
    $cod_airhsp   = trim($_POST['cod_airhsp']);
    $remuneracion = floatval($_POST['remuneracion_mensual']);
    $fecha_inicio = $_POST['fecha_inicio'];
    $fecha_termino= !empty($_POST['fecha_termino']) ? $_POST['fecha_termino'] : null;
    $tipo         = $_POST['tipo_contrato'];
    $obs          = trim($_POST['observaciones']);

    // Finalizar los contratos anteriores de este trabajador
    $stmtCerrar = $pdo->prepare("UPDATE contratos_cas SET estado = 'finalizado' WHERE id_trabajador = ?");
    $stmtCerrar->execute(array($id_trabajador));

    // Insertar el nuevo contrato activo
    $sqlIns = "INSERT INTO contratos_cas 
        (id_trabajador, num_contrato, cargo, meta, establecimiento, cod_nexus, cod_airhsp, remuneracion_mensual, fecha_inicio, fecha_termino, tipo_contrato, observaciones, estado)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'activo')";
    $stmtIns = $pdo->prepare($sqlIns);
    $stmtIns->execute(array(
        $id_trabajador, $num_contrato, $cargo, $meta, $establec, $cod_nexus, $cod_airhsp, $remuneracion, $fecha_inicio, $fecha_termino, $tipo, $obs
    ));

    // Sincronizar el contrato activo en la ficha principal para compatibilidad inmediata
    $stmtSync = $pdo->prepare("UPDATE trabajadores_cas SET num_contrato = ?, cargo = ?, meta = ?, establecimiento = ?, cod_nexus = ?, cod_airhsp = ?, remuneracion_mensual = ?, fecha_inicio = ?, fecha_termino = ? WHERE id = ?");
    $stmtSync->execute(array($num_contrato, $cargo, $meta, $establec, $cod_nexus, $cod_airhsp, $remuneracion, $fecha_inicio, $fecha_termino, $id_trabajador));

    $mensaje = "¡Nuevo contrato registrado con éxito! El contrato anterior quedó guardado en el historial.";
}

// Cargar todos los contratos del trabajador cronológicamente descendente
$stmtContratos = $pdo->prepare("SELECT * FROM contratos_cas WHERE id_trabajador = ? ORDER BY fecha_inicio DESC, id DESC");
$stmtContratos->execute(array($id_trabajador));
$contratos = $stmtContratos->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Historial de Contratos CAS - UGEL Ventanilla</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
  <style> body { font-family: 'Plus Jakarta Sans', sans-serif; } .font-mono { font-family: 'JetBrains Mono', monospace; } </style>
</head>
<body class="bg-[#f0f4f8] text-slate-700 min-h-screen p-6 lg:p-10">

  <div class="max-w-5xl mx-auto">
    <!-- Encabezado con datos del trabajador -->
    <div class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-sm mb-6 flex items-center justify-between">
      <div>
        <span class="text-[11px] font-extrabold text-[#008f5d] uppercase tracking-wider bg-[#e6f7ef] px-3 py-1 rounded-full">
          DNI: <?php echo htmlspecialchars($trabajador['dni']); ?>
        </span>
        <h2 class="text-2xl font-extrabold text-slate-800 mt-2">
          <?php echo htmlspecialchars($trabajador['apellido_paterno'] . ' ' . $trabajador['apellido_materno'] . ', ' . $trabajador['nombres']); ?>
        </h2>
        <p class="text-xs text-slate-400 mt-0.5">Historial cronológico de contratos, adendas y modificaciones salariales</p>
      </div>
      <div class="flex items-center gap-3">
        <button onclick="document.getElementById('modalNuevoContrato').classList.remove('hidden')" class="px-5 py-2.5 bg-[#008f5d] hover:bg-[#007a4f] text-white rounded-xl font-bold text-xs shadow-md transition-all">
          + Registrar Nuevo Contrato / Adenda
        </button>
        <a href="index.php" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-bold text-xs">Volver</a>
      </div>
    </div>

    <?php if ($mensaje): ?>
      <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-2xl text-xs font-semibold">
        <?php echo $mensaje; ?>
      </div>
    <?php endif; ?>

    <!-- Tabla Historial de Contratos -->
    <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm overflow-hidden">
      <div class="p-5 border-b border-slate-100">
        <h3 class="font-bold text-slate-800 text-sm">Línea de Tiempo de Contratos Laborales</h3>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead>
            <tr class="border-b border-slate-200 text-slate-400 font-bold uppercase text-[10.5px]">
              <th class="py-3.5 px-5">TIPO / N° CONTRATO</th>
              <th class="py-3.5 px-4">CARGO Y SEDE</th>
              <th class="py-3.5 px-4 text-center">META</th>
              <th class="py-3.5 px-4 text-center">VIGENCIA</th>
              <th class="py-3.5 px-4 text-right">REMUNERACIÓN</th>
              <th class="py-3.5 px-5 text-center">CONDICIÓN</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 font-medium">
            <?php foreach ($contratos as $c): 
              $esActivo = ($c['estado'] === 'activo');
            ?>
            <tr class="<?php echo $esActivo ? 'bg-emerald-50/40' : 'hover:bg-slate-50'; ?>">
              <td class="py-4 px-5">
                <span class="inline-block px-2 py-0.5 rounded text-[10px] font-extrabold <?php echo $esActivo ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600'; ?> mb-1">
                  <?php echo htmlspecialchars($c['tipo_contrato']); ?>
                </span>
                <div class="font-bold text-slate-800 font-mono"><?php echo htmlspecialchars($c['num_contrato']); ?></div>
              </td>
              <td class="py-4 px-4">
                <div class="font-bold text-slate-800"><?php echo htmlspecialchars($c['cargo']); ?></div>
                <div class="text-[11px] text-slate-400"><?php echo htmlspecialchars($c['establecimiento']); ?></div>
                <?php if (!empty($c['cod_nexus']) || !empty($c['cod_airhsp'])): ?>
                  <div class="text-[10px] text-slate-400 font-mono mt-0.5">NEXUS: <?php echo $c['cod_nexus'] ?: '-'; ?> | AIRHSP: <?php echo $c['cod_airhsp'] ?: '-'; ?></div>
                <?php endif; ?>
              </td>
              <td class="py-4 px-4 text-center font-mono font-bold text-slate-700">
                <?php echo htmlspecialchars($c['meta']); ?>
              </td>
              <td class="py-4 px-4 text-center font-mono">
                <div><?php echo $c['fecha_inicio']; ?></div>
                <div class="text-slate-400 text-[10px]">al <?php echo $c['fecha_termino'] ? $c['fecha_termino'] : 'Indeterminado'; ?></div>
              </td>
              <td class="py-4 px-4 text-right font-mono font-bold text-slate-800">
                S/ <?php echo number_format($c['remuneracion_mensual'], 2); ?>
              </td>
              <td class="py-4 px-5 text-center">
                <?php if ($esActivo): ?>
                  <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-extrabold bg-[#008f5d] text-white">
                    VIGENTE
                  </span>
                <?php else: ?>
                  <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold bg-slate-100 text-slate-500">
                    CONCLUIDO
                  </span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- MODAL: REGISTRAR NUEVO CONTRATO -->
  <div id="modalNuevoContrato" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm hidden flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-3xl max-w-xl w-full p-7 shadow-2xl border border-slate-100">
      <div class="flex items-center justify-between pb-3 border-b border-slate-100">
        <div>
          <h3 class="text-base font-bold text-slate-800">Registrar Nuevo Contrato / Adenda</h3>
          <p class="text-xs text-slate-400">El contrato previo pasará a estado "Concluido" automáticamente</p>
        </div>
        <button onclick="document.getElementById('modalNuevoContrato').classList.add('hidden')" class="w-8 h-8 rounded-full flex items-center justify-center text-slate-400 hover:text-slate-700 text-xl">&times;</button>
      </div>

      <form method="POST" class="mt-4 space-y-3.5 text-xs font-semibold">
        <input type="hidden" name="nuevo_contrato" value="1">

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-slate-500 mb-1">Tipo de Acto Laboral *</label>
            <select name="tipo_contrato" class="w-full px-3 py-2 bg-slate-50 border rounded-xl outline-none">
              <option value="CONTRATO CAS">Nuevo Contrato CAS</option>
              <option value="ADENDA">Adenda Modificatoria</option>
              <option value="ENCARGATURA">Encargatura de Puesto</option>
            </select>
          </div>
          <div>
            <label class="block text-slate-500 mb-1">N° de Contrato / Adenda *</label>
            <input type="text" name="num_contrato" required placeholder="Ej. CAS-045-2026-UGELV" class="w-full px-3 py-2 bg-slate-50 border rounded-xl outline-none font-mono">
          </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-slate-500 mb-1">Nuevo Cargo *</label>
            <input type="text" name="cargo" required placeholder="Ej. Coordinador de Innovación" class="w-full px-3 py-2 bg-slate-50 border rounded-xl outline-none">
          </div>
          <div>
            <label class="block text-slate-500 mb-1">Nueva Remuneración Mensual (S/) *</label>
            <input type="number" step="0.01" name="remuneracion_mensual" required placeholder="Ej. 3500.00" class="w-full px-3 py-2 bg-slate-50 border rounded-xl outline-none font-mono font-bold text-slate-800">
          </div>
        </div>

        <div class="grid grid-cols-3 gap-3">
          <div>
            <label class="block text-slate-500 mb-1">Meta *</label>
            <input type="text" name="meta" value="0131" required class="w-full px-3 py-2 bg-slate-50 border rounded-xl outline-none font-mono">
          </div>
          <div>
            <label class="block text-slate-500 mb-1">Cód. Plaza NEXUS</label>
            <input type="text" name="cod_nexus" placeholder="11223344" class="w-full px-3 py-2 bg-slate-50 border rounded-xl outline-none font-mono">
          </div>
          <div>
            <label class="block text-slate-500 mb-1">Cód. AIRHSP</label>
            <input type="text" name="cod_airhsp" placeholder="000564" class="w-full px-3 py-2 bg-slate-50 border rounded-xl outline-none font-mono">
          </div>
        </div>

        <div>
          <label class="block text-slate-500 mb-1">Centro de Trabajo / Establecimiento *</label>
          <input type="text" name="establecimiento" value="SEDE UGEL VENTANILLA" required class="w-full px-3 py-2 bg-slate-50 border rounded-xl outline-none">
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-slate-500 mb-1">Fecha de Inicio *</label>
            <input type="date" name="fecha_inicio" required class="w-full px-3 py-2 bg-slate-50 border rounded-xl outline-none">
          </div>
          <div>
            <label class="block text-slate-500 mb-1">Fecha de Término (Opcional)</label>
            <input type="date" name="fecha_termino" class="w-full px-3 py-2 bg-slate-50 border rounded-xl outline-none">
          </div>
        </div>

        <div>
          <label class="block text-slate-500 mb-1">Observaciones / Motivo del Cambio</label>
          <input type="text" name="observaciones" placeholder="Ej. Ganador de convocatoria CAS N° 003-2026" class="w-full px-3 py-2 bg-slate-50 border rounded-xl outline-none">
        </div>

        <div class="flex justify-end gap-2 pt-3 border-t">
          <button type="button" onclick="document.getElementById('modalNuevoContrato').classList.add('hidden')" class="px-4 py-2 text-slate-600 rounded-xl">Cancelar</button>
          <button type="submit" class="px-5 py-2 bg-[#008f5d] hover:bg-[#007a4f] text-white rounded-xl font-bold shadow-md">Guardar y Activar Contrato</button>
        </div>
      </form>
    </div>
  </div>

</body>
</html>