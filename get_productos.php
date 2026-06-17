<?php
// Le avisamos al navegador que esto entregará datos en formato JSON
header('Content-Type: application/json');

// 1. Importas tu conexión existente
require_once 'conexion.php'; 

try {
    // 2. Traemos todos los productos cambiando $pdo por $conexion
    $stmt = $conexion->prepare("SELECT * FROM productos");
    $stmt->execute();
    
    // Obtenemos los datos con PDO
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 3. Convertimos el resultado a JSON para que app.js lo entienda
    echo json_encode($resultados);

} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode(["error" => "No se pudo conectar a la base de datos."]);
}
?>