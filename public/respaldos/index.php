<?php
// Respaldos de la base de datos (solo administradores): hacer uno ahora, descargarlos y restaurar
// uno de la lista o un archivo subido (por ejemplo, el de otra PC). Antes de restaurar se respalda lo actual.
require_once __DIR__ . "/../../app/bootstrap.php";
requerirAdministrador();

// Restaura y deja el resultado como mensaje. $ruta: archivo .sql.gz o .sql; $nombre: cómo se muestra.
$restaurar = function($ruta, $nombre){
    if(!esArchivoDeRespaldo($ruta)){
        guardarMensaje('danger', 'El archivo no es un respaldo de este sistema. No se cambió nada.');
        return;
    }
    try {
        $previo = crearRespaldo('antes_de_restaurar');
        $filas = restaurarRespaldo($ruta);
        // El carrito puede tener productos que ya no existen en los datos restaurados
        $_SESSION['lista'] = [];
        $_SESSION['clienteVenta'] = null;
        guardarMensaje('success', "Se restauró $nombre (" . number_format($filas) . ' registros). Lo que había antes quedó en el respaldo '
            . $previo['archivo'] . '.');
    } catch(RuntimeException $e) {
        guardarMensaje('danger', $e->getMessage() . ' No se cambió nada.');
    } catch(Throwable $e) {
        error_log((string) $e);
        guardarMensaje('danger', 'No se pudo restaurar ' . $nombre . ': ' . $e->getMessage() . ' No se cambió nada.');
    }
};

if(isset($_POST['respaldar'])){
    try {
        $respaldo = crearRespaldo('manual');
        guardarMensaje($respaldo['aviso'] ? 'warning' : 'success', $respaldo['aviso'] ?? 'Respaldo creado: ' . $respaldo['archivo'] . '.');
    } catch(Throwable $e) {
        error_log((string) $e);
        guardarErrorRespaldo('No se pudo hacer el respaldo: ' . $e->getMessage());
        guardarMensaje('danger', 'No se pudo hacer el respaldo: ' . $e->getMessage());
    }
    redirigir('respaldos/index.php');
}

if(isset($_POST['restaurar'])){
    $archivo = basename($_POST['archivo'] ?? '');
    if(!leerNombreRespaldo($archivo) || !is_file(carpetaRespaldos() . '/' . $archivo)){
        guardarMensaje('danger', 'El respaldo elegido no existe.');
    } else {
        $restaurar(carpetaRespaldos() . '/' . $archivo, 'el respaldo del ' . date('d/m/Y H:i', strtotime(leerNombreRespaldo($archivo)['fecha'])));
    }
    redirigir('respaldos/index.php');
}

if(isset($_POST['subir'])){
    $subido = $_FILES['archivo'] ?? null;
    if(!$subido || $subido['error'] !== UPLOAD_ERR_OK){
        guardarMensaje('danger', ($subido['error'] ?? null) === UPLOAD_ERR_INI_SIZE
            ? 'El archivo es más grande de lo que PHP permite subir (upload_max_filesize en php.ini).'
            : 'Elige el archivo del respaldo (.sql.gz o .sql).');
    } elseif(!preg_match('/\.sql(\.gz)?$/i', $subido['name'])){
        guardarMensaje('danger', 'El archivo debe ser un respaldo .sql.gz o .sql de este sistema.');
    } else {
        $restaurar($subido['tmp_name'], 'el archivo ' . basename($subido['name']));
    }
    redirigir('respaldos/index.php');
}

include_once VISTAS_DIR . "/encabezado.php";
include_once VISTAS_DIR . "/navbar.php";

