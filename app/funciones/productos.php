<?php
// Productos e inventario.

function obtenerProductoPorCodigo($codigo){
    $sentencia = "SELECT id, codigo, nombre, compra, venta, existencia, unidad, clasificacion, idCategoria FROM productos WHERE codigo = ?";
    $producto = select($sentencia, [$codigo]);
    if($producto) return $producto[0];
    return []; 
}

function obtenerProductoPorId($id){
    $sentencia = "SELECT * FROM productos WHERE id = ?";
    return select($sentencia, [$id])[0] ?? null;
}

// Cada producto trae además el nombre de su categoría (->categoria, null si no tiene).
// $idCategoria: id de una categoría, o 'ninguna' para los productos sin categoría.
function obtenerProductos($busqueda = null, $idCategoria = null){
    $parametros = [];
    $condiciones = [];
    $sentencia = "SELECT productos.*, categorias.nombre AS categoria
    FROM productos
    LEFT JOIN categorias ON categorias.id = productos.idCategoria";
    if(isset($busqueda)){
        $condiciones[] = "(productos.nombre LIKE ? OR productos.codigo LIKE ?)";
        array_push($parametros, "%".$busqueda."%", "%".$busqueda."%");
    }
    if($idCategoria === 'ninguna'){
        $condiciones[] = "productos.idCategoria IS NULL";
    } elseif(!empty($idCategoria)){
        $condiciones[] = "productos.idCategoria = ?";
        array_push($parametros, $idCategoria);
    }
    if($condiciones){
        $sentencia .= " WHERE " . implode(" AND ", $condiciones);
    }
    return select($sentencia, $parametros);
}

function registrarProducto($codigo, $nombre, $venta, $clasificacion, $unidad = 'und', $idCategoria = null){
    $sentencia = "INSERT INTO productos(codigo, nombre, compra, venta, existencia, unidad, clasificacion, idCategoria) VALUES (?,?,?,?,?,?,?,?)";
    $parametros = [$codigo, $nombre, 0, $venta, 0, $unidad, $clasificacion, $idCategoria];
    return insertar($sentencia, $parametros);
}


// No toca compra ni existencia: esas solo cambian con compras y ventas.
function editarProducto($codigo, $nombre, $venta, $clasificacion, $unidad, $idCategoria, $id){
    $sentencia = "UPDATE productos SET codigo = ?, nombre = ?, venta = ?, clasificacion = ?, unidad = ?, idCategoria = ? WHERE id = ?";
    $parametros = [$codigo, $nombre, $venta, $clasificacion, $unidad, $idCategoria, $id];
    return editar($sentencia, $parametros);
}

function obtenerProductoPorNombre($busqueda) {
    $sentencia = "SELECT * FROM productos WHERE nombre = ? OR codigo = ? LIMIT 1";
    $resultado = select($sentencia, [$busqueda, $busqueda]);
    return $resultado[0] ?? null;
}

// Solo se eliminan productos sin compras ni ventas; sus ajustes de inventario se van con ellos.
function eliminarProducto($id){
    eliminar("DELETE FROM ajustes_inventario WHERE idProducto = ?", $id);
    $sentencia = "DELETE FROM productos WHERE id = ?";
    return eliminar($sentencia, $id);
}

// Suma de existencias de todos los productos (mezcla unidades: metros, kilos, unidades...).
function obtenerNumeroProductos(){
    $sentencia = "SELECT IFNULL(SUM(existencia),0) AS total FROM productos";
    $fila = select($sentencia);
    if($fila) return $fila[0]->total;
}

// Cuántos productos distintos hay registrados.
function contarProductos(){
    $sentencia = "SELECT COUNT(*) AS total FROM productos";
    return select($sentencia)[0]->total;
}

