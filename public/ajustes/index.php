<?php
// Ajustes de inventario registrados (solo administradores): filtros por fecha, motivo y producto,
// valor de lo que se perdió o se agregó al costo, y exportación a PDF con ?formato=pdf.
require_once __DIR__ . "/../../app/bootstrap.php";
requerirAdministrador();

$fechaInicio = $_GET['inicio'] ?? '';
$fechaFin = $_GET['fin'] ?? '';
if(!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaInicio)) $fechaInicio = date('Y-m-01');
if(!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaFin)) $fechaFin = HOY;
$motivo = $_GET['motivo'] ?? '';
if($motivo !== MOTIVO_IMPORTACION && !isset(MOTIVOS_AJUSTE[$motivo])) $motivo = '';
$busqueda = trim($_GET['busqueda'] ?? '');

$ajustes = obtenerAjustes($fechaInicio, $fechaFin, $motivo, $busqueda);
$valorPerdido = 0;
$valorAgregado = 0;
foreach($ajustes as $ajuste){
    $valor = valorAjuste($ajuste);
    if($valor < 0) $valorPerdido += -$valor; else $valorAgregado += $valor;
}
$consultaFiltros = array_filter(['inicio' => $fechaInicio, 'fin' => $fechaFin, 'motivo' => $motivo, 'busqueda' => $busqueda], fn($v) => $v !== '');
$motivos = array_map(fn($datos) => $datos['nombre'], MOTIVOS_AJUSTE) + [MOTIVO_IMPORTACION => nombreMotivoAjuste(MOTIVO_IMPORTACION)];

if(($_GET['formato'] ?? '') === 'pdf') {
    $filas = [];
    foreach($ajustes as $ajuste){
        $diferencia = diferenciaAjuste($ajuste);
        $filas[] = [
            date('d/m/Y H:i', strtotime($ajuste->fecha)),
            $ajuste->codigo . ' · ' . $ajuste->nombre,
            nombreMotivoAjuste($ajuste->motivo),
            formatearCantidad($ajuste->cantidadAnterior) . ' -> ' . cantidadConUnidad($ajuste->cantidadNueva, $ajuste->unidad),
            ($diferencia > 0 ? '+' : '') . cantidadConUnidad($diferencia, $ajuste->unidad),
            MONEDA . ' ' . number_format(valorAjuste($ajuste), 2),
            $ajuste->usuario,
            (string) $ajuste->notas,
        ];
    }
    $fecha = fn($valor) => date('d/m/Y', strtotime($valor));
    enviarPdf('ajustes_inventario_' . $fechaInicio . '_al_' . $fechaFin . '.pdf', generarPdfReporte([
        'titulo'   => 'Ajustes de inventario',
        'detalles' => ['Período: ' . $fecha($fechaInicio) . ' al ' . $fecha($fechaFin)
            . '     ·     Motivo: ' . ($motivo ? nombreMotivoAjuste($motivo) : 'todos')
            . ($busqueda !== '' ? '     ·     Producto: ' . $busqueda : '')],
        'resumen'  => [
            'N° de ajustes'  => number_format(count($ajustes)),
            'Valor perdido'  => MONEDA . ' ' . number_format($valorPerdido, 2),
            'Valor agregado' => MONEDA . ' ' . number_format($valorAgregado, 2),
        ],
        'columnas' => [
            ['titulo' => 'Fecha', 'ancho' => 74],
            ['titulo' => 'Producto', 'ancho' => 190],
            ['titulo' => 'Motivo', 'ancho' => 110],
            ['titulo' => 'Antes -> después', 'ancho' => 96, 'derecha' => true],
            ['titulo' => 'Diferencia', 'ancho' => 70, 'derecha' => true],
            ['titulo' => 'Valor', 'ancho' => 66, 'derecha' => true],
            ['titulo' => 'Usuario', 'ancho' => 60],
            ['titulo' => 'Notas', 'ancho' => 104],
        ],
        'filas'    => $filas,
        'total'    => ['VALOR NETO', MONEDA . ' ' . number_format($valorAgregado - $valorPerdido, 2)],
        'vacio'    => 'No hay ajustes para los filtros elegidos.',
    ]));
}

$cartas = [
    ["titulo" => "Ajustes", "icono" => "fa fa-sliders-h", "total" => number_format(count($ajustes)), "color" => ""],
    ["titulo" => "Valor perdido (al costo)", "icono" => "fa fa-arrow-down", "total" => MONEDA . number_format($valorPerdido, 2), "color" => ""],
    ["titulo" => "Valor agregado (al costo)", "icono" => "fa fa-arrow-up", "total" => MONEDA . number_format($valorAgregado, 2), "color" => ""],
];

