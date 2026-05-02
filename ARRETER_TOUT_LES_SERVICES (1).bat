@echo off
title Arreter AGV
color 0C
echo Arret des services AGV...
taskkill /f /im httpd.exe 2>nul && echo Apache arrete. || echo Apache deja arrete.
taskkill /f /im mysqld.exe 2>nul && echo MySQL arrete. || echo MySQL deja arrete.
taskkill /f /im php.exe 2>nul && echo PHP arrete. || echo PHP deja arrete.
echo.
echo Tous les services arretes.
pause
