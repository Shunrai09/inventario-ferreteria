<?php
// Resumen del cierre de una caja, listo para imprimir en la impresora de tickets.
// ?id=ID de la caja. Con &nuevo=1 (al cerrarla) muestra el aviso de caja cerrada.
require_once __DIR__ . "/../../app/bootstrap.php";
requerirSesion();

$caja = obtenerCajaPorId($_GET['id'] ?? 0);
$nuevo = isset($_GET['nuevo']);
$ancho = max(48, min(120, (int) TICKET['ancho_mm']));
$numero = $caja ? str_pad($caja->id, 6, '0', STR_PAD_LEFT) : '';
$resumen = $caja ? resumenCaja($caja) : null;
$volver = esAdministrador() && !$nuevo ? 'caja/cierres.php' : 'caja/index.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/svg+xml" href="<?= url('assets/img/favicon.svg') ?>">
    <title><?= $caja ? 'Cierre de caja N° ' . $numero : 'Cierre de caja' ?> | <?= htmlspecialchars(EMPRESA_NOMBRE) ?></title>
    <?php include VISTAS_DIR . '/estilos_ticket.php'; ?>
</head>
<body>

<?php if(!$caja): ?>
    <div class="acciones">
        <p class="aviso" style="color: #842029;">La caja solicitada no existe.</p>
        <a href="<?= url('caja/index.php') ?>">Ir a la caja</a>
    </div>
<?php else: ?>
    <div class="acciones no-imprimir">
        <?php if($nuevo): ?>
            <p class="aviso">✔ Caja N° <?= $numero ?> cerrada</p>
        <?php endif; ?>
        <button type="button" class="principal" id="btnImprimir" onclick="window.print()">Imprimir (Enter)</button>
        <a href="<?= url($volver) ?>" id="btnSalir"><?= $volver === 'caja/cierres.php' ? 'Historial de cierres' : 'Volver a la caja' ?> (Esc)</a>
    </div>

    <div class="ticket<?= $ancho < 70 ? ' angosto' : '' ?>">
        <div class="centro">
            <div class="empresa"><?= htmlspecialchars(EMPRESA_NOMBRE) ?></div>
            <?php if(EMPRESA_RUC): ?><div>RUC <?= htmlspecialchars(EMPRESA_RUC) ?></div><?php endif; ?>
        </div>

        <div class="separador"></div>
        <table>
            <tr>
                <td><strong><?= $caja->fechaCierre ? 'CIERRE DE CAJA' : 'CAJA ABIERTA' ?></strong></td>
                <td class="derecha"><strong>N° <?= $numero ?></strong></td>
            </tr>
            <tr><td>Apertura:</td><td class="derecha"><?= date('d/m/Y H:i', strtotime($caja->fechaApertura)) ?></td></tr>
            <tr><td>Abrió:</td><td class="derecha"><?= htmlspecialchars($caja->usuarioApertura) ?></td></tr>
            <?php if($caja->fechaCierre): ?>
            <tr><td>Cierre:</td><td class="derecha"><?= date('d/m/Y H:i', strtotime($caja->fechaCierre)) ?></td></tr>
            <tr><td>Cerró:</td><td class="derecha"><?= htmlspecialchars($caja->usuarioCierre) ?></td></tr>
            <?php endif; ?>
        </table>

        <div class="separador"></div>
        <table>
            <tr><td colspan="2"><strong>VENTAS POR FORMA DE PAGO</strong></td></tr>
            <?php foreach($resumen->formas as $forma): ?>
                <tr>
                    <td><?= htmlspecialchars($forma->nombre) ?> (<?= $forma->numero ?>)</td>
                    <td class="importe"><?= number_format($forma->total, 2) ?></td>
                </tr>
                <?php if($forma->numeroDevoluciones > 0): ?>
                <tr>
                    <td>&nbsp;Devoluciones (<?= $forma->numeroDevoluciones ?>)</td>
                    <td class="importe">-<?= number_format($forma->devoluciones, 2) ?></td>
                </tr>
                <?php endif; ?>
            <?php endforeach; ?>
            <tr class="producto">
                <td><strong>Total vendido</strong></td>
                <td class="importe"><strong><?= MONEDA ?> <?= number_format($resumen->totalVentas - $resumen->totalDevoluciones, 2) ?></strong></td>
            </tr>
            <?php if($resumen->numeroAnuladas > 0): ?>
            <tr><td colspan="2">Ventas anuladas: <?= $resumen->numeroAnuladas ?> (<?= MONEDA ?> <?= number_format($resumen->totalAnuladas, 2) ?>, no cuentan)</td></tr>
            <?php endif; ?>
        </table>

        <div class="separador"></div>
        <table>
            <tr><td colspan="2"><strong>EFECTIVO</strong></td></tr>
            <tr><td>Monto inicial</td><td class="importe"><?= number_format($caja->montoInicial, 2) ?></td></tr>
            <tr><td>+ Ventas en efectivo</td><td class="importe"><?= number_format($resumen->formas['efectivo']->total ?? 0, 2) ?></td></tr>
            <?php if(($resumen->formas['efectivo']->devoluciones ?? 0) > 0): ?>
            <tr><td>- Devoluciones</td><td class="importe"><?= number_format($resumen->formas['efectivo']->devoluciones, 2) ?></td></tr>
            <?php endif; ?>
            <tr class="producto"><td><strong>Debe haber</strong></td><td class="importe"><strong><?= number_format($caja->fechaCierre ? $caja->efectivoEsperado : $resumen->efectivoEsperado, 2) ?></strong></td></tr>
            <?php if($caja->fechaCierre):
                $diferencia = round($caja->efectivoContado - $caja->efectivoEsperado, 2); ?>
            <tr><td><strong>Contado</strong></td><td class="importe"><strong><?= number_format($caja->efectivoContado, 2) ?></strong></td></tr>
            <tr class="total">
                <td><?= abs($diferencia) < 0.005 ? 'CUADRA' : ($diferencia > 0 ? 'SOBRANTE' : 'FALTANTE') ?></td>
                <td class="importe"><?= abs($diferencia) < 0.005 ? '' : MONEDA . ' ' . number_format(abs($diferencia), 2) ?></td>
            </tr>
            <?php endif; ?>
        </table>

        <?php if(!empty($caja->notas)): ?>
            <div class="separador"></div>
            <div>Notas: <?= nl2br(htmlspecialchars($caja->notas)) ?></div>
        <?php endif; ?>

        <?php if($caja->fechaCierre): ?>
            <div class="firma">Firma</div>
        <?php endif; ?>
    </div>

    <script>
    // Enter imprime; Esc vuelve
    document.getElementById('btnImprimir').focus();
    document.addEventListener('keydown', function(e) {
        if(e.key === 'Enter') {
            e.preventDefault();
            window.print();
        }
        if(e.key === 'Escape') {
            window.location.href = document.getElementById('btnSalir').href;
        }
    });
    </script>
<?php endif; ?>

</body>
</html>
