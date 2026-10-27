<?php
// Importación del inventario desde Excel (.xlsx) o CSV. Lo usa productos/importar.php en dos pasos:
// leerArchivoInventario() + prepararImportacion() para la vista previa, y aplicarImportacion() al confirmar.
// Las columnas se reconocen por su encabezado, así que sirve el mismo archivo que genera productos/exportar.php.

const IMPORTACION_MAXIMO_FILAS = 5000;

// Encabezados aceptados por campo (se comparan en minúsculas, sin tildes y sin lo que va entre paréntesis).
const ENCABEZADOS_IMPORTACION = [
    'codigo'     => ['codigo', 'cod', 'codigo de barras', 'sku'],
    'nombre'     => ['producto', 'nombre', 'descripcion', 'nombre del producto'],
    'categoria'  => ['categoria'],
    'tienda'     => ['tienda', 'clasificacion', 'sucursal'],
    'unidad'     => ['unidad', 'unidad de medida', 'um'],
    'existencia' => ['existencia', 'existencias', 'stock'],
    'compra'     => ['costo unitario', 'costo', 'precio compra', 'precio de compra', 'compra'],
    'venta'      => ['precio venta', 'precio de venta', 'venta', 'precio'],
];

// ==================== LECTURA DEL ARCHIVO ====================

// Devuelve las filas del archivo subido como [número de fila => [celdas]], o lanza RuntimeException con un mensaje para el usuario.
function leerArchivoInventario($archivo){
    $error = $archivo['error'] ?? UPLOAD_ERR_NO_FILE;
    if($error === UPLOAD_ERR_NO_FILE){
        throw new RuntimeException('Elige un archivo .xlsx o .csv.');
    }
    if($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE){
        throw new RuntimeException('El archivo es demasiado grande (máximo ' . ini_get('upload_max_filesize') . ').');
    }
    if($error !== UPLOAD_ERR_OK || !is_uploaded_file($archivo['tmp_name'])){
        throw new RuntimeException('No se pudo subir el archivo. Intenta nuevamente.');
    }

    $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    if($extension === 'xls'){
        throw new RuntimeException('Los archivos .xls (Excel 97-2003) no se pueden leer. En Excel usa Archivo > Guardar como > "Libro de Excel (.xlsx)".');
    }
    if($extension === 'xlsx'){
        $filas = leerXlsx($archivo['tmp_name']);
    } elseif($extension === 'csv' || $extension === 'txt'){
        $filas = leerCsv($archivo['tmp_name']);
    } else {
        throw new RuntimeException('Formato no admitido: usa un archivo de Excel (.xlsx) o CSV.');
    }

    if(count($filas) > IMPORTACION_MAXIMO_FILAS + 1){
        throw new RuntimeException('El archivo tiene más de ' . IMPORTACION_MAXIMO_FILAS . ' filas. Divídelo en varios archivos.');
    }
    return $filas;
}

