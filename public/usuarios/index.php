<?php
require_once __DIR__ . "/../../app/bootstrap.php";
requerirAdministrador();

include_once VISTAS_DIR . "/encabezado.php";
include_once VISTAS_DIR . "/navbar.php";

$usuarios = obtenerUsuarios();

if(isset($_SESSION['mensaje_usuario'])) {
    $mensaje = $_SESSION['mensaje_usuario'];
    unset($_SESSION['mensaje_usuario']);
}
?>
<div class="container-xl">
    <?php if(isset($mensaje)): ?>
        <div class="alert alert-<?php echo $mensaje['tipo']; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($mensaje['texto']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endif; ?>

    <div class="encabezado-pagina">
        <div>
            <h1 class="titulo-pagina"><i class="fa fa-users"></i> Usuarios</h1>
            <p class="subtitulo-pagina">Quién puede entrar al sistema y con qué rol</p>
        </div>
        <div class="acciones-pagina">
            <a class="btn btn-primary" href="<?= url('usuarios/agregar.php') ?>">
                <i class="fa fa-plus"></i>
                Agregar usuario
            </a>
        </div>
    </div>
    <div class="card">
    <div class="table-responsive">
    <table class="table table-hover">
        <thead>
            <tr>
                <th>Usuario</th>
                <th>Nombre</th>
                <th>Rol</th>
                <th>Teléfono</th>
                <th>Dirección</th>
                <th class="text-end">Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php
            foreach($usuarios as $usuario){
                $esUnoMismo = $usuario->id == $_SESSION['idUsuario'];
            ?>
                <tr>
                    <td>
                        <span class="fw-semibold"><?php echo htmlspecialchars($usuario->usuario); ?></span>
                        <?php if($esUnoMismo): ?><span class="text-muted small">(tú)</span><?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($usuario->nombre); ?></td>
                    <td>
                        <span class="badge <?php echo $usuario->rol === 'administrador' ? 'bg-primary' : 'bg-secondary'; ?>">
                            <?php echo htmlspecialchars(nombreRol($usuario->rol)); ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($usuario->telefono); ?></td>
                    <td><?php echo htmlspecialchars($usuario->direccion); ?></td>
                    <td>
                        <div class="d-flex gap-1 justify-content-end">
                        <a class="btn btn-outline-secondary btn-sm btn-icono" href="<?= url('usuarios/editar.php') ?>?id=<?php echo $usuario->id; ?>" title="Editar" aria-label="Editar <?= htmlspecialchars($usuario->usuario) ?>">
                            <i class="fa fa-edit"></i>
                        </a>
                        <?php if($esUnoMismo): ?>
                            <button class="btn btn-outline-danger btn-sm btn-icono" disabled title="No puedes eliminar tu propio usuario">
                                <i class="fa fa-trash"></i>
                            </button>
                        <?php else: ?>
                            <a class="btn btn-outline-danger btn-sm btn-icono" title="Eliminar" aria-label="Eliminar <?= htmlspecialchars($usuario->usuario) ?>" href="<?= url('usuarios/eliminar.php') ?>?id=<?php echo $usuario->id; ?>"
                               onclick="return confirm('¿Eliminar al usuario <?= htmlspecialchars($usuario->usuario, ENT_QUOTES) ?>?')">
                                <i class="fa fa-trash"></i>
                            </a>
                        <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
    </div>
    </div>
</div>
