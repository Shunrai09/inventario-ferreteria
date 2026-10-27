<?php
// Caja: se abre con un monto inicial, muestra lo cobrado por forma de pago y se cierra con el efectivo contado.
// La usan administradores y vendedores; el historial de cierres es solo para administradores.
require_once __DIR__ . "/../../app/bootstrap.php";
requerirSesion();

// El punto de venta abre la caja desde su propia pantalla y vuelve a ella
$volver = ($_POST['volver'] ?? '') === 'ventas' ? 'ventas/index.php' : 'caja/index.php';

if(isset($_POST['abrir'])){
    $monto = leerImporte($_POST['montoInicial'] ?? '');
    if($monto === null){
        guardarMensaje('danger', 'Escribe el monto inicial de la caja (puede ser 0).');
    } elseif(abrirCaja($monto, $_SESSION['idUsuario'])){
        guardarMensaje('success', 'Caja abierta con ' . MONEDA . number_format($monto, 2) . ' de monto inicial.');
    } else {
        guardarMensaje('warning', 'La caja ya estaba abierta.');
    }
    redirigir($volver);
}

$caja = obtenerCajaAbierta();

if(isset($_POST['cerrar'])){
    $contado = leerImporte($_POST['efectivoContado'] ?? '');
    $notas = trim($_POST['notas'] ?? '');
    if(!$caja){
        guardarMensaje('warning', 'La caja ya estaba cerrada.');
    } elseif($contado === null){
        guardarMensaje('danger', 'Escribe el efectivo que contaste en la caja.');
    } elseif(cerrarCaja($caja, $contado, $notas === '' ? null : mb_substr($notas, 0, 1000), $_SESSION['idUsuario'])){
        // Respaldo de la base al cerrar el día. Si falla, el cierre ya quedó guardado y el aviso se ve en Respaldos.
        try {
            crearRespaldo('cierre');
        } catch(Throwable $e) {
            error_log((string) $e);
            guardarErrorRespaldo('No se pudo hacer el respaldo del cierre de caja: ' . $e->getMessage());
        }
        redirigir('caja/cierre.php?id=' . $caja->id . '&nuevo=1');
    } else {
        guardarMensaje('warning', 'La caja ya estaba cerrada.');
    }
    redirigir('caja/index.php');
}

include_once VISTAS_DIR . "/encabezado.php";
include_once VISTAS_DIR . "/navbar.php";

