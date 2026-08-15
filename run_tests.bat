@echo off
setlocal
set "PHP=php"
where php >nul 2>nul || set "PHP=C:\xampp\php\php.exe"
"%PHP%" "%~dp0tests\run.php"
if errorlevel 1 pause
endlocal
