<?php
// Configuración del sistema.
// Copia este archivo como config/config.php y completa los datos de la empresa.
// config.php no se sube a git porque contiene la contraseña de la base de datos.

return [
    'empresa' => [
        'nombre'  => 'Mi Ferretería',
        'sistema' => 'Sistema de Inventario',
        // Ruta del logo dentro de public/, por ejemplo 'assets/img/logo.png'.
        // Si se deja vacío se muestra el nombre de la empresa en su lugar.
        'logo'    => '',
        // Icono de Font Awesome que acompaña al nombre cuando no hay logo.
        'icono'   => 'fas fa-tools',
        // Datos del encabezado del ticket de venta. Los que queden vacíos no se imprimen.
        'ruc'       => '',
        'direccion' => '',
        'telefono'  => '',
    ],

    // Ticket de venta (ventas/ticket.php). ancho_mm: 80 o 58, según el rollo de la impresora térmica.
    // pie: texto al final del ticket; cada salto de línea se imprime como una línea aparte.
    'ticket' => [
        'ancho_mm' => 80,
        'pie'      => "¡Gracias por su compra!\nConserve este ticket para cambios.",
    ],

    'moneda' => 'S/',
    'zona_horaria' => 'America/Lima',
    // Desfase que se aplica a la sesión de MySQL para que NOW() y CURDATE() usen la misma hora que PHP.
    'zona_horaria_bd' => '-05:00',

    // Contraseña con la que se crean los usuarios nuevos. Cada usuario la cambia desde su perfil.
    'password_predeterminada' => 'Cambiar123',

    'base_datos' => [
        'host'     => 'localhost',
        'nombre'   => 'inventario',
        'usuario'  => 'root',
        'password' => '',
        'charset'  => 'utf8mb4',
    ],

    // Formas de pago que se eligen al cobrar. La clave se guarda en ventas.formaPago.
    // 'efectivo' debe existir: es la que calcula el vuelto y la que se cuenta al cerrar la caja.
    'formas_pago' => [
        'efectivo'      => 'Efectivo',
        'yape'          => 'Yape',
        'plin'          => 'Plin',
        'tarjeta'       => 'Tarjeta',
        'transferencia' => 'Transferencia',
    ],

    // Respaldos de la base de datos (menú del usuario > Respaldos). Se hace uno automático al iniciar sesión
    // (uno por día) y otro al cerrar la caja. Se guardan en la carpeta respaldos/ del sistema.
    // copia: otra carpeta donde guardar también cada respaldo, para no perderlo si falla el disco. Por ejemplo
    //        una USB ('E:\Respaldos') o la carpeta de Google Drive ('G:\Mi unidad\Respaldos'). Vacío: no copia.
    // conservar: cuántos respaldos se guardan; al pasar de ese número se borran los más antiguos.
    'respaldos' => [
        'copia'     => '',
        'conservar' => 30,
    ],

    // Colores de la interfaz (botones, barra de navegación y pantalla de inicio de sesión).
    'tema' => [
        'primario'        => '#2563EB',
        'primario_oscuro' => '#1D4ED8',
        'fondo'           => '#F1F5F9',
        'fondo_navbar'    => '#FFFFFF',
        'texto_navbar'    => '#1E293B',
        'borde_navbar'    => '#E2E8F0',
    ],

    // Tiendas o sucursales. La clave es el valor que se guarda en productos.clasificacion.
    // nombre: texto completo. corto: etiqueta de 1 o 2 letras para las tablas.
    // badge / texto: clases de Bootstrap. colores: 4 tonos para las tarjetas del panel.
    // Con una sola tienda se ocultan los selectores, filtros y estadísticas por tienda.
    // Para abrir una sucursal basta con agregar otra entrada, por ejemplo:
    //     '2' => ['nombre' => 'Sucursal 2', 'corto' => 'S2', 'badge' => 'bg-warning text-dark',
    //             'texto' => 'text-warning', 'colores' => ['#854D0E', '#CA8A04', '#EAB308', '#FDE047']],
    'tiendas' => [
        '1' => [
            'nombre'  => 'Tienda principal',
            'corto'   => 'TP',
            'badge'   => 'bg-primary',
            'texto'   => 'text-primary',
            'colores' => ['#1E3A8A', '#3B82F6', '#60A5FA', '#93C5FD'],
        ],
    ],

    // Unidades de venta. La clave es lo que se guarda en productos.unidad y lo que se muestra junto a las cantidades.
    // decimales: true permite vender y comprar fracciones (2.5 m, 0.25 kg); false solo números enteros.
    // 'und' debe existir: es la unidad de los productos que no tienen otra.
    'unidades' => [
        'und'     => ['nombre' => 'Unidad',         'decimales' => false],
        'm'       => ['nombre' => 'Metro',          'decimales' => true],
        'kg'      => ['nombre' => 'Kilogramo',      'decimales' => true],
        'l'       => ['nombre' => 'Litro',          'decimales' => true],
        'pie'     => ['nombre' => 'Pie',            'decimales' => true],
        'm2'      => ['nombre' => 'Metro cuadrado', 'decimales' => true],
        'gal'     => ['nombre' => 'Galón',          'decimales' => false],
        'caja'    => ['nombre' => 'Caja',           'decimales' => false],
        'bolsa'   => ['nombre' => 'Bolsa',          'decimales' => false],
        'rollo'   => ['nombre' => 'Rollo',          'decimales' => false],
        'par'     => ['nombre' => 'Par',            'decimales' => false],
        'juego'   => ['nombre' => 'Juego',          'decimales' => false],
        'plancha' => ['nombre' => 'Plancha',        'decimales' => false],
        'varilla' => ['nombre' => 'Varilla',        'decimales' => false],
    ],
];
