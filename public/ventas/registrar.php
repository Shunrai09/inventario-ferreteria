<?php
// Registra la venta del carrito con su forma de pago. Recibe por POST: formaPago y pagoCon (efectivo entregado, opcional).
// Solo con la caja abierta; por GET no registra nada (un enlace o una vista previa no deben vender).
require_once __DIR__ . "/../../app/bootstrap.php";
requerirSesion();

if($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['lista'])) {
    redirigir("ventas/index.php");
}

$caja = obtenerCajaAbierta();
if(!$caja) {
    guardarMensaje('warning', 'La caja está cerrada. Ábrela para poder cobrar la venta.');
    redirigir("ventas/index.php");
}

$productos = $_SESSION['lista'];
$total = round(calcularTotalLista($productos), 2);
// Si no hay cliente, se registra como venta de MOSTRADOR
$idCliente = !empty($_SESSION['clienteVenta']) ? $_SESSION['clienteVenta'] : null;

$formaPago = $_POST['formaPago'] ?? 'efectivo';
if(!isset(FORMAS_PAGO[$formaPago])) {
    guardarMensaje('danger', 'Elige una forma de pago válida.');
    redirigir("ventas/index.php");
}

// "Paga con" solo vale para efectivo y debe alcanzar para el total
$pagoCon = null;
if($formaPago === 'efectivo' && trim($_POST['pagoCon'] ?? '') !== '') {
    $pagoCon = leerImporte($_POST['pagoCon']);
    if($pagoCon === null || $pagoCon < $total) {
        guardarMensaje('danger', 'El monto con el que paga el cliente no alcanza para el total de ' . MONEDA . number_format($total, 2) . '.');
        redirigir("ventas/index.php");
    }
}

$idVenta = registrarVenta($productos, $_SESSION['idUsuario'], $idCliente, $total, $formaPago, $pagoCon, $caja->id);

// Limpiar la sesión después de registrar
$_SESSION['lista'] = [];
$_SESSION['clienteVenta'] = null;

// Se abre el ticket de la venta, listo para imprimir
redirigir("ventas/ticket.php?id=" . $idVenta . "&nueva=1");
