# Sistema de Inventario y Ventas

Aplicación web en PHP y MySQL, sin framework, para llevar el inventario, el punto de venta, las compras a proveedores, los clientes y los usuarios de una empresa con una o varias tiendas.

Pensada para una ferretería: cada producto tiene una unidad de venta (unidad, metro, kilo, litro...) y las que son fraccionables se venden y compran con decimales (2.5 m, 0.25 kg). Los productos se agrupan en categorías que se administran desde el menú **Categorías** y sirven para filtrar el listado de productos y los reportes.

Cada venta genera un ticket imprimible (impresora térmica de 80 o 58 mm), el inventario se puede exportar a Excel (`.xlsx`) desde **Productos → Exportar a Excel** e importar desde Excel o CSV con **Productos → Importar** (con vista previa antes de confirmar), y los reportes de ventas y de compras a PDF con el botón **Exportar PDF**. Ambas exportaciones respetan los filtros aplicados.

La venta se cobra con la **caja** abierta y se elige la forma de pago (efectivo con cálculo del vuelto, Yape, Plin, tarjeta o transferencia). Al cerrar la caja se compara el efectivo que debía haber con el contado y se imprime el resumen. Las ventas hechas por error se **anulan** con un motivo: no se borran, devuelven su stock y dejan de sumar. Los cambios de stock que no son compras ni ventas (conteo físico, merma, robo, uso interno) se registran como **ajustes de inventario**, y la base se **respalda** sola todos los días.

## Requisitos

- PHP 8.0 o superior con las extensiones `pdo_mysql` y `mbstring`.
- MySQL 5.7+ o MariaDB 10.3+.

