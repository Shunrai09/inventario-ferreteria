<?php
require_once __DIR__ . "/../../app/bootstrap.php";
requerirAdministrador();

include_once VISTAS_DIR . "/encabezado.php";
include_once VISTAS_DIR . "/navbar.php";

// Obtener productos para el select
$productos = obtenerProductos();

// Si hay búsqueda, obtener productos filtrados
$busqueda = $_GET['busqueda'] ?? '';
if(!empty($busqueda)) {
    $productos = obtenerProductos($busqueda);
}
?>

<div class="container-xl">
    <div class="encabezado-pagina">
        <div>
            <h1 class="titulo-pagina"><i class="fa fa-truck"></i> Registrar Compra</h1>
            <p class="subtitulo-pagina">Ingresa mercadería: suma la cantidad al stock y actualiza el costo del producto</p>
        </div>
    </div>
    
    <!-- Barra de búsqueda rápida -->
    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title"><i class="fa fa-search"></i> Buscar Producto Rápido</h5>
            <form method="get" action="<?= url('compras/index.php') ?>" class="row g-3">
                <div class="col-md-8">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa fa-barcode"></i></span>
                        <input type="text" name="busqueda" class="form-control" 
                               placeholder="Buscar por código o nombre del producto..." 
                               value="<?php echo htmlspecialchars($busqueda); ?>"
                               id="busquedaRapida">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="d-grid gap-2 d-md-flex">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-search"></i> Buscar
                        </button>
                        <?php if(!empty($busqueda)): ?>
                            <a href="<?= url('compras/index.php') ?>" class="btn btn-secondary">
                                <i class="fa fa-times"></i> Limpiar
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
            <?php if(!empty($busqueda)): ?>
                <div class="mt-2">
                    <small class="text-muted">
                        Mostrando <?php echo count($productos); ?> producto(s) para "<strong><?php echo htmlspecialchars($busqueda); ?></strong>"
                    </small>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Formulario principal de compra -->
    <form method="post" id="formCompra" class="card card-body mb-4">
        <div class="row mb-3">
            <div class="col-md-6">
                <label for="producto" class="form-label">Producto *</label>
                <select name="idProducto" id="producto" class="form-select" required>
                    <option value="" selected disabled>Selecciona un producto</option>
                    <?php if(count($productos) > 0): ?>
                        <?php foreach($productos as $producto): ?>
                            <option value="<?php echo $producto->id; ?>" 
                                    data-codigo="<?php echo htmlspecialchars($producto->codigo); ?>"
                                    data-precio="<?php echo $producto->compra; ?>"
                                    data-existencia="<?php echo htmlspecialchars(cantidadConUnidad($producto->existencia, $producto->unidad)); ?>"
                                    data-unidad="<?php echo htmlspecialchars($producto->unidad); ?>"
                                    data-decimales="<?php echo unidadConDecimales($producto->unidad) ? '1' : '0'; ?>"
                                    data-clasificacion="<?php echo htmlspecialchars(nombreTienda($producto->clasificacion)); ?>">
                                <?php echo htmlspecialchars($producto->nombre . ' (Existencia: ' . cantidadConUnidad($producto->existencia, $producto->unidad) . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <option value="" disabled>No se encontraron productos</option>
                    <?php endif; ?>
                </select>
                <div class="form-text">
                    <i class="fa fa-barcode"></i> Código: <span id="codigoProducto" class="badge bg-info">-</span> | 
                    <i class="fa fa-box"></i> Existencia: <span id="existenciaActual" class="badge bg-secondary">-</span>
                    <!-- Con una sola tienda queda oculto; el script de la página lo sigue actualizando -->
                    <span class="<?= hayVariasTiendas() ? '' : 'd-none' ?>">| <i class="fa fa-tag"></i> Clasificación: <span id="clasificacionProducto" class="badge bg-success">-</span></span>
                </div>
            </div>
            
            <div class="col-md-3">
                <label for="cantidad" class="form-label">Cantidad *</label>
                <div class="input-group">
                    <button class="btn btn-outline-secondary" type="button" id="btnMenos" aria-label="Restar 1">
                        <i class="fa fa-minus"></i>
                    </button>
                    <input type="number" name="cantidad" id="cantidad" class="form-control text-center" 
                           placeholder="Ej. 10" min="1" step="1" value="1" required>
                    <button class="btn btn-outline-secondary" type="button" id="btnMas" aria-label="Sumar 1">
                        <i class="fa fa-plus"></i>
                    </button>
                </div>
            </div>
            
            <div class="col-md-3">
                <label for="precio" class="form-label">Precio Unitario *</label>
                <div class="input-group">
                    <span class="input-group-text"><?= MONEDA ?></span>
                    <input type="number" name="precio" id="precio" class="form-control" 
                           placeholder="Ej. 15.50" step="0.01" min="0">
                </div>
                <div class="form-text">
                    Precio anterior: <?= MONEDA ?><span id="precioAnterior">0.00</span>
                </div>
            </div>
        </div>
        
        <div class="row mb-3">
            <div class="col-md-6">
                <label for="fecha" class="form-label">Fecha de Compra (Opcional)</label>
                <input type="datetime-local" name="fecha" id="fecha" class="form-control">
                <div class="form-text">Si no seleccionas fecha, se usará la fecha y hora actual</div>
            </div>
            
            <div class="col-md-6">
                <label for="notas" class="form-label">Notas (Opcional)</label>
                <textarea name="notas" id="notas" class="form-control" rows="2" 
                          placeholder="Ej. Compra a proveedor XYZ, factura #123"></textarea>
            </div>
        </div>
        
        <div class="card mb-3">
            <div class="card-body">
                <h5 class="card-title"><i class="fa fa-calculator"></i> Resumen de Compra</h5>
                <div class="row">
                    <div class="col-md-6">
                        <p><i class="fa fa-box"></i> Cantidad: <span id="resCantidad">1</span> <span id="resUnidad">und</span></p>
                        <p><i class="fa fa-money-bill"></i> Precio Unitario: <?= MONEDA ?><span id="resPrecio">0.00</span></p>
                    </div>
                    <div class="col-md-6">
                        <h4 class="text-end text-success">
                            <i class="fa fa-shopping-cart"></i> Total: <?= MONEDA ?><span id="resSubtotal">0.00</span>
                        </h4>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="d-flex flex-wrap justify-content-end gap-2 pt-3 border-top">
            <a class="btn btn-outline-secondary btn-lg" href="<?= url('productos/index.php') ?>">
                Cancelar
            </a>
            <button type="submit" name="registrarCompra" class="btn btn-success btn-lg">
                <i class="fa fa-check"></i> Registrar Compra
            </button>
        </div>
    </form>
</div>

<!-- Script para funcionalidades mejoradas -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const productoSelect = document.getElementById('producto');
    const cantidadInput = document.getElementById('cantidad');
    const precioInput = document.getElementById('precio');
    const busquedaInput = document.getElementById('busquedaRapida');
    
    // Elementos para mostrar información
    const codigoSpan = document.getElementById('codigoProducto');
    const existenciaSpan = document.getElementById('existenciaActual');
    const clasificacionSpan = document.getElementById('clasificacionProducto');
    const precioAnteriorSpan = document.getElementById('precioAnterior');
    
    // Elementos para el resumen
    const resCantidad = document.getElementById('resCantidad');
    const resPrecio = document.getElementById('resPrecio');
    const resSubtotal = document.getElementById('resSubtotal');
    const resUnidad = document.getElementById('resUnidad');
    
    // Variables para almacenar precio anterior
    let precioAnterior = 0;
    
    // Función para calcular subtotal
    function calcularSubtotal() {
        const cantidad = parseFloat(cantidadInput.value) || 0;
        const precio = parseFloat(precioInput.value) || 0;
        const subtotal = cantidad * precio;
        
        // Actualizar resumen
        resCantidad.textContent = cantidad;
        resPrecio.textContent = precio.toFixed(2);
        resSubtotal.textContent = subtotal.toFixed(2);
    }
    
    // Cuando se selecciona un producto
    productoSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        if (selectedOption.value) {
            // Mostrar información del producto
            codigoSpan.textContent = selectedOption.getAttribute('data-codigo');
            existenciaSpan.textContent = selectedOption.getAttribute('data-existencia');
            clasificacionSpan.textContent = selectedOption.getAttribute('data-clasificacion');
            
            // Unidad del producto: con decimales acepta fracciones (2.5 m); sin decimales, solo enteros
            const decimales = selectedOption.getAttribute('data-decimales') === '1';
            cantidadInput.step = decimales ? 'any' : '1';
            cantidadInput.min = decimales ? '0.001' : '1';
            if(!decimales) {
                cantidadInput.value = Math.max(1, Math.round(parseFloat(cantidadInput.value) || 1));
            }
            resUnidad.textContent = selectedOption.getAttribute('data-unidad');
            
            // Guardar y mostrar precio anterior
            precioAnterior = parseFloat(selectedOption.getAttribute('data-precio')) || 0;
            precioAnteriorSpan.textContent = precioAnterior.toFixed(2);
            
            // Autocompletar precio
            if(precioAnterior > 0) {
                precioInput.value = precioAnterior;
            } else {
                precioInput.value = '';
                precioInput.focus();
            }
            
            // Calcular subtotal
            calcularSubtotal();
        } else {
            codigoSpan.textContent = '-';
            existenciaSpan.textContent = '-';
            clasificacionSpan.textContent = '-';
            resUnidad.textContent = 'und';
            precioAnteriorSpan.textContent = '0.00';
            precioInput.value = '';
            calcularSubtotal();
        }
    });
    
    // Calcular subtotal cuando cambia cantidad o precio
    cantidadInput.addEventListener('input', calcularSubtotal);
    precioInput.addEventListener('input', calcularSubtotal);
    
    // Botones de cantidad rápida
    document.getElementById('btnMenos').addEventListener('click', function() {
        let cantidad = parseFloat(cantidadInput.value) || 1;
        if(cantidad > 1) {
            cantidadInput.value = Math.round((cantidad - 1) * 1000) / 1000;
            calcularSubtotal();
        }
    });
    
    document.getElementById('btnMas').addEventListener('click', function() {
        let cantidad = parseFloat(cantidadInput.value) || 1;
        cantidadInput.value = Math.round((cantidad + 1) * 1000) / 1000;
        calcularSubtotal();
    });
    
    // Establecer fecha actual por defecto
    const fechaInput = document.getElementById('fecha');
    const now = new Date();
    const localDateTime = new Date(now.getTime() - now.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
    fechaInput.value = localDateTime;
    
    // Busqueda rápida con Enter
    busquedaInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            this.form.submit();
        }
    });
    
    // Autofocus en búsqueda si hay búsqueda previa
    if(busquedaInput.value) {
        busquedaInput.focus();
        busquedaInput.select();
    }
    
    // Prevenir envío del formulario con Enter en campos de número
    cantidadInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            precioInput.focus();
        }
    });
    
    precioInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            fechaInput.focus();
        }
    });
    
    // Inicializar cálculo
    calcularSubtotal();
    
    // Función para buscar producto por código usando Enter en precio
    precioInput.addEventListener('keydown', function(e) {
        // Si presiona Ctrl + Enter en el campo de precio
        if (e.key === 'Enter' && e.ctrlKey) {
            e.preventDefault();
            const codigo = prompt('Ingresa el código del producto:');
            if(codigo) {
                // Buscar producto por código
                window.location.href = '<?= url('compras/index.php') ?>?busqueda=' + encodeURIComponent(codigo);
            }
        }
    });
});

