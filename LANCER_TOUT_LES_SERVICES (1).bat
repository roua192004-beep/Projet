@echo off
title Robot AGV — Sagemcom El Zahra
color 0A
cls
echo.
echo  ============================================================
echo   ROBOT AGV — SAGEMCOM EL ZAHRA
echo  ============================================================
echo.
echo  [1] Tout lancer  (XAMPP + Email Daemon)
echo  [2] XAMPP seulement
echo  [3] Ouvrir firewall port 80 (une seule fois)
echo  [4] Ouvrir l interface dans le navigateur
echo  [5] Quitter
echo.
set /p choix= Votre choix (1-5) :
if "%choix%"=="1" goto ALL
if "%choix%"=="2" goto XAMPP
if "%choix%"=="3" goto FIREWALL
if "%choix%"=="4" goto BROWSER
goto END
:ALL
echo Demarrage XAMPP...
if exist "C:\xampp\xampp_start.exe" (start "" "C:\xampp\xampp_start.exe") else (start "" "C:\xampp\xampp-control.exe")
timeout /t 5 /nobreak >nul
echo Demarrage Email Daemon...
start "Email Daemon AGV" cmd /k "cd /d C:\xampp\htdocs\robot-inventaire && php email_daemon.php"
timeout /t 2 /nobreak >nul
start "" "http://localhost/robot-inventaire/login.php"
goto END
:XAMPP
if exist "C:\xampp\xampp_start.exe" (start "" "C:\xampp\xampp_start.exe") else (start "" "C:\xampp\xampp-control.exe")
timeout /t 3 /nobreak >nul
start "" "http://localhost/robot-inventaire/login.php"
goto END
:FIREWALL
netsh advfirewall firewall add rule name="XAMPP-AGV-Port80" dir=in action=allow protocol=TCP localport=80
echo Port 80 ouvert. Le Pi peut maintenant acceder au serveur.
goto END
:BROWSER
start "" "http://localhost/robot-inventaire/login.php"
goto END
:END
echo.
pause
