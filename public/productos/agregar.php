<?php
require_once __DIR__ . "/../../app/bootstrap.php";
requerirAdministrador();

// Se procesa antes de imprimir la página para que las redirecciones funcionen
if(isset($_POST['registrar'])){
    $codigo = $_POST['codigo'];
    $nombre = $_POST['nombre'];
    $venta = $_POST['venta'];
    $clasificacion = $_POST['clasificacion'];
    $unidad = $_POST['unidad'] ?? 'und';
    $idCategoria = !empty($_POST['idCategoria']) ? $_POST['idCategoria'] : null;

    // Validación de campos vacíos
    if(empty($codigo) || empty($nombre) || empty($venta) || empty($clasificacion)){
        $_SESSION['mensaje_producto'] = [
            'tipo' => 'danger',
            'texto' => 'Debes completar todos los datos.'
        ];
        redirigir("productos/agregar.php");
    }

    // Validar que el precio sea positivo
    if(floatval($venta) <= 0) {
        $_SESSION['mensaje_producto'] = [
            'tipo' => 'danger',
            'texto' => 'El precio de venta debe ser mayor a 0.'
        ];
        redirigir("productos/agregar.php");
    }

    // La unidad tiene que estar en config/config.php y la categoría tiene que existir
    if(!array_key_exists($unidad, UNIDADES) || ($idCategoria !== null && !obtenerCategoriaPorId($idCategoria))) {
        $_SESSION['mensaje_producto'] = [
            'tipo' => 'danger',
            'texto' => 'La unidad o la categoría elegida no es válida.'
        ];
        redirigir("productos/agregar.php");
    }

    // Verificar si el código de barras ya existe
    $productoExistente = obtenerProductoPorCodigo($codigo);
    if($productoExistente){
        $_SESSION['mensaje_producto'] = [
            'tipo' => 'danger',
            'texto' => 'El código de barras ya existe en el sistema. Por favor, ingresa uno diferente.'
        ];
        redirigir("productos/agregar.php");
    }

    // Registrar el producto
    $resultado = registrarProducto($codigo, $nombre, $venta, $clasificacion, $unidad, $idCategoria);
    if($resultado){
        $_SESSION['mensaje_producto'] = [
            'tipo' => 'success',
            'texto' => 'Producto registrado con éxito.'
        ];
    } else {
        $_SESSION['mensaje_producto'] = [
            'tipo' => 'danger',
            'texto' => 'Error al registrar el producto. Por favor, intente nuevamente.'
        ];
    }
    // Redirigir para evitar reenvío del formulario
    redirigir("productos/agregar.php");
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
                <div class="card-header bg-primary text-white py-3">
                    <div class="d-flex align-items-center">
                        <i class="fa fa-plus-circle fa-2x me-3"></i>
                        <div>
                            <h4 class="mb-0">Agregar Nuevo Producto</h4>
                            <small class="opacity-75">Complete todos los campos requeridos</small>
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
                    <form method="post" id="formAgregarProducto">
                        <!-- Información del formulario -->
                        <div class="alert alert-info mb-4">
                            <div class="d-flex">
                                <i class="fa fa-info-circle fa-lg me-3 mt-1"></i>
                                <div>
                                    <strong>Información importante:</strong>
                                    <ul class="mb-0 mt-1">
                                        <li>Código, nombre y precio son obligatorios; la categoría es opcional</li>
                                        <li>El código debe ser único</li>
                                        <li>El precio es por unidad de venta (por metro, por kilo...)</li>
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
                                   placeholder="Escribe el código del producto"
                                   autocomplete="off">
                            <div class="form-text">
                                Este código debe ser único para cada producto.
                            </div>
                        </div>

                        <!-- Campo Nombre -->
                        <div class="mb-4">
                            <label for="nombre" class="form-label fw-bold">
                                <i class="fa fa-tag me-2"></i>Nombre del producto
                            </label>
                            <input type="text" name="nombre" class="form-control form-control-lg" id="nombre"
                                   placeholder="Ej. Martillo de uña 16 oz, Cemento 42.5 kg, Tubo PVC 1/2&quot;"
                                   autocomplete="off">
                            <div class="form-text">
                                Escriba el nombre completo del producto.
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
                                    <option value="" selected disabled>Selecciona una opción</option>
                                    <?php foreach(TIENDAS as $clave => $tienda): ?>
                                    <option value="<?= $clave ?>"><?= htmlspecialchars($tienda['nombre']) ?></option>
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
                                    <option value="<?= htmlspecialchars($clave) ?>"><?= htmlspecialchars($unidad['nombre']) ?> (<?= htmlspecialchars($clave) ?>)<?= $unidad['decimales'] ? ' · fraccionable' : '' ?></option>
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
                                    <option value="<?= $categoria->id ?>"><?= htmlspecialchars($categoria->nombre) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text mt-1">
                                    <a href="<?= url('categorias/index.php') ?>">Administrar categorías</a>
                                </div>
                            </div>
                        </div>

                        <!-- Botones de acción -->
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4 pt-3 border-top">
                            <a class="btn btn-outline-secondary btn-lg px-4" href="<?= url('productos/index.php') ?>">
                                <i class="fa fa-times me-2"></i> Cancelar
                            </a>
                            <button type="submit" name="registrar" class="btn btn-primary btn-lg px-5">
                                <i class="fa fa-save me-2"></i> Registrar Producto
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Resumen de lo que se va a registrar -->
            <div class="card shadow-sm border-0 mt-4">
                <div class="card-header bg-light py-3">
                    <h6 class="mb-0">
                        <i class="fa fa-eye me-2"></i> Vista previa del producto
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 text-center">
                            <div class="display-1 text-muted opacity-25">
                                <i class="fa fa-box"></i>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <table class="table table-sm">
                                <tr>
                                    <th width="40%">Código:</th>
                                    <td id="preview-codigo" class="text-muted">Por completar</td>
                                </tr>
                                <tr>
                                    <th>Nombre:</th>
                                    <td id="preview-nombre" class="text-muted">Por completar</td>
                                </tr>
                                <tr>
                                    <th>Precio Venta:</th>
                                    <td id="preview-venta" class="text-muted">Por completar</td>
                                </tr>
                                <tr>
                                    <th>Unidad:</th>
                                    <td id="preview-unidad" class="text-muted">Por completar</td>
                                </tr>
                                <tr>
                                    <th>Categoría:</th>
                                    <td id="preview-idCategoria" class="text-muted">Por completar</td>
                                </tr>
                                <?php if(hayVariasTiendas()): ?>
                                <tr>
                                    <th>Clasificación:</th>
                                    <td id="preview-clasificacion" class="text-muted">Por completar</td>
                                </tr>
                                <?php endif; ?>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tiendas definidas en config/config.php
    const tiendas = <?= json_encode(TIENDAS, JSON_HEX_TAG) ?>;

    // Auto-focus en el campo código
    document.getElementById('codigo').focus();

    // Vista previa en tiempo real
    const inputs = ['codigo', 'nombre', 'venta', 'clasificacion', 'unidad', 'idCategoria'];

    inputs.forEach(inputId => {
        const input = document.getElementById(inputId);
        const preview = document.getElementById('preview-' + inputId);

        if(input) {
            input.addEventListener('input', function() {
                updatePreview(inputId, this.value);
            });

            input.addEventListener('change', function() {
                updatePreview(inputId, this.value);
            });
        }
    });

    function updatePreview(field, value) {
        const previewElement = document.getElementById('preview-' + field);

        // La categoría es opcional: vacía significa "Sin categoría"
        if(field === 'idCategoria' && !value) {
            previewElement.textContent = 'Sin categoría';
            previewElement.className = 'text-muted';
            return;
        }

        if(!value || value.trim() === '') {
            previewElement.textContent = 'Por completar';
            previewElement.className = 'text-muted';
            return;
        }

        previewElement.className = 'fw-bold text-primary';

        switch(field) {
            case 'codigo':
                previewElement.textContent = value;
                break;
            case 'nombre':
                previewElement.textContent = value;
                break;
            case 'venta':
                previewElement.textContent = <?= json_encode(MONEDA) ?> + parseFloat(value || 0).toFixed(2)
                    + ' / ' + document.getElementById('unidad').value;
                break;
            case 'unidad':
            case 'idCategoria': {
                const select = document.getElementById(field);
                previewElement.textContent = select.options[select.selectedIndex].text;
                // El precio se muestra "por unidad"
                if(field === 'unidad' && document.getElementById('venta').value) {
                    updatePreview('venta', document.getElementById('venta').value);
                }
                break;
            }
            case 'clasificacion':
                if(tiendas[value]) {
                    const badge = document.createElement('span');
                    badge.className = 'badge ' + tiendas[value].badge;
                    badge.textContent = tiendas[value].nombre;
                    previewElement.replaceChildren(badge);
                } else {
                    previewElement.textContent = 'Por completar';
                    previewElement.className = 'text-muted';
                }
                break;
        }
    }

    // Los selectores ya traen un valor elegido
    updatePreview('unidad', document.getElementById('unidad').value);
    updatePreview('idCategoria', document.getElementById('idCategoria').value);

    // Validación del formulario
    const form = document.getElementById('formAgregarProducto');
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
