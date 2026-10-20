<?php
require_once 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $periodo_id    = intval($_POST['periodo_id'] ?? 1);
    $trabajador_id = intval($_POST['trabajador_id'] ?? 0);

    // Obtener la remuneración del trabajador para calcular el valor tiempo
    $stmt = $pdo->prepare("SELECT remuneracion_mensual FROM trabajadores_cas WHERE id = ?");
    $stmt->execute([$trabajador_id]);
    $trabajador = $stmt->fetch();

    if ($trabajador) {
        $sueldo = floatval($trabajador['remuneracion_mensual']);
        $valorDia = $sueldo / 30;
        $valorHora = $valorDia / 8;
        $valorMinuto = $valorHora / 60;

        // Variables de Asistencia
        $dias_tard    = intval($_POST['dias_tardanza'] ?? 0);
        $horas_tard   = intval($_POST['horas_tardanza'] ?? 0);
        $minutos_tard = intval($_POST['minutos_tardanza'] ?? 0);

        $montoAsistencia = round(($dias_tard * $valorDia) + ($horas_tard * $valorHora) + ($minutos_tard * $valorMinuto), 2);

        // Terceros
        $montoTerceros   = floatval($_POST['descuento_terceros'] ?? 0);
        $detalleTerceros = trim($_POST['detalle_terceros'] ?? '');

        // Judiciales
        $porcJudicial    = floatval($_POST['porcentaje_judicial'] ?? 0);
        $montoFijoJud    = floatval($_POST['monto_judicial'] ?? 0);
        $beneficiario    = trim($_POST['beneficiario_judicial'] ?? '');
        $expediente      = trim($_POST['num_expediente'] ?? '');

        // Si tiene porcentaje judicial, se calcula sobre el haber deducido asistencia
        $montoJudicialCalculado = $montoFijoJud;
        if ($porcJudicial > 0) {
            $baseCalculo = max(0, $sueldo - $montoAsistencia);
            $montoJudicialCalculado = round($baseCalculo * ($porcJudicial / 100), 2);
        }

        $totalDescuentos = $montoAsistencia + $montoTerceros + $montoJudicialCalculado;

        // Guardar o Actualizar (UPSERT)
        $sql = "INSERT INTO descuentos_trabajador 
                (periodo_id, trabajador_id, dias_tardanza, horas_tardanza, minutos_tardanza, monto_descuento_asistencia,
                 descuento_terceros, detalle_terceros, porcentaje_judicial, monto_judicial, beneficiario_judicial, num_expediente, total_descuentos_variables)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    dias_tardanza = VALUES(dias_tardanza),
                    horas_tardanza = VALUES(horas_tardanza),
                    minutos_tardanza = VALUES(minutos_tardanza),
                    monto_descuento_asistencia = VALUES(monto_descuento_asistencia),
                    descuento_terceros = VALUES(descuento_terceros),
                    detalle_terceros = VALUES(detalle_terceros),
                    porcentaje_judicial = VALUES(porcentaje_judicial),
                    monto_judicial = VALUES(monto_judicial),
                    beneficiario_judicial = VALUES(beneficiario_judicial),
                    num_expediente = VALUES(num_expediente),
                    total_descuentos_variables = VALUES(total_descuentos_variables)";

        $stmtInsert = $pdo->prepare($sql);
        $stmtInsert->execute([
            $periodo_id, $trabajador_id, $dias_tard, $horas_tard, $minutos_tard, $montoAsistencia,
            $montoTerceros, $detalleTerceros, $porcJudicial, $montoJudicialCalculado, $beneficiario, $expediente, $totalDescuentos
        ]);
    }
}

header('Location: descuentos.php?msg=guardado');
exit;