$respaldos = listarRespaldos();
$ultimo = $respaldos[0] ?? null;
$error = ultimoErrorRespaldo();
$copia = trim(RESPALDOS['copia']);
$colorTipo = ['auto' => 'bg-secondary', 'manual' => 'bg-primary', 'cierre' => 'bg-success', 'antes_de_restaurar' => 'bg-warning', 'antes_de_actualizar' => 'bg-info'];
?>
<div class="container-xl">
    <?php mostrarMensaje(); ?>
    <?php if($error): ?>
        <div class="alert alert-warning"><i class="fa fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="encabezado-pagina">
        <div>
            <h1 class="titulo-pagina"><i class="fa fa-database"></i> Respaldos</h1>
            <p class="subtitulo-pagina">Copias de toda la información del sistema: productos, ventas, compras, clientes, cajas y usuarios</p>
        </div>
        <form method="post" class="acciones-pagina">
            <button type="submit" name="respaldar" value="1" class="btn btn-primary">
                <i class="fa fa-download"></i> Respaldar ahora
            </button>
        </form>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-md-4">
            <div class="indicador <?= $ultimo && strtotime($ultimo->fecha) > strtotime('-2 days') ? 'indicador--exito' : 'indicador--aviso' ?>">
                <div class="indicador-icono"><i class="fa fa-clock"></i></div>
                <div class="indicador-cuerpo">
                    <div class="indicador-etiqueta">Último respaldo</div>
                    <div class="indicador-valor fs-5"><?= $ultimo ? date('d/m/Y H:i', strtotime($ultimo->fecha)) : 'Ninguno' ?></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-8">
            <div class="card h-100">
                <div class="card-body small">
                    <dl class="datos-lista">
                        <dt>Automáticos</dt><dd>Uno por día al iniciar sesión, y otro cada vez que se cierra la caja.</dd>
                        <dt>Carpeta</dt><dd><code><?= htmlspecialchars(str_replace('/', DIRECTORY_SEPARATOR, carpetaRespaldos())) ?></code></dd>
                        <dt>Copia</dt>
                        <dd>
                            <?php if($copia === ''): ?>
                                Sin configurar. Para no perder todo si falla el disco, indica una USB o la carpeta de Google Drive en
                                <code>config/config.php</code> (<code>respaldos.copia</code>).
                            <?php elseif(is_dir($copia)): ?>
                                <code><?= htmlspecialchars($copia) ?></code> <span class="badge bg-success">Disponible</span>
                            <?php else: ?>
                                <code><?= htmlspecialchars($copia) ?></code> <span class="badge bg-danger">No disponible</span>
                            <?php endif; ?>
                        </dd>
                        <dt>Se guardan</dt><dd>Los últimos <?= (int) RESPALDOS['conservar'] ?>; los más antiguos se borran solos.</dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><i class="fa fa-list text-muted me-1"></i> Respaldos guardados</div>
                <?php if($respaldos): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Tipo</th>
                                <th class="text-end">Tamaño</th>
                                <th class="text-end"><span class="visually-hidden">Acciones</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($respaldos as $respaldo): $fecha = date('d/m/Y H:i', strtotime($respaldo->fecha)); ?>
                            <tr>
                                <td class="text-nowrap"><?= $fecha ?></td>
                                <td><span class="badge <?= $colorTipo[$respaldo->tipo] ?? 'bg-secondary' ?>"><?= htmlspecialchars(TIPOS_RESPALDO[$respaldo->tipo] ?? $respaldo->tipo) ?></span></td>
                                <td class="text-end text-nowrap"><?= tamanoLegible($respaldo->tamano) ?></td>
                                <td>
                                    <div class="d-flex gap-1 justify-content-end">
                                        <a class="btn btn-outline-secondary btn-sm btn-icono" href="<?= url('respaldos/descargar.php') ?>?archivo=<?= urlencode($respaldo->archivo) ?>"
                                           title="Descargar" aria-label="Descargar el respaldo del <?= $fecha ?>">
                                            <i class="fa fa-download"></i>
                                        </a>
                                        <form method="post" onsubmit="return confirm('¿Restaurar el respaldo del <?= $fecha ?>? Todos los datos actuales se reemplazarán por los de ese momento (antes se guarda un respaldo de lo actual).')">
                                            <input type="hidden" name="archivo" value="<?= htmlspecialchars($respaldo->archivo) ?>">
                                            <button type="submit" name="restaurar" value="1" class="btn btn-outline-danger btn-sm btn-icono"
                                                    title="Restaurar" aria-label="Restaurar el respaldo del <?= $fecha ?>">
                                                <i class="fa fa-undo"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="card-body estado-vacio">
                    <i class="fa fa-database"></i>
                    <h3>Todavía no hay respaldos</h3>
                    <p class="mb-0">Se hará uno automáticamente al iniciar sesión, o pulsa "Respaldar ahora".</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><i class="fa fa-upload text-muted me-1"></i> Restaurar desde un archivo</div>
                <div class="card-body">
                    <form method="post" enctype="multipart/form-data"
                          onsubmit="return confirm('¿Restaurar este archivo? Todos los datos actuales se reemplazarán (antes se guarda un respaldo de lo actual).')">
                        <label for="archivo" class="form-label">Respaldo descargado de este sistema (.sql.gz)</label>
                        <input type="file" class="form-control mb-2" name="archivo" id="archivo" accept=".gz,.sql" required>
                        <p class="small text-muted">Sirve para pasar la información a otra PC: descarga el respaldo en la PC anterior y restáuralo aquí.</p>
                        <div class="d-grid">
                            <button type="submit" name="subir" value="1" class="btn btn-outline-danger">
                                <i class="fa fa-undo"></i> Restaurar archivo
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
