<?php
// Descarga el inventario en Excel. Usa los mismos filtros que el listado de productos:
// ?busqueda=texto (nombre o código) y ?categoria=ID o 'ninguna'. Sin filtros exporta todo.
require_once __DIR__ . "/../../app/bootstrap.php";
requerirAdministrador();

$busqueda = ($_GET['busqueda'] ?? '') !== '' ? $_GET['busqueda'] : null;
$idCategoria = ($_GET['categoria'] ?? '') !== '' ? $_GET['categoria'] : null;
$productos = obtenerProductos($busqueda, $idCategoria);

$columnas = [
    ['titulo' => 'Código', 'ancho' => 14, 'tipo' => 'texto'],
    ['titulo' => 'Producto', 'ancho' => 38, 'tipo' => 'texto'],
    ['titulo' => 'Categoría', 'ancho' => 22, 'tipo' => 'texto'],
];
if(hayVariasTiendas()){
    $columnas[] = ['titulo' => 'Tienda', 'ancho' => 16, 'tipo' => 'texto'];
}
$columnas = array_merge($columnas, [
    ['titulo' => 'Unidad', 'ancho' => 9, 'tipo' => 'texto'],
    ['titulo' => 'Existencia', 'ancho' => 12, 'tipo' => 'numero'],
    ['titulo' => 'Costo unitario (' . MONEDA . ')', 'ancho' => 17, 'tipo' => 'dinero'],
    ['titulo' => 'Precio venta (' . MONEDA . ')', 'ancho' => 17, 'tipo' => 'dinero'],
    ['titulo' => 'Ganancia unitaria (' . MONEDA . ')', 'ancho' => 20, 'tipo' => 'dinero'],
    ['titulo' => 'Valor al costo (' . MONEDA . ')', 'ancho' => 18, 'tipo' => 'dinero'],
    ['titulo' => 'Valor a precio de venta (' . MONEDA . ')', 'ancho' => 26, 'tipo' => 'dinero'],
    ['titulo' => 'Ganancia potencial (' . MONEDA . ')', 'ancho' => 22, 'tipo' => 'dinero'],
]);

$filas = [];
foreach($productos as $producto){
    $existencia = (float) $producto->existencia;
    $fila = [$producto->codigo, $producto->nombre, $producto->categoria ?? 'Sin categoría'];
    if(hayVariasTiendas()){
        $fila[] = nombreTienda($producto->clasificacion);
    }
    $filas[] = array_merge($fila, [
        $producto->unidad,
        $existencia,
        (float) $producto->compra,
        (float) $producto->venta,
        $producto->venta - $producto->compra,
        $existencia * $producto->compra,
        $existencia * $producto->venta,
        $existencia * ($producto->venta - $producto->compra),
    ]);
}

// Fila de totales: solo los valores del inventario (las existencias no se suman porque mezclan unidades)
$totales = array_fill(0, count($columnas), null);
$totales[0] = 'TOTAL (' . count($filas) . ' productos)';
// Se suman las tres últimas columnas: valor al costo, valor a precio de venta y ganancia potencial
foreach(array_slice(array_keys($columnas), -3) as $indice){
    $totales[$indice] = true;
}

// Nombre del archivo: inventario[_categoria]_AAAA-MM-DD.xlsx
$sufijo = '';
if($idCategoria === 'ninguna'){
    $sufijo = '_sin-categoria';
} elseif($idCategoria && ($categoria = obtenerCategoriaPorId($idCategoria))){
    $sinTildes = strtr(mb_strtolower($categoria->nombre), ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
    $sufijo = '_' . trim(preg_replace('/[^a-z0-9]+/', '-', $sinTildes), '-');
}
enviarExcel('inventario' . $sufijo . '_' . date('Y-m-d') . '.xlsx', generarExcel('Inventario', $columnas, $filas, $totales));
