---
name: Project overview
description: ACCOUNTING repo is a PHP/XAMPP school management app — modules for academics, finance, staff attendance, comms; per-page architecture (no router)
type: project
---

PHP school management system running on XAMPP/Apache. Repo: github.com/EricBaidoo/ACCOUNTING.

**Stack:** PHP + MySQL (mysqli), TailwindCSS frontend (compiled via local tailwindcss.exe), Composer deps (mpdf, phpspreadsheet, phpword) — vendor/ not committed.

**Architecture:** Per-page PHP files (NOT a single-router app). Each page in pages/<module>/ does its own session_start + includes/db_connect + includes/auth_functions. Modules: academics, administration, finance, communication, teacher, supervisor, common.

**Key includes:** db_connect.php, auth_functions.php (sessions, CSRF, password hashing, role checks), system_settings.php (getSystemSetting/setSystemSetting key-value config), sidebar.php.

**Schema migrations:** Done via update_schema.php at webroot — runs patches on page load if admin is logged in, tracked in _migration_log. NOT a formal migration tool. sql/ folder has schema dumps + .htaccess Deny from all to block web access.

**Why:** Active development by Eric Baidoo. Recent work (last ~7 days, 5 commits) is the staff_attendance module with geofencing + manual override.

**How to apply:** When adding features, follow the per-page module convention — create pages/<module>/<feature>.php with the standard header (session_start, include db_connect, include auth_functions, role check, redirect to login). Use prepared statements for all queries (login.php is the gold-standard pattern; attendance.php and staff_attendance.php still mix raw queries with real_escape_string and need cleanup).
