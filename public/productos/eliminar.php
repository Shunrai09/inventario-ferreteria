<?php
require_once __DIR__ . "/../../app/bootstrap.php";
requerirAdministrador();

if(!isset($_GET['id'])) {
    echo "Error: ID no proporcionado";
    exit;
}

$id = $_GET['id'];

$tieneCompras = productoConCompras($id);
$tieneVentas = productoConVentas($id);

if($tieneCompras || $tieneVentas) {
    // No se puede eliminar, redirigir con mensaje de error
    $_SESSION['error_eliminacion'] = "No se puede eliminar el producto porque tiene " . 
        ($tieneCompras && $tieneVentas ? "compras y ventas registradas" : 
         ($tieneCompras ? "compras registradas" : "ventas registradas")) . 
        ". Primero debe eliminar todos los movimientos relacionados.";
    header("Location: " . url("productos/index.php"));
    exit;
}

// Si no tiene compras ni ventas, proceder con la eliminación
$resultado = eliminarProducto($id);

if($resultado) {
    header("Location: " . url("productos/index.php"));
} else {
    echo "Error eliminando";
}