<?php
require_once __DIR__ . "/../../app/bootstrap.php";
requerirAdministrador();

// Procesar eliminación de compras (antes de imprimir la página, para que la redirección funcione)
if(isset($_POST['eliminarComprasSeleccionadas']) && isset($_POST['comprasSeleccionadas'])) {
    $comprasAEliminar = $_POST['comprasSeleccionadas'];
    $comprasEliminadas = 0;
    
    foreach($comprasAEliminar as $idCompra) {
        // Elimina la compra y descuenta del inventario los productos comprados
        if(eliminarCompra($idCompra)) {
            $comprasEliminadas++;
        }
    }
    
    $_SESSION['mensaje'] = [
        'tipo' => 'success',
        'texto' => 'Se eliminaron ' . $comprasEliminadas . ' compra(s) correctamente.'
    ];
    
    // Redirigir para evitar reenvío del formulario
    $queryString = $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '';
    header("Location: " . url("reportes/compras.php") . $queryString);
    exit();
}

// Procesar búsquedas
$fechaInicio = $_POST['inicio'] ?? ($_GET['inicio'] ?? null);
$fechaFin = $_POST['fin'] ?? ($_GET['fin'] ?? null);
$usuario = $_POST['idUsuario'] ?? ($_GET['idUsuario'] ?? null);
$clasificacion = $_POST['clasificacion'] ?? ($_GET['clasificacion'] ?? null);
$categoria = $_POST['categoria'] ?? ($_GET['categoria'] ?? null);

// Si el usuario selecciona "Todos los usuarios" (valor vacío), no debe filtrar por usuario
$usuarioFiltro = (empty($usuario) || $usuario === '') ? null : $usuario;

// Obtener todas las compras primero
$compras = obtenerCompras($fechaInicio, $fechaFin, $usuarioFiltro);

// Filtrar por clasificación si se especificó
if(!empty($clasificacion)) {
    $nombresProductosClasificacion = obtenerProductosPorClasificacion($clasificacion);
    
    $comprasFiltradas = [];
    foreach($compras as $compra) {
        $productosFiltrados = [];
        foreach($compra->productos as $producto) {
            if(in_array($producto->nombre, $nombresProductosClasificacion)) {
                $productosFiltrados[] = $producto;
            }
        }
        
        if(count($productosFiltrados) > 0) {
            $compraFiltrada = clone $compra;
            $compraFiltrada->productos = $productosFiltrados;
            
            $totalFiltrado = 0;
            foreach($productosFiltrados as $prod) {
                $totalFiltrado += $prod->cantidad * $prod->precio;
            }
            $compraFiltrada->total = $totalFiltrado;
            $comprasFiltradas[] = $compraFiltrada;
        }
    }
    $compras = $comprasFiltradas;
}

// Filtrar por categoría si se especificó ('ninguna' = productos sin categoría)
if(!empty($categoria)) {
    $compras = filtrarLineasPorProducto($compras, function($producto) use ($categoria) {
        return $categoria === 'ninguna' ? $producto->idCategoria === null : $producto->idCategoria == $categoria;
    });
}

// Calcular estadísticas
$totalCompras = calcularTotalCompras($compras);
$totalProductos = calcularProductosComprados($compras);
$inversionTotal = $totalCompras; // En compras, el total es la inversión

$cartas = [
    ["titulo" => "No. Compras", "icono" => "fa fa-shopping-basket", "total" => number_format(count($compras)), "color" => "#A71D45"],
    ["titulo" => "Total Invertido", "icono" => "fa fa-money-bill", "total" => MONEDA . number_format($totalCompras, 2), "color" => "#2A8D22"],
    ["titulo" => "Productos Comprados", "icono" => "fa fa-box", "total" => formatearCantidad($totalProductos), "color" => "#223D8D"],
];

$usuarios = obtenerUsuarios();
$categorias = obtenerCategorias();

// Obtener usuario seleccionado para mostrar
$usuarioSeleccionado = null;
if(!empty($usuario) && $usuario !== '') {
    $usuarioSeleccionado = obtenerUsuarioPorId($usuario);
}

