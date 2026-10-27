-- comprobar: productos.unidad
-- Migración: venta por metro/kilo (cantidades con decimales y unidad por producto) y categorías de productos.
--
-- Solo para bases creadas con la versión anterior de database/esquema.sql. Ejecutar UNA sola vez,
-- con la base seleccionada (phpMyAdmin > SQL, o bien: mysql -u USUARIO -p NOMBRE_BD < este_archivo).
-- Haz una copia de la base antes (phpMyAdmin > Exportar).
--
-- No borra datos: las existencias y cantidades enteras pasan a 10.000, 3.000, etc.,
-- y todos los productos quedan con unidad 'und' y sin categoría hasta que se editen.

CREATE TABLE IF NOT EXISTS categorias (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    UNIQUE KEY uq_categorias_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO categorias (nombre) VALUES
    ('Herramientas'), ('Herramientas eléctricas'), ('Electricidad'), ('Gasfitería'), ('Pinturas'),
    ('Construcción'), ('Clavos y tornillos'), ('Cerrajería'), ('Jardinería'), ('Seguridad industrial');

ALTER TABLE productos
    MODIFY existencia DECIMAL(12,3) NOT NULL DEFAULT 0,
    ADD COLUMN unidad VARCHAR(10) NOT NULL DEFAULT 'und' AFTER existencia,
    ADD COLUMN idCategoria BIGINT UNSIGNED NULL AFTER clasificacion,
    ADD KEY idx_productos_categoria (idCategoria);

ALTER TABLE productos_ventas MODIFY cantidad DECIMAL(12,3) NOT NULL;

ALTER TABLE productos_compras MODIFY cantidad DECIMAL(12,3) NOT NULL;
