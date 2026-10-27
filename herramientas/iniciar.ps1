# Abre el sistema en el navegador (lo usa iniciar.bat). Si hace falta, enciende antes MySQL de XAMPP y el
# servidor de PHP, los dos sin ventana: siguen funcionando hasta que se apaga la PC.
param([int]$Puerto = 8000)

. "$PSScriptRoot\entorno.ps1"

$php = Buscar-Php
if (-not $php) {
    Write-Host 'No se encontró PHP. Instala XAMPP (ver README.md) y vuelve a intentarlo.' -ForegroundColor Red
    Esperar-Enter
    exit 1
}

if (-not (Encender-MySql $php)) {
    Write-Host 'MySQL no está encendido. Abre XAMPP Control Panel y pulsa Start en MySQL' -ForegroundColor Yellow
    Write-Host '(o enciende el servicio de MySQL en Servicios de Windows) y vuelve a abrir el sistema.' -ForegroundColor Yellow
    Esperar-Enter
    exit 1
}

if (-not (Probar-Puerto $Puerto)) {
    Start-Process -FilePath $php -ArgumentList '-S', "127.0.0.1:$Puerto", '-t', 'public' -WorkingDirectory $Raiz -WindowStyle Hidden
    for ($i = 0; $i -lt 20 -and -not (Probar-Puerto $Puerto); $i++) {
        Start-Sleep -Milliseconds 500
    }
    if (-not (Probar-Puerto $Puerto)) {
        Write-Host "No se pudo iniciar el sistema en el puerto $Puerto. Puede que otro programa lo esté usando." -ForegroundColor Red
        Esperar-Enter
        exit 1
    }
}

Start-Process "http://localhost:$Puerto/"
