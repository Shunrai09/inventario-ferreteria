<?php
// Conexión a MySQL y helpers de consultas preparadas.
// Los datos de conexión vienen de config/config.php (constante BASE_DATOS).

function conectarBaseDatos() {
    $host = BASE_DATOS['host'];
    $db   = BASE_DATOS['nombre'];
    $user = BASE_DATOS['usuario'];
    $pass = BASE_DATOS['password'];
    $charset = BASE_DATOS['charset'];

    $options = [
        \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,   
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_OBJ,
        \PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    
    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    
    try {
        $pdo = new \PDO($dsn, $user, $pass, $options);
        $pdo->exec("SET time_zone = " . $pdo->quote(ZONA_HORARIA_BD));
        
        return $pdo;
    } catch (\PDOException $e) {
        throw new \PDOException($e->getMessage(), (int)$e->getCode());
    }
}

function select($sentencia, $parametros = []){
    $bd = conectarBaseDatos();
    $respuesta = $bd->prepare($sentencia);
    $respuesta->execute($parametros);
    return $respuesta->fetchAll();
}

function insertar($sentencia, $parametros){
    $bd = conectarBaseDatos();
    $respuesta = $bd->prepare($sentencia);
    return $respuesta->execute($parametros);
}

function eliminar($sentencia, $id){
    $bd = conectarBaseDatos();
    $respuesta = $bd->prepare($sentencia);
    return $respuesta->execute([$id]);
}

function editar($sentencia, $parametros){
    $bd = conectarBaseDatos();
    $respuesta = $bd->prepare($sentencia);
    return $respuesta->execute($parametros);
}
