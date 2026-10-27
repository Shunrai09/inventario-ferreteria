<?php
// Carrito de venta y lista de compra (se guardan en la sesión).

function calcularTotalLista($lista){
    $total = 0;
    foreach($lista as $producto){
        $total += floatval($producto->venta * $producto->cantidad);
    }
    return $total;
}

function calcularTotalListaCompra($lista){
    $total = 0;
    foreach($lista as $producto){
        $total += floatval($producto->compra * $producto->cantidad);
    }
    return $total;
}

// Entra con 1 (o con lo que quede, si hay menos de 1 metro, kilo, etc.) y nunca supera la existencia.
function agregarProductoALista($producto, $listaProductos){
    if($producto->existencia <= 0) return $listaProductos;
    $producto->cantidad = min(1, (float) $producto->existencia);

    $existe = verificarSiEstaEnLista($producto->id, $listaProductos);

    if(!$existe){
        array_push($listaProductos, $producto);
    } else{
        $existenciaAlcanzada = verificarExistencia($producto->id, $listaProductos, $producto->existencia);

        if($existenciaAlcanzada) return $listaProductos;

        $listaProductos = agregarCantidad($producto->id, $listaProductos, $producto->existencia);
    }
        
    return $listaProductos;
}

function agregarProductoAListaCompra($producto, $listaProductos){
    $producto->cantidad = 1;
    
    $existe = verificarSiEstaEnLista($producto->id, $listaProductos);

    if(!$existe){
        array_push($listaProductos, $producto);
    } else{
        $listaProductos = agregarCantidad($producto->id, $listaProductos);
    }
        
    return $listaProductos;
}

function verificarExistencia($idProducto, $listaProductos, $existencia){
    foreach($listaProductos as $producto){
        if($producto->id == $idProducto){
           if($existencia <= $producto->cantidad) return true; 
        }
    }
    return false;
}

function verificarSiEstaEnLista($idProducto, $listaProductos){
    foreach($listaProductos as $producto){
        if($producto->id == $idProducto){
            return true;
        }
    }
    return false;
}

// Suma 1 a la cantidad; con $maximo no pasa de ese valor (la existencia, en ventas).
function agregarCantidad($idProducto, $listaProductos, $maximo = null){
    foreach($listaProductos as $producto){
        if($producto->id == $idProducto){
            $producto->cantidad = round($producto->cantidad + 1, 3);
            if($maximo !== null) $producto->cantidad = min($producto->cantidad, (float) $maximo);
        }
    }
    return $listaProductos;
}
