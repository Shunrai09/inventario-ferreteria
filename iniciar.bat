@echo off
rem Abre el sistema en el navegador. Si hace falta, enciende antes MySQL de XAMPP y el servidor de PHP.
title Sistema de Inventario
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0herramientas\iniciar.ps1"
