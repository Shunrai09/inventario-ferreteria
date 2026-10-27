<?php
require_once __DIR__ . "/../app/bootstrap.php";

// Se valida antes de imprimir la página: con datos correctos redirige al panel sin depender del búfer de salida
if(isset($_POST['ingresar'])){
    if(empty($_POST['usuario']) || empty($_POST['password'])){
        $error = 'Debes completar todos los datos.';
    } else {
        $datosSesion = iniciarSesion($_POST['usuario'], $_POST['password']);
        if($datosSesion){
            // Identificador de sesión nuevo al entrar, para que no se pueda reutilizar uno anterior
            session_regenerate_id(true);
            $_SESSION['usuario'] = $datosSesion->usuario;
            $_SESSION['idUsuario'] = $datosSesion->id;
            // Respaldo automático de la base: el primero del día (si falla, no impide entrar)
            respaldarSiCorresponde();
            // El vendedor empieza en el punto de venta; el administrador, en el panel
            redirigir($datosSesion->rol === 'administrador' ? "index.php" : "ventas/index.php");
        }
        $error = 'Nombre de usuario y/o contraseña incorrectas.';
    }
}

include_once VISTAS_DIR . "/encabezado.php";

if(isset($error)){
    echo '
    <div class="alert alert-' . ($error === 'Debes completar todos los datos.' ? 'warning' : 'danger') . ' mt-3" role="alert">
        ' . $error . '
        <a href="login.php">Regresar</a>
    </div>';
    return;
}
?>

<!-- HTML para la página de login -->
<div class="d-flex justify-content-center align-items-center" style="min-height: 100vh;">
    <div class="" style="max-width: 500px; width: 100%;">
        <div class="text-center">
            <?php if(EMPRESA_LOGO): ?>
                <img src="<?= url(EMPRESA_LOGO) ?>" class="img-fluid mb-4" style="max-width: 200px;" alt="<?= htmlspecialchars(EMPRESA_NOMBRE) ?>">
            <?php else: ?>
                <h3 class="fw-bold mb-4"><?= htmlspecialchars(EMPRESA_NOMBRE) ?></h3>
            <?php endif; ?>
        </div>
        <form method="POST" action="<?= url('iniciar_sesion.php') ?>">
            <div class="form-group">
                <label for="usuario">Usuario</label>
                <input type="text" name="usuario" class="form-control" id="usuario" placeholder="Usuario">
            </div>
            <div class="form-group">
                <label for="password">Contraseña</label>
                <input type="password" name="password" class="form-control" id="password" placeholder="Contraseña">
            </div>
            <button type="submit" name="ingresar" class="btn btn-marca w-100 font-weight-bold mt-2">Ingresar</button>
        </form>
    </div>
</div>
