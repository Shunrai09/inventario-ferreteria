<?php
require_once __DIR__ . "/../../app/bootstrap.php";
requerirAdministrador();

$id = $_GET['id'] ?? null;
$usuario = $id ? obtenerUsuarioPorId($id) : null;
if (!$usuario) {
    $_SESSION['mensaje_usuario'] = ['tipo' => 'danger', 'texto' => 'Usuario no encontrado.'];
    redirigir("usuarios/index.php");
}

// Nadie cambia su propio rol: así siempre queda al menos un administrador
$esUnoMismo = $usuario->id == $_SESSION['idUsuario'];

// Se procesa antes de imprimir la página para poder volver al listado con un mensaje
if (isset($_POST['actualizar'])) {
    $nombreUsuario = trim($_POST['usuario'] ?? '');
    $nombre = trim($_POST['nombre'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $rol = $esUnoMismo ? null : ($_POST['rol'] ?? $usuario->rol);
    $otroConEseUsuario = obtenerIdUsuarioPorNombre($nombreUsuario);

    if (empty($nombreUsuario) || empty($nombre) || empty($telefono) || empty($direccion)) {
        $error = 'Debes completar todos los datos.';
    } elseif (!preg_match('/^[a-zA-Z\s]+$/', $nombreUsuario)) {
        $error = 'El nombre de usuario no debe contener números, tildes ni caracteres especiales.';
    } elseif (!preg_match('/^[\p{L}\s]+$/u', $nombre)) {
        $error = 'El nombre completo no debe contener números ni caracteres especiales.';
    } elseif (!preg_match('/^9[0-9]{8}$/', $telefono)) {
        $error = 'El teléfono debe comenzar con 9 y tener 9 dígitos numéricos.';
    } elseif ($rol !== null && !array_key_exists($rol, ROLES)) {
        $error = 'Elige un rol válido.';
    } elseif ($otroConEseUsuario && $otroConEseUsuario != $id) {
        $error = 'Ya existe otro usuario con ese nombre de usuario.';
    } elseif (editarUsuario($nombreUsuario, $nombre, $telefono, $direccion, $id, $rol)) {
        // Si se cambió el propio nombre de usuario, la sesión lo muestra actualizado
        if ($esUnoMismo) {
            $_SESSION['usuario'] = $nombreUsuario;
        }
        $_SESSION['mensaje_usuario'] = ['tipo' => 'success', 'texto' => 'Información de usuario actualizada con éxito.'];
        redirigir("usuarios/index.php");
    } else {
        $error = 'Error al actualizar la información del usuario.';
    }
}

include_once VISTAS_DIR . "/encabezado.php";
include_once VISTAS_DIR . "/navbar.php";

// Si hubo un error se muestran los datos que se enviaron; si no, los guardados
$valor = fn($campo) => htmlspecialchars($_POST[$campo] ?? $usuario->$campo, ENT_QUOTES, 'UTF-8');
$rolElegido = $_POST['rol'] ?? $usuario->rol;
?>
<div class="container-xl">
  <div class="formulario-angosto mx-auto">
    <div class="encabezado-pagina">
        <div>
            <h1 class="titulo-pagina"><i class="fa fa-user-edit"></i> Editar usuario</h1>
            <p class="subtitulo-pagina">Datos de acceso y rol</p>
        </div>
        <div class="acciones-pagina">
            <a href="<?= url('usuarios/index.php') ?>" class="btn btn-outline-secondary"><i class="fa fa-arrow-left"></i> Volver</a>
        </div>
    </div>

    <?php if(isset($error)): ?>
        <div class="alert alert-danger mt-3" role="alert"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="post" class="card card-body p-4">
        <div class="mb-3">
            <label for="usuario" class="form-label">Nombre de usuario</label>
            <input type="text" name="usuario" class="form-control" value="<?= $valor('usuario') ?>" id="usuario" placeholder="Escribe el nombre de usuario. Ej. Paco">
        </div>
        <div class="mb-3">
            <label for="nombre" class="form-label">Nombre completo</label>
            <input type="text" name="nombre" class="form-control" value="<?= $valor('nombre') ?>" id="nombre" placeholder="Escribe el nombre completo del usuario">
        </div>
        <div class="mb-3">
            <label for="telefono" class="form-label">Teléfono</label>
            <input type="text" name="telefono" class="form-control" value="<?= $valor('telefono') ?>" id="telefono" placeholder="Ej. 987654321">
        </div>
        <div class="mb-3">
            <label for="direccion" class="form-label">Dirección</label>
            <input type="text" name="direccion" class="form-control" value="<?= $valor('direccion') ?>" id="direccion" placeholder="Ej. Av. Los Olivos 123">
        </div>
        <div class="mb-3">
            <label for="rol" class="form-label">Rol</label>
            <select name="rol" id="rol" class="form-select" <?= $esUnoMismo ? 'disabled' : '' ?>>
                <?php foreach(ROLES as $clave => $nombreDelRol): ?>
                <option value="<?= $clave ?>" <?= $rolElegido === $clave ? 'selected' : '' ?>><?= htmlspecialchars($nombreDelRol) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if($esUnoMismo): ?>
                <div class="form-text">No puedes cambiar tu propio rol; pídeselo a otro administrador.</div>
            <?php endif; ?>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-3 pt-3 border-top">
            <a href="<?= url('usuarios/index.php') ?>" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" name="actualizar" value="Actualizar" class="btn btn-primary"><i class="fa fa-save"></i> Guardar cambios</button>
        </div>
    </form>
  </div>
</div>
