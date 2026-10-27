<?php
// Anula una venta (solo administradores): queda registrada como anulada con su motivo y sus productos vuelven al inventario.
// Recibe por POST: id de la venta, motivo y volver (página a la que regresar: el reporte de ventas con sus filtros o el ticket).
require_once __DIR__ . "/../../app/bootstrap.php";
requerirAdministrador();

// Solo se vuelve a páginas del propio sistema (evita redirecciones a otros sitios)
$volver = $_POST['volver'] ?? '';
if(!preg_match('#^(reportes/ventas\.php(\?[^\r\n]*)?|ventas/ticket\.php\?id=\d+)$#', $volver)){
    $volver = 'reportes/ventas.php';
}

// Por GET no se anula nada (un enlace o una vista previa del navegador no deben anular ventas)
if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    redirigir($volver);
}

$venta = obtenerVentaPorId($_POST['id'] ?? 0);
$motivo = trim($_POST['motivo'] ?? '');
$numero = $venta ? str_pad($venta->id, 6, '0', STR_PAD_LEFT) : '';
if(!$venta){
    guardarMensaje('danger', 'La venta no existe.');
} elseif($venta->anulada){
    guardarMensaje('warning', "La venta N° $numero ya estaba anulada.");
} elseif($motivo === ''){
    guardarMensaje('danger', "Escribe el motivo para anular la venta N° $numero.");
} elseif(anularVenta($venta->id, mb_substr($motivo, 0, 255), $_SESSION['idUsuario'])){
    guardarMensaje('success', "Se anuló la venta N° $numero (" . MONEDA . number_format($venta->total, 2) . ', '
        . date('d/m/Y H:i', strtotime($venta->fecha)) . '). Sus productos volvieron al inventario.');
} else {
    guardarMensaje('danger', "No se pudo anular la venta N° $numero.");
}

redirigir($volver);
