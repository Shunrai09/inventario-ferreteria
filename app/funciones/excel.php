<?php
// Exportación a Excel (.xlsx) sin librerías externas ni la extensión zip: arma el XML de una hoja y lo empaqueta
// en un ZIP propio (comprimido con zlib si está disponible). Lo usa productos/exportar.php.

// Genera el contenido binario de un .xlsx con una sola hoja.
// $columnas: [['titulo' => 'Código', 'ancho' => 14, 'tipo' => 'texto'|'numero'|'dinero'], ...]
// $filas: arreglos de valores en el mismo orden que $columnas.
// $totales: fila final en negrita; en cada columna 'dinero' o 'numero' poner true para sumarla,
//           un texto para escribirlo tal cual, o null para dejarla vacía. Las sumas ignoran las filas filtradas.
function generarExcel($nombreHoja, $columnas, $filas, $totales = null){
    $nombreHoja = mb_substr(preg_replace('/[\[\]:*?\/\\\\]/', '', $nombreHoja), 0, 31);
    $ultimaColumna = columnaExcel(count($columnas) - 1);
    $ultimaFilaDatos = count($filas) + 1;

    // Estilos (índices de cellXfs en estilosExcel()): 1 encabezado, 2 dinero, 3 dinero en negrita, 4 texto en negrita
    $xml = '<row r="1">';
    foreach($columnas as $i => $columna){
        $xml .= celdaExcel(columnaExcel($i) . '1', $columna['titulo'], 'texto', 1);
    }
    $xml .= '</row>';

    foreach($filas as $n => $fila){
        $numeroFila = $n + 2;
        $xml .= '<row r="' . $numeroFila . '">';
        foreach($columnas as $i => $columna){
            $estilo = $columna['tipo'] === 'dinero' ? 2 : 0;
            $xml .= celdaExcel(columnaExcel($i) . $numeroFila, $fila[$i] ?? null, $columna['tipo'], $estilo);
        }
        $xml .= '</row>';
    }

    if($totales !== null){
        $numeroFila = $ultimaFilaDatos + 1;
        $xml .= '<row r="' . $numeroFila . '">';
        foreach($columnas as $i => $columna){
            $letra = columnaExcel($i);
            $valor = $totales[$i] ?? null;
            if($valor === true){
                $suma = array_sum(array_map(fn($fila) => (float) ($fila[$i] ?? 0), $filas));
                $estilo = $columna['tipo'] === 'dinero' ? 3 : 4;
                $xml .= '<c r="' . $letra . $numeroFila . '" s="' . $estilo . '"><f>SUBTOTAL(9,' . $letra . '2:' . $letra . max(2, $ultimaFilaDatos) . ')</f>'
                      . '<v>' . numeroExcel($suma) . '</v></c>';
            } elseif($valor !== null){
                $xml .= celdaExcel($letra . $numeroFila, $valor, 'texto', 4);
            }
        }
        $xml .= '</row>';
    }

    $anchos = '';
    foreach($columnas as $i => $columna){
        $anchos .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . ($columna['ancho'] ?? 12) . '" customWidth="1"/>';
    }

    $rangoFiltro = 'A1:' . $ultimaColumna . max(1, $ultimaFilaDatos);
    $hoja = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
        . '<cols>' . $anchos . '</cols>'
        . '<sheetData>' . $xml . '</sheetData>'
        . '<autoFilter ref="' . $rangoFiltro . '"/>'
        . '</worksheet>';

    $libro = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="' . textoXml($nombreHoja) . '" sheetId="1" r:id="rId1"/></sheets>'
        . '<definedNames><definedName name="_xlnm._FilterDatabase" localSheetId="0" hidden="1">'
        . textoXml("'" . str_replace("'", "''", $nombreHoja) . "'!" . preg_replace_callback('/([A-Z]+)(\d+)/', fn($m) => '$' . $m[1] . '$' . $m[2], $rangoFiltro))
        . '</definedName></definedNames>'
        . '<calcPr calcId="0" fullCalcOnLoad="1"/>'
        . '</workbook>';

    return zipExcel([
        '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>',
        '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>',
        'xl/workbook.xml' => $libro,
        'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>',
        'xl/worksheets/sheet1.xml' => $hoja,
        'xl/styles.xml' => estilosExcel(),
    ]);
}

