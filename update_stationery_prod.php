<?php
/**
 * ONE-TIME PRODUCTION UPDATE SCRIPT
 * Upload this to your live server and visit it once in your browser.
 * e.g., yourdomain.com/ACCOUNTING/update_stationery_prod.php
 * 
 * IMPORTANT: Delete this file after it successfully runs!
 */

require_once __DIR__ . '/includes/db_connect.php';

// Check if admin is logged in (optional, but good for security)
session_start();
if (!isset($_SESSION['user_id']) || !in_array(($_SESSION['role'] ?? ''), ['admin', 'data_entry'])) {
    die("Unauthorized access. Please log in as an administrator first.");
}

$queries = [
    // stationery_items
    "INSERT IGNORE INTO stationery_items (id, name, description, unit, default_price, created_at) VALUES ('1','sdfgagf','','1','5.00','2026-07-01 07:42:47')",
    "INSERT IGNORE INTO stationery_items (id, name, description, unit, default_price, created_at) VALUES ('3','Ark file','','pcs','0.00','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_items (id, name, description, unit, default_price, created_at) VALUES ('4','A4 rim white','','pcs','0.00','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_items (id, name, description, unit, default_price, created_at) VALUES ('5','A4 rim coloured','','pcs','0.00','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_items (id, name, description, unit, default_price, created_at) VALUES ('6','Manila card','','pcs','0.00','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_items (id, name, description, unit, default_price, created_at) VALUES ('7','Play dough','','pcs','0.00','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_items (id, name, description, unit, default_price, created_at) VALUES ('8','Jumbo crayon (crayola)','','pcs','0.00','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_items (id, name, description, unit, default_price, created_at) VALUES ('9','Acrylic paint','','pcs','0.00','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_items (id, name, description, unit, default_price, created_at) VALUES ('10','Painting brushes','','pcs','0.00','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_items (id, name, description, unit, default_price, created_at) VALUES ('11','Child scissors','','pcs','0.00','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_items (id, name, description, unit, default_price, created_at) VALUES ('12','Decorative tape','','pcs','0.00','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_items (id, name, description, unit, default_price, created_at) VALUES ('13','Tissue Jumbo size','','pcs','0.00','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_items (id, name, description, unit, default_price, created_at) VALUES ('14','Disposable gloves','','pcs','0.00','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_items (id, name, description, unit, default_price, created_at) VALUES ('15','Foam sheet','','pcs','0.00','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_items (id, name, description, unit, default_price, created_at) VALUES ('16','Gloves box','','pcs','0.00','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_items (id, name, description, unit, default_price, created_at) VALUES ('17','Nataraj pencils','','pcs','0.00','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_items (id, name, description, unit, default_price, created_at) VALUES ('18','Nataraj erasers','','pcs','0.00','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_items (id, name, description, unit, default_price, created_at) VALUES ('19','Chip board','','pcs','0.00','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_items (id, name, description, unit, default_price, created_at) VALUES ('20','Note 1 A1','','pcs','0.00','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_items (id, name, description, unit, default_price, created_at) VALUES ('21','Note 1 D1','','pcs','0.00','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_items (id, name, description, unit, default_price, created_at) VALUES ('22','Note 1 G','','pcs','0.00','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_items (id, name, description, unit, default_price, created_at) VALUES ('23','Clear bag (file)','','pcs','0.00','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_items (id, name, description, unit, default_price, created_at) VALUES ('24','Sharpeners','','pcs','0.00','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_items (id, name, description, unit, default_price, created_at) VALUES ('25','Pack coloured pencils','','pcs','0.00','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_items (id, name, description, unit, default_price, created_at) VALUES ('26','Pack Nataraj pencils','','pcs','0.00','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_items (id, name, description, unit, default_price, created_at) VALUES ('27','Note 1 A2','','pcs','0.00','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_items (id, name, description, unit, default_price, created_at) VALUES ('28','Note 1 D2','','pcs','0.00','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_items (id, name, description, unit, default_price, created_at) VALUES ('29','Coloured pencils','','pcs','0.00','2026-08-04 13:37:52')",

    // stationery_assignments
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('3','3','Crèche','2026/2027','Semester 1','1','0.00','1','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('4','4','Crèche','2026/2027','Semester 1','1','0.00','2','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('5','5','Crèche','2026/2027','Semester 1','1','0.00','3','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('6','6','Crèche','2026/2027','Semester 1','1','0.00','4','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('7','7','Crèche','2026/2027','Semester 1','1','0.00','5','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('8','8','Crèche','2026/2027','Semester 1','2','0.00','6','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('9','9','Crèche','2026/2027','Semester 1','1','0.00','7','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('10','10','Crèche','2026/2027','Semester 1','1','0.00','8','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('11','11','Crèche','2026/2027','Semester 1','1','0.00','9','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('12','12','Crèche','2026/2027','Semester 1','1','0.00','10','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('13','13','Crèche','2026/2027','Semester 1','1','0.00','11','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('14','14','Crèche','2026/2027','Semester 1','1','0.00','12','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('15','15','Crèche','2026/2027','Semester 1','1','0.00','13','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('16','16','Crèche','2026/2027','Semester 1','1','0.00','14','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('17','8','Nursery','2026/2027','Semester 1','1','0.00','1','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('18','17','Nursery','2026/2027','Semester 1','1','0.00','2','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('19','18','Nursery','2026/2027','Semester 1','1','0.00','3','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('20','12','Nursery','2026/2027','Semester 1','1','0.00','4','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('21','19','Nursery','2026/2027','Semester 1','1','0.00','5','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('22','6','Nursery','2026/2027','Semester 1','1','0.00','6','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('23','5','Nursery','2026/2027','Semester 1','1','0.00','7','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('24','4','Nursery','2026/2027','Semester 1','1','0.00','8','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('25','11','Nursery','2026/2027','Semester 1','1','0.00','9','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('26','7','Nursery','2026/2027','Semester 1','1','0.00','10','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('27','20','Nursery','2026/2027','Semester 1','4','0.00','11','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('28','21','Nursery','2026/2027','Semester 1','4','0.00','12','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('29','22','Nursery','2026/2027','Semester 1','8','0.00','13','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('30','23','Nursery','2026/2027','Semester 1','3','0.00','14','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('31','15','Nursery','2026/2027','Semester 1','1','0.00','15','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('32','10','Nursery','2026/2027','Semester 1','1','0.00','16','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('33','13','Nursery','2026/2027','Semester 1','1','0.00','17','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('34','14','Nursery','2026/2027','Semester 1','1','0.00','18','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('35','24','Nursery','2026/2027','Semester 1','1','0.00','19','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('36','25','Nursery 2','2026/2027','Semester 1','1','0.00','1','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('37','8','Nursery 2','2026/2027','Semester 1','1','0.00','2','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('38','26','Nursery 2','2026/2027','Semester 1','2','0.00','3','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('39','11','Nursery 2','2026/2027','Semester 1','1','0.00','4','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('40','7','Nursery 2','2026/2027','Semester 1','1','0.00','5','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('41','4','Nursery 2','2026/2027','Semester 1','1','0.00','6','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('42','5','Nursery 2','2026/2027','Semester 1','1','0.00','7','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('43','6','Nursery 2','2026/2027','Semester 1','1','0.00','8','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('44','19','Nursery 2','2026/2027','Semester 1','1','0.00','9','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('45','12','Nursery 2','2026/2027','Semester 1','1','0.00','10','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('46','18','Nursery 2','2026/2027','Semester 1','1','0.00','11','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('47','9','Nursery 2','2026/2027','Semester 1','1','0.00','12','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('48','10','Nursery 2','2026/2027','Semester 1','1','0.00','13','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('49','20','Nursery 2','2026/2027','Semester 1','6','0.00','14','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('50','27','Nursery 2','2026/2027','Semester 1','4','0.00','15','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('51','21','Nursery 2','2026/2027','Semester 1','6','0.00','16','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('52','28','Nursery 2','2026/2027','Semester 1','4','0.00','17','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('53','22','Nursery 2','2026/2027','Semester 1','3','0.00','18','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('54','23','Nursery 2','2026/2027','Semester 1','3','0.00','19','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('55','13','Nursery 2','2026/2027','Semester 1','1','0.00','20','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('56','24','Nursery 2','2026/2027','Semester 1','1','0.00','21','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('57','15','Nursery 2','2026/2027','Semester 1','1','0.00','22','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('58','19','KG1','2026/2027','Semester 1','1','0.00','1','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('59','6','KG1','2026/2027','Semester 1','1','0.00','2','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('60','12','KG1','2026/2027','Semester 1','1','0.00','3','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('61','5','KG1','2026/2027','Semester 1','1','0.00','4','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('62','4','KG1','2026/2027','Semester 1','1','0.00','5','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('63','7','KG1','2026/2027','Semester 1','1','0.00','6','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('64','13','KG1','2026/2027','Semester 1','1','0.00','7','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('65','26','KG1','2026/2027','Semester 1','2','0.00','8','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('66','25','KG1','2026/2027','Semester 1','2','0.00','9','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('67','18','KG1','2026/2027','Semester 1','1','0.00','10','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('68','15','KG1','2026/2027','Semester 1','1','0.00','11','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('69','10','KG1','2026/2027','Semester 1','1','0.00','12','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('70','11','KG1','2026/2027','Semester 1','1','0.00','13','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('71','23','KG1','2026/2027','Semester 1','3','0.00','14','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('72','20','KG1','2026/2027','Semester 1','6','0.00','15','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('73','27','KG1','2026/2027','Semester 1','4','0.00','16','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('74','21','KG1','2026/2027','Semester 1','4','0.00','17','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('75','28','KG1','2026/2027','Semester 1','4','0.00','18','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('76','22','KG1','2026/2027','Semester 1','4','0.00','19','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('77','24','KG1','2026/2027','Semester 1','1','0.00','20','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('78','29','KG1','2026/2027','Semester 1','1','0.00','21','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('79','11','KG2','2026/2027','Semester 1','1','0.00','1','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('80','6','KG2','2026/2027','Semester 1','1','0.00','2','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('81','7','KG2','2026/2027','Semester 1','1','0.00','3','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('82','19','KG2','2026/2027','Semester 1','1','0.00','4','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('83','5','KG2','2026/2027','Semester 1','1','0.00','5','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('84','4','KG2','2026/2027','Semester 1','1','0.00','6','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('85','26','KG2','2026/2027','Semester 1','2','0.00','7','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('86','25','KG2','2026/2027','Semester 1','2','0.00','8','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('87','18','KG2','2026/2027','Semester 1','1','0.00','9','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('88','15','KG2','2026/2027','Semester 1','1','0.00','10','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('89','10','KG2','2026/2027','Semester 1','1','0.00','11','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('90','12','KG2','2026/2027','Semester 1','1','0.00','12','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('91','23','KG2','2026/2027','Semester 1','3','0.00','13','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('92','13','KG2','2026/2027','Semester 1','1','0.00','14','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('93','24','KG2','2026/2027','Semester 1','2','0.00','15','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('94','27','KG2','2026/2027','Semester 1','10','0.00','16','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('95','28','KG2','2026/2027','Semester 1','5','0.00','17','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('96','22','KG2','2026/2027','Semester 1','4','0.00','18','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('97','29','KG2','2026/2027','Semester 1','1','0.00','19','2026-08-04 13:37:52')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('98','5','Basic 3','2025/2026','Trimester','1','0.00','1','2026-08-04 14:05:57')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('99','4','Basic 3','2025/2026','Trimester','1','0.00','2','2026-08-04 14:05:59')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('100','19','Basic 3','2025/2026','Trimester','1','0.00','3','2026-08-04 14:06:06')",
    "INSERT IGNORE INTO stationery_assignments (id, item_id, class, academic_year, semester, quantity, price, sort_order, assigned_at) VALUES ('101','29','Basic 3','2025/2026','Trimester','1','0.00','4','2026-08-04 14:06:08')",
];

$success_count = 0;
$error_count = 0;

foreach ($queries as $q) {
    if ($conn->query($q) === TRUE) {
        $success_count++;
    } else {
        $error_count++;
        echo "Error: " . $conn->error . "<br>";
    }
}

echo "<h1>Production Update Complete!</h1>";
echo "<p>Successfully executed $success_count records.</p>";
if ($error_count > 0) {
    echo "<p>Encountered $error_count errors.</p>";
}
echo "<p style='color:red; font-weight:bold;'>IMPORTANT: For security, please delete this file (update_stationery_prod.php) immediately.</p>";
echo "<a href='index.php'>Return to Dashboard</a>";
?>
