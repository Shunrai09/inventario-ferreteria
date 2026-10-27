<?php
// Importar el inventario desde Excel (.xlsx) o CSV, en dos pasos: revisar (vista previa) y confirmar.
// Las filas leídas del archivo se guardan en la sesión entre un paso y otro; al confirmar se vuelven a validar
// contra la base, por si algo cambió mientras tanto.
require_once __DIR__ . "/../../app/bootstrap.php";
requerirAdministrador();

if(isset($_GET['plantilla'])){
    enviarExcel('plantilla_inventario.xlsx', generarExcel('Inventario', columnasPlantillaImportacion(), []));
}

if(isset($_POST['revisar'])){
    try {
        $_SESSION['importacion'] = [
            'archivo' => basename($_FILES['archivo']['name'] ?? ''),
            'filas' => leerArchivoInventario($_FILES['archivo'] ?? []),
        ];
    } catch(RuntimeException $e) {
        unset($_SESSION['importacion']);
        $_SESSION['mensaje_importacion'] = ['tipo' => 'danger', 'texto' => $e->getMessage()];
    }
    redirigir("productos/importar.php");
}

if(isset($_POST['cancelar'])){
    unset($_SESSION['importacion']);
    redirigir("productos/importar.php");
}

if(isset($_POST['confirmar']) && isset($_SESSION['importacion'])){
    $preparada = prepararImportacion($_SESSION['importacion']['filas']);
    if(isset($preparada['error'])){
        $_SESSION['mensaje_importacion'] = ['tipo' => 'danger', 'texto' => $preparada['error']];
    } else {
        $resultado = aplicarImportacion($preparada, $_SESSION['idUsuario']);
        $texto = "Importación terminada: {$resultado['nuevos']} producto(s) nuevo(s) y {$resultado['actualizados']} actualizado(s)";
        if($resultado['categorias']){
            $texto .= ", {$resultado['categorias']} categoría(s) creada(s)";
        }
        if($preparada['resumen']['error']){
            $texto .= ". Se omitieron {$preparada['resumen']['error']} fila(s) con errores";
        }
        $_SESSION['mensaje_importacion'] = ['tipo' => 'success', 'texto' => $texto . '.'];
    }
    unset($_SESSION['importacion']);
    redirigir("productos/importar.php");
}

include_once VISTAS_DIR . "/encabezado.php";
include_once VISTAS_DIR . "/navbar.php";

if(isset($_SESSION['mensaje_importacion'])){
    $mensaje = $_SESSION['mensaje_importacion'];
    unset($_SESSION['mensaje_importacion']);
}
$preparada = isset($_SESSION['importacion']) ? prepararImportacion($_SESSION['importacion']['filas']) : null;
if(isset($preparada['error'])){
    $mensaje = ['tipo' => 'danger', 'texto' => $preparada['error']];
    unset($_SESSION['importacion']);
    $preparada = null;
}

