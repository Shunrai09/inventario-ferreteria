<?php
require_once __DIR__ . "/../../app/bootstrap.php";
requerirSesion();

include_once VISTAS_DIR . "/encabezado.php";
include_once VISTAS_DIR . "/navbar.php";

$clientes = obtenerClientes();
?>
<div class="container-xl">
    <div class="encabezado-pagina">
        <div>
            <h1 class="titulo-pagina"><i class="fa fa-user-friends"></i> Clientes</h1>
            <p class="subtitulo-pagina">Personas y empresas a las que les vendes</p>
        </div>
        <div class="acciones-pagina">
            <a class="btn btn-primary" href="<?= url('clientes/agregar.php') ?>">
                <i class="fa fa-plus"></i>
                Agregar cliente
            </a>
        </div>
    </div>
    <div class="card">
    <?php if(count($clientes) > 0): ?>
    <div class="table-responsive">
    <table class="table table-hover">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Teléfono</th>
                <th>Dirección</th>
                <th class="text-end">Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php
            foreach($clientes as $cliente){
            ?>
                <tr>
                    <td class="fw-semibold"><?php echo htmlspecialchars($cliente->nombre); ?></td>
                    <td><?php echo htmlspecialchars($cliente->telefono); ?></td>
                    <td><?php echo htmlspecialchars($cliente->direccion); ?></td>
                    <td>
                        <div class="d-flex gap-1 justify-content-end">
                        <a class="btn btn-outline-secondary btn-sm btn-icono" href="<?= url('clientes/editar.php') ?>?id=<?php echo $cliente->id;?>" title="Editar" aria-label="Editar <?= htmlspecialchars($cliente->nombre) ?>">
                            <i class="fa fa-edit"></i>
                        </a>
                        <?php if(esAdministrador()): ?>
                        <a class="btn btn-outline-danger btn-sm btn-icono" href="<?= url('clientes/eliminar.php') ?>?id=<?php echo $cliente->id;?>"
                           title="Eliminar" aria-label="Eliminar <?= htmlspecialchars($cliente->nombre) ?>"
                           onclick="return confirm('¿Eliminar al cliente <?= htmlspecialchars($cliente->nombre, ENT_QUOTES) ?>?')">
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
    <?php else: ?>
    <div class="estado-vacio">
        <i class="fa fa-user-friends"></i>
        <h4>Todavía no hay clientes</h4>
        <p class="mb-0">Agrégalos para asignarlos a las ventas.</p>
    </div>
    <?php endif; ?>
    </div>
</div>
