@echo off
echo Menjalankan export database SiDispo...
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0export_db.ps1"
if %ERRORLEVEL% EQU 0 (
    echo.
    echo Selesai! sidispo.sql telah berhasil diperbarui dan disanitasi.
) else (
    echo.
    echo Terjadi kesalahan saat export database.
)
pause
