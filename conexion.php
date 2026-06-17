<?php
$host = "localhost";
$db_name = "chocolat2_xime";
$username = "chocolat2_xime"; // Cambia esto en tu hosting real
$password = "Migatololo007";     // Cambia esto en tu hosting real

function initialize_sqlite_schema(PDO $conexion)
{
    $conexion->exec("PRAGMA foreign_keys = ON");

    $conexion->exec("CREATE TABLE IF NOT EXISTS productos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nombre TEXT NOT NULL,
        categoria TEXT NOT NULL,
        precio REAL NOT NULL,
        precio_original REAL DEFAULT NULL,
        stock INTEGER NOT NULL DEFAULT 0,
        imagen TEXT DEFAULT NULL,
        descripcion TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $conexion->exec("CREATE TABLE IF NOT EXISTS pedidos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        cliente_nombre TEXT NOT NULL,
        cliente_email TEXT NOT NULL,
        cliente_telefono TEXT,
        estado TEXT NOT NULL DEFAULT 'pendiente',
        total REAL NOT NULL,
        items_json TEXT,
        mercadopago_id TEXT,
        notas TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $conexion->exec("CREATE TABLE IF NOT EXISTS usuarios (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        email TEXT NOT NULL UNIQUE,
        nombre TEXT NOT NULL,
        telefono TEXT,
        direccion TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $conexion->exec("CREATE TABLE IF NOT EXISTS site_config (
        config_key TEXT PRIMARY KEY,
        config_value TEXT NOT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
}

try {
    $conexion = new PDO("mysql:host=$host;dbname=$db_name", $username, $password);
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conexion->exec("set names utf8");
    $db_driver = 'mysql';
} catch(PDOException $exception) {
    error_log($exception->getMessage());

    try {
        $sqlitePath = __DIR__ . '/chocoxime.sqlite';
        $conexion = new PDO('sqlite:' . $sqlitePath);
        $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db_driver = 'sqlite';
        initialize_sqlite_schema($conexion);
    } catch (PDOException $sqliteException) {
        error_log($sqliteException->getMessage());
        echo "Error de conexión a la base de datos.";
    }
}
?>