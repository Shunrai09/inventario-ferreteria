<?php
require_once __DIR__ . "/../../app/bootstrap.php";
requerirAdministrador();

$id = $_GET['id'] ?? null;
$categoria = $id ? obtenerCategoriaPorId($id) : null;
if(!$categoria) {
    $_SESSION['mensaje_categoria'] = ['tipo' => 'danger', 'texto' => 'Categoría no encontrada.'];
    redirigir("categorias/index.php");
}

if(isset($_POST['guardar'])){
    $nombre = trim($_POST['nombre'] ?? '');
    $existente = $nombre !== '' ? obtenerCategoriaPorNombre($nombre) : null;

    if($nombre === ''){
        $error = 'Escribe el nombre de la categoría.';
    } elseif($existente && $existente->id != $id){
        $error = 'Ya existe otra categoría con ese nombre.';
    } elseif(editarCategoria($nombre, $id)){
        $_SESSION['mensaje_categoria'] = ['tipo' => 'success', 'texto' => 'Categoría actualizada.'];
        redirigir("categorias/index.php");
    } else {
        $error = 'Error al actualizar la categoría. Intenta nuevamente.';
    }
}

include_once VISTAS_DIR . "/encabezado.php";
include_once VISTAS_DIR . "/navbar.php";
?>
<div class="container-xl">
  <div class="formulario-angosto mx-auto">
    <div class="encabezado-pagina">
        <div>
            <h1 class="titulo-pagina"><i class="fa fa-tags"></i> Editar categoría</h1>
            <p class="subtitulo-pagina">El nuevo nombre se ve en todos sus productos</p>
        </div>
    </div>

    <?php if(isset($error)): ?>
        <div class="alert alert-danger" role="alert">
            <i class="fa fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="post" class="card card-body p-4">
        <div class="mb-3">
            <label for="nombre" class="form-label">Nombre</label>
            <input type="text" name="nombre" class="form-control" id="nombre" maxlength="100" required autofocus
                   value="<?= htmlspecialchars($_POST['nombre'] ?? $categoria->nombre) ?>">
            <div class="form-text">
                <?= contarProductosDeCategoria($id) ?> producto(s) en esta categoría.
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-3 pt-3 border-top">
            <a href="<?= url('categorias/index.php') ?>" class="btn btn-outline-secondary">
                Cancelar
            </a>
            <button type="submit" name="guardar" class="btn btn-primary">
                <i class="fa fa-save"></i> Guardar
            </button>
        </div>
    </form>
  </div>
</div>
