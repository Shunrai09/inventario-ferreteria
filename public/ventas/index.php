<?php
require_once __DIR__ . "/../../app/bootstrap.php";
requerirSesion();

include_once VISTAS_DIR . "/encabezado.php";
include_once VISTAS_DIR . "/navbar.php";

// Inicializar lista de productos en venta
$_SESSION['lista'] = isset($_SESSION['lista']) ? $_SESSION['lista'] : [];
$total = calcularTotalLista($_SESSION['lista']);

// Caja: sin caja abierta se puede armar el carrito, pero no cobrar
$caja = obtenerCajaAbierta();

// Obtener clientes y cliente seleccionados
$clientes = obtenerClientes();
$clienteSeleccionado = isset($_SESSION['clienteVenta']) ? obtenerClientePorId($_SESSION['clienteVenta']) : null;

// Procesar búsqueda
$busqueda = $_GET['busqueda'] ?? '';
$productosEncontrados = [];
if(!empty($busqueda)) {
    $productosEncontrados = obtenerProductos($busqueda);
}
?>

<div class="container-xl">
    <?php mostrarMensaje(); ?>
    <div class="encabezado-pagina">
        <div>
            <h1 class="titulo-pagina"><i class="fa fa-cash-register"></i> Vender</h1>
            <p class="subtitulo-pagina">Busca productos por código o nombre y agrégalos al carrito</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <?php if($caja): ?>
                <a href="<?= url('caja/index.php') ?>" class="badge bg-success fs-6 text-decoration-none" title="Ver la caja">
                    <i class="fa fa-lock-open"></i> Caja abierta
                </a>
            <?php else: ?>
                <span class="badge bg-warning fs-6"><i class="fa fa-lock"></i> Caja cerrada</span>
            <?php endif; ?>
            <span class="badge bg-primary fs-6">
                <i class="fa fa-cart-shopping"></i> <?php echo count($_SESSION['lista']); ?> en carrito
            </span>
        </div>
    </div>

    <div class="row g-4">
        <!-- Columna principal: búsqueda y carrito -->
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-body">
                    <!-- Formulario de búsqueda rápida -->
                    <form method="get" action="<?= url('ventas/index.php') ?>" id="formBusqueda">
                        <div class="input-group input-group-lg">
                            <span class="input-group-text">
                                <i class="fa fa-barcode"></i>
                            </span>
                            <input type="text"
                                   name="busqueda"
                                   class="form-control"
                                   placeholder="Código o nombre del producto"
                                   aria-label="Buscar producto por código o nombre"
                                   value="<?php echo htmlspecialchars($busqueda); ?>"
                                   autofocus
                                   id="inputBusqueda">
                            <button class="btn btn-primary" type="submit" aria-label="Buscar">
                                <i class="fa fa-search"></i><span class="d-none d-sm-inline">Buscar</span>
                            </button>
                            <?php if(!empty($busqueda)): ?>
                                <a href="<?= url('ventas/index.php') ?>" class="btn btn-outline-secondary" title="Limpiar búsqueda" aria-label="Limpiar búsqueda">
                                    <i class="fa fa-times"></i><span class="d-none d-sm-inline">Limpiar</span>
                                </a>
                            <?php endif; ?>
                        </div>
                        <div class="form-text mt-2">
                            <i class="fa fa-lightbulb"></i> Enter busca · Ctrl+F vuelve a este campo · F2 finaliza la venta
                        </div>
                    </form>

                    <!-- Resultados de búsqueda -->
                    <?php if(!empty($busqueda)): ?>
                        <div class="d-flex align-items-center justify-content-between mt-4 mb-2">
                            <h2 class="titulo-seccion mb-0">Resultados de búsqueda</h2>
                            <span class="badge bg-secondary"><?php echo count($productosEncontrados); ?> productos</span>
                        </div>

                        <?php if(count($productosEncontrados) > 0): ?>
                            <div class="table-responsive border rounded-3" tabindex="0" role="region" aria-label="Resultados de búsqueda">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th class="d-none d-md-table-cell">Código</th>
                                            <th>Producto</th>
                                            <th class="text-end">Precio</th>
                                            <th>Existencia</th>
                                            <?php if(hayVariasTiendas()): ?>
                                            <th>Clasificación</th>
                                            <?php endif; ?>
                                            <th class="text-end">Agregar</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($productosEncontrados as $producto):
                                            $enCarrito = false;
                                            $cantidadEnCarrito = 0;
                                            foreach($_SESSION['lista'] as $item) {
                                                if($item->id == $producto->id) {
                                                    $enCarrito = true;
                                                    $cantidadEnCarrito = $item->cantidad;
                                                    break;
                                                }
                                            }
                                        ?>
                                            <tr class="<?php echo $enCarrito ? 'table-success' : ''; ?>">
                                                <td class="text-muted d-none d-md-table-cell"><?php echo htmlspecialchars($producto->codigo); ?></td>
                                                <td>
                                                    <span class="fw-semibold"><?php echo htmlspecialchars($producto->nombre); ?></span>
                                                    <?php if($enCarrito): ?>
                                                        <span class="badge bg-success ms-1">
                                                            <i class="fa fa-check"></i> En carrito: <?php echo cantidadConUnidad($cantidadEnCarrito, $producto->unidad); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                    <?php if($producto->categoria): ?>
                                                        <br><small class="text-muted"><i class="fa fa-tag"></i> <?php echo htmlspecialchars($producto->categoria); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end fw-semibold text-nowrap">
                                                    <?= MONEDA ?><?php echo number_format($producto->venta, 2); ?>
                                                    <small class="text-muted fw-normal">/ <?php echo htmlspecialchars($producto->unidad); ?></small>
                                                </td>
                                                <td>
                                                    <?php if($producto->existencia > 0): ?>
                                                        <span class="badge bg-success"><?php echo cantidadConUnidad($producto->existencia, $producto->unidad); ?></span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">Agotado</span>
                                                    <?php endif; ?>
                                                </td>
                                                <?php if(hayVariasTiendas()): ?>
                                                <td>
                                                    <span class="badge <?php echo badgeTienda($producto->clasificacion); ?>"><?php echo htmlspecialchars(nombreTienda($producto->clasificacion)); ?></span>
                                                </td>
                                                <?php endif; ?>
                                                <td class="text-end">
                                                    <?php if($producto->existencia > 0): ?>
                                                        <form action="<?= url('ventas/agregar_producto.php') ?>" method="post" class="d-inline">
                                                            <input type="hidden" name="codigo" value="<?php echo $producto->codigo; ?>">
                                                            <button type="submit" name="agregar" class="btn btn-sm <?php echo $enCarrito ? 'btn-outline-success' : 'btn-primary'; ?>"
                                                                    <?php echo $enCarrito ? 'disabled' : ''; ?>
                                                                    title="<?php echo $enCarrito ? 'Ya en carrito' : 'Agregar al carrito'; ?>">
                                                                <i class="fa fa-<?php echo $enCarrito ? 'check' : 'cart-plus'; ?>"></i>
                                                                <?php echo $enCarrito ? 'Agregado' : 'Agregar'; ?>
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <span class="text-muted small">Sin stock</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning mb-2">
                                <i class="fa fa-exclamation-triangle"></i> No se encontraron productos para "<?php echo htmlspecialchars($busqueda); ?>"
                            </div>
                            <a href="<?= url('productos/index.php') ?>" class="btn btn-outline-secondary btn-sm">
                                <i class="fa fa-list"></i> Ver todos los productos
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Carrito de venta -->
            <?php if(count($_SESSION['lista']) > 0): ?>
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fa fa-shopping-cart text-muted me-1"></i> Carrito de Venta</span>
                    <span class="badge bg-secondary"><?php echo count($_SESSION['lista']); ?> productos</span>
                </div>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th class="text-end d-none d-sm-table-cell">Precio Unit.</th>
                                <th>Cantidad</th>
                                <th class="text-end">Subtotal</th>
                                <th class="text-end"><span class="visually-hidden">Acciones</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($_SESSION['lista'] as $indice => $producto):
                                $subtotal = $producto->cantidad * $producto->venta;
                            ?>
                                <tr>
                                    <td>
                                        <span class="fw-semibold"><?php echo htmlspecialchars($producto->nombre); ?></span>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($producto->codigo); ?></small>
                                        <?php if($producto->existencia < $producto->cantidad): ?>
                                            <br>
                                            <small class="text-danger">
                                                <i class="fa fa-exclamation-triangle"></i> Stock disponible: <?php echo cantidadConUnidad($producto->existencia, $producto->unidad ?? 'und'); ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end text-nowrap d-none d-sm-table-cell"><?= MONEDA ?><?php echo number_format($producto->venta, 2); ?> <small class="text-muted">/ <?php echo htmlspecialchars($producto->unidad ?? 'und'); ?></small></td>
                                    <td>
                                        <form action="<?= url('ventas/actualizar_cantidad.php') ?>" method="post">
                                            <input type="hidden" name="indice" value="<?php echo $indice; ?>">
                                            <div class="input-group input-group-sm cantidad-carrito">
                                                <button class="btn btn-outline-secondary" type="submit" name="accion" value="menos" aria-label="Restar 1">
                                                    <i class="fa fa-minus"></i>
                                                </button>
                                                <input type="number"
                                                       class="form-control text-center"
                                                       name="cantidad"
                                                       aria-label="Cantidad de <?php echo htmlspecialchars($producto->nombre); ?>"
                                                       value="<?php echo (float) $producto->cantidad; ?>"
                                                       <?php echo atributosCantidad($producto->unidad ?? 'und'); ?>
                                                       max="<?php echo (float) $producto->existencia; ?>"
                                                       onchange="this.form.submit()">
                                                <button class="btn btn-outline-secondary" type="submit" name="accion" value="mas" aria-label="Sumar 1">
                                                    <i class="fa fa-plus"></i>
                                                </button>
                                            </div>
                                        </form>
                                        <div class="stock-carrito mt-1">
                                            Stock: <?php echo cantidadConUnidad($producto->existencia, $producto->unidad ?? 'und'); ?>
                                        </div>
                                    </td>
                                    <td class="text-end fw-semibold text-nowrap"><?= MONEDA ?><?php echo number_format($subtotal, 2); ?></td>
                                    <td class="text-end">
                                        <a href="<?= url('ventas/quitar_producto.php') ?>?id=<?php echo $producto->id; ?>"
                                           class="btn btn-outline-danger btn-sm btn-icono"
                                           title="Quitar del carrito" aria-label="Quitar <?php echo htmlspecialchars($producto->nombre); ?> del carrito"
                                           onclick="return confirm('¿Eliminar este producto del carrito?');">
                                            <i class="fa fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Columna lateral: total, cliente y cierre de la venta -->
        <div class="col-lg-4">
            <div class="resumen-venta">
                <?php if(!$caja): ?>
                <div class="card mb-3">
                    <div class="card-header"><i class="fa fa-lock text-muted me-1"></i> La caja está cerrada</div>
                    <div class="card-body">
                        <form method="post" action="<?= url('caja/index.php') ?>">
                            <input type="hidden" name="volver" value="ventas">
                            <label for="montoInicial" class="form-label">Ábrela para poder cobrar. Monto inicial:</label>
                            <div class="input-group mb-2">
                                <span class="input-group-text"><?= MONEDA ?></span>
                                <input type="number" step="0.01" min="0" class="form-control" name="montoInicial" id="montoInicial" required
                                       placeholder="0.00" inputmode="decimal" autocomplete="off">
                                <button type="submit" name="abrir" value="1" class="btn btn-success">
                                    <i class="fa fa-lock-open"></i> Abrir caja
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
                <?php if(count($_SESSION['lista']) > 0): ?>
                <div class="card">
                    <div class="card-header"><i class="fa fa-receipt text-muted me-1"></i> Resumen de la venta</div>
                    <div class="card-body">
                        <div class="resumen-total">
                            <div class="resumen-total-etiqueta">Total a cobrar</div>
                            <div class="resumen-total-valor"><?= MONEDA ?><?php echo number_format($total, 2); ?></div>
                        </div>

                        <!-- Cliente -->
                        <h2 class="titulo-seccion"><i class="fa fa-user text-muted me-1"></i> Cliente <span class="text-muted fw-normal small">(opcional)</span></h2>
                        <?php if($clienteSeleccionado): ?>
                            <div class="d-flex justify-content-between align-items-start border rounded-3 p-3 mb-3 bg-light">
                                <dl class="datos-lista">
                                    <dt>Nombre</dt><dd class="fw-semibold"><?php echo htmlspecialchars($clienteSeleccionado->nombre); ?></dd>
                                    <dt>Teléfono</dt><dd><?php echo htmlspecialchars($clienteSeleccionado->telefono); ?></dd>
                                    <dt>Dirección</dt><dd><?php echo htmlspecialchars($clienteSeleccionado->direccion); ?></dd>
                                </dl>
                                <a href="<?= url('ventas/quitar_cliente.php') ?>" class="btn btn-sm btn-outline-secondary btn-icono" title="Quitar cliente" aria-label="Quitar cliente">
                                    <i class="fa fa-times"></i>
                                </a>
                            </div>
                        <?php else: ?>
                            <p class="small text-muted mb-2">No hace falta elegir uno: sin cliente, la venta se registra como mostrador.</p>
                        <?php endif; ?>

                        <!-- El cliente no es obligatorio: la opción vacía deja la venta como mostrador -->
                        <form method="post" action="<?= url('ventas/establecer_cliente.php') ?>" class="d-flex gap-2">
                            <select class="form-select" name="idCliente" aria-label="Cliente">
                                <option value="">Sin cliente (mostrador)</option>
                                <?php foreach($clientes as $cliente): ?>
                                    <option value="<?php echo $cliente->id; ?>" <?= $clienteSeleccionado && $clienteSeleccionado->id == $cliente->id ? 'selected' : '' ?>>
                                        <?php echo htmlspecialchars($cliente->nombre); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-outline-primary text-nowrap">
                                <i class="fa fa-user-check"></i> Asignar
                            </button>
                        </form>
                        <a href="<?= url('clientes/agregar.php') ?>?redireccion=ventas/index.php" class="btn btn-link btn-sm px-0 mt-1">
                            <i class="fa fa-user-plus"></i> Agregar cliente nuevo
                        </a>

                        <hr>
                        <?php if($caja): ?>
                        <form method="post" action="<?= url('ventas/registrar.php') ?>" id="formCobrar" data-total="<?= round($total, 2) ?>">
                            <h2 class="titulo-seccion"><i class="fa fa-wallet text-muted me-1"></i> Forma de pago</h2>
                            <div class="formas-pago mb-3" role="radiogroup" aria-label="Forma de pago">
                                <?php foreach(FORMAS_PAGO as $clave => $nombre): ?>
                                    <input type="radio" class="btn-check" name="formaPago" id="pago_<?= htmlspecialchars($clave) ?>" value="<?= htmlspecialchars($clave) ?>"
                                           autocomplete="off" <?= $clave === 'efectivo' ? 'checked' : '' ?>>
                                    <label class="btn btn-outline-primary" for="pago_<?= htmlspecialchars($clave) ?>"><?= htmlspecialchars($nombre) ?></label>
                                <?php endforeach; ?>
                            </div>

                            <div id="pagoEfectivo" class="mb-3">
                                <label for="pagoCon" class="form-label">Paga con <span class="text-muted">(opcional, para el vuelto)</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><?= MONEDA ?></span>
                                    <input type="number" step="0.01" min="0" class="form-control" name="pagoCon" id="pagoCon"
                                           placeholder="0.00" inputmode="decimal" autocomplete="off">
                                </div>
                                <div class="d-flex flex-wrap gap-1 mt-2">
                                    <button type="button" class="btn btn-outline-secondary btn-sm" data-monto="<?= round($total, 2) ?>">Exacto</button>
                                    <?php foreach(array_slice(array_filter([10, 20, 50, 100, 200], fn($billete) => $billete > $total), 0, 3) as $billete): ?>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" data-monto="<?= $billete ?>"><?= MONEDA . $billete ?></button>
                                    <?php endforeach; ?>
                                </div>
                                <div class="vuelto mt-2" id="vuelto" aria-live="polite"></div>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="fa fa-check-circle"></i> Finalizar Venta <span class="atajo">(F2)</span>
                                </button>
                                <a href="<?= url('ventas/cancelar.php') ?>"
                                   class="btn btn-outline-danger"
                                   onclick="return confirm('¿Estás seguro de cancelar toda la venta?');">
                                    <i class="fa fa-times-circle"></i> Cancelar <span class="atajo">(Esc)</span>
                                </a>
                            </div>
                        </form>
                        <?php else: ?>
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-success btn-lg" disabled>
                                <i class="fa fa-check-circle"></i> Finalizar Venta
                            </button>
                            <p class="small text-muted text-center mb-0">Abre la caja (arriba) para cobrar.</p>
                            <a href="<?= url('ventas/cancelar.php') ?>"
                               class="btn btn-outline-danger"
                               onclick="return confirm('¿Estás seguro de cancelar toda la venta?');">
                                <i class="fa fa-times-circle"></i> Cancelar <span class="atajo">(Esc)</span>
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php else: ?>
                    <!-- Mensaje cuando el carrito está vacío -->
                    <div class="card">
                        <div class="card-body estado-vacio">
                            <i class="fa fa-cart-shopping"></i>
                            <h3>Carrito de venta vacío</h3>
                            <p class="mb-3">Busca productos para agregarlos a la venta</p>
                            <div class="d-flex justify-content-center gap-2 flex-wrap">
                                <button onclick="document.getElementById('inputBusqueda').focus()" class="btn btn-primary">
                                    <i class="fa fa-search"></i> Buscar Producto
                                </button>
                                <a href="<?= url('productos/index.php') ?>" class="btn btn-outline-secondary">
                                    <i class="fa fa-list"></i> Ver Catálogo
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Scripts para mejoras de usabilidad -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const inputBusqueda = document.getElementById('inputBusqueda');
    
    // Autofocus en input de búsqueda
    inputBusqueda.focus();
    inputBusqueda.select();
    
    // Atajos de teclado
    document.addEventListener('keydown', function(e) {
        // F2 finaliza la venta con la forma de pago elegida; con la caja cerrada lleva al monto inicial
        if(e.key === 'F2' && <?php echo count($_SESSION['lista']) > 0 ? 'true' : 'false'; ?>) {
            e.preventDefault();
            const formCobrar = document.getElementById('formCobrar');
            if(formCobrar) {
                formCobrar.requestSubmit();
            } else {
                document.getElementById('montoInicial').focus();
            }
        }
        
        // Escape para cancelar
        if(e.key === 'Escape' && <?php echo count($_SESSION['lista']) > 0 ? 'true' : 'false'; ?>) {
            e.preventDefault();
            if(confirm('¿Cancelar toda la venta?')) {
                window.location.href = '<?= url('ventas/cancelar.php') ?>';
            }
        }
        
        // Ctrl+F para enfocar búsqueda
        if(e.ctrlKey && e.key === 'f') {
            e.preventDefault();
            inputBusqueda.focus();
            inputBusqueda.select();
        }
        
        // Ctrl+L para limpiar búsqueda
        if(e.ctrlKey && e.key === 'l') {
            e.preventDefault();
            window.location.href = '<?= url('ventas/index.php') ?>';
        }
    });
});

