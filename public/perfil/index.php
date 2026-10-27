<?php
require_once __DIR__ . "/../../app/bootstrap.php";
requerirSesion();

include_once VISTAS_DIR . "/encabezado.php";
include_once VISTAS_DIR . "/navbar.php";
$nombreUsuario = $_SESSION['usuario'];
$idUsuario = $_SESSION['idUsuario'];

$cartas = [
    ["titulo" => "Mis ventas hoy", "icono" => "fa fa-calendar-day", "total" => MONEDA.number_format(obtenerTotalVentasHoy($idUsuario), 2), "color" => ""],
    ["titulo" => "Esta semana", "icono" => "fa fa-calendar-week", "total" => MONEDA.number_format(obtenerTotalVentasSemana($idUsuario), 2), "color" => ""],
    ["titulo" => "Este mes", "icono" => "fa fa-calendar-alt", "total" => MONEDA.number_format(obtenerTotalVentasMes($idUsuario), 2), "color" => ""],
    ["titulo" => "Total vendido", "icono" => "fa fa-money-bill", "total" => MONEDA.number_format(obtenerTotalVentas($idUsuario), 2), "color" => ""],
];
?>

<div class="container-xl">
	<div class="card mb-4">
		<div class="card-body d-flex flex-wrap align-items-center gap-3">
			<span class="usuario-avatar" style="width: 56px; height: 56px; font-size: 1.4rem;" aria-hidden="true"><?= htmlspecialchars(mb_substr($nombreUsuario, 0, 1)) ?></span>
			<div class="me-auto">
				<h1 class="titulo-pagina mb-1"><?= htmlspecialchars($nombreUsuario) ?></h1>
				<span class="badge <?= esAdministrador() ? 'bg-primary' : 'bg-secondary' ?>"><?= htmlspecialchars(nombreRol(rolActual())) ?></span>
			</div>
			<a href="<?= url('perfil/cambiar_password.php') ?>" class="btn btn-outline-secondary">
				<i class="fa fa-key"></i>
				Cambiar contraseña
			</a>
		</div>
	</div>

	<h2 class="titulo-seccion">Mis ventas</h2>
	<?php include_once VISTAS_DIR . "/cartas_totales.php"?>
</div>