// Descarga el archivo generado con generarExcel().
function enviarExcel($nombreArchivo, $contenido){
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
    header('Content-Length: ' . strlen($contenido));
    header('Cache-Control: no-store');
    echo $contenido;
    exit;
}

// 0 -> A, 25 -> Z, 26 -> AA
function columnaExcel($indice){
    $letras = '';
    for($n = $indice + 1; $n > 0; $n = intdiv($n - 1, 26)){
        $letras = chr(65 + ($n - 1) % 26) . $letras;
    }
    return $letras;
}

function celdaExcel($referencia, $valor, $tipo, $estilo){
    $s = $estilo ? ' s="' . $estilo . '"' : '';
    if($valor === null || $valor === ''){
        return $estilo ? '<c r="' . $referencia . '"' . $s . '/>' : '';
    }
    // Los códigos y textos van como texto para no perder ceros a la izquierda (00123)
    if($tipo === 'texto' || !is_numeric($valor)){
        return '<c r="' . $referencia . '"' . $s . ' t="inlineStr"><is><t xml:space="preserve">' . textoXml($valor) . '</t></is></c>';
    }
    return '<c r="' . $referencia . '"' . $s . '><v>' . numeroExcel($valor) . '</v></c>';
}

function numeroExcel($valor){
    $numero = round((float) $valor, 6);
    return is_finite($numero) ? (string) $numero : '0';
}

function textoXml($texto){
    $texto = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', (string) $texto);
    return htmlspecialchars($texto, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function estilosExcel(){
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
        . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FFE2E8F0"/><bgColor indexed="64"/></patternFill></fill></fills>'
        . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="5">'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
        . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
        . '<xf numFmtId="4" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
        . '<xf numFmtId="4" fontId="1" fillId="0" borderId="0" xfId="0" applyNumberFormat="1" applyFont="1"/>'
        . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
        . '</cellXfs>'
        . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
        . '</styleSheet>';
}

// Empaqueta [ruta => contenido] en un ZIP (formato que usa .xlsx).
function zipExcel($archivos){
    $comprimir = function_exists('gzdeflate');
    $hora = getdate();
    $horaDos = ($hora['hours'] << 11) | ($hora['minutes'] << 5) | intdiv($hora['seconds'], 2);
    $fechaDos = (($hora['year'] - 1980) << 9) | ($hora['mon'] << 5) | $hora['mday'];

    $zip = '';
    $directorio = '';
    foreach($archivos as $ruta => $datos){
        $crc = crc32($datos);
        $empaquetado = $comprimir ? gzdeflate($datos, 6) : $datos;
        $metodo = $comprimir ? 8 : 0;
        // Versión necesaria, banderas, método, hora, fecha, CRC, tamaño comprimido, tamaño real, largo del nombre
        $comun = pack('vvvvvVVVv', 20, 0, $metodo, $horaDos, $fechaDos, $crc, strlen($empaquetado), strlen($datos), strlen($ruta));
        $directorio .= pack('Vv', 0x02014b50, 20) . $comun . pack('vvvvV', 0, 0, 0, 0, 0) . pack('V', strlen($zip)) . $ruta;
        $zip .= pack('V', 0x04034b50) . $comun . pack('v', 0) . $ruta . $empaquetado;
    }
    $cantidad = count($archivos);
    return $zip . $directorio . pack('VvvvvVVv', 0x06054b50, 0, 0, $cantidad, $cantidad, strlen($directorio), strlen($zip), 0);
}
