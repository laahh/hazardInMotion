@echo off
REM SSH Tunnel Helper Script for Windows
REM This script creates an SSH tunnel to Besigma MySQL database

SET SSH_HOST=13.250.29.29
SET SSH_PORT=22
SET SSH_USER=ubuntu
SET SSH_KEY=C:\laragon\www\Admin\public\bsigma-jumpserver.pem
SET LOCAL_PORT=3307
SET DB_HOST=10.11.58.139
SET DB_PORT=3306

echo ========================================
echo SSH Tunnel Setup - Besigma Database
echo ========================================
echo.

REM Check if key file exists
if not exist "%SSH_KEY%" (
    echo ERROR: Key file not found: %SSH_KEY%
    echo Please ensure the file exists at the specified path.
    pause
    exit /b 1
)

REM Fix permissions if needed (silent)
echo Checking key file permissions...
icacls "%SSH_KEY%" /inheritance:r >nul 2>&1
icacls "%SSH_KEY%" /remove "BUILTIN\Users" >nul 2>&1
icacls "%SSH_KEY%" /remove "NT AUTHORITY\Authenticated Users" >nul 2>&1
icacls "%SSH_KEY%" /grant:r "%USERNAME%:R" >nul 2>&1

echo Creating SSH tunnel...
echo Local port: %LOCAL_PORT%
echo Remote host: %DB_HOST%:%DB_PORT%
echo SSH server: %SSH_USER%@%SSH_HOST%:%SSH_PORT%
echo.
echo Keep this window open while using the application.
echo Press Ctrl+C to stop the tunnel.
echo.
echo ========================================
echo.

ssh -N -L %LOCAL_PORT%:%DB_HOST%:%DB_PORT% %SSH_USER%@%SSH_HOST% -p %SSH_PORT% -i "%SSH_KEY%" -o StrictHostKeyChecking=no

pause