// Exportar a PDF con los mismos filtros: ?formato=pdf&inicio=&fin=&idUsuario=&categoria=
if(($_GET['formato'] ?? '') === 'pdf') {
    $filas = [];
    foreach($compras as $compra) {
        foreach($compra->productos as $i => $producto) {
            $primera = $i === 0;
            $filas[] = [
                $primera ? str_pad($compra->id, 6, '0', STR_PAD_LEFT) : '',
                $primera ? date('d/m/Y H:i', strtotime($compra->fecha)) : '',
                $primera ? $compra->usuario : '',
                $producto->nombre,
                cantidadConUnidad($producto->cantidad, $producto->unidad),
                MONEDA . ' ' . number_format($producto->precio, 2),
                MONEDA . ' ' . number_format($producto->cantidad * $producto->precio, 2),
                $primera ? MONEDA . ' ' . number_format($compra->total, 2) : '',
                $primera ? (string) $compra->notas : '',
            ];
        }
    }
    enviarPdf('reporte_compras_' . ($fechaInicio && $fechaFin ? $fechaInicio . '_al_' . $fechaFin : date('Y-m-d')) . '.pdf', generarPdfReporte([
        'titulo'   => 'Reporte de compras',
        'detalles' => describirFiltrosReporte($fechaInicio, $fechaFin, $usuarioSeleccionado, $clasificacion, $categoria),
        'resumen'  => [
            'N° de compras'       => number_format(count($compras)),
            'Total invertido'     => MONEDA . ' ' . number_format($totalCompras, 2),
            'Productos comprados' => formatearCantidad($totalProductos),
        ],
        'columnas' => [
            ['titulo' => 'N° compra', 'ancho' => 46],
            ['titulo' => 'Fecha', 'ancho' => 74],
            ['titulo' => 'Usuario', 'ancho' => 64],
            ['titulo' => 'Producto', 'ancho' => 216],
            ['titulo' => 'Cantidad', 'ancho' => 64, 'derecha' => true],
            ['titulo' => 'P. unitario', 'ancho' => 70, 'derecha' => true],
            ['titulo' => 'Subtotal', 'ancho' => 72, 'derecha' => true],
            ['titulo' => 'Total compra', 'ancho' => 74, 'derecha' => true],
            ['titulo' => 'Notas', 'ancho' => 90],
        ],
        'filas'    => $filas,
        'total'    => ['TOTAL GENERAL', MONEDA . ' ' . number_format($totalCompras, 2)],
        'vacio'    => 'No hay compras para los filtros elegidos.',
    ]));
}

include_once VISTAS_DIR . "/encabezado.php";
include_once VISTAS_DIR . "/navbar.php";

