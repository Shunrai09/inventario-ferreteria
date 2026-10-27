<?php
// Ventas: registro, consultas y totales.

function obtenerNumeroVentas(){
    $sentencia = "SELECT IFNULL(COUNT(*),0) AS total FROM ventas WHERE anulada = 0";
    return select($sentencia)[0]->total;
}

function obtenerVentasPorUsuario(){
    $sentencia = "SELECT SUM(ventas.total) AS total, usuarios.usuario, COUNT(*) AS numeroVentas 
    FROM ventas
    INNER JOIN usuarios ON usuarios.id = ventas.idUsuario
    WHERE ventas.anulada = 0
    GROUP BY ventas.idUsuario
    ORDER BY total DESC";
    return select($sentencia);
}

function obtenerVentasPorCliente(){
    $sentencia = "SELECT SUM(ventas.total) AS total, IFNULL(clientes.nombre, 'MOSTRADOR') AS cliente,
    COUNT(*) AS numeroCompras
    FROM ventas
    LEFT JOIN clientes ON clientes.id = ventas.idCliente
    WHERE ventas.anulada = 0
    GROUP BY ventas.idCliente
    ORDER BY total DESC";
    return select($sentencia);
}

function obtenerProductosMasVendidos(){
    $sentencia = "SELECT SUM(productos_ventas.cantidad * productos_ventas.precio) AS total, 
    SUM(productos_ventas.cantidad) AS unidades,
    productos.nombre, productos.unidad
    FROM productos_ventas 
    INNER JOIN productos ON productos.id = productos_ventas.idProducto
    INNER JOIN ventas ON ventas.id = productos_ventas.idVenta
    WHERE ventas.anulada = 0
    GROUP BY productos_ventas.idProducto
    ORDER BY total DESC
    LIMIT 10";
    return select($sentencia);
}

function obtenerTotalVentas($idUsuario = null){
    $parametros = [];
    $sentencia = "SELECT IFNULL(SUM(total),0) AS total FROM ventas WHERE anulada = 0";
    if(isset($idUsuario)){
        $sentencia .= " AND idUsuario = ?";
        array_push($parametros, $idUsuario);
    }
    $fila = select($sentencia, $parametros);
    if($fila) return $fila[0]->total;
}

function obtenerTotalVentasHoy($idUsuario = null){
    $parametros = [];
    $sentencia = "SELECT IFNULL(SUM(total),0) AS total FROM ventas WHERE anulada = 0 AND DATE(fecha) = CURDATE() ";
    if(isset($idUsuario)){
        $sentencia .= " AND idUsuario = ?";
        array_push($parametros, $idUsuario);
    }
    $fila = select($sentencia, $parametros);
    if($fila) return $fila[0]->total;
}

function obtenerTotalVentasSemana($idUsuario = null){
    $parametros = [];
    $sentencia = "SELECT IFNULL(SUM(total),0) AS total FROM ventas WHERE anulada = 0 AND YEARWEEK(fecha) = YEARWEEK(NOW())";
    if(isset($idUsuario)){
        $sentencia .= " AND idUsuario = ?";
        array_push($parametros, $idUsuario);
    }
    $fila = select($sentencia, $parametros);
    if($fila) return $fila[0]->total;
}

function obtenerTotalVentasMes($idUsuario = null){
    $parametros = [];
    $sentencia = "SELECT IFNULL(SUM(total),0) AS total FROM ventas WHERE anulada = 0 AND MONTH(fecha) = MONTH(CURRENT_DATE()) AND YEAR(fecha) = YEAR(CURRENT_DATE())";
    if(isset($idUsuario)){
        $sentencia .= " AND idUsuario = ?";
        array_push($parametros, $idUsuario);
    }
    $fila = select($sentencia, $parametros);
    if($fila) return $fila[0]->total;
}

function calcularTotalVentas($ventas){
    $total = 0;
    foreach ($ventas as $venta) {
        $total += $venta->total;
    }
    return $total;
}

function calcularProductosVendidos($ventas){
    $total = 0;
    foreach ($ventas as $venta) {
        foreach ($venta->productos as $producto) {
            $total += $producto->cantidad;
        }
    }
    return $total;
}

