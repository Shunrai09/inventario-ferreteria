<?php
require_once __DIR__ . "/../app/bootstrap.php";
requerirSesion();
// El panel muestra totales de compras e inversión: el vendedor entra directo al punto de venta
if(!esAdministrador()){
    redirigir("ventas/index.php");
}

include_once VISTAS_DIR . "/encabezado.php";
include_once VISTAS_DIR . "/navbar.php";

// Obtener estadísticas generales
// Productos distintos registrados (sumar existencias mezclaría metros, kilos y unidades)
$totalProductos = contarProductos();
$ventasRegistradas = obtenerNumeroVentas();

// Estadísticas de ventas generales
$ventasGenerales = [
    ["titulo" => "Total ventas", "total" => MONEDA.number_format(obtenerTotalVentas(), 2), "color" => "#A71D45"],
    ["titulo" => "Ventas hoy", "total" => MONEDA.number_format(obtenerTotalVentasHoy(), 2), "color" => "#2A8D22"],
    ["titulo" => "Ventas semana", "total" => MONEDA.number_format(obtenerTotalVentasSemana(), 2), "color" => "#223D8D"],
    ["titulo" => "Ventas mes", "total" => MONEDA.number_format(obtenerTotalVentasMes(), 2), "color" => "#D55929"],
];

// Estadísticas por tienda (las tiendas se definen en config/config.php).
// Con una sola tienda no se calculan: repetirían las generales.
$estadisticasTiendas = [];
if(hayVariasTiendas()) {
    foreach(TIENDAS as $clave => $tienda) {
        $clave = (string) $clave;
        $estadisticasTiendas[] = [
            "tienda" => $tienda,
            "compras" => obtenerTotalComprasPorClasificacion($clave),
            "productos" => obtenerNumeroProductosPorClasificacion($clave),
            "ventas" => [
                ["titulo" => "Total ventas", "total" => MONEDA.number_format(obtenerTotalVentasPorClasificacion($clave), 2), "color" => $tienda['colores'][0]],
                ["titulo" => "Ventas hoy", "total" => MONEDA.number_format(obtenerTotalVentasHoyPorClasificacion($clave), 2), "color" => $tienda['colores'][1]],
                ["titulo" => "Ventas semana", "total" => MONEDA.number_format(obtenerTotalVentasSemanaPorClasificacion($clave), 2), "color" => $tienda['colores'][2]],
                ["titulo" => "Ventas mes", "total" => MONEDA.number_format(obtenerTotalVentasMesPorClasificacion($clave), 2), "color" => $tienda['colores'][3]],
            ],
        ];
    }
}
// Con varias tiendas caben 4 tarjetas por fila (2 generales + 1 de compras por tienda); con una, 3.
$columnaTarjeta = hayVariasTiendas() ? "col-md-3" : "col-md-4";

$productosMasVendidos = obtenerProductosMasVendidosConClasificacion();

$errorRespaldo = ultimoErrorRespaldo();
$ultimoRespaldo = listarRespaldos()[0] ?? null;
$cajaAbierta = obtenerCajaAbierta();
?>

