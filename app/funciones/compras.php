<?php
// Compras: registro, consultas y totales.

function obtenerNumeroCompras(){
    $sentencia = "SELECT IFNULL(COUNT(*),0) AS total FROM compras";
    return select($sentencia)[0]->total;
}

function obtenerTotalCompras($idUsuario = null){
    $parametros = [];
    $sentencia = "SELECT IFNULL(SUM(total),0) AS total FROM compras";
    if(isset($idUsuario)){
        $sentencia .= " WHERE idUsuario = ?";
        array_push($parametros, $idUsuario);
    }
    $fila = select($sentencia, $parametros);
    if($fila) return $fila[0]->total;
}

function obtenerTotalComprasHoy($idUsuario = null){
    $parametros = [];
    $sentencia = "SELECT IFNULL(SUM(total),0) AS total FROM compras WHERE DATE(fecha) = CURDATE() ";
    if(isset($idUsuario)){
        $sentencia .= " AND idUsuario = ?";
        array_push($parametros, $idUsuario);
    }
    $fila = select($sentencia, $parametros);
    if($fila) return $fila[0]->total;
}

function obtenerTotalComprasSemana($idUsuario = null){
    $parametros = [];
    $sentencia = "SELECT IFNULL(SUM(total),0) AS total FROM compras WHERE YEARWEEK(fecha) = YEARWEEK(NOW())";
    if(isset($idUsuario)){
        $sentencia .= " AND idUsuario = ?";
        array_push($parametros, $idUsuario);
    }
    $fila = select($sentencia, $parametros);
    if($fila) return $fila[0]->total;
}

function obtenerTotalComprasMes($idUsuario = null){
    $parametros = [];
    $sentencia = "SELECT IFNULL(SUM(total),0) AS total FROM compras WHERE MONTH(fecha) = MONTH(CURRENT_DATE()) AND YEAR(fecha) = YEAR(CURRENT_DATE())";
    if(isset($idUsuario)){
        $sentencia .= " AND idUsuario = ?";
        array_push($parametros, $idUsuario);
    }
    $fila = select($sentencia, $parametros);
    if($fila) return $fila[0]->total;
}

// Los filtros se combinan: usuario, fecha inicial y fecha final (cualquiera puede faltar).
// Sin fechas devuelve las compras de hoy, salvo que se filtre por usuario: entonces, todas las suyas.
function obtenerCompras($fechaInicio, $fechaFin, $usuario){
    $parametros = [];
    $condiciones = [];
    $sentencia = "SELECT compras.*, usuarios.usuario
    FROM compras 
    INNER JOIN usuarios ON usuarios.id = compras.idUsuario";

    if(isset($usuario)){
        $condiciones[] = "compras.idUsuario = ?";
        array_push($parametros, $usuario);
    }
    if(empty($fechaInicio) && empty($fechaFin) && !isset($usuario)){
        $condiciones[] = "DATE(compras.fecha) = ?";
        array_push($parametros, HOY);
    }
    if(!empty($fechaInicio)){
        $condiciones[] = "DATE(compras.fecha) >= ?";
        array_push($parametros, $fechaInicio);
    }
    if(!empty($fechaFin)){
        $condiciones[] = "DATE(compras.fecha) <= ?";
        array_push($parametros, $fechaFin);
    }
    if($condiciones){
        $sentencia .= " WHERE " . implode(" AND ", $condiciones);
    }
    $sentencia .= " ORDER BY compras.fecha, compras.id";

    $compras = select($sentencia, $parametros);
    return agregarProductosComprados($compras);
}

function agregarProductosComprados($compras){
    foreach($compras as $compra){
        $compra->productos = obtenerProductosComprados($compra->id);
    }
    return $compras;
}

function obtenerProductosComprados($idCompra){
    $sentencia = "SELECT productos_compras.cantidad, productos_compras.precio, productos.nombre,
    productos.id AS idProducto, productos.unidad, productos.idCategoria
    FROM productos_compras
    INNER JOIN productos ON productos.id = productos_compras.idProducto
    WHERE idCompra = ? ";
    return select($sentencia, [$idCompra]);
}

function registrarCompra($productos, $idUsuario, $total, $notas = null){
    $sentencia = "INSERT INTO compras (fecha, total, idUsuario, notas) VALUES (?,?,?,?)";
    $parametros = [date("Y-m-d H:i:s"), $total, $idUsuario, $notas];

    $resultadoCompra = insertar($sentencia, $parametros);
    if($resultadoCompra){
        $idCompra = obtenerUltimoIdCompra();
        $productosRegistrados = registrarProductosCompra($productos, $idCompra);
        return $resultadoCompra && $productosRegistrados;
    }
}

