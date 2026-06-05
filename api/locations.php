<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
Auth::requireAuth();

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'countries':
        $countries = Database::fetchAll("SELECT id, name, code, phone_code, currency_code FROM countries WHERE status = 'active' ORDER BY name");
        json_response($countries);
        break;

    case 'regions':
        $countryId = intval($_GET['country_id'] ?? 0);
        if (!$countryId) json_response([], 400);
        $regions = Database::fetchAll("SELECT id, name, code FROM regions WHERE country_id = ? AND status = 'active' ORDER BY name", [$countryId]);
        json_response($regions);
        break;

    case 'districts':
        $regionId = intval($_GET['region_id'] ?? 0);
        if (!$regionId) json_response([], 400);
        $districts = Database::fetchAll("SELECT id, name, code FROM districts WHERE region_id = ? AND status = 'active' ORDER BY name", [$regionId]);
        json_response($districts);
        break;

    case 'wards':
        $districtId = intval($_GET['district_id'] ?? 0);
        if (!$districtId) json_response([], 400);
        $wards = Database::fetchAll("SELECT id, name, code FROM location_wards WHERE district_id = ? AND status = 'active' ORDER BY name", [$districtId]);
        json_response($wards);
        break;

    case 'villages':
        $wardId = intval($_GET['ward_id'] ?? 0);
        if (!$wardId) json_response([], 400);
        $villages = Database::fetchAll("SELECT id, name, code FROM villages WHERE ward_id = ? AND status = 'active' ORDER BY name", [$wardId]);
        json_response($villages);
        break;

    default:
        json_response(['error' => 'Invalid action'], 400);
}
