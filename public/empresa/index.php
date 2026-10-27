<?php
// Datos de la empresa (solo administradores): nombre, RUC, dirección, teléfono y el pie y ancho del ticket.
// Se usan en el ticket, el cierre de caja, los reportes en PDF, la barra del sistema y el inicio de sesión.
require_once __DIR__ . "/../../app/bootstrap.php";
requerirAdministrador();

if(isset($_POST['guardar'])){
    $datos = [
        'empresa.nombre'    => mb_substr(trim($_POST['nombre'] ?? ''), 0, 100),
        'empresa.ruc'       => preg_replace('/\s+/', '', $_POST['ruc'] ?? ''),
        'empresa.direccion' => mb_substr(trim($_POST['direccion'] ?? ''), 0, 150),
        'empresa.telefono'  => mb_substr(trim($_POST['telefono'] ?? ''), 0, 30),
        'ticket.pie'        => mb_substr(trim(str_replace("\r\n", "\n", $_POST['pie'] ?? '')), 0, 300),
        'ticket.ancho_mm'   => $_POST['ancho'] ?? '',
    ];
    if($datos['empresa.nombre'] === ''){
        $error = 'Escribe el nombre de la empresa.';
    } elseif($datos['empresa.ruc'] !== '' && !preg_match('/^\d{11}$/', $datos['empresa.ruc'])){
        $error = 'El RUC debe tener 11 dígitos (o déjalo vacío).';
    } elseif(!in_array($datos['ticket.ancho_mm'], ['80', '58'], true)){
        $error = 'Elige el ancho del papel de la ticketera: 80 o 58 mm.';
    }
    if(isset($error)){
        // Se conserva lo escrito para corregirlo
        $_SESSION['formulario_empresa'] = $datos;
        guardarMensaje('danger', $error);
    } else {
        guardarDatosEmpresa($datos);
        guardarMensaje('success', 'Datos guardados. Así saldrán desde el próximo ticket.');
    }
    redirigir('empresa/index.php');
}

$valores = $_SESSION['formulario_empresa'] ?? [
    'empresa.nombre'    => EMPRESA_NOMBRE,
    'empresa.ruc'       => EMPRESA_RUC,
    'empresa.direccion' => EMPRESA_DIRECCION,
    'empresa.telefono'  => EMPRESA_TELEFONO,
    'ticket.pie'        => TICKET['pie'],
    'ticket.ancho_mm'   => (string) TICKET['ancho_mm'],
];
unset($_SESSION['formulario_empresa']);

