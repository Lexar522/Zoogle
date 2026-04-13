@echo off
setlocal

REM Always run from this file's directory (Laravel app root).
set "APP_DIR=%~dp0"
cd /d "%APP_DIR%"

REM Preferred PHP path from your current setup.
set "PHP_EXE=C:\Users\Lexar\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe"

REM Fallback to php from PATH if preferred path doesn't exist.
if not exist "%PHP_EXE%" (
    where php >nul 2>nul
    if errorlevel 1 (
        echo [ERROR] PHP not found.
        echo Install PHP or update PHP_EXE in start-server.cmd
        pause
        exit /b 1
    )
    set "PHP_EXE=php"
)

echo Starting Laravel server at http://127.0.0.1:8000
"%PHP_EXE%" artisan serve --host=127.0.0.1 --port=8000

endlocal