En una PC con Windows basta con instalar [XAMPP](https://www.apachefriends.org/es/download.html), que trae los dos (ver **Instalación en una PC con Windows**).

## Estructura

```
config/            Configuración de la empresa y de la base de datos
  config.example.php   Plantilla (se sube a git)
  config.php           Copia con los datos reales (NO se sube a git)
app/               Código que no se publica en la web
  bootstrap.php        Arranque: configuración, zona horaria, funciones y sesión
  base_datos.php       Conexión PDO y consultas preparadas
  ayudantes.php        url(), redirigir(), requerirSesion(), tiendas, unidades
                       y cantidades, tema
  funciones/           Lógica por módulo: usuarios, clientes, productos, categorías,
                       ventas, compras, carrito, tiendas, caja, ajustes, respaldos,
                       excel, pdf e importacion (leen y generan .xlsx, .pdf y .sql.gz
                       sin librerías ni la extensión zip)
  vistas/              Encabezado HTML, barra de navegación, tarjetas de totales y
                       estilos de los tickets
public/            Raíz web: lo único accesible desde el navegador
  index.php            Panel principal
  login.php, iniciar_sesion.php, cerrar_sesion.php
  productos/  categorias/  clientes/  usuarios/  perfil/
  empresa/             Datos de la empresa (nombre, RUC, ticket) y ticket de prueba
  ventas/              Punto de venta (carrito), cobro, anulación y ticket imprimible
  caja/                Apertura, cierre con arqueo, comprobante e historial de cierres
  compras/             Registro de compras
  ajustes/             Ajustes de inventario e historial
  reportes/            Reportes de ventas y de compras
  respaldos/           Respaldar, descargar y restaurar la base
  assets/              CSS, JS, fuentes e imágenes
database/
  esquema.sql          Tablas del sistema y categorías iniciales
  migraciones/         Cambios para bases creadas con una versión anterior
herramientas/      Scripts de consola
  instalar.php         Crea la base, el usuario de MySQL y el primer administrador;
                       en una versión nueva, respalda y aplica las migraciones que falten
  respaldar.php        Hace un respaldo (para el Programador de tareas de Windows)
  instalar.ps1, iniciar.ps1, entorno.ps1
                       Lo que ejecutan instalar.bat e iniciar.bat
  generar_hash.php     Genera el hash de una contraseña
  hora.php             Muestra la hora que usa el sistema
instalar.bat       Instalación (y actualización) en una PC con Windows
iniciar.bat        Abre el sistema en el navegador
respaldos/         Respaldos de la base (se crea sola; NO se sube a git)
.htaccess          Redirige a public/ si el hosting no permite cambiar la raíz
```

## Instalación en una PC con Windows

1. **Instala XAMPP** desde https://www.apachefriends.org/es/download.html. Elige la versión con PHP 8.4 e instala con las opciones que vienen marcadas. El sistema usa MySQL y PHP de XAMPP; Apache no hace falta encenderlo.
   Recomendado: abre *XAMPP Control Panel* como administrador y marca la casilla **Service** de MySQL para que se encienda solo con Windows. Si no lo haces, `iniciar.bat` lo enciende cuando hace falta.
2. **Copia la carpeta del sistema** a la PC, por ejemplo a `C:\Inventario`.
3. **Doble clic en `instalar.bat`** (una sola vez). Pide:
   - la contraseña de root de MySQL (en XAMPP no tiene: solo Enter);
   - el usuario y la contraseña del administrador del sistema (Enter genera una contraseña y la muestra al final).

   Crea la base `inventario` con sus tablas y categorías, un usuario de MySQL con permisos solo sobre esa base, el administrador, la conexión en `config/config.php` y el acceso directo **Sistema de Inventario** en el escritorio. Se puede repetir sin riesgo: no borra datos ni crea un segundo administrador.
4. **Para usar el sistema:** doble clic en **Sistema de Inventario** en el escritorio o en `iniciar.bat`. Abre http://localhost:8000 en el navegador. El servidor queda corriendo sin ventana hasta que se apaga la PC, así que cerrar el navegador no lo detiene.

Los vendedores se crean después desde **Usuarios**. Para pasar toda la información de otra PC (productos, ventas, clientes...): allá **Respaldos → Respaldar ahora** y descarga el archivo; acá **Respaldos → Restaurar desde un archivo**. Solo los productos: **Productos → Exportar a Excel** allá e **Importar** acá.

**Actualizar a una versión nueva:** copia los archivos nuevos encima de los anteriores (sin borrar `config/config.php` ni la carpeta `respaldos/`) y vuelve a ejecutar `instalar.bat`. Respalda la base y le aplica los cambios de `database/migraciones/` que falten.

Los scripts también funcionan con MySQL y PHP instalados por separado (PHP en el `PATH` o en `C:\php`).

## Instalación manual o en un hosting

1. **Configuración.** Copia `config/config.example.php` como `config/config.php` y completa el nombre de la empresa, las tiendas y los datos de conexión.
2. **Base de datos.** Crea una base vacía con juego de caracteres `utf8mb4` e importa `database/esquema.sql`.
   Si la base ya existía con una versión anterior, haz una copia y ejecuta una sola vez, en orden, los archivos de `database/migraciones/` que falten (la primera línea de cada uno dice qué columna crea: si ya existe, ese archivo ya se aplicó).
3. **Primer usuario.** Genera el hash de la contraseña:

   ```
   php herramientas/generar_hash.php "TuClave"
   ```

   Inserta el usuario con el `INSERT` de ejemplo que está al final de `database/esquema.sql`. Los demás usuarios se crean desde el sistema. Entran con la contraseña `password_predeterminada` de la configuración y la cambian desde su perfil.
4. **Probar en local.**

   ```
   php -S localhost:8000 -t public
   ```

   Abre http://localhost:8000/login.php

## Roles de usuario

| | Administrador | Vendedor |
|---|---|---|
| Punto de venta y tickets | Sí | Sí |
| Caja: abrir, ver lo cobrado y cerrar | Sí | Sí |
| Anular ventas | Sí | No |
| Historial de cierres de caja, ajustes de inventario, respaldos | Sí | No |
| Productos | Todo (alta, edición, costos, exportar a Excel) | Solo consulta de precio de venta y existencias |
| Clientes | Todo | Ver, agregar y editar (no eliminar) |
| Panel, compras, categorías, reportes | Sí | No |
| Usuarios (crear, editar, cambiar rol, eliminar) | Sí | No |
| Perfil y cambio de contraseña | Sí | Sí |

Los permisos se comprueban en el servidor (`requerirAdministrador()` en `app/ayudantes.php`), no solo ocultando el menú. El rol se lee de la base en cada página, así que un cambio de rol aplica de inmediato. Nadie puede cambiar su propio rol ni eliminarse, para que siempre quede un administrador, y no se elimina a un usuario con ventas o compras registradas.

## Caja y formas de pago

1. Al empezar el día se abre la caja (**Caja**, o desde el mismo punto de venta) con el monto inicial: el sencillo para dar vuelto. Sin caja abierta se puede armar el carrito, pero no cobrar.
2. Al cobrar se elige la forma de pago. Con efectivo se puede escribir con cuánto paga el cliente (o pulsar *Exacto*, *S/50*, *S/100*...) y se muestra el vuelto, que también sale en el ticket.
3. Al cerrar se cuenta el efectivo del cajón. El sistema muestra si cuadra, sobra o falta contra lo que debía haber (monto inicial + ventas en efectivo − devoluciones en efectivo), imprime el comprobante del cierre y hace un respaldo de la base.

Yape, Plin, tarjeta y transferencia no entran al cajón: el cierre los muestra aparte para revisarlos en el celular o en el banco. El historial de cierres está en **Reportes → Cierres de caja**.

## Anular ventas

Una venta hecha por error se anula desde el reporte de ventas (una o varias a la vez) o desde su ticket, siempre con un motivo. No se borra: queda marcada con quién la anuló, cuándo y por qué, sus productos vuelven al inventario y deja de sumar en el panel, los reportes y la caja. En el reporte se ven con el filtro **Ventas: Anuladas** o **Todas**.

Si se anula una venta de una caja que ya se cerró (por ejemplo, una devolución al día siguiente), su importe se descuenta de la caja abierta en ese momento, que es de donde sale el dinero; el cierre de la caja anterior no cambia.

## Ajustes de inventario

**Productos → Ajustar stock** (o el botón de cada producto) registra un cambio de existencia que no es compra ni venta: un conteo físico (se escribe lo que se contó), una merma, una pérdida o robo, uso interno, una devolución al proveedor o un ingreso sin compra. Cada ajuste guarda la existencia anterior y la nueva, quién lo hizo y el costo, así que **Reportes → Ajustes de inventario** muestra cuánto se perdió al costo en el período y se puede exportar a PDF. Las importaciones de Excel que cambian la existencia también quedan registradas ahí.

## Respaldos

La base se respalda sola una vez al día (al primer inicio de sesión) y cada vez que se cierra la caja. Los archivos (`.sql.gz`) quedan en la carpeta `respaldos/` y, si se configura `respaldos.copia`, también en una USB o en Google Drive, que es lo que protege si falla el disco de la PC. En **menú del usuario → Respaldos** se puede respaldar en el momento, descargar un respaldo y restaurar uno (de la lista o subiendo el archivo); antes de restaurar se guarda un respaldo de lo que había. El panel avisa si el último respaldo tiene más de dos días o si algo falló.

Los respaldos también se pueden importar con phpMyAdmin (**Importar**, con la base seleccionada). Para programar uno más con el Programador de tareas de Windows: `php herramientas/respaldar.php`.

## Publicar en un hosting

- **Recomendado:** configura `public/` como carpeta raíz del sitio.
- **Si el hosting no lo permite** (Apache con `mod_rewrite`): sube el proyecto completo. El `.htaccess` de la raíz envía todas las peticiones a `public/`, así que `app/`, `config/` y `database/` no quedan expuestos.

`config/config.php` hay que subirlo a mano: no está en git porque contiene la contraseña de la base de datos.

## Personalizar para la empresa

**El nombre de la empresa, el RUC, la dirección, el teléfono, el mensaje al final del ticket y el ancho del papel** (80 o 58 mm) se cambian desde el sistema: **menú del usuario → Datos de la empresa** (solo administradores). Se guardan en la base (tabla `configuracion`), así que viajan con los respaldos y no se pierden al actualizar. En esa misma pantalla, **Imprimir ticket de prueba** sirve para probar la ticketera sin hacer una venta.

Lo demás está en `config/config.php`:

| Clave | Qué cambia |
|---|---|
| `empresa.nombre`, `empresa.sistema` | Nombre en la barra, el login y el título de las páginas. El nombre es solo el valor inicial: luego se cambia en Datos de la empresa |
| `empresa.logo` | Ruta de un logo dentro de `public/`, por ejemplo `assets/img/logo.png`. Vacío muestra el nombre |
| `empresa.icono` | Icono de Font Awesome junto al nombre cuando no hay logo, por ejemplo `fas fa-tools` |
| `empresa.ruc`, `empresa.direccion`, `empresa.telefono` | Valores iniciales del encabezado del ticket (luego, Datos de la empresa). Los vacíos no se imprimen |
| `ticket.ancho_mm`, `ticket.pie` | Valores iniciales del ancho del rollo (80 o 58) y del texto final del ticket (luego, Datos de la empresa) |
| `moneda` | Símbolo de los importes (`S/`, `$`, `€`…) |
| `zona_horaria`, `zona_horaria_bd` | Hora de PHP y de MySQL (deben coincidir) |
| `tiendas` | Tiendas o sucursales: nombre, etiqueta corta y colores. La clave es lo que se guarda en `productos.clasificacion`. Con una sola tienda (lo configurado de inicio) se ocultan el selector de tienda, sus columnas, filtros y estadísticas; al agregar una segunda vuelven a aparecer |
| `unidades` | Unidades de venta. La clave (`und`, `m`, `kg`…) es lo que se guarda en `productos.unidad` y lo que se muestra junto a las cantidades. `decimales => true` permite fracciones |
| `tema` | Colores de la interfaz |
| `password_predeterminada` | Contraseña inicial de los usuarios nuevos |
| `formas_pago` | Formas de pago del punto de venta. La clave se guarda en `ventas.formaPago`; `efectivo` debe existir (calcula el vuelto y es la que se cuenta al cerrar la caja) |
| `respaldos.copia` | Otra carpeta donde guardar también cada respaldo: una USB (`E:\\Respaldos`) o la carpeta de Google Drive. Vacío: solo en `respaldos/` |
| `respaldos.conservar` | Cuántos respaldos se guardan; los más antiguos se borran solos |

## Convenciones para el código

- Cada página de `public/` empieza cargando `app/bootstrap.php` y, si es privada, llama a `requerirSesion()`.
- Los enlaces y redirecciones internas usan `url('modulo/archivo.php')`, que calcula la ruta relativa desde la página actual. Así el sistema funciona en la raíz del dominio o en una subcarpeta.
- Las consultas SQL van en `app/funciones/`, no en las páginas.
- **Diseño:** los estilos están en `public/assets/css/app.css` (variables de color, radios y sombras al inicio). Para pantallas nuevas usa los componentes existentes en vez de colores sueltos: `.encabezado-pagina` (título `.titulo-pagina`, subtítulo y `.acciones-pagina`), `.indicador` para cifras, `.card` con encabezado neutro, `.barra-filtros`, `.estado-vacio`, botones `btn-primary` (acción principal), `btn-success` (confirmar venta o compra), `btn-outline-secondary` (secundarias) y `btn-outline-danger btn-icono` (eliminar en tablas), e insignias `badge bg-success/danger/warning` para estados. Los botones de solo icono llevan `aria-label`.
