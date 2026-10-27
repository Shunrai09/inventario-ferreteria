<?php
require_once __DIR__ . "/../../app/bootstrap.php";
requerirAdministrador();

// Se procesa antes de imprimir la página para poder volver al listado con un mensaje
if (isset($_POST['registrar'])) {
    $usuario = trim($_POST['usuario'] ?? '');
    $nombre = trim($_POST['nombre'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $rol = $_POST['rol'] ?? 'vendedor';

    if (empty($usuario) || empty($nombre) || empty($telefono) || empty($direccion)) {
        $error = 'Debes completar todos los datos.';
    } elseif (!preg_match('/^[a-zA-Z\s]+$/', $usuario)) {
        // Solo letras sin tildes en el usuario, para que sea fácil de escribir al iniciar sesión
        $error = 'El nombre de usuario no debe contener números, tildes ni caracteres especiales.';
    } elseif (!preg_match('/^[\p{L}\s]+$/u', $nombre)) {
        $error = 'El nombre completo no debe contener números ni caracteres especiales.';
    } elseif (!preg_match('/^9[0-9]{8}$/', $telefono)) {
        $error = 'El teléfono debe comenzar con 9 y tener 9 dígitos numéricos.';
    } elseif (!array_key_exists($rol, ROLES)) {
        $error = 'Elige un rol válido.';
    } elseif (usuarioExiste($usuario, $nombre)) {
        $error = 'El nombre de usuario o el nombre completo ya existe. Por favor, elige otro.';
    } elseif (registrarUsuario($usuario, $nombre, $telefono, $direccion, $rol)) {
        $_SESSION['mensaje_usuario'] = [
            'tipo' => 'success',
            'texto' => "Usuario $usuario registrado como " . nombreRol($rol) . ". Entra con la contraseña inicial \"" . PASSWORD_PREDETERMINADA . "\" y la cambia desde su perfil."
        ];
        redirigir("usuarios/index.php");
    } else {
        $error = 'Error al registrar el usuario.';
    }
}

include_once VISTAS_DIR . "/encabezado.php";
include_once VISTAS_DIR . "/navbar.php";

$valor = fn($campo) => htmlspecialchars($_POST[$campo] ?? '', ENT_QUOTES, 'UTF-8');
?>
<div class="container-xl">
  <div class="formulario-angosto mx-auto">
    <div class="encabezado-pagina">
        <div>
            <h1 class="titulo-pagina"><i class="fa fa-user-plus"></i> Agregar usuario</h1>
            <p class="subtitulo-pagina">Entrará con la contraseña inicial y la cambiará desde su perfil</p>
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
            <input type="text" name="usuario" class="form-control" id="usuario" value="<?= $valor('usuario') ?>" placeholder="Escribe el nombre de usuario. Ej. Paco" >
        </div>
        <div class="mb-3">
            <label for="nombre" class="form-label">Nombre completo</label>
            <input type="text" name="nombre" class="form-control" id="nombre" value="<?= $valor('nombre') ?>" placeholder="Escribe el nombre completo del usuario">
        </div>
        <div class="mb-3">
            <label for="telefono" class="form-label">Teléfono</label>
            <input type="text" name="telefono" class="form-control" id="telefono" value="<?= $valor('telefono') ?>" placeholder="Ej. 987654321">
        </div>
        <div class="mb-3">
            <label for="direccion" class="form-label">Dirección</label>
            <input type="text" name="direccion" class="form-control" id="direccion" value="<?= $valor('direccion') ?>" placeholder="Ej. Av. Los Olivos 123">
        </div>
        <div class="mb-3">
            <label for="rol" class="form-label">Rol</label>
            <select name="rol" id="rol" class="form-select">
                <?php foreach(ROLES as $clave => $nombreDelRol): ?>
                <option value="<?= $clave ?>" <?= ($_POST['rol'] ?? 'vendedor') === $clave ? 'selected' : '' ?>><?= htmlspecialchars($nombreDelRol) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="form-text">
                El vendedor solo usa el punto de venta, consulta productos (sin costos) y atiende clientes.
                El administrador tiene acceso a todo, incluida la creación de usuarios.
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-3 pt-3 border-top">
            <a href="<?= url('usuarios/index.php') ?>" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" name="registrar" value="Registrar" class="btn btn-primary"><i class="fa fa-save"></i> Registrar usuario</button>
        </div>
    </form>
  </div>
</div>