$estados = [
    'nuevo'       => ['Nuevo', 'bg-success'],
    'actualizar'  => ['Actualiza', 'bg-primary'],
    'sin_cambios' => ['Sin cambios', 'bg-light text-dark border'],
    'error'       => ['Error', 'bg-danger'],
];
// Valor nuevo y, si cambia, el anterior tachado
$conCambio = function($fila, $campo, $texto, $anterior) {
    if(!array_key_exists($campo, $fila['cambios'])) return htmlspecialchars($texto);
    return '<del class="text-muted small">' . htmlspecialchars($anterior) . '</del> <strong class="text-primary">' . htmlspecialchars($texto) . '</strong>';
};
?>
<div class="container-xl">
    <?php if(isset($mensaje)): ?>
        <div class="alert alert-<?= $mensaje['tipo'] ?> alert-dismissible fade show" role="alert">
            <i class="fa fa-<?= $mensaje['tipo'] === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
            <?= htmlspecialchars($mensaje['texto']) ?>
            <?php if($mensaje['tipo'] === 'success'): ?>
                <a href="<?= url('productos/index.php') ?>" class="alert-link ms-2">Ver productos</a>
            <?php endif; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endif; ?>

    <div class="encabezado-pagina">
        <div>
            <h1 class="titulo-pagina"><i class="fa fa-file-import"></i> Importar inventario</h1>
            <p class="subtitulo-pagina">Carga o actualiza productos desde Excel (.xlsx) o CSV, con vista previa antes de guardar</p>
        </div>
        <div class="acciones-pagina">
            <a href="<?= url('productos/index.php') ?>" class="btn btn-outline-secondary"><i class="fa fa-arrow-left"></i> Volver a productos</a>
        </div>
    </div>

    <?php if(!$preparada): ?>
    <!-- Paso 1: subir el archivo -->
    <div class="row g-4 mt-1">
        <div class="col-lg-5">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">1. Elige tu archivo</h5>
                    <form method="post" enctype="multipart/form-data">
                        <label for="archivo" class="form-label">Archivo de Excel (.xlsx) o CSV</label>
                        <input type="file" name="archivo" id="archivo" class="form-control mb-3" accept=".xlsx,.csv" required>
                        <button type="submit" name="revisar" class="btn btn-primary w-100">
                            <i class="fa fa-search"></i> Revisar archivo
                        </button>
                    </form>
                    <p class="small text-muted mt-3 mb-2">
                        Excel (.xlsx) o CSV. Nada se guarda hasta que revises la vista previa y confirmes.
                    </p>
                    <a href="<?= url('productos/importar.php') ?>?plantilla=1" class="btn btn-outline-success btn-sm">
                        <i class="fa fa-file-excel"></i> Descargar plantilla vacía
                    </a>
                    <a href="<?= url('productos/exportar.php') ?>" class="btn btn-outline-secondary btn-sm">
                        <i class="fa fa-download"></i> Exportar inventario actual
                    </a>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Cómo debe ser el archivo</h5>
                    <p class="small mb-2">
                        La primera fila lleva los encabezados. Las columnas se reconocen por su nombre, en cualquier orden;
                        las demás se ignoran. Puedes <strong>exportar tu inventario, corregirlo en Excel y volver a importarlo</strong>.
                    </p>
                    <table class="table table-sm small mb-2">
                        <thead class="table-light"><tr><th>Columna</th><th>¿Obligatoria?</th><th>Qué hace</th></tr></thead>
                        <tbody>
                            <tr><td><strong>Código</strong></td><td>Sí</td><td>Si el código ya existe, el producto se actualiza; si no, se crea.</td></tr>
                            <tr><td><strong>Producto</strong></td><td>En productos nuevos</td><td>Nombre del producto.</td></tr>
                            <tr><td><strong>Precio venta</strong></td><td>En productos nuevos</td><td>Precio por unidad de venta, mayor que 0.</td></tr>
                            <tr><td>Costo unitario</td><td>No</td><td>Precio de compra.</td></tr>
                            <tr><td>Existencia</td><td>No</td><td><strong>Reemplaza</strong> la existencia actual (sirve para cargar el stock inicial o un conteo).</td></tr>
                            <tr><td>Unidad</td><td>No</td><td><?= htmlspecialchars(implode(', ', array_keys(UNIDADES))) ?> o su nombre (metro, kilo...). Por defecto, und.</td></tr>
                            <tr><td>Categoría</td><td>No</td><td>Si no existe, se crea.</td></tr>
                            <?php if(hayVariasTiendas()): ?>
                            <tr><td>Tienda</td><td>No</td><td>Nombre de la tienda. Por defecto, la principal.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <p class="small text-muted mb-0">
                        En un producto que ya existe, las celdas vacías no cambian nada. Se aceptan decimales con punto o coma (12.5 o 12,5).
                    </p>
                </div>
            </div>
        </div>
    </div>

    <?php else: $resumen = $preparada['resumen']; $aplicables = $resumen['nuevo'] + $resumen['actualizar']; ?>
    <!-- Paso 2: vista previa y confirmación -->
    <p class="text-muted">
        Archivo: <strong><?= htmlspecialchars($_SESSION['importacion']['archivo']) ?></strong> ·
        <?= count($preparada['filas']) ?> fila(s) con datos. Revisa los cambios antes de confirmar.
    </p>
    <div class="d-flex flex-wrap gap-2 mb-3" id="filtrosEstado">
        <button type="button" class="btn btn-outline-dark btn-sm active" data-estado="">Todas (<?= count($preparada['filas']) ?>)</button>
        <?php foreach($estados as $clave => [$etiqueta, $clase]): ?>
            <button type="button" class="btn btn-outline-dark btn-sm" data-estado="<?= $clave ?>" <?= $resumen[$clave] ? '' : 'disabled' ?>>
                <span class="badge <?= $clase ?>"><?= $etiqueta ?></span> <?= $resumen[$clave] ?>
            </button>
        <?php endforeach; ?>
    </div>

    <?php if($preparada['categoriasNuevas']): ?>
        <div class="alert alert-info py-2">
            <i class="fa fa-tags"></i> Se crearán las categorías: <strong><?= htmlspecialchars(implode(', ', $preparada['categoriasNuevas'])) ?></strong>.
        </div>
    <?php endif; ?>
    <?php if($resumen['error']): ?>
        <div class="alert alert-warning py-2">
            <i class="fa fa-exclamation-triangle"></i> <?= $resumen['error'] ?> fila(s) tienen errores y <strong>no se importarán</strong>.
            Puedes corregirlas en el archivo y volver a subirlo, o importar solo las correctas.
        </div>
    <?php endif; ?>

    <form method="post" class="d-flex gap-2 mb-3">
        <button type="submit" name="confirmar" class="btn btn-success" <?= $aplicables ? '' : 'disabled' ?>
                onclick="return confirm('¿Importar <?= $aplicables ?> producto(s)? La existencia del archivo reemplaza la actual.')">
            <i class="fa fa-check"></i> Confirmar importación (<?= $resumen['nuevo'] ?> nuevos, <?= $resumen['actualizar'] ?> actualizados)
        </button>
        <button type="submit" name="cancelar" class="btn btn-outline-danger" formnovalidate>
            <i class="fa fa-times"></i> Cancelar
        </button>
    </form>

    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle" id="tablaImportacion">
            <thead class="table-dark">
                <tr>
                    <th>Fila</th>
                    <th>Código</th>
                    <th>Producto</th>
                    <th>Categoría</th>
                    <th class="text-end">Existencia</th>
                    <th class="text-end">Costo</th>
                    <th class="text-end">Precio venta</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($preparada['filas'] as $fila): $final = $fila['final']; $antes = $fila['cambios']; ?>
                    <tr data-estado="<?= $fila['estado'] ?>" class="<?= $fila['estado'] === 'error' ? 'table-danger' : '' ?>">
                        <td class="text-muted"><?= $fila['fila'] ?></td>
                        <td><?= htmlspecialchars($fila['codigo']) ?></td>
                        <td><?= $conCambio($fila, 'nombre', $final['nombre'], $antes['nombre'] ?? '') ?></td>
                        <td><?= $conCambio($fila, 'categoria', $final['categoria'] ?? 'Sin categoría', $antes['categoria'] ?? 'Sin categoría') ?></td>
                        <td class="text-end">
                            <?= $conCambio($fila, 'existencia', cantidadConUnidad($final['existencia'], $final['unidad']),
                                    cantidadConUnidad($antes['existencia'] ?? 0, $antes['unidad'] ?? $final['unidad'])) ?>
                        </td>
                        <td class="text-end"><?= $conCambio($fila, 'compra', MONEDA . number_format($final['compra'], 2), MONEDA . number_format($antes['compra'] ?? 0, 2)) ?></td>
                        <td class="text-end"><?= $conCambio($fila, 'venta', MONEDA . number_format($final['venta'], 2), MONEDA . number_format($antes['venta'] ?? 0, 2)) ?></td>
                        <td>
                            <span class="badge <?= $estados[$fila['estado']][1] ?>"><?= $estados[$fila['estado']][0] ?></span>
                            <?php foreach($fila['errores'] as $error): ?>
                                <div class="small text-danger"><?= htmlspecialchars($error) ?></div>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <script>
    // Mostrar solo las filas de un estado
    document.querySelectorAll('#filtrosEstado button').forEach(function(boton) {
        boton.addEventListener('click', function() {
            document.querySelectorAll('#filtrosEstado button').forEach(b => b.classList.remove('active'));
            boton.classList.add('active');
            const estado = boton.getAttribute('data-estado');
            document.querySelectorAll('#tablaImportacion tbody tr').forEach(function(fila) {
                fila.style.display = (!estado || fila.getAttribute('data-estado') === estado) ? '' : 'none';
            });
        });
    });
    </script>
    <?php endif; ?>
</div>