function obtenerGananciaVentas($ventas){
    $total = 0;
    foreach ($ventas as $venta) {
        foreach ($venta->productos as $producto) {
            $total += $producto->cantidad * ($producto->precio - $producto->compra);
        }
    }
    return $total;
}

// Deja solo las ventas que no están anuladas (las que cuentan en los totales).
function ventasValidas($ventas){
    return array_values(array_filter($ventas, fn($venta) => !$venta->anulada));
}

// Los filtros se combinan: usuario, cliente, fecha inicial y fecha final (cualquiera puede faltar).
// Sin fechas devuelve las ventas de hoy, salvo que se filtre por usuario o cliente: entonces, todas las suyas.
// $estado: 'validas' (sin las anuladas), 'anuladas' o 'todas'. $formaPago: clave de config 'formas_pago'.
function obtenerVentas($fechaInicio, $fechaFin, $cliente, $usuario, $estado = 'validas', $formaPago = null){
    $parametros = [];
    $condiciones = [];
    $sentencia = "SELECT ventas.*, usuarios.usuario, IFNULL(clientes.nombre, 'MOSTRADOR') AS cliente,
    anulo.usuario AS usuarioAnulacion
    FROM ventas
    INNER JOIN usuarios ON usuarios.id = ventas.idUsuario
    LEFT JOIN clientes ON clientes.id = ventas.idCliente
    LEFT JOIN usuarios anulo ON anulo.id = ventas.idUsuarioAnulacion";

    if($estado === 'validas'){
        $condiciones[] = "ventas.anulada = 0";
    } elseif($estado === 'anuladas'){
        $condiciones[] = "ventas.anulada = 1";
    }
    if(!empty($formaPago)){
        $condiciones[] = "ventas.formaPago = ?";
        array_push($parametros, $formaPago);
    }
    if(isset($usuario)){
        $condiciones[] = "ventas.idUsuario = ?";
        array_push($parametros, $usuario);
    }
    if(isset($cliente)){
        $condiciones[] = "ventas.idCliente = ?";
        array_push($parametros, $cliente);
    }
    if(empty($fechaInicio) && empty($fechaFin) && !isset($usuario) && !isset($cliente)){
        $condiciones[] = "DATE(ventas.fecha) = ?";
        array_push($parametros, HOY);
    }
    if(!empty($fechaInicio)){
        $condiciones[] = "DATE(ventas.fecha) >= ?";
        array_push($parametros, $fechaInicio);
    }
    if(!empty($fechaFin)){
        $condiciones[] = "DATE(ventas.fecha) <= ?";
        array_push($parametros, $fechaFin);
    }
    if($condiciones){
        $sentencia .= " WHERE " . implode(" AND ", $condiciones);
    }
    $sentencia .= " ORDER BY ventas.fecha, ventas.id";

    $ventas = select($sentencia, $parametros);
    return agregarProductosVendidos($ventas);
}

function agregarProductosVendidos($ventas){
    foreach($ventas as $venta){
        $venta->productos = obtenerProductosVendidos($venta->id);
    }
    return $ventas;
}

function obtenerProductosVendidos($idVenta){
    $sentencia = "SELECT productos_ventas.cantidad, productos_ventas.precio, productos.nombre,
    productos.compra, productos.id AS idProducto, productos.unidad, productos.idCategoria
    FROM productos_ventas
    INNER JOIN productos ON productos.id = productos_ventas.idProducto
    WHERE idVenta = ? ";
    return select($sentencia, [$idVenta]);
}

// Venta de ejemplo para el ticket de prueba (Datos de la empresa): no se guarda ni toca el stock.
function ventaDePrueba($usuario){
    $productos = [
        (object) ['nombre' => 'Martillo de una 16 oz', 'cantidad' => 1, 'precio' => 25, 'unidad' => 'und'],
        (object) ['nombre' => 'Cable THW 14 AWG', 'cantidad' => 2.5, 'precio' => 2, 'unidad' => 'm'],
    ];
    return (object) [
        'id' => 0, 'fecha' => date("Y-m-d H:i:s"), 'usuario' => $usuario, 'cliente' => 'MOSTRADOR', 'total' => 30,
        'formaPago' => 'efectivo', 'pagoCon' => 50, 'anulada' => 0, 'productos' => $productos,
    ];
}

