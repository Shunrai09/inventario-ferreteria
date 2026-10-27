@echo off
rem Instala el sistema en esta PC: base de datos, usuario administrador y acceso directo en el escritorio.
title Instalar Sistema de Inventario
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0herramientas\instalar.ps1"
