<?php
require_once __DIR__ . "/../../app/bootstrap.php";
requerirAdministrador();

$id = $_GET['id'] ?? null;
if (!$id) {
    $_SESSION['mensaje_producto'] = [
        'tipo' => 'danger',
        'texto' => 'No se ha seleccionado el producto'
    ];
    redirigir("productos/index.php");
}

$producto = obtenerProductoPorId($id);
if(!$producto) {
    $_SESSION['mensaje_producto'] = [
        'tipo' => 'danger',
        'texto' => 'Producto no encontrado'
    ];
    redirigir("productos/index.php");
}

// Se procesa antes de imprimir la página para que las redirecciones funcionen
if(isset($_POST['registrar'])){
    $codigo = $_POST['codigo'];
    $nombre = $_POST['nombre'];
    $venta = $_POST['venta'];
    $clasificacion = $_POST['clasificacion'];
    $unidad = $_POST['unidad'] ?? 'und';
    $idCategoria = !empty($_POST['idCategoria']) ? $_POST['idCategoria'] : null;
    $id = $_POST['id'] ?? $id;
    $paginaEdicion = url("productos/editar.php") . "?id=" . urlencode($id);

    // Validación de campos vacíos
    if(empty($codigo) || empty($nombre) || empty($venta) || empty($clasificacion)){
        $_SESSION['mensaje_producto'] = [
            'tipo' => 'danger',
            'texto' => 'Debes completar todos los datos.'
        ];
        header("Location: " . $paginaEdicion);
        exit;
    }

    // Validar que el precio sea positivo
    if(floatval($venta) <= 0) {
        $_SESSION['mensaje_producto'] = [
            'tipo' => 'danger',
            'texto' => 'El precio de venta debe ser mayor a 0.'
        ];
        header("Location: " . $paginaEdicion);
        exit;
    }

    // La unidad tiene que estar en config/config.php y la categoría tiene que existir
    if(!array_key_exists($unidad, UNIDADES) || ($idCategoria !== null && !obtenerCategoriaPorId($idCategoria))) {
        $_SESSION['mensaje_producto'] = [
            'tipo' => 'danger',
            'texto' => 'La unidad o la categoría elegida no es válida.'
        ];
        header("Location: " . $paginaEdicion);
        exit;
    }

    // Verificar si el código de barras ya existe (excepto para este producto)
    $productoExistente = obtenerProductoPorCodigo($codigo);
    if($productoExistente && $productoExistente->id != $id){
        $_SESSION['mensaje_producto'] = [
            'tipo' => 'danger',
            'texto' => 'El código de barras ya existe en otro producto. Por favor, ingresa uno diferente.'
        ];
        header("Location: " . $paginaEdicion);
        exit;
    }

    // Editar el producto
    $resultado = editarProducto($codigo, $nombre, $venta, $clasificacion, $unidad, $idCategoria, $id);
    if($resultado){
        $_SESSION['mensaje_producto'] = [
            'tipo' => 'success',
            'texto' => 'Producto actualizado con éxito.'
        ];
    } else {
        $_SESSION['mensaje_producto'] = [
            'tipo' => 'danger',
            'texto' => 'Error al actualizar el producto. Por favor, intente nuevamente.'
        ];
    }
    header("Location: " . $paginaEdicion);
    exit;
}

include_once VISTAS_DIR . "/encabezado.php";
include_once VISTAS_DIR . "/navbar.php";

$categorias = obtenerCategorias();

