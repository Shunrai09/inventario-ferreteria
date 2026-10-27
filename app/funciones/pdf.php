<?php
// Exportación de reportes a PDF sin librerías externas: hoja A4 horizontal con encabezado, recuadros de resumen,
// tabla con el encabezado repetido en cada página y pie "Página X de N". Usa las fuentes estándar de PDF
// (Helvetica) con codificación Windows-1252, que incluye tildes, ñ, ¿ y ¡. Lo usan los reportes con ?formato=pdf.

const PDF_ANCHO = 842;   // A4 horizontal, en puntos (1/72 de pulgada)
const PDF_ALTO = 595;
const PDF_MARGEN = 36;
const PDF_ANCHO_UTIL = PDF_ANCHO - 2 * PDF_MARGEN;

// $documento = [
//     'titulo'   => 'Reporte de ventas',
//     'detalles' => ['Período: ...', ...],                     líneas bajo el título
//     'resumen'  => ['N° de ventas' => '3', 'Total' => 'S/ 120.00'],
//     'columnas' => [['titulo' => 'N°', 'ancho' => 40, 'derecha' => false], ...],   anchos en puntos (máximo 770 en total)
//     'filas'    => [[celdas...], ...],   una fila cuya primera celda no está vacía empieza un grupo (una venta o compra)
//     'total'    => ['TOTAL GENERAL', 'S/ 120.00'],
//     'vacio'    => 'Texto cuando no hay filas',
// ]
function generarPdfReporte($documento){
    $columnas = $documento['columnas'];
    $anchoTabla = min(PDF_ANCHO_UTIL, array_sum(array_column($columnas, 'ancho')));
    $alto = 15;
    $limiteInferior = PDF_MARGEN + 10;   // debajo va el pie de página
    $paginas = [];

    // Encabezado del documento (solo en la primera página)
    $y = PDF_ALTO - PDF_MARGEN;
    $c = pdfTexto(PDF_MARGEN, $y - 15, EMPRESA_NOMBRE, 15, true);
    $y -= 24;
    $c .= pdfTexto(PDF_MARGEN, $y - 12, $documento['titulo'], 12, true);
    $y -= 19;
    foreach($documento['detalles'] ?? [] as $linea){
        $c .= pdfTexto(PDF_MARGEN, $y - 9, $linea, 9, false, 0.3);
        $y -= 13;
    }

    if(!empty($documento['resumen'])){
        $y -= 6;
        $separacion = 10;
        $anchoCaja = ($anchoTabla - $separacion * (count($documento['resumen']) - 1)) / count($documento['resumen']);
        $x = PDF_MARGEN;
        foreach($documento['resumen'] as $etiqueta => $valor){
            $c .= pdfRectangulo($x, $y - 36, $anchoCaja, 36, [0.945, 0.960, 0.976]);
            $c .= pdfTexto($x + 8, $y - 13, $etiqueta, 8, false, 0.35);
            $c .= pdfTexto($x + 8, $y - 29, pdfRecortar($valor, $anchoCaja - 16, 12, true), 12, true);
            $x += $anchoCaja + $separacion;
        }
        $y -= 36 + 14;
    }

    $encabezadoTabla = function($y) use ($columnas, $anchoTabla, $alto){
        return pdfRectangulo(PDF_MARGEN, $y - $alto - 2, $anchoTabla, $alto + 2, [0.886, 0.910, 0.941])
             . pdfFila($columnas, array_column($columnas, 'titulo'), $y, $alto + 2, true);
    };
    $c .= $encabezadoTabla($y);
    $y -= $alto + 2;

    if(empty($documento['filas'])){
        $c .= pdfTexto(PDF_MARGEN + 3, $y - $alto - 2, $documento['vacio'] ?? 'Sin datos.', 9, false, 0.4);
        $y -= $alto + 6;
    }

    $filas = $documento['filas'];
    $filasPorPagina = (int) floor((PDF_ALTO - PDF_MARGEN - $alto - 2 - $limiteInferior) / $alto);
    foreach($filas as $i => $fila){
        // Una venta (o compra) no se parte entre páginas, salvo que por sí sola ocupe más de una
        $filasDelGrupo = 1;
        if(($fila[0] ?? '') !== ''){
            while(isset($filas[$i + $filasDelGrupo]) && ($filas[$i + $filasDelGrupo][0] ?? '') === ''){
                $filasDelGrupo++;
            }
        }
        if($y - min($filasDelGrupo, $filasPorPagina) * $alto < $limiteInferior){
            $paginas[] = $c;
            $y = PDF_ALTO - PDF_MARGEN;
            $c = $encabezadoTabla($y);
            $y -= $alto + 2;
        } elseif($i > 0 && ($fila[0] ?? '') !== ''){
            // Línea fina entre una venta (o compra) y la siguiente
            $c .= pdfLinea(PDF_MARGEN, $y, PDF_MARGEN + $anchoTabla, $y, 0.82, 0.5);
        }
        $c .= pdfFila($columnas, $fila, $y, $alto);
        $y -= $alto;
    }

    if(!empty($documento['total'])){
        if($y - 26 < $limiteInferior){
            $paginas[] = $c;
            $y = PDF_ALTO - PDF_MARGEN;
            $c = '';
        }
        [$etiqueta, $valor] = $documento['total'];
        $derecha = PDF_MARGEN + $anchoTabla - 3;
        $anchoValor = pdfAncho($valor, 11, true);
        $c .= pdfLinea(PDF_MARGEN, $y - 2, PDF_MARGEN + $anchoTabla, $y - 2, 0.25, 1);
        // La etiqueta va antes que el importe en el contenido, para que al copiar o buscar se lea en orden
        $c .= pdfTexto($derecha - $anchoValor - 18 - pdfAncho($etiqueta, 11, true), $y - 18, $etiqueta, 11, true);
        $c .= pdfTexto($derecha - $anchoValor, $y - 18, $valor, 11, true);
    }
    $paginas[] = $c;

    // Pie de página, ahora que se sabe cuántas páginas hay
    $pie = EMPRESA_NOMBRE . ' · ' . $documento['titulo'] . ' · Generado el ' . date('d/m/Y H:i');
    foreach($paginas as $n => $contenido){
        $numero = 'Página ' . ($n + 1) . ' de ' . count($paginas);
        $paginas[$n] = $contenido
            . pdfLinea(PDF_MARGEN, PDF_MARGEN, PDF_ANCHO - PDF_MARGEN, PDF_MARGEN, 0.75, 0.5)
            . pdfTexto(PDF_MARGEN, PDF_MARGEN - 12, $pie, 7.5, false, 0.4)
            . pdfTexto(PDF_ANCHO - PDF_MARGEN - pdfAncho($numero, 7.5), PDF_MARGEN - 12, $numero, 7.5, false, 0.4);
    }

    return armarPdf($paginas, $documento['titulo']);
}

