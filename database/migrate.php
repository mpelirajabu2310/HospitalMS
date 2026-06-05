<?php
/**
 * Database Migration & Installation Script
 * Run this file to set up the database for the first time.
 * Run from browser: http://localhost/HospitalMS/database/migrate.php
 * Run from CLI: php database/migrate.php
 */

$isCLI = (php_sapi_name() === "cli");

function output($msg, $type = "info") {
    global $isCLI;
    $colors = ["info" => "\033[36m", "success" => "\033[32m", "error" => "\033[31m", "warning" => "\033[33m"];
    if ($isCLI) {
        echo $colors[$type] . $msg . "\033[0m" . PHP_EOL;
    } else {
        $badges = ["info" => "primary", "success" => "success", "error" => "danger", "warning" => "warning"];
        echo '<div class="alert alert-' . $badges[$type] . '">' . htmlspecialchars($msg) . '</div>';
        ob_flush(); flush();
    }
}

if (!$isCLI) {
    echo '<!DOCTYPE html><html><head><title>HMS Installation</title>';
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">';
    echo '<style>body{padding:40px;background:#f4f6f9;font-family:sans-serif;} .container{max-width:800px;margin:0 auto;}</style></head><body>';
    echo '<div class="container"><h2 class="mb-4"><i class="fas fa-hospital-alt text-primary"></i> Hospital Management System - Installation</h2>';
}

output("Starting HMS Installation...", "info");

