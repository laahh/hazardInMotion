@echo off
REM Fix SSH Key Permissions for Windows
REM This script fixes the permissions on the PEM file so SSH will accept it

echo ========================================
echo Fixing SSH Key Permissions
echo ========================================
echo.

SET KEY_FILE=C:\laragon\www\Admin\public\JumpHostVPC2.pem

if not exist "%KEY_FILE%" (
    echo ERROR: Key file not found: %KEY_FILE%
    pause
    exit /b 1
)

echo Current permissions:
icacls "%KEY_FILE%"
echo.

echo Step 1: Removing inheritance...
icacls "%KEY_FILE%" /inheritance:r
echo.

echo Step 2: Removing group permissions...
icacls "%KEY_FILE%" /remove "BUILTIN\Users" 2>nul
icacls "%KEY_FILE%" /remove "NT AUTHORITY\Authenticated Users" 2>nul
echo.

echo Step 3: Granting read-only permission to current user...
icacls "%KEY_FILE%" /grant:r "%USERNAME%:R"
echo.

echo New permissions:
icacls "%KEY_FILE%"
echo.

echo ========================================
echo Permission fix complete!
echo ========================================
echo.
echo Now you can run setup-ssh-tunnel.bat
echo.
pause