// Registra la venta, sus líneas y el descuento de stock en una sola transacción.
// $pagoCon: efectivo que entregó el cliente (null si no se indicó o no pagó en efectivo). $idCaja: caja abierta.
// Devuelve el id de la venta registrada (para abrir su ticket).
function registrarVenta($productos, $idUsuario, $idCliente, $total, $formaPago = 'efectivo', $pagoCon = null, $idCaja = null){
    $bd = conectarBaseDatos();
    $bd->beginTransaction();
    try {
        $bd->prepare("INSERT INTO ventas (fecha, total, idUsuario, idCliente, formaPago, pagoCon, idCaja) VALUES (?,?,?,?,?,?,?)")
           ->execute([date("Y-m-d H:i:s"), $total, $idUsuario, $idCliente, $formaPago, $pagoCon, $idCaja]);
        $idVenta = (int) $bd->lastInsertId();
        registrarProductosVenta($productos, $idVenta, $bd);
        $bd->commit();
    } catch(Throwable $e) {
        $bd->rollBack();
        throw $e;
    }
    return $idVenta;
}

function registrarProductosVenta($productos, $idVenta, $bd){
    $linea = $bd->prepare("INSERT INTO productos_ventas (cantidad, precio, idProducto, idVenta) VALUES (?,?,?,?)");
    $descontar = $bd->prepare("UPDATE productos SET existencia = existencia - ? WHERE id = ?");
    foreach ($productos as $producto) {
        $linea->execute([$producto->cantidad, $producto->venta, $producto->id, $idVenta]);
        $descontar->execute([$producto->cantidad, $producto->id]);
    }
    return true;
}

function obtenerUltimoIdVenta(){
    $sentencia = "SELECT id FROM ventas ORDER BY id DESC LIMIT 1";
    return select($sentencia)[0]->id;
}

function obtenerVentaPorId($idVenta) {
    $sentencia = "SELECT v.*, u.usuario, IFNULL(c.nombre, 'MOSTRADOR') AS cliente, anulo.usuario AS usuarioAnulacion
                  FROM ventas v
                  INNER JOIN usuarios u ON u.id = v.idUsuario
                  LEFT JOIN clientes c ON c.id = v.idCliente
                  LEFT JOIN usuarios anulo ON anulo.id = v.idUsuarioAnulacion
                  WHERE v.id = ?";
    $venta = select($sentencia, [$idVenta]);
    
    if($venta) {
        $venta[0]->productos = obtenerProductosVendidos($idVenta);
        return $venta[0];
    }
    
    return null;
}

// Anula una venta: no se borra, queda marcada con quién, cuándo y por qué, deja de contar en los totales
// y sus cantidades vuelven al inventario (por id de producto, aunque se haya renombrado), todo en una transacción.
// Se anota la caja abierta en ese momento: el dinero devuelto sale de ella. Devuelve false si no existe o ya estaba anulada.
function anularVenta($idVenta, $motivo, $idUsuario){
    $venta = obtenerVentaPorId($idVenta);
    if(!$venta || $venta->anulada) return false;
    $caja = obtenerCajaAbierta();

    $bd = conectarBaseDatos();
    $bd->beginTransaction();
    try {
        $marcar = $bd->prepare("UPDATE ventas SET anulada = 1, fechaAnulacion = ?, idUsuarioAnulacion = ?, motivoAnulacion = ?, idCajaAnulacion = ?
            WHERE id = ? AND anulada = 0");
        $marcar->execute([date("Y-m-d H:i:s"), $idUsuario, $motivo, $caja->id ?? null, $venta->id]);
        if($marcar->rowCount() !== 1){
            $bd->rollBack();
            return false;
        }
        $devolver = $bd->prepare("UPDATE productos SET existencia = existencia + ? WHERE id = ?");
        foreach($venta->productos as $productoVendido) {
            $devolver->execute([$productoVendido->cantidad, $productoVendido->idProducto]);
        }
        $bd->commit();
    } catch(Throwable $e) {
        $bd->rollBack();
        throw $e;
    }
    return true;
}
