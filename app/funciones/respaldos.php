<?php
// Respaldos de la base de datos: archivos .sql.gz en la carpeta respaldos/ del sistema (y, si se configuró,
// una copia en otra carpeta). Son SQL de MySQL que también se puede importar con phpMyAdmin: las tablas de
// database/esquema.sql (CREATE TABLE IF NOT EXISTS) y, por cada tabla, DELETE + INSERT de todas sus filas,
// con una sentencia por línea. Al restaurar desde el sistema solo se leen las filas de las tablas conocidas
// y se cargan en una transacción: si algo falla, la base queda como estaba.

const RESPALDO_CABECERA = '-- Respaldo del Sistema de Inventario';
const RESPALDO_FILAS_POR_INSERT = 100;
const TIPOS_RESPALDO = [
    'auto'                => 'Automático',
    'manual'              => 'Manual',
    'cierre'              => 'Cierre de caja',
    'antes_de_restaurar'  => 'Antes de restaurar',
    'antes_de_actualizar' => 'Antes de actualizar',
];

function carpetaRespaldos(){
    return RAIZ . '/respaldos';
}

// Tablas del sistema, en el orden de database/esquema.sql.
function tablasDelSistema(){
    preg_match_all('/^CREATE TABLE IF NOT EXISTS (\w+)/m', file_get_contents(RAIZ . '/database/esquema.sql'), $coincidencias);
    return $coincidencias[1];
}

// respaldo_2026-10-27_183005_cierre.sql.gz -> ['fecha' => '2026-10-27 18:30:05', 'tipo' => 'cierre'], o null.
function leerNombreRespaldo($archivo){
    if(!preg_match('/^respaldo_(\d{4}-\d{2}-\d{2})_(\d{2})(\d{2})(\d{2})_([a-z_]+)\.sql\.gz$/', $archivo, $partes)) return null;
    return ['fecha' => "$partes[1] $partes[2]:$partes[3]:$partes[4]", 'tipo' => $partes[5]];
}

// Respaldos de una carpeta, del más reciente al más antiguo.
function listarRespaldos($carpeta = null){
    $carpeta = $carpeta ?? carpetaRespaldos();
    $respaldos = [];
    foreach(is_dir($carpeta) ? scandir($carpeta, SCANDIR_SORT_DESCENDING) : [] as $archivo){
        $datos = leerNombreRespaldo($archivo);
        if($datos){
            $respaldos[] = (object) ($datos + ['archivo' => $archivo, 'tamano' => filesize("$carpeta/$archivo")]);
        }
    }
    return $respaldos;
}

// Valor de una celda como literal de MySQL (mismo escape que mysqldump), siempre en una sola línea.
function valorSqlRespaldo($valor){
    if($valor === null) return 'NULL';
    return "'" . strtr((string) $valor, ["\\" => "\\\\", "\0" => "\\0", "\n" => "\\n", "\r" => "\\r", "'" => "\\'", "\x1a" => "\\Z"]) . "'";
}