// Función para seleccionar producto por código (puedes llamarla desde consola para debug)
function buscarProductoPorCodigo(codigo) {
    const select = document.getElementById('producto');
    for(let i = 0; i < select.options.length; i++) {
        const option = select.options[i];
        if(option.getAttribute('data-codigo') === codigo) {
            select.value = option.value;
            select.dispatchEvent(new Event('change'));
            return true;
        }
    }
    alert('Producto no encontrado con código: ' + codigo);
    return false;
}
</script>

<?php
// Procesar la compra (mantener el mismo código de procesamiento que antes)
if(isset($_POST['registrarCompra'])) {
    // DEBUG: Mostrar datos recibidos (quitar en producción)
    // echo '<pre>POST: '; print_r($_POST); echo '</pre>';
    // echo '<pre>SESSION: '; print_r($_SESSION); echo '</pre>';
    
    $idProducto = $_POST['idProducto'] ?? null;
    $cantidad = $_POST['cantidad'] ?? 0;
    $precio = $_POST['precio'] ?? 0;
    $fecha = $_POST['fecha'] ?? '';
    $notas = $_POST['notas'] ?? '';
    
    // Validaciones básicas
    if(empty($idProducto) || empty($cantidad) || empty($precio) || $cantidad <= 0 || $precio <= 0) {
        echo '
        <div class="alert alert-danger mt-3" role="alert">
            <i class="fa fa-exclamation-triangle"></i> Por favor completa todos los campos requeridos con valores válidos.
        </div>';
        return;
    }
    
    // Validar que el producto exista
    $producto = obtenerProductoPorId($idProducto);
    if(!$producto) {
        echo '
        <div class="alert alert-danger mt-3" role="alert">
            <i class="fa fa-exclamation-triangle"></i> El producto seleccionado no existe.
        </div>';
        return;
    }
    
    // La cantidad debe ser entera salvo que la unidad admita decimales (metros, kilos...)
    $cantidad = leerCantidad($cantidad, $producto->unidad);
    if($cantidad === null) {
        echo '
        <div class="alert alert-danger mt-3" role="alert">
            <i class="fa fa-exclamation-triangle"></i> La cantidad no es válida: '.htmlspecialchars(nombreUnidad($producto->unidad)).' solo admite números enteros mayores que 0.
        </div>';
        return;
    }
    
    // Obtener ID del usuario en sesión
    if(!isset($_SESSION['usuario']) || empty($_SESSION['usuario'])) {
        echo '
        <div class="alert alert-danger mt-3" role="alert">
            <i class="fa fa-exclamation-triangle"></i> Error de sesión. Por favor inicia sesión nuevamente.
        </div>';
        return;
    }
    
    // Obtener ID de usuario según cómo está almacenado en sesión
    if(is_object($_SESSION['usuario']) && isset($_SESSION['usuario']->id)) {
        $idUsuario = $_SESSION['usuario']->id;
    } elseif(is_array($_SESSION['usuario']) && isset($_SESSION['usuario']['id'])) {
        $idUsuario = $_SESSION['usuario']['id'];
    } else {
        // Si solo tenemos el nombre de usuario, buscar el ID
        $usuarioNombre = $_SESSION['usuario'];
        $idUsuario = obtenerIdUsuarioPorNombre($usuarioNombre);
    }
    
    if(empty($idUsuario)) {
        echo '
        <div class="alert alert-danger mt-3" role="alert">
            <i class="fa fa-exclamation-triangle"></i> No se pudo obtener el ID del usuario.
        </div>';
        return;
    }
    
    // Preparar fecha (usar actual si no se especifica)
    if(empty($fecha)) {
        $fechaCompra = date('Y-m-d H:i:s');
    } else {
        $fechaCompra = date('Y-m-d H:i:s', strtotime($fecha));
    }
    
    // Calcular total
    $total = $cantidad * $precio;
    
    try {
        // Registrar la compra
        $resultadoCompra = registrarCompraIndividual($idProducto, $cantidad, $precio, $fechaCompra, $total, $idUsuario, $notas);
        
        if($resultadoCompra) {
            echo '
            <div class="alert alert-success mt-3" role="alert">
                <h4 class="alert-heading"><i class="fa fa-check-circle"></i> ¡Compra registrada exitosamente!</h4>
                <hr>
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Producto:</strong> '.htmlspecialchars($producto->nombre).'</p>
                        <p><strong>Código:</strong> '.htmlspecialchars($producto->codigo).'</p>
                        '.(hayVariasTiendas() ? '<p><strong>Clasificación:</strong> '.htmlspecialchars(nombreTienda($producto->clasificacion)).'</p>' : '').'
                    </div>
                    <div class="col-md-6">
                        <p><strong>Cantidad:</strong> '.cantidadConUnidad($cantidad, $producto->unidad).'</p>
                        <p><strong>Precio unitario:</strong> '.MONEDA.number_format($precio, 2).'</p>
                        <p><strong>Total compra:</strong> '.MONEDA.number_format($total, 2).'</p>
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Fecha:</strong> '.date('d/m/Y H:i', strtotime($fechaCompra)).'</p>';
            
            if(!empty($notas)) {
                echo '<p><strong>Notas:</strong> '.htmlspecialchars($notas).'</p>';
            }
            
            echo '</div>
                    <div class="col-md-6">
                        <p class="mb-0"><strong>Existencia anterior:</strong> ' . cantidadConUnidad($producto->existencia, $producto->unidad) . '</p>
                        <p class="mb-0"><strong>Nueva existencia:</strong> ' . cantidadConUnidad($producto->existencia + $cantidad, $producto->unidad) . '</p>
                    </div>
                </div>
            </div>';
            
            // Limpiar formulario
            echo '<script>
                setTimeout(function() {
                    document.getElementById("formCompra").reset();
                    document.getElementById("codigoProducto").textContent = "-";
                    document.getElementById("existenciaActual").textContent = "-";
                    document.getElementById("clasificacionProducto").textContent = "-";
                    document.getElementById("precioAnterior").textContent = "0.00";
                    document.getElementById("resCantidad").textContent = "1";
                    document.getElementById("resUnidad").textContent = "und";
                    document.getElementById("cantidad").step = "1";
                    document.getElementById("cantidad").min = "1";
                    document.getElementById("resPrecio").textContent = "0.00";
                    document.getElementById("resSubtotal").textContent = "0.00";
                    // Restaurar fecha actual
                    const now = new Date();
                    const localDateTime = new Date(now.getTime() - now.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
                    document.getElementById("fecha").value = localDateTime;
                }, 100);
            </script>';
        } else {
            echo '
            <div class="alert alert-danger mt-3" role="alert">
                <i class="fa fa-exclamation-triangle"></i> Error al registrar la compra. Por favor intenta nuevamente.
            </div>';
        }
        
    } catch(Exception $e) {
        echo '
        <div class="alert alert-danger mt-3" role="alert">
            <i class="fa fa-exclamation-triangle"></i> Error: ' . htmlspecialchars($e->getMessage()) . '
        </div>';
    }
}
?>