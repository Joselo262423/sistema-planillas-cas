<?php
require_once 'conexion.php';

$periodo_id = 1; // Periodo Setiembre 2026

// Consultar personal y sus descuentos registrados en el periodo
$sql = "SELECT t.id AS trabajador_id, t.dni, t.apellido_paterno, t.apellido_materno, t.nombres, 
               t.cargo, t.remuneracion_mensual,
               d.dias_tardanza, d.horas_tardanza, d.minutos_tardanza, d.monto_descuento_asistencia,
               d.descuento_terceros, d.detalle_terceros,
               d.porcentaje_judicial, d.monto_judicial, d.beneficiario_judicial, d.num_expediente,
               COALESCE(d.total_descuentos_variables, 0) AS total_descuentos
        FROM trabajadores_cas t
        LEFT JOIN descuentos_trabajador d ON t.id = d.trabajador_id AND d.periodo_id = ?
        WHERE t.estado = 'activo'
        ORDER BY t.apellido_paterno ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute(array($periodo_id));
$lista = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Descuentos CAS - UGEL Ventanilla</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-slate-50 text-slate-700 min-h-screen flex">

  <!-- SIDEBAR -->
  <aside class="w-64 bg-white border-r border-slate-200 flex flex-col flex-shrink-0">
    <div class="p-6 border-b border-slate-100">
      <h1 class="text-base font-bold text-slate-800 tracking-tight">Planillas CAS</h1>
      <p class="text-xs text-slate-400 mt-0.5">DL 1057 · UGEL Ventanilla</p>
    </div>
    <nav class="flex-1 px-3 py-4 space-y-1.5 text-sm font-medium">
      <a href="index.php" class="flex items-center gap-3.5 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-slate-50 transition-colors">
        <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
        <span>Personal CAS</span>
      </a>
      <a href="descuentos.php" class="flex items-center gap-3.5 px-3.5 py-2.5 rounded-xl bg-[#0f172a] text-white font-medium shadow-sm">
        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>
        <span>Descuentos y Retenciones</span>
      </a>
    </nav>
  </aside>

  <!-- CONTENIDO -->
  <main class="flex-1 p-8 overflow-y-auto">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h2 class="text-2xl font-bold text-slate-800 tracking-tight">Descuentos y Retenciones</h2>
        <p class="text-sm text-slate-500 mt-1">Periodo: Setiembre 2026 · Asistencias, Judiciales y Terceros</p>
      </div>
    </div>

    <!-- Buscador -->
    <div class="mb-6 max-w-md relative">
      <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-slate-400">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
      </span>
      <input type="text" id="buscador" onkeyup="filtrar()" placeholder="Buscar por DNI o nombres..." 
             class="w-full pl-9 pr-4 py-2 border border-slate-200 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-slate-300">
    </div>

    <!-- Tabla -->
    <div class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-[0_1px_3px_rgba(0,0,0,0.03)]">
      <table class="w-full text-left text-xs" id="tabla">
        <thead>
          <tr class="border-b border-slate-200 text-slate-400 uppercase font-semibold bg-slate-50/50">
            <th class="py-3.5 px-6">DNI</th>
            <th class="py-3.5 px-6">TRABAJADOR</th>
            <th class="py-3.5 px-6 text-center">DSCTO. ASISTENCIA</th>
            <th class="py-3.5 px-6 text-center">TERCEROS</th>
            <th class="py-3.5 px-6 text-center">JUDICIAL</th>
            <th class="py-3.5 px-6 text-center">TOTAL DSCTOS.</th>
            <th class="py-3.5 px-6 text-right">ACCIONES</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 text-slate-600">
          <?php foreach ($lista as $r): 
            $nombreComp = $r['apellido_paterno'] . ' ' . $r['apellido_materno'] . ', ' . $r['nombres'];
            $asistencia = isset($r['monto_descuento_asistencia']) ? floatval($r['monto_descuento_asistencia']) : 0.00;
            $terceros   = isset($r['descuento_terceros']) ? floatval($r['descuento_terceros']) : 0.00;
            $judicial   = isset($r['monto_judicial']) ? floatval($r['monto_judicial']) : 0.00;
            $total      = isset($r['total_descuentos']) ? floatval($r['total_descuentos']) : 0.00;
          ?>
          <tr class="hover:bg-slate-50/80 transition-colors">
            <td class="py-4 px-6 font-mono text-slate-500"><?php echo htmlspecialchars($r['dni']); ?></td>
            <td class="py-4 px-6 font-semibold text-slate-800">
              <?php echo htmlspecialchars($nombreComp); ?>
              <div class="text-[11px] font-normal text-slate-400"><?php echo htmlspecialchars($r['cargo']); ?></div>
            </td>
            <td class="py-4 px-6 text-center font-mono">S/ <?php echo number_format($asistencia, 2); ?></td>
            <td class="py-4 px-6 text-center font-mono">S/ <?php echo number_format($terceros, 2); ?></td>
            <td class="py-4 px-6 text-center font-mono">S/ <?php echo number_format($judicial, 2); ?></td>
            <td class="py-4 px-6 text-center font-mono font-bold text-rose-600">
              S/ <?php echo number_format($total, 2); ?>
            </td>
            <td class="py-4 px-6 text-right">
              <button onclick='abrirModal(<?php echo json_encode($r); ?>)' class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-semibold transition-colors">
                Aplicar Descuentos
              </button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </main>

  <!-- MODAL DE DESCUENTOS -->
  <div id="modalDscto" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm hidden flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl max-w-xl w-full p-6 shadow-xl border border-slate-100">
      <div class="flex items-center justify-between pb-3 border-b border-slate-100">
        <div>
          <h3 class="text-base font-bold text-slate-800" id="mTitulo">Registrar Descuentos</h3>
          <p class="text-xs text-slate-400" id="mSubtitulo"></p>
        </div>
        <button onclick="cerrarModal()" class="text-slate-400 hover:text-slate-600 text-2xl leading-none">&times;</button>
      </div>

      <form action="guardar_descuentos.php" method="POST" class="space-y-4 mt-4 text-xs font-medium">
        <input type="hidden" name="periodo_id" value="1">
        <input type="hidden" name="trabajador_id" id="mTrabajadorId">

        <!-- Inasistencias y tardanzas -->
        <div class="bg-slate-50 p-3 rounded-lg border border-slate-100">
          <span class="block font-bold text-slate-700 mb-2">1. Inasistencias y Tardanzas</span>
          <div class="grid grid-cols-3 gap-2">
            <div>
              <label class="block text-slate-500 mb-1">Días Inasistencia</label>
              <input type="number" name="dias_tardanza" id="mDias" min="0" value="0" class="w-full px-2 py-1.5 border rounded-lg focus:ring-2 focus:ring-slate-300 outline-none">
            </div>
            <div>
              <label class="block text-slate-500 mb-1">Horas Tardanza</label>
              <input type="number" name="horas_tardanza" id="mHoras" min="0" value="0" class="w-full px-2 py-1.5 border rounded-lg focus:ring-2 focus:ring-slate-300 outline-none">
            </div>
            <div>
              <label class="block text-slate-500 mb-1">Minutos Tardanza</label>
              <input type="number" name="minutos_tardanza" id="mMin" min="0" value="0" class="w-full px-2 py-1.5 border rounded-lg focus:ring-2 focus:ring-slate-300 outline-none">
            </div>
          </div>
        </div>

        <!-- Terceros -->
        <div class="bg-slate-50 p-3 rounded-lg border border-slate-100">
          <span class="block font-bold text-slate-700 mb-2">2. Descuentos de Terceros (Convenios / CAFAE)</span>
          <div class="grid grid-cols-2 gap-2">
            <div>
              <label class="block text-slate-500 mb-1">Monto en Soles (S/)</label>
              <input type="number" step="0.01" name="descuento_terceros" id="mTercerosMonto" value="0.00" class="w-full px-2 py-1.5 border rounded-lg focus:ring-2 focus:ring-slate-300 outline-none">
            </div>
            <div>
              <label class="block text-slate-500 mb-1">Detalle / Concepto</label>
              <input type="text" name="detalle_terceros" id="mTercerosDetalle" placeholder="Ej. SUBCAFAE" class="w-full px-2 py-1.5 border rounded-lg focus:ring-2 focus:ring-slate-300 outline-none">
            </div>
          </div>
        </div>

        <!-- Judiciales -->
        <div class="bg-slate-50 p-3 rounded-lg border border-slate-100">
          <span class="block font-bold text-slate-700 mb-2">3. Retención Judicial (Alimentos)</span>
          <div class="grid grid-cols-2 gap-2">
            <div>
              <label class="block text-slate-500 mb-1">% de Descuento (Ej. 20%)</label>
              <input type="number" step="0.01" name="porcentaje_judicial" id="mJudicialPorc" placeholder="0.00" class="w-full px-2 py-1.5 border rounded-lg focus:ring-2 focus:ring-slate-300 outline-none">
            </div>
            <div>
              <label class="block text-slate-500 mb-1">O Monto Fijo (S/)</label>
              <input type="number" step="0.01" name="monto_judicial" id="mJudicialMonto" placeholder="0.00" class="w-full px-2 py-1.5 border rounded-lg focus:ring-2 focus:ring-slate-300 outline-none">
            </div>
            <div>
              <label class="block text-slate-500 mb-1">Beneficiario</label>
              <input type="text" name="beneficiario_judicial" id="mBeneficiario" placeholder="Nombre demandante" class="w-full px-2 py-1.5 border rounded-lg focus:ring-2 focus:ring-slate-300 outline-none">
            </div>
            <div>
              <label class="block text-slate-500 mb-1">N° Expediente / Juzgado</label>
              <input type="text" name="num_expediente" id="mExpediente" placeholder="Exp. 0123-2024" class="w-full px-2 py-1.5 border rounded-lg focus:ring-2 focus:ring-slate-300 outline-none">
            </div>
          </div>
        </div>

        <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
          <button type="button" onclick="cerrarModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-lg">Cancelar</button>
          <button type="submit" class="px-5 py-2 bg-[#008f5d] hover:bg-[#00784e] text-white rounded-lg font-medium shadow-sm">Guardar Descuentos</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    const modal = document.getElementById('modalDscto');

    function abrirModal(data) {
      document.getElementById('mTrabajadorId').value = data.trabajador_id;
      document.getElementById('mTitulo').innerText = 'Descuentos: ' + data.apellido_paterno + ' ' + data.nombres;
      document.getElementById('mSubtitulo').innerText = 'DNI: ' + data.dni + ' | Sueldo: S/ ' + parseFloat(data.remuneracion_mensual).toFixed(2);

      document.getElementById('mDias').value = data.dias_tardanza || 0;
      document.getElementById('mHoras').value = data.horas_tardanza || 0;
      document.getElementById('mMin').value = data.minutos_tardanza || 0;

      document.getElementById('mTercerosMonto').value = data.descuento_terceros || '0.00';
      document.getElementById('mTercerosDetalle').value = data.detalle_terceros || '';

      document.getElementById('mJudicialPorc').value = data.porcentaje_judicial || '0.00';
      document.getElementById('mJudicialMonto').value = data.monto_judicial || '0.00';
      document.getElementById('mBeneficiario').value = data.beneficiario_judicial || '';
      document.getElementById('mExpediente').value = data.num_expediente || '';

      modal.classList.remove('hidden');
    }

    function cerrarModal() {
      modal.classList.add('hidden');
    }

    function filtrar() {
      const q = document.getElementById('buscador').value.toLowerCase();
      document.querySelectorAll('#tabla tbody tr').forEach(tr => {
        tr.style.display = tr.innerText.toLowerCase().includes(q) ? '' : 'none';
      });
    }
  </script>
</body>
</html>