-- comprobar: configuracion.clave
-- Migración: datos de la empresa editables desde el sistema (nombre, RUC, dirección, teléfono y ticket).
--
-- Solo para bases creadas antes de este cambio. instalar.bat la aplica sola; a mano: ejecutarla UNA sola vez
-- con la base seleccionada. Mientras la tabla esté vacía se siguen usando los datos de config/config.php.

CREATE TABLE IF NOT EXISTS configuracion (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(50) NOT NULL,
    valor TEXT NULL,
    UNIQUE KEY uq_configuracion_clave (clave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
