<?php
require_once __DIR__ . "/../../app/bootstrap.php";

// Incluye costos e historial de compras: solo administradores
if(empty($_SESSION['usuario']) || !esAdministrador()) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

if(!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID de producto no especificado']);
    exit;
}

$idProducto = $_GET['id'];

// Obtener información del producto
$producto = obtenerProductoPorId($idProducto);

// Obtener compras del producto
$compras = [];
$resultadoCompras = obtenerComprasDeProducto($idProducto);
foreach($resultadoCompras as $compra) {
    $compras[] = [
        'idCompra' => $compra->idCompra,
        'cantidad' => $compra->cantidad,
        'precio' => $compra->precio,
        'fecha' => $compra->fecha,
        'usuario' => $compra->usuario
    ];
}

// Obtener ventas del producto
$ventas = [];
$resultadoVentas = obtenerVentasDeProducto($idProducto);
foreach($resultadoVentas as $venta) {
    $ventas[] = [
        'idVenta' => $venta->idVenta,
        'cantidad' => $venta->cantidad,
        'precio' => $venta->precio,
        'fecha' => $venta->fecha,
        'cliente' => $venta->cliente,
        'anulada' => (bool) $venta->anulada
    ];
}

echo json_encode([
    'success' => true,
    'producto' => $producto,
    'compras' => $compras,
    'ventas' => $ventas,
    'ajustes' => obtenerAjustesDeProducto($idProducto)
]);