-- comprobar: ventas.anulada
-- Migración: formas de pago, caja (apertura y cierre), ventas anuladas en vez de borradas y ajustes de inventario.
--
-- Solo para bases creadas antes de este cambio. instalar.bat la aplica sola (después de hacer un respaldo);
-- a mano: ejecutarla UNA sola vez con la base seleccionada.
-- Las ventas que ya existían quedan como pagadas en efectivo, sin caja y sin anular.

ALTER TABLE ventas
    ADD COLUMN formaPago VARCHAR(20) NOT NULL DEFAULT 'efectivo',
    ADD COLUMN pagoCon DECIMAL(10,2) NULL,
    ADD COLUMN idCaja BIGINT UNSIGNED NULL,
    ADD COLUMN anulada TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN fechaAnulacion DATETIME NULL,
    ADD COLUMN idUsuarioAnulacion BIGINT UNSIGNED NULL,
    ADD COLUMN motivoAnulacion VARCHAR(255) NULL,
    ADD COLUMN idCajaAnulacion BIGINT UNSIGNED NULL,
    ADD KEY idx_ventas_caja (idCaja),
    ADD KEY idx_ventas_caja_anulacion (idCajaAnulacion);

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