// Primera hoja de un .xlsx. Lee el ZIP sin la extensión zip (solo con zlib).
function leerXlsx($ruta){
    $partes = leerEntradasZip($ruta, ['xl/workbook.xml', 'xl/_rels/workbook.xml.rels', 'xl/sharedStrings.xml']);
    if(!isset($partes['xl/workbook.xml'])){
        throw new RuntimeException('El archivo no es un libro de Excel (.xlsx) válido.');
    }

    // Ruta de la primera hoja según el libro y sus relaciones (casi siempre xl/worksheets/sheet1.xml)
    $rutaHoja = 'xl/worksheets/sheet1.xml';
    $libro = xmlExcel($partes['xl/workbook.xml']);
    $relaciones = isset($partes['xl/_rels/workbook.xml.rels']) ? xmlExcel($partes['xl/_rels/workbook.xml.rels']) : null;
    if(isset($libro->sheets->sheet[0]) && $relaciones){
        $idHoja = (string) $libro->sheets->sheet[0]->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
        foreach($relaciones->Relationship as $relacion){
            if((string) $relacion['Id'] === $idHoja){
                $destino = (string) $relacion['Target'];
                $rutaHoja = str_starts_with($destino, '/') ? ltrim($destino, '/') : 'xl/' . $destino;
            }
        }
    }

    $compartidos = [];
    if(isset($partes['xl/sharedStrings.xml'])){
        foreach(xmlExcel($partes['xl/sharedStrings.xml'])->si as $texto){
            $compartidos[] = textoCeldaExcel($texto);
        }
    }

    $hoja = leerEntradasZip($ruta, [$rutaHoja])[$rutaHoja] ?? null;
    if($hoja === null){
        throw new RuntimeException('No se encontró la primera hoja del libro.');
    }

    $filas = [];
    $siguienteFila = 1;
    foreach(xmlExcel($hoja)->sheetData->row as $fila){
        $numero = isset($fila['r']) ? (int) $fila['r'] : $siguienteFila;
        $siguienteFila = $numero + 1;
        $celdas = [];
        $siguienteColumna = 0;
        foreach($fila->c as $celda){
            $columna = isset($celda['r']) ? indiceColumnaExcel((string) $celda['r']) : $siguienteColumna;
            $siguienteColumna = $columna + 1;
            $tipo = (string) $celda['t'];
            if($tipo === 's'){
                $valor = $compartidos[(int) $celda->v] ?? '';
            } elseif($tipo === 'inlineStr'){
                $valor = textoCeldaExcel($celda->is);
            } else {
                $valor = (string) $celda->v;
            }
            $celdas[$columna] = $valor;
        }
        if($celdas){
            $filas[$numero] = array_replace(array_fill(0, max(array_keys($celdas)) + 1, ''), $celdas);
        }
    }
    return $filas;
}

// Texto de una celda (<t>) o de texto enriquecido (varios <r><t>), sin la guía fonética.
function textoCeldaExcel($nodo){
    if(isset($nodo->t)){
        return (string) $nodo->t;
    }
    $texto = '';
    foreach($nodo->r as $tramo){
        $texto .= (string) $tramo->t;
    }
    return $texto;
}

// "B12" -> 1, "AA3" -> 26
function indiceColumnaExcel($referencia){
    $indice = 0;
    foreach(str_split(preg_replace('/[^A-Z]/', '', strtoupper($referencia))) as $letra){
        $indice = $indice * 26 + (ord($letra) - 64);
    }
    return $indice - 1;
}

function xmlExcel($contenido){
    $xml = simplexml_load_string($contenido, 'SimpleXMLElement', LIBXML_NONET | LIBXML_COMPACT);
    if($xml === false){
        throw new RuntimeException('El archivo de Excel está dañado.');
    }
    return $xml;
}

// Contenido de las entradas pedidas de un ZIP: [nombre => contenido]. Solo admite entradas guardadas o comprimidas con deflate.
function leerEntradasZip($ruta, $nombres){
    $datos = file_get_contents($ruta);
    $fin = $datos === false ? false : strrpos($datos, "PK\x05\x06");
    if($fin === false){
        throw new RuntimeException('El archivo no es un libro de Excel (.xlsx) válido.');
    }
    $directorio = unpack('ventradas/Vtamano/Vinicio', substr($datos, $fin + 10, 10));
    $posicion = $directorio['inicio'];
    $contenidos = [];
    for($i = 0; $i < $directorio['entradas']; $i++){
        if(substr($datos, $posicion, 4) !== "PK\x01\x02"){
            throw new RuntimeException('El archivo de Excel está dañado.');
        }
        $entrada = unpack('vmetodo/x8/Vcomprimido/Vreal/vlargoNombre/vlargoExtra/vlargoComentario/x8/Vlocal', substr($datos, $posicion + 10, 36));
        $nombre = substr($datos, $posicion + 46, $entrada['largoNombre']);
        $posicion += 46 + $entrada['largoNombre'] + $entrada['largoExtra'] + $entrada['largoComentario'];
        if(!in_array($nombre, $nombres, true)){
            continue;
        }
        if($entrada['real'] > 50 * 1024 * 1024){
            throw new RuntimeException('El archivo es demasiado grande.');
        }
        $local = unpack('vlargoNombre/vlargoExtra', substr($datos, $entrada['local'] + 26, 4));
        $empaquetado = substr($datos, $entrada['local'] + 30 + $local['largoNombre'] + $local['largoExtra'], $entrada['comprimido']);
        $contenido = match($entrada['metodo']){
            0 => $empaquetado,
            8 => @gzinflate($empaquetado, 50 * 1024 * 1024),
            default => false,
        };
        if($contenido === false){
            throw new RuntimeException('No se pudo leer el archivo de Excel (compresión no admitida).');
        }
        $contenidos[$nombre] = $contenido;
    }
    return $contenidos;
}

