<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin_auth_xime'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

require_once 'conexion.php';
require_once 'dashboard_utils.php';

try {
    $dashboard = get_dashboard_data($conexion);
    echo json_encode($dashboard);
} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode(['error' => 'No se pudo cargar el dashboard.']);
}
?>