<div class="container-xl">

	<!-- Bienvenida -->
	<div class="encabezado-pagina">
		<div>
			<h1 class="titulo-pagina">¡Hola, <?= htmlspecialchars($_SESSION['usuario'])?>!</h1>
			<p class="subtitulo-pagina"><?= htmlspecialchars(EMPRESA_NOMBRE) ?> · Resumen del negocio</p>
		</div>
		<div class="text-end">
			<div class="fw-semibold fs-5" id="fecha-hora"></div>
			<small class="text-muted"><?= date('d/m/Y') ?></small>
		</div>
	</div>

	<!-- Avisos: respaldo con problemas o atrasado, y caja que quedó abierta de otro día -->
	<?php if($errorRespaldo): ?>
		<div class="alert alert-warning">
			<i class="fa fa-database"></i> <?= htmlspecialchars($errorRespaldo) ?>
			<a href="<?= url('respaldos/index.php') ?>" class="alert-link">Ver respaldos</a>
		</div>
	<?php elseif(!$ultimoRespaldo || strtotime($ultimoRespaldo->fecha) < strtotime('-2 days')): ?>
		<div class="alert alert-warning">
			<i class="fa fa-database"></i>
			<?= $ultimoRespaldo ? 'El último respaldo de la base es del ' . date('d/m/Y', strtotime($ultimoRespaldo->fecha)) . '.' : 'Todavía no hay ningún respaldo de la base.' ?>
			<a href="<?= url('respaldos/index.php') ?>" class="alert-link">Respaldar ahora</a>
		</div>
	<?php endif; ?>
	<?php if($cajaAbierta && date('Y-m-d', strtotime($cajaAbierta->fechaApertura)) !== HOY): ?>
		<div class="alert alert-warning">
			<i class="fa fa-cash-register"></i>
			La caja sigue abierta desde el <?= date('d/m/Y', strtotime($cajaAbierta->fechaApertura)) ?>.
			<a href="<?= url('caja/index.php') ?>" class="alert-link">Ciérrala</a> para cuadrar el efectivo de ese día.
		</div>
	<?php endif; ?>

	<!-- Ventas: hoy es lo más importante, por eso va destacado -->
	<div class="row g-3 mb-3">
		<div class="col-12 col-sm-6 col-xl-3">
			<div class="indicador indicador--destacado">
				<div class="indicador-icono"><i class="fa fa-cash-register"></i></div>
				<div class="indicador-cuerpo">
					<div class="indicador-etiqueta"><?= $ventasGenerales[1]['titulo'] ?></div>
					<div class="indicador-valor"><?= $ventasGenerales[1]['total'] ?></div>
				</div>
			</div>
		</div>
		<?php foreach([2 => 'fa fa-calendar-week', 3 => 'fa fa-calendar-alt', 0 => 'fa fa-chart-line'] as $indice => $icono): ?>
		<div class="col-12 col-sm-6 col-xl-3">
			<div class="indicador">
				<div class="indicador-icono"><i class="<?= $icono ?>"></i></div>
				<div class="indicador-cuerpo">
					<div class="indicador-etiqueta"><?= $ventasGenerales[$indice]['titulo'] ?></div>
					<div class="indicador-valor"><?= $ventasGenerales[$indice]['total'] ?></div>
				</div>
			</div>
		</div>
		<?php endforeach; ?>
	</div>

	<!-- Inventario y movimientos -->
	<div class="row g-3 mb-4">
		<div class="col-12 col-md">
			<div class="indicador indicador--info">
				<div class="indicador-icono"><i class="fa fa-box"></i></div>
				<div class="indicador-cuerpo">
					<div class="indicador-etiqueta">Total Productos</div>
					<div class="indicador-valor"><?= number_format($totalProductos) ?></div>
					<div class="indicador-nota">
						<?php if(hayVariasTiendas()): ?>
							<?php foreach($estadisticasTiendas as $i => $estadistica): ?>
								<?= $i > 0 ? '· ' : '' ?><?= htmlspecialchars($estadistica['tienda']['nombre']) ?>: <?= $estadistica['productos'] ?>
							<?php endforeach; ?>
						<?php else: ?>
							Productos registrados en el inventario
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>

		<div class="col-12 col-md">
			<div class="indicador">
				<div class="indicador-icono"><i class="fa fa-receipt"></i></div>
				<div class="indicador-cuerpo">
					<div class="indicador-etiqueta">Ventas Registradas</div>
					<div class="indicador-valor"><?= number_format($ventasRegistradas) ?></div>
					<div class="indicador-nota">Total de transacciones realizadas</div>
				</div>
			</div>
		</div>

		<?php if(!hayVariasTiendas()): ?>
		<div class="col-12 col-md">
			<div class="indicador indicador--exito">
				<div class="indicador-icono"><i class="fa fa-truck"></i></div>
				<div class="indicador-cuerpo">
					<div class="indicador-etiqueta">Total Compras</div>
					<div class="indicador-valor"><?= MONEDA ?><?= number_format(obtenerTotalCompras(), 2) ?></div>
					<div class="indicador-nota">Inversión total en mercadería</div>
				</div>
			</div>
		</div>
		<?php endif; ?>

		<?php foreach($estadisticasTiendas as $estadistica): $tienda = $estadistica['tienda']; ?>
		<div class="col-12 col-md">
			<div class="indicador indicador--exito">
				<div class="indicador-icono"><i class="fa fa-store"></i></div>
				<div class="indicador-cuerpo">
					<div class="indicador-etiqueta">Compras <?= htmlspecialchars($tienda['nombre']) ?></div>
					<div class="indicador-valor"><?= MONEDA ?><?= number_format($estadistica['compras'], 2) ?></div>
					<div class="indicador-nota">Inversión total <?= htmlspecialchars($tienda['nombre']) ?></div>
				</div>
			</div>
		</div>
		<?php endforeach; ?>
	</div>

	<!-- Comparativa por tienda -->
	<?php if($estadisticasTiendas): ?>
	<div class="row g-3 mb-4">
		<?php foreach($estadisticasTiendas as $estadistica): $tienda = $estadistica['tienda']; ?>
		<div class="col-lg-6">
			<div class="card h-100">
				<div class="card-header d-flex align-items-center gap-2">
					<span class="badge <?= $tienda['badge'] ?>">&nbsp;</span>
					<i class="fa fa-store text-muted"></i> <?= htmlspecialchars($tienda['nombre']) ?>
				</div>
				<div class="card-body">
					<div class="row g-3">
						<?php foreach($estadistica['ventas'] as $venta): ?>
						<div class="col-6">
							<div class="indicador-etiqueta"><?= $venta['titulo'] ?></div>
							<div class="indicador-valor fs-5"><?= $venta['total'] ?></div>
						</div>
						<?php endforeach; ?>
					</div>
					<div class="indicador-nota mt-3">
						<i class="fa fa-info-circle"></i> Productos registrados: <?= $estadistica['productos'] ?>
					</div>
				</div>
			</div>
		</div>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>

	<div class="row g-4">
		<!-- Productos más vendidos -->
		<div class="col-lg-8">
			<div class="card h-100">
				<div class="card-header">
					<i class="fa fa-trophy text-muted me-1"></i> Top 10 Productos Más Vendidos
				</div>
				<?php if(count($productosMasVendidos) > 0): ?>
				<div class="table-responsive">
					<table class="table table-hover">
						<thead>
							<tr>
								<th width="8%">#</th>
								<th>Producto</th>
								<th class="text-end">Cantidad vendida</th>
								<th class="text-end">Total generado</th>
							</tr>
						</thead>
						<tbody>
							<?php
							$contador = 1;
							foreach($productosMasVendidos as $producto):
								// Determinar clasificación (solo se muestra con varias tiendas)
								$clasificacion = null;
								if(hayVariasTiendas()) {
									$productoInfo = obtenerProductoPorNombre($producto->nombre);
									$clasificacion = $productoInfo ? $productoInfo->clasificacion : null;
									$badgeColor = badgeTienda($clasificacion);
									$badgeText = htmlspecialchars(cortoTienda($clasificacion));
								}
							?>
							<tr>
								<td class="text-muted fw-semibold"><?= $contador ?></td>
								<td>
									<?= htmlspecialchars($producto->nombre)?>
									<?php if($clasificacion): ?>
										<span class="badge <?= $badgeColor ?> ms-2"><?= $badgeText ?></span>
									<?php endif; ?>
								</td>
								<td class="text-end"><?= cantidadConUnidad($producto->unidades, $producto->unidad)?></td>
								<td class="text-end fw-semibold"><?= MONEDA ?><?= number_format($producto->total, 2)?></td>
							</tr>
							<?php
							$contador++;
							endforeach;
							?>
						</tbody>
					</table>
				</div>
				<?php else: ?>
				<div class="estado-vacio">
					<i class="fa fa-trophy"></i>
					<h4>Todavía no hay ventas</h4>
					<p class="mb-0">Aquí verás los productos que más se venden.</p>
				</div>
				<?php endif; ?>
			</div>
		</div>

		<!-- Acciones rápidas -->
		<div class="col-lg-4">
			<h2 class="titulo-seccion">Acciones Rápidas</h2>
			<div class="d-grid gap-2">
				<a href="<?= url('ventas/index.php') ?>" class="acceso-rapido">
					<span class="indicador-icono"><i class="fa fa-cash-register"></i></span>
					<span><span class="acceso-rapido-titulo">Realizar Venta</span><span class="acceso-rapido-texto">Abrir el punto de venta</span></span>
				</a>
				<a href="<?= url('caja/index.php') ?>" class="acceso-rapido">
					<span class="indicador-icono"><i class="fa fa-money-bill-wave"></i></span>
					<span><span class="acceso-rapido-titulo"><?= $cajaAbierta ? 'Cerrar Caja' : 'Abrir Caja' ?></span><span class="acceso-rapido-texto"><?= $cajaAbierta ? 'Cuadrar el efectivo del día' : 'Empezar el día con el monto inicial' ?></span></span>
				</a>
				<a href="<?= url('compras/index.php') ?>" class="acceso-rapido">
					<span class="indicador-icono"><i class="fa fa-truck"></i></span>
					<span><span class="acceso-rapido-titulo">Registrar Compra</span><span class="acceso-rapido-texto">Ingresar mercadería al stock</span></span>
				</a>
				<a href="<?= url('productos/index.php') ?>" class="acceso-rapido">
					<span class="indicador-icono"><i class="fa fa-boxes"></i></span>
					<span><span class="acceso-rapido-titulo">Ver Productos</span><span class="acceso-rapido-texto">Precios, existencias y categorías</span></span>
				</a>
				<a href="<?= url('reportes/ventas.php') ?>" class="acceso-rapido">
					<span class="indicador-icono"><i class="fa fa-chart-line"></i></span>
					<span><span class="acceso-rapido-titulo">Ver Reportes</span><span class="acceso-rapido-texto">Ventas por fecha, usuario y categoría</span></span>
				</a>
			</div>
		</div>
	</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
	// Actualizar la hora cada segundo
	function actualizarHora() {
		const ahora = new Date();
		const opcionesHora = {
			hour: '2-digit',
			minute: '2-digit',
			second: '2-digit'
		};
		const hora = ahora.toLocaleTimeString('es-ES', opcionesHora);
		document.getElementById('fecha-hora').textContent = hora;
	}

	// Inicializar hora
	actualizarHora();
	setInterval(actualizarHora, 1000);
});
</script>
