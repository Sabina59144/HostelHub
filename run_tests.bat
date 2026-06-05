@echo off
echo ==========================================
echo  HostelHub Room Module - Running Tests
echo ==========================================

cd /d C:\xampp\htdocs\HostelHub

echo.
echo [1/3] Installing PHPUnit via Composer...
C:\xampp\php\php.exe C:\xampp\php\composer.phar install --no-interaction
if errorlevel 1 (
    echo Composer not found. Downloading...
    C:\xampp\php\php.exe -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    C:\xampp\php\php.exe composer-setup.php
    C:\xampp\php\php.exe -r "unlink('composer-setup.php');"
    C:\xampp\php\php.exe composer.phar install --no-interaction
)

echo.
echo [2/3] Creating logs folder...
if not exist "tests\logs" mkdir tests\logs

echo.
echo [3/3] Running PHPUnit tests...
C:\xampp\php\php.exe vendor\bin\phpunit --configuration phpunit.xml

echo.
echo ==========================================
echo  Done! Log files saved to tests\logs\
echo    - tests\logs\junit.xml
echo    - tests\logs\testdox.html
echo    - tests\logs\testdox.txt
echo ==========================================
pause
