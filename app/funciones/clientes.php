<?php
// Clientes.

function eliminarCliente($id){
    $sentencia = "DELETE FROM clientes WHERE id = ?";
    return eliminar($sentencia, $id);
}

function editarCliente($nombre, $telefono, $direccion, $id){
    $sentencia = "UPDATE clientes SET nombre = ?, telefono = ?, direccion = ? WHERE id = ?";
    $parametros = [$nombre, $telefono, $direccion, $id];
    return editar($sentencia, $parametros);
}

function obtenerClientePorId($id){
    $sentencia = "SELECT * FROM clientes WHERE id = ?";
    $cliente = select($sentencia, [$id]);
    if($cliente) return $cliente[0];
}

function obtenerClientes(){
    $sentencia = "SELECT * FROM clientes";
    return select($sentencia);
}

function registrarCliente($nombre, $telefono, $direccion){
    $sentencia = "INSERT INTO clientes (nombre, telefono, direccion) VALUES (?,?,?)";
    $parametros = [$nombre, $telefono, $direccion];
    return insertar($sentencia, $parametros);
}

function obtenerNumeroClientes(){
    $sentencia = "SELECT IFNULL(COUNT(*),0) AS total FROM clientes";
    return select($sentencia)[0]->total;
}
