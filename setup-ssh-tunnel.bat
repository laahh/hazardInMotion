@echo off
REM SSH Tunnel Helper Script for Windows
REM This script creates an SSH tunnel to PostgreSQL database

SET SSH_HOST=13.212.87.127
SET SSH_PORT=22
SET SSH_USER=ubuntu
SET SSH_KEY=C:\laragon\www\Admin\public\JumpHostVPC2.pem
SET LOCAL_PORT=5433
SET DB_HOST=postgresql-olap-bc-production.cgehsbzl48r0.ap-southeast-1.rds.amazonaws.com
SET DB_PORT=5432

echo ========================================
echo SSH Tunnel Setup
echo ========================================
echo.

REM Check if key file exists
if not exist "%SSH_KEY%" (
    echo ERROR: Key file not found: %SSH_KEY%
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
echo Keep this window open while using the database viewer.
echo Press Ctrl+C to stop the tunnel.
echo.
echo ========================================
echo.

ssh -N -L %LOCAL_PORT%:%DB_HOST%:%DB_PORT% %SSH_USER%@%SSH_HOST% -p %SSH_PORT% -i "%SSH_KEY%" -o StrictHostKeyChecking=no

pause

