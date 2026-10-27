<?php
require_once __DIR__ . "/../../app/bootstrap.php";
requerirAdministrador();

// Anular varias ventas (antes de imprimir la página, para que la redirección funcione).
// No se borran: quedan anuladas con el motivo y sus productos vuelven al inventario.
if(isset($_POST['anularVentasSeleccionadas']) && isset($_POST['ventasSeleccionadas'])) {
    $motivo = mb_substr(trim($_POST['motivo'] ?? ''), 0, 255);
    if($motivo === '') {
        guardarMensaje('danger', 'Escribe el motivo de la anulación.');
    } else {
        $ventasAnuladas = 0;
        foreach((array) $_POST['ventasSeleccionadas'] as $idVenta) {
            if(anularVenta($idVenta, $motivo, $_SESSION['idUsuario'])) {
                $ventasAnuladas++;
            }
        }
        guardarMensaje('success', 'Se anularon ' . $ventasAnuladas . ' venta(s). Sus productos volvieron al inventario.');
    }

    // Redirigir para evitar reenvío del formulario
    $queryString = $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '';
    header("Location: " . url("reportes/ventas.php") . $queryString);
    exit();
}

// Procesar búsquedas
$fechaInicio = $_POST['inicio'] ?? ($_GET['inicio'] ?? null);
$fechaFin = $_POST['fin'] ?? ($_GET['fin'] ?? null);
$usuario = $_POST['idUsuario'] ?? ($_GET['idUsuario'] ?? null);
$clasificacion = $_POST['clasificacion'] ?? ($_GET['clasificacion'] ?? null);
$categoria = $_POST['categoria'] ?? ($_GET['categoria'] ?? null);
$formaPago = $_POST['formaPago'] ?? ($_GET['formaPago'] ?? null);
$estado = $_POST['estado'] ?? ($_GET['estado'] ?? 'validas');
if(!in_array($estado, ['validas', 'anuladas', 'todas'], true)) $estado = 'validas';
if(!isset(FORMAS_PAGO[$formaPago ?? ''])) $formaPago = null;

// Si el usuario selecciona "Todos los usuarios" (valor vacío), no debe filtrar por usuario
$usuarioFiltro = (empty($usuario) || $usuario === '') ? null : $usuario;

// Obtener todas las ventas primero
$ventas = obtenerVentas($fechaInicio, $fechaFin, null, $usuarioFiltro, $estado, $formaPago);

// Filtrar por clasificación si se especificó
if(!empty($clasificacion)) {
    $nombresProductosClasificacion = obtenerProductosPorClasificacion($clasificacion);

    $ventasFiltradas = [];
    foreach($ventas as $venta) {
        $productosFiltrados = [];
        foreach($venta->productos as $producto) {
            if(in_array($producto->nombre, $nombresProductosClasificacion)) {
                $productosFiltrados[] = $producto;
            }
        }

        if(count($productosFiltrados) > 0) {
            $ventaFiltrada = clone $venta;
            $ventaFiltrada->productos = $productosFiltrados;

            $totalFiltrado = 0;
            foreach($productosFiltrados as $prod) {
                $totalFiltrado += $prod->cantidad * $prod->precio;
            }
            $ventaFiltrada->total = $totalFiltrado;
            $ventasFiltradas[] = $ventaFiltrada;
        }
    }
    $ventas = $ventasFiltradas;
}

// Filtrar por categoría si se especificó ('ninguna' = productos sin categoría)
if(!empty($categoria)) {
    $ventas = filtrarLineasPorProducto($ventas, function($producto) use ($categoria) {
        return $categoria === 'ninguna' ? $producto->idCategoria === null : $producto->idCategoria == $categoria;
    });
}

// Calcular estadísticas (las ventas anuladas se listan, pero no suman)
$ventasValidas = ventasValidas($ventas);
$numeroAnuladas = count($ventas) - count($ventasValidas);
$totalVentas = calcularTotalVentas($ventasValidas);
$totalProductos = calcularProductosVendidos($ventasValidas);
$ganancia = obtenerGananciaVentas($ventasValidas);

