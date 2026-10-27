<?php
// Historial de cajas: apertura, cierre, lo vendido y la diferencia de cada arqueo. Solo administradores.
require_once __DIR__ . "/../../app/bootstrap.php";
requerirAdministrador();

$fechaInicio = $_GET['inicio'] ?? '';
$fechaFin = $_GET['fin'] ?? '';
if(!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaInicio)) $fechaInicio = date('Y-m-01');
if(!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaFin)) $fechaFin = HOY;

$cajas = obtenerCajas($fechaInicio, $fechaFin);
$cerradas = array_filter($cajas, fn($caja) => $caja->fechaCierre !== null);
$totalVendido = array_sum(array_map(fn($caja) => (float) $caja->totalVentas, $cerradas));
$diferenciaTotal = array_sum(array_map(fn($caja) => $caja->efectivoContado - $caja->efectivoEsperado, $cerradas));

$cartas = [
    ["titulo" => "Cierres", "icono" => "fa fa-cash-register", "total" => number_format(count($cerradas)), "color" => ""],
    ["titulo" => "Total vendido", "icono" => "fa fa-money-bill", "total" => MONEDA . number_format($totalVendido, 2), "color" => ""],
    ["titulo" => "Diferencia acumulada", "icono" => "fa fa-balance-scale", "total" => ($diferenciaTotal < -0.004 ? '−' : '') . MONEDA . number_format(abs($diferenciaTotal), 2), "color" => ""],
];

include_once VISTAS_DIR . "/encabezado.php";
include_once VISTAS_DIR . "/navbar.php";
?>
<div class="container-xl">
    <div class="encabezado-pagina">
        <div>
            <h1 class="titulo-pagina"><i class="fa fa-history"></i> Cierres de caja</h1>
            <p class="subtitulo-pagina">Lo vendido en cada caja y si el efectivo cuadró al cerrarla</p>
        </div>
        <div class="acciones-pagina">
            <a href="<?= url('caja/index.php') ?>" class="btn btn-outline-secondary"><i class="fa fa-money-bill-wave"></i> Ir a la caja</a>
        </div>
    </div>

    <form method="get" class="barra-filtros">
        <div class="row g-2 align-items-end">
            <div class="col-sm-4 col-md-3">
                <label for="inicio" class="form-label">Desde</label>
                <input type="date" class="form-control" name="inicio" id="inicio" value="<?= htmlspecialchars($fechaInicio) ?>" max="<?= HOY ?>">
            </div>
            <div class="col-sm-4 col-md-3">
                <label for="fin" class="form-label">Hasta</label>
                <input type="date" class="form-control" name="fin" id="fin" value="<?= htmlspecialchars($fechaFin) ?>" max="<?= HOY ?>">
            </div>
            <div class="col-sm-4 col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="fa fa-search"></i> Buscar</button>
            </div>
        </div>
    </form>

    <?php include VISTAS_DIR . "/cartas_totales.php"; ?>

    <div class="card">
        <?php if($cajas): ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>N°</th>
                        <th>Apertura</th>
                        <th>Cierre</th>
                        <th class="text-end">Monto inicial</th>
                        <th class="text-end">Vendido</th>
                        <th class="text-end">Debía haber</th>
                        <th class="text-end">Contado</th>
                        <th class="text-center">Diferencia</th>
                        <th class="text-end"><span class="visually-hidden">Acciones</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($cajas as $caja): $numero = str_pad($caja->id, 6, '0', STR_PAD_LEFT); ?>
                    <tr>
                        <td class="fw-semibold"><?= $numero ?></td>
                        <td>
                            <?= date('d/m/Y H:i', strtotime($caja->fechaApertura)) ?>
                            <div class="small text-muted"><?= htmlspecialchars($caja->usuarioApertura) ?></div>
                        </td>
                        <?php if($caja->fechaCierre): ?>
                            <td>
                                <?= date('d/m/Y H:i', strtotime($caja->fechaCierre)) ?>
                                <div class="small text-muted"><?= htmlspecialchars($caja->usuarioCierre) ?></div>
                            </td>
                            <td class="text-end"><?= MONEDA . number_format($caja->montoInicial, 2) ?></td>
                            <td class="text-end"><?= MONEDA . number_format($caja->totalVentas, 2) ?></td>
                            <td class="text-end"><?= MONEDA . number_format($caja->efectivoEsperado, 2) ?></td>
                            <td class="text-end"><?= MONEDA . number_format($caja->efectivoContado, 2) ?></td>
                            <td class="text-center"><?= etiquetaDiferenciaCaja(round($caja->efectivoContado - $caja->efectivoEsperado, 2)) ?></td>
                        <?php else: ?>
                            <td><span class="badge bg-warning">Abierta</span></td>
                            <td class="text-end"><?= MONEDA . number_format($caja->montoInicial, 2) ?></td>
                            <td colspan="4" class="text-muted small">Se calcula al cerrarla</td>
                        <?php endif; ?>
                        <td class="text-end">
                            <a class="btn btn-outline-secondary btn-sm btn-icono" href="<?= url('caja/cierre.php') ?>?id=<?= $caja->id ?>"
                               title="Ver o reimprimir" aria-label="Ver o reimprimir la caja N° <?= $numero ?>">
                                <i class="fa fa-print"></i>
                            </a>
                        </td>
                    </tr>
                    <?php if(!empty($caja->notas)): ?>
                    <tr class="table-light">
                        <td></td>
                        <td colspan="8" class="small text-muted"><i class="fa fa-sticky-note"></i> <?= htmlspecialchars($caja->notas) ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="card-body estado-vacio">
            <i class="fa fa-cash-register"></i>
            <h3>No hay cajas en estas fechas</h3>
            <p class="mb-0">Elige otro período o abre la caja desde el menú Caja.</p>
        </div>
        <?php endif; ?>
    </div>
</div>
