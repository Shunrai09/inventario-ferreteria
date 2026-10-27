-- comprobar: usuarios.rol
-- Migración: roles de usuario (administrador / vendedor).
--
-- Solo para bases creadas antes de este cambio. instalar.bat la aplica sola; a mano: ejecutar UNA sola vez, con la base seleccionada.
-- Los usuarios que ya existían quedan como administradores para que nadie pierda el acceso;
-- después se puede pasar a vendedor a quien corresponda desde Usuarios > Editar.

ALTER TABLE usuarios ADD COLUMN rol VARCHAR(20) NOT NULL DEFAULT 'vendedor' AFTER password;

UPDATE usuarios SET rol = 'administrador';
