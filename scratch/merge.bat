@echo off
set MYSQL="C:\xampp\mysql\bin\mysql.exe"
set MYSQLDUMP="C:\xampp\mysql\bin\mysqldump.exe"

echo Creating database smis_merge...
%MYSQL% -u root -proot -e "DROP DATABASE IF EXISTS smis_merge; CREATE DATABASE smis_merge DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

echo Importing production master schema...
%MYSQL% -u root -proot smis_merge < sql\production_ready_utf8.sql

echo Importing reconciled data...
%MYSQL% -u root -proot smis_merge < sql\reconciled_production_ready.sql

echo Setting current semester to Second Semester...
%MYSQL% -u root -proot smis_merge -e "UPDATE system_settings SET current_semester = 'Second Semester' WHERE id = 1;"

echo Exporting merged database...
%MYSQLDUMP% -u root -proot smis_merge > sql\final_full_system_reconciled.sql

echo Done!
