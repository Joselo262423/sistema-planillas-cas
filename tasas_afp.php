<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}
require_once 'conexion.php';

$mensaje = '';

// Guardar actualizaciones de tasas
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_tasas'])) {
    foreach ($_POST['tasas'] as $id => $val) {
        $aporte   = floatval($val['aporte']);
        $comision = floatval($val['comision']);
        $seguro   = floatval($val['seguro']);
        
        $stmt = $pdo->prepare("UPDATE comisiones_pensionarias SET aporte_obligatorio = ?, comision_porcentaje = ?, prima_seguro = ? WHERE id = ?");
        $stmt->execute(array($aporte, $comision, $seguro, $id));
    }
    $mensaje = "¡Tasas y comisiones previsionales actualizadas correctamente!";
}

$comisiones = $pdo->query("SELECT * FROM comisiones_pensionarias ORDER BY id ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Comisiones y Tasas AFP - UGEL Ventanilla</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
  <style> body { font-family: 'Plus Jakarta Sans', sans-serif; } </style>
</head>
<body class="bg-[#f0f4f8] text-slate-700 min-h-screen p-8">
  <div class="max-w-4xl mx-auto bg-white p-7 rounded-3xl border border-slate-200/80 shadow-md">
    <div class="flex items-center justify-between pb-5 border-b border-slate-100 mb-6">
      <div>
        <h2 class="text-2xl font-extrabold text-slate-800">Tasas y Comisiones SBS / AFP</h2>
        <p class="text-xs text-slate-400 mt-1">Configuración mensual de Aportes, Comisiones sobre Flujo y Prima de Seguro</p>
      </div>
      <a href="configuracion.php" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-bold text-xs">← Volver</a>
    </div>

    <?php if ($mensaje): ?>
      <div class="mb-5 p-3.5 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl text-xs font-semibold">
        <?php echo $mensaje; ?>
      </div>
    <?php endif; ?>

    <form method="POST">
      <input type="hidden" name="actualizar_tasas" value="1">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead>
            <tr class="text-slate-400 font-bold uppercase text-[11px] border-b">
              <th class="py-3 px-3">Régimen / AFP</th>
              <th class="py-3 px-3 text-center">Aporte Oblig. (%)</th>
              <th class="py-3 px-3 text-center">Comisión Mensual (%)</th>
              <th class="py-3 px-3 text-center">Prima Seguro (%)</th>
              <th class="py-3 px-3 text-center">Total Descuento (%)</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 font-semibold text-slate-700">
            <?php foreach ($comisiones as $c): ?>
            <tr>
              <td class="py-3.5 px-3 font-bold"><?php echo htmlspecialchars($c['nombre_completo']); ?></td>
              <td class="py-2 px-3 text-center">
                <input type="number" step="0.01" min="0" name="tasas[<?php echo $c['id']; ?>][aporte]" value="<?php echo number_format($c['aporte_obligatorio'], 2); ?>" class="w-20 text-center py-1 bg-slate-50 border rounded-lg font-mono">
              </td>
              <td class="py-2 px-3 text-center">
                <input type="number" step="0.01" min="0" name="tasas[<?php echo $c['id']; ?>][comision]" value="<?php echo number_format($c['comision_porcentaje'], 2); ?>" class="w-20 text-center py-1 bg-slate-50 border rounded-lg font-mono">
              </td>
              <td class="py-2 px-3 text-center">
                <input type="number" step="0.01" min="0" name="tasas[<?php echo $c['id']; ?>][seguro]" value="<?php echo number_format($c['prima_seguro'], 2); ?>" class="w-20 text-center py-1 bg-slate-50 border rounded-lg font-mono">
              </td>
              <td class="py-2 px-3 text-center font-mono font-bold text-[#008f5d]">
                <?php echo number_format($c['total_porcentaje'], 2); ?>%
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="flex justify-end mt-6">
        <button type="submit" class="px-6 py-2.5 bg-[#008f5d] hover:bg-[#007a4f] text-white rounded-xl font-bold text-xs shadow-md transition-all">
          Guardar Tasas SBS
        </button>
      </div>
    </form>
  </div>
</body>
</html>