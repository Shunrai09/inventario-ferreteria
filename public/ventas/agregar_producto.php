<?php
require_once __DIR__ . "/../../app/bootstrap.php";
requerirSesion();

if(isset($_POST['agregar'])) {
    if(isset($_POST['codigo'])) {
        $codigo = trim($_POST['codigo']);
        $producto = obtenerProductoPorCodigo($codigo);
        
        if(!$producto) {
            $_SESSION['error'] = "No se ha encontrado el producto con código: $codigo";
            header("Location: " . url("ventas/index.php"));
            exit;
        }
        
        $_SESSION['lista'] = agregarProductoALista($producto, $_SESSION['lista']);
        $_SESSION['exito'] = "Producto agregado al carrito";
        header("Location: " . url("ventas/index.php"));
        exit;
    }
}
?>