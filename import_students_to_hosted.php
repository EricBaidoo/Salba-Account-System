<?php
/**
 * Student Data Migration Script for Hosted Database
 * This script will import all 123 students from your local database to the hosted system
 */

// HOSTED DATABASE CONNECTION SETTINGS
// *** IMPORTANT: Update these with your Hostinger database credentials ***
$host = 'localhost';  // Usually something like 'localhost' or 'mysql.hostinger.com'
$username = 'u420775839_salba_admin';
$password = 'Eric0056@2024';
$database = 'u420775839_salba_acc';

echo "=== STUDENT DATA MIGRATION SCRIPT ===\n";
echo "This will import 123 students to your hosted database\n\n";

try {
    // Connect to hosted database
    $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ Connected to hosted database successfully!\n\n";
    
    // Check current student count
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM students");
    $currentCount = $stmt->fetch()['count'];
    echo "Current students in hosted database: $currentCount\n\n";
    
    // Student data from your local database
    $students = [
        [1,'FIRDAUS','ABDALLAH','Basic 4',NULL,NULL,'2025-09-29 01:46:08','active'],
        [2,'TIPAYA','ABDALLAH MOHAMMED','Basic 1',NULL,NULL,'2025-09-29 01:46:08','active'],
        [3,'MARIAM','ABDALLA','Basic 6',NULL,NULL,'2025-09-29 01:46:08','active'],
        [4,'RAHMAN','ABDUL','KG 2',NULL,NULL,'2025-09-29 01:46:08','active'],
        [5,'JANELLE','ACHEAMPONGMAA ASIEDU','KG 1',NULL,NULL,'2025-09-29 01:46:08','active'],
        [6,'BRIAN','ADAMS','KG 2',NULL,NULL,'2025-09-29 01:46:08','active'],
        [7,'JESHURUN','ADAMS','NURSERY 2',NULL,NULL,'2025-09-29 01:46:08','active'],
        [8,'OSAE','ADIEPENA ANUMWAA','KG 1',NULL,NULL,'2025-09-29 01:46:08','active'],
        [9,'DAVID','ADOBOE EYIRAM','Basic 2',NULL,NULL,'2025-09-29 01:46:08','active'],
        [10,'RANSFORD','ADOBOE WOMA','Basic 5',NULL,NULL,'2025-09-29 01:46:08','active'],
        [11,'CHEALSEA','AGYAPONG','Basic 3',NULL,NULL,'2025-09-29 01:46:08','active'],
        [12,'GLENDER','AGYAPONG','KG 2',NULL,NULL,'2025-09-29 01:46:08','active'],
        [13,'ASAFO','AGYEI DONALD','Basic 1',NULL,NULL,'2025-09-29 01:46:08','active'],
        [14,'ASAFO','AGYEI GERALD','Basic 3',NULL,NULL,'2025-09-29 01:46:08','active'],
        [15,'CHRISTIAN','AKITAH K','Basic 2',NULL,NULL,'2025-09-29 01:46:08','active'],
        [16,'JEREMY','ALOAYE','KG 2',NULL,NULL,'2025-09-29 01:46:08','active'],
        [17,'TESTIMONY','ALOAYE','Basic 4',NULL,NULL,'2025-09-29 01:46:08','active'],
        [18,'CANDACE','AMANKWAAH YAA MENSAH','KG 1',NULL,NULL,'2025-09-29 01:46:08','active'],
        [19,'MICALY','AMANKWAH OWUSU GYAMFUAH','NURSERY 2',NULL,NULL,'2025-09-29 01:46:08','active'],
        [20,'ADEPA','AMEYAW ESI','CRECHE',NULL,NULL,'2025-09-29 01:46:08','active'],
        [21,'AMA','AMEYAW NANA','Basic 2',NULL,NULL,'2025-09-29 01:46:08','active'],
        [22,'NUTIFAFA','AMEYAW','KG 2',NULL,NULL,'2025-09-29 01:46:08','active'],
        [23,'AFIA','AMOAKOA','Basic 1',NULL,NULL,'2025-09-29 01:46:08','active'],
        [24,'PRINCE','ANGEL ANIM','Basic 2',NULL,NULL,'2025-09-29 01:46:08','active'],
        [25,'RAYMOND','ANKRAH','KG 1',NULL,NULL,'2025-09-29 01:46:08','active'],
        [26,'LIZABETH','APHRIPHA','Creche',NULL,NULL,'2025-09-29 01:46:08','active'],
        [27,'JAHAZIEL','APPIAH','KG 2',NULL,NULL,'2025-09-29 01:46:08','active'],
        [28,'JOCHEBED','APPIAH','Basic 4',NULL,NULL,'2025-09-29 01:46:08','active'],
        [29,'MORIAH','APPIAH','NURSERY 2',NULL,NULL,'2025-09-29 01:46:08','active'],
        [32,'OTHNIELTA','APPIAH','Basic 7',NULL,NULL,'2025-09-29 01:46:08','active'],
        [33,'MAJOLA','ARKHURST KLENAM SAWYERR','NURSERY 1',NULL,NULL,'2025-09-29 01:46:08','active'],
        [34,'MAJOLA','ARKHURST KLENAM SAWYERR','NURSERY 1',NULL,NULL,'2025-09-29 01:46:08','active'],
        [35,'FOH','ARKHURST KWEKU','KG 1',NULL,NULL,'2025-09-29 01:46:08','active'],
        [36,'AMOBIA','ARKHURST NAA','Basic 4',NULL,NULL,'2025-09-29 01:46:08','active'],
        [37,'ABA','ARKOH ADELAIDE MAMAE','KG 1',NULL,NULL,'2025-09-29 01:46:08','active'],
        [39,'MIRABEL','ARMAH','Basic 1',NULL,NULL,'2025-09-29 01:46:08','active'],
        [40,'AMELEY','ARMAH THEODOSIAH NAA MIISHEE','Basic 6',NULL,NULL,'2025-09-29 01:46:08','active'],
        [41,'ARYEE','ARYEEQUAYE ABDUL SALEEM NII','Basic 2',NULL,NULL,'2025-09-29 01:46:08','active'],
        [42,'DEDE','ARMAH','CRECHE',NULL,NULL,'2025-09-29 01:46:08','active'],
        [43,'ARMAH','ARYEEQUAYE DAWUD','NURSERY 2',NULL,NULL,'2025-09-29 01:46:08','active'],
        [44,'JULIOUS','ARYEETEY','Basic 5',NULL,NULL,'2025-09-29 01:46:08','active'],
        [46,'REINA','ASAMOAH YEBOAH MAXWELLA','NURSERY 2',NULL,NULL,'2025-09-29 01:46:08','active'],
        [47,'DEANGIL','ASIRIFI','Basic 6',NULL,NULL,'2025-09-29 01:46:08','active'],
        [48,'JULIET','AWUAH','Basic 4',NULL,NULL,'2025-09-29 01:46:08','active'],
        [49,'ARKOH','BAAH MANUEL AFRIYIE','KG 1',NULL,NULL,'2025-09-29 01:46:08','active'],
        [50,'EDGARDO','BAIDOO','NURSERY 1',NULL,NULL,'2025-09-29 01:46:08','active'],
        [51,'ENYAMA','BANNIE GODSGIFT NANA','Basic 2',NULL,NULL,'2025-09-29 01:46:08','active'],
        [52,'SAMAABA','BANNIE GODSWILL NANA','Basic 5',NULL,NULL,'2025-09-29 01:46:08','active'],
        [53,'MARVIN','BEDZRAH','KG 1',NULL,NULL,'2025-09-29 01:46:08','active'],
        [54,'SIAW','BEMPOE EMMANUEL','KG 2',NULL,NULL,'2025-09-29 01:46:08','active'],
        [55,'ARIEL','BENTIL','NURSERY 2',NULL,NULL,'2025-09-29 01:46:08','active'],
        [56,'FOSUAA','BENTIL CHARISMA','Basic 2',NULL,NULL,'2025-09-29 01:46:08','active'],
        [57,'ABAYAA','BERMUDEZ','KG 2',NULL,NULL,'2025-09-29 01:46:08','active'],
        [58,'RAFAEL','BERMUDEZ','Basic 2',NULL,NULL,'2025-09-29 01:46:08','active'],
        [59,'GEORGINA','BLACKWELL OBENG','KG 2',NULL,NULL,'2025-09-29 01:46:08','active'],
        [60,'ALVIN','BOATENG','Basic 1',NULL,NULL,'2025-09-29 01:46:08','active'],
        [61,'NANA','BOATENG JETHRO','KG 2',NULL,NULL,'2025-09-29 01:46:08','active'],
        [62,'AFRIRYIE','BOATENG PRINCE','Basic 6',NULL,NULL,'2025-09-29 01:46:08','active'],
        [63,'BLESSING','DANSO','KG 1',NULL,NULL,'2025-09-29 01:46:08','active'],
        [64,'KYEKYEKU','DAPAAH ZION OPPONG','KG 1',NULL,NULL,'2025-09-29 01:46:08','active'],
        [65,'JESSE','DOE','KG 2',NULL,NULL,'2025-09-29 01:46:08','active'],
        [66,'JOEL','DOTSE','CRECHE',NULL,NULL,'2025-09-29 01:46:08','active'],
        [67,'NANA','FILSON EKOW','KG 2',NULL,NULL,'2025-09-29 01:46:08','active'],
        [68,'EMERALDIA','FILSON','NURSERY 2',NULL,NULL,'2025-09-29 01:46:08','active'],
        [69,'OSEI','FRIMPONG JADEN','NURSERY 1',NULL,NULL,'2025-09-29 01:46:08','active'],
        [70,'ELIANA','GAVI AMENUVEVE','Nursery 1',NULL,NULL,'2025-09-29 01:46:08','inactive'],
        [71,'JEFFREY','HINI','KG 2',NULL,NULL,'2025-09-29 01:46:08','active'],
        [72,'AGYEMANG','KODUAH YOUNGMONEY','KG 1',NULL,NULL,'2025-09-29 01:46:08','active'],
        [73,'MAWUENA','KPORMEGBEY RYAN KOJO','Creche',NULL,NULL,'2025-09-29 01:46:08','active'],
        [74,'KWAME','KWAANSAH NANA','Basic 7',NULL,NULL,'2025-09-29 01:46:08','active'],
        [75,'ADELA','KWOFIE BRIANNA NANA','NURSERY 1',NULL,NULL,'2025-09-29 01:46:08','active'],
        [76,'BROOKLYN','KYEI LOLA AKOSUA','Basic 2',NULL,NULL,'2025-09-29 01:46:08','inactive'],
        [77,'TAWFIQ','MAHMUD DREAM RYAN','Basic 1',NULL,NULL,'2025-09-29 01:46:08','active'],
        [79,'JAYSON','MANTE-SARFO','Basic 6',NULL,NULL,'2025-09-29 01:46:08','inactive'],
        [80,'JOYCE','MENSAH','NURSERY 1',NULL,NULL,'2025-09-29 01:46:08','active'],
        [81,'JULIOUS','MENSAH','Basic 1',NULL,NULL,'2025-09-29 01:46:08','active'],
        [82,'OPARE','MINTAH CHLOE','Basic 4',NULL,NULL,'2025-09-29 01:46:08','active'],
        [83,'OPARE','MINTAH ETHEN','Basic 2',NULL,NULL,'2025-09-29 01:46:08','active'],
        [84,'OPARE','MINTAH SABASTIAN','Basic 6',NULL,NULL,'2025-09-29 01:46:08','active'],
        [85,'ANAT','MOHAMMAD','NURSERY 2',NULL,NULL,'2025-09-29 01:46:08','active'],
        [86,'RAHEEMA','MOHAMMED','Basic 1',NULL,NULL,'2025-09-29 01:46:08','active'],
        [87,'GABRIELLA','NIZER','Basic 6',NULL,NULL,'2025-09-29 01:46:08','inactive'],
        [88,'JIBRIL','NIZER JANAT','NURSERY 2',NULL,NULL,'2025-09-29 01:46:08','inactive'],
        [89,'DANIELLA','NYARKO','Basic 1',NULL,NULL,'2025-09-29 01:46:08','active'],
        [90,'SAMUEL','NYARKO','KG 1',NULL,NULL,'2025-09-29 01:46:08','active'],
        [91,'LAMPTEY','ODARTEI HARRISSON NII','KG 1',NULL,NULL,'2025-09-29 01:46:08','active'],
        [92,'MONDAY','ODUM','Basic 4',NULL,NULL,'2025-09-29 01:46:08','active'],
        [93,'PRECIOUS','ODUM','KG 2',NULL,NULL,'2025-09-29 01:46:08','active'],
        [94,'NHYIRA','OFORI DONATELLA','KG 2',NULL,NULL,'2025-09-29 01:46:08','active'],
        [95,'SAMUEL','OFOSU','KG 1',NULL,NULL,'2025-09-29 01:46:08','active'],
        [96,'ELIANA','OPPONG BOADUWAA','KG 1',NULL,NULL,'2025-09-29 01:46:08','active'],
        [97,'JAYDEN','OSEI QUAYE','Basic 4',NULL,NULL,'2025-09-29 01:46:08','active'],
        [98,'NYAMEKYE','OTOO JAIDAH','NURSERY 1',NULL,NULL,'2025-09-29 01:46:08','active'],
        [99,'OKAINSIE','OTUMFUO MARY','KG 1',NULL,NULL,'2025-09-29 01:46:08','active'],
        [100,'ADOM','TETTEH GODWIN','Basic 5',NULL,NULL,'2025-09-29 01:46:08','active'],
        [101,'MAXWELL','TETTEH','Basic 2',NULL,NULL,'2025-09-29 01:46:08','active'],
        [102,'RAYMOND','TETTEH','NURSERY 1',NULL,NULL,'2025-09-29 01:46:08','active'],
        [103,'TRUDILOVE','TETTEH','Basic 4',NULL,NULL,'2025-09-29 01:46:08','active'],
        [104,'BLESSING','THOMAS-SAM','Basic 5',NULL,NULL,'2025-09-29 01:46:08','active'],
        [105,'MERCY','THOMAS-SAM','Basic 5',NULL,NULL,'2025-09-29 01:46:08','active'],
        [106,'VAVA','TSIDI VALERIE','Basic 6',NULL,NULL,'2025-09-29 01:46:08','active'],
        [107,'DANSO','YAMOAH QUEENSTER ABENA','NURSERY 2',NULL,NULL,'2025-09-29 01:46:08','active'],
        [108,'MALTITI','AISHA','NURSERY 2',NULL,NULL,'2025-09-29 01:46:08','active'],
        [109,'DAVID','ABANGAH','NURSERY 1',NULL,NULL,'2025-09-29 01:46:08','active'],
        [110,'CHRISTODIA','OTOO MERYGOLD','CRECHE',NULL,NULL,'2025-09-29 01:46:08','active'],
        [111,'OKATAKYIE','OSAE','CRECHE',NULL,NULL,'2025-09-29 01:46:08','active'],
        [112,'TEIKO','TAGOE JYLAN NII','CRECHE',NULL,NULL,'2025-09-29 01:46:08','active'],
        [113,'APPIAH','KELVIN','Basic 2',NULL,NULL,'2025-09-29 01:46:08','active'],
        [114,'BOATENG','YEBOAH KESTER','KG 2',NULL,NULL,'2025-09-29 01:46:08','active'],
        [115,'RAHMAN','ABDUL','Basic 1',NULL,NULL,'2025-09-30 04:50:03','active'],
        [116,'ABLORDE GERHARD KEKELI WOLARNYO YAW','ABLORDE','Basic 4',NULL,NULL,'2025-09-30 14:57:46','active'],
        [117,'GERHARDINE KLENAM ESI','ABLORDE','Basic 5',NULL,NULL,'2025-09-30 14:58:19','active'],
        [118,'RYAN','AWUDE','KG 2',NULL,NULL,'2025-09-30 14:59:09','active'],
        [119,'ELSIE','ADOBOE','Creche',NULL,NULL,'2025-09-30 14:59:37','active'],
        [120,'AUSTIN','NANA WIAFE BROWN','Creche',NULL,NULL,'2025-09-30 15:00:12','active'],
        [121,'AASIYA NAANA','JAFAR','Creche',NULL,NULL,'2025-09-30 15:00:58','active'],
        [122,'MATILDA','ABOGLE','Basic 7',NULL,NULL,'2025-09-30 15:01:43','active'],
        [123,'YVONNE NUNOO','ADDAI','Nursery 1',NULL,NULL,'2025-09-30 16:09:38','active']
    ];
    
    // Prepare insert statement (excluding id to let auto-increment work)
    $stmt = $pdo->prepare("INSERT INTO students (first_name, last_name, class, date_of_birth, parent_contact, created_at, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    $successCount = 0;
    $errorCount = 0;
    
    echo "Starting migration...\n";
    echo "==================\n\n";
    
    foreach ($students as $index => $student) {
        // Skip the original ID (index 0), use the rest
        $first_name = $student[1];
        $last_name = $student[2];
        $class = $student[3];
        $date_of_birth = $student[4]; // NULL
        $parent_contact = $student[5]; // NULL
        $created_at = $student[6];
        $status = $student[7];
        
        try {
            $stmt->execute([$first_name, $last_name, $class, $date_of_birth, $parent_contact, $created_at, $status]);
            $successCount++;
            
            if ($successCount % 10 == 0) {
                echo "✅ Migrated $successCount students...\n";
            }
            
        } catch (PDOException $e) {
            $errorCount++;
            echo "❌ Error migrating student: $first_name $last_name - " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n=== MIGRATION COMPLETE ===\n";
    echo "✅ Successfully migrated: $successCount students\n";
    echo "❌ Errors: $errorCount\n";
    
    // Final count check
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM students");
    $finalCount = $stmt->fetch()['count'];
    echo "📊 Total students in hosted database: $finalCount\n\n";
    
    echo "🎉 Migration completed! Your students are now in the hosted system.\n";
    echo "You can now access your hosted accounting system and see all students.\n\n";
    
    // Show class distribution
    echo "📋 Students by Class:\n";
    echo "====================\n";
    $stmt = $pdo->query("SELECT class, COUNT(*) as count FROM students GROUP BY class ORDER BY class");
    while ($row = $stmt->fetch()) {
        echo sprintf("%-15s: %d students\n", $row['class'], $row['count']);
    }
    
} catch (PDOException $e) {
    echo "❌ Database Error: " . $e->getMessage() . "\n\n";
    echo "Please check your database connection settings:\n";
    echo "- Host: $host\n";
    echo "- Username: $username\n";
    echo "- Database: $database\n\n";
    echo "Make sure these match your Hostinger database credentials.\n";
}
?>