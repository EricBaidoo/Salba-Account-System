# Student Data Migration Options

Since XAMPP is having startup issues, here are three alternative methods to migrate your student data:

## Option 1: Export from PHPMyAdmin (Recommended)

1. **Access PHPMyAdmin directly:**
   - Open browser and go to: http://localhost/phpmyadmin/
   - If XAMPP Control Panel opened, click "Admin" next to MySQL

2. **Export student data:**
   - Select your database (accounting)
   - Click on "students" table
   - Click "Export" tab
   - Choose "CSV" format
   - Click "Go" to download

3. **Import to Hostinger:**
   - Log into your Hostinger control panel
   - Go to PHPMyAdmin for your hosted database
   - Select "students" table
   - Click "Import" tab
   - Upload the CSV file

## Option 2: Use the Migration Script (If MySQL starts)

If you can get MySQL running:

1. Open http://localhost/ACCOUNTING/migrate_students.php
2. The script will show your current students
3. Copy the data and paste into the hosted system

## Option 3: Manual Re-entry

If other options fail:

1. Go to your hosted site: [your-domain]/add_student.php
2. Manually re-enter each student
3. Or use the bulk upload feature with CSV

## Option 4: Direct File Copy (Advanced)

If you know where your student data is stored locally, you can:

1. Find the MySQL data directory: C:\xampp\mysql\data\accounting\
2. Look for students.* files
3. This requires more technical knowledge to import

## Quick Test: Check if PHPMyAdmin Works

Try opening: http://localhost/phpmyadmin/
If it works, use Option 1 (easiest method)