$resumen = $caja ? resumenCaja($caja) : null;
$ultimaCerrada = $caja ? null : obtenerUltimaCajaCerrada();
$fechaHora = fn($fecha) => date('d/m/Y', strtotime($fecha)) . ' a las ' . date('H:i', strtotime($fecha));
?>
<div class="container-xl">
    <?php mostrarMensaje(); ?>

    <div class="encabezado-pagina">
        <div>
            <h1 class="titulo-pagina"><i class="fa fa-money-bill-wave"></i> Caja</h1>
            <p class="subtitulo-pagina">
                <?php if($caja): ?>
                    Abierta por <?= htmlspecialchars($caja->usuarioApertura) ?> el <?= $fechaHora($caja->fechaApertura) ?>
                <?php else: ?>
                    La caja está cerrada. Ábrela para empezar a vender.
                <?php endif; ?>
            </p>
        </div>
        <?php if(esAdministrador()): ?>
        <div class="acciones-pagina">
            <a href="<?= url('caja/cierres.php') ?>" class="btn btn-outline-secondary">
                <i class="fa fa-history"></i> Historial de cierres
            </a>
        </div>
        <?php endif; ?>
    </div>

    <?php if($caja): ?>
        <?php if(date('Y-m-d', strtotime($caja->fechaApertura)) !== HOY): ?>
            <div class="alert alert-warning">
                <i class="fa fa-exclamation-triangle"></i>
                La caja sigue abierta desde el <?= date('d/m/Y', strtotime($caja->fechaApertura)) ?>. Ciérrala para cuadrar el efectivo de ese día.
            </div>
        <?php endif; ?>

        <div class="row g-3 mb-4">
            <div class="col-12 col-md-4">
                <div class="indicador">
                    <div class="indicador-icono"><i class="fa fa-coins"></i></div>
                    <div class="indicador-cuerpo">
                        <div class="indicador-etiqueta">Monto inicial</div>
                        <div class="indicador-valor"><?= MONEDA . number_format($caja->montoInicial, 2) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="indicador indicador--exito">
                    <div class="indicador-icono"><i class="fa fa-receipt"></i></div>
                    <div class="indicador-cuerpo">
                        <div class="indicador-etiqueta">Vendido (<?= $resumen->numeroVentas ?> venta<?= $resumen->numeroVentas == 1 ? '' : 's' ?>)</div>
                        <div class="indicador-valor"><?= MONEDA . number_format($resumen->totalVentas, 2) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="indicador indicador--destacado">
                    <div class="indicador-icono"><i class="fa fa-cash-register"></i></div>
                    <div class="indicador-cuerpo">
                        <div class="indicador-etiqueta">Efectivo que debe haber</div>
                        <div class="indicador-valor" id="efectivoEsperado" data-valor="<?= $resumen->efectivoEsperado ?>"><?= MONEDA . number_format($resumen->efectivoEsperado, 2) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card mb-4">
                    <div class="card-header"><i class="fa fa-wallet text-muted me-1"></i> Cobrado por forma de pago</div>
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>Forma de pago</th>
                                    <th class="text-center">Ventas</th>
                                    <th class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($resumen->formas as $clave => $forma): ?>
                                <tr>
                                    <td><?= htmlspecialchars($forma->nombre) ?></td>
                                    <td class="text-center"><?= $forma->numero ?></td>
                                    <td class="text-end"><?= MONEDA . number_format($forma->total, 2) ?></td>
                                </tr>
                                <?php if($forma->numeroDevoluciones > 0): ?>
                                <tr class="text-danger">
                                    <td class="ps-4"><i class="fa fa-undo"></i> Devoluciones en <?= htmlspecialchars(mb_strtolower($forma->nombre)) ?></td>
                                    <td class="text-center"><?= $forma->numeroDevoluciones ?></td>
                                    <td class="text-end">−<?= MONEDA . number_format($forma->devoluciones, 2) ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="fw-semibold">
                                    <td>Total</td>
                                    <td class="text-center"><?= $resumen->numeroVentas ?></td>
                                    <td class="text-end"><?= MONEDA . number_format($resumen->totalVentas - $resumen->totalDevoluciones, 2) ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><i class="fa fa-calculator text-muted me-1"></i> Cómo se calcula el efectivo</div>
                    <div class="card-body">
                        <dl class="datos-lista datos-lista--importes">
                            <dt>Monto inicial</dt><dd><?= MONEDA . number_format($caja->montoInicial, 2) ?></dd>
                            <dt>+ Ventas en efectivo</dt><dd><?= MONEDA . number_format($resumen->formas['efectivo']->total ?? 0, 2) ?></dd>
                            <dt>− Devoluciones en efectivo</dt><dd><?= MONEDA . number_format($resumen->formas['efectivo']->devoluciones ?? 0, 2) ?></dd>
                            <dt class="fw-semibold">= Efectivo que debe haber</dt><dd class="fw-semibold"><?= MONEDA . number_format($resumen->efectivoEsperado, 2) ?></dd>
                        </dl>
                        <p class="small text-muted mb-0 mt-3">
                            Yape, Plin, tarjeta y transferencias no entran al cajón: se revisan en el celular o en el banco.
                            <?php if($resumen->numeroAnuladas > 0): ?>
                                <br><?= $resumen->numeroAnuladas ?> venta(s) de esta caja se anularon (<?= MONEDA . number_format($resumen->totalAnuladas, 2) ?>) y no cuentan.
                            <?php endif; ?>
                            <?php if($resumen->totalDevoluciones > 0): ?>
                                <br>Las devoluciones son ventas de días anteriores anuladas con esta caja abierta: su dinero salió de aquí.
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header"><i class="fa fa-lock text-muted me-1"></i> Cerrar caja</div>
                    <div class="card-body">
                        <form method="post" onsubmit="return confirm('¿Cerrar la caja? Después no se podrán registrar ventas hasta abrirla otra vez.')">
                            <label for="efectivoContado" class="form-label">Efectivo contado en el cajón</label>
                            <div class="input-group input-group-lg mb-2">
                                <span class="input-group-text"><?= MONEDA ?></span>
                                <input type="number" step="0.01" min="0" class="form-control" name="efectivoContado" id="efectivoContado" required
                                       placeholder="0.00" inputmode="decimal" autocomplete="off">
                            </div>
                            <div class="diferencia-caja mb-3" id="diferencia" aria-live="polite">Cuenta el dinero y escribe el total.</div>

                            <label for="notas" class="form-label">Notas <span class="text-muted">(opcional)</span></label>
                            <textarea class="form-control mb-3" name="notas" id="notas" rows="2" maxlength="1000"
                                      placeholder="Ej. se pagó S/20 de flete con dinero de la caja"></textarea>

                            <div class="d-grid">
                                <button type="submit" name="cerrar" value="1" class="btn btn-primary btn-lg">
                                    <i class="fa fa-lock"></i> Cerrar caja
                                </button>
                            </div>
                            <p class="small text-muted mb-0 mt-2">Al cerrar se imprime el resumen y se guarda un respaldo de la base de datos.</p>
                        </form>
                    </div>
                </div>
            </div>
        </div>

    <?php else: ?>
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header"><i class="fa fa-lock-open text-muted me-1"></i> Abrir caja</div>
                    <div class="card-body">
                        <form method="post">
                            <label for="montoInicial" class="form-label">Monto inicial</label>
                            <div class="input-group input-group-lg mb-2">
                                <span class="input-group-text"><?= MONEDA ?></span>
                                <input type="number" step="0.01" min="0" class="form-control" name="montoInicial" id="montoInicial" required
                                       placeholder="0.00" inputmode="decimal" autocomplete="off" autofocus>
                            </div>
                            <p class="small text-muted">El sencillo con el que empieza el día (monedas y billetes para dar vuelto).</p>
                            <div class="d-grid">
                                <button type="submit" name="abrir" value="1" class="btn btn-success btn-lg">
                                    <i class="fa fa-lock-open"></i> Abrir caja
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php if($ultimaCerrada):
                $diferencia = round($ultimaCerrada->efectivoContado - $ultimaCerrada->efectivoEsperado, 2); ?>
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header"><i class="fa fa-history text-muted me-1"></i> Último cierre</div>
                    <div class="card-body">
                        <dl class="datos-lista datos-lista--importes">
                            <dt>Cerrada</dt><dd><?= date('d/m/Y H:i', strtotime($ultimaCerrada->fechaCierre)) ?></dd>
                            <dt>Vendido</dt><dd><?= MONEDA . number_format($ultimaCerrada->totalVentas, 2) ?></dd>
                            <dt>Efectivo contado</dt><dd><?= MONEDA . number_format($ultimaCerrada->efectivoContado, 2) ?></dd>
                            <dt>Diferencia</dt><dd><?= etiquetaDiferenciaCaja($diferencia) ?></dd>
                        </dl>
                        <a href="<?= url('caja/cierre.php') ?>?id=<?= $ultimaCerrada->id ?>" class="btn btn-outline-secondary btn-sm mt-3">
                            <i class="fa fa-print"></i> Ver o reimprimir
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php if($caja): ?>
<script>
// Diferencia en vivo entre lo contado y lo que debe haber
(function() {
    var esperado = parseFloat(document.getElementById('efectivoEsperado').dataset.valor);
    var contado = document.getElementById('efectivoContado');
    var diferencia = document.getElementById('diferencia');
    var moneda = <?= json_encode(MONEDA) ?>;
    contado.addEventListener('input', function() {
        if(contado.value === '') {
            diferencia.className = 'diferencia-caja mb-3';
            diferencia.textContent = 'Cuenta el dinero y escribe el total.';
            return;
        }
        var resta = Math.round((parseFloat(contado.value.replace(',', '.')) - esperado) * 100) / 100;
        if(isNaN(resta)) return;
        if(resta === 0) {
            diferencia.className = 'diferencia-caja diferencia-caja--cuadra mb-3';
            diferencia.textContent = 'La caja cuadra.';
        } else if(resta > 0) {
            diferencia.className = 'diferencia-caja diferencia-caja--sobra mb-3';
            diferencia.textContent = 'Sobran ' + moneda + resta.toFixed(2);
        } else {
            diferencia.className = 'diferencia-caja diferencia-caja--falta mb-3';
            diferencia.textContent = 'Faltan ' + moneda + Math.abs(resta).toFixed(2);
        }
    });
})();
</script>
<?php endif; ?>
