@echo off
set MYSQL="C:\xampp\mysql\bin\mysql.exe"

echo Recreating Salba_acc database...
%MYSQL% -u root -proot -e "DROP DATABASE IF EXISTS Salba_acc; CREATE DATABASE Salba_acc DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

echo Importing final_full_system_reconciled.sql into Salba_acc...
%MYSQL% -u root -proot Salba_acc < sql\final_full_system_reconciled.sql

echo Database Salba_acc is now fully synchronized with the merged structure and reconciled financial totals!
