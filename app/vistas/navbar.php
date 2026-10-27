<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
<a class="visually-hidden-focusable saltar-contenido" href="#contenido">Saltar al contenido</a>
<?php
// Sección que se está viendo, solo para resaltarla en el menú (ej. "productos/editar.php", "index.php")
$paginaActual = str_replace('\\', '/', substr((string) realpath(get_included_files()[0]), strlen((string) realpath(PUBLIC_DIR)) + 1));
$enlaceMenu = function($ruta, $icono, $texto, $seccion) use ($paginaActual) {
    $activo = $seccion === 'index.php' ? $paginaActual === 'index.php' : str_starts_with($paginaActual, $seccion);
    return '<li class="nav-item"><a class="nav-link' . ($activo ? ' active" aria-current="page' : '') . '" href="' . url($ruta) . '">'
         . '<i class="' . $icono . '"></i><span>' . $texto . '</span></a></li>';
};
?>
<nav class="navbar navbar-expand-xl navbar-light navbar-custom">
  <div class="container-fluid">
    <a class="navbar-brand" href="<?= url('index.php') ?>">
      <?php if(EMPRESA_LOGO): ?>
        <img src="<?= url(EMPRESA_LOGO) ?>" alt="<?= htmlspecialchars(EMPRESA_NOMBRE) ?>" height="38">
      <?php else: ?>
        <span class="marca-texto">
          <span class="marca-icono"><i class="<?= htmlspecialchars(EMPRESA_ICONO) ?>"></i></span>
          <?= htmlspecialchars(EMPRESA_NOMBRE) ?>
        </span>
      <?php endif; ?>
    </a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Abrir menú">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarSupportedContent">
      <ul class="navbar-nav me-auto">
        <?php if(esAdministrador()): ?>
          <?= $enlaceMenu('index.php', 'fas fa-home', 'Inicio', 'index.php') ?>
        <?php endif; ?>
        <?= $enlaceMenu('ventas/index.php', 'fas fa-cash-register', 'Vender', 'ventas/') ?>
        <?= $enlaceMenu('caja/index.php', 'fas fa-money-bill-wave', 'Caja', 'caja/index.php') ?>
        <?= $enlaceMenu('productos/index.php', 'fas fa-box', 'Productos', 'productos/') ?>
        <?php if(esAdministrador()): ?>
          <?= $enlaceMenu('categorias/index.php', 'fas fa-tags', 'Categorías', 'categorias/') ?>
        <?php endif; ?>
        <?= $enlaceMenu('clientes/index.php', 'fas fa-user-friends', 'Clientes', 'clientes/') ?>
        <?php if(esAdministrador()): ?>
          <?= $enlaceMenu('compras/index.php', 'fas fa-truck', 'Comprar', 'compras/') ?>

          <li class="nav-item dropdown">
            <?php $enReportes = str_starts_with($paginaActual, 'reportes/') || str_starts_with($paginaActual, 'ajustes/') || in_array($paginaActual, ['caja/cierres.php', 'caja/cierre.php']); ?>
            <a class="nav-link dropdown-toggle<?= $enReportes ? ' active' : '' ?>" href="#" id="reportesDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="fas fa-chart-bar"></i>
              <span>Reportes</span>
            </a>
            <ul class="dropdown-menu dropdown-menu-custom" aria-labelledby="reportesDropdown">
              <li><a class="dropdown-item<?= $paginaActual === 'reportes/ventas.php' ? ' active' : '' ?>" href="<?= url('reportes/ventas.php') ?>"><i class="fas fa-file-invoice-dollar me-2"></i>Ventas</a></li>
              <li><a class="dropdown-item<?= $paginaActual === 'reportes/compras.php' ? ' active' : '' ?>" href="<?= url('reportes/compras.php') ?>"><i class="fas fa-file-invoice me-2"></i>Compras</a></li>
              <li><a class="dropdown-item<?= in_array($paginaActual, ['caja/cierres.php', 'caja/cierre.php']) ? ' active' : '' ?>" href="<?= url('caja/cierres.php') ?>"><i class="fas fa-cash-register me-2"></i>Cierres de caja</a></li>
              <li><a class="dropdown-item<?= str_starts_with($paginaActual, 'ajustes/') ? ' active' : '' ?>" href="<?= url('ajustes/index.php') ?>"><i class="fas fa-sliders-h me-2"></i>Ajustes de inventario</a></li>
            </ul>
          </li>

          <?= $enlaceMenu('usuarios/index.php', 'fas fa-users', 'Usuarios', 'usuarios/') ?>
        <?php endif; ?>
      </ul>

      <!-- Usuario conectado: perfil, contraseña y salir -->
      <ul class="navbar-nav usuario-menu">
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle<?= str_starts_with($paginaActual, 'perfil/') ? ' active' : '' ?>" href="#" id="usuarioDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <span class="usuario-avatar" aria-hidden="true"><?= htmlspecialchars(mb_substr($_SESSION['usuario'], 0, 1)) ?></span>
            <span class="usuario-datos">
              <span class="usuario-nombre"><?= htmlspecialchars($_SESSION['usuario']) ?></span>
              <span class="usuario-rol"><?= htmlspecialchars(nombreRol(rolActual())) ?></span>
            </span>
          </a>
          <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="usuarioDropdown">
            <li><a class="dropdown-item" href="<?= url('perfil/index.php') ?>"><i class="fas fa-user-circle me-2"></i>Mi perfil</a></li>
            <li><a class="dropdown-item" href="<?= url('perfil/cambiar_password.php') ?>"><i class="fas fa-key me-2"></i>Cambiar contraseña</a></li>
            <?php if(esAdministrador()): ?>
            <li><a class="dropdown-item<?= str_starts_with($paginaActual, 'empresa/') ? ' active' : '' ?>" href="<?= url('empresa/index.php') ?>"><i class="fas fa-building me-2"></i>Datos de la empresa</a></li>
            <li><a class="dropdown-item<?= str_starts_with($paginaActual, 'respaldos/') ? ' active' : '' ?>" href="<?= url('respaldos/index.php') ?>"><i class="fas fa-database me-2"></i>Respaldos</a></li>
            <?php endif; ?>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="<?= url('cerrar_sesion.php') ?>"><i class="fas fa-sign-out-alt me-2"></i>Cerrar sesión</a></li>
          </ul>
        </li>
      </ul>
    </div>
  </div>
</nav>

<?php if(!empty($_SESSION['aviso_permiso'])): ?>
<div class="container-xl aviso-global">
  <div class="alert alert-warning alert-dismissible fade show mb-0" role="alert">
    <i class="fas fa-lock"></i> <?= htmlspecialchars($_SESSION['aviso_permiso']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
  </div>
</div>
<?php unset($_SESSION['aviso_permiso']); endif; ?>

<script>
// Accesibilidad sin tocar cada página: el contenido que sigue a la barra es la zona principal
// (destino de "Saltar al contenido") y los iconos son decorativos para los lectores de pantalla.
document.addEventListener('DOMContentLoaded', function() {
  var contenido = document.querySelector('.navbar-custom ~ .container-xl:not(.aviso-global), .navbar-custom ~ .container, .navbar-custom ~ .container-fluid');
  if (contenido) {
    contenido.setAttribute('role', 'main');
    contenido.setAttribute('tabindex', '-1');
    if (!contenido.id) contenido.id = 'contenido';
  }
  document.querySelectorAll('i.fa, i.fas, i.far, i.fab, i.bi').forEach(function(icono) {
    if (!icono.hasAttribute('aria-hidden')) icono.setAttribute('aria-hidden', 'true');
  });
});
</script>