// Verificar si hay mensaje de éxito
if(isset($_SESSION['mensaje_producto'])) {
    $mensaje = $_SESSION['mensaje_producto'];
    unset($_SESSION['mensaje_producto']);
}
?>
<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-md-10">
            <!-- Tarjeta del formulario -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-warning text-dark py-3">
                    <div class="d-flex align-items-center">
                        <i class="fa fa-edit fa-2x me-3"></i>
                        <div>
                            <h4 class="mb-0">Editar Producto</h4>
                            <small class="opacity-75">Modifique los campos que desee actualizar</small>
                        </div>
                    </div>
                </div>

                <!-- Mostrar mensaje si existe -->
                <?php if(isset($mensaje)): ?>
                    <div class="alert alert-<?php echo $mensaje['tipo']; ?> alert-dismissible fade show m-3 mb-0" role="alert">
                        <i class="fa fa-<?php echo $mensaje['tipo'] == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                        <?php echo $mensaje['texto']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                    </div>
                <?php endif; ?>

                <div class="card-body p-4">
                    <form method="post" id="formEditarProducto">
                        <!-- Información del producto actual -->
                        <div class="alert alert-info mb-4">
                            <div class="d-flex">
                                <i class="fa fa-info-circle fa-lg me-3 mt-1"></i>
                                <div>
                                    <strong>Editando producto:</strong> <span class="fw-bold"><?php echo htmlspecialchars($producto->nombre); ?></span>
                                    <ul class="mb-0 mt-1">
                                        <li>Los cambios se aplicarán inmediatamente</li>
                                        <li>Verifique que los datos sean correctos</li>
                                        <li>El código de barras debe ser único</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Campo Código -->
                        <div class="mb-4">
                            <label for="codigo" class="form-label fw-bold">
                                <i class="fa fa-barcode me-2"></i>Código del producto
                            </label>
                            <input type="text" name="codigo" class="form-control form-control-lg" id="codigo"
                                   value="<?php echo htmlspecialchars($producto->codigo); ?>"
                                   placeholder="Código de barras único"
                                   autocomplete="off">
                            <div class="form-text">
                                Identificador único del producto en el sistema.
                            </div>
                        </div>

                        <!-- Campo Nombre -->
                        <div class="mb-4">
                            <label for="nombre" class="form-label fw-bold">
                                <i class="fa fa-tag me-2"></i>Nombre del producto
                            </label>
                            <input type="text" name="nombre" class="form-control form-control-lg" id="nombre"
                                   value="<?php echo htmlspecialchars($producto->nombre); ?>"
                                   placeholder="Nombre completo del producto"
                                   autocomplete="off">
                            <div class="form-text">
                                Nombre descriptivo del producto.
                            </div>
                        </div>

                        <!-- Precio y Clasificación en una fila -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label for="venta" class="form-label fw-bold">
                                    <i class="fa fa-money-bill-wave me-2"></i>Precio de venta
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><?= MONEDA ?></span>
                                    <input type="number" name="venta" step="any" id="venta"
                                           class="form-control form-control-lg"
                                           value="<?php echo number_format($producto->venta, 2, '.', ''); ?>"
                                           placeholder="0.00"
                                           min="0">
                                </div>
                                <div class="form-text mt-1">
                                    Precio al que se venderá el producto.
                                </div>
                            </div>
                            <?php if(hayVariasTiendas()): ?>
                            <div class="col-md-6">
                                <label for="clasificacion" class="form-label fw-bold">
                                    <i class="fa fa-store me-2"></i>Clasificación
                                </label>
                                <select name="clasificacion" id="clasificacion" class="form-select form-select-lg" required>
                                    <option value="" disabled>Selecciona una opción</option>
                                    <?php foreach(TIENDAS as $clave => $tienda): ?>
                                    <option value="<?= $clave ?>" <?php echo $producto->clasificacion == $clave ? 'selected' : ''; ?>><?= htmlspecialchars($tienda['nombre']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text mt-1">
                                    Indique a qué tienda pertenece el producto.
                                </div>
                            </div>
                            <?php else: ?>
                            <input type="hidden" name="clasificacion" id="clasificacion" value="<?= htmlspecialchars(tiendaPrincipal()) ?>">
                            <?php endif; ?>
                        </div>

                        <!-- Unidad y Categoría en una fila -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label for="unidad" class="form-label fw-bold">
                                    <i class="fa fa-ruler me-2"></i>Unidad de venta
                                </label>
                                <select name="unidad" id="unidad" class="form-select form-select-lg">
                                    <?php foreach(UNIDADES as $clave => $unidad): ?>
                                    <option value="<?= htmlspecialchars($clave) ?>" <?php echo $producto->unidad == $clave ? 'selected' : ''; ?>><?= htmlspecialchars($unidad['nombre']) ?> (<?= htmlspecialchars($clave) ?>)<?= $unidad['decimales'] ? ' · fraccionable' : '' ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text mt-1">
                                    Las fraccionables se pueden vender por partes (2.5 m, 0.25 kg).
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="idCategoria" class="form-label fw-bold">
                                    <i class="fa fa-tags me-2"></i>Categoría
                                </label>
                                <select name="idCategoria" id="idCategoria" class="form-select form-select-lg">
                                    <option value="">Sin categoría</option>
                                    <?php foreach($categorias as $categoria): ?>
                                    <option value="<?= $categoria->id ?>" <?php echo $producto->idCategoria == $categoria->id ? 'selected' : ''; ?>><?= htmlspecialchars($categoria->nombre) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text mt-1">
                                    <a href="<?= url('categorias/index.php') ?>">Administrar categorías</a>
                                </div>
                            </div>
                        </div>

                        <!-- Información adicional del producto -->
                        <div class="card border-secondary mb-4">
                            <div class="card-header bg-light py-2">
                                <i class="fa fa-info-circle me-2"></i>Información actual del producto
                            </div>
                            <div class="card-body">
                                <div class="row small">
                                    <div class="col-md-4">
                                        <strong>Precio compra:</strong><br>
                                        <span class="text-primary"><?= MONEDA ?><?php echo number_format($producto->compra, 2); ?></span>
                                    </div>
                                    <div class="col-md-4">
                                        <strong>Existencia:</strong><br>
                                        <span class="badge <?php echo $producto->existencia > 0 ? 'bg-success' : 'bg-danger'; ?>">
                                            <?php echo cantidadConUnidad($producto->existencia, $producto->unidad); ?>
                                        </span>
                                    </div>
                                    <div class="col-md-4">
                                        <strong>Ganancia por <?php echo htmlspecialchars($producto->unidad); ?>:</strong><br>
                                        <span class="<?php echo ($producto->venta - $producto->compra) >= 0 ? 'text-success' : 'text-danger'; ?>">
                                            <?= MONEDA ?><?php echo number_format($producto->venta - $producto->compra, 2); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Botones de acción -->
                        <div class="d-grid gap-2 d-md-flex justify-content-md-between mt-4 pt-3 border-top">
                            <div>
                                <a class="btn btn-outline-secondary btn-lg" href="<?= url('productos/index.php') ?>">
                                    <i class="fa fa-arrow-left me-2"></i> Volver a productos
                                </a>
                            </div>
                            <div>
                                <a class="btn btn-outline-secondary btn-lg px-4" href="<?= url('productos/index.php') ?>">
                                    <i class="fa fa-times me-2"></i> Cancelar
                                </a>
                                <button type="submit" name="registrar" class="btn btn-primary btn-lg px-5">
                                    <i class="fa fa-save me-2"></i> Guardar Cambios
                                </button>
                            </div>
                        </div>

                        <!-- Campo oculto para ID -->
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($id); ?>">
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-focus en el primer campo
    document.getElementById('codigo').focus();

    // Validación del formulario
    const form = document.getElementById('formEditarProducto');
    if(form) {
        form.addEventListener('submit', function(e) {
            const codigo = document.getElementById('codigo').value.trim();
            const nombre = document.getElementById('nombre').value.trim();
            const venta = document.getElementById('venta').value.trim();
            const clasificacion = document.getElementById('clasificacion').value;

            let errores = [];

            if(!codigo) errores.push('El código de barras es requerido');
            if(!nombre) errores.push('El nombre del producto es requerido');
            if(!venta || parseFloat(venta) <= 0) errores.push('El precio de venta debe ser mayor a 0');
            if(!clasificacion) errores.push('Debe seleccionar una clasificación');

            if(errores.length > 0) {
                e.preventDefault();
                alert('Por favor corrija los siguientes errores:\n\n• ' + errores.join('\n• '));
            }
        });
    }

    // Formatear precio mientras se escribe
    const precioInput = document.getElementById('venta');
    if(precioInput) {
        precioInput.addEventListener('blur', function() {
            if(this.value) {
                const valor = parseFloat(this.value);
                if(!isNaN(valor)) {
                    this.value = valor.toFixed(2);
                }
            }
        });
    }
});
</script>
