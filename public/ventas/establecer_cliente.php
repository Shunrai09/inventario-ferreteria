<?php
// Asigna el cliente de la venta en curso. El cliente es opcional: vacío (o uno que no existe) deja la venta como mostrador.
require_once __DIR__ . "/../../app/bootstrap.php";
requerirSesion();
$idCliente = $_POST['idCliente'] ?? '';
$_SESSION['clienteVenta'] = ($idCliente !== '' && obtenerClientePorId($idCliente)) ? $idCliente : "";
header("Location: " . url("ventas/index.php"));
