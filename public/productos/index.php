<?php
require_once __DIR__ . "/../../app/bootstrap.php";
requerirSesion();

include_once VISTAS_DIR . "/encabezado.php";
include_once VISTAS_DIR . "/navbar.php";

$nombreProducto = (isset($_POST['nombreProducto'])) ? $_POST['nombreProducto'] : null;
// Categoría elegida en el buscador, o la que llega desde la página de categorías (?categoria=ID)
$idCategoria = $_POST['idCategoria'] ?? ($_GET['categoria'] ?? null);

$productos = obtenerProductos($nombreProducto, $idCategoria);
$categorias = obtenerCategorias();

// Verificar mensaje de eliminación fallida
if(isset($_SESSION['error_eliminacion'])) {
    $error = $_SESSION['error_eliminacion'];
    unset($_SESSION['error_eliminacion']);
}

// El vendedor consulta precios y existencias: no ve costos, ganancias ni las acciones de administración
$administrador = esAdministrador();
$cartas = [
    ["titulo" => "No. Productos", "icono" => "fa fa-box", "total" => count($productos), "color" => "#3578FE"],
];
if($administrador){
    array_push($cartas,
        ["titulo" => "Existencias totales", "icono" => "fa fa-shopping-cart", "total" => formatearCantidad(obtenerNumeroProductos()), "color" => "#4F7DAF"],
        ["titulo" => "Total inventario", "icono" => "fa fa-money-bill", "total" => MONEDA. number_format(obtenerTotalInventario(), 2), "color" => "#1FB824"],
        ["titulo" => "Ganancia", "icono" => "fa fa-wallet", "total" => MONEDA. number_format(calcularGananciaProductos(), 2), "color" => "#D55929"]
    );
}
// Código, nombre, categoría, venta y existencia; más compra, ganancia y acciones para el administrador
$columnasTabla = 5 + ($administrador ? 3 : 0) + (hayVariasTiendas() ? 1 : 0);
?>
<div class="container-xl">
    <!-- Mostrar mensaje de error si existe -->
    <?php if(isset($error)): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <i class="fa fa-exclamation-triangle"></i>
            <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endif; ?>
    
    <div class="encabezado-pagina">
        <div>
            <h1 class="titulo-pagina"><i class="fa fa-box"></i> Productos</h1>
            <p class="subtitulo-pagina"><?= $administrador ? 'Catálogo, precios, costos y existencias' : 'Consulta de precios y existencias' ?></p>
        </div>
        <?php if($administrador): ?>
        <div class="acciones-pagina">
        <a class="btn btn-outline-secondary" href="<?= url('productos/importar.php') ?>">
            <i class="fa fa-file-import"></i>
            Importar
        </a>
        <!-- Exporta lo que muestra el listado (con la búsqueda y la categoría elegidas) -->
        <a class="btn btn-outline-secondary" href="<?= url('productos/exportar.php') ?>?<?= htmlspecialchars(http_build_query(array_filter(['busqueda' => $nombreProducto, 'categoria' => $idCategoria], fn($v) => $v !== null && $v !== ''))) ?>">
            <i class="fa fa-file-excel"></i>
            Exportar a Excel
        </a>
        <a class="btn btn-outline-secondary" href="<?= url('ajustes/nuevo.php') ?>">
            <i class="fa fa-sliders-h"></i>
            Ajustar stock
        </a>
        <a class="btn btn-primary" href="<?= url('productos/agregar.php') ?>">
            <i class="fa fa-plus"></i>
            Agregar producto
        </a>
        </div>
        <?php endif; ?>
    </div>
    <?php include_once VISTAS_DIR . "/cartas_totales.php"; ?>

    <form action="" method="post" class="barra-filtros">
        <div class="row g-2">
        <div class="col-12 col-md">
        <div class="input-group">
        <span class="input-group-text"><i class="fa fa-search"></i></span>
        <input autofocus name="nombreProducto" type="text" class="form-control" placeholder="Buscar por nombre o código" aria-label="Nombre producto" aria-describedby="button-addon2" value="<?= htmlspecialchars($nombreProducto ?? '') ?>">
        </div>
        </div>
        <div class="col-8 col-md-auto">
        <select name="idCategoria" class="form-select" aria-label="Categoría">
            <option value="">Todas las categorías</option>
            <?php foreach($categorias as $categoria): ?>
            <option value="<?= $categoria->id ?>" <?= $idCategoria == $categoria->id ? 'selected' : '' ?>><?= htmlspecialchars($categoria->nombre) ?> (<?= $categoria->productos ?>)</option>
            <?php endforeach; ?>
            <option value="ninguna" <?= $idCategoria === 'ninguna' ? 'selected' : '' ?>>Sin categoría</option>
        </select>
        </div>
        <div class="col-4 col-md-auto d-grid">
        <button type="submit" name="buscarProducto" class="btn btn-primary" id="button-addon2">
            <i class="fa fa-search"></i>
            Buscar
        </button>
        </div>
        </div>
    </form>

    <div class="card">
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Nombre</th>
                    <th>Categoría</th>
                    <?php if($administrador): ?>
                    <th class="text-end">Compra</th>
                    <?php endif; ?>
                    <th class="text-end">Venta</th>
                    <?php if($administrador): ?>
                    <th class="text-end">Ganancia</th>
                    <?php endif; ?>
                    <th class="text-center">Existencia</th>
                    <?php if(hayVariasTiendas()): ?>
                    <th>Clasificación</th>
                    <?php endif; ?>
                    <?php if($administrador): ?>
                    <th class="text-end">Acciones</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php
                foreach($productos as $producto){
                    $puedeEliminar = $administrador && !productoConCompras($producto->id) && !productoConVentas($producto->id);
                    $ganancia = floatval($producto->venta - $producto->compra);
                    
                    // Determinar clasificación (tienda definida en config/config.php)
                    $clasificacionTexto = array_key_exists($producto->clasificacion, TIENDAS) ? nombreTienda($producto->clasificacion) : "";
                    $clasificacionColor = badgeTienda($producto->clasificacion);
                ?>
                    <tr>
                        <td class="text-muted"><?= $producto->codigo; ?></td>
                        <td class="fw-semibold"><?= htmlspecialchars($producto->nombre); ?></td>
                        <td>
                            <?php if($producto->categoria): ?>
                                <a class="badge bg-secondary text-decoration-none" href="<?= url('productos/index.php') ?>?categoria=<?= $producto->idCategoria ?>"><?= htmlspecialchars($producto->categoria) ?></a>
                            <?php else: ?>
                                <span class="text-muted small">Sin categoría</span>
                            <?php endif; ?>
                        </td>
                        <?php if($administrador): ?>
                        <td class="text-end"><?= MONEDA ?><?= number_format($producto->compra, 2); ?></td>
                        <?php endif; ?>
                        <td class="text-end"><?= MONEDA ?><?= number_format($producto->venta, 2); ?> <small class="text-muted">/ <?= htmlspecialchars($producto->unidad) ?></small></td>
                        <?php if($administrador): ?>
                        <td class="text-end <?php echo $ganancia > 0 ? 'text-success fw-bold' : 'text-danger'; ?>">
                            <?= MONEDA ?><?= number_format($ganancia, 2); ?>
                        </td>
                        <?php endif; ?>
                        <td class="text-center">
                            <span class="badge <?php echo $producto->existencia > 0 ? 'bg-success' : 'bg-danger'; ?>">
                                <?= cantidadConUnidad($producto->existencia, $producto->unidad); ?>
                            </span>
                        </td>
                        <?php if(hayVariasTiendas()): ?>
                        <td>
                            <?php if($clasificacionTexto): ?>
                                <span class="badge <?= $clasificacionColor; ?>">
                                    <?= $clasificacionTexto; ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                        <?php if($administrador): ?>
                        <td>
                            <div class="d-flex gap-1 justify-content-end">
                                <a class="btn btn-outline-secondary btn-sm btn-icono" href="<?= url('productos/editar.php') ?>?id=<?= $producto->id; ?>" title="Editar producto" aria-label="Editar <?= htmlspecialchars($producto->nombre) ?>">
                                    <i class="fa fa-edit"></i>
                                </a>
                                <a class="btn btn-outline-secondary btn-sm btn-icono" href="<?= url('ajustes/nuevo.php') ?>?id=<?= $producto->id; ?>" title="Ajustar stock (conteo, merma, pérdida)" aria-label="Ajustar stock de <?= htmlspecialchars($producto->nombre) ?>">
                                    <i class="fa fa-sliders-h"></i>
                                </a>
                                <?php if($puedeEliminar): ?>
                                    <a class="btn btn-outline-danger btn-sm btn-icono" aria-label="Eliminar <?= htmlspecialchars($producto->nombre) ?>" href="<?= url('productos/eliminar.php') ?>?id=<?= $producto->id; ?>" 
                                       onclick="return confirm('¿Estás seguro de eliminar el producto: <?= htmlspecialchars($producto->nombre, ENT_QUOTES) ?>?')"
                                       title="Eliminar producto">
                                        <i class="fa fa-trash"></i>
                                    </a>
                                <?php else: ?>
                                    <button class="btn btn-outline-danger btn-sm btn-icono" disabled title="No se puede eliminar porque tiene compras o ventas registradas">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                        <?php endif; ?>
                    </tr>
                <?php } ?>
            </tbody>
            <tfoot class="table-light">
                <tr>
                    <td colspan="<?= $columnasTabla ?>" class="text-end small text-muted">
                        Mostrando <?php echo count($productos); ?> producto(s)
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
    </div>
</div>

<?php if(count($productos) === 0): ?>
<div class="container-xl pt-0">
    <div class="card">
        <div class="card-body estado-vacio">
            <i class="fa fa-box"></i>
            <h3 class="text-muted">No se encontraron productos</h3>
            <p class="text-muted mb-4">
                <?php echo ($nombreProducto || $idCategoria) ? "No hay productos que coincidan con la búsqueda" : "No hay productos registrados"; ?>
            </p>
            <a href="<?= url('productos/index.php') ?>" class="btn btn-primary">
                <i class="fa fa-refresh"></i> Ver todos los productos
            </a>
        </div>
    </div>
</div>
<?php endif; ?>