// Descarga el archivo generado con generarPdfReporte().
function enviarPdf($nombreArchivo, $contenido){
    $nombreArchivo = preg_replace('/[^A-Za-z0-9._-]+/', '_', $nombreArchivo);
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
    header('Content-Length: ' . strlen($contenido));
    header('Cache-Control: no-store');
    echo $contenido;
    exit;
}

// Líneas que describen los filtros de un reporte, para el encabezado del PDF.
function describirFiltrosReporte($fechaInicio, $fechaFin, $usuario, $clasificacion, $categoria){
    $fecha = fn($valor) => date('d/m/Y', strtotime($valor));
    if($fechaInicio && $fechaFin){
        $periodo = $fecha($fechaInicio) . ' al ' . $fecha($fechaFin);
    } elseif($fechaInicio){
        $periodo = 'desde el ' . $fecha($fechaInicio);
    } elseif($fechaFin){
        $periodo = 'hasta el ' . $fecha($fechaFin);
    } else {
        $periodo = $usuario ? 'todas las fechas' : 'hoy, ' . date('d/m/Y');
    }

    $filtros = ['Período: ' . $periodo, 'Usuario: ' . ($usuario ? $usuario->nombre : 'todos')];
    if($categoria === 'ninguna'){
        $filtros[] = 'Categoría: sin categoría';
    } elseif($categoria){
        $filtros[] = 'Categoría: ' . (obtenerCategoriaPorId($categoria)->nombre ?? 'eliminada');
    }
    if($clasificacion && hayVariasTiendas()){
        $filtros[] = 'Tienda: ' . nombreTienda($clasificacion);
    }
    return [implode('     ·     ', $filtros)];
}

