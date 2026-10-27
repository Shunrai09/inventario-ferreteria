<?php
require_once __DIR__ . "/../../app/bootstrap.php";
requerirSesion();
$id = $_GET['id'];
$productos = $_SESSION['lista'];

foreach ($productos as $key => $producto) {
	if($producto->id == $id){
		unset($productos[$key]);
	}
}

$_SESSION['lista'] = $productos;
header("Location: " . url("ventas/index.php"));
?>