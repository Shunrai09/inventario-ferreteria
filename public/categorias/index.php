<?php
require_once __DIR__ . "/../../app/bootstrap.php";
requerirAdministrador();

// Alta de categoría: se procesa antes de imprimir la página para poder redirigir
if(isset($_POST['registrar'])){
    $nombre = trim($_POST['nombre'] ?? '');
    if($nombre === ''){
        $_SESSION['mensaje_categoria'] = ['tipo' => 'danger', 'texto' => 'Escribe el nombre de la categoría.'];
    } elseif(obtenerCategoriaPorNombre($nombre)){
        $_SESSION['mensaje_categoria'] = ['tipo' => 'danger', 'texto' => 'Ya existe una categoría con ese nombre.'];
    } elseif(registrarCategoria($nombre)){
        $_SESSION['mensaje_categoria'] = ['tipo' => 'success', 'texto' => 'Categoría registrada.'];
    } else {
        $_SESSION['mensaje_categoria'] = ['tipo' => 'danger', 'texto' => 'Error al registrar la categoría. Intenta nuevamente.'];
    }
    redirigir("categorias/index.php");
}

include_once VISTAS_DIR . "/encabezado.php";
include_once VISTAS_DIR . "/navbar.php";

$categorias = obtenerCategorias();

if(isset($_SESSION['mensaje_categoria'])) {
    $mensaje = $_SESSION['mensaje_categoria'];
    unset($_SESSION['mensaje_categoria']);
}
?>
<div class="container-xl">
    <?php if(isset($mensaje)): ?>
        <div class="alert alert-<?php echo $mensaje['tipo']; ?> alert-dismissible fade show" role="alert">
            <i class="fa fa-<?php echo $mensaje['tipo'] == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <?php echo htmlspecialchars($mensaje['texto']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endif; ?>

    <div class="encabezado-pagina">
        <div>
            <h1 class="titulo-pagina"><i class="fa fa-tags"></i> Categorías</h1>
            <p class="subtitulo-pagina">Agrupan los productos (herramientas, electricidad, gasfitería...) para buscarlos y filtrar los reportes</p>
        </div>
    </div>

    <form method="post" class="barra-filtros input-group" style="max-width: 900px;">
        <input type="text" name="nombre" class="form-control" placeholder="Nueva categoría, ej. Electricidad" maxlength="100" required autofocus>
        <button type="submit" name="registrar" class="btn btn-success">
            <i class="fa fa-plus"></i> Agregar
        </button>
    </form>

    <?php if(count($categorias) > 0): ?>
    <div class="card" style="max-width: 900px;">
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th class="text-center">Productos</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($categorias as $categoria): ?>
                    <tr>
                        <td class="fw-semibold"><?= htmlspecialchars($categoria->nombre) ?></td>
                        <td class="text-center">
                            <a href="<?= url('productos/index.php') ?>?categoria=<?= $categoria->id ?>" class="badge bg-secondary text-decoration-none" title="Ver productos de esta categoría">
                                <?= $categoria->productos ?>
                            </a>
                        </td>
                        <td>
                            <div class="d-flex gap-1 justify-content-end">
                                <a class="btn btn-outline-secondary btn-sm btn-icono" href="<?= url('categorias/editar.php') ?>?id=<?= $categoria->id ?>" title="Cambiar nombre" aria-label="Cambiar nombre de <?= htmlspecialchars($categoria->nombre) ?>">
                                    <i class="fa fa-edit"></i>
                                </a>
                                <?php if($categoria->productos == 0): ?>
                                    <a class="btn btn-outline-danger btn-sm btn-icono" aria-label="Eliminar <?= htmlspecialchars($categoria->nombre) ?>" href="<?= url('categorias/eliminar.php') ?>?id=<?= $categoria->id ?>"
                                       onclick="return confirm('¿Eliminar la categoría <?= htmlspecialchars($categoria->nombre, ENT_QUOTES) ?>?')"
                                       title="Eliminar categoría">
                                        <i class="fa fa-trash"></i>
                                    </a>
                                <?php else: ?>
                                    <button class="btn btn-outline-danger btn-sm btn-icono" disabled title="No se puede eliminar porque tiene productos">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    </div>
    <?php else: ?>
        <div class="alert alert-info" style="max-width: 900px;">
            <i class="fa fa-info-circle"></i> Todavía no hay categorías. Agrega la primera con el formulario de arriba.
        </div>
    <?php endif; ?>
</div>