// ---------- Dibujo ----------

function pdfFila($columnas, $celdas, $y, $alto, $negrita = false){
    $tamano = 8.5;
    $x = PDF_MARGEN;
    $salida = '';
    foreach($columnas as $i => $columna){
        $texto = pdfRecortar((string) ($celdas[$i] ?? ''), $columna['ancho'] - 6, $tamano, $negrita);
        if($texto !== ''){
            $tx = !empty($columna['derecha']) ? $x + $columna['ancho'] - 3 - pdfAncho($texto, $tamano, $negrita) : $x + 3;
            $salida .= pdfTexto($tx, $y - $alto + 4.5, $texto, $tamano, $negrita);
        }
        $x += $columna['ancho'];
    }
    return $salida;
}

// $gris: 0 negro ... 1 blanco
function pdfTexto($x, $y, $texto, $tamano, $negrita = false, $gris = 0){
    return sprintf("BT %.3F g /%s %.2F Tf %.2F %.2F Td (%s) Tj ET\n", $gris, $negrita ? 'F2' : 'F1', $tamano, $x, $y, pdfCadena($texto));
}

function pdfRectangulo($x, $y, $ancho, $alto, $rgb){
    return sprintf("%.3F %.3F %.3F rg %.2F %.2F %.2F %.2F re f 0 g\n", $rgb[0], $rgb[1], $rgb[2], $x, $y, $ancho, $alto);
}

function pdfLinea($x1, $y1, $x2, $y2, $gris, $grosor){
    return sprintf("%.3F G %.2F w %.2F %.2F m %.2F %.2F l S 0 G\n", $gris, $grosor, $x1, $y1, $x2, $y2);
}

// Texto de PDF: Windows-1252 con \, ( y ) escapados.
function pdfCadena($texto){
    $texto = mb_convert_encoding((string) $texto, 'Windows-1252', 'UTF-8');
    return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', ' '], $texto);
}

// Ancho en puntos de un texto UTF-8 en Helvetica (o Helvetica-Bold) del tamaño dado.
function pdfAncho($texto, $tamano, $negrita = false){
    static $anchos = null;
    if($anchos === null){
        $anchos = pdfTablaAnchos();
    }
    $tabla = $anchos[$negrita ? 1 : 0];
    $total = 0;
    foreach(str_split(mb_convert_encoding((string) $texto, 'Windows-1252', 'UTF-8')) as $byte){
        $total += $tabla[ord($byte)] ?? 556;
    }
    return $total * $tamano / 1000;
}

// Acorta el texto con "…" hasta que entre en el ancho dado.
function pdfRecortar($texto, $ancho, $tamano, $negrita = false){
    if(pdfAncho($texto, $tamano, $negrita) <= $ancho) return $texto;
    while($texto !== '' && pdfAncho($texto . '…', $tamano, $negrita) > $ancho){
        $texto = mb_substr($texto, 0, -1);
    }
    return rtrim($texto) . '…';
}

