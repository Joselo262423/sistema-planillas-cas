<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'conexion.php';

$accion = isset($_REQUEST['accion']) ? $_REQUEST['accion'] : '';

// 1. ABRIR NUEVO PERIODO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'abrir') {
    $anio     = isset($_POST['anio']) ? intval($_POST['anio']) : 2026;
    $mes      = isset($_POST['mes']) ? intval($_POST['mes']) : 1;
    $dias_mes = isset($_POST['dias_mes']) ? intval($_POST['dias_mes']) : 30;

    $meses = array(
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
    );

    $nombre_mes = isset($meses[$mes]) ? $meses[$mes] : 'Mes';
    $codigo = sprintf('%04d-%02d', $anio, $mes);

    // Regla: No permitir más de un periodo en estado 'abierto'
    $stmtCheck = $pdo->query("SELECT id, codigo FROM periodos WHERE estado = 'abierto' LIMIT 1");
    $abierto = $stmtCheck->fetch();

    if ($abierto) {
        header('Location: periodos.php?error=existe_abierto&cod=' . urlencode($abierto['codigo']));
        exit;
    }

    // Verificar si el periodo ya fue creado anteriormente
    $stmtExiste = $pdo->prepare("SELECT id FROM periodos WHERE codigo = ?");
    $stmtExiste->execute(array($codigo));
    if ($stmtExiste->fetch()) {
        header('Location: periodos.php?error=periodo_duplicado');
        exit;
    }

    $sql = "INSERT INTO periodos (anio, mes, codigo, nombre_mes, dias_mes, estado) VALUES (?, ?, ?, ?, ?, 'abierto')";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array($anio, $mes, $codigo, $nombre_mes, $dias_mes));

    header('Location: periodos.php?exito=creado');
    exit;
}

// 2. CERRAR PERIODO ACTUAL, CONGELAR CIFRAS Y LIMPIAR VARIABLES MENSUALES
if ($accion === 'cerrar') {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    if ($id > 0) {
        // Obtener el resumen real desde la planilla procesada de este periodo (o del padrón activo si no se procesó)
        $stmtRes = $pdo->prepare("SELECT COUNT(*) as total_pers, SUM(neto_a_pagar) as total_monto FROM planilla_cas_mensual WHERE id_periodo = ?");
        $stmtRes->execute(array($id));
        $resumen = $stmtRes->fetch();

        if (!$resumen || intval($resumen['total_pers']) === 0) {
            $resumen = $pdo->query("SELECT COUNT(*) as total_pers, SUM(remuneracion_mensual) as total_monto FROM trabajadores_cas WHERE estado = 'activo'")->fetch();
        }

        $totalPers  = $resumen ? intval($resumen['total_pers']) : 0;
        $totalMonto = $resumen ? floatval($resumen['total_monto']) : 0.00;

        // A. Cerrar el periodo y guardar totales históricos
        $stmtCerrar = $pdo->prepare("UPDATE periodos SET estado = 'cerrado', fecha_cierre = NOW(), total_personal = ?, monto_total = ? WHERE id = ?");
        $stmtCerrar->execute(array($totalPers, $totalMonto, $id));

        // B. LIMPIEZA AUTOMÁTICA DE MOVIMIENTOS DEL MES EN LA FICHA DEL TRABAJADOR
        // Se reinician reintegros, tardanzas, inasistencias y licencias para empezar el siguiente mes en limpio.
        // (Los descuentos judiciales, de terceros y configuración de 4ta categoría se mantienen intactos).
        $sqlLimpieza = "UPDATE trabajadores_cas SET 
                        dias_laborados = 30,
                        dias_tardanza = 0,
                        horas_tardanza = 0,
                        minutos_tardanza = 0,
                        dias_lic_con_goce = 0,
                        dias_lic_sin_goce = 0,
                        motivo_licencia = '',
                        monto_reintegro = 0.00,
                        motivo_reintegro = '',
                        reintegro_afecto = 1";
        $pdo->query($sqlLimpieza);
    }

    header('Location: periodos.php?exito=cerrado_limpio');
    exit;
}

// 3. REABRIR PERIODO PREVIAMENTE CERRADO
if ($accion === 'reabrir') {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    if ($id > 0) {
        // Regla: No permitir reabrir si ya hay otro periodo en estado 'abierto'
        $stmtCheck = $pdo->query("SELECT id, codigo FROM periodos WHERE estado = 'abierto' LIMIT 1");
        $abierto = $stmtCheck->fetch();

        if ($abierto) {
            header('Location: periodos.php?error=existe_abierto&cod=' . urlencode($abierto['codigo']));
            exit;
        }

        // Habilitar nuevamente el periodo
        $stmt = $pdo->prepare("UPDATE periodos SET estado = 'abierto', fecha_cierre = NULL WHERE id = ?");
        $stmt->execute(array($id));
    }

    header('Location: periodos.php?exito=reabierto');
    exit;
}

header('Location: periodos.php');
exit;