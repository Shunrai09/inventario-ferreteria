<?php
require_once __DIR__ . "/../../app/bootstrap.php";
requerirAdministrador();

$id = $_GET['id'];
if (!$id) {
    echo 'No se ha seleccionado el cliente';
    exit;
}

$resultado = eliminarCliente($id);
if(!$resultado){
    echo "Error al eliminar";
    return;
}

header("Location: " . url("clientes/index.php"));
?>