// Anchos (milésimas del tamaño de letra) de Helvetica y Helvetica-Bold, de las métricas estándar de Adobe,
// para los caracteres 32 a 126 y los de uso común en español.
function pdfTablaAnchos(){
    $ascii = [
        '278 278 355 556 556 889 667 191 333 333 389 584 278 333 278 278 556 556 556 556 556 556 556 556 556 556 278 278 584 584 584 556 '
        . '1015 667 667 722 722 667 611 778 722 278 500 667 556 833 722 778 667 778 722 667 611 722 667 944 667 667 611 278 278 278 469 556 '
        . '333 556 556 500 556 556 278 556 556 222 222 500 222 833 556 556 556 556 333 500 278 556 500 722 500 500 500 334 260 334 584',
        '278 333 474 556 556 889 722 238 333 333 389 584 278 333 278 278 556 556 556 556 556 556 556 556 556 556 333 333 584 584 584 611 '
        . '975 722 722 722 722 667 611 778 722 278 556 722 611 833 722 778 667 778 722 667 611 722 667 944 667 667 611 333 278 333 584 556 '
        . '333 556 611 556 611 556 333 611 611 278 278 556 278 889 611 611 611 611 389 556 333 611 556 778 556 556 500 389 280 389 584',
    ];
    // … ¡ ª ° · º ¿ Á É Í Ñ Ó Ú Ü á é í ñ ó ú ü
    $especiales = [0x85, 0xA1, 0xAA, 0xB0, 0xB7, 0xBA, 0xBF, 0xC1, 0xC9, 0xCD, 0xD1, 0xD3, 0xDA, 0xDC, 0xE1, 0xE9, 0xED, 0xF1, 0xF3, 0xFA, 0xFC];
    $anchosEspeciales = [
        [1000, 333, 370, 400, 278, 365, 611, 667, 667, 278, 722, 778, 722, 722, 556, 556, 278, 556, 556, 556, 556],
        [1000, 333, 370, 400, 278, 365, 611, 722, 667, 278, 722, 778, 722, 722, 556, 556, 278, 611, 611, 611, 611],
    ];
    $tablas = [];
    foreach([0, 1] as $fuente){
        $tablas[$fuente] = array_combine(range(32, 126), array_map('intval', explode(' ', $ascii[$fuente])))
                         + array_combine($especiales, $anchosEspeciales[$fuente]);
    }
    return $tablas;
}

// Arma el archivo PDF: catálogo, páginas, las dos fuentes, información del documento y tabla de referencias.
function armarPdf($paginas, $titulo){
    $objetos = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
        5 => '<< /Title (' . pdfCadena(EMPRESA_NOMBRE . ' - ' . $titulo) . ') /Creator (' . pdfCadena(EMPRESA_SISTEMA) . ') /CreationDate (D:' . date('YmdHis') . ') >>',
    ];
    $hijos = [];
    $numero = 6;
    foreach($paginas as $contenido){
        $flujo = function_exists('gzcompress') ? gzcompress($contenido) : $contenido;
        $filtro = function_exists('gzcompress') ? ' /Filter /FlateDecode' : '';
        $objetos[$numero] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . PDF_ANCHO . ' ' . PDF_ALTO . '] '
            . '/Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . ($numero + 1) . ' 0 R >>';
        $objetos[$numero + 1] = '<< /Length ' . strlen($flujo) . $filtro . " >>\nstream\n" . $flujo . "\nendstream";
        $hijos[] = $numero . ' 0 R';
        $numero += 2;
    }
    $objetos[2] = '<< /Type /Pages /Kids [' . implode(' ', $hijos) . '] /Count ' . count($paginas) . ' >>';
    ksort($objetos);

    $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
    $posiciones = [];
    foreach($objetos as $n => $objeto){
        $posiciones[$n] = strlen($pdf);
        $pdf .= $n . " 0 obj\n" . $objeto . "\nendobj\n";
    }
    $inicioReferencias = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objetos) + 1) . "\n0000000000 65535 f \n";
    foreach($posiciones as $posicion){
        $pdf .= sprintf("%010d 00000 n \n", $posicion);
    }
    $pdf .= "trailer\n<< /Size " . (count($objetos) + 1) . " /Root 1 0 R /Info 5 0 R >>\nstartxref\n" . $inicioReferencias . "\n%%EOF\n";
    return $pdf;
}
