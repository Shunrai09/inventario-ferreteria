-- Esquema completo de la base de datos del sistema de inventario.
--
-- Uso: crear una base vacía con juego de caracteres utf8mb4 e importar este archivo con esa base seleccionada
-- (phpMyAdmin > Importar, o bien: mysql -u USUARIO -p NOMBRE_BD < database/esquema.sql).
-- No crea ni borra la base. Solo crea las tablas que falten, así que se puede repetir sin riesgo.
--
-- productos.clasificacion guarda la clave de la tienda definida en config/config.php ('tiendas')
-- y productos.unidad la clave de la unidad de venta ('unidades'). Las cantidades llevan 3 decimales
-- para vender por metro, kilo, etc.
--
-- Si la base se creó con una versión anterior de este archivo, ejecuta también, una sola vez y en orden,
-- los archivos de database/migraciones/ que no hayas aplicado.
--
-- usuarios.rol: 'administrador' (acceso completo) o 'vendedor' (punto de venta, consulta de productos y clientes).

CREATE TABLE IF NOT EXISTS usuarios (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    usuario VARCHAR(50) NOT NULL,
    nombre VARCHAR(255) NOT NULL,
    telefono VARCHAR(25) NOT NULL,
    direccion VARCHAR(255) NOT NULL,
    password VARCHAR(255) NOT NULL,
    rol VARCHAR(20) NOT NULL DEFAULT 'vendedor',
    KEY idx_usuarios_usuario (usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS clientes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    telefono VARCHAR(25) NOT NULL,
    direccion VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS categorias (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    UNIQUE KEY uq_categorias_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Categorías iniciales de una ferretería; se pueden cambiar desde el menú Categorías.
INSERT IGNORE INTO categorias (nombre) VALUES
    ('Herramientas'), ('Herramientas eléctricas'), ('Electricidad'), ('Gasfitería'), ('Pinturas'),
    ('Construcción'), ('Clavos y tornillos'), ('Cerrajería'), ('Jardinería'), ('Seguridad industrial');

CREATE TABLE IF NOT EXISTS productos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(255) NOT NULL,
    nombre VARCHAR(255) NOT NULL,
    compra DECIMAL(8,2) NOT NULL DEFAULT 0,
    venta DECIMAL(8,2) NOT NULL DEFAULT 0,
    existencia DECIMAL(12,3) NOT NULL DEFAULT 0,
    unidad VARCHAR(10) NOT NULL DEFAULT 'und',
    clasificacion VARCHAR(10) NOT NULL,
    idCategoria BIGINT UNSIGNED NULL,
    KEY idx_productos_codigo (codigo),
    KEY idx_productos_clasificacion (clasificacion),
    KEY idx_productos_categoria (idCategoria)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- formaPago: clave de config 'formas_pago'. pagoCon: efectivo que entregó el cliente (para el vuelto).
-- idCaja: caja abierta al vender. Una venta anulada no se borra: devuelve su stock y deja de contar en los totales;
-- idCajaAnulacion es la caja abierta al anularla (de ahí sale el dinero devuelto).
CREATE TABLE IF NOT EXISTS ventas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    fecha DATETIME NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    idUsuario BIGINT UNSIGNED NOT NULL,
    idCliente BIGINT UNSIGNED NULL,
    formaPago VARCHAR(20) NOT NULL DEFAULT 'efectivo',
    pagoCon DECIMAL(10,2) NULL,
    idCaja BIGINT UNSIGNED NULL,
    anulada TINYINT(1) NOT NULL DEFAULT 0,
    fechaAnulacion DATETIME NULL,
    idUsuarioAnulacion BIGINT UNSIGNED NULL,
    motivoAnulacion VARCHAR(255) NULL,
    idCajaAnulacion BIGINT UNSIGNED NULL,
    KEY idx_ventas_fecha (fecha),
    KEY idx_ventas_usuario (idUsuario),
    KEY idx_ventas_cliente (idCliente),
    KEY idx_ventas_caja (idCaja),
    KEY idx_ventas_caja_anulacion (idCajaAnulacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS productos_ventas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    cantidad DECIMAL(12,3) NOT NULL,
    precio DECIMAL(8,2) NOT NULL,
    idProducto BIGINT UNSIGNED NOT NULL,
    idVenta BIGINT UNSIGNED NOT NULL,
    KEY idx_pv_venta (idVenta),
    KEY idx_pv_producto (idProducto)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS compras (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    fecha DATETIME NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    idUsuario BIGINT UNSIGNED NOT NULL,
    notas TEXT NULL,
    KEY idx_compras_fecha (fecha),
    KEY idx_compras_usuario (idUsuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS productos_compras (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    cantidad DECIMAL(12,3) NOT NULL,
    precio DECIMAL(8,2) NOT NULL,
    idProducto BIGINT UNSIGNED NOT NULL,
    idCompra BIGINT UNSIGNED NOT NULL,
    KEY idx_pc_compra (idCompra),
    KEY idx_pc_producto (idProducto)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Caja: se abre con un monto inicial y al cerrarla se guarda el arqueo (efectivo esperado y contado).
-- Mientras fechaCierre es NULL la caja está abierta; solo puede haber una abierta.
CREATE TABLE IF NOT EXISTS cajas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    fechaApertura DATETIME NOT NULL,
    montoInicial DECIMAL(10,2) NOT NULL DEFAULT 0,
    idUsuarioApertura BIGINT UNSIGNED NOT NULL,
    fechaCierre DATETIME NULL,
    idUsuarioCierre BIGINT UNSIGNED NULL,
    totalVentas DECIMAL(10,2) NULL,
    efectivoEsperado DECIMAL(10,2) NULL,
    efectivoContado DECIMAL(10,2) NULL,
    notas TEXT NULL,
    KEY idx_cajas_apertura (fechaApertura),
    KEY idx_cajas_cierre (fechaCierre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Ajustes de inventario: cambios de existencia que no son compras ni ventas (conteo físico, merma, robo...).
-- costo: precio de compra del producto en ese momento, para valorizar la diferencia.
CREATE TABLE IF NOT EXISTS ajustes_inventario (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    fecha DATETIME NOT NULL,
    idProducto BIGINT UNSIGNED NOT NULL,
    idUsuario BIGINT UNSIGNED NOT NULL,
    motivo VARCHAR(20) NOT NULL,
    cantidadAnterior DECIMAL(12,3) NOT NULL,
    cantidadNueva DECIMAL(12,3) NOT NULL,
    costo DECIMAL(8,2) NOT NULL DEFAULT 0,
    notas VARCHAR(255) NULL,
    KEY idx_ajustes_fecha (fecha),
    KEY idx_ajustes_producto (idProducto)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Usuario administrador inicial (descomentar y completar).
-- Genera el hash de la clave en tu equipo con este comando:
--   php herramientas/generar_hash.php "TuClave"
-- El nombre de usuario solo admite letras y espacios (misma regla que el formulario de usuarios).
--
-- INSERT INTO usuarios (usuario, nombre, telefono, direccion, password, rol)
-- VALUES ('TuUsuario', 'Tu Nombre', '900000000', 'Tu direccion', 'HASH_GENERADO', 'administrador');
