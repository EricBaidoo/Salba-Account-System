@echo off
set MYSQLDUMP="C:\xampp\mysql\bin\mysqldump.exe"
echo Exporting Salba_acc to final SQL...
%MYSQLDUMP% -u root -proot Salba_acc > sql\final_full_system_reconciled.sql
echo Done!
