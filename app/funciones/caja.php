<?php
// Caja: apertura, cierre con arqueo y formas de pago.
// Solo se vende con la caja abierta (cada venta guarda su ventas.idCaja). Al cerrar se compara el efectivo
// esperado (monto inicial + ventas en efectivo - devoluciones en efectivo) con el que se contó.

function nombreFormaPago($clave){
    return FORMAS_PAGO[$clave] ?? ucfirst((string) $clave);
}

function obtenerCajaAbierta(){
    $sentencia = "SELECT cajas.*, usuarios.usuario AS usuarioApertura
    FROM cajas
    INNER JOIN usuarios ON usuarios.id = cajas.idUsuarioApertura
    WHERE cajas.fechaCierre IS NULL
    ORDER BY cajas.id DESC LIMIT 1";
    return select($sentencia)[0] ?? null;
}

function obtenerCajaPorId($id){
    $sentencia = "SELECT cajas.*, abrio.usuario AS usuarioApertura, cerro.usuario AS usuarioCierre
    FROM cajas
    INNER JOIN usuarios abrio ON abrio.id = cajas.idUsuarioApertura
    LEFT JOIN usuarios cerro ON cerro.id = cajas.idUsuarioCierre
    WHERE cajas.id = ?";
    return select($sentencia, [$id])[0] ?? null;
}

function obtenerUltimaCajaCerrada(){
    $sentencia = "SELECT * FROM cajas WHERE fechaCierre IS NOT NULL ORDER BY fechaCierre DESC, id DESC LIMIT 1";
    return select($sentencia)[0] ?? null;
}

// Devuelve false si ya hay una caja abierta.
function abrirCaja($montoInicial, $idUsuario){
    if(obtenerCajaAbierta()) return false;
    $sentencia = "INSERT INTO cajas (fechaApertura, montoInicial, idUsuarioApertura) VALUES (?,?,?)";
    return insertar($sentencia, [date("Y-m-d H:i:s"), $montoInicial, $idUsuario]);
}

// Arqueo de una caja (abierta o cerrada):
// - ventas: las cobradas con esta caja, salvo las que se anularon mientras ella seguía abierta
//   (las anuladas después ya estaban en el cajón al cerrar, así que el cierre no cambia);
// - devoluciones: ventas de otras cajas (o de antes de usar la caja) anuladas con esta caja abierta.
// Devuelve un objeto con formas (clave => nombre, numero, total, devoluciones), totales y efectivo esperado.
function resumenCaja($caja){
    $formas = [];
    foreach(FORMAS_PAGO as $clave => $nombre){
        $formas[$clave] = (object) ['nombre' => $nombre, 'numero' => 0, 'total' => 0.0, 'numeroDevoluciones' => 0, 'devoluciones' => 0.0];
    }
    $agregarForma = function($clave) use (&$formas){
        if(!isset($formas[$clave])){
            $formas[$clave] = (object) ['nombre' => nombreFormaPago($clave), 'numero' => 0, 'total' => 0.0, 'numeroDevoluciones' => 0, 'devoluciones' => 0.0];
        }
    };

    $ventas = select("SELECT formaPago, COUNT(*) AS numero, SUM(total) AS total FROM ventas
        WHERE idCaja = ? AND (anulada = 0 OR idCajaAnulacion IS NULL OR idCajaAnulacion <> ?)
        GROUP BY formaPago", [$caja->id, $caja->id]);
    foreach($ventas as $fila){
        $agregarForma($fila->formaPago);
        $formas[$fila->formaPago]->numero = (int) $fila->numero;
        $formas[$fila->formaPago]->total = (float) $fila->total;
    }

    $devoluciones = select("SELECT formaPago, COUNT(*) AS numero, SUM(total) AS total FROM ventas
        WHERE idCajaAnulacion = ? AND (idCaja IS NULL OR idCaja <> ?)
        GROUP BY formaPago", [$caja->id, $caja->id]);
    foreach($devoluciones as $fila){
        $agregarForma($fila->formaPago);
        $formas[$fila->formaPago]->numeroDevoluciones = (int) $fila->numero;
        $formas[$fila->formaPago]->devoluciones = (float) $fila->total;
    }

    $anuladas = select("SELECT COUNT(*) AS numero, IFNULL(SUM(total),0) AS total FROM ventas
        WHERE idCaja = ? AND anulada = 1 AND idCajaAnulacion = ?", [$caja->id, $caja->id])[0];

    $totalVentas = array_sum(array_column($formas, 'total'));
    $totalDevoluciones = array_sum(array_column($formas, 'devoluciones'));
    $efectivo = $formas['efectivo'] ?? (object) ['total' => 0.0, 'devoluciones' => 0.0];
    return (object) [
        'formas'            => $formas,
        'numeroVentas'      => array_sum(array_column($formas, 'numero')),
        'totalVentas'       => round($totalVentas, 2),
        'totalDevoluciones' => round($totalDevoluciones, 2),
        'numeroAnuladas'    => (int) $anuladas->numero,
        'totalAnuladas'     => (float) $anuladas->total,
        'efectivoEsperado'  => round($caja->montoInicial + $efectivo->total - $efectivo->devoluciones, 2),
    ];
}

// Cierra la caja abierta guardando el arqueo. Devuelve false si ya estaba cerrada.
function cerrarCaja($caja, $efectivoContado, $notas, $idUsuario){
    $resumen = resumenCaja($caja);
    $bd = conectarBaseDatos();
    $cerrar = $bd->prepare("UPDATE cajas SET fechaCierre = ?, idUsuarioCierre = ?, totalVentas = ?, efectivoEsperado = ?, efectivoContado = ?, notas = ?
        WHERE id = ? AND fechaCierre IS NULL");
    $cerrar->execute([date("Y-m-d H:i:s"), $idUsuario, $resumen->totalVentas - $resumen->totalDevoluciones, $resumen->efectivoEsperado,
                      $efectivoContado, $notas, $caja->id]);
    return $cerrar->rowCount() === 1;
}

// Cajas abiertas entre dos fechas (incluidas), de la más reciente a la más antigua.
function obtenerCajas($fechaInicio, $fechaFin){
    $sentencia = "SELECT cajas.*, abrio.usuario AS usuarioApertura, cerro.usuario AS usuarioCierre
    FROM cajas
    INNER JOIN usuarios abrio ON abrio.id = cajas.idUsuarioApertura
    LEFT JOIN usuarios cerro ON cerro.id = cajas.idUsuarioCierre
    WHERE DATE(cajas.fechaApertura) >= ? AND DATE(cajas.fechaApertura) <= ?
    ORDER BY cajas.fechaApertura DESC, cajas.id DESC";
    return select($sentencia, [$fechaInicio, $fechaFin]);
}

// Insignia con la diferencia del arqueo (contado - esperado): "Cuadra", "Sobran S/5.00" o "Faltan S/5.00".
function etiquetaDiferenciaCaja($diferencia){
    if(abs($diferencia) < 0.005) return '<span class="badge bg-success">Cuadra</span>';
    if($diferencia > 0) return '<span class="badge bg-info">Sobran ' . MONEDA . number_format($diferencia, 2) . '</span>';
    return '<span class="badge bg-danger">Faltan ' . MONEDA . number_format(abs($diferencia), 2) . '</span>';
}

// Lee un importe en soles escrito por el usuario (acepta coma decimal). null si no es un número mayor o igual a 0.
function leerImporte($valor){
    $valor = str_replace(',', '.', trim((string) $valor));
    if(!is_numeric($valor) || (float) $valor < 0) return null;
    return round((float) $valor, 2);
}
