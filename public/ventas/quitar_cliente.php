<?php
require_once __DIR__ . "/../../app/bootstrap.php";
requerirSesion();
$_SESSION['clienteVenta'] = "";
header("Location: " . url("ventas/index.php"));
?>