include_once VISTAS_DIR . "/encabezado.php";
include_once VISTAS_DIR . "/navbar.php";
?>
<div class="container-xl">
    <?php mostrarMensaje(); ?>

    <div class="encabezado-pagina">
        <div>
            <h1 class="titulo-pagina"><i class="fa fa-sliders-h"></i> Ajustes de inventario</h1>
            <p class="subtitulo-pagina">Cambios de existencia que no son compras ni ventas: conteos, mermas, pérdidas e importaciones</p>
        </div>
        <div class="acciones-pagina">
            <a href="<?= url('ajustes/index.php') ?>?<?= htmlspecialchars(http_build_query(['formato' => 'pdf'] + $consultaFiltros)) ?>" class="btn btn-outline-secondary">
                <i class="fa fa-file-pdf"></i> Exportar PDF
            </a>
            <a href="<?= url('ajustes/nuevo.php') ?>" class="btn btn-primary">
                <i class="fa fa-plus"></i> Nuevo ajuste
            </a>
        </div>
    </div>

    <form method="get" class="barra-filtros">
        <div class="row g-2 align-items-end">
            <div class="col-6 col-md-2">
                <label for="inicio" class="form-label">Desde</label>
                <input type="date" class="form-control" name="inicio" id="inicio" value="<?= htmlspecialchars($fechaInicio) ?>" max="<?= HOY ?>">
            </div>
            <div class="col-6 col-md-2">
                <label for="fin" class="form-label">Hasta</label>
                <input type="date" class="form-control" name="fin" id="fin" value="<?= htmlspecialchars($fechaFin) ?>" max="<?= HOY ?>">
            </div>
            <div class="col-md-3">
                <label for="selectMotivo" class="form-label">Motivo</label>
                <select class="form-select" name="motivo" id="selectMotivo">
                    <option value="">Todos los motivos</option>
                    <?php foreach($motivos as $clave => $nombre): ?>
                    <option value="<?= $clave ?>" <?= $motivo === $clave ? 'selected' : '' ?>><?= htmlspecialchars($nombre) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="busqueda" class="form-label">Producto</label>
                <input type="text" class="form-control" name="busqueda" id="busqueda" value="<?= htmlspecialchars($busqueda) ?>" placeholder="Código o nombre">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="fa fa-search"></i> Buscar</button>
            </div>
        </div>
    </form>

    <?php include VISTAS_DIR . "/cartas_totales.php"; ?>

    <div class="card">
        <?php if($ajustes): ?>
        <div class="table-responsive" tabindex="0" role="region" aria-label="Ajustes registrados">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Producto</th>
                        <th>Motivo</th>
                        <th class="text-end">Antes → después</th>
                        <th class="text-end">Diferencia</th>
                        <th class="text-end">Valor</th>
                        <th>Usuario</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($ajustes as $ajuste): $diferencia = diferenciaAjuste($ajuste); $valor = valorAjuste($ajuste); ?>
                    <tr>
                        <td class="text-nowrap"><?= date('d/m/Y H:i', strtotime($ajuste->fecha)) ?></td>
                        <td>
                            <span class="fw-semibold"><?= htmlspecialchars($ajuste->nombre) ?></span>
                            <div class="small text-muted"><?= htmlspecialchars($ajuste->codigo) ?><?= $ajuste->notas ? ' · ' . htmlspecialchars($ajuste->notas) : '' ?></div>
                        </td>
                        <td><span class="badge <?= $ajuste->motivo === MOTIVO_IMPORTACION ? 'bg-info' : 'bg-secondary' ?>"><?= htmlspecialchars(nombreMotivoAjuste($ajuste->motivo)) ?></span></td>
                        <td class="text-end text-nowrap"><?= formatearCantidad($ajuste->cantidadAnterior) ?> → <?= cantidadConUnidad($ajuste->cantidadNueva, $ajuste->unidad) ?></td>
                        <td class="text-end text-nowrap fw-semibold <?= $diferencia < 0 ? 'text-danger' : 'text-success' ?>">
                            <?= ($diferencia > 0 ? '+' : '') . cantidadConUnidad($diferencia, $ajuste->unidad) ?>
                        </td>
                        <td class="text-end text-nowrap"><?= ($valor < 0 ? '−' : '') . MONEDA . number_format(abs($valor), 2) ?></td>
                        <td><?= htmlspecialchars($ajuste->usuario) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="card-body estado-vacio">
            <i class="fa fa-sliders-h"></i>
            <h3>No hay ajustes en estas fechas</h3>
            <p class="mb-3">Registra uno cuando un conteo no coincida o un producto se dañe o se pierda.</p>
            <a href="<?= url('ajustes/nuevo.php') ?>" class="btn btn-primary"><i class="fa fa-plus"></i> Nuevo ajuste</a>
        </div>
        <?php endif; ?>
    </div>
</div>