// Cobro: "Paga con" y el vuelto solo se ven con efectivo; no deja cobrar si lo entregado no alcanza
(function() {
    const form = document.getElementById('formCobrar');
    if(!form) return;
    const total = parseFloat(form.dataset.total);
    const pagoCon = document.getElementById('pagoCon');
    const vuelto = document.getElementById('vuelto');
    const bloqueEfectivo = document.getElementById('pagoEfectivo');
    const moneda = <?= json_encode(MONEDA) ?>;

    function calcularVuelto() {
        if(pagoCon.value === '') {
            vuelto.textContent = '';
            return null;
        }
        const resto = Math.round((parseFloat(pagoCon.value) - total) * 100) / 100;
        vuelto.classList.toggle('vuelto--falta', resto < 0);
        vuelto.textContent = resto < 0 ? 'Faltan ' + moneda + Math.abs(resto).toFixed(2) : 'Vuelto: ' + moneda + resto.toFixed(2);
        return resto;
    }
    pagoCon.addEventListener('input', calcularVuelto);
    form.querySelectorAll('[data-monto]').forEach(function(boton) {
        boton.addEventListener('click', function() {
            pagoCon.value = boton.dataset.monto;
            calcularVuelto();
        });
    });
    form.querySelectorAll('input[name="formaPago"]').forEach(function(opcion) {
        opcion.addEventListener('change', function() {
            const efectivo = form.querySelector('input[name="formaPago"]:checked').value === 'efectivo';
            bloqueEfectivo.hidden = !efectivo;
            if(!efectivo) {
                pagoCon.value = '';
                calcularVuelto();
            }
        });
    });
    form.addEventListener('submit', function(e) {
        const resto = calcularVuelto();
        if(resto !== null && resto < 0) {
            e.preventDefault();
            pagoCon.focus();
        }
    });
})();

// Función para actualizar cantidad rápidamente
function actualizarCantidadRapida(indice, cambio) {
    const form = document.createElement('form');
    form.method = 'post';
    form.action = '<?= url('ventas/actualizar_cantidad.php') ?>';
    
    const inputIndice = document.createElement('input');
    inputIndice.type = 'hidden';
    inputIndice.name = 'indice';
    inputIndice.value = indice;
    
    const inputAccion = document.createElement('input');
    inputAccion.type = 'hidden';
    inputAccion.name = 'accion';
    inputAccion.value = cambio > 0 ? 'mas' : 'menos';
    
    form.appendChild(inputIndice);
    form.appendChild(inputAccion);
    document.body.appendChild(form);
    form.submit();
}
</script>