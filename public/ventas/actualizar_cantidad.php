<?php
require_once __DIR__ . "/../../app/bootstrap.php";
requerirSesion();

if(isset($_POST['indice']) && isset($_SESSION['lista'][$_POST['indice']])) {
    $indice = $_POST['indice'];
    $producto = $_SESSION['lista'][$indice];

    // + y - mueven de 1 en 1 sin pasar de la existencia; el campo acepta decimales si la unidad los admite
    if(isset($_POST['accion'])) {
        if($_POST['accion'] === 'mas' && $producto->cantidad < $producto->existencia) {
            $producto->cantidad = min(round($producto->cantidad + 1, 3), (float) $producto->existencia);
        } elseif($_POST['accion'] === 'menos' && $producto->cantidad > 1) {
            $producto->cantidad = round($producto->cantidad - 1, 3);
        }
    } elseif(isset($_POST['cantidad'])) {
        $cantidad = leerCantidad($_POST['cantidad'], $producto->unidad ?? 'und');
        if($cantidad !== null && $cantidad <= $producto->existencia) {
            $producto->cantidad = $cantidad;
        }
    }
}

header("Location: " . url("ventas/index.php"));
exit();
?>