// CSV separado por comas, punto y coma o tabulaciones, en UTF-8 o en la codificación de Excel en Windows.
function leerCsv($ruta){
    $texto = (string) file_get_contents($ruta);
    if(str_starts_with($texto, "\xEF\xBB\xBF")){
        $texto = substr($texto, 3);
    }
    if(!mb_check_encoding($texto, 'UTF-8')){
        $texto = mb_convert_encoding($texto, 'UTF-8', 'Windows-1252');
    }
    $primeraLinea = strtok($texto, "\n");
    $conteos = [';' => substr_count($primeraLinea, ';'), ',' => substr_count($primeraLinea, ','), "\t" => substr_count($primeraLinea, "\t")];
    $separador = array_search(max($conteos), $conteos);

    $flujo = fopen('php://memory', 'r+');
    fwrite($flujo, $texto);
    rewind($flujo);
    $filas = [];
    $numero = 0;
    while(($fila = fgetcsv($flujo, 0, $separador, '"', '')) !== false){
        $numero++;
        if($fila !== [null]){
            $filas[$numero] = $fila;
        }
    }
    fclose($flujo);
    return $filas;
}

// ==================== VALIDACIÓN ====================

// minúsculas, sin tildes, sin lo que va entre paréntesis y con espacios simples: "Costo unitario (S/)" -> "costo unitario"
function normalizarTextoImportacion($texto){
    $texto = mb_strtolower(trim((string) $texto));
    $texto = strtr($texto, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
    $texto = preg_replace('/\(.*?\)/', '', $texto);
    return trim(preg_replace('/[^a-z0-9]+/', ' ', $texto));
}

// Número escrito como 1234.5, 1,234.50, 1.234,50, 12,5 o "S/ 25.00". null si no es un número.
function interpretarNumeroImportacion($texto){
    $texto = preg_replace('/[^\d.,\-eE+]/', '', trim((string) $texto));
    if($texto === '' || $texto === '-'){
        return null;
    }
    if(preg_match('/^-?\d+(\.\d+)?[eE][+\-]?\d+$/', $texto)){
        return (float) $texto;
    }
    $coma = strrpos($texto, ',');
    $punto = strrpos($texto, '.');
    if($coma !== false && $punto !== false){
        // El separador que aparece último es el decimal
        $texto = $coma > $punto ? str_replace(['.', ','], ['', '.'], $texto) : str_replace(',', '', $texto);
    } elseif($coma !== false){
        // 12,5 o 0,250 son decimales; 1,250 o 12,500,000 son miles
        $texto = (preg_match('/^-?\d{1,3}(,\d{3})+$/', $texto) && !preg_match('/^-?0,/', $texto)) ? str_replace(',', '', $texto) : str_replace(',', '.', $texto);
    }
    return is_numeric($texto) ? (float) $texto : null;
}

// Clave de unidad a partir de "m", "Metro", "metros", "kilos", "unidad"... null si no se reconoce.
function interpretarUnidadImportacion($texto){
    $buscado = normalizarTextoImportacion($texto);
    $alias = ['u' => 'und', 'un' => 'und', 'unid' => 'und', 'unidades' => 'und', 'kilo' => 'kg', 'kilos' => 'kg',
              'mt' => 'm', 'mts' => 'm', 'lt' => 'l', 'lts' => 'l', 'galones' => 'gal'];
    foreach(UNIDADES as $clave => $unidad){
        $nombre = normalizarTextoImportacion($unidad['nombre']);
        if(in_array($buscado, [normalizarTextoImportacion($clave), $nombre, $nombre . 's', $nombre . 'es'], true)){
            return $clave;
        }
    }
    $clave = $alias[$buscado] ?? null;
    return ($clave !== null && isset(UNIDADES[$clave])) ? $clave : null;
}

// Código tal como se guarda: sin espacios y sin el ".0" que a veces agrega Excel a los números.
function normalizarCodigoImportacion($texto){
    $codigo = trim((string) $texto);
    if(preg_match('/^\d+(\.\d+)?[eE]\+\d+$/', $codigo)){
        $codigo = number_format((float) $codigo, 0, '', '');
    }
    return preg_replace('/^(\d+)\.0+$/', '$1', $codigo);
}

// Revisa las filas leídas contra la base y devuelve lo que se haría con cada una, sin guardar nada:
// ['filas' => [...], 'categoriasNuevas' => [...], 'resumen' => [...]] o ['error' => 'mensaje'].
function prepararImportacion($filasArchivo){
    // Encabezado: la primera de las 10 primeras filas que tenga la columna Código
    $columnas = null;
    foreach(array_slice($filasArchivo, 0, 10, true) as $numero => $fila){
        $encontradas = [];
        foreach($fila as $indice => $titulo){
            $titulo = normalizarTextoImportacion($titulo);
            foreach(ENCABEZADOS_IMPORTACION as $campo => $alias){
                if(!isset($encontradas[$campo]) && in_array($titulo, $alias, true)){
                    $encontradas[$campo] = $indice;
                }
            }
        }
        if(isset($encontradas['codigo'])){
            $columnas = $encontradas;
            $filaEncabezado = $numero;
            break;
        }
    }
    if($columnas === null){
        return ['error' => 'No se encontró la columna "Código" en las primeras filas del archivo. Usa la plantilla o un inventario exportado.'];
    }

    $actuales = [];
    foreach(obtenerProductos() as $producto){
        $actuales[trim($producto->codigo)] = $producto;
    }
    $categorias = [];
    foreach(obtenerCategorias() as $categoria){
        $categorias[normalizarTextoImportacion($categoria->nombre)] = $categoria;
    }

    $resultado = [];
    $categoriasNuevas = [];
    $filaDeCodigo = [];
    $resumen = ['nuevo' => 0, 'actualizar' => 0, 'sin_cambios' => 0, 'error' => 0];

    foreach($filasArchivo as $numero => $fila){
        if($numero <= $filaEncabezado || implode('', array_map('trim', $fila)) === ''){
            continue;
        }
        $celda = fn($campo) => isset($columnas[$campo]) ? trim((string) ($fila[$columnas[$campo]] ?? '')) : '';
        $codigo = normalizarCodigoImportacion($celda('codigo'));
        // La fila de totales que agrega la exportación
        if(stripos($codigo, 'TOTAL') === 0){
            continue;
        }

        $actual = $actuales[$codigo] ?? null;
        $errores = [];
        $datos = [];   // solo los campos que trae el archivo

        if($codigo === ''){
            $errores[] = 'Falta el código.';
        } elseif(isset($filaDeCodigo[$codigo])){
            $errores[] = 'Código repetido (ya aparece en la fila ' . $filaDeCodigo[$codigo] . ').';
        } else {
            $filaDeCodigo[$codigo] = $numero;
        }

        if($celda('nombre') !== ''){
            $datos['nombre'] = mb_substr($celda('nombre'), 0, 255);
        } elseif(!$actual){
            $errores[] = 'Falta el nombre del producto.';
        }

        foreach(['venta' => 'precio de venta', 'compra' => 'costo'] as $campo => $descripcion){
            if($celda($campo) === '') continue;
            $numeroLeido = interpretarNumeroImportacion($celda($campo));
            if($numeroLeido === null || $numeroLeido < 0){
                $errores[] = 'El ' . $descripcion . ' "' . $celda($campo) . '" no es un número válido.';
            } else {
                $datos[$campo] = round($numeroLeido, 2);
            }
        }
        if(!$actual && empty($datos['venta'])){
            $errores[] = 'Falta el precio de venta (mayor que 0).';
        }

        if($celda('unidad') !== ''){
            $unidad = interpretarUnidadImportacion($celda('unidad'));
            if($unidad === null){
                $errores[] = 'Unidad desconocida: "' . $celda('unidad') . '" (usa ' . implode(', ', array_keys(UNIDADES)) . ').';
            } else {
                $datos['unidad'] = $unidad;
            }
        }
        $unidadFinal = $datos['unidad'] ?? ($actual->unidad ?? 'und');

        if($celda('existencia') !== ''){
            $existencia = interpretarNumeroImportacion($celda('existencia'));
            if($existencia === null || $existencia < 0){
                $errores[] = 'La existencia "' . $celda('existencia') . '" no es un número válido.';
            } elseif(!unidadConDecimales($unidadFinal) && floor($existencia) != $existencia){
                $errores[] = 'La unidad "' . $unidadFinal . '" no admite decimales en la existencia (' . $celda('existencia') . ').';
            } else {
                $datos['existencia'] = round($existencia, 3);
            }
        }

        if($celda('categoria') !== ''){
            $clave = normalizarTextoImportacion($celda('categoria'));
            if(in_array($clave, ['sin categoria', 'ninguna', ''], true)){
                $datos['idCategoria'] = null;
                $datos['categoria'] = null;
            } elseif(isset($categorias[$clave])){
                $datos['idCategoria'] = (int) $categorias[$clave]->id;
                $datos['categoria'] = $categorias[$clave]->nombre;
            } else {
                // Se crea al confirmar; se guarda con el nombre tal como viene en la primera fila que la usa
                $categoriasNuevas[$clave] ??= mb_substr($celda('categoria'), 0, 100);
                $datos['categoriaNueva'] = $clave;
                $datos['categoria'] = $categoriasNuevas[$clave];
            }
        }

        if(hayVariasTiendas() && $celda('tienda') !== ''){
            $tienda = null;
            foreach(TIENDAS as $clave => $datosTienda){
                if(in_array(normalizarTextoImportacion($celda('tienda')), [normalizarTextoImportacion($clave), normalizarTextoImportacion($datosTienda['nombre'])], true)){
                    $tienda = (string) $clave;
                }
            }
            if($tienda === null){
                $errores[] = 'Tienda desconocida: "' . $celda('tienda') . '".';
            } else {
                $datos['clasificacion'] = $tienda;
            }
        }

        // Qué cambia respecto de lo guardado (para mostrarlo antes de confirmar)
        $cambios = [];
        if($actual){
            $antes = [
                'nombre' => $actual->nombre, 'venta' => (float) $actual->venta, 'compra' => (float) $actual->compra,
                'unidad' => $actual->unidad, 'existencia' => (float) $actual->existencia, 'categoria' => $actual->categoria,
                'clasificacion' => (string) $actual->clasificacion,
            ];
            foreach($antes as $campo => $valorAntes){
                if(array_key_exists($campo, $datos) && $datos[$campo] != $valorAntes){
                    $cambios[$campo] = $valorAntes;
                }
            }
        }

        $estado = $errores ? 'error' : (!$actual ? 'nuevo' : ($cambios ? 'actualizar' : 'sin_cambios'));
        $resumen[$estado]++;
        $resultado[] = [
            'fila' => $numero, 'codigo' => $codigo, 'id' => $actual->id ?? null, 'estado' => $estado,
            'datos' => $datos, 'cambios' => $cambios, 'errores' => $errores,
            // Lo que queda después de importar, para la vista previa
            'final' => [
                'nombre' => $datos['nombre'] ?? ($actual->nombre ?? ''),
                'categoria' => array_key_exists('categoria', $datos) ? $datos['categoria'] : ($actual->categoria ?? null),
                'unidad' => $unidadFinal,
                'existencia' => $datos['existencia'] ?? (float) ($actual->existencia ?? 0),
                'compra' => $datos['compra'] ?? (float) ($actual->compra ?? 0),
                'venta' => $datos['venta'] ?? (float) ($actual->venta ?? 0),
            ],
        ];
    }

    // Solo se crean las categorías que usa alguna fila sin errores
    $usadas = [];
    foreach($resultado as $fila){
        if($fila['estado'] !== 'error' && isset($fila['datos']['categoriaNueva'])){
            $usadas[$fila['datos']['categoriaNueva']] = $categoriasNuevas[$fila['datos']['categoriaNueva']];
        }
    }

    return ['filas' => $resultado, 'categoriasNuevas' => $usadas, 'resumen' => $resumen];
}

// ==================== APLICACIÓN ====================

// Crea las categorías nuevas, inserta los productos nuevos y actualiza los existentes, todo en una transacción.
// Las filas con error o sin cambios se omiten. Cada cambio de existencia queda como ajuste de inventario
// (motivo 'importacion') a nombre de $idUsuario. Devuelve ['nuevos' => n, 'actualizados' => n, 'categorias' => n].
function aplicarImportacion($preparada, $idUsuario){
    $bd = conectarBaseDatos();
    $bd->beginTransaction();
    try {
        $idsCategorias = [];
        $crearCategoria = $bd->prepare("INSERT INTO categorias (nombre) VALUES (?)");
        foreach($preparada['categoriasNuevas'] as $clave => $nombre){
            $crearCategoria->execute([$nombre]);
            $idsCategorias[$clave] = (int) $bd->lastInsertId();
        }

        $insertar = $bd->prepare("INSERT INTO productos (codigo, nombre, compra, venta, existencia, unidad, clasificacion, idCategoria) VALUES (?,?,?,?,?,?,?,?)");
        $leerActual = $bd->prepare("SELECT existencia, compra FROM productos WHERE id = ?");
        $ajuste = $bd->prepare("INSERT INTO ajustes_inventario (fecha, idProducto, idUsuario, motivo, cantidadAnterior, cantidadNueva, costo, notas) VALUES (?,?,?,?,?,?,?,?)");
        $registrarAjuste = function($idProducto, $anterior, $nueva, $costo) use ($ajuste, $idUsuario){
            if(round($nueva, 3) != round($anterior, 3)){
                $ajuste->execute([date("Y-m-d H:i:s"), $idProducto, $idUsuario, MOTIVO_IMPORTACION, $anterior, $nueva, $costo, null]);
            }
        };
        $nuevos = 0;
        $actualizados = 0;
        foreach($preparada['filas'] as $fila){
            $datos = $fila['datos'];
            if(isset($datos['categoriaNueva'])){
                $datos['idCategoria'] = $idsCategorias[$datos['categoriaNueva']];
            }
            if($fila['estado'] === 'nuevo'){
                $insertar->execute([
                    $fila['codigo'], $datos['nombre'], $datos['compra'] ?? 0, $datos['venta'], $datos['existencia'] ?? 0,
                    $datos['unidad'] ?? 'und', $datos['clasificacion'] ?? tiendaPrincipal(), $datos['idCategoria'] ?? null,
                ]);
                $registrarAjuste((int) $bd->lastInsertId(), 0, $datos['existencia'] ?? 0, $datos['compra'] ?? 0);
                $nuevos++;
            } elseif($fila['estado'] === 'actualizar'){
                $campos = array_intersect_key($datos, array_flip(['nombre', 'compra', 'venta', 'existencia', 'unidad', 'clasificacion', 'idCategoria']));
                $asignaciones = implode(', ', array_map(fn($campo) => "$campo = ?", array_keys($campos)));
                $leerActual->execute([$fila['id']]);
                $actual = $leerActual->fetch(PDO::FETCH_OBJ);
                $bd->prepare("UPDATE productos SET $asignaciones WHERE id = ?")->execute([...array_values($campos), $fila['id']]);
                if(isset($campos['existencia'])){
                    $registrarAjuste($fila['id'], (float) $actual->existencia, $campos['existencia'], $campos['compra'] ?? $actual->compra);
                }
                $actualizados++;
            }
        }
        $bd->commit();
    } catch(Throwable $e) {
        $bd->rollBack();
        throw $e;
    }
    return ['nuevos' => $nuevos, 'actualizados' => $actualizados, 'categorias' => count($idsCategorias)];
}

// Columnas de la plantilla vacía (las mismas que reconoce la importación).
function columnasPlantillaImportacion(){
    $columnas = [
        ['titulo' => 'Código', 'ancho' => 14, 'tipo' => 'texto'],
        ['titulo' => 'Producto', 'ancho' => 38, 'tipo' => 'texto'],
        ['titulo' => 'Categoría', 'ancho' => 22, 'tipo' => 'texto'],
    ];
    if(hayVariasTiendas()){
        $columnas[] = ['titulo' => 'Tienda', 'ancho' => 16, 'tipo' => 'texto'];
    }
    return array_merge($columnas, [
        ['titulo' => 'Unidad', 'ancho' => 9, 'tipo' => 'texto'],
        ['titulo' => 'Existencia', 'ancho' => 12, 'tipo' => 'numero'],
        ['titulo' => 'Costo unitario (' . MONEDA . ')', 'ancho' => 17, 'tipo' => 'dinero'],
        ['titulo' => 'Precio venta (' . MONEDA . ')', 'ancho' => 17, 'tipo' => 'dinero'],
    ]);
}
