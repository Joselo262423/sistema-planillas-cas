<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'conexion.php';

$mensaje = '';
$error = '';
$registrosImportados = 0;
$registrosActualizados = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo_csv'])) {
    $archivo = $_FILES['archivo_csv'];

    if ($archivo['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));

        if ($ext === 'csv' || $ext === 'txt') {
            $handle = fopen($archivo['tmp_name'], 'r');
            
            // Omitir cabecera
            $cabecera = fgetcsv($handle, 1500, ";");
            if (!$cabecera) {
                $handle = fopen($archivo['tmp_name'], 'r');
                fgetcsv($handle, 1500, ","); // Intentar con coma si falla punto y coma
            }

            $pdo->beginTransaction();
            try {
                while (($fila = fgetcsv($handle, 1500, ";")) !== FALSE) {
                    if (count($fila) < 15) {
                        $fila = fgetcsv($handle, 1500, ",");
                    }

                    if (count($fila) >= 20) {
                        $dni               = trim($fila[0]);
                        $sexo              = strtoupper(trim($fila[1])) === 'F' ? 'F' : 'M';
                        $paterno           = strtoupper(trim($fila[2]));
                        $materno           = strtoupper(trim($fila[3]));
                        $nombres           = strtoupper(trim($fila[4]));
                        $fecha_nacimiento  = !empty($fila[5]) ? trim($fila[5]) : null;
                        
                        $num_contrato      = trim($fila[6]);
                        $establecimiento   = !empty($fila[7]) ? strtoupper(trim($fila[7])) : 'SEDE UGEL VENTANILLA';
                        $fecha_inicio      = !empty($fila[8]) ? trim($fila[8]) : null;
                        $fecha_termino     = !empty($fila[9]) ? trim($fila[9]) : null;
                        $cargo             = strtoupper(trim($fila[10]));
                        $condicion_laboral = !empty($fila[11]) ? strtoupper(trim($fila[11])) : 'CONTRATO A PLAZO DETERMINADO';
                        $meta              = !empty($fila[12]) ? trim($fila[12]) : '0131';
                        $cod_nexus         = trim($fila[13]);
                        $cod_airhsp        = trim($fila[14]);
                        $id_regimen        = intval(trim($fila[15])) > 0 ? intval(trim($fila[15])) : 1;
                        $cuenta_ahorros    = trim($fila[16]);
                        $dias_laborados    = intval(trim($fila[17])) > 0 ? intval(trim($fila[17])) : 30;
                        $estado            = !empty($fila[18]) ? strtolower(trim($fila[18])) : 'activo';
                        $remuneracion      = floatval(str_replace(',', '.', trim($fila[19])));

                        if (!empty($dni) && strlen($dni) === 8 && !empty($paterno) && !empty($nombres) && $remuneracion > 0) {
                            // Verificar si ya existe por DNI para actualizar o insertar
                            $stmtChk = $pdo->prepare("SELECT id FROM trabajadores_cas WHERE dni = ?");
                            $stmtChk->execute(array($dni));
                            $existente = $stmtChk->fetch();

                            if ($existente) {
                                // Actualizar si ya existe
                                $sqlUpd = "UPDATE trabajadores_cas SET 
                                    sexo = ?, apellido_paterno = ?, apellido_materno = ?, nombres = ?, fecha_nacimiento = ?,
                                    num_contrato = ?, establecimiento = ?, fecha_inicio = ?, fecha_termino = ?, cargo = ?,
                                    condicion_laboral = ?, meta = ?, cod_nexus = ?, cod_airhsp = ?, id_regimen = ?,
                                    cuenta_ahorros = ?, dias_laborados = ?, estado = ?, remuneracion_mensual = ?
                                    WHERE dni = ?";
                                $stmtUpd = $pdo->prepare($sqlUpd);
                                $stmtUpd->execute(array(
                                    $sexo, $paterno, $materno, $nombres, $fecha_nacimiento,
                                    $num_contrato, $establecimiento, $fecha_inicio, $fecha_termino, $cargo,
                                    $condicion_laboral, $meta, $cod_nexus, $cod_airhsp, $id_regimen,
                                    $cuenta_ahorros, $dias_laborados, $estado, $remuneracion,
                                    $dni
                                ));
                                $registrosActualizados++;
                            } else {
                                // Insertar nuevo
                                $sqlIns = "INSERT INTO trabajadores_cas 
                                    (dni, sexo, apellido_paterno, apellido_materno, nombres, fecha_nacimiento,
                                     num_contrato, establecimiento, fecha_inicio, fecha_termino, cargo, condicion_laboral,
                                     meta, cod_nexus, cod_airhsp, id_regimen, cuenta_ahorros, dias_laborados, estado, remuneracion_mensual)
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                                $stmtIns = $pdo->prepare($sqlIns);
                                $stmtIns->execute(array(
                                    $dni, $sexo, $paterno, $materno, $nombres, $fecha_nacimiento,
                                    $num_contrato, $establecimiento, $fecha_inicio, $fecha_termino, $cargo, $condicion_laboral,
                                    $meta, $cod_nexus, $cod_airhsp, $id_regimen, $cuenta_ahorros, $dias_laborados, $estado, $remuneracion
                                ));
                                $registrosImportados++;
                            }
                        }
                    }
                }
                fclose($handle);
                $pdo->commit();
                $mensaje = "¡Importación masiva completada! Nuevos registrados: <b>$registrosImportados</b> | Actualizados: <b>$registrosActualizados</b>.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Error al procesar el archivo: " . $e->getMessage();
            }
        } else {
            $error = "Por favor, suba un archivo en formato CSV válido.";
        }
    } else {
        $error = "Ocurrió un error al subir el archivo.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Carga Masiva Completa - UGEL Ventanilla</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style> body { font-family: 'Plus Jakarta Sans', sans-serif; } </style>
</head>
<body class="bg-[#f0f4f8] text-slate-700 min-h-screen flex antialiased">

  <aside class="w-64 bg-[#0c1526] text-slate-300 flex flex-col flex-shrink-0 h-screen sticky top-0">
    <div class="px-6 py-6 border-b border-slate-800/60 flex items-center gap-3.5">
      <div class="w-10 h-10 rounded-xl bg-[#008f5d]/20 border border-[#00a86b]/40 flex items-center justify-center text-[#00c982]">
        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5" /></svg>
      </div>
      <div>
        <h1 class="text-base font-extrabold text-white tracking-tight">Planillas CAS</h1>
        <p class="text-[11px] text-slate-400">D.L. 1057 - Perú</p>
      </div>
    </div>
    <nav class="flex-1 px-4 py-6 space-y-1.5 text-sm font-semibold">
      <a href="index.php" class="flex items-center gap-3.5 px-3.5 py-3 rounded-xl bg-gradient-to-r from-[#17243c] to-[#1c2c49] text-white font-bold shadow-md">
        <span>← Volver a Personal CAS</span>
      </a>
    </nav>
  </aside>

  <main class="flex-1 p-8 lg:p-10 overflow-y-auto">
    <div class="max-w-4xl mx-auto">
      <div class="mb-6">
        <h2 class="text-2xl font-extrabold text-[#111827]">Importación Masiva (Datos Personales y Laborales Completos)</h2>
        <p class="text-xs text-slate-500 mt-1">Cargue todos los campos de la ficha del servidor mediante un archivo CSV separado por punto y coma (;).</p>
      </div>

      <?php if ($mensaje): ?>
        <div class="mb-5 p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-2xl text-xs font-semibold">
          <?php echo $mensaje; ?>
        </div>
      <?php endif; ?>

      <?php if ($error): ?>
        <div class="mb-5 p-4 bg-rose-50 border border-rose-200 text-rose-700 rounded-2xl text-xs font-semibold">
          <?php echo $error; ?>
        </div>
      <?php endif; ?>

      <div class="bg-white rounded-3xl p-8 border border-slate-200 shadow-sm space-y-6">
        <div>
          <h3 class="font-bold text-slate-800 text-sm mb-1">Estructura obligatoria de las 20 columnas del CSV:</h3>
          <p class="text-xs text-slate-400 mb-3">Orden exacto requerido en cada fila:</p>
          <div class="p-3 bg-slate-50 border rounded-xl font-mono text-[10.5px] text-slate-600 overflow-x-auto leading-relaxed">
            <b>1. DNI</b> | <b>2. SEXO</b> (M/F) | <b>3. APELLIDO_PATERNO</b> | <b>4. APELLIDO_MATERNO</b> | <b>5. NOMBRES</b> | <b>6. FECHA_NACIMIENTO</b> (AAAA-MM-DD) | <b>7. NUM_CONTRATO</b> | <b>8. ESTABLECIMIENTO</b> | <b>9. FECHA_INICIO</b> (AAAA-MM-DD) | <b>10. FECHA_TERMINO</b> (AAAA-MM-DD) | <b>11. CARGO</b> | <b>12. CONDICION_LABORAL</b> | <b>13. META</b> | <b>14. COD_NEXUS</b> | <b>15. COD_AIRHSP</b> | <b>16. ID_REGIMEN</b> (1 al 9) | <b>17. CUENTA_AHORROS</b> | <b>18. DIAS_LABORADOS</b> | <b>19. ESTADO</b> (activo/cesado) | <b>20. REMUNERACION</b>
          </div>
          <div class="mt-3">
            <a href="data:text/csv;charset=utf-8,DNI%3BSEXO%3BAPELLIDO_PATERNO%3BAPELLIDO_MATERNO%3BNOMBRES%3BFECHA_NACIMIENTO%3BNUM_CONTRATO%3BESTABLECIMIENTO%3BFECHA_INICIO%3BFECHA_TERMINO%3BCARGO%3BCONDICION_LABORAL%3BMETA%3BCOD_NEXUS%3BCOD_AIRHSP%3BID_REGIMEN%3BCUENTA_AHORROS%3BDIAS_LABORADOS%3BESTADO%3BREMUNERACION%0A73111169%3BM%3BDIAZ%3BHINOSTROZA%3BNOAM%20EMILIO%3B1993-08-19%3BCAS-045-2026%3BSEDE%20UGEL%20VENTANILLA%3B2026-01-01%3B2026-12-31%3BESPECIALISTA%3BCONTRATO%20A%20PLAZO%20DETERMINADO%3B0131%3B11223344%3B000564%3B3%3B04-000-000000%3B30%3Bactivo%3B3200.00" 
               download="plantilla_personal_cas_completa.csv" 
               class="inline-flex items-center gap-2 px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition-all">
              ↓ Descargar Plantilla CSV Completa de Ejemplo
            </a>
          </div>
        </div>

        <hr class="border-slate-100">

        <form action="importar_personal.php" method="POST" enctype="multipart/form-data" class="space-y-4">
          <div>
            <h3 class="font-bold text-slate-800 text-sm mb-1">Subir archivo completado</h3>
            <label class="block text-xs text-slate-400 mb-2">Seleccione el archivo CSV con los 20 campos completos:</label>
            <input type="file" name="archivo_csv" accept=".csv, .txt" required class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold">
          </div>

          <div class="pt-3">
            <button type="submit" class="w-full py-3 bg-[#008f5d] hover:bg-[#007a4f] text-white rounded-xl font-bold text-xs shadow-md transition-all">
              Procesar Carga Masiva Completa
            </button>
          </div>
        </form>
      </div>
    </div>
  </main>
</body>
</html>