<?php
// Registrar un ajuste de inventario (solo administradores). Sin ?id muestra un buscador de productos;
// con ?id=ID del producto, el formulario: motivo, cantidad (contada, a quitar o a agregar) y notas.
require_once __DIR__ . "/../../app/bootstrap.php";
requerirAdministrador();

if(isset($_POST['registrar'])){
    $idProducto = (int) ($_POST['idProducto'] ?? 0);
    $motivo = $_POST['motivo'] ?? '';
    $notas = mb_substr(trim($_POST['notas'] ?? ''), 0, 255);
    $producto = obtenerProductoPorId($idProducto);
    $tipo = MOTIVOS_AJUSTE[$motivo]['tipo'] ?? null;

    // En un conteo la cantidad puede ser 0 (no quedó nada); al quitar o agregar debe ser mayor que 0
    $valor = trim((string) ($_POST['cantidad'] ?? ''));
    $cantidad = null;
    if($producto){
        $cantidad = ($tipo === 'fijar' && is_numeric(str_replace(',', '.', $valor)) && (float) str_replace(',', '.', $valor) == 0)
            ? 0.0
            : leerCantidad($valor, $producto->unidad);
    }

    if(!$producto){
        guardarMensaje('danger', 'El producto no existe.');
        redirigir('ajustes/nuevo.php');
    } elseif(!$tipo){
        guardarMensaje('danger', 'Elige el motivo del ajuste.');
    } elseif($cantidad === null){
        guardarMensaje('danger', unidadConDecimales($producto->unidad)
            ? 'Escribe una cantidad válida.'
            : 'Escribe una cantidad válida: la unidad "' . $producto->unidad . '" solo admite números enteros.');
    } else {
        $resultado = registrarAjuste($producto->id, $motivo, $cantidad, $notas === '' ? null : $notas, $_SESSION['idUsuario']);
        if(is_array($resultado)){
            guardarMensaje('success', 'Ajuste registrado: ' . $producto->nombre . ' pasó de ' . cantidadConUnidad($resultado['anterior'], $producto->unidad)
                . ' a ' . cantidadConUnidad($resultado['nueva'], $producto->unidad) . ' (' . nombreMotivoAjuste($motivo) . ').');
            redirigir('ajustes/index.php');
        }
        guardarMensaje('danger', $resultado);
    }
    redirigir('ajustes/nuevo.php?id=' . $producto->id . '&motivo=' . urlencode($motivo));
}

$producto = isset($_GET['id']) ? obtenerProductoPorId($_GET['id']) : null;
$busqueda = trim($_GET['busqueda'] ?? '');
$encontrados = !$producto && $busqueda !== '' ? obtenerProductos($busqueda) : [];
$motivoElegido = $_GET['motivo'] ?? 'conteo';

