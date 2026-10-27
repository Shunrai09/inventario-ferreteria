<?php
require_once __DIR__ . "/../../app/bootstrap.php";
requerirSesion();

include_once VISTAS_DIR . "/encabezado.php";
include_once VISTAS_DIR . "/navbar.php";

$id = $_GET['id'];
if (!$id) {
    echo 'No se ha seleccionado el cliente';
    exit;
}
$cliente = obtenerClientePorId($id);
?>

<div class="container-xl">
  <div class="formulario-angosto mx-auto">
    <div class="encabezado-pagina">
        <div>
            <h1 class="titulo-pagina"><i class="fa fa-user-edit"></i> Editar cliente</h1>
            <p class="subtitulo-pagina">Sus datos se muestran al asignarlo a una venta</p>
        </div>
        <div class="acciones-pagina">
            <a href="<?= url('clientes/index.php') ?>" class="btn btn-outline-secondary"><i class="fa fa-arrow-left"></i> Volver</a>
        </div>
    </div>
    <form method="post" class="card card-body p-4">
        <div class="mb-3">
            <label for="nombre" class="form-label">Nombre</label>
            <input type="text" name="nombre" class="form-control" value="<?php echo htmlspecialchars($cliente->nombre);?>" id="nombre" placeholder="Escribe el nombre del cliente">
        </div>
        <div class="mb-3">
            <label for="telefono" class="form-label">Teléfono</label>
            <input type="text" name="telefono" class="form-control" value="<?php echo htmlspecialchars($cliente->telefono);?>" id="telefono" placeholder="Ej. 987654321">
        </div>
        <div class="mb-3">
            <label for="direccion" class="form-label">Dirección</label>
            <input type="text" name="direccion" class="form-control" value="<?php echo htmlspecialchars($cliente->direccion);?>" id="direccion" placeholder="Ej. Av. Los Olivos 123">
        </div>

        <div class="d-flex justify-content-end gap-2 mt-3 pt-3 border-top">
            <a href="<?= url('clientes/index.php') ?>" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" name="registrar" value="Registrar" class="btn btn-primary"><i class="fa fa-save"></i> Guardar cliente</button>
        </div>
    </form>
  </div>
</div>

<?php
if(isset($_POST['registrar'])){
    $nombre = $_POST['nombre'];
    $telefono = $_POST['telefono'];
    $direccion = $_POST['direccion'];

    // Validación de campos vacíos
    if(empty($nombre) || empty($telefono) || empty($direccion)){
        echo '
        <div class="alert alert-danger mt-3" role="alert">
            Debes completar todos los datos.
        </div>';
        return;
    }

    // Validación de longitud del teléfono
    if(strlen($telefono) != 9){
        echo '
        <div class="alert alert-danger mt-3" role="alert">
            El número de teléfono debe tener 9 dígitos.
        </div>';
        return;
    }

    // Validación del primer dígito del teléfono
    if($telefono[0] != '9'){
        echo '
        <div class="alert alert-danger mt-3" role="alert">
            El número de teléfono debe comenzar con el dígito 9.
        </div>';
        return;
    }

    // Si pasa todas las validaciones, se procede a la actualización
    $resultado = editarCliente($nombre, $telefono, $direccion, $id);
    if($resultado){
        echo '
        <div class="alert alert-success mt-3" role="alert">
            Información del cliente actualizada con éxito.
        </div>';
    }
}
?>
