<?php
// Ticket de una venta, listo para imprimir en impresora térmica (80 o 58 mm) o en hoja normal.
// ?id=ID de la venta. Con &nueva=1 (al terminar una venta) muestra el aviso y el botón "Nueva venta".
require_once __DIR__ . "/../../app/bootstrap.php";
requerirSesion();

$venta = obtenerVentaPorId($_GET['id'] ?? 0);
$nueva = isset($_GET['nueva']);
$ancho = max(48, min(120, (int) TICKET['ancho_mm']));
$numero = $venta ? str_pad($venta->id, 6, '0', STR_PAD_LEFT) : '';
// Resultado de anular desde este mismo ticket
$mensajeAnulacion = $_SESSION['mensaje_pagina'] ?? null;
unset($_SESSION['mensaje_pagina']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/svg+xml" href="<?= url('assets/img/favicon.svg') ?>">
    <title><?= $venta ? 'Ticket N° ' . $numero : 'Ticket' ?> | <?= htmlspecialchars(EMPRESA_NOMBRE) ?></title>
    <?php include VISTAS_DIR . '/estilos_ticket.php'; ?>
</head>
<body>

<?php if(!$venta): ?>
    <div class="acciones">
        <p class="aviso" style="color: #842029;">La venta solicitada no existe.</p>
        <a href="<?= url('reportes/ventas.php') ?>">Ir al reporte de ventas</a>
    </div>
<?php else: ?>
    <div class="acciones no-imprimir">
        <?php if($nueva): ?>
            <p class="aviso">✔ Venta N° <?= $numero ?> registrada</p>
        <?php endif; ?>
        <button type="button" class="principal" id="btnImprimir" onclick="window.print()">Imprimir (Enter)</button>
        <?php if($nueva || !esAdministrador()): ?>
            <a href="<?= url('ventas/index.php') ?>" id="btnSalir">Nueva venta (Esc)</a>
        <?php else: ?>
            <a href="<?= url('reportes/ventas.php') ?>" id="btnSalir">Reporte de ventas (Esc)</a>
        <?php endif; ?>
        <?php if($mensajeAnulacion): ?>
            <p class="aviso"><?= htmlspecialchars($mensajeAnulacion['texto']) ?></p>
        <?php endif; ?>
        <?php if(esAdministrador() && !$venta->anulada): ?>
            <!-- Para una venta hecha por error: queda anulada (no se borra) y sus productos vuelven al inventario -->
            <details class="anular">
                <summary>Anular venta</summary>
                <form method="post" action="<?= url('ventas/anular.php') ?>"
                      onsubmit="return confirm('¿Anular la venta N° <?= $numero ?>? Sus productos volverán al inventario.')">
                    <input type="hidden" name="id" value="<?= $venta->id ?>">
                    <input type="hidden" name="volver" value="ventas/ticket.php?id=<?= $venta->id ?>">
                    <input type="text" name="motivo" maxlength="255" required placeholder="Motivo, ej. el cliente devolvió el producto" aria-label="Motivo de la anulación">
                    <button type="submit" class="peligro">Anular</button>
                </form>
            </details>
        <?php endif; ?>
    </div>

    <div class="ticket<?= $ancho < 70 ? ' angosto' : '' ?>">
        <div class="centro">
            <?php if(EMPRESA_LOGO): ?>
                <img src="<?= url(EMPRESA_LOGO) ?>" class="logo" alt="">
            <?php endif; ?>
            <div class="empresa"><?= htmlspecialchars(EMPRESA_NOMBRE) ?></div>
            <?php if(EMPRESA_RUC): ?><div>RUC <?= htmlspecialchars(EMPRESA_RUC) ?></div><?php endif; ?>
            <?php if(EMPRESA_DIRECCION): ?><div><?= htmlspecialchars(EMPRESA_DIRECCION) ?></div><?php endif; ?>
            <?php if(EMPRESA_TELEFONO): ?><div>Tel. <?= htmlspecialchars(EMPRESA_TELEFONO) ?></div><?php endif; ?>
        </div>

        <div class="separador"></div>
        <table>
            <tr>
                <td><strong>TICKET DE VENTA</strong></td>
                <td class="derecha"><strong>N° <?= $numero ?></strong></td>
            </tr>
            <tr><td>Fecha:</td><td class="derecha"><?= date('d/m/Y H:i', strtotime($venta->fecha)) ?></td></tr>
            <tr><td>Atendió:</td><td class="derecha"><?= htmlspecialchars($venta->usuario) ?></td></tr>
            <tr><td>Cliente:</td><td class="derecha"><?= htmlspecialchars($venta->cliente) ?></td></tr>
        </table>

        <?php if($venta->anulada): ?>
            <div class="sello-anulada">ANULADA</div>
            <div><?= date('d/m/Y H:i', strtotime($venta->fechaAnulacion)) ?> por <?= htmlspecialchars($venta->usuarioAnulacion ?? '') ?></div>
            <div>Motivo: <?= htmlspecialchars($venta->motivoAnulacion) ?></div>
        <?php endif; ?>

        <div class="separador"></div>
        <table>
            <?php foreach($venta->productos as $producto): ?>
                <tr class="producto"><td colspan="2"><?= htmlspecialchars($producto->nombre) ?></td></tr>
                <tr>
                    <td><?= cantidadConUnidad($producto->cantidad, $producto->unidad) ?> x <?= MONEDA ?><?= number_format($producto->precio, 2) ?></td>
                    <td class="importe"><?= number_format($producto->cantidad * $producto->precio, 2) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>

        <div class="separador"></div>
        <table>
            <tr class="total">
                <td>TOTAL</td>
                <td class="importe"><?= MONEDA ?> <?= number_format($venta->total, 2) ?></td>
            </tr>
            <tr><td colspan="2"><?= count($venta->productos) ?> producto(s)</td></tr>
            <tr><td>Pago:</td><td class="derecha"><?= htmlspecialchars(nombreFormaPago($venta->formaPago)) ?></td></tr>
            <?php if($venta->pagoCon !== null): ?>
                <tr><td>Recibido:</td><td class="importe"><?= MONEDA ?> <?= number_format($venta->pagoCon, 2) ?></td></tr>
                <tr><td>Vuelto:</td><td class="importe"><?= MONEDA ?> <?= number_format($venta->pagoCon - $venta->total, 2) ?></td></tr>
            <?php endif; ?>
        </table>

        <?php if(trim(TICKET['pie']) !== ''): ?>
            <div class="separador"></div>
            <div class="centro"><?= nl2br(htmlspecialchars(TICKET['pie'])) ?></div>
        <?php endif; ?>
    </div>

    <script>
    // Enter imprime; Esc vuelve a vender (o al reporte, si se abrió para reimprimir)
    document.getElementById('btnImprimir').focus();
    document.addEventListener('keydown', function(e) {
        // Mientras se escribe el motivo de anulación, Enter envía ese formulario y Esc no sale.
        // Sobre un botón, un enlace o el desplegable de anulación, Enter hace lo de ese elemento.
        if(e.target.tagName === 'INPUT') return;
        if(e.key === 'Enter' && !['BUTTON', 'A', 'SUMMARY'].includes(e.target.tagName)) {
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
