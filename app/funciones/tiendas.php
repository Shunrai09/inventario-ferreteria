<?php
// Estadísticas por tienda (columna productos.clasificacion).
// Los importes se suman por línea (cantidad * precio) y no con el total de la venta o compra:
// una venta puede tener varias líneas y productos de distintas tiendas. Las ventas anuladas no cuentan.

function obtenerTotalVentasPorClasificacion($clasificacion) {
    $sentencia = "SELECT IFNULL(SUM(pv.cantidad * pv.precio),0) AS total 
                  FROM ventas v
                  INNER JOIN productos_ventas pv ON pv.idVenta = v.id
                  INNER JOIN productos p ON p.id = pv.idProducto
                  WHERE p.clasificacion = ? AND v.anulada = 0";
    $resultado = select($sentencia, [$clasificacion]);
    return $resultado[0]->total;
}

function obtenerTotalVentasHoyPorClasificacion($clasificacion) {
    $sentencia = "SELECT IFNULL(SUM(pv.cantidad * pv.precio),0) AS total 
                  FROM ventas v
                  INNER JOIN productos_ventas pv ON pv.idVenta = v.id
                  INNER JOIN productos p ON p.id = pv.idProducto
                  WHERE p.clasificacion = ? AND v.anulada = 0 AND DATE(v.fecha) = CURDATE()";
    $resultado = select($sentencia, [$clasificacion]);
    return $resultado[0]->total;
}

function obtenerTotalVentasSemanaPorClasificacion($clasificacion) {
    $sentencia = "SELECT IFNULL(SUM(pv.cantidad * pv.precio),0) AS total 
                  FROM ventas v
                  INNER JOIN productos_ventas pv ON pv.idVenta = v.id
                  INNER JOIN productos p ON p.id = pv.idProducto
                  WHERE p.clasificacion = ? AND v.anulada = 0 AND YEARWEEK(v.fecha) = YEARWEEK(NOW())";
    $resultado = select($sentencia, [$clasificacion]);
    return $resultado[0]->total;
}

function obtenerTotalVentasMesPorClasificacion($clasificacion) {
    $sentencia = "SELECT IFNULL(SUM(pv.cantidad * pv.precio),0) AS total 
                  FROM ventas v
                  INNER JOIN productos_ventas pv ON pv.idVenta = v.id
                  INNER JOIN productos p ON p.id = pv.idProducto
                  WHERE p.clasificacion = ? AND v.anulada = 0 AND MONTH(v.fecha) = MONTH(CURRENT_DATE()) AND YEAR(v.fecha) = YEAR(CURRENT_DATE())";
    $resultado = select($sentencia, [$clasificacion]);
    return $resultado[0]->total;
}

function obtenerTotalComprasPorClasificacion($clasificacion) {
    $sentencia = "SELECT IFNULL(SUM(pc.cantidad * pc.precio),0) AS total 
                  FROM compras c
                  INNER JOIN productos_compras pc ON pc.idCompra = c.id
                  INNER JOIN productos p ON p.id = pc.idProducto
                  WHERE p.clasificacion = ?";
    $resultado = select($sentencia, [$clasificacion]);
    return $resultado[0]->total;
}

function obtenerNumeroProductosPorClasificacion($clasificacion) {
    $sentencia = "SELECT IFNULL(COUNT(*),0) AS total FROM productos WHERE clasificacion = ?";
    $resultado = select($sentencia, [$clasificacion]);
    return $resultado[0]->total;
}

function obtenerProductosMasVendidosConClasificacion() {
    $sentencia = "SELECT SUM(productos_ventas.cantidad * productos_ventas.precio) AS total, 
    SUM(productos_ventas.cantidad) AS unidades,
    productos.nombre,
    productos.unidad,
    productos.clasificacion
    FROM productos_ventas 
    INNER JOIN productos ON productos.id = productos_ventas.idProducto
    INNER JOIN ventas ON ventas.id = productos_ventas.idVenta
    WHERE ventas.anulada = 0
    GROUP BY productos_ventas.idProducto
    ORDER BY total DESC
    LIMIT 10";
    return select($sentencia);
}
