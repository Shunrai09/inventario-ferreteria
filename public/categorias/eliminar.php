<?php
require_once __DIR__ . "/../../app/bootstrap.php";
requerirAdministrador();

$id = $_GET['id'] ?? null;
if(!$id) {
    redirigir("categorias/index.php");
}

$productos = contarProductosDeCategoria($id);
if($productos > 0) {
    $_SESSION['mensaje_categoria'] = [
        'tipo' => 'danger',
        'texto' => "No se puede eliminar: tiene $productos producto(s). Cámbialos de categoría primero."
    ];
} elseif(eliminarCategoria($id)) {
    $_SESSION['mensaje_categoria'] = ['tipo' => 'success', 'texto' => 'Categoría eliminada.'];
} else {
    $_SESSION['mensaje_categoria'] = ['tipo' => 'danger', 'texto' => 'Error al eliminar la categoría.'];
}

redirigir("categorias/index.php");