// Escribe el respaldo y devuelve ['archivo' => nombre, 'aviso' => texto o null (si no se pudo copiar)].
// $tipo: clave de TIPOS_RESPALDO. Después borra los más antiguos según config 'respaldos.conservar'.
function crearRespaldo($tipo = 'manual'){
    set_time_limit(0);
    $carpeta = carpetaRespaldos();
    if(!is_dir($carpeta) && !mkdir($carpeta, 0775, true)){
        throw new RuntimeException("No se pudo crear la carpeta $carpeta.");
    }
    $archivo = 'respaldo_' . date('Y-m-d_His') . '_' . $tipo . '.sql.gz';
    $temporal = "$carpeta/$archivo.tmp";
    $gz = gzopen($temporal, 'wb6');
    if(!$gz){
        throw new RuntimeException("No se pudo escribir en la carpeta $carpeta.");
    }

    try {
        $esquema = trim(preg_replace('/^\s*--.*\R?/m', '', file_get_contents(RAIZ . '/database/esquema.sql')));
        gzwrite($gz, RESPALDO_CABECERA . "\n"
            . '-- Empresa: ' . EMPRESA_NOMBRE . "\n"
            . '-- Fecha: ' . date('d/m/Y H:i:s') . ' (' . (TIPOS_RESPALDO[$tipo] ?? $tipo) . ")\n"
            . "-- Para restaurarlo: menú del usuario > Respaldos, o phpMyAdmin > Importar con la base seleccionada.\n\n"
            . "SET NAMES utf8mb4;\n\n" . $esquema . "\n\nSTART TRANSACTION;\n");

        $bd = conectarBaseDatos();
        foreach(tablasDelSistema() as $tabla){
            gzwrite($gz, "\nDELETE FROM `$tabla`;\n");
            $consulta = $bd->prepare("SELECT * FROM `$tabla` WHERE id > ? ORDER BY id LIMIT 1000");
            $ultimoId = 0;
            do {
                $consulta->execute([$ultimoId]);
                $filas = $consulta->fetchAll(PDO::FETCH_ASSOC);
                foreach(array_chunk($filas, RESPALDO_FILAS_POR_INSERT) as $grupo){
                    $columnas = '`' . implode('`, `', array_keys($grupo[0])) . '`';
                    $valores = array_map(fn($fila) => '(' . implode(', ', array_map('valorSqlRespaldo', $fila)) . ')', $grupo);
                    gzwrite($gz, "INSERT INTO `$tabla` ($columnas) VALUES " . implode(', ', $valores) . ";\n");
                }
                if($filas){
                    $ultimoId = end($filas)['id'];
                }
            } while(count($filas) === 1000);
        }
        gzwrite($gz, "\nCOMMIT;\n");
    } catch(Throwable $e) {
        gzclose($gz);
        @unlink($temporal);
        throw $e;
    }
    gzclose($gz);
    // Se escribe con otro nombre y se renombra al final: nunca queda listado un respaldo a medias
    if(!rename($temporal, "$carpeta/$archivo")){
        @unlink($temporal);
        throw new RuntimeException("No se pudo guardar el respaldo en $carpeta.");
    }
    borrarRespaldosAntiguos($carpeta);

    $aviso = null;
    $copia = rtrim(trim(RESPALDOS['copia']), '\\/');
    if($copia !== ''){
        if(!is_dir($copia)){
            $aviso = "El respaldo se guardó, pero no se pudo copiar a $copia: la carpeta no está disponible (¿la USB está conectada?).";
        } elseif(!@copy("$carpeta/$archivo", "$copia/$archivo")){
            $aviso = "El respaldo se guardó, pero no se pudo copiar a $copia.";
        } else {
            borrarRespaldosAntiguos($copia);
        }
    }
    guardarErrorRespaldo($aviso);
    return ['archivo' => $archivo, 'aviso' => $aviso];
}

function borrarRespaldosAntiguos($carpeta){
    foreach(array_slice(listarRespaldos($carpeta), max(1, (int) RESPALDOS['conservar'])) as $respaldo){
        @unlink("$carpeta/$respaldo->archivo");
    }
}

// Respaldo automático: uno por día, al primer inicio de sesión. Un error no impide entrar: queda anotado
// y se muestra en el panel y en la página de respaldos.
function respaldarSiCorresponde(){
    foreach(listarRespaldos() as $respaldo){
        if(str_starts_with($respaldo->fecha, date('Y-m-d'))) return;
    }
    try {
        crearRespaldo('auto');
    } catch(Throwable $e) {
        error_log((string) $e);
        guardarErrorRespaldo('No se pudo hacer el respaldo automático: ' . $e->getMessage());
    }
}

// El último problema con los respaldos (null = ninguno) se guarda en respaldos/error.txt.
function guardarErrorRespaldo($mensaje){
    $ruta = carpetaRespaldos() . '/error.txt';
    if($mensaje === null){
        if(is_file($ruta)) @unlink($ruta);
        return;
    }
    if(is_dir(carpetaRespaldos()) || @mkdir(carpetaRespaldos(), 0775, true)){
        @file_put_contents($ruta, date('d/m/Y H:i') . ' · ' . $mensaje);
    }
}