// Deja en cada venta o compra solo las líneas para las que $incluir($linea) es verdadero y recalcula su total.
// Las que se quedan sin líneas se descartan. Se usa en los reportes para filtrar por categoría.
function filtrarLineasPorProducto($movimientos, callable $incluir){
    $filtrados = [];
    foreach($movimientos as $movimiento){
        $lineas = array_values(array_filter($movimiento->productos, $incluir));
        if(!$lineas) continue;
        $copia = clone $movimiento;
        $copia->productos = $lineas;
        $copia->total = 0;
        foreach($lineas as $linea){
            $copia->total += $linea->cantidad * $linea->precio;
        }
        $filtrados[] = $copia;
    }
    return $filtrados;
}

function obtenerTotalInventario(){
    $sentencia = "SELECT IFNULL(SUM(existencia * venta),0) AS total FROM productos";
    $fila = select($sentencia);
    if($fila) return $fila[0]->total;
}

function calcularGananciaProductos(){
    $sentencia = "SELECT IFNULL(SUM(existencia*venta) - SUM(existencia*compra),0) AS total FROM productos";
    $fila = select($sentencia);
    if($fila) return $fila[0]->total;
}

function descontarProductos($idProducto, $cantidad){
    $sentencia = "UPDATE productos SET existencia = existencia - ? WHERE id = ?";
    $parametros = [$cantidad, $idProducto];
    return editar($sentencia, $parametros);
}

function aumentarProductos($idProducto, $cantidad){
    $sentencia = "UPDATE productos SET existencia = existencia + ? WHERE id = ?";
    $parametros = [$cantidad, $idProducto];
    return editar($sentencia, $parametros);
}

// Verificar si un producto tiene compras asociadas
function productoConCompras($idProducto) {
    $sentencia = "SELECT COUNT(*) as total FROM productos_compras WHERE idProducto = ?";
    $resultado = select($sentencia, [$idProducto]);
    return $resultado[0]->total > 0;
}

// Verificar si un producto tiene ventas asociadas
function productoConVentas($idProducto) {
    $sentencia = "SELECT COUNT(*) as total FROM productos_ventas WHERE idProducto = ?";
    $resultado = select($sentencia, [$idProducto]);
    return $resultado[0]->total > 0;
}

// Obtener la clasificación (tienda) de un producto por su nombre
function obtenerClasificacionProducto($nombreProducto) {
    $sentencia = "SELECT clasificacion FROM productos WHERE nombre = ? LIMIT 1";
    $resultado = select($sentencia, [$nombreProducto]);
    
    if(empty($resultado)) {
        $sentencia = "SELECT clasificacion FROM productos WHERE nombre LIKE ? LIMIT 1";
        $resultado = select($sentencia, ["%$nombreProducto%"]);
    }
    
    return $resultado[0]->clasificacion ?? null;
}

// Obtener los nombres de todos los productos con una clasificación específica
function obtenerProductosPorClasificacion($clasificacion) {
    $sentencia = "SELECT nombre FROM productos WHERE clasificacion = ?";
    $resultado = select($sentencia, [$clasificacion]);
    
    $productos = [];
    foreach($resultado as $item) {
        $productos[] = $item->nombre;
    }
    return $productos;
}

// Historial de compras de un producto
function obtenerComprasDeProducto($idProducto) {
    $sentencia = "SELECT pc.cantidad, pc.precio, c.fecha, c.id as idCompra, u.usuario 
              FROM productos_compras pc
              INNER JOIN compras c ON c.id = pc.idCompra
              INNER JOIN usuarios u ON u.id = c.idUsuario
              WHERE pc.idProducto = ?
              ORDER BY c.fecha DESC";
    return select($sentencia, [$idProducto]);
}

// Historial de ventas de un producto (incluye las anuladas, marcadas con ->anulada)
function obtenerVentasDeProducto($idProducto) {
    $sentencia = "SELECT pv.cantidad, pv.precio, v.fecha, v.id as idVenta, v.anulada, IFNULL(cl.nombre, 'MOSTRADOR') as cliente 
              FROM productos_ventas pv
              INNER JOIN ventas v ON v.id = pv.idVenta
              LEFT JOIN clientes cl ON cl.id = v.idCliente
              WHERE pv.idProducto = ?
              ORDER BY v.fecha DESC";
    return select($sentencia, [$idProducto]);
}
