<?php
// Estilos de los comprobantes para impresora térmica (ticket de venta y cierre de caja).
// Usa $ancho (milímetros del rollo, de config 'ticket.ancho_mm').
?>
    <style>
        :root { <?= variablesTema() ?> --ancho-ticket: <?= $ancho ?>mm; }
        body { margin: 0; background: #e9ecef; font-family: system-ui, -apple-system, "Segoe UI", sans-serif; }

        /* Botones de pantalla (no se imprimen) */
        .acciones { display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; padding: 16px; }
        .acciones a, .acciones button {
            padding: 8px 16px; border: 1px solid #ced4da; border-radius: 6px; background: #fff;
            color: #212529; font: inherit; font-size: 15px; text-decoration: none; cursor: pointer;
        }
        .acciones .principal { background: var(--color-primario); border-color: var(--color-primario); color: #fff; }
        .acciones .peligro { border-color: #f1aeb5; color: #b02a37; }
        .aviso { width: 100%; text-align: center; color: #0f5132; font-weight: 600; }

        /* Ticket */
        .ticket {
            box-sizing: border-box; width: var(--ancho-ticket); margin: 0 auto 24px; padding: 4mm;
            background: #fff; color: #000; box-shadow: 0 2px 8px rgba(0, 0, 0, .15);
            font-family: "Courier New", Courier, monospace; font-size: 12px; line-height: 1.35;
        }
        .ticket.angosto { font-size: 10.5px; padding: 2mm; }
        .ticket table { width: 100%; border-collapse: collapse; }
        .ticket td { padding: 0; vertical-align: top; }
        .centro { text-align: center; }
        .derecha { text-align: right; }
        .importe { text-align: right; white-space: nowrap; padding-left: 6px; }
        .empresa { font-size: 1.3em; font-weight: bold; }
        .logo { max-width: 60%; max-height: 25mm; margin-bottom: 2mm; filter: grayscale(1); }
        .separador { border-top: 1px dashed #000; margin: 6px 0; }
        .producto td { padding-top: 4px; }
        .total td { font-size: 1.3em; font-weight: bold; }
        .firma { margin: 14mm 6mm 0; border-top: 1px solid #000; padding-top: 2px; text-align: center; }
        /* Venta anulada: sello visible en pantalla y en el papel */
        .sello-anulada { border: 2px solid #000; text-align: center; font-weight: bold; font-size: 1.3em; letter-spacing: .15em; padding: 2mm; margin: 2mm 0; }

        /* Anular desde el ticket: formulario plegable con el motivo */
        .anular { width: 100%; text-align: center; }
        .anular summary { display: inline-block; padding: 8px 16px; border: 1px solid #f1aeb5; border-radius: 6px; background: #fff; color: #b02a37; cursor: pointer; }
        .anular form { display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; margin-top: 8px; }
        .anular input { padding: 8px; border: 1px solid #8592a6; border-radius: 6px; font: inherit; font-size: 15px; width: min(320px, 90vw); }

        @page { margin: 0; }
        @media print {
            body { background: #fff; }
            .no-imprimir { display: none !important; }
            .ticket { margin: 0; box-shadow: none; }
        }
    </style>
