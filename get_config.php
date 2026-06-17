<?php
header('Content-Type: application/json');

require_once 'conexion.php';

try {
    $stmt = $conexion->prepare("SELECT config_key, config_value FROM site_config");
    $stmt->execute();

    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode(["error" => "No se pudo cargar la configuración del sitio."]);
}
?>