<?php
require_once 'conexion.php';

$accion = isset($_REQUEST['accion']) ? $_REQUEST['accion'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($accion === 'guardar' || $accion === 'editar')) {
    $id = !empty($_POST['id']) ? intval($_POST['id']) : null;

    // 1. Datos Personales
    $dni              = isset($_POST['dni']) ? trim($_POST['dni']) : '';
    $ap_paterno       = isset($_POST['apellido_paterno']) ? trim($_POST['apellido_paterno']) : '';
    $ap_materno       = isset($_POST['apellido_materno']) ? trim($_POST['apellido_materno']) : '';
    $nombres          = isset($_POST['nombres']) ? trim($_POST['nombres']) : '';
    $fecha_nacimiento = !empty($_POST['fecha_nacimiento']) ? $_POST['fecha_nacimiento'] : null;
    $sexo             = isset($_POST['sexo']) ? trim($_POST['sexo']) : 'M';

    // 2. Datos Laborales
    $num_contrato      = isset($_POST['num_contrato']) ? trim($_POST['num_contrato']) : '';
    $fecha_inicio      = !empty($_POST['fecha_inicio']) ? $_POST['fecha_inicio'] : null;
    $fecha_termino     = !empty($_POST['fecha_termino']) ? $_POST['fecha_termino'] : null;
    $establecimiento   = isset($_POST['establecimiento']) ? trim($_POST['establecimiento']) : 'SEDE UGEL VENTANILLA';
    $cargo             = isset($_POST['cargo']) ? trim($_POST['cargo']) : '';
    $condicion_laboral = isset($_POST['condicion_laboral']) ? trim($_POST['condicion_laboral']) : 'CONTRATO A PLAZO DETERMINADO';
    $meta              = isset($_POST['meta']) ? trim($_POST['meta']) : '0131';
    $cod_nexus         = isset($_POST['cod_nexus']) ? trim($_POST['cod_nexus']) : '';
    $cod_airhsp        = isset($_POST['cod_airhsp']) ? trim($_POST['cod_airhsp']) : '';
    $id_regimen        = isset($_POST['id_regimen']) ? intval($_POST['id_regimen']) : 1;
    $cuenta_ahorros    = isset($_POST['cuenta_ahorros']) ? trim($_POST['cuenta_ahorros']) : '';
    $dias_laborados    = isset($_POST['dias_laborados']) ? intval($_POST['dias_laborados']) : 30;
    $estado            = isset($_POST['estado']) ? trim($_POST['estado']) : 'activo';
    $remuneracion      = isset($_POST['remuneracion_mensual']) ? floatval($_POST['remuneracion_mensual']) : 0;

    // 3. Descuentos, Retenciones, Licencias y Reintegros
    $monto_reintegro       = isset($_POST['monto_reintegro']) ? floatval($_POST['monto_reintegro']) : 0.00;
    $motivo_reintegro      = isset($_POST['motivo_reintegro']) ? trim($_POST['motivo_reintegro']) : '';
    $reintegro_afecto      = isset($_POST['reintegro_afecto']) ? 1 : 0;

    $dias_lic_con_goce     = isset($_POST['dias_licencia_con_goce']) ? intval($_POST['dias_licencia_con_goce']) : 0;
    $dias_lic_sin_goce     = isset($_POST['dias_licencia_sin_goce']) ? intval($_POST['dias_licencia_sin_goce']) : 0;
    $motivo_licencia       = isset($_POST['motivo_licencia']) ? trim($_POST['motivo_licencia']) : '';

    $dias_tardanza         = isset($_POST['dias_tardanza']) ? intval($_POST['dias_tardanza']) : 0;
    $horas_tardanza        = isset($_POST['horas_tardanza']) ? intval($_POST['horas_tardanza']) : 0;
    $minutos_tardanza      = isset($_POST['minutos_tardanza']) ? intval($_POST['minutos_tardanza']) : 0;
    
    $descuento_terceros    = isset($_POST['descuento_terceros']) ? floatval($_POST['descuento_terceros']) : 0.00;
    $detalle_terceros      = isset($_POST['detalle_terceros']) ? trim($_POST['detalle_terceros']) : '';
    
    $porcentaje_judicial   = isset($_POST['porcentaje_judicial']) ? floatval($_POST['porcentaje_judicial']) : 0.00;
    $base_calculo_judicial = isset($_POST['base_calculo_judicial']) ? trim($_POST['base_calculo_judicial']) : 'NETO_LEY';
    $monto_judicial        = isset($_POST['monto_judicial']) ? floatval($_POST['monto_judicial']) : 0.00;
    $beneficiario_judicial = isset($_POST['beneficiario_judicial']) ? trim($_POST['beneficiario_judicial']) : '';
    $cuenta_judicial       = isset($_POST['cuenta_judicial']) ? trim($_POST['cuenta_judicial']) : '';
    $num_expediente        = isset($_POST['num_expediente']) ? trim($_POST['num_expediente']) : '';

    $renta_cuarta_aplica   = isset($_POST['renta_cuarta_aplica']) ? 1 : 0;
    $renta_cuarta_monto    = isset($_POST['renta_cuarta_monto']) ? floatval($_POST['renta_cuarta_monto']) : 0.00;
    $suspension_cuarta     = isset($_POST['suspension_cuarta']) ? 1 : 0;
    $num_suspension_1609   = isset($_POST['num_suspension_1609']) ? trim($_POST['num_suspension_1609']) : '';

    if (!empty($dni) && !empty($ap_paterno) && !empty($nombres) && $remuneracion > 0) {
        if ($accion === 'guardar') {
            $sql = "INSERT INTO trabajadores_cas 
                    (dni, apellido_paterno, apellido_materno, nombres, fecha_nacimiento, sexo,
                     num_contrato, fecha_inicio, fecha_termino, establecimiento, cargo, condicion_laboral, meta,
                     cod_nexus, cod_airhsp, id_regimen, cuenta_ahorros, dias_laborados, estado, remuneracion_mensual,
                     monto_reintegro, motivo_reintegro, reintegro_afecto,
                     dias_lic_con_goce, dias_lic_sin_goce, motivo_licencia,
                     dias_tardanza, horas_tardanza, minutos_tardanza, descuento_terceros, detalle_terceros,
                     porcentaje_judicial, base_calculo_judicial, monto_judicial, beneficiario_judicial, cuenta_judicial, num_expediente,
                     renta_cuarta_aplica, renta_cuarta_monto, suspension_cuarta, num_suspension_1609)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array(
                $dni, $ap_paterno, $ap_materno, $nombres, $fecha_nacimiento, $sexo,
                $num_contrato, $fecha_inicio, $fecha_termino, $establecimiento, $cargo, $condicion_laboral, $meta,
                $cod_nexus, $cod_airhsp, $id_regimen, $cuenta_ahorros, $dias_laborados, $estado, $remuneracion,
                $monto_reintegro, $motivo_reintegro, $reintegro_afecto,
                $dias_lic_con_goce, $dias_lic_sin_goce, $motivo_licencia,
                $dias_tardanza, $horas_tardanza, $minutos_tardanza, $descuento_terceros, $detalle_terceros,
                $porcentaje_judicial, $base_calculo_judicial, $monto_judicial, $beneficiario_judicial, $cuenta_judicial, $num_expediente,
                $renta_cuarta_aplica, $renta_cuarta_monto, $suspension_cuarta, $num_suspension_1609
            ));
        } elseif ($accion === 'editar' && $id) {
            $sql = "UPDATE trabajadores_cas SET 
                    dni = ?, apellido_paterno = ?, apellido_materno = ?, nombres = ?, fecha_nacimiento = ?, sexo = ?,
                    num_contrato = ?, fecha_inicio = ?, fecha_termino = ?, establecimiento = ?, cargo = ?, condicion_laboral = ?, meta = ?,
                    cod_nexus = ?, cod_airhsp = ?, id_regimen = ?, cuenta_ahorros = ?, dias_laborados = ?, estado = ?, remuneracion_mensual = ?,
                    monto_reintegro = ?, motivo_reintegro = ?, reintegro_afecto = ?,
                    dias_lic_con_goce = ?, dias_lic_sin_goce = ?, motivo_licencia = ?,
                    dias_tardanza = ?, horas_tardanza = ?, minutos_tardanza = ?, descuento_terceros = ?, detalle_terceros = ?,
                    porcentaje_judicial = ?, base_calculo_judicial = ?, monto_judicial = ?, beneficiario_judicial = ?, cuenta_judicial = ?, num_expediente = ?,
                    renta_cuarta_aplica = ?, renta_cuarta_monto = ?, suspension_cuarta = ?, num_suspension_1609 = ?
                    WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array(
                $dni, $ap_paterno, $ap_materno, $nombres, $fecha_nacimiento, $sexo,
                $num_contrato, $fecha_inicio, $fecha_termino, $establecimiento, $cargo, $condicion_laboral, $meta,
                $cod_nexus, $cod_airhsp, $id_regimen, $cuenta_ahorros, $dias_laborados, $estado, $remuneracion,
                $monto_reintegro, $motivo_reintegro, $reintegro_afecto,
                $dias_lic_con_goce, $dias_lic_sin_goce, $motivo_licencia,
                $dias_tardanza, $horas_tardanza, $minutos_tardanza, $descuento_terceros, $detalle_terceros,
                $porcentaje_judicial, $base_calculo_judicial, $monto_judicial, $beneficiario_judicial, $cuenta_judicial, $num_expediente,
                $renta_cuarta_aplica, $renta_cuarta_monto, $suspension_cuarta, $num_suspension_1609,
                $id
            ));
        }
    }

    header('Location: index.php');
    exit;
}

if ($accion === 'eliminar') {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($id > 0) {
        $stmt = $pdo->prepare("DELETE FROM trabajadores_cas WHERE id = ?");
        $stmt->execute(array($id));
    }
    header('Location: index.php');
    exit;
}

header('Location: index.php');
exit;