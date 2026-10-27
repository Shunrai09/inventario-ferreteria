<?php
// Cabecera HTML común. Requiere app/bootstrap.php cargado.
// Nada antes de <!DOCTYPE html>: cualquier espacio impediría las redirecciones con header().
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?= url('assets/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/css/fontawesome.min.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/css/app.css') ?>">
    <style>:root { <?= variablesTema() ?>}</style>
    <link rel="icon" type="image/svg+xml" href="<?= url('assets/img/favicon.svg') ?>">
    <!-- Bootstrap se carga una sola vez (el bundle ya incluye Popper para los menús) -->
    <script src="<?= url('assets/js/bootstrap.bundle.min.js') ?>"></script>
    <title><?= htmlspecialchars(EMPRESA_NOMBRE . ' | ' . EMPRESA_SISTEMA) ?></title>
</head>
<body>