require_once __DIR__ . "/../config/database.php";

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";charset=" . DB_CHARSET, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    output("Connected to MySQL server.", "success");

    $sql = file_get_contents(__DIR__ . "/schema.sql");
    if (!$sql) {
        throw new Exception("Could not read schema.sql file");
    }

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    $statements = explode(";", $sql);
    $count = 0;
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement)) {
            try {
                $pdo->exec($statement);
                $count++;
            } catch (PDOException $e) {
                output("SQL Warning: " . $e->getMessage() . " (Statement: " . substr($statement, 0, 80) . "...)", "warning");
            }
        }
    }

    // Clean up old password_reset columns from users table (replaced by password_resets table)
    try {
        $columns = $pdo->query("SHOW COLUMNS FROM " . DB_NAME . ".users LIKE 'password_reset_token'")->fetchAll();
        if (!empty($columns)) {
            $pdo->exec("ALTER TABLE " . DB_NAME . ".users DROP COLUMN password_reset_token, DROP COLUMN password_reset_expires");
            output("Removed deprecated password_reset columns from users table.", "success");
        }
    } catch (Exception $e) {
        output("Note: " . $e->getMessage(), "warning");
    }

    // Add location FK columns to patients table
    try {
        $columns = $pdo->query("SHOW COLUMNS FROM " . DB_NAME . ".patients LIKE 'country_id'")->fetchAll();
        if (empty($columns)) {
            $pdo->exec("ALTER TABLE " . DB_NAME . ".patients
                ADD COLUMN country_id INT DEFAULT NULL AFTER country,
                ADD COLUMN region_id INT DEFAULT NULL AFTER country_id,
                ADD COLUMN district_id INT DEFAULT NULL AFTER region_id,
                ADD COLUMN ward_id INT DEFAULT NULL AFTER district_id,
                ADD COLUMN village_id INT DEFAULT NULL AFTER ward_id,
                ADD FOREIGN KEY fk_patients_country (country_id) REFERENCES " . DB_NAME . ".countries(id),
                ADD FOREIGN KEY fk_patients_region (region_id) REFERENCES " . DB_NAME . ".regions(id),
                ADD FOREIGN KEY fk_patients_district (district_id) REFERENCES " . DB_NAME . ".districts(id),
                ADD FOREIGN KEY fk_patients_village (village_id) REFERENCES " . DB_NAME . ".villages(id)");
            output("Added location FK columns to patients table.", "success");
        }
    } catch (Exception $e) {
        output("Note (patients location columns): " . $e->getMessage(), "warning");
    }

    // Fix location ward naming conflict: location wards vs hospital wards
    try {
        // Check if patients.ward_id incorrectly points to hospital wards
        $badPatientWardFk = $pdo->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = '" . DB_NAME . "' AND TABLE_NAME = 'patients' AND COLUMN_NAME = 'ward_id' AND REFERENCED_TABLE_NAME = 'wards'")->fetch();
        if ($badPatientWardFk) {
            $pdo->exec("ALTER TABLE " . DB_NAME . ".patients DROP FOREIGN KEY " . $badPatientWardFk['CONSTRAINT_NAME']);
            output("Dropped wrong patients.ward_id FK (pointed to hospital wards).", "success");
        }
        // Check if villages.ward_id incorrectly points to hospital wards
        $badVillageFk = $pdo->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = '" . DB_NAME . "' AND TABLE_NAME = 'villages' AND COLUMN_NAME = 'ward_id' AND REFERENCED_TABLE_NAME = 'wards'")->fetch();
        if ($badVillageFk) {
            $pdo->exec("ALTER TABLE " . DB_NAME . ".villages DROP FOREIGN KEY " . $badVillageFk['CONSTRAINT_NAME']);
            output("Dropped wrong villages.ward_id FK (pointed to hospital wards).", "success");
        }
        // Ensure location_wards table exists
        $locWardsExists = $pdo->query("SHOW TABLES IN " . DB_NAME . " LIKE 'location_wards'")->fetchAll();
        if (empty($locWardsExists)) {
            $pdo->exec("CREATE TABLE location_wards (
                id INT PRIMARY KEY AUTO_INCREMENT,
                district_id INT NOT NULL,
                name VARCHAR(100) NOT NULL,
                code VARCHAR(20),
                status ENUM('active','inactive') DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (district_id) REFERENCES " . DB_NAME . ".districts(id) ON DELETE CASCADE,
                INDEX idx_location_wards_district (district_id)
            ) ENGINE=InnoDB");
            output("Created location_wards table.", "success");
        }
        // Re-create villages FK pointing to location_wards (if missing or was just dropped)
        $villagesFkCheck = $pdo->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = '" . DB_NAME . "' AND TABLE_NAME = 'villages' AND COLUMN_NAME = 'ward_id' AND REFERENCED_TABLE_NAME = 'location_wards'")->fetch();
        if (!$villagesFkCheck) {
            $pdo->exec("ALTER TABLE " . DB_NAME . ".villages ADD FOREIGN KEY fk_villages_location_ward (ward_id) REFERENCES " . DB_NAME . ".location_wards(id) ON DELETE CASCADE");
            output("Re-created villages FK pointing to location_wards.", "success");
        }
        // Re-create patients FK for ward_id pointing to location_wards (if missing or was just dropped)
        $patientLocationWardFk = $pdo->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = '" . DB_NAME . "' AND TABLE_NAME = 'patients' AND COLUMN_NAME = 'ward_id' AND REFERENCED_TABLE_NAME = 'location_wards'")->fetch();
        if (!$patientLocationWardFk) {
            $pdo->exec("ALTER TABLE " . DB_NAME . ".patients ADD FOREIGN KEY fk_patients_location_ward (ward_id) REFERENCES " . DB_NAME . ".location_wards(id)");
            output("Re-created patients ward_id FK pointing to location_wards.", "success");
        }
    } catch (Exception $e) {
        output("Note (location_wards fix): " . $e->getMessage(), "warning");
    }

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    output("Database schema created successfully ($count statements executed).", "success");

    $dbPdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $check = $dbPdo->query("SELECT COUNT(*) as count FROM information_schema.TABLES WHERE TABLE_SCHEMA = " . $dbPdo->quote(DB_NAME))->fetch();
    output("Total tables created: " . $check["count"], "success");

    $indexCount = $dbPdo->query("SELECT COUNT(*) as count FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = " . $dbPdo->quote(DB_NAME))->fetch();
    output("Total indexes created: " . $indexCount["count"], "success");

    $userCount = $dbPdo->query("SELECT COUNT(*) as count FROM users")->fetch();
    if ($userCount["count"] == 0) {
        output("No users found. The first person to register will become Super Admin.", "info");
    } else {
        output("Existing users: " . $userCount["count"] . " (Super Admin already exists)", "success");
    }

    output("", "info");
    output("============================================", "success");
    output("INSTALLATION COMPLETED SUCCESSFULLY!", "success");
    output("============================================", "success");
    output("", "info");
    output("Next steps:", "info");
    output("1. Visit http://localhost/HospitalMS/ to access the system", "info");
    output("2. If no users exist, click 'Create First User' on the login page to register as Super Admin", "info");
    output("3. After registering, log in and start managing the system", "info");

} catch (Exception $e) {
    output("Installation failed: " . $e->getMessage(), "error");
}

if (!$isCLI) {
    echo '<div class="mt-4"><a href="../index.php" class="btn btn-primary">Go to Login Page</a></div>';
    echo '</div></body></html>';
}