function registrarProductosCompra($productos, $idCompra){
    $sentencia = "INSERT INTO productos_compras (cantidad, precio, idProducto, idCompra) VALUES (?,?,?,?)";
    foreach ($productos as $producto) {
        $parametros = [$producto->cantidad, $producto->compra, $producto->id, $idCompra];
        insertar($sentencia, $parametros);
        aumentarProductos($producto->id, $producto->cantidad);
    }
    return true;
}

function obtenerUltimoIdCompra(){
    $sentencia = "SELECT id FROM compras ORDER BY id DESC LIMIT 1";
    return select($sentencia)[0]->id;
}

function calcularTotalCompras($compras){
    $total = 0;
    foreach ($compras as $compra) {
        $total += $compra->total;
    }
    return $total;
}

function calcularProductosComprados($compras){
    $total = 0;
    foreach ($compras as $compra) {
        foreach ($compra->productos as $producto) {
            $total += $producto->cantidad;
        }
    }
    return $total;
}

function registrarCompraIndividual($idProducto, $cantidad, $precio, $fecha, $total, $idUsuario, $notas = null) {
    $bd = conectarBaseDatos();
    
    try {
        $bd->beginTransaction();
        
        // 1. Insertar en tabla compras
        $sentenciaCompra = "INSERT INTO compras (fecha, total, idUsuario, notas) VALUES (?, ?, ?, ?)";
        $stmtCompra = $bd->prepare($sentenciaCompra);
        $stmtCompra->execute([$fecha, $total, $idUsuario, $notas]);
        $idCompra = $bd->lastInsertId();
        
        // 2. Insertar en productos_compras
        $sentenciaProductoCompra = "INSERT INTO productos_compras (cantidad, precio, idProducto, idCompra) VALUES (?, ?, ?, ?)";
        $stmtProductoCompra = $bd->prepare($sentenciaProductoCompra);
        $stmtProductoCompra->execute([$cantidad, $precio, $idProducto, $idCompra]);
        
        // 3. Actualizar existencia y precio de compra del producto
        $sentenciaActualizar = "UPDATE productos SET existencia = existencia + ?, compra = ? WHERE id = ?";
        $stmtActualizar = $bd->prepare($sentenciaActualizar);
        $stmtActualizar->execute([$cantidad, $precio, $idProducto]);
        
        $bd->commit();
        return true;
        
    } catch(Exception $e) {
        $bd->rollBack();
        throw $e;
    }
}


function obtenerComprasIndividuales($filtroFecha = null) {
    $parametros = [];
    $sentencia = "SELECT c.*, p.nombre as producto_nombre, p.codigo, u.usuario 
                  FROM compras c
                  INNER JOIN productos_compras pc ON pc.idCompra = c.id
                  INNER JOIN productos p ON p.id = pc.idProducto
                  INNER JOIN usuarios u ON u.id = c.idUsuario";
    
    if($filtroFecha) {
        $sentencia .= " WHERE DATE(c.fecha) = ?";
        $parametros[] = $filtroFecha;
    }
    
    $sentencia .= " ORDER BY c.fecha DESC";
    
    return select($sentencia, $parametros);
}

function obtenerCompraPorId($idCompra) {
    $sentencia = "SELECT c.*, u.usuario 
                  FROM compras c
                  INNER JOIN usuarios u ON u.id = c.idUsuario
                  WHERE c.id = ?";
    $compra = select($sentencia, [$idCompra]);
    
    if($compra) {
        $compra[0]->productos = obtenerProductosComprados($idCompra);
        return $compra[0];
    }
    
    return null;
}

// Elimina una compra y descuenta del inventario las cantidades compradas (sin bajar de 0), todo en una transacción.
// El stock se descuenta por id de producto (antes se buscaba por nombre y fallaba si el producto se había renombrado).
function eliminarCompra($idCompra){
    $compra = obtenerCompraPorId($idCompra);
    if(!$compra) return false;

    $bd = conectarBaseDatos();
    $bd->beginTransaction();
    try {
        $descontar = $bd->prepare("UPDATE productos SET existencia = CASE WHEN existencia > ? THEN existencia - ? ELSE 0 END WHERE id = ?");
        foreach($compra->productos as $productoComprado) {
            $descontar->execute([$productoComprado->cantidad, $productoComprado->cantidad, $productoComprado->idProducto]);
        }
        $bd->prepare("DELETE FROM productos_compras WHERE idCompra = ?")->execute([$idCompra]);
        $bd->prepare("DELETE FROM compras WHERE id = ?")->execute([$idCompra]);
        $bd->commit();
    } catch(Throwable $e) {
        $bd->rollBack();
        throw $e;
    }
    return true;
}