$cartas = [
    ["titulo" => "No. Ventas", "icono" => "fa fa-shopping-cart", "total" => number_format(count($ventasValidas)), "color" => "#A71D45"],
    ["titulo" => "Total Ventas", "icono" => "fa fa-money-bill", "total" => MONEDA . number_format($totalVentas, 2), "color" => "#2A8D22"],
    ["titulo" => "Productos Vendidos", "icono" => "fa fa-box", "total" => formatearCantidad($totalProductos), "color" => "#223D8D"],
    ["titulo" => "Ganancia", "icono" => "fa fa-wallet", "total" => MONEDA . number_format($ganancia, 2), "color" => "#D55929"],
];
if($numeroAnuladas > 0) {
    $cartas[] = ["titulo" => "Anuladas (no suman)", "icono" => "fa fa-ban", "total" => number_format($numeroAnuladas), "color" => ""];
}

$usuarios = obtenerUsuarios();
$categorias = obtenerCategorias();

// Obtener usuario seleccionado para mostrar
// Filtros actuales como consulta GET: para exportar el PDF y para volver al mismo reporte después de anular una venta
$consultaFiltros = array_filter(['inicio' => $fechaInicio, 'fin' => $fechaFin, 'idUsuario' => $usuario, 'clasificacion' => $clasificacion, 'categoria' => $categoria,
    'formaPago' => $formaPago, 'estado' => $estado === 'validas' ? null : $estado], fn($v) => $v !== null && $v !== '');

$usuarioSeleccionado = null;
if(!empty($usuario) && $usuario !== '') {
    $usuarioSeleccionado = obtenerUsuarioPorId($usuario);
}

// Exportar a PDF con los mismos filtros: ?formato=pdf&inicio=&fin=&idUsuario=&categoria=
if(($_GET['formato'] ?? '') === 'pdf') {
    $filas = [];
    foreach($ventas as $venta) {
        foreach($venta->productos as $i => $producto) {
            $primera = $i === 0;
            $filas[] = [
                $primera ? str_pad($venta->id, 6, '0', STR_PAD_LEFT) : '',
                $primera ? date('d/m/Y H:i', strtotime($venta->fecha)) : '',
                $primera ? $venta->usuario : '',
                $primera ? $venta->cliente : '',
                $primera ? nombreFormaPago($venta->formaPago) : '',
                ($venta->anulada ? '[ANULADA] ' : '') . $producto->nombre,
                cantidadConUnidad($producto->cantidad, $producto->unidad),
                MONEDA . ' ' . number_format($producto->precio, 2),
                MONEDA . ' ' . number_format($producto->cantidad * $producto->precio, 2),
                $primera ? ($venta->anulada ? 'ANULADA' : MONEDA . ' ' . number_format($venta->total, 2)) : '',
            ];
        }
    }
    enviarPdf('reporte_ventas_' . ($fechaInicio && $fechaFin ? $fechaInicio . '_al_' . $fechaFin : date('Y-m-d')) . '.pdf', generarPdfReporte([
        'titulo'   => 'Reporte de ventas',
        'detalles' => array_merge(describirFiltrosReporte($fechaInicio, $fechaFin, $usuarioSeleccionado, $clasificacion, $categoria),
            ($formaPago || $estado !== 'validas') ? ['Forma de pago: ' . ($formaPago ? nombreFormaPago($formaPago) : 'todas')
                . '     ·     Ventas: ' . ['validas' => 'válidas', 'anuladas' => 'anuladas', 'todas' => 'todas (las anuladas no suman)'][$estado]] : []),
        'resumen'  => [
            'N° de ventas'       => number_format(count($ventasValidas)),
            'Total vendido'      => MONEDA . ' ' . number_format($totalVentas, 2),
            'Productos vendidos' => formatearCantidad($totalProductos),
            'Ganancia'           => MONEDA . ' ' . number_format($ganancia, 2),
        ],
        'columnas' => [
            ['titulo' => 'N° venta', 'ancho' => 46],
            ['titulo' => 'Fecha', 'ancho' => 74],
            ['titulo' => 'Usuario', 'ancho' => 64],
            ['titulo' => 'Cliente', 'ancho' => 92],
            ['titulo' => 'Pago', 'ancho' => 62],
            ['titulo' => 'Producto', 'ancho' => 152],
            ['titulo' => 'Cantidad', 'ancho' => 64, 'derecha' => true],
            ['titulo' => 'P. unitario', 'ancho' => 70, 'derecha' => true],
            ['titulo' => 'Subtotal', 'ancho' => 72, 'derecha' => true],
            ['titulo' => 'Total venta', 'ancho' => 74, 'derecha' => true],
        ],
        'filas'    => $filas,
        'total'    => ['TOTAL GENERAL', MONEDA . ' ' . number_format($totalVentas, 2)],
        'vacio'    => 'No hay ventas para los filtros elegidos.',
    ]));
}

