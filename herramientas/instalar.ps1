# Instala el sistema en esta PC (lo usa instalar.bat): pide las contraseñas sin mostrarlas, ejecuta
# herramientas/instalar.php y crea el acceso directo "Sistema de Inventario" en el escritorio.

. "$PSScriptRoot\entorno.ps1"

function Leer-Clave([string]$Pregunta) {
    $segura = Read-Host $Pregunta -AsSecureString
    $puntero = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($segura)
    try {
        return [Runtime.InteropServices.Marshal]::PtrToStringBSTR($puntero)
    } finally {
        [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($puntero)
    }
}

function Crear-AccesoDirecto([string]$Carpeta) {
    $acceso = (New-Object -ComObject WScript.Shell).CreateShortcut((Join-Path $Carpeta 'Sistema de Inventario.lnk'))
    $acceso.TargetPath = Join-Path $Raiz 'iniciar.bat'
    $acceso.WorkingDirectory = $Raiz
    $acceso.WindowStyle = 7  # minimizada: solo se ve si hay un problema
    $acceso.Description = 'Abrir el Sistema de Inventario'
    $acceso.Save()
}

Write-Host '=== Instalación del Sistema de Inventario ===' -ForegroundColor Cyan
Write-Host ''

$php = Buscar-Php
if (-not $php) {
    Write-Host 'No se encontró PHP. Instala XAMPP (ver README.md) y vuelve a ejecutar instalar.bat.' -ForegroundColor Red
    Esperar-Enter
    exit 1
}
if (-not (Encender-MySql $php)) {
    Write-Host 'MySQL no está encendido. Abre XAMPP Control Panel, pulsa Start en MySQL y vuelve a ejecutar instalar.bat.' -ForegroundColor Red
    Esperar-Enter
    exit 1
}

$env:INSTALAR_CLAVE_ROOT = Leer-Clave 'Contraseña de root de MySQL (en XAMPP no tiene: solo presiona Enter)'
Write-Host ''
Write-Host 'Ahora el usuario con el que vas a entrar al sistema (administrador).'
$env:INSTALAR_USUARIO = Read-Host 'Usuario [admin]'
do {
    $clave = Leer-Clave 'Contraseña (Enter para generar una)'
    $repetida = if ($clave) { Leer-Clave 'Repite la contraseña' } else { '' }
    if ($clave -cne $repetida) { Write-Host 'Las contraseñas no coinciden, escríbela otra vez.' -ForegroundColor Yellow }
} while ($clave -cne $repetida)
$env:INSTALAR_CLAVE_ADMIN = $clave
Write-Host ''

for ($intento = 1; $intento -le 3; $intento++) {
    & $php (Join-Path $PSScriptRoot 'instalar.php')
    $codigo = $LASTEXITCODE
    if ($codigo -ne 2 -or $intento -eq 3) { break }
    $env:INSTALAR_CLAVE_ROOT = Leer-Clave 'Escribe otra vez la contraseña de root de MySQL'
}
Remove-Item Env:INSTALAR_CLAVE_ROOT, Env:INSTALAR_USUARIO, Env:INSTALAR_CLAVE_ADMIN -ErrorAction SilentlyContinue

if ($codigo -eq 0) {
    try {
        Crear-AccesoDirecto ([Environment]::GetFolderPath('Desktop'))
        Write-Host ''
        Write-Host 'Para abrir el sistema: doble clic en "Sistema de Inventario" en el escritorio (o en iniciar.bat).' -ForegroundColor Green
    } catch {
        Write-Host ''
        Write-Host 'Para abrir el sistema: doble clic en iniciar.bat.' -ForegroundColor Green
    }
}
Esperar-Enter
exit $codigo
