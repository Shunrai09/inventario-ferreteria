<?php
// Datos de la empresa que se editan desde el sistema (menú del usuario > Datos de la empresa): nombre, RUC,
// dirección, teléfono y el pie y ancho del ticket. Se guardan en la tabla configuracion (clave => valor),
// así viajan con los respaldos; los que no se guardaron toman su valor de config/config.php.

const CLAVES_EMPRESA = ['empresa.nombre', 'empresa.ruc', 'empresa.direccion', 'empresa.telefono', 'ticket.pie', 'ticket.ancho_mm'];

// Si la tabla todavía no existe (base de una versión anterior) o no hay conexión, devuelve [] y se usan los
// valores de config.php; el error de conexión, si lo hay, lo muestra después la propia página.
function leerDatosEmpresaGuardados(){
    try {
        $filas = select("SELECT clave, valor FROM configuracion");
    } catch(Throwable $e) {
        return [];
    }
    $datos = [];
    foreach($filas as $fila){
        if(in_array($fila->clave, CLAVES_EMPRESA, true)){
            $datos[$fila->clave] = (string) $fila->valor;
        }
    }
    return $datos;
}

// Guarda todos los datos en una transacción. $datos: clave => valor (solo las de CLAVES_EMPRESA).
function guardarDatosEmpresa($datos){
    $bd = conectarBaseDatos();
    $bd->beginTransaction();
    try {
        $borrar = $bd->prepare("DELETE FROM configuracion WHERE clave = ?");
        $insertar = $bd->prepare("INSERT INTO configuracion (clave, valor) VALUES (?, ?)");
        foreach(array_intersect_key($datos, array_flip(CLAVES_EMPRESA)) as $clave => $valor){
            $borrar->execute([$clave]);
            $insertar->execute([$clave, (string) $valor]);
        }
        $bd->commit();
    } catch(Throwable $e) {
        $bd->rollBack();
        throw $e;
    }
    return true;
}
