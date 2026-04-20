@echo off
echo Creating vendor folder structure...

mkdir vendor\phpmailer\phpmailer\src 2>nul

echo Folder structure created!
echo.
echo Now download PHPMailer from:
echo https://github.com/PHPMailer/PHPMailer/archive/refs/heads/master.zip
echo.
echo Extract and copy the contents of 'src' folder to:
echo vendor\phpmailer\phpmailer\src\
echo.
pause