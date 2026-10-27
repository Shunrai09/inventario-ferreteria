<?php
// Categorías de productos. Un producto puede no tener categoría (productos.idCategoria NULL).

// Todas las categorías por nombre, con cuántos productos tiene cada una.
function obtenerCategorias(){
    $sentencia = "SELECT categorias.id, categorias.nombre, COUNT(productos.id) AS productos
    FROM categorias
    LEFT JOIN productos ON productos.idCategoria = categorias.id
    GROUP BY categorias.id, categorias.nombre
    ORDER BY categorias.nombre";
    return select($sentencia);
}

function obtenerCategoriaPorId($id){
    $sentencia = "SELECT * FROM categorias WHERE id = ?";
    $categoria = select($sentencia, [$id]);
    return $categoria[0] ?? null;
}

// Sirve para no repetir nombres; con la intercalación de MySQL no distingue mayúsculas ni tildes.
function obtenerCategoriaPorNombre($nombre){
    $sentencia = "SELECT * FROM categorias WHERE nombre = ?";
    $categoria = select($sentencia, [$nombre]);
    return $categoria[0] ?? null;
}

function registrarCategoria($nombre){
    $sentencia = "INSERT INTO categorias (nombre) VALUES (?)";
    return insertar($sentencia, [$nombre]);
}

function editarCategoria($nombre, $id){
    $sentencia = "UPDATE categorias SET nombre = ? WHERE id = ?";
    return editar($sentencia, [$nombre, $id]);
}

function contarProductosDeCategoria($id){
    $sentencia = "SELECT COUNT(*) AS total FROM productos WHERE idCategoria = ?";
    return (int) select($sentencia, [$id])[0]->total;
}

// Solo elimina categorías vacías, para que ningún producto quede apuntando a una categoría inexistente.
function eliminarCategoria($id){
    if(contarProductosDeCategoria($id) > 0) return false;
    $sentencia = "DELETE FROM categorias WHERE id = ?";
    return eliminar($sentencia, $id);
}