include_once VISTAS_DIR . "/encabezado.php";
include_once VISTAS_DIR . "/navbar.php";
?>
<div class="container-xl">
    <?php mostrarMensaje(); ?>

    <div class="encabezado-pagina">
        <div>
            <h1 class="titulo-pagina"><i class="fa fa-building"></i> Datos de la empresa</h1>
            <p class="subtitulo-pagina">Salen en el ticket, el cierre de caja, los reportes en PDF y la barra del sistema</p>
        </div>
        <div class="acciones-pagina">
            <a href="<?= url('ventas/ticket.php') ?>?prueba=1" class="btn btn-outline-secondary" target="_blank" rel="noopener">
                <i class="fa fa-print"></i> Imprimir ticket de prueba
            </a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-body">
                    <form method="post" id="formEmpresa">
                        <label for="nombre" class="form-label">Nombre de la empresa</label>
                        <input type="text" class="form-control mb-3" name="nombre" id="nombre" maxlength="100" required
                               value="<?= htmlspecialchars($valores['empresa.nombre']) ?>" placeholder="Ej. Ferretería El Tornillo">

                        <div class="row g-3 mb-3">
                            <div class="col-sm-6">
                                <label for="ruc" class="form-label">RUC <span class="text-muted">(opcional)</span></label>
                                <input type="text" class="form-control" name="ruc" id="ruc" maxlength="11" inputmode="numeric" pattern="\d{11}"
                                       title="11 dígitos" value="<?= htmlspecialchars($valores['empresa.ruc']) ?>" placeholder="Ej. 20123456789">
                            </div>
                            <div class="col-sm-6">
                                <label for="telefono" class="form-label">Teléfono <span class="text-muted">(opcional)</span></label>
                                <input type="text" class="form-control" name="telefono" id="telefono" maxlength="30"
                                       value="<?= htmlspecialchars($valores['empresa.telefono']) ?>" placeholder="Ej. 987 654 321">
                            </div>
                        </div>

                        <label for="direccion" class="form-label">Dirección <span class="text-muted">(opcional)</span></label>
                        <input type="text" class="form-control mb-3" name="direccion" id="direccion" maxlength="150"
                               value="<?= htmlspecialchars($valores['empresa.direccion']) ?>" placeholder="Ej. Av. Los Próceres 123, Lima">

                        <label for="pie" class="form-label">Mensaje al final del ticket <span class="text-muted">(opcional)</span></label>
                        <textarea class="form-control mb-1" name="pie" id="pie" rows="3" maxlength="300"
                                  placeholder="Ej. ¡Gracias por su compra!"><?= htmlspecialchars($valores['ticket.pie']) ?></textarea>
                        <p class="small text-muted">Cada línea se imprime en una línea aparte. Los datos que dejes vacíos no se imprimen.</p>

                        <fieldset class="mb-3">
                            <legend class="form-label fs-6">Ancho del papel de la ticketera</legend>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="ancho" id="ancho80" value="80" <?= $valores['ticket.ancho_mm'] === '80' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="ancho80">80 mm <span class="text-muted">(la más común)</span></label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="ancho" id="ancho58" value="58" <?= $valores['ticket.ancho_mm'] === '58' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="ancho58">58 mm</label>
                            </div>
                        </fieldset>

                        <div class="d-flex justify-content-end">
                            <button type="submit" name="guardar" value="1" class="btn btn-primary">
                                <i class="fa fa-save"></i> Guardar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card">
                <div class="card-header"><i class="fa fa-receipt text-muted me-1"></i> Así se verá en el ticket</div>
                <div class="card-body">
                    <div class="vista-ticket" aria-live="polite">
                        <div class="vista-ticket-empresa" id="vistaNombre"></div>
                        <div id="vistaRuc"></div>
                        <div id="vistaDireccion"></div>
                        <div id="vistaTelefono"></div>
                        <div class="vista-ticket-separador"></div>
                        <div class="text-start">TICKET DE VENTA &nbsp;&nbsp; N° 000123</div>
                        <div class="text-start">…</div>
                        <div class="vista-ticket-separador"></div>
                        <div class="vista-ticket-pie" id="vistaPie"></div>
                    </div>
                    <p class="small text-muted mt-3 mb-0">
                        Para probar la ticketera, pulsa <strong>Imprimir ticket de prueba</strong> y en la ventana de impresión elige la ticketera,
                        márgenes <em>Ninguno</em> y sin encabezados ni pies de página.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Vista previa en vivo del encabezado y el pie del ticket
(function() {
    const campo = id => document.getElementById(id);
    const lineas = {
        vistaNombre: () => campo('nombre').value.trim(),
        vistaRuc: () => campo('ruc').value.trim() ? 'RUC ' + campo('ruc').value.trim() : '',
        vistaDireccion: () => campo('direccion').value.trim(),
        vistaTelefono: () => campo('telefono').value.trim() ? 'Tel. ' + campo('telefono').value.trim() : '',
        vistaPie: () => campo('pie').value.trim(),
    };
    function actualizar() {
        for (const id in lineas) {
            const texto = lineas[id]();
            campo(id).textContent = texto;
            campo(id).hidden = texto === '';
        }
    }
    campo('formEmpresa').addEventListener('input', actualizar);
    actualizar();
})();
</script>
