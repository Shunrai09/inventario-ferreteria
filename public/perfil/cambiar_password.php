<?php
require_once __DIR__ . "/../../app/bootstrap.php";
requerirSesion();

include_once VISTAS_DIR . "/encabezado.php";
include_once VISTAS_DIR . "/navbar.php";
$idUsuario = $_SESSION['idUsuario'];
?>
<div class="container-xl">
  <div class="formulario-angosto mx-auto" style="max-width: 560px;">
	<div class="encabezado-pagina">
		<div>
			<h1 class="titulo-pagina"><i class="fa fa-key"></i> Cambiar contraseña</h1>
			<p class="subtitulo-pagina">Usa una contraseña que no uses en otros sitios</p>
		</div>
	</div>
	<form action="" method="post" class="card card-body p-4">
		<div class="mb-3">
            <label for="actual" class="form-label">Contraseña actual</label>
            <input type="password" name="actual" class="form-control" id="actual" placeholder="Escribe tu contraseña actual">
        </div>

        <div class="mb-3">
            <label for="nueva" class="form-label">Contraseña nueva</label>
            <input type="password" name="nueva" class="form-control" id="nueva" placeholder="Escribe tu contraseña nueva">
        </div>

        <div class="mb-3">
            <label for="repite" class="form-label">Repite la nueva contraseña</label>
            <input type="password" name="repite" class="form-control" id="repite" placeholder="Escribe la contraseña nueva de nuevo">
        </div>

        <div class="d-flex justify-content-end gap-2 mt-2 pt-3 border-top">
        	<a href="<?= url('perfil/index.php') ?>" class="btn btn-outline-secondary">
        		Cancelar
        	</a>
        	<input type="submit" name="cambiar" class="btn btn-primary" value="Cambiar contraseña">
        </div>
	</form>
  </div>
</div>
<?php
if(isset($_POST['cambiar'])){
	if(empty($_POST['actual']) || empty($_POST['nueva']) || empty($_POST['repite'])){
		echo'
        <div class="alert alert-danger mt-3" role="alert">
            Debes completar todos los datos.
        </div>';
        return;
	}

	$actual = $_POST['actual'];
	$nueva = $_POST['nueva'];
	$repite = $_POST['repite'];

    if(strlen($nueva) < 8){
        echo'
        <div class="alert alert-danger mt-3" role="alert">
            La contraseña nueva debe tener al menos 8 caracteres.
        </div>';
        return;
    }

	if($nueva !== $repite) {
		echo'
        <div class="alert alert-danger mt-3" role="alert">
            La contraseña repetida debe coincidir con la nueva.
        </div>';
        return;
	}

	$passwordVerificada = verificarPassword($idUsuario, $actual);
	if(!$passwordVerificada){
		echo'
        <div class="alert alert-danger mt-3" role="alert">
            La contraseña actual es incorrecta.
        </div>';
        return;
	}

    $resultado = cambiarPassword($idUsuario, $repite);
    if($resultado){
        echo'
        <div class="alert alert-success mt-3" role="alert">
            Contraseña actualizada.
        </div>';
        return;
    }

    
}
?>