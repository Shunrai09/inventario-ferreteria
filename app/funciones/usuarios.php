<?php
// Usuarios: sesión, contraseñas, roles y mantenimiento de usuarios.

// Roles disponibles. El administrador tiene acceso completo; el vendedor solo al punto de venta,
// a consultar productos (sin costos) y a los clientes. Ver requerirAdministrador() en app/ayudantes.php.
const ROLES = [
    'administrador' => 'Administrador',
    'vendedor'      => 'Vendedor',
];

function nombreRol($rol){
    return ROLES[$rol] ?? (string) $rol;
}

function iniciarSesion($usuario, $password){
    $sentencia = "SELECT id, usuario, rol FROM usuarios WHERE usuario = ?";
    $resultado = select($sentencia, [$usuario]);
    if($resultado){
        $usuario = $resultado[0];
        $verificaPass = verificarPassword($usuario->id, $password);
        if($verificaPass) return $usuario;
    }
}

function verificarPassword($idUsuario, $password){
    $sentencia = "SELECT password FROM usuarios WHERE id = ?";
    $contrasenia = select($sentencia, [$idUsuario])[0]->password;
    $verifica = password_verify($password, $contrasenia);
    if($verifica) return true;
}

function cambiarPassword($idUsuario, $password){
    $nueva = password_hash($password, PASSWORD_DEFAULT);
    $sentencia = "UPDATE usuarios SET password = ? WHERE id = ?";
    return editar($sentencia, [$nueva, $idUsuario]);
}

function eliminarUsuario($id){
    $sentencia = "DELETE FROM usuarios WHERE id = ?";
    return eliminar($sentencia, $id);
}

// $rol null deja el rol como está.
function editarUsuario($usuario, $nombre, $telefono, $direccion, $id, $rol = null){
    $sentencia = "UPDATE usuarios SET usuario = ?, nombre = ?, telefono = ?, direccion = ?" . ($rol !== null ? ", rol = ?" : "") . " WHERE id = ?";
    $parametros = $rol !== null ? [$usuario, $nombre, $telefono, $direccion, $rol, $id] : [$usuario, $nombre, $telefono, $direccion, $id];
    return editar($sentencia, $parametros);
}

function obtenerUsuarioPorId($id){
    $sentencia = "SELECT id, usuario, nombre, telefono, direccion, rol FROM usuarios WHERE id = ?";
    return select($sentencia, [$id])[0] ?? null;
}

// Ventas y compras registradas por el usuario. Si tiene alguna no se elimina, para no dejar movimientos sin autor.
function contarMovimientosDeUsuario($id){
    $sentencia = "SELECT (SELECT COUNT(*) FROM ventas WHERE idUsuario = ?) + (SELECT COUNT(*) FROM compras WHERE idUsuario = ?) AS total";
    return (int) select($sentencia, [$id, $id])[0]->total;
}

function usuarioExiste($usuario, $nombre) {
    $sentencia = "SELECT COUNT(*) as total FROM usuarios WHERE usuario = ? OR nombre = ?";
    $resultado = select($sentencia, [$usuario, $nombre]);
    return $resultado[0]->total > 0;
}

function obtenerUsuarios(){
    $sentencia = "SELECT id, usuario, nombre, telefono, direccion, rol FROM usuarios";
    return select($sentencia);
}

function registrarUsuario($usuario, $nombre, $telefono, $direccion, $rol = 'vendedor'){
    $password = password_hash(PASSWORD_PREDETERMINADA, PASSWORD_DEFAULT);
    $sentencia = "INSERT INTO usuarios (usuario, nombre, telefono, direccion, password, rol) VALUES (?,?,?,?,?,?)";
    $parametros = [$usuario, $nombre, $telefono, $direccion, $password, $rol];
    return insertar($sentencia, $parametros);
}

function obtenerNumeroUsuarios(){
    $sentencia = "SELECT IFNULL(COUNT(*),0) AS total FROM usuarios";
    return select($sentencia)[0]->total;
}

function obtenerIdUsuarioPorNombre($usuario){
    $sentencia = "SELECT id FROM usuarios WHERE usuario = ? LIMIT 1";
    $resultado = select($sentencia, [$usuario]);
    return $resultado[0]->id ?? null;
}