function ultimoErrorRespaldo(){
    $ruta = carpetaRespaldos() . '/error.txt';
    return is_file($ruta) ? trim((string) file_get_contents($ruta)) : null;
}

// Separa una línea INSERT de un respaldo en [tabla, columnas, filas]. Los valores son NULL o textos entre comillas.
function leerInsertRespaldo($linea){
    if(!preg_match('/^INSERT INTO `(\w+)` \(([^)]*)\) VALUES (.*);\s*$/s', $linea, $partes)){
        throw new RuntimeException('El respaldo tiene una línea que no se puede leer.');
    }
    $columnas = array_map(fn($columna) => trim($columna, " `"), explode(',', $partes[2]));
    foreach($columnas as $columna){
        if(!preg_match('/^\w+$/', $columna)) throw new RuntimeException('El respaldo tiene una columna no válida.');
    }
    $desescapar = fn($texto) => preg_replace_callback('/\\\\(.)/s',
        fn($m) => ['0' => "\0", 'n' => "\n", 'r' => "\r", 't' => "\t", 'Z' => "\x1a", 'b' => "\x08"][$m[1]] ?? $m[1], $texto);

    preg_match_all("/\\(|\\)|NULL|'((?:[^'\\\\]|\\\\.)*)'/s", $partes[3], $piezas, PREG_SET_ORDER);
    $filas = [];
    $fila = null;
    foreach($piezas as $pieza){
        if($pieza[0] === '('){
            $fila = [];
        } elseif($pieza[0] === ')'){
            if(count($fila ?? []) !== count($columnas)) throw new RuntimeException('El respaldo tiene una fila incompleta.');
            $filas[] = $fila;
            $fila = null;
        } elseif($fila !== null){
            $fila[] = $pieza[0] === 'NULL' ? null : $desescapar($pieza[1]);
        }
    }
    return [$partes[1], $columnas, $filas];
}

// true si el archivo (.sql.gz o .sql) empieza con la cabecera de los respaldos de este sistema.
function esArchivoDeRespaldo($ruta){
    $gz = @gzopen($ruta, 'rb');
    if(!$gz) return false;
    $primeraLinea = (string) gzgets($gz);
    gzclose($gz);
    return str_starts_with($primeraLinea, RESPALDO_CABECERA);
}

// Reemplaza todos los datos por los del respaldo, en una transacción. Acepta .sql.gz o .sql.
// Devuelve cuántas filas se cargaron. Lanza RuntimeException si el archivo no es un respaldo del sistema.
function restaurarRespaldo($ruta){
    set_time_limit(0);
    if(!esArchivoDeRespaldo($ruta)){
        throw new RuntimeException('El archivo no es un respaldo de este sistema.');
    }
    $gz = gzopen($ruta, 'rb');
    gzgets($gz);

    $tablas = tablasDelSistema();
    $bd = conectarBaseDatos();
    $bd->beginTransaction();
    try {
        foreach($tablas as $tabla){
            $bd->exec("DELETE FROM `$tabla`");
        }
        $filasCargadas = 0;
        $insertar = [];
        while(($linea = gzgets($gz)) !== false){
            if(!str_starts_with($linea, 'INSERT INTO `')) continue;
            [$tabla, $columnas, $filas] = leerInsertRespaldo($linea);
            if(!in_array($tabla, $tablas, true)) continue;
            $clave = $tabla . ':' . implode(',', $columnas);
            $insertar[$clave] ??= $bd->prepare("INSERT INTO `$tabla` (`" . implode('`, `', $columnas) . "`) VALUES ("
                . implode(', ', array_fill(0, count($columnas), '?')) . ")");
            foreach($filas as $fila){
                $insertar[$clave]->execute($fila);
                $filasCargadas++;
            }
        }
        $bd->commit();
    } catch(Throwable $e) {
        $bd->rollBack();
        throw $e;
    } finally {
        gzclose($gz);
    }
    return $filasCargadas;
}

// "1.2 MB", "350 KB"
function tamanoLegible($bytes){
    if($bytes >= 1048576) return number_format($bytes / 1048576, 1) . ' MB';
    return max(1, round($bytes / 1024)) . ' KB';
}