include_once VISTAS_DIR . "/encabezado.php";
include_once VISTAS_DIR . "/navbar.php";

?>
<div class="container-xl">
    <!-- Encabezado -->
    <div class="encabezado-pagina">
        <div>
            <h1 class="titulo-pagina"><i class="fa fa-chart-line"></i> Reporte de Ventas</h1>
            <p class="subtitulo-pagina">Filtra por fechas, usuario, categoría y forma de pago; exporta o anula ventas</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalAnularVentas">
                <i class="fa fa-ban"></i> Anular ventas
            </button>
            <!-- Descarga el reporte en PDF con los filtros aplicados -->
            <a href="<?= url('reportes/ventas.php') ?>?<?= htmlspecialchars(http_build_query(['formato' => 'pdf'] + $consultaFiltros)) ?>" class="btn btn-outline-secondary">
                <i class="fa fa-file-pdf"></i> Exportar PDF
            </a>
            <a href="<?= url('reportes/ventas.php') ?>" class="btn btn-outline-secondary">
                <i class="fa fa-times"></i> Limpiar Filtros
            </a>
        </div>
    </div>

    <?php mostrarMensaje(); ?>

    <!-- Formulario de filtros combinados -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fa fa-filter"></i> Filtros de Búsqueda</h5>
        </div>
        <div class="card-body">
            <form method="post" action="" id="formFiltros">
                <div class="row g-3">
                    <!-- Filtro por fecha -->
                    <div class="col-md-3">
                        <label for="inicio" class="form-label">Fecha Inicial</label>
                        <input type="date" name="inicio" class="form-control" id="inicio"
                               value="<?php echo $fechaInicio ?? ''; ?>" max="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="fin" class="form-label">Fecha Final</label>
                        <input type="date" name="fin" class="form-control" id="fin"
                               value="<?php echo $fechaFin ?? ''; ?>" max="<?php echo date('Y-m-d'); ?>">
                    </div>

                    <!-- Filtro por usuario -->
                    <div class="col-md-3">
                        <label for="selectUsuario" class="form-label">Usuario</label>
                        <select class="form-select" name="idUsuario" id="selectUsuario">
                            <option value="">Todos los usuarios</option>
                            <?php foreach($usuarios as $usuarioItem): ?>
                                <option value="<?php echo $usuarioItem->id; ?>"
                                    <?php echo ($usuario == $usuarioItem->id) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($usuarioItem->nombre); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Filtro por categoría -->
                    <div class="col-md-3">
                        <label for="selectCategoria" class="form-label">Categoría</label>
                        <select class="form-select" name="categoria" id="selectCategoria">
                            <option value="">Todas las categorías</option>
                            <?php foreach($categorias as $categoriaItem): ?>
                            <option value="<?= $categoriaItem->id ?>" <?php echo ($categoria == $categoriaItem->id) ? 'selected' : ''; ?>><?= htmlspecialchars($categoriaItem->nombre) ?></option>
                            <?php endforeach; ?>
                            <option value="ninguna" <?php echo ($categoria === 'ninguna') ? 'selected' : ''; ?>>Sin categoría</option>
                        </select>
                    </div>

                    <!-- Filtro por forma de pago -->
                    <div class="col-md-3">
                        <label for="selectFormaPago" class="form-label">Forma de pago</label>
                        <select class="form-select" name="formaPago" id="selectFormaPago">
                            <option value="">Todas las formas de pago</option>
                            <?php foreach(FORMAS_PAGO as $clave => $nombre): ?>
                            <option value="<?= htmlspecialchars($clave) ?>" <?= $formaPago === $clave ? 'selected' : '' ?>><?= htmlspecialchars($nombre) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Ventas válidas, anuladas o todas -->
                    <div class="col-md-3">
                        <label for="selectEstado" class="form-label">Ventas</label>
                        <select class="form-select" name="estado" id="selectEstado">
                            <option value="validas" <?= $estado === 'validas' ? 'selected' : '' ?>>Válidas</option>
                            <option value="anuladas" <?= $estado === 'anuladas' ? 'selected' : '' ?>>Anuladas</option>
                            <option value="todas" <?= $estado === 'todas' ? 'selected' : '' ?>>Todas</option>
                        </select>
                    </div>

                    <?php if(hayVariasTiendas()): ?>
                    <!-- Filtro por clasificación -->
                    <div class="col-md-3">
                        <label for="selectClasificacion" class="form-label">Clasificación</label>
                        <select class="form-select" name="clasificacion" id="selectClasificacion">
                            <option value="">Todas las clasificaciones</option>
                            <?php foreach(TIENDAS as $clave => $tienda): ?>
                            <option value="<?= $clave ?>" <?php echo ($clasificacion == $clave) ? 'selected' : ''; ?>><?= htmlspecialchars($tienda['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <!-- Botones de acción -->
                    <div class="col-md-12 mt-3">
                        <div class="d-flex gap-2">
                            <button type="submit" name="buscar" class="btn btn-primary">
                                <i class="fa fa-search"></i> Aplicar Filtros
                            </button>
                            <button type="button" class="btn btn-outline-secondary" onclick="limpiarFiltros()">
                                <i class="fa fa-eraser"></i> Limpiar Campos
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Información del filtro aplicado -->
    <?php if($fechaInicio || $fechaFin || $usuario || $clasificacion || $categoria || $formaPago || $estado !== 'validas'): ?>
    <div class="alert alert-info mb-4">
        <h6 class="alert-heading"><i class="fa fa-info-circle"></i> Filtros aplicados:</h6>
        <div class="row">
            <?php if($fechaInicio || $fechaFin): ?>
                <div class="col-md-3">
                    <strong><i class="fa fa-calendar"></i> Período:</strong><br>
                    <?php
                    if($fechaInicio && $fechaFin) {
                        echo date('d/m/Y', strtotime($fechaInicio)) . ' al ' . date('d/m/Y', strtotime($fechaFin));
                    } elseif($fechaInicio) {
                        echo 'Desde: ' . date('d/m/Y', strtotime($fechaInicio));
                    } elseif($fechaFin) {
                        echo 'Hasta: ' . date('d/m/Y', strtotime($fechaFin));
                    }
                    ?>
                </div>
            <?php endif; ?>

            <div class="col-md-3">
                <strong><i class="fa fa-user"></i> Usuario:</strong><br>
                <?php
                if($usuarioSeleccionado) {
                    echo htmlspecialchars($usuarioSeleccionado->nombre);
                } else {
                    echo 'Todos los usuarios';
                }
                ?>
            </div>

            <?php if(!empty($categoria)): ?>
            <div class="col-md-3">
                <strong><i class="fa fa-tags"></i> Categoría:</strong><br>
                <?php
                if($categoria === 'ninguna') {
                    echo 'Sin categoría';
                } else {
                    $categoriaSeleccionada = obtenerCategoriaPorId($categoria);
                    echo htmlspecialchars($categoriaSeleccionada->nombre ?? 'Categoría eliminada');
                }
                ?>
            </div>
            <?php endif; ?>

            <?php if(hayVariasTiendas()): ?>
            <div class="col-md-3">
                <strong><i class="fa fa-store"></i> Clasificación:</strong><br>
                <?php
                if(!empty($clasificacion)) {
                    echo htmlspecialchars(nombreTienda($clasificacion));
                } else {
                    echo 'Todas las clasificaciones';
                }
                ?>
            </div>
            <?php endif; ?>

            <?php if($formaPago || $estado !== 'validas'): ?>
            <div class="col-md-3">
                <strong><i class="fa fa-wallet"></i> Pago y estado:</strong><br>
                <?= $formaPago ? htmlspecialchars(nombreFormaPago($formaPago)) : 'Todas las formas' ?> ·
                <?= ['validas' => 'válidas', 'anuladas' => 'anuladas', 'todas' => 'todas'][$estado] ?>
            </div>
            <?php endif; ?>

            <div class="col-md-3">
                <strong><i class="fa fa-chart-bar"></i> Resultados:</strong><br>
                <?php echo count($ventas); ?> ventas encontradas<?= $numeroAnuladas ? " ($numeroAnuladas anuladas)" : '' ?>
            </div>
        </div>
    </div>
    <?php else: ?>
        <div class="alert alert-warning mb-4">
            <i class="fa fa-info-circle"></i> Mostrando ventas de hoy (<?php echo date('d/m/Y'); ?>)
        </div>
    <?php endif; ?>

    <!-- Tarjetas de estadísticas -->
    <?php include VISTAS_DIR . "/cartas_totales.php"; ?>

    <!-- Tabla de ventas -->
    <?php if(count($ventas) > 0): ?>
    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fa fa-list"></i> Reporte Detallado de Ventas
                    <span class="badge bg-light text-dark ms-2"><?php echo count($ventas); ?> ventas</span>
                </h5>
                <small class="text-light">
                    Total General: <span class="badge bg-success"><?= MONEDA ?><?php echo number_format($totalVentas, 2); ?></span>
                </small>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="tablaVentas">
                    <thead class="table-light">
                        <tr>
                            <th width="5%"># Venta</th>
                            <th width="15%">Fecha y Hora</th>
                            <th width="25%">Producto</th>
                            <th width="10%" class="text-center">Cantidad</th>
                            <th width="15%" class="text-end">Precio Unitario</th>
                            <th width="15%" class="text-end">Subtotal</th>
                            <th width="15%" class="text-center">Total Venta</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        foreach($ventas as $venta):
                            $fechaFormateada = date('d/m/Y H:i', strtotime($venta->fecha));
                            $numProductos = count($venta->productos);
                            $numeroVenta = str_pad($venta->id, 6, '0', STR_PAD_LEFT);
                        ?>
                            <?php foreach($venta->productos as $productoIndex => $producto):
                                $subtotal = $producto->cantidad * $producto->precio;
                                $clasificacionTexto = '';
                                $clasificacionColor = '';

                                // La tienda del producto solo se muestra cuando hay varias
                                if(hayVariasTiendas()) {
                                    $productoInfo = obtenerProductoPorNombre($producto->nombre);
                                    $clasificacionProducto = $productoInfo ? $productoInfo->clasificacion : null;
                                    if(array_key_exists($clasificacionProducto, TIENDAS)) {
                                        $clasificacionTexto = nombreTienda($clasificacionProducto);
                                        $clasificacionColor = badgeTienda($clasificacionProducto);
                                    }
                                }
                            ?>
                                <tr<?= $venta->anulada ? ' class="venta-anulada"' : '' ?>>
                                    <?php if($productoIndex === 0): ?>
                                        <td rowspan="<?php echo $numProductos; ?>" class="align-middle">
                                            <strong><?php echo $venta->id; ?></strong>
                                            <?php if($venta->anulada): ?>
                                                <br><span class="badge bg-danger">Anulada</span>
                                            <?php endif; ?>
                                        </td>
                                        <td rowspan="<?php echo $numProductos; ?>" class="align-middle">
                                            <span class="badge bg-secondary"><?php echo $fechaFormateada; ?></span><br>
                                            <small class="text-muted">
                                                Usuario: <?php echo htmlspecialchars($venta->usuario); ?><br>
                                                Cliente: <?php echo htmlspecialchars($venta->cliente); ?><br>
                                                Pago: <?= htmlspecialchars(nombreFormaPago($venta->formaPago)) ?>
                                                <?php if($venta->anulada): ?>
                                                    <br><span class="text-danger">Anulada el <?= date('d/m/Y H:i', strtotime($venta->fechaAnulacion)) ?>
                                                    por <?= htmlspecialchars($venta->usuarioAnulacion ?? '') ?>: <?= htmlspecialchars($venta->motivoAnulacion) ?></span>
                                                <?php endif; ?>
                                            </small>
                                        </td>
                                    <?php endif; ?>

                                    <td>
                                        <?php echo htmlspecialchars($producto->nombre); ?>
                                        <?php if($clasificacionTexto): ?>
                                            <br>
                                            <span class="badge <?php echo $clasificacionColor; ?>">
                                                <i class="fa fa-store"></i> <?php echo $clasificacionTexto; ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center"><?php echo cantidadConUnidad($producto->cantidad, $producto->unidad); ?></td>
                                    <td class="text-end"><?= MONEDA ?><?php echo number_format($producto->precio, 2); ?></td>
                                    <td class="text-end fw-bold"><?= MONEDA ?><?php echo number_format($subtotal, 2); ?></td>

                                    <?php if($productoIndex === 0): ?>
                                        <td rowspan="<?php echo $numProductos; ?>" class="align-middle text-center">
                                            <h5 class="<?= $venta->anulada ? 'text-muted' : 'text-success' ?> mb-0 importe-venta"><?= MONEDA ?><?php echo number_format($venta->total, 2); ?></h5>
                                            <a href="<?= url('ventas/ticket.php') ?>?id=<?php echo $venta->id; ?>" target="_blank" class="btn btn-outline-secondary btn-sm mt-1" title="Ver o reimprimir el ticket">
                                                <i class="fa fa-receipt"></i> Ticket
                                            </a>
                                            <?php if(!$venta->anulada): ?>
                                            <!-- Anula la venta completa (aunque el reporte esté filtrado por categoría): pide el motivo -->
                                            <button type="button" class="btn btn-outline-danger btn-sm mt-1" data-bs-toggle="modal" data-bs-target="#modalAnularVenta"
                                                    data-id="<?= $venta->id ?>" data-numero="<?= $numeroVenta ?>"
                                                    data-detalle="<?= htmlspecialchars(MONEDA . number_format($venta->total, 2) . ' · ' . $fechaFormateada) ?>"
                                                    title="Anular esta venta">
                                                <i class="fa fa-ban"></i> Anular
                                            </button>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>

                            <!-- Línea separadora entre ventas -->
                            <tr class="table-light">
                                <td colspan="7" class="p-0">
                                    <div class="border-bottom my-1"></div>
                                </td>
                            </tr>

                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <td colspan="5" class="text-end fw-bold">TOTALES GENERALES:</td>
                            <td class="fw-bold text-success"><?= MONEDA ?><?php echo number_format($totalVentas, 2); ?></td>
                            <td class="text-muted"><?php echo formatearCantidad($totalProductos); ?> productos<?= $numeroAnuladas ? '<br><small>sin las anuladas</small>' : '' ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <?php else: ?>
        <!-- Mensaje cuando no hay ventas -->
        <div class="card shadow-sm">
            <div class="card-body text-center py-5">
                <div class="mb-3">
                    <i class="fa fa-chart-line fa-5x text-muted"></i>
                </div>
                <h3 class="text-muted mb-3">No se han encontrado ventas</h3>
                <p class="text-muted mb-4">
                    <?php if($fechaInicio || $fechaFin || $usuario || $clasificacion || $categoria): ?>
                        No hay ventas con los filtros aplicados.
                    <?php else: ?>
                        No hay ventas registradas para hoy.
                    <?php endif; ?>
                </p>
                <button type="button" class="btn btn-primary" onclick="limpiarFiltrosCompletos()">
                    <i class="fa fa-refresh"></i> Ver todas las ventas
                </button>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Anular una venta: pide el motivo -->
<div class="modal fade" id="modalAnularVenta" tabindex="-1" aria-labelledby="modalAnularVentaLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="<?= url('ventas/anular.php') ?>">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalAnularVentaLabel"><i class="fa fa-ban"></i> Anular la venta N° <span id="anularNumero"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2"><span id="anularDetalle"></span></p>
                    <p class="small text-muted">La venta no se borra: queda anulada con el motivo, deja de sumar en los reportes y sus productos vuelven al inventario.</p>
                    <input type="hidden" name="id" id="anularId">
                    <input type="hidden" name="volver" value="<?= htmlspecialchars('reportes/ventas.php' . ($consultaFiltros ? '?' . http_build_query($consultaFiltros) : '')) ?>">
                    <label for="anularMotivo" class="form-label">Motivo</label>
                    <input type="text" class="form-control" name="motivo" id="anularMotivo" maxlength="255" required
                           placeholder="Ej. el cliente devolvió el producto">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger"><i class="fa fa-ban"></i> Anular venta</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Anular varias ventas a la vez -->
<div class="modal fade" id="modalAnularVentas" tabindex="-1" aria-labelledby="modalAnularVentasLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalAnularVentasLabel">
                    <i class="fa fa-ban"></i> Anular ventas
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form method="post" action="" id="formAnularVentas">
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fa fa-exclamation-triangle"></i>
                        Las ventas anuladas no se borran: quedan en el reporte marcadas como anuladas, dejan de sumar
                        y sus productos vuelven al inventario.
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Selecciona las ventas a anular:</label>
                        <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                            <table class="table table-sm table-hover">
                                <thead class="table-light sticky-top" style="top: 0;">
                                    <tr>
                                        <th width="5%">
                                            <input type="checkbox" id="selectAllVentas" class="form-check-input" aria-label="Seleccionar todas">
                                        </th>
                                        <th width="10%"># Venta</th>
                                        <th width="15%">Fecha</th>
                                        <th width="40%">Productos</th>
                                        <th width="15%">Cant. Total</th>
                                        <th width="15%">Total Venta</th>
                                    </tr>
                                </thead>
                                <tbody id="listaVentasAnular">
                                    <?php
                                    // Ventas completas (sin el filtro de categoría): se anula la venta entera
                                    foreach(obtenerVentas($fechaInicio, $fechaFin, null, $usuarioFiltro, 'validas', $formaPago) as $venta):
                                        $cantidadTotal = 0;
                                        foreach($venta->productos as $producto) {
                                            $cantidadTotal += $producto->cantidad;
                                        }
                                    ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" name="ventasSeleccionadas[]"
                                                       value="<?php echo $venta->id; ?>"
                                                       class="form-check-input venta-checkbox"
                                                       aria-label="Venta <?= $venta->id ?>"
                                                       data-total="<?php echo $venta->total; ?>"
                                                       data-cantidad="<?php echo $cantidadTotal; ?>">
                                            </td>
                                            <td class="fw-bold"><?php echo $venta->id; ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($venta->fecha)); ?></td>
                                            <td>
                                                <div class="productos-list" style="max-height: 80px; overflow-y: auto;">
                                                    <?php foreach($venta->productos as $producto): ?>
                                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                                            <span><?php echo htmlspecialchars($producto->nombre); ?></span>
                                                            <span class="badge bg-light text-dark">
                                                                <?php echo cantidadConUnidad($producto->cantidad, $producto->unidad); ?> x <?= MONEDA ?><?php echo number_format($producto->precio, 2); ?>
                                                            </span>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </td>
                                            <td class="text-center fw-bold">
                                                <span class="badge bg-info"><?php echo formatearCantidad($cantidadTotal); ?></span>
                                            </td>
                                            <td class="fw-bold"><?= MONEDA ?><?php echo number_format($venta->total, 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="motivoVarias" class="form-label">Motivo (se guarda en cada venta)</label>
                            <input type="text" class="form-control" name="motivo" id="motivoVarias" maxlength="255" required
                                   placeholder="Ej. ventas de prueba">
                        </div>
                        <div class="col-md-6">
                            <div class="alert alert-info mb-0 py-2">
                                <strong>Seleccionadas:</strong> <span id="contadorVentasSeleccionadas">0</span> ·
                                <span id="totalProductosSeleccionados">0</span> producto(s) ·
                                <?= MONEDA ?><span id="totalVentasSeleccionadas">0.00</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="anularVentasSeleccionadas" value="1" class="btn btn-danger" id="btnAnularVentas" disabled>
                        <i class="fa fa-ban"></i> Anular ventas seleccionadas
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Establecer fechas por defecto (hoy) solo si no hay filtros
    const fechaInicio = document.getElementById('inicio');
    const fechaFin = document.getElementById('fin');

    if(!window.location.search) {
        if(fechaInicio && !fechaInicio.value) {
            fechaInicio.value = '<?php echo date("Y-m-d"); ?>';
        }
        if(fechaFin && !fechaFin.value) {
            fechaFin.value = '<?php echo date("Y-m-d"); ?>';
        }
    }

    // Validar que fecha inicial no sea mayor a fecha final
    if(fechaInicio) {
        fechaInicio.addEventListener('change', function() {
            if(fechaFin && fechaFin.value && this.value > fechaFin.value) {
                fechaFin.value = this.value;
            }
        });
    }

    if(fechaFin) {
        fechaFin.addEventListener('change', function() {
            if(fechaInicio && fechaInicio.value && this.value < fechaInicio.value) {
                fechaInicio.value = this.value;
            }
        });
    }

    // ========== ANULAR VENTAS ==========

    // Una venta: la ventana recibe el número y el detalle del botón que la abrió
    const modalAnularVenta = document.getElementById('modalAnularVenta');
    if(modalAnularVenta) {
        modalAnularVenta.addEventListener('show.bs.modal', function(e) {
            const boton = e.relatedTarget;
            document.getElementById('anularId').value = boton.dataset.id;
            document.getElementById('anularNumero').textContent = boton.dataset.numero;
            document.getElementById('anularDetalle').textContent = boton.dataset.detalle;
            document.getElementById('anularMotivo').value = '';
        });
        modalAnularVenta.addEventListener('shown.bs.modal', function() {
            document.getElementById('anularMotivo').focus();
        });
    }

    // Varias ventas: seleccionar todas y resumen de la selección
    const selectAllCheckbox = document.getElementById('selectAllVentas');
    if(selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            document.querySelectorAll('.venta-checkbox').forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            actualizarResumenAnulacion();
        });
    }

    document.addEventListener('change', function(e) {
        if(e.target.classList.contains('venta-checkbox')) {
            actualizarResumenAnulacion();
        }
    });

    function actualizarResumenAnulacion() {
        const checkboxes = document.querySelectorAll('.venta-checkbox');
        const btnAnular = document.getElementById('btnAnularVentas');
        let contadorVentas = 0;
        let totalVentas = 0;
        let totalProductos = 0;

        checkboxes.forEach(checkbox => {
            if(checkbox.checked) {
                contadorVentas++;
                totalVentas += parseFloat(checkbox.getAttribute('data-total')) || 0;
                totalProductos = Math.round((totalProductos + (parseFloat(checkbox.getAttribute('data-cantidad')) || 0)) * 1000) / 1000;
            }
        });

        document.getElementById('contadorVentasSeleccionadas').textContent = contadorVentas;
        document.getElementById('totalVentasSeleccionadas').textContent = totalVentas.toFixed(2);
        document.getElementById('totalProductosSeleccionados').textContent = totalProductos;

        if(selectAllCheckbox) {
            selectAllCheckbox.checked = contadorVentas === checkboxes.length && checkboxes.length > 0;
            selectAllCheckbox.indeterminate = contadorVentas > 0 && contadorVentas < checkboxes.length;
        }
        if(btnAnular) {
            btnAnular.disabled = contadorVentas === 0;
            btnAnular.innerHTML = contadorVentas > 0
                ? `<i class="fa fa-ban"></i> Anular ${contadorVentas} venta(s)`
                : '<i class="fa fa-ban"></i> Anular ventas seleccionadas';
        }
    }

    const formAnularVentas = document.getElementById('formAnularVentas');
    if(formAnularVentas) {
        formAnularVentas.addEventListener('submit', function(e) {
            const seleccionadas = document.querySelectorAll('.venta-checkbox:checked').length;
            if(seleccionadas === 0) {
                e.preventDefault();
                alert('Selecciona al menos una venta para anular.');
                return;
            }
            if(!confirm(`¿Anular ${seleccionadas} venta(s)? Sus productos volverán al inventario.`)) {
                e.preventDefault();
            }
        });
    }

    const modalAnularVentas = document.getElementById('modalAnularVentas');
    if(modalAnularVentas) {
        modalAnularVentas.addEventListener('shown.bs.modal', function() {
            actualizarResumenAnulacion();
        });
    }
});

// Función para limpiar solo los campos del formulario
function limpiarFiltros() {
    document.getElementById('inicio').value = '';
    document.getElementById('fin').value = '';
    document.getElementById('selectUsuario').value = '';
    document.getElementById('selectCategoria').value = '';
    document.getElementById('selectFormaPago').value = '';
    document.getElementById('selectEstado').value = 'validas';
    // El selector de clasificación solo existe con varias tiendas
    const selectClasificacion = document.getElementById('selectClasificacion');
    if(selectClasificacion) selectClasificacion.value = '';
}

// Función para limpiar todos los filtros y recargar
function limpiarFiltrosCompletos() {
    window.location.href = '<?= url('reportes/ventas.php') ?>';
}
</script>