include_once VISTAS_DIR . "/encabezado.php";
include_once VISTAS_DIR . "/navbar.php";
?>
<div class="container-xl">
    <?php mostrarMensaje(); ?>

    <div class="encabezado-pagina">
        <div>
            <h1 class="titulo-pagina"><i class="fa fa-sliders-h"></i> Nuevo ajuste de inventario</h1>
            <p class="subtitulo-pagina">Para corregir la existencia por un conteo físico, una merma, una pérdida o un ingreso sin compra</p>
        </div>
        <div class="acciones-pagina">
            <a href="<?= url('ajustes/index.php') ?>" class="btn btn-outline-secondary"><i class="fa fa-history"></i> Ajustes registrados</a>
        </div>
    </div>

    <?php if(!$producto): ?>
        <form method="get" class="barra-filtros">
            <label for="busqueda" class="form-label">Producto a ajustar</label>
            <div class="input-group">
                <input type="text" class="form-control" name="busqueda" id="busqueda" value="<?= htmlspecialchars($busqueda) ?>"
                       placeholder="Código o nombre del producto" autofocus>
                <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> Buscar</button>
            </div>
        </form>

        <?php if($busqueda !== ''): ?>
        <div class="card">
            <?php if($encontrados): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Producto</th>
                            <th class="text-end">Existencia</th>
                            <th class="text-end"><span class="visually-hidden">Acciones</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($encontrados as $encontrado): ?>
                        <tr>
                            <td class="text-muted"><?= htmlspecialchars($encontrado->codigo) ?></td>
                            <td class="fw-semibold"><?= htmlspecialchars($encontrado->nombre) ?></td>
                            <td class="text-end"><?= cantidadConUnidad($encontrado->existencia, $encontrado->unidad) ?></td>
                            <td class="text-end">
                                <a href="<?= url('ajustes/nuevo.php') ?>?id=<?= $encontrado->id ?>" class="btn btn-outline-primary btn-sm">
                                    <i class="fa fa-sliders-h"></i> Ajustar
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="card-body estado-vacio">
                <i class="fa fa-search"></i>
                <h3>No se encontró ningún producto</h3>
                <p class="mb-0">Prueba con otra parte del nombre o con el código.</p>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    <?php else: ?>
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header"><i class="fa fa-box text-muted me-1"></i> Producto</div>
                    <div class="card-body">
                        <dl class="datos-lista">
                            <dt>Código</dt><dd><?= htmlspecialchars($producto->codigo) ?></dd>
                            <dt>Nombre</dt><dd class="fw-semibold"><?= htmlspecialchars($producto->nombre) ?></dd>
                            <dt>Unidad</dt><dd><?= htmlspecialchars(nombreUnidad($producto->unidad)) ?></dd>
                            <dt>Existencia</dt><dd class="fw-semibold"><?= cantidadConUnidad($producto->existencia, $producto->unidad) ?></dd>
                            <dt>Costo</dt><dd><?= MONEDA . number_format($producto->compra, 2) ?></dd>
                        </dl>
                        <a href="<?= url('ajustes/nuevo.php') ?>" class="btn btn-link btn-sm px-0 mt-2"><i class="fa fa-exchange-alt"></i> Elegir otro producto</a>
                    </div>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header"><i class="fa fa-sliders-h text-muted me-1"></i> Ajuste</div>
                    <div class="card-body">
                        <form method="post" id="formAjuste"
                              data-existencia="<?= (float) $producto->existencia ?>" data-unidad="<?= htmlspecialchars($producto->unidad) ?>">
                            <input type="hidden" name="idProducto" value="<?= $producto->id ?>">

                            <label for="motivo" class="form-label">Motivo</label>
                            <select class="form-select mb-3" name="motivo" id="motivo" required>
                                <?php foreach(MOTIVOS_AJUSTE as $clave => $motivo): ?>
                                <option value="<?= $clave ?>" data-tipo="<?= $motivo['tipo'] ?>" <?= $motivoElegido === $clave ? 'selected' : '' ?>><?= htmlspecialchars($motivo['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>

                            <label for="cantidad" class="form-label" id="etiquetaCantidad">Cantidad contada</label>
                            <div class="input-group mb-2">
                                <input type="number" class="form-control" name="cantidad" id="cantidad" required
                                       step="<?= unidadConDecimales($producto->unidad) ? 'any' : '1' ?>" min="0" inputmode="decimal" autocomplete="off">
                                <span class="input-group-text"><?= htmlspecialchars($producto->unidad) ?></span>
                            </div>
                            <div class="diferencia-caja mb-3" id="vistaPrevia" aria-live="polite">
                                Existencia actual: <?= cantidadConUnidad($producto->existencia, $producto->unidad) ?>
                            </div>

                            <label for="notas" class="form-label">Notas <span class="text-muted">(opcional)</span></label>
                            <input type="text" class="form-control mb-3" name="notas" id="notas" maxlength="255"
                                   placeholder="Ej. 2 bolsas mojadas por la lluvia">

                            <div class="d-flex justify-content-end gap-2">
                                <a href="<?= url('ajustes/index.php') ?>" class="btn btn-outline-secondary">Cancelar</a>
                                <button type="submit" name="registrar" value="1" class="btn btn-primary">
                                    <i class="fa fa-check"></i> Registrar ajuste
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php if($producto): ?>
<script>
// La etiqueta de la cantidad y la vista previa cambian según el motivo: contar, quitar o agregar
(function() {
    const form = document.getElementById('formAjuste');
    const existencia = parseFloat(form.dataset.existencia);
    const unidad = form.dataset.unidad;
    const motivo = document.getElementById('motivo');
    const cantidad = document.getElementById('cantidad');
    const etiqueta = document.getElementById('etiquetaCantidad');
    const vista = document.getElementById('vistaPrevia');
    const etiquetas = {fijar: 'Cantidad contada', quitar: 'Cantidad a quitar', agregar: 'Cantidad a agregar'};
    const formato = n => (Math.round(n * 1000) / 1000).toLocaleString('en-US', {maximumFractionDigits: 3}) + ' ' + unidad;

    function actualizar() {
        const tipo = motivo.selectedOptions[0].dataset.tipo;
        etiqueta.textContent = etiquetas[tipo];
        cantidad.min = tipo === 'fijar' ? '0' : (cantidad.step === 'any' ? '0.001' : '1');
        const valor = parseFloat(cantidad.value.replace(',', '.'));
        if(isNaN(valor)) {
            vista.className = 'diferencia-caja mb-3';
            vista.textContent = 'Existencia actual: ' + formato(existencia);
            return;
        }
        const nueva = tipo === 'fijar' ? valor : (tipo === 'quitar' ? existencia - valor : existencia + valor);
        const diferencia = Math.round((nueva - existencia) * 1000) / 1000;
        if(nueva < 0) {
            vista.className = 'diferencia-caja diferencia-caja--falta mb-3';
            vista.textContent = 'No se puede quitar más de ' + formato(existencia);
            return;
        }
        vista.className = 'diferencia-caja mb-3 ' + (diferencia < 0 ? 'diferencia-caja--falta' : (diferencia > 0 ? 'diferencia-caja--sobra' : ''));
        vista.textContent = 'Existencia: ' + formato(existencia) + ' → ' + formato(nueva)
            + (diferencia === 0 ? ' (sin cambio)' : ' (' + (diferencia > 0 ? '+' : '−') + formato(Math.abs(diferencia)) + ')');
    }
    motivo.addEventListener('change', actualizar);
    cantidad.addEventListener('input', actualizar);
    actualizar();
    cantidad.focus();
})();
</script>
<?php endif; ?>
