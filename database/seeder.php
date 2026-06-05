<?php
/**
 * Database Seeder - Creates demo data for testing
 * Run: php database/seeder.php
 */

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../includes/db.php";

echo "Starting HMS Database Seeder...\n";

try {
    $db = Database::getInstance()->getConnection();

    // Check if location data already exists (seed regardless of user data)
    $tzCheck = Database::fetch("SELECT id FROM countries WHERE code = 'TZ' LIMIT 1");
    if (!$tzCheck) {
        echo "Creating Tanzania location data...\n";
        Database::insert("INSERT INTO countries (name, code, phone_code, currency_code, status) VALUES (?, ?, ?, ?, 'active')", ['Tanzania', 'TZ', '+255', 'TZS']);
        $tzId = Database::getInstance()->getConnection()->lastInsertId();

        $regionData = [
            ['Arusha', '01'], ['Dar es Salaam', '02'], ['Dodoma', '03'], ['Geita', '04'],
            ['Iringa', '05'], ['Kagera', '06'], ['Katavi', '07'], ['Kigoma', '08'],
            ['Kilimanjaro', '09'], ['Lindi', '10'], ['Manyara', '11'], ['Mara', '12'],
            ['Mbeya', '13'], ['Morogoro', '14'], ['Mtwara', '15'], ['Mwanza', '16'],
            ['Njombe', '17'], ['Pemba North', '18'], ['Pemba South', '19'], ['Pwani', '20'],
            ['Rukwa', '21'], ['Ruvuma', '22'], ['Shinyanga', '23'], ['Simiyu', '24'],
            ['Singida', '25'], ['Songwe', '26'], ['Tabora', '27'], ['Tanga', '28'],
            ['Zanzibar Central/South', '29'], ['Zanzibar North', '30'], ['Zanzibar South', '31'],
        ];
        $regionIds = [];
        foreach ($regionData as $r) {
            Database::insert("INSERT INTO regions (country_id, name, code, status) VALUES (?, ?, ?, 'active')", [$tzId, $r[0], $r[1]]);
            $regionIds[$r[0]] = Database::getInstance()->getConnection()->lastInsertId();
        }

        $districtData = [
            ['Arusha City', 'Arusha'], ['Arusha DC', 'Arusha'], ['Meru', 'Arusha'], ['Karatu', 'Arusha'], ['Longido', 'Arusha'],
            ['Ilala', 'Dar es Salaam'], ['Kinondoni', 'Dar es Salaam'], ['Ubungo', 'Dar es Salaam'], ['Temeke', 'Dar es Salaam'], ['Kigamboni', 'Dar es Salaam'],
            ['Dodoma City', 'Dodoma'], ['Kondoa', 'Dodoma'], ['Mpwapwa', 'Dodoma'], ['Kongwa', 'Dodoma'], ['Chemba', 'Dodoma'],
            ['Geita Town', 'Geita'], ['Bukombe', 'Geita'], ['Chato', 'Geita'],
            ['Iringa City', 'Iringa'], ['Iringa DC', 'Iringa'], ['Kilolo', 'Iringa'], ['Mafinga Town', 'Iringa'],
            ['Bukoba Urban', 'Kagera'], ['Bukoba DC', 'Kagera'], ['Karagwe', 'Kagera'], ['Muleba', 'Kagera'],
            ['Mpanda', 'Katavi'], ['Mlele', 'Katavi'], ['Kigoma Urban', 'Kigoma'], ['Kigoma DC', 'Kigoma'], ['Uvinza', 'Kigoma'],
            ['Moshi City', 'Kilimanjaro'], ['Moshi DC', 'Kilimanjaro'], ['Hai', 'Kilimanjaro'], ['Same', 'Kilimanjaro'], ['Rombo', 'Kilimanjaro'],
            ['Lindi Urban', 'Lindi'], ['Lindi DC', 'Lindi'], ['Nachingwea', 'Lindi'], ['Kilwa', 'Lindi'],
            ['Babati Town', 'Manyara'], ['Babati DC', 'Manyara'], ['Mbulu', 'Manyara'], ['Hanang', 'Manyara'],
            ['Musoma Urban', 'Mara'], ['Musoma DC', 'Mara'], ['Tarime', 'Mara'], ['Bunda', 'Mara'],
            ['Mbeya City', 'Mbeya'], ['Mbeya DC', 'Mbeya'], ['Mbarali', 'Mbeya'], ['Rungwe', 'Mbeya'], ['Kyela', 'Mbeya'],
            ['Morogoro Urban', 'Morogoro'], ['Morogoro DC', 'Morogoro'], ['Kilosa', 'Morogoro'], ['Ulanga', 'Morogoro'],
            ['Mtwara Urban', 'Mtwara'], ['Mtwara DC', 'Mtwara'], ['Masasi', 'Mtwara'], ['Newala', 'Mtwara'],
            ['Mwanza City', 'Mwanza'], ['Nyamagana', 'Mwanza'], ['Ilemela', 'Mwanza'], ['Magu', 'Mwanza'],
            ['Njombe Town', 'Njombe'], ['Njombe DC', 'Njombe'], ['Makete', 'Njombe'], ['Ludewa', 'Njombe'],
            ['Wete', 'Pemba North'], ['Chake Chake', 'Pemba South'], ['Kibaha', 'Pwani'], ['Kisarawe', 'Pwani'], ['Mkuranga', 'Pwani'], ['Rufiji', 'Pwani'],
            ['Sumbawanga', 'Rukwa'], ['Nkasi', 'Rukwa'], ['Songea Urban', 'Ruvuma'], ['Songea DC', 'Ruvuma'], ['Mbinga', 'Ruvuma'],
            ['Shinyanga Urban', 'Shinyanga'], ['Shinyanga DC', 'Shinyanga'], ['Kahama Town', 'Shinyanga'],
            ['Bariadi', 'Simiyu'], ['Maswa', 'Simiyu'], ['Meatu', 'Simiyu'],
            ['Singida Urban', 'Singida'], ['Singida DC', 'Singida'], ['Manyoni', 'Singida'],
            ['Songwe', 'Songwe'], ['Tunduma', 'Songwe'], ['Tabora Urban', 'Tabora'], ['Tabora DC', 'Tabora'], ['Nzega', 'Tabora'],
            ['Tanga City', 'Tanga'], ['Tanga DC', 'Tanga'], ['Muheza', 'Tanga'], ['Korogwe', 'Tanga'], ['Lushoto', 'Tanga'],
            ['Mjini', 'Zanzibar Central/South'], ['Magharibi', 'Zanzibar Central/South'],
            ['Kaskazini A', 'Zanzibar North'], ['Kaskazini B', 'Zanzibar North'],
            ['Kusini', 'Zanzibar South'], ['Mkoani', 'Zanzibar South'],
        ];
        $districtIds = [];
        foreach ($districtData as $d) {
            Database::insert("INSERT INTO districts (region_id, name, status) VALUES (?, ?, 'active')", [$regionIds[$d[1]], $d[0]]);
            $districtIds[$d[0]] = Database::getInstance()->getConnection()->lastInsertId();
        }

        $wardData = [
            ['Lemara', 'Arusha City'], ['Sokoni I', 'Arusha City'], ['Kati', 'Arusha City'],
            ['Kariakoo', 'Ilala'], ['Gerezani', 'Ilala'], ['Mchafukoge', 'Ilala'],
            ['Mikocheni', 'Kinondoni'], ['Msasani', 'Kinondoni'], ['Kawe', 'Kinondoni'],
            ['Majengo', 'Temeke'], ['Keko', 'Temeke'], ['Miburani', 'Temeke'],
            ['Kijitonyama', 'Ubungo'], ['Sinza', 'Ubungo'], ['Ubungo', 'Ubungo'],
            ['Kisutu', 'Ilala'], ['Jangwani', 'Ilala'], ['Kivukoni', 'Ilala'],
            ['Njiro', 'Arusha City'], ['Sekei', 'Arusha City'], ['Themi', 'Arusha City'],
            ['Mwembesongo', 'Moshi City'], ['Njoro', 'Moshi City'], ['Mji Mpya', 'Moshi City'],
            ['Mwanjelwa', 'Mbeya City'], ['Iringa Road', 'Mbeya City'], ['Ruanda', 'Mbeya City'],
            ['Mabatini', 'Dodoma City'], ['Majengo', 'Dodoma City'], ['Kizota', 'Dodoma City'],
        ];
        foreach ($wardData as $w) {
            Database::insert("INSERT INTO location_wards (district_id, name, status) VALUES (?, ?, 'active')", [$districtIds[$w[1]], $w[0]]);
        }
        echo "Created Tanzania: 1 country, " . count($regionData) . " regions, " . count($districtData) . " districts, " . count($wardData) . " wards.\n";

        // Update default country setting
        Database::query("UPDATE settings SET `value` = ? WHERE `key` = 'default_country_id'", [$tzId]);
    } else {
        echo "Tanzania location data already exists.\n";
    }

    // Check if user data already exists
    $userCount = Database::fetch("SELECT COUNT(*) as c FROM users");
    if ($userCount["c"] > 0) {
        echo "Users already exist. Skipping demo data.\n";
        echo "Run 'TRUNCATE users;' etc. first if you want to re-seed demo data.\n";
        exit;
    }

    // 1. Create roles
    echo "Creating roles...\n";
    $roles = [
        ["super_admin", "Super Admin", "System Super Administrator"],
        ["admin", "Administrator", "System Administrator"],
        ["doctor", "Doctor", "Medical Doctor"],
        ["nurse", "Nurse", "Registered Nurse"],
        ["receptionist", "Receptionist", "Front Desk Receptionist"],
        ["pharmacist", "Pharmacist", "Pharmacist"],
        ["lab_technician", "Lab Technician", "Laboratory Technician"],
        ["cashier", "Cashier", "Billing Officer"],
        ["records_officer", "Records Officer", "Medical Records Officer"],
    ];
    $roleIds = [];
    foreach ($roles as $r) {
        Database::insert("INSERT INTO roles (name, display_name, description, is_system) VALUES (?, ?, ?, 1)", $r);
        $roleIds[$r[0]] = Database::getInstance()->getConnection()->lastInsertId();
    }
    echo "Created " . count($roles) . " roles.\n";

    // 2. Create permissions
    echo "Creating permissions...\n";
    $permissions = [
        ["manage_users", "Manage Users", "admin"],
        ["manage_roles", "Manage Roles", "admin"],
        ["manage_permissions", "Manage Permissions", "admin"],
        ["manage_departments", "Manage Departments", "admin"],
        ["manage_settings", "Manage Settings", "admin"],
        ["view_reports", "View Reports", "reports"],
        ["manage_patients", "Manage Patients", "patients"],
        ["view_patients", "View Patients", "patients"],
        ["manage_appointments", "Manage Appointments", "appointments"],
        ["manage_visits", "Manage Visits", "visits"],
        ["manage_consultations", "Manage Consultations", "consultations"],
        ["manage_prescriptions", "Manage Prescriptions", "prescriptions"],
        ["manage_medicines", "Manage Medicines", "pharmacy"],
        ["dispense_medicines", "Dispense Medicines", "pharmacy"],
        ["manage_lab_tests", "Manage Lab Tests", "laboratory"],
        ["perform_lab_tests", "Perform Lab Tests", "laboratory"],
        ["manage_invoices", "Manage Invoices", "billing"],
        ["process_payments", "Process Payments", "billing"],
        ["manage_admissions", "Manage Admissions", "admissions"],
        ["manage_wards", "Manage Wards", "wards"],
        ["manage_beds", "Manage Beds", "wards"],
        ["manage_referrals", "Manage Referrals", "referrals"],
        ["view_audit_logs", "View Audit Logs", "admin"],
        ["manage_nursing", "Manage Nursing", "nursing"],
        ["manage_medical_records", "Manage Medical Records", "records"],
    ];
    $permIds = [];
    foreach ($permissions as $p) {
        Database::insert("INSERT INTO permissions (name, display_name, module) VALUES (?, ?, ?)", $p);
        $permIds[$p[0]] = Database::getInstance()->getConnection()->lastInsertId();
    }
    echo "Created " . count($permissions) . " permissions.\n";

    // 3. Assign all permissions to super_admin
    echo "Assigning permissions to Super Admin...\n";
    foreach ($permIds as $permId) {
        Database::query("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)", [$roleIds["super_admin"], $permId]);
    }

    // Assign some permissions to other roles
    $rolePerms = [
        "doctor" => ["manage_consultations", "manage_prescriptions", "view_patients", "manage_referrals", "manage_lab_tests"],
        "nurse" => ["manage_nursing", "view_patients"],
        "receptionist" => ["manage_appointments", "manage_visits", "manage_patients"],
        "pharmacist" => ["manage_medicines", "dispense_medicines"],
        "lab_technician" => ["manage_lab_tests", "perform_lab_tests"],
        "cashier" => ["manage_invoices", "process_payments"],
        "records_officer" => ["manage_medical_records"],
    ];
    foreach ($rolePerms as $role => $perms) {
        foreach ($perms as $perm) {
            Database::query("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)", [$roleIds[$role], $permIds[$perm]]);
        }
    }

    // 4. Create departments
    echo "Creating departments...\n";
    $deptData = [
        ["Reception", "REC", "Front Desk & Patient Registration"],
        ["Consultation", "CON", "Medical Consultation & Doctor Services"],
        ["Laboratory", "LAB", "Diagnostic Laboratory Services"],
        ["Pharmacy", "PHA", "Medicine Dispensing & Pharmacy Services"],
        ["Billing", "BIL", "Billing & Cashier Services"],
        ["Nursing", "NUR", "Nursing Care Services"],
        ["Admission", "ADM", "Patient Admission Services"],
        ["Ward Management", "WRD", "Ward & Bed Management"],
        ["Records", "RCD", "Medical Records Management"],
        ["Administration", "ADM", "Hospital Administration"],
    ];
    $deptIds = [];
    foreach ($deptData as $d) {
        Database::insert("INSERT INTO departments (name, code, description, status) VALUES (?, ?, ?, 'active')", $d);
        $deptIds[$d[1]] = Database::getInstance()->getConnection()->lastInsertId();
    }
    echo "Created " . count($deptData) . " departments.\n";

    // 5. Create users
    echo "Creating users...\n";
    $users = [
        ["superadmin", "admin@hospital.com", "password123", "Super", "Admin", "0712000000", "super_admin", null],
        ["drkamau", "dr.kamau@hospital.com", "password123", "James", "Kamau", "0712000001", "doctor", "CON"],
        ["drmuthoni", "dr.muthoni@hospital.com", "password123", "Grace", "Muthoni", "0712000002", "doctor", "CON"],
        ["nursewanjiku", "nurse.wanjiku@hospital.com", "password123", "Mary", "Wanjiku", "0712000003", "nurse", "NUR"],
        ["receptionjuma", "reception@hospital.com", "password123", "John", "Juma", "0712000004", "receptionist", "REC"],
        ["pharmakip", "pharmacy@hospital.com", "password123", "Hassan", "Kiprop", "0712000005", "pharmacist", "PHA"],
        ["labchege", "lab@hospital.com", "password123", "Faith", "Chege", "0712000006", "lab_technician", "LAB"],
        ["cashiernyaga", "cashier@hospital.com", "password123", "Peter", "Nyaga", "0712000007", "cashier", "BIL"],
        ["recordskimani", "records@hospital.com", "password123", "Sarah", "Kimani", "0712000008", "records_officer", "RCD"],
    ];
    $userIds = [];
    foreach ($users as $u) {
        $deptId = $u[5] ? $deptIds[$u[5]] ?? null : null;
        Database::insert(
            "INSERT INTO users (username, email, password, first_name, last_name, phone, role_id, department_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')",
            [$u[0], $u[1], password_hash($u[2], PASSWORD_BCRYPT), $u[3], $u[4], $u[5], $roleIds[$u[6]], $deptId]
        );
        $userIds[$u[0]] = Database::getInstance()->getConnection()->lastInsertId();
    }

    // Update department heads
    Database::query("UPDATE departments SET head_user_id = ? WHERE code = 'CON'", [$userIds["drkamau"]]);
    Database::query("UPDATE departments SET head_user_id = ? WHERE code = 'NUR'", [$userIds["nursewanjiku"]]);
    Database::query("UPDATE departments SET head_user_id = ? WHERE code = 'REC'", [$userIds["receptionjuma"]]);
    Database::query("UPDATE departments SET head_user_id = ? WHERE code = 'PHA'", [$userIds["pharmakip"]]);
    Database::query("UPDATE departments SET head_user_id = ? WHERE code = 'LAB'", [$userIds["labchege"]]);
    Database::query("UPDATE departments SET head_user_id = ? WHERE code = 'BIL'", [$userIds["cashiernyaga"]]);

    echo "Created " . count($users) . " users.\n";

    // 6. Create sample patients
    echo "Creating sample patients...\n";
    $patients = [
        ["HMS2024000001", "David", "Omondi", "1990-05-15", "male", "O+", "0711000001", "david.omondi@email.com", "Nairobi", "Kenya"],
        ["HMS2024000002", "Alice", "Wanjiku", "1985-08-22", "female", "A+", "0711000002", "alice.w@email.com", "Mombasa", "Kenya"],
        ["HMS2024000003", "Samuel", "Kiprop", "1978-12-03", "male", "B+", "0711000003", "samuel.k@email.com", "Kisumu", "Kenya"],
        ["HMS2024000004", "Jane", "Akinyi", "1995-03-18", "female", "AB+", "0711000004", "jane.a@email.com", "Nakuru", "Kenya"],
        ["HMS2024000005", "Peter", "Mwangi", "2000-07-09", "male", "O-", "0711000005", "peter.m@email.com", "Eldoret", "Kenya"],
    ];
    $patientIds = [];
    foreach ($patients as $p) {
        Database::insert(
            "INSERT INTO patients (patient_number, first_name, last_name, date_of_birth, gender, blood_group, phone, email, city, country, registration_date, registered_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), ?)",
            [$p[0], $p[1], $p[2], $p[3], $p[4], $p[5], $p[6], $p[7], $p[8], $p[9], $userIds["receptionjuma"]]
        );
        $patientIds[$p[1]] = Database::getInstance()->getConnection()->lastInsertId();
    }
    echo "Created " . count($patients) . " patients.\n";

    // 7. Create sample appointments
    echo "Creating sample appointments...\n";
    Database::insert(
        "INSERT INTO appointments (patient_id, doctor_id, department_id, appointment_date, appointment_time, type, status, reason, created_by) VALUES (?, ?, ?, CURDATE(), '09:00:00', 'consultation', 'scheduled', 'Routine checkup', ?)",
        [$patientIds["David"], $userIds["drkamau"], $deptIds["CON"], $userIds["receptionjuma"]]
    );
    Database::insert(
        "INSERT INTO appointments (patient_id, doctor_id, department_id, appointment_date, appointment_time, type, status, reason, created_by) VALUES (?, ?, ?, CURDATE(), '10:30:00', 'followup', 'scheduled', 'Follow up on lab results', ?)",
        [$patientIds["Alice"], $userIds["drmuthoni"], $deptIds["CON"], $userIds["receptionjuma"]]
    );
    echo "Created sample appointments.\n";

    // 8. Create medicine categories and medicines
    echo "Creating pharmacy data...\n";
    $catIds = [];
    $cats = ["Antibiotics", "Pain Management", "Cardiovascular", "Respiratory", "Gastrointestinal"];
    foreach ($cats as $c) {
        Database::insert("INSERT INTO medicine_categories (name) VALUES (?)", [$c]);
        $catIds[$c] = Database::getInstance()->getConnection()->lastInsertId();
    }

    $medicines = [
        ["Amoxicillin 500mg", "Amoxicillin", "Antibiotics", "capsule", "500 mg", 500, 10, 15.00, 25.00, "AMX-001", "2025-12-31"],
        ["Paracetamol 500mg", "Paracetamol", "Pain Management", "tablet", "500 mg", 1000, 50, 2.00, 5.00, "PAR-001", "2026-06-30"],
        ["Ibuprofen 400mg", "Ibuprofen", "Pain Management", "tablet", "400 mg", 800, 30, 3.00, 8.00, "IBU-001", "2025-10-31"],
        ["Metformin 850mg", "Metformin", "Cardiovascular", "tablet", "850 mg", 600, 40, 5.00, 12.00, "MET-001", "2025-11-30"],
        ["Omeprazole 20mg", "Omeprazole", "Gastrointestinal", "capsule", "20 mg", 400, 20, 8.00, 18.00, "OME-001", "2026-03-31"],
        ["Salbutamol Inhaler", "Salbutamol", "Respiratory", "inhaler", "100mcg/dose", 200, 10, 15.00, 30.00, "SAL-001", "2025-08-31"],
        ["Ciprofloxacin 500mg", "Ciprofloxacin", "Antibiotics", "tablet", "500 mg", 300, 15, 12.00, 25.00, "CIP-001", "2025-09-30"],
        ["Amlodipine 5mg", "Amlodipine", "Cardiovascular", "tablet", "5 mg", 500, 30, 4.00, 10.00, "AML-001", "2026-02-28"],
    ];
    foreach ($medicines as $m) {
        Database::insert(
            "INSERT INTO medicines (name, generic_name, category_id, dosage_form, strength, current_stock, reorder_level, unit_price, selling_price, batch_number, expiry_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$m[0], $m[1], $catIds[$m[2]], $m[3], $m[4], $m[5], $m[6], $m[7], $m[8], $m[9], $m[10]]
        );
    }
    echo "Created " . count($medicines) . " medicines.\n";

    // 9. Create lab test categories and tests
    echo "Creating laboratory data...\n";
    Database::insert("INSERT INTO lab_test_categories (name, description) VALUES ('Hematology', 'Blood cell analysis')");
    $hemCat = Database::getInstance()->getConnection()->lastInsertId();
    Database::insert("INSERT INTO lab_test_categories (name, description) VALUES ('Biochemistry', 'Chemical analysis of body fluids')");
    $bioCat = Database::getInstance()->getConnection()->lastInsertId();
    Database::insert("INSERT INTO lab_test_categories (name, description) VALUES ('Microbiology', 'Study of microorganisms')");
    $micCat = Database::getInstance()->getConnection()->lastInsertId();
    Database::insert("INSERT INTO lab_test_categories (name, description) VALUES ('Urinalysis', 'Urine analysis')");
    $uriCat = Database::getInstance()->getConnection()->lastInsertId();

    $labTests = [
        ["Complete Blood Count", $hemCat, "CBC", "Blood", "4-10 x10^9/L", 800, 4],
        ["Malaria Parasites", $micCat, "MP", "Blood", "Negative", 500, 2],
        ["Blood Glucose", $bioCat, "BG", "Blood", "3.9-6.1 mmol/L", 400, 1],
        ["Urinalysis", $uriCat, "U/A", "Urine", "Normal", 600, 2],
        ["Lipid Profile", $bioCat, "LIPID", "Blood", "See reference", 1500, 6],
        ["HIV Test", $micCat, "HIV", "Blood", "Non-reactive", 800, 2],
        ["Pregnancy Test", $uriCat, "BHCG", "Urine", "Negative", 500, 1],
        ["Liver Function Test", $bioCat, "LFT", "Blood", "See reference", 1200, 6],
        ["Renal Function Test", $bioCat, "RFT", "Blood", "See reference", 1000, 6],
        ["Widal Test", $micCat, "WIDAL", "Blood", "Negative", 600, 3],
    ];
    foreach ($labTests as $lt) {
        Database::insert("INSERT INTO lab_tests (name, category_id, code, specimen_type, reference_range, price, turnaround_hours) VALUES (?, ?, ?, ?, ?, ?, ?)", $lt);
    }
    echo "Created " . count($labTests) . " lab tests.\n";

    // 10. Create billing items
    echo "Creating billing items...\n";
    $billingItems = [
        ["Consultation Fee", "consultation", "CONS", "General doctor consultation", 500],
        ["Specialist Consultation", "consultation", "SPCONS", "Specialist doctor consultation", 1000],
        ["Nursing Care (per day)", "service", "NURSE", "Daily nursing care charge", 500],
        ["Ward Bed (General)", "admission", "BEDGEN", "General ward bed per night", 1500],
        ["Ward Bed (Private)", "admission", "BEDPRI", "Private ward bed per night", 3500],
        ["Ward Bed (ICU)", "admission", "BEDICU", "ICU bed per night", 8000],
        ["Registration Fee", "service", "REG", "Patient registration fee", 200],
        ["Administration Fee", "service", "ADMIN", "Administrative processing fee", 300],
    ];
    foreach ($billingItems as $bi) {
        Database::insert("INSERT INTO billing_items (name, category, code, description, price) VALUES (?, ?, ?, ?, ?)", $bi);
    }
    echo "Created " . count($billingItems) . " billing items.\n";

    // 11. Create wards and beds
    echo "Creating wards and beds...\n";
    $wardData = [
        ["General Ward A", "GW-A", "1", "general", 20, 1500],
        ["General Ward B", "GW-B", "1", "general", 20, 1500],
        ["Private Ward", "PW", "2", "private", 10, 3500],
        ["ICU", "ICU", "2", "icu", 5, 8000],
        ["Maternity Ward", "MW", "3", "maternity", 15, 2500],
        ["Pediatric Ward", "PW", "3", "pediatric", 15, 2000],
    ];
    foreach ($wardData as $w) {
        Database::insert("INSERT INTO wards (name, code, floor, type, total_beds, price_per_day) VALUES (?, ?, ?, ?, ?, ?)", [
            $w[0], $w[1], $w[2], $w[3], $w[4], $w[5]
        ]);
        $wardId = Database::getInstance()->getConnection()->lastInsertId();
        for ($i = 1; $i <= $w[4]; $i++) {
            $bedNum = str_pad($i, 2, "0", STR_PAD_LEFT);
            Database::insert("INSERT INTO beds (ward_id, bed_number, bed_type, price_per_day, status) VALUES (?, ?, 'standard', ?, 'available')", [
                $wardId, $bedNum, $w[5]
            ]);
        }
    }
    echo "Created 6 wards with beds.\n";

    // 12. Create visits for today
    echo "Creating today's visits...\n";
    Database::insert(
        "INSERT INTO visits (patient_id, visit_number, visit_date, visit_time, type, status, chief_complaint, checked_in_by) VALUES (?, ?, CURDATE(), '08:30:00', 'outpatient', 'waiting', 'General checkup and headache', ?)",
        [$patientIds["David"], "VIS" . date("Ymd") . "0001", $userIds["receptionjuma"]]
    );
    Database::insert(
        "INSERT INTO visits (patient_id, visit_number, visit_date, visit_time, type, status, chief_complaint, checked_in_by) VALUES (?, ?, CURDATE(), '09:15:00', 'outpatient', 'waiting', 'Follow up on diabetes management', ?)",
        [$patientIds["Alice"], "VIS" . date("Ymd") . "0002", $userIds["receptionjuma"]]
    );
    echo "Created sample visits.\n";

    // 13. Create settings
    echo "Creating system settings...\n";
    $settings = [
        ["hospital_name", "City Hospital & Health Center"],
        ["hospital_address", "123 Health Avenue, Medical District"],
        ["hospital_phone", "+254 712 345 678"],
        ["hospital_email", "info@cityhospital.ke"],
        ["hospital_website", "www.cityhospital.ke"],
        ["currency", "TZS"],
        ["tax_rate", "0"],
        ["default_appointment_duration", "30"],
        ["timezone", "Africa/Nairobi"],
        ["date_format", "d M Y"],
        ["auto_invoice_on_discharge", "1"],
        ["enable_notifications", "1"],
        ["default_country_id", "", "general"],
    ];
    foreach ($settings as $s) {
        Database::query("INSERT INTO settings (`key`, `value`, `group_name`) VALUES (?, ?, 'general') ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)", $s);
    }
    echo "Created " . count($settings) . " settings.\n";

    // 14. Location data handled at top of seeder (runs even if users exist)

    echo "\n========================================\n";
    echo "SEEDING COMPLETED SUCCESSFULLY!\n";
    echo "========================================\n";
    echo "\nDemo Login Credentials:\n";
    echo "  Super Admin: admin@hospital.com / password123\n";
    echo "  Doctor:      dr.kamau@hospital.com / password123\n";
    echo "  Doctor:      dr.muthoni@hospital.com / password123\n";
    echo "  Nurse:       nurse.wanjiku@hospital.com / password123\n";
    echo "  Reception:   reception@hospital.com / password123\n";
    echo "  Pharmacist:  pharmacy@hospital.com / password123\n";
    echo "  Lab Tech:    lab@hospital.com / password123\n";
    echo "  Cashier:     cashier@hospital.com / password123\n";
    echo "  Records:     records@hospital.com / password123\n";

} catch (Exception $e) {
    echo "Seeder failed: " . $e->getMessage() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
