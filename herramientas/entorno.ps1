# Funciones comunes de instalar.bat e iniciar.bat: ubicar PHP y encender MySQL de XAMPP si está apagado.

$Raiz = Split-Path $PSScriptRoot -Parent

# PHP del PATH, o el de XAMPP, o el de C:\php
function Buscar-Php {
    $enRuta = Get-Command php.exe -ErrorAction SilentlyContinue
    if ($enRuta) { return $enRuta.Source }
    foreach ($ruta in 'C:\xampp\php\php.exe', 'D:\xampp\php\php.exe', 'C:\php\php.exe') {
        if (Test-Path $ruta) { return $ruta }
    }
    return $null
}

function Probar-Puerto([int]$Puerto) {
    $cliente = New-Object Net.Sockets.TcpClient
    try {
        return $cliente.ConnectAsync('127.0.0.1', $Puerto).Wait(700) -and $cliente.Connected
    } catch {
        return $false
    } finally {
        $cliente.Close()
    }
}

# Si MySQL no responde y está XAMPP, enciende su MySQL (MariaDB) sin ventana, igual que el botón Start del panel.
# Devuelve $true cuando MySQL responde.
function Encender-MySql([string]$Php) {
    if (Probar-Puerto 3306) { return $true }
    $carpetas = @((Split-Path (Split-Path $Php -Parent) -Parent), 'C:\xampp', 'D:\xampp') | Select-Object -Unique
    $xampp = $carpetas | Where-Object { Test-Path (Join-Path $_ 'mysql\bin\mysqld.exe') } | Select-Object -First 1
    if (-not $xampp) { return $false }

    Write-Host 'Encendiendo MySQL de XAMPP...'
    Start-Process -FilePath (Join-Path $xampp 'mysql\bin\mysqld.exe') -ArgumentList '--defaults-file=mysql\bin\my.ini', '--standalone' `
        -WorkingDirectory $xampp -WindowStyle Hidden
    for ($i = 0; $i -lt 30; $i++) {
        Start-Sleep -Milliseconds 500
        if (Probar-Puerto 3306) { return $true }
    }
    return $false
}

function Esperar-Enter {
    Write-Host ''
    Read-Host 'Presiona Enter para cerrar' | Out-Null
}
