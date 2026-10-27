<?php
// Ajustes de inventario: cambios de existencia que no vienen de una compra ni de una venta.
// Cada ajuste guarda la existencia anterior y la nueva, el motivo y el costo del producto para valorizarlo.

// tipo: 'fijar' (se escribe la cantidad contada), 'quitar' o 'agregar' (se escribe la diferencia).
const MOTIVOS_AJUSTE = [
    'conteo'      => ['nombre' => 'Conteo físico', 'tipo' => 'fijar'],
    'merma'       => ['nombre' => 'Merma o producto dañado', 'tipo' => 'quitar'],
    'perdida'     => ['nombre' => 'Pérdida o robo', 'tipo' => 'quitar'],
    'uso_interno' => ['nombre' => 'Uso interno', 'tipo' => 'quitar'],
    'devolucion'  => ['nombre' => 'Devolución al proveedor', 'tipo' => 'quitar'],
    'ingreso'     => ['nombre' => 'Ingreso sin compra (sobrante, obsequio)', 'tipo' => 'agregar'],
];

// Los cambios de existencia hechos al importar un Excel también quedan como ajuste
const MOTIVO_IMPORTACION = 'importacion';

function nombreMotivoAjuste($motivo){
    if($motivo === MOTIVO_IMPORTACION) return 'Importación de Excel';
    return MOTIVOS_AJUSTE[$motivo]['nombre'] ?? (string) $motivo;
}

// Aplica el ajuste y lo registra en una transacción. $cantidad es la cantidad contada (motivo 'fijar')
// o la que se quita o agrega. Devuelve ['anterior' => x, 'nueva' => y], o un texto con el error.
function registrarAjuste($idProducto, $motivo, $cantidad, $notas, $idUsuario){
    if(!isset(MOTIVOS_AJUSTE[$motivo])) return 'El motivo elegido no es válido.';
    $tipo = MOTIVOS_AJUSTE[$motivo]['tipo'];

    $bd = conectarBaseDatos();
    $bd->beginTransaction();
    try {
        $consulta = $bd->prepare("SELECT existencia, compra, unidad FROM productos WHERE id = ?");
        $consulta->execute([$idProducto]);
        $producto = $consulta->fetch(PDO::FETCH_OBJ);
        if(!$producto){
            $bd->rollBack();
            return 'El producto no existe.';
        }
        $anterior = round((float) $producto->existencia, 3);
        $nueva = match($tipo) {
            'fijar'   => round($cantidad, 3),
            'quitar'  => round($anterior - $cantidad, 3),
            'agregar' => round($anterior + $cantidad, 3),
        };
        if($nueva < 0){
            $bd->rollBack();
            return 'No se puede quitar más de lo que hay: la existencia es ' . cantidadConUnidad($anterior, $producto->unidad) . '.';
        }
        if($nueva == $anterior){
            $bd->rollBack();
            return 'La existencia ya es ' . cantidadConUnidad($anterior, $producto->unidad) . ': no hay nada que ajustar.';
        }
        $bd->prepare("UPDATE productos SET existencia = ? WHERE id = ?")->execute([$nueva, $idProducto]);
        $bd->prepare("INSERT INTO ajustes_inventario (fecha, idProducto, idUsuario, motivo, cantidadAnterior, cantidadNueva, costo, notas) VALUES (?,?,?,?,?,?,?,?)")
           ->execute([date("Y-m-d H:i:s"), $idProducto, $idUsuario, $motivo, $anterior, $nueva, $producto->compra, $notas]);
        $bd->commit();
    } catch(Throwable $e) {
        $bd->rollBack();
        throw $e;
    }
    return ['anterior' => $anterior, 'nueva' => $nueva];
}

// Los filtros se combinan; las fechas son obligatorias. $busqueda: código o nombre del producto.
function obtenerAjustes($fechaInicio, $fechaFin, $motivo = null, $busqueda = null){
    $parametros = [$fechaInicio, $fechaFin];
    $sentencia = "SELECT ajustes_inventario.*, productos.codigo, productos.nombre, productos.unidad, usuarios.usuario
    FROM ajustes_inventario
    INNER JOIN productos ON productos.id = ajustes_inventario.idProducto
    INNER JOIN usuarios ON usuarios.id = ajustes_inventario.idUsuario
    WHERE DATE(ajustes_inventario.fecha) >= ? AND DATE(ajustes_inventario.fecha) <= ?";
    if(!empty($motivo)){
        $sentencia .= " AND ajustes_inventario.motivo = ?";
        $parametros[] = $motivo;
    }
    if(!empty($busqueda)){
        $sentencia .= " AND (productos.nombre LIKE ? OR productos.codigo LIKE ?)";
        array_push($parametros, "%$busqueda%", "%$busqueda%");
    }
    $sentencia .= " ORDER BY ajustes_inventario.fecha DESC, ajustes_inventario.id DESC";
    return select($sentencia, $parametros);
}

function obtenerAjustesDeProducto($idProducto){
    $sentencia = "SELECT ajustes_inventario.*, usuarios.usuario
    FROM ajustes_inventario
    INNER JOIN usuarios ON usuarios.id = ajustes_inventario.idUsuario
    WHERE ajustes_inventario.idProducto = ?
    ORDER BY ajustes_inventario.fecha DESC, ajustes_inventario.id DESC";
    return select($sentencia, [$idProducto]);
}

// Diferencia de un ajuste (negativa si se quitó) y su valor al costo.
function diferenciaAjuste($ajuste){
    return round($ajuste->cantidadNueva - $ajuste->cantidadAnterior, 3);
}

function valorAjuste($ajuste){
    return round(diferenciaAjuste($ajuste) * $ajuste->costo, 2);
}