// Mostrar mensaje de éxito si existe
if(isset($_SESSION['mensaje'])) {
    $mensaje = $_SESSION['mensaje'];
    unset($_SESSION['mensaje']);
}
?>
<div class="container-xl">
    <!-- Encabezado -->
    <div class="encabezado-pagina">
        <div>
            <h1 class="titulo-pagina"><i class="fa fa-chart-line"></i> Reporte de Compras</h1>
            <p class="subtitulo-pagina">Filtra por fechas, usuario y categoría; exporta o elimina compras</p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalEliminarCompras">
                <i class="fa fa-trash"></i> Eliminar Compra
            </button>
            <!-- Descarga el reporte en PDF con los filtros aplicados -->
            <a href="<?= url('reportes/compras.php') ?>?<?= htmlspecialchars(http_build_query(['formato' => 'pdf'] + array_filter(['inicio' => $fechaInicio, 'fin' => $fechaFin, 'idUsuario' => $usuario, 'clasificacion' => $clasificacion, 'categoria' => $categoria], fn($v) => $v !== null && $v !== ''))) ?>" class="btn btn-outline-secondary">
                <i class="fa fa-file-pdf"></i> Exportar PDF
            </a>
            <a href="<?= url('reportes/compras.php') ?>" class="btn btn-outline-secondary">
                <i class="fa fa-times"></i> Limpiar Filtros
            </a>
        </div>
    </div>
    
    <!-- Mostrar mensaje de éxito -->
    <?php if(isset($mensaje)): ?>
        <div class="alert alert-<?php echo $mensaje['tipo']; ?> alert-dismissible fade show" role="alert">
            <i class="fa fa-<?php echo $mensaje['tipo'] == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <?php echo $mensaje['texto']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endif; ?>
    
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
    <?php if($fechaInicio || $fechaFin || $usuario || $clasificacion || $categoria): ?>
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
            
            <div class="col-md-3">
                <strong><i class="fa fa-chart-bar"></i> Resultados:</strong><br>
                <?php echo count($compras); ?> compras encontradas
            </div>
        </div>
    </div>
    <?php elseif(empty($fechaInicio) && empty($fechaFin) && empty($usuario) && empty($clasificacion) && empty($categoria)): ?>
        <div class="alert alert-warning mb-4">
            <i class="fa fa-info-circle"></i> Mostrando compras de hoy (<?php echo date('d/m/Y'); ?>)
        </div>
    <?php endif; ?>
    
    <!-- Tarjetas de estadísticas -->
    <?php include VISTAS_DIR . "/cartas_totales.php"; ?>

    <!-- Tabla de compras -->
    <?php if(count($compras) > 0): ?>
    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fa fa-list"></i> Reporte Detallado de Compras
                    <span class="badge bg-light text-dark ms-2"><?php echo count($compras); ?> compras</span>
                </h5>
                <small class="text-light">
                    Total General: <span class="badge bg-success"><?= MONEDA ?><?php echo number_format($totalCompras, 2); ?></span>
                </small>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="tablaCompras">
                    <thead class="table-light">
                        <tr>
                            <th width="5%"># Compra</th>
                            <th width="15%">Fecha y Hora</th>
                            <th width="25%">Producto</th>
                            <th width="10%" class="text-center">Cantidad</th>
                            <th width="15%" class="text-end">Precio Unitario</th>
                            <th width="15%" class="text-center">Total Compra</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        foreach($compras as $compra): 
                            $fechaFormateada = date('d/m/Y H:i', strtotime($compra->fecha));
                            $numProductos = count($compra->productos);
                        ?>
                            <?php foreach($compra->productos as $productoIndex => $producto): 
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
                                <tr>
                                    <?php if($productoIndex === 0): ?>
                                        <td rowspan="<?php echo $numProductos; ?>" class="align-middle">
                                            <strong><?php echo $compra->id; ?></strong>
                                        </td>
                                        <td rowspan="<?php echo $numProductos; ?>" class="align-middle">
                                            <span class="badge bg-secondary"><?php echo $fechaFormateada; ?></span><br>
                                            <small class="text-muted">
                                                Usuario: <?php echo htmlspecialchars($compra->usuario); ?><br>
                                                <?php if(!empty($compra->notas)): ?>
                                                    <span class="text-info">
                                                        <i class="fa fa-sticky-note"></i> <?php echo htmlspecialchars($compra->notas); ?>
                                                    </span>
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
                                    
                                    <?php if($productoIndex === 0): ?>
                                        <td rowspan="<?php echo $numProductos; ?>" class="align-middle text-center">
                                            <h5 class="text-success mb-0"><?= MONEDA ?><?php echo number_format($compra->total, 2); ?></h5>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                            
                            <!-- Línea separadora entre compras -->
                            <tr class="table-light">
                                <td colspan="7" class="p-0">
                                    <div class="border-bottom my-1"></div>
                                </td>
                            </tr>
                            
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <td colspan="4" class="text-end fw-bold">TOTALES GENERALES:</td>
                            <td class="fw-bold text-success"><?= MONEDA ?><?php echo number_format($totalCompras, 2); ?></td>
                            <td class="text-muted"><?php echo number_format($totalProductos); ?> productos</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
    
    <?php else: ?>
        <!-- Mensaje cuando no hay compras -->
        <div class="card shadow-sm">
            <div class="card-body text-center py-5">
                <div class="mb-3">
                    <i class="fa fa-shopping-basket fa-5x text-muted"></i>
                </div>
                <h3 class="text-muted mb-3">No se han encontrado compras</h3>
                <p class="text-muted mb-4">
                    <?php if($fechaInicio || $fechaFin || $usuario || $clasificacion || $categoria): ?>
                        No hay compras con los filtros aplicados.
                    <?php else: ?>
                        No hay compras registradas para hoy.
                    <?php endif; ?>
                </p>
                <button type="button" class="btn btn-primary" onclick="limpiarFiltrosCompletos()">
                    <i class="fa fa-refresh"></i> Ver todas las compras
                </button>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Modal para eliminar compras -->
