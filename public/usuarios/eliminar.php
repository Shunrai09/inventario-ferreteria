<?php
require_once __DIR__ . "/../../app/bootstrap.php";
requerirAdministrador();

$id = $_GET['id'] ?? null;
if (!$id) {
    redirigir("usuarios/index.php");
}

if ($id == $_SESSION['idUsuario']) {
    $mensaje = ['tipo' => 'danger', 'texto' => 'No puedes eliminar tu propio usuario.'];
} elseif (!obtenerUsuarioPorId($id)) {
    $mensaje = ['tipo' => 'danger', 'texto' => 'El usuario no existe.'];
} elseif (($movimientos = contarMovimientosDeUsuario($id)) > 0) {
    // Los reportes muestran cada venta o compra con su usuario; borrarlo las haría desaparecer
    $mensaje = ['tipo' => 'danger', 'texto' => "No se puede eliminar: tiene $movimientos venta(s) o compra(s) registradas a su nombre y desaparecerían de los reportes."];
} elseif (eliminarUsuario($id)) {
    $mensaje = ['tipo' => 'success', 'texto' => 'Usuario eliminado.'];
} else {
    $mensaje = ['tipo' => 'danger', 'texto' => 'Error al eliminar el usuario.'];
}

$_SESSION['mensaje_usuario'] = $mensaje;
redirigir("usuarios/index.php");
