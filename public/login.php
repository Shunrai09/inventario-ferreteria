<?php
require_once __DIR__ . "/../app/bootstrap.php";
include_once VISTAS_DIR . "/encabezado.php";
?>
<div class="login-container">
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-xl-8 col-lg-10">
                <div class="login-card">
                    <div class="row g-0">
                        <!-- Columna izquierda con logo -->
                        <div class="col-lg-6 d-none d-lg-block">
                            <div class="login-left text-white p-5 h-100">
                                <div class="text-center">
                                    <?php if(EMPRESA_LOGO): ?>
                                        <img src="<?= url(EMPRESA_LOGO) ?>" class="login-logo mb-4" alt="<?= htmlspecialchars(EMPRESA_NOMBRE) ?>">
                                    <?php else: ?>
                                        <div class="login-marca mb-3"><i class="<?= htmlspecialchars(EMPRESA_ICONO) ?>"></i></div>
                                    <?php endif; ?>
                                    <h3 class="fw-bold mb-1"><?= htmlspecialchars(EMPRESA_NOMBRE) ?></h3>
                                    <p class="mb-3 opacity-75"><?= htmlspecialchars(EMPRESA_SISTEMA) ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Columna derecha con formulario -->
                        <div class="col-lg-6">
                            <div class="login-right h-100">
                                <div class="text-center mb-4 d-lg-none">
                                    <?php if(EMPRESA_LOGO): ?>
                                        <img src="<?= url(EMPRESA_LOGO) ?>" style="max-width: 120px; margin-bottom: 20px;" alt="<?= htmlspecialchars(EMPRESA_NOMBRE) ?>">
                                    <?php else: ?>
                                        <h4 class="fw-bold" style="color: var(--color-primario-oscuro);"><i class="<?= htmlspecialchars(EMPRESA_ICONO) ?>"></i> <?= htmlspecialchars(EMPRESA_NOMBRE) ?></h4>
                                    <?php endif; ?>
                                </div>
                                
                                <h3 class="login-title">Iniciar Sesión</h3>
                                
                                <form action="<?= url('iniciar_sesion.php') ?>" method="post" id="loginForm">
                                    <div class="mb-4">
                                        <label for="usuario" class="form-label">
                                            <i class="fa fa-user me-2"></i>Usuario
                                        </label>
                                        <div class="position-relative">
                                            <i class="fa fa-user input-group-icon"></i>
                                            <input type="text" 
                                                   class="form-control form-control-lg input-with-icon" 
                                                   id="usuario" 
                                                   name="usuario" 
                                                   placeholder="Ingresa tu usuario" 
                                                   required
                                                   autocomplete="username"
                                                   autofocus>
                                        </div>
                                        <div class="form-text">
                                            Usuario registrado en el sistema
                                        </div>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <label for="password" class="form-label">
                                            <i class="fa fa-lock me-2"></i>Contraseña
                                        </label>
                                        <div class="position-relative">
                                            <i class="fa fa-lock input-group-icon"></i>
                                            <input type="password" 
                                                   class="form-control form-control-lg input-with-icon" 
                                                   id="password" 
                                                   name="password" 
                                                   placeholder="Ingresa tu contraseña" 
                                                   required
                                                   autocomplete="current-password">
                                        </div>
                                        <div class="form-text">
                                            Contraseña de acceso al sistema
                                        </div>
                                    </div>
                                    
                                    <!-- Mostrar mensaje de error si existe -->
                                    <?php if(isset($_GET['error'])): ?>
                                        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                                            <i class="fa fa-exclamation-circle me-2"></i>
                                            <?php 
                                            switch($_GET['error']) {
                                                case '1': echo 'Usuario o contraseña incorrectos'; break;
                                                case '2': echo 'Debe completar todos los campos'; break;
                                                case '3': echo 'Sesión expirada, ingrese nuevamente'; break;
                                                default: echo 'Error al iniciar sesión';
                                            }
                                            ?>
                                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Mostrar mensaje de éxito si existe -->
                                    <?php if(isset($_GET['success'])): ?>
                                        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                                            <i class="fa fa-check-circle me-2"></i>
                                            <?php 
                                            switch($_GET['success']) {
                                                case '1': echo 'Sesión iniciada correctamente'; break;
                                                case '2': echo 'Contraseña cambiada exitosamente'; break;
                                                default: echo 'Operación completada';
                                            }
                                            ?>
                                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="d-grid gap-2 mt-4 pt-2">
                                        <button type="submit" name="ingresar" class="btn btn-login btn-lg" style="color: #fff;">
                                            <i class="fa fa-sign-in-alt me-2" ></i> Ingresar al Sistema
                                        </button>
                                    </div>
                                    
                                    <div class="login-footer mt-4">
                                        <p class="mb-0">
                                            <i class="fa fa-info-circle me-1"></i>
                                            Sistema desarrollado para <?= htmlspecialchars(EMPRESA_NOMBRE) ?>
                                        </p>
                                        
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('loginForm');
    const usuarioInput = document.getElementById('usuario');
    const passwordInput = document.getElementById('password');
    
    // Validación del formulario
    form.addEventListener('submit', function(e) {
        let valid = true;
        
        // Limpiar errores previos
        document.querySelectorAll('.is-invalid').forEach(el => {
            el.classList.remove('is-invalid');
        });
        
        // Validar usuario
        if(!usuarioInput.value.trim()) {
            usuarioInput.classList.add('is-invalid');
            valid = false;
        }
        
        // Validar contraseña
        if(!passwordInput.value.trim()) {
            passwordInput.classList.add('is-invalid');
            valid = false;
        }
        
        if(!valid) {
            e.preventDefault();
            
            // Mostrar mensaje de error
            if(!usuarioInput.value.trim() && !passwordInput.value.trim()) {
                alert('Por favor, ingrese usuario y contraseña');
            } else if(!usuarioInput.value.trim()) {
                alert('Por favor, ingrese su usuario');
            } else if(!passwordInput.value.trim()) {
                alert('Por favor, ingrese su contraseña');
            }
        }
    });
    
    // Quitar clase de error al empezar a escribir
    usuarioInput.addEventListener('input', function() {
        if(this.classList.contains('is-invalid')) {
            this.classList.remove('is-invalid');
        }
    });
    
    passwordInput.addEventListener('input', function() {
        if(this.classList.contains('is-invalid')) {
            this.classList.remove('is-invalid');
        }
    });
    
    // Auto-focus en el campo de usuario
    usuarioInput.focus();
    
    // Mostrar/ocultar contraseña (opcional)
    const togglePassword = document.createElement('button');
    togglePassword.type = 'button';
    togglePassword.className = 'btn btn-sm position-absolute end-0 top-50 translate-middle-y me-3';
    togglePassword.innerHTML = '<i class="fa fa-eye" aria-hidden="true"></i>';
    togglePassword.setAttribute('aria-label', 'Mostrar contraseña');
    togglePassword.setAttribute('aria-pressed', 'false');
    togglePassword.style.zIndex = '5';
    
    passwordInput.parentNode.appendChild(togglePassword);
    
    togglePassword.addEventListener('click', function() {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        this.innerHTML = type === 'password' ? '<i class="fa fa-eye" aria-hidden="true"></i>' : '<i class="fa fa-eye-slash" aria-hidden="true"></i>';
        this.setAttribute('aria-pressed', type === 'password' ? 'false' : 'true');
    });
});
</script>