<div class="modal fade" id="modalEliminarCompras" tabindex="-1" aria-labelledby="modalEliminarComprasLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalEliminarComprasLabel">
                    <i class="fa fa-trash"></i> Eliminar Compras
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" action="" id="formEliminarCompras">
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fa fa-exclamation-triangle"></i> 
                        <strong>Advertencia:</strong> Esta acción no se puede deshacer. Al eliminar una compra, 
                        los productos comprados se descontarán del inventario.
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Selecciona las compras a eliminar:</label>
                        <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                            <table class="table table-sm table-hover">
                                <thead class="table-light sticky-top" style="top: 0;">
                                    <tr>
                                        <th width="5%">
                                            <input type="checkbox" id="selectAllCompras" class="form-check-input">
                                        </th>
                                        <th width="10%"># Compra</th>
                                        <th width="15%">Fecha</th>
                                        <th width="40%">Productos</th>
                                        <th width="15%">Cant. Total</th>
                                        <th width="15%">Total Compra</th>
                                    </tr>
                                </thead>
                                <tbody id="listaComprasEliminar">
                                    <?php 
                                    $comprasParaModal = obtenerCompras($fechaInicio, $fechaFin, $usuarioFiltro);
                                    foreach($comprasParaModal as $compra): 
                                        $fechaCorta = date('d/m/Y', strtotime($compra->fecha));
                                        $cantidadTotal = 0;
                                        foreach($compra->productos as $producto) {
                                            $cantidadTotal += $producto->cantidad;
                                        }
                                    ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" name="comprasSeleccionadas[]" 
                                                       value="<?php echo $compra->id; ?>" 
                                                       class="form-check-input compra-checkbox"
                                                       data-total="<?php echo $compra->total; ?>"
                                                       data-cantidad="<?php echo $cantidadTotal; ?>">
                                            </td>
                                            <td class="fw-bold"><?php echo $compra->id; ?></td>
                                            <td><?php echo $fechaCorta; ?></td>
                                            <td>
                                                <div class="productos-list" style="max-height: 80px; overflow-y: auto;">
                                                    <?php foreach($compra->productos as $producto): 
                                                        $clasificacionProducto = hayVariasTiendas() ? obtenerClasificacionProducto($producto->nombre) : null;
                                                        $clasificacionColor = badgeTienda($clasificacionProducto);
                                                    ?>
                                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                                            <span>
                                                                <?php if(hayVariasTiendas()): ?>
                                                                <span class="badge <?php echo $clasificacionColor; ?> me-1">
                                                                    <?php echo htmlspecialchars(cortoTienda($clasificacionProducto)); ?>
                                                                </span>
                                                                <?php endif; ?>
                                                                <?php echo htmlspecialchars($producto->nombre); ?>
                                                            </span>
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
                                            <td class="text-success fw-bold"><?= MONEDA ?> <?php echo number_format($compra->total, 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Resumen de selección:</label>
                        <div class="alert alert-info">
                            <div class="row">
                                <div class="col-md-3">
                                    <strong>Compras seleccionadas:</strong><br>
                                    <span id="contadorComprasSeleccionadas" class="badge bg-primary fs-5">0</span>
                                </div>
                                <div class="col-md-3">
                                    <strong>Productos totales:</strong><br>
                                    <span id="totalProductosSeleccionados" class="badge bg-info fs-5">0</span>
                                </div>
                                <div class="col-md-3">
                                    <strong>Total a eliminar:</strong><br>
                                    <span class="text-success fw-bold fs-5"><?= MONEDA ?><span id="totalComprasSeleccionadas">0.00</span></span>
                                </div>
                                <div class="col-md-3">
                                    <strong>Acción:</strong><br>
                                    <span class="badge bg-danger fs-6">IRREVERSIBLE</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fa fa-times"></i> Cancelar
                    </button>
                    <button type="submit" name="eliminarComprasSeleccionadas" class="btn btn-danger" id="btnEliminarCompras" disabled>
                        <i class="fa fa-trash"></i> Eliminar Compras Seleccionadas
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
    
    // ========== FUNCIONALIDAD PARA ELIMINAR COMPRAS ==========
    
    // Seleccionar/deseleccionar todas las compras
    const selectAllCheckbox = document.getElementById('selectAllCompras');
    if(selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.compra-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            actualizarResumenEliminacion();
        });
    }
    
    // Actualizar cuando se cambia cualquier checkbox
    document.addEventListener('change', function(e) {
        if(e.target.classList.contains('compra-checkbox')) {
            actualizarResumenEliminacion();
        }
    });
    
    // Función para actualizar el resumen de eliminación
    function actualizarResumenEliminacion() {
        const checkboxes = document.querySelectorAll('.compra-checkbox');
        const btnEliminar = document.getElementById('btnEliminarCompras');
        let contadorCompras = 0;
        let totalCompras = 0;
        let totalProductos = 0;
        
        checkboxes.forEach(checkbox => {
            if(checkbox.checked) {
                contadorCompras++;
                totalCompras += parseFloat(checkbox.getAttribute('data-total')) || 0;
                totalProductos = Math.round((totalProductos + (parseFloat(checkbox.getAttribute('data-cantidad')) || 0)) * 1000) / 1000;
            }
        });
        
        // Actualizar contadores
        document.getElementById('contadorComprasSeleccionadas').textContent = contadorCompras;
        document.getElementById('totalComprasSeleccionadas').textContent = totalCompras.toFixed(2);
        document.getElementById('totalProductosSeleccionados').textContent = totalProductos;
        
        // Actualizar checkbox "Seleccionar todos"
        if(selectAllCheckbox) {
            selectAllCheckbox.checked = contadorCompras === checkboxes.length && checkboxes.length > 0;
            selectAllCheckbox.indeterminate = contadorCompras > 0 && contadorCompras < checkboxes.length;
        }
        
        // Actualizar botón eliminar
        if(btnEliminar) {
            btnEliminar.disabled = contadorCompras === 0;
            if(contadorCompras > 0) {
                btnEliminar.innerHTML = `<i class="fa fa-trash"></i> Eliminar ${contadorCompras} Compra(s)`;
            }
        }
    }
    
    // Confirmar antes de eliminar
    const formEliminarCompras = document.getElementById('formEliminarCompras');
    if(formEliminarCompras) {
        formEliminarCompras.addEventListener('submit', function(e) {
            const checkboxes = document.querySelectorAll('.compra-checkbox:checked');
            
            if(checkboxes.length === 0) {
                e.preventDefault();
                alert('Por favor, selecciona al menos una compra para eliminar.');
                return;
            }
            
            let totalProductos = 0;
            checkboxes.forEach(checkbox => {
                totalProductos = Math.round((totalProductos + (parseFloat(checkbox.getAttribute('data-cantidad')) || 0)) * 1000) / 1000;
            });
            
            const confirmacion = confirm(
                `¿Estás seguro de eliminar ${checkboxes.length} compra(s)?\n\n` +
                `Esto eliminará:\n` +
                `• ${checkboxes.length} compra(s)\n` +
                `• ${totalProductos} producto(s) comprados\n\n` +
                `Los productos se descontarán del inventario.\n` +
                `Esta acción NO se puede deshacer.`
            );
            
            if(!confirmacion) {
                e.preventDefault();
            }
        });
    }
    
    // Inicializar cuando se abre el modal
    const modalEliminar = document.getElementById('modalEliminarCompras');
    if(modalEliminar) {
        modalEliminar.addEventListener('shown.bs.modal', function() {
            actualizarResumenEliminacion();
        });
    }
});

// Función para limpiar solo los campos del formulario
function limpiarFiltros() {
    document.getElementById('inicio').value = '';
    document.getElementById('fin').value = '';
    document.getElementById('selectUsuario').value = '';
    document.getElementById('selectCategoria').value = '';
    // El selector de clasificación solo existe con varias tiendas
    const selectClasificacion = document.getElementById('selectClasificacion');
    if(selectClasificacion) selectClasificacion.value = '';
}

// Función para limpiar todos los filtros y recargar
function limpiarFiltrosCompletos() {
    window.location.href = '<?= url('reportes/compras.php') ?>';
}
</script>