<?php
// INICIO DE SESIÓN Y CONEXIÓN
session_start();

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function csrf_token()
{
  return $_SESSION['csrf_token'] ?? '';
}

function csrf_is_valid()
{
  return isset($_POST['csrf_token'], $_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

// 💡 CONSEJO DE ORO: Si estás teniendo fallos, deja estas dos líneas activas
// para que PHP te diga exactamente qué línea se rompió.
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Incluimos tu archivo de conexión real
include 'conexion.php';
require_once 'dashboard_utils.php';

function get_site_config()
{
  global $conexion;
  static $cache = null;

  if ($cache !== null) {
    return $cache;
  }

  $cache = [];

  try {
    $stmt = $conexion->query("SELECT config_key, config_value FROM site_config");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $cache[$row['config_key']] = $row['config_value'];
    }
  } catch (PDOException $e) {
    error_log($e->getMessage());
  }

  return $cache;
}

function get_config_value($key, $default = '')
{
  $config = get_site_config();
  return array_key_exists($key, $config) ? $config[$key] : $default;
}

function save_config_value($key, $value)
{
  global $conexion;
  $stmt = $conexion->prepare("INSERT INTO site_config (config_key, config_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)");
  $stmt->execute([$key, $value]);
}

function get_users_list()
{
  global $conexion;

  try {
    $stmt = $conexion->query("SELECT * FROM usuarios ORDER BY id DESC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
  } catch (PDOException $e) {
    error_log($e->getMessage());
    return [];
  }
}

$site_store_name = get_config_value('store_name', 'Chocolates Xime');
$site_store_email = get_config_value('store_email', '');
$site_store_phone = get_config_value('store_phone', '');
$site_backend_url = get_config_value('backend_url', '');
$site_hero_image = get_config_value('hero_image', 'https://via.placeholder.com/1200x800?text=Portada');
$site_about_image = get_config_value('about_image', 'https://via.placeholder.com/1200x800?text=Sobre+nosotros');
$usuarios = get_users_list();
$dashboard = get_dashboard_data($conexion);

$error_login = "";

// PROCESAR CIERRE DE SESIÓN
if (isset($_POST['btn_logout'])) {
  if (csrf_is_valid()) {
    session_unset();
    session_destroy();
    header("Location: admin.php");
    exit();
  }
}

// PROCESAR INICIO DE SESIÓN
if (isset($_POST['btn_login'])) {
  $input_user = trim($_POST['admin_user'] ?? '');
  $input_pass = trim($_POST['admin_pass'] ?? '');

    if (isset($username) && isset($password)) {
        if ($input_user === $username && $input_pass === $password) {
      session_regenerate_id(true);
            $_SESSION['admin_auth_xime'] = true;
      $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            header("Location: admin.php");
            exit();
        } else {
            $error_login = "Usuario o contraseña incorrectos.";
        }
    } else {
        $error_login = "Error: No se pudieron leer las credenciales en conexion.php.";
    }
}

// PROCESAR NUEVO PRODUCTO (GUARDAR EN BD)
if (isset($_POST['btn_save_product']) && isset($_SESSION['admin_auth_xime'])) {
  if (!csrf_is_valid()) {
    header("Location: admin.php?msg=error");
    exit();
  }

  $nombre = trim($_POST['np_name'] ?? '');
  $categoria = trim($_POST['np_cat'] ?? '');
  $precio = (float) ($_POST['np_price'] ?? 0);
  $precio_original = trim($_POST['np_original'] ?? '');
  $precio_original = $precio_original !== '' ? (float) $precio_original : null;
  $stock = (int) ($_POST['np_stock'] ?? 0);
  $imagen = trim($_POST['np_img'] ?? '');
  $descripcion = trim($_POST['np_desc'] ?? '');

  if ($nombre === '' || $categoria === '' || $precio <= 0 || $stock < 0) {
    header("Location: admin.php?msg=error");
    exit();
  }

    try {
        $stmt = $conexion->prepare("INSERT INTO productos (nombre, categoria, precio, precio_original, stock, imagen, descripcion) VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        // En PDO pasamos los parámetros directamente al execute como un arreglo
        $stmt->execute([$nombre, $categoria, $precio, $precio_original, $stock, $imagen, $descripcion]);
        
        header("Location: admin.php?msg=creado");
        exit();
    } catch(PDOException $e) {
        error_log($e->getMessage());
        header("Location: admin.php?msg=error");
        exit();
    }
}

// ELIMINAR PRODUCTO (BORRAR DE BD)
if (isset($_POST['btn_delete_product']) && isset($_SESSION['admin_auth_xime'])) {
  if (!csrf_is_valid()) {
    header("Location: admin.php?msg=error");
    exit();
  }

  $id_borrar = intval($_POST['delete_id'] ?? 0);
    
    try {
        $stmt = $conexion->prepare("DELETE FROM productos WHERE id = ?");
        $stmt->execute([$id_borrar]);
        
        header("Location: admin.php?msg=borrado");
        exit();
    } catch(PDOException $e) {
      error_log($e->getMessage());
      header("Location: admin.php?msg=error");
      exit();
    }
}

// GUARDAR CONFIGURACIÓN GENERAL DEL SITIO
if (isset($_POST['btn_save_settings']) && isset($_SESSION['admin_auth_xime'])) {
  if (!csrf_is_valid()) {
    header("Location: admin.php?msg=error");
    exit();
  }

  $settings = [
    'store_name' => trim($_POST['store_name'] ?? ''),
    'store_email' => trim($_POST['store_email'] ?? ''),
    'store_phone' => trim($_POST['store_phone'] ?? ''),
    'backend_url' => trim($_POST['backend_url'] ?? ''),
    'hero_image' => trim($_POST['hero_image'] ?? ''),
    'about_image' => trim($_POST['about_image'] ?? ''),
  ];

  try {
    foreach ($settings as $key => $value) {
      save_config_value($key, $value);
    }

    header("Location: admin.php?msg=configurado");
    exit();
  } catch (PDOException $e) {
    error_log($e->getMessage());
    header("Location: admin.php?msg=error");
    exit();
  }
}

// CREAR USUARIO
if (isset($_POST['btn_save_user']) && isset($_SESSION['admin_auth_xime'])) {
  if (!csrf_is_valid()) {
    header("Location: admin.php?msg=error");
    exit();
  }

  $user_email = trim($_POST['user_email'] ?? '');
  $user_name = trim($_POST['user_name'] ?? '');
  $user_phone = trim($_POST['user_phone'] ?? '');
  $user_address = trim($_POST['user_address'] ?? '');

  if ($user_email === '' || $user_name === '') {
    header("Location: admin.php?msg=error");
    exit();
  }

  try {
    $stmt = $conexion->prepare("INSERT INTO usuarios (email, nombre, telefono, direccion) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user_email, $user_name, $user_phone, $user_address]);
    header("Location: admin.php?msg=usuario_creado");
    exit();
  } catch (PDOException $e) {
    error_log($e->getMessage());
    header("Location: admin.php?msg=error");
    exit();
  }
}

// ELIMINAR USUARIO
if (isset($_POST['btn_delete_user']) && isset($_SESSION['admin_auth_xime'])) {
  if (!csrf_is_valid()) {
    header("Location: admin.php?msg=error");
    exit();
  }

  $user_id = intval($_POST['user_id'] ?? 0);

  try {
    $stmt = $conexion->prepare("DELETE FROM usuarios WHERE id = ?");
    $stmt->execute([$user_id]);
    header("Location: admin.php?msg=usuario_borrado");
    exit();
  } catch (PDOException $e) {
    error_log($e->getMessage());
    header("Location: admin.php?msg=error");
    exit();
  }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Admin — Chocolates Xime</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
:root {
  --brown-dark:    #2C1A0E;
  --brown-mid:     #5D3A1A;
  --brown-warm:    #8B5E3C;
  --cream-bg:      #F5EFE6;
  --cream-card:    #FDF9F4;
  --cream-border: #E8DDD0;
  --gold:          #C9993A;
  --gold-light:    #F0C060;
  --text-dark:     #1A0F07;
  --text-body:     #4A3728;
  --text-muted:    #9A7B65;
  --success:       #3A7D44;
  --success-bg:    #EAF5EC;
  --danger:        #C0392B;
  --danger-bg:     #FDECEA;
  --warning:       #D4860A;
  --warning-bg:    #FEF3E2;
  --info:          #1A6F9A;
  --info-bg:       #E3F2FD;
  --white:         #ffffff;
  --sidebar-w:     260px;
  --radius:        12px;
  --radius-sm:     8px;
  --shadow:        0 2px 12px rgba(44,26,14,0.09);
  --shadow-md:     0 4px 24px rgba(44,26,14,0.13);
  --transition:    0.22s ease;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }
body { font-family: 'Jost', sans-serif; background: var(--cream-bg); color: var(--text-dark); line-height: 1.6; overflow-x: hidden; }
a { text-decoration: none; color: inherit; }
button { cursor: pointer; border: none; background: none; font-family: inherit; }
input, textarea, select { font-family: inherit; }
img { display: block; max-width: 100%; }
.hidden { display: none !important; }
h1,h2,h3,h4 { font-family: 'Playfair Display', serif; color: var(--brown-dark); line-height: 1.25; }
em { font-style: italic; color: var(--gold); }

/* ── LAYOUT ── */
.admin-layout { display: flex; min-height: 100vh; }

/* ── SIDEBAR ── */
.sidebar {
  width: var(--sidebar-w);
  background: var(--brown-dark);
  display: flex; flex-direction: column;
  position: fixed; top: 0; left: 0; bottom: 0;
  z-index: 100;
  transition: transform var(--transition);
  overflow-y: auto;
}
.sidebar-logo {
  padding: 1.6rem 1.5rem 1.4rem;
  border-bottom: 1px solid rgba(255,255,255,0.08);
  display: flex; align-items: center; gap: 0.5rem;
}
.sidebar-logo span { font-family: 'Playfair Display', serif; font-size: 1.25rem; color: var(--cream-bg); }
.sidebar-logo em { color: var(--gold-light); font-style: italic; }
.logo-star { color: var(--gold); font-size: 1rem; }
.sidebar-label {
  font-size: 0.65rem; font-weight: 600; letter-spacing: 0.18em;
  text-transform: uppercase; color: rgba(245,239,230,0.35);
  padding: 1.2rem 1.5rem 0.4rem;
}
.nav-item {
  display: flex; align-items: center; gap: 0.85rem;
  padding: 0.75rem 1.5rem;
  color: rgba(245,239,230,0.65);
  font-size: 0.9rem; font-weight: 500;
  transition: all var(--transition);
  cursor: pointer;
  position: relative;
}
.nav-item i { width: 18px; text-align: center; font-size: 0.95rem; }
.nav-item:hover { background: rgba(255,255,255,0.07); color: var(--cream-bg); }
.nav-item.active { background: rgba(201,153,58,0.15); color: var(--gold-light); }
.nav-item.active::before {
  content: ''; position: absolute; left: 0; top: 0; bottom: 0;
  width: 3px; background: var(--gold);
  border-radius: 0 3px 3px 0;
}
.nav-badge {
  margin-left: auto; background: var(--gold); color: var(--brown-dark);
  font-size: 0.65rem; font-weight: 700; min-width: 18px; height: 18px;
  border-radius: 50px; display: flex; align-items: center; justify-content: center;
  padding: 0 4px;
}
.sidebar-bottom {
  margin-top: auto;
  padding: 1rem 1.5rem 1.5rem;
  border-top: 1px solid rgba(255,255,255,0.08);
}
.sidebar-user {
  display: flex; align-items: center; gap: 0.75rem;
}
.sidebar-avatar {
  width: 36px; height: 36px; border-radius: 50%;
  background: var(--gold); color: var(--brown-dark);
  display: flex; align-items: center; justify-content: center;
  font-weight: 700; font-size: 0.85rem;
}
.sidebar-user-info { flex: 1; }
.sidebar-user-info strong { display: block; font-size: 0.85rem; color: var(--cream-bg); font-weight: 600; }
.sidebar-user-info small { font-size: 0.72rem; color: rgba(245,239,230,0.45); }

/* ── MAIN ── */
.main-content {
  margin-left: var(--sidebar-w);
  flex: 1;
  display: flex; flex-direction: column;
  min-height: 100vh;
}

/* ── TOPBAR ── */
.topbar {
  background: var(--white);
  border-bottom: 1px solid var(--cream-border);
  padding: 0 2rem;
  height: 64px;
  display: flex; align-items: center; justify-content: space-between;
  position: sticky; top: 0; z-index: 50;
  box-shadow: 0 1px 8px rgba(44,26,14,0.06);
}
.topbar-left { display: flex; align-items: center; gap: 1rem; }
.topbar-title { font-size: 1.1rem; font-weight: 600; color: var(--brown-dark); font-family: 'Playfair Display', serif; }
.topbar-subtitle { font-size: 0.8rem; color: var(--text-muted); margin-top: 1px; }
.hamburger-admin { display: none; color: var(--brown-dark); font-size: 1.2rem; padding: 0.4rem; }
.topbar-right { display: flex; align-items: center; gap: 0.75rem; }
.topbar-icon-btn {
  width: 38px; height: 38px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  color: var(--text-muted); font-size: 1rem;
  transition: all var(--transition); position: relative;
}
.topbar-icon-btn:hover { background: var(--cream-bg); color: var(--brown-dark); }
.notif-dot {
  position: absolute; top: 6px; right: 6px;
  width: 8px; height: 8px; border-radius: 50%;
  background: var(--danger); border: 2px solid var(--white);
}
.topbar-avatar {
  width: 36px; height: 36px; border-radius: 50%;
  background: var(--brown-dark); color: var(--gold);
  display: flex; align-items: center; justify-content: center;
  font-size: 0.85rem; font-weight: 700; cursor: pointer;
}
.btn-new {
  display: inline-flex; align-items: center; gap: 0.4rem;
  background: var(--brown-dark); color: var(--white);
  padding: 0.5rem 1.1rem; border-radius: 50px;
  font-size: 0.85rem; font-weight: 500;
  transition: all var(--transition);
}
.btn-new:hover { background: var(--brown-mid); transform: translateY(-1px); }

/* ── PAGE CONTENT ── */
.page-content { padding: 2rem; flex: 1; display: none; }
.page-content.active { display: block; }

/* ── SECTION TITLE ── */
.section-title {
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;
}
.section-title h2 { font-size: 1.6rem; }
.section-title p { font-size: 0.85rem; color: var(--text-muted); margin-top: 0.2rem; }

/* ── STATS GRID ── */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 1.2rem;
  margin-bottom: 2rem;
}
.stat-card {
  background: var(--white);
  border-radius: var(--radius);
  padding: 1.4rem;
  box-shadow: var(--shadow);
  border: 1px solid var(--cream-border);
  position: relative;
  overflow: hidden;
}
.stat-card::before {
  content: '';
  position: absolute; top: 0; left: 0; right: 0; height: 3px;
}
.stat-card.gold::before { background: var(--gold); }
.stat-card.green::before { background: var(--success); }
.stat-card.blue::before { background: var(--info); }
.stat-card.warm::before { background: var(--brown-warm); }
.stat-label { font-size: 0.75rem; font-weight: 600; letter-spacing: 0.12em; text-transform: uppercase; color: var(--text-muted); margin-bottom: 0.5rem; }
.stat-value { font-family: 'Playfair Display', serif; font-size: 2rem; font-weight: 700; color: var(--brown-dark); margin-bottom: 0.4rem; }
.stat-change {
  font-size: 0.78rem; display: flex; align-items: center; gap: 0.3rem; color: var(--text-muted);
}
.stat-icon {
  position: absolute; right: 1.2rem; top: 1.2rem;
  color: var(--cream-border); font-size: 1.6rem;
}

/* ── CARDS ── */
.card {
  background: var(--white);
  border-radius: var(--radius);
  box-shadow: var(--shadow);
  border: 1px solid var(--cream-border);
  overflow: hidden;
  margin-bottom: 1.5rem;
}
.card-header {
  padding: 1.1rem 1.5rem;
  border-bottom: 1px solid var(--cream-border);
  display: flex; align-items: center; justify-content: space-between;
  background: var(--cream-card);
}
.card-header h3 { font-size: 1rem; }
.card-body { padding: 1.5rem; }
.card-body-flush { padding: 0; }

/* ── TABLE ── */
.table-wrap { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
thead th {
  text-align: left; padding: 0.75rem 1.2rem;
  font-size: 0.72rem; font-weight: 600; letter-spacing: 0.1em;
  text-transform: uppercase; color: var(--text-muted);
  background: var(--cream-bg);
  border-bottom: 1px solid var(--cream-border);
}
tbody td { padding: 1rem 1.2rem; border-bottom: 1px solid var(--cream-border); vertical-align: middle; }
tbody tr:last-child td { border-bottom: none; }
tbody tr:hover td { background: var(--cream-bg); }
.empty-table { text-align: center; padding: 2rem; color: var(--text-muted); }

.badge { display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.25rem 0.7rem; border-radius: 50px; font-size: 0.72rem; font-weight: 600; letter-spacing: 0.04em; }
.badge-success { background: var(--success-bg); color: var(--success); }
.badge-muted { background: var(--cream-bg); color: var(--text-muted); }
.badge-dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }

.btn-sm { display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.35rem 0.8rem; border-radius: 6px; font-size: 0.78rem; font-weight: 500; transition: all var(--transition); }
.btn-sm-outline { border: 1.5px solid var(--cream-border); color: var(--text-body); background: var(--white); }
.btn-sm-outline:hover { border-color: var(--brown-warm); color: var(--brown-dark); }

.filter-bar { display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; margin-bottom: 1.2rem; }
.search-wrap { position: relative; flex: 1; min-width: 200px; }
.search-wrap i { position: absolute; left: 0.85rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 0.85rem; }
.search-input { width: 100%; padding: 0.6rem 1rem 0.6rem 2.3rem; border: 1.5px solid var(--cream-border); border-radius: var(--radius-sm); font-size: 0.88rem; background: var(--white); transition: border-color var(--transition); }
.search-input:focus { outline: none; border-color: var(--brown-warm); }
.filter-select { padding: 0.6rem 1rem; border: 1.5px solid var(--cream-border); border-radius: var(--radius-sm); font-size: 0.85rem; background: var(--white); color: var(--text-body); cursor: pointer; }

.charts-row { display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem; }
.donut-wrap { display: flex; flex-direction: column; gap: 0.6rem; padding: 0.5rem 1rem; }
.donut-item { display: flex; align-items: center; gap: 0.6rem; font-size: 0.83rem; }
.donut-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
.donut-item span { flex: 1; color: var(--text-body); }
.donut-item strong { color: var(--brown-mid); }
.donut-svg-wrap { display: flex; justify-content: center; margin-bottom: 0.5rem; }

.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.2rem; }
.form-group { display: flex; flex-direction: column; gap: 0.4rem; }
.form-group.full { grid-column: 1 / -1; }
.form-label { font-size: 0.8rem; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase; color: var(--text-muted); }
.form-input, .form-select, .form-textarea { padding: 0.7rem 1rem; border: 1.5px solid var(--cream-border); border-radius: var(--radius-sm); font-size: 0.92rem; background: var(--white); transition: border-color var(--transition); color: var(--text-dark); }
.form-textarea { resize: vertical; min-height: 90px; }
.form-actions { display: flex; gap: 0.75rem; justify-content: flex-end; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--cream-border); }
.btn-save { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.65rem 1.6rem; border-radius: 50px; background: var(--brown-dark); color: var(--white); font-size: 0.9rem; font-weight: 500; transition: all var(--transition); }

.dashboard-mini-chart { display: flex; flex-direction: column; gap: 0.85rem; }
.dashboard-bar-row { display: grid; grid-template-columns: 110px 1fr 90px; gap: 0.75rem; align-items: center; font-size: 0.82rem; }
.dashboard-bar-label { color: var(--text-body); font-weight: 500; }
.dashboard-bar-track { height: 10px; border-radius: 999px; overflow: hidden; background: var(--cream-bg); border: 1px solid var(--cream-border); }
.dashboard-bar-fill { height: 100%; border-radius: inherit; background: linear-gradient(90deg, var(--gold), var(--brown-warm)); }
.dashboard-bar-row strong { text-align: right; color: var(--brown-dark); }

.dashboard-live-fade { transition: opacity 0.2s ease; }
.dashboard-live-loading { opacity: 0.5; }

@media (max-width: 900px) {
  .sidebar { transform: translateX(-100%); }
  .sidebar.open { transform: translateX(0); }
  .main-content { margin-left: 0; }
  .hamburger-admin { display: block; }
  .charts-row { grid-template-columns: 1fr; }
  .form-grid { grid-template-columns: 1fr; }
}
@media (max-width: 600px) {
  .stats-grid { grid-template-columns: 1fr 1fr; }
  .page-content { padding: 1rem; }
  .topbar { padding: 0 1rem; }
}

.login-wrap { display: flex; min-height: 100vh; align-items: center; justify-content: center; padding: 1rem; }
.login-box { width: 100%; max-width: 400px; padding: 2.5rem; }
.login-logo { text-align: center; margin-bottom: 2rem; }
.login-logo h1 { font-size: 2rem; margin-bottom: 0.5rem; }
.alert-box { background: var(--danger-bg); color: var(--danger); padding: 0.8rem; border-radius: var(--radius-sm); margin-bottom: 1.5rem; font-size: 0.85rem; font-weight: 500; text-align: center; border: 1px solid #f5c6cb; }
</style>
</head>
<body>

<?php if (!isset($_SESSION['admin_auth_xime'])): ?>

<div class="login-wrap">
  <div class="card login-box">
    <div class="login-logo">
      <span class="logo-star" style="font-size: 2rem; display: block; margin-bottom: 10px;">✦</span>
      <h1>Chocolates<em>Xime</em></h1>
      <p style="color: var(--text-muted); font-size: 0.9rem;">Acceso Administrativo</p>
    </div>
    
    <?php if(!empty($error_login)): ?>
      <div class="alert-box"><i class="fas fa-exclamation-circle"></i> <?php echo $error_login; ?></div>
    <?php endif; ?>

    <form method="POST" action="">
      <div class="form-group" style="margin-bottom: 1.2rem;">
        <label class="form-label">Usuario de la Base de Datos</label>
        <div class="search-wrap">
          <i class="fas fa-user"></i>
          <input type="text" name="admin_user" class="search-input" placeholder="Ej: chocolat2_xime" required />
        </div>
      </div>
      <div class="form-group" style="margin-bottom: 2rem;">
        <label class="form-label">Contraseña</label>
        <div class="search-wrap">
          <i class="fas fa-lock"></i>
          <input type="password" name="admin_pass" class="search-input" placeholder="••••••••" required />
        </div>
      </div>
      <button type="submit" name="btn_login" class="btn-save" style="width: 100%; justify-content: center; padding: 0.8rem;">
        Entrar al Panel <i class="fas fa-arrow-right" style="margin-left: 5px;"></i>
      </button>
    </form>
  </div>
</div>

<?php else: ?>

<div class="admin-layout">

  <aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
      <span class="logo-star">✦</span>
      <span>Chocolates<em>Xime</em></span>
    </div>

    <div class="sidebar-label">Principal</div>
    <div class="nav-item active" onclick="navigate('dashboard',this)">
      <i class="fas fa-chart-pie"></i> Dashboard
    </div>
    <div class="nav-item" onclick="navigate('orders',this)">
      <i class="fas fa-shopping-bag"></i> Pedidos
      <span class="nav-badge hidden" id="badge-orders">0</span>
    </div>

    <div class="sidebar-label">Tienda</div>
    <div class="nav-item" onclick="navigate('products',this)">
      <i class="fas fa-box-open"></i> Productos
    </div>
    <div class="nav-item" onclick="navigate('new-product',this)">
      <i class="fas fa-plus-circle"></i> Nuevo Producto
    </div>

    <div class="sidebar-label">Clientes</div>
    <div class="nav-item" onclick="navigate('users',this)">
      <i class="fas fa-users"></i> Usuarios
    </div>

    <div class="sidebar-label">Sistema</div>
    <div class="nav-item" onclick="navigate('settings',this)">
      <i class="fas fa-cog"></i> Configuración
    </div>
    <div class="nav-item" onclick="window.open('index.html','_blank')">
      <i class="fas fa-external-link-alt"></i> Ver Tienda
    </div>

    <div class="sidebar-bottom">
      <div class="sidebar-user">
        <div class="sidebar-avatar">AX</div>
        <div class="sidebar-user-info">
          <strong>Admin Xime</strong>
          <small>Superadmin</small>
        </div>
        <form method="POST" action="admin.php" style="margin:0;">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>" />
          <button type="submit" name="btn_logout" title="Cerrar sesión" style="color:rgba(245,239,230,0.4); background:none; border:none; padding:0; cursor:pointer;">
            <i class="fas fa-sign-out-alt" style="font-size:1.1rem;"></i>
          </button>
        </form>
      </div>
    </div>
  </aside>

  <div class="main-content">

    <div class="topbar">
      <div class="topbar-left">
        <button class="hamburger-admin" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
        <div>
          <div class="topbar-title" id="topbar-title">Dashboard</div>
          <div class="topbar-subtitle" id="topbar-sub">Resumen general</div>
        </div>
      </div>
      <div class="topbar-right">
        <button class="topbar-icon-btn" title="Notificaciones" onclick="alert('No hay notificaciones nuevas')">
          <i class="fas fa-bell"></i>
        </button>
        <button class="topbar-icon-btn" title="Ayuda">
          <i class="fas fa-question-circle"></i>
        </button>
        <div class="topbar-avatar" title="Mi perfil">AX</div>
      </div>
    </div>

    <?php if(isset($_GET['msg'])): ?>
      <?php if($_GET['msg'] == 'error'): ?>
        <div class="alert-box" style="background: var(--warning-bg); color: var(--warning); border-color: #f0d7a3; margin: 1.5rem 2rem 0; text-align: left;">
          <i class="fas fa-exclamation-triangle"></i> No se pudo completar la acción. Verifica los datos y vuelve a intentarlo.
        </div>
      <?php elseif($_GET['msg'] == 'configurado'): ?>
        <div class="alert-box" style="background: var(--success-bg); color: var(--success); border-color: #c3e6cb; margin: 1.5rem 2rem 0; text-align: left;">
          <i class="fas fa-check-circle"></i> Configuración guardada correctamente.
        </div>
      <?php elseif($_GET['msg'] == 'usuario_creado'): ?>
        <div class="alert-box" style="background: var(--success-bg); color: var(--success); border-color: #c3e6cb; margin: 1.5rem 2rem 0; text-align: left;">
          <i class="fas fa-user-check"></i> Usuario creado correctamente.
        </div>
      <?php elseif($_GET['msg'] == 'usuario_borrado'): ?>
        <div class="alert-box" style="margin: 1.5rem 2rem 0; text-align: left;">
          <i class="fas fa-user-minus"></i> Usuario eliminado correctamente.
        </div>
      <?php endif; ?>
      <?php if($_GET['msg'] == 'creado'): ?>
        <div class="alert-box" style="background: var(--success-bg); color: var(--success); border-color: #c3e6cb; margin: 1.5rem 2rem 0; text-align: left;">
          <i class="fas fa-check-circle"></i> Producto guardado exitosamente.
        </div>
      <?php elseif($_GET['msg'] == 'borrado'): ?>
        <div class="alert-box" style="margin: 1.5rem 2rem 0; text-align: left;">
          <i class="fas fa-trash"></i> Producto eliminado de la base de datos correctamente.
        </div>
      <?php endif; ?>
    <?php endif; ?>

    <div class="page-content active" id="page-dashboard">
      <div class="section-title">
        <div>
          <h2>Dashboard</h2>
          <p>Resumen del negocio — Actual</p>
        </div>
        <button class="btn-new" onclick="navigate('orders',document.querySelector('.nav-item[onclick*=\'orders\']'))">
          <i class="fas fa-plus"></i> Nuevo Pedido
        </button>
      </div>

      <div class="stats-grid">
        <div class="stat-card gold">
          <i class="fas fa-dollar-sign stat-icon"></i>
          <div class="stat-label">Ventas del mes</div>
          <div class="stat-value" id="dashboard-sales-this-month"><?php echo '$' . number_format($dashboard['sales_this_month'], 0, ',', '.'); ?></div>
          <div class="stat-change"><i class="fas fa-minus"></i> Datos reales del mes</div>
        </div>
        <div class="stat-card green">
          <i class="fas fa-shopping-bag stat-icon"></i>
          <div class="stat-label">Pedidos totales</div>
          <div class="stat-value" id="dashboard-orders-total"><?php echo (int) $dashboard['orders_total']; ?></div>
          <div class="stat-change"><i class="fas fa-minus"></i> <span id="dashboard-pending-orders"><?php echo (int) $dashboard['pending_orders']; ?></span> pendientes</div>
        </div>
        <div class="stat-card blue">
          <i class="fas fa-users stat-icon"></i>
          <div class="stat-label">Clientes registrados</div>
          <div class="stat-value" id="dashboard-users-total"><?php echo (int) $dashboard['users_total']; ?></div>
          <div class="stat-change"><i class="fas fa-minus"></i> En la base de datos</div>
        </div>
        <div class="stat-card warm">
          <i class="fas fa-box stat-icon"></i>
          <div class="stat-label">Productos activos</div>
          <div class="stat-value" id="dashboard-products-active"><?php echo (int) $dashboard['products_active']; ?></div>
          <div class="stat-change"><i class="fas fa-minus"></i> <?php echo (int) $dashboard['products_total']; ?> productos creados</div>
        </div>
      </div>

      <div class="charts-row">
        <div class="card">
          <div class="card-header">
            <h3>Ventas últimos 6 meses</h3>
            <span class="badge badge-muted"><span class="badge-dot"></span> Tiempo real</span>
          </div>
          <div class="card-body">
            <div id="dashboard-monthly-sales">
              <?php if (!empty($dashboard['monthly_sales']) && array_sum(array_column($dashboard['monthly_sales'], 'total')) > 0): ?>
                <div class="dashboard-mini-chart">
                  <?php
                  $maxSales = max(array_column($dashboard['monthly_sales'], 'total')) ?: 1;
                  foreach ($dashboard['monthly_sales'] as $monthRow):
                    $width = $maxSales > 0 ? round(($monthRow['total'] / $maxSales) * 100) : 0;
                  ?>
                    <div class="dashboard-bar-row">
                      <span class="dashboard-bar-label"><?php echo htmlspecialchars($monthRow['label'] . ' ' . $monthRow['year']); ?></span>
                      <div class="dashboard-bar-track"><div class="dashboard-bar-fill" style="width: <?php echo $width; ?>%;"></div></div>
                      <strong><?php echo '$' . number_format($monthRow['total'], 0, ',', '.'); ?></strong>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <div class="empty-table">Todavía no hay pedidos para graficar ventas.</div>
              <?php endif; ?>
            </div>
          </div>
        </div>
        
        <div class="card">
          <div class="card-header"><h3>Productos por categoría</h3></div>
          <div class="card-body-flush">
            <div class="donut-wrap" id="dashboard-category-breakdown">
              <?php if (!empty($dashboard['category_counts'])): ?>
                <?php
                $categoryTotal = array_sum(array_column($dashboard['category_counts'], 'total')) ?: 1;
                foreach ($dashboard['category_counts'] as $categoryRow):
                  $percent = round(($categoryRow['total'] / $categoryTotal) * 100);
                ?>
                  <div class="donut-item">
                    <div class="donut-dot" style="background:#8B5E3C"></div>
                    <span><?php echo htmlspecialchars($categoryRow['category']); ?></span>
                    <strong><?php echo $percent; ?>% · <?php echo (int) $categoryRow['total']; ?></strong>
                  </div>
                <?php endforeach; ?>
              <?php else: ?>
                <div class="empty-table">Aún no has creado productos por categoría.</div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <h3>Pedidos recientes</h3>
        </div>
        <div class="card-body-flush table-wrap" id="dashboard-recent-orders">
          <?php if (!empty($dashboard['recent_orders'])): ?>
            <table>
              <thead>
                <tr><th>Cliente</th><th>Estado</th><th>Total</th><th>Fecha</th></tr>
              </thead>
              <tbody>
                <?php foreach ($dashboard['recent_orders'] as $order): ?>
                  <tr>
                    <td><strong><?php echo htmlspecialchars($order['cliente_nombre']); ?></strong></td>
                    <td><span class="badge badge-muted"><?php echo htmlspecialchars($order['estado']); ?></span></td>
                    <td><?php echo '$' . number_format($order['total'], 0, ',', '.'); ?></td>
                    <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($order['created_at']))); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php else: ?>
            <div class="empty-table">No se han registrado pedidos recientes.</div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="page-content" id="page-orders">
      <div class="section-title">
        <div><h2>Pedidos</h2><p>Gestiona y actualiza el estado de cada orden</p></div>
      </div>
      <div class="filter-bar">
        <div class="search-wrap">
          <i class="fas fa-search"></i>
          <input class="search-input" type="text" placeholder="Buscar por cliente o #pedido..."/>
        </div>
        <select class="filter-select">
          <option value="">Todos los estados</option>
          <option value="pendiente">Pendiente</option>
          <option value="pagado">Pagado</option>
          <option value="enviado">Enviado</option>
          <option value="entregado">Entregado</option>
          <option value="cancelado">Cancelado</option>
        </select>
      </div>
      <div class="card">
        <div class="card-body-flush table-wrap">
          <div class="empty-table">No hay pedidos registrados en el sistema.</div>
        </div>
      </div>
    </div>

    <div class="page-content" id="page-products">
      <div class="section-title">
        <div><h2>Productos</h2><p>Catálogo completo de la tienda</p></div>
        <button class="btn-new" onclick="navigate('new-product',document.querySelector('.nav-item[onclick*=\'new-product\']'))">
          <i class="fas fa-plus"></i> Nuevo Producto
        </button>
      </div>
      <div class="filter-bar">
        <div class="search-wrap">
          <i class="fas fa-search"></i>
          <input class="search-input" type="text" placeholder="Buscar producto..."/>
        </div>
        <select class="filter-select">
          <option>Todas las categorías</option>
          <option>Trufas</option>
          <option>Barras</option>
          <option>Bombones</option>
        </select>
      </div>
      <div class="card">
        <div class="card-body-flush table-wrap">
          <?php
          try {
              // Consulta usando PDO
              $sql = "SELECT * FROM productos ORDER BY id DESC";
              $stmt = $conexion->query($sql);
              $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

              if ($productos && count($productos) > 0) {
                  echo '<table>';
                  echo '<thead><tr><th>Img</th><th>Nombre</th><th>Categoría</th><th>Precio</th><th>Stock</th><th>Acción</th></tr></thead>';
                  echo '<tbody>';
                  foreach ($productos as $prod) {
                      $img = !empty($prod['imagen']) ? $prod['imagen'] : 'https://via.placeholder.com/40';
                      echo '<tr>';
                      echo '<td><img src="'.htmlspecialchars($img).'" width="40" style="border-radius:4px; object-fit:cover; height:40px;"></td>';
                      echo '<td><strong>'.htmlspecialchars($prod['nombre']).'</strong></td>';
                      echo '<td><span class="badge badge-muted">'.htmlspecialchars(ucfirst($prod['categoria'])).'</span></td>';
                      echo '<td>$'.number_format($prod['precio'], 0, ',', '.').'</td>';
                      echo '<td>'.htmlspecialchars($prod['stock']).'</td>';
                      echo '<td><form method="POST" action="admin.php" style="display:inline;margin:0;" onsubmit="return confirm(\'¿Seguro que deseas borrar este producto?\');"><input type="hidden" name="csrf_token" value="'.htmlspecialchars(csrf_token()).'" /><input type="hidden" name="delete_id" value="'.htmlspecialchars($prod['id']).'" /><button type="submit" name="btn_delete_product" class="btn-sm" style="color:var(--danger); background:var(--danger-bg);"><i class="fas fa-trash"></i> Borrar</button></form></td>';
                      echo '</tr>';
                  }
                  echo '</tbody></table>';
              } else {
                  echo '<div class="empty-table">Aún no has agregado productos a la base de datos.</div>';
              }
          } catch (PDOException $e) {
              // Si falla la base de datos, mostramos el error de forma segura sin romper el javascript
              echo '<div class="alert-box" style="margin: 1.5rem;"><i class="fas fa-exclamation-triangle"></i> Error al cargar los productos: ' . $e->getMessage() . '</div>';
          }
          ?>
        </div>
      </div>
    </div>

    <div class="page-content" id="page-new-product">
      <div class="section-title">
        <div><h2>Nuevo Producto</h2><p>Completa los datos del producto</p></div>
      </div>
      <div class="card">
        <div class="card-header"><h3>Información del producto</h3></div>
        <div class="card-body">
          <form method="POST" action="admin.php">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>" />
            <div class="form-grid">
              <div class="form-group">
                <label class="form-label">Nombre</label>
                <input class="form-input" type="text" name="np_name" placeholder="Ej: Trufa de Frambuesa" required/>
              </div>
              <div class="form-group">
                <label class="form-label">Categoría</label>
                <input class="form-input" type="text" name="np_cat" placeholder="Ej: Trufa, Barra, Bombón..." required/>
              </div>
              <div class="form-group">
                <label class="form-label">Precio (CLP)</label>
                <input class="form-input" type="number" name="np_price" placeholder="Ej: 8900" required/>
              </div>
              <div class="form-group">
                <label class="form-label">Precio original (opcional)</label>
                <input class="form-input" type="number" name="np_original" placeholder="Ej: 10900" />
              </div>
              <div class="form-group">
                <label class="form-label">Stock disponible</label>
                <input class="form-input" type="number" name="np_stock" placeholder="Ej: 24" required/>
              </div>
              <div class="form-group">
                <label class="form-label">URL de imagen</label>
                <input class="form-input" type="url" name="np_img" placeholder="https://..." />
              </div>
              <div class="form-group full">
                <label class="form-label">Descripción corta</label>
                <input class="form-input" type="text" name="np_desc" placeholder="Descripción visible en la card" />
              </div>
            </div>
            
            <div class="form-actions">
               <button type="button" class="btn-sm btn-sm-outline" onclick="navigate('products', document.querySelector('.nav-item[onclick*=\'products\']'))">Cancelar</button>
               <button type="submit" name="btn_save_product" class="btn-save"><i class="fas fa-save"></i> Guardar Producto</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="page-content" id="page-users">
      <div class="section-title">
        <div><h2>Usuarios</h2><p>Administración de cuentas con acceso</p></div>
      </div>
      <div class="card">
        <div class="card-header"><h3>Nuevo Usuario</h3></div>
        <div class="card-body">
          <form method="POST" action="admin.php">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>" />
            <div class="form-grid">
              <div class="form-group">
                <label class="form-label">Nombre</label>
                <input class="form-input" type="text" name="user_name" placeholder="Ej: Camila Pérez" required />
              </div>
              <div class="form-group">
                <label class="form-label">Email</label>
                <input class="form-input" type="email" name="user_email" placeholder="Ej: camila@correo.cl" required />
              </div>
              <div class="form-group">
                <label class="form-label">Teléfono</label>
                <input class="form-input" type="tel" name="user_phone" placeholder="Ej: +56912345678" />
              </div>
              <div class="form-group full">
                <label class="form-label">Dirección</label>
                <input class="form-input" type="text" name="user_address" placeholder="Dirección o referencia" />
              </div>
            </div>
            <div class="form-actions">
              <button type="submit" name="btn_save_user" class="btn-save"><i class="fas fa-user-plus"></i> Guardar Usuario</button>
            </div>
          </form>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h3>Usuarios Registrados</h3></div>
        <div class="card-body-flush table-wrap">
          <?php if ($usuarios && count($usuarios) > 0): ?>
            <table>
              <thead>
                <tr><th>Nombre</th><th>Email</th><th>Teléfono</th><th>Dirección</th><th>Acción</th></tr>
              </thead>
              <tbody>
                <?php foreach ($usuarios as $usuario): ?>
                  <tr>
                    <td><strong><?php echo htmlspecialchars($usuario['nombre']); ?></strong></td>
                    <td><?php echo htmlspecialchars($usuario['email']); ?></td>
                    <td><?php echo htmlspecialchars($usuario['telefono'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($usuario['direccion'] ?? ''); ?></td>
                    <td>
                      <form method="POST" action="admin.php" style="display:inline;margin:0;" onsubmit="return confirm('¿Seguro que deseas borrar este usuario?');">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>" />
                        <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($usuario['id']); ?>" />
                        <button type="submit" name="btn_delete_user" class="btn-sm" style="color:var(--danger); background:var(--danger-bg);"><i class="fas fa-trash"></i> Borrar</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php else: ?>
            <div class="empty-table">Aún no hay usuarios registrados.</div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="page-content" id="page-settings">
      <div class="section-title">
        <div><h2>Configuración</h2><p>Ajustes generales del sistema</p></div>
      </div>

      <div class="card">
        <div class="card-header">
          <h3><i class="fas fa-cog"></i> Datos de la Tienda</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="admin.php">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>" />
            <div class="form-grid">
              <div class="form-group full">
                <label class="form-label">Nombre de la tienda</label>
                <input class="form-input" type="text" name="store_name" value="<?php echo htmlspecialchars($site_store_name); ?>" placeholder="Nombre de tu tienda" />
              </div>
              <div class="form-group">
                <label class="form-label">Email de contacto</label>
                <input class="form-input" type="email" name="store_email" value="<?php echo htmlspecialchars($site_store_email); ?>" placeholder="contacto@chocolatesxime.cl" />
              </div>
              <div class="form-group">
                <label class="form-label">Teléfono</label>
                <input class="form-input" type="tel" name="store_phone" value="<?php echo htmlspecialchars($site_store_phone); ?>" placeholder="+56912345678" />
              </div>
              <div class="form-group full">
                <label class="form-label">URL de Backend (MercadoPago)</label>
                <input class="form-input" type="url" name="backend_url" value="<?php echo htmlspecialchars($site_backend_url); ?>" placeholder="https://tu-backend.cl" />
              </div>
              <div class="form-group full">
                <label class="form-label">Imagen portada - El placer puro del cacao</label>
                <input class="form-input" type="url" name="hero_image" value="<?php echo htmlspecialchars($site_hero_image); ?>" placeholder="https://..." />
              </div>
              <div class="form-group full">
                <label class="form-label">Imagen - Sobre nosotros</label>
                <input class="form-input" type="url" name="about_image" value="<?php echo htmlspecialchars($site_about_image); ?>" placeholder="https://..." />
              </div>
            </div>
              <div class="form-actions">
                <button type="submit" name="btn_save_settings" class="btn-save"><i class="fas fa-save"></i> Guardar Cambios</button>
              </div>
          </form>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <h3><i class="fas fa-image"></i> Vista previa rápida</h3>
        </div>
        <div class="card-body">
          <div class="form-grid">
            <div class="form-group full">
              <label class="form-label">Portada</label>
              <img src="<?php echo htmlspecialchars($site_hero_image); ?>" alt="Portada" style="width:100%;max-height:220px;object-fit:cover;border-radius:var(--radius-sm);border:1px solid var(--cream-border);" />
            </div>
            <div class="form-group full">
              <label class="form-label">Sobre nosotros</label>
              <img src="<?php echo htmlspecialchars($site_about_image); ?>" alt="Sobre nosotros" style="width:100%;max-height:220px;object-fit:cover;border-radius:var(--radius-sm);border:1px solid var(--cream-border);" />
            </div>
          </div>
        </div>
      </div>

    </div>

  </div> 
</div> 

<script>
  function navigate(pageId, element) {
    document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));
    if(element) element.classList.add('active');
    
    document.querySelectorAll('.page-content').forEach(el => el.classList.remove('active'));
    
    const targetPage = document.getElementById('page-' + pageId);
    if(targetPage) {
        targetPage.classList.add('active');
      targetPage.scrollIntoView({ behavior: 'smooth', block: 'start' });
      const firstField = targetPage.querySelector('input, textarea, select, button');
      if (firstField) {
        setTimeout(() => firstField.focus(), 100);
      }
    }

    const titleMap = {
        'dashboard': 'Dashboard',
        'orders': 'Gestión de Pedidos',
        'products': 'Catálogo',
        'new-product': 'Crear Producto',
        'users': 'Usuarios',
        'settings': 'Configuración'
    };
    document.getElementById('topbar-title').innerText = titleMap[pageId] || 'Panel';
  }

  function toggleSidebar() {
      const sidebar = document.getElementById('sidebar');
      sidebar.classList.toggle('open');
  }

  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.get('msg') === 'creado' || urlParams.get('msg') === 'borrado') {
      const navBtn = document.querySelector('.nav-item[onclick*="products"]');
      if (navBtn) navigate('products', navBtn);
  }

  async function refreshDashboard() {
    const dashboardSections = [
      document.getElementById('dashboard-sales-this-month'),
      document.getElementById('dashboard-orders-total'),
      document.getElementById('dashboard-users-total'),
      document.getElementById('dashboard-products-active'),
      document.getElementById('dashboard-pending-orders'),
      document.getElementById('dashboard-monthly-sales'),
      document.getElementById('dashboard-category-breakdown'),
      document.getElementById('dashboard-recent-orders'),
    ].filter(Boolean);

    try {
      dashboardSections.forEach(el => el.classList.add('dashboard-live-loading'));
      const response = await fetch('get_dashboard_data.php', { cache: 'no-store' });
      const data = await response.json();

      if (data.error) return;

      const money = (value) => '$' + Number(value || 0).toLocaleString('es-CL');
      document.getElementById('dashboard-sales-this-month').textContent = money(data.sales_this_month);
      document.getElementById('dashboard-orders-total').textContent = data.orders_total ?? 0;
      document.getElementById('dashboard-users-total').textContent = data.users_total ?? 0;
      document.getElementById('dashboard-products-active').textContent = data.products_active ?? 0;
      document.getElementById('dashboard-pending-orders').textContent = data.pending_orders ?? 0;

      const monthlySalesEl = document.getElementById('dashboard-monthly-sales');
      if (monthlySalesEl) {
        if (data.monthly_sales && data.monthly_sales.length && data.monthly_sales.some(item => Number(item.total) > 0)) {
          const maxSales = Math.max(...data.monthly_sales.map(item => Number(item.total) || 0), 1);
          monthlySalesEl.innerHTML = `<div class="dashboard-mini-chart">${data.monthly_sales.map(item => {
            const total = Number(item.total) || 0;
            const width = Math.round((total / maxSales) * 100);
            return `<div class="dashboard-bar-row"><span class="dashboard-bar-label">${item.label} ${item.year}</span><div class="dashboard-bar-track"><div class="dashboard-bar-fill" style="width:${width}%;"></div></div><strong>${money(total)}</strong></div>`;
          }).join('')}</div>`;
        } else {
          monthlySalesEl.innerHTML = '<div class="empty-table">Todavía no hay pedidos para graficar ventas.</div>';
        }
      }

      const categoryEl = document.getElementById('dashboard-category-breakdown');
      if (categoryEl) {
        if (data.category_counts && data.category_counts.length) {
          const total = data.category_counts.reduce((acc, item) => acc + (Number(item.total) || 0), 0) || 1;
          categoryEl.innerHTML = data.category_counts.map((item, index) => {
            const percent = Math.round(((Number(item.total) || 0) / total) * 100);
            const palette = ['#C9993A', '#8B5E3C', '#5D3A1A', '#E8DDD0'];
            return `<div class="donut-item"><div class="donut-dot" style="background:${palette[index % palette.length]};"></div><span>${item.category}</span><strong>${percent}% · ${item.total}</strong></div>`;
          }).join('');
        } else {
          categoryEl.innerHTML = '<div class="empty-table">Aún no has creado productos por categoría.</div>';
        }
      }

      const ordersEl = document.getElementById('dashboard-recent-orders');
      if (ordersEl) {
        if (data.recent_orders && data.recent_orders.length) {
          ordersEl.innerHTML = `<table><thead><tr><th>Cliente</th><th>Estado</th><th>Total</th><th>Fecha</th></tr></thead><tbody>${data.recent_orders.map(order => `<tr><td><strong>${order.cliente_nombre}</strong></td><td><span class="badge badge-muted">${order.estado}</span></td><td>${money(order.total)}</td><td>${new Date(order.created_at).toLocaleString('es-CL', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })}</td></tr>`).join('')}</tbody></table>`;
        } else {
          ordersEl.innerHTML = '<div class="empty-table">No se han registrado pedidos recientes.</div>';
        }
      }

      const ordersBadge = document.getElementById('badge-orders');
      if (ordersBadge) {
        const pendingOrders = Number(data.pending_orders || 0);
        ordersBadge.textContent = pendingOrders;
        ordersBadge.classList.toggle('hidden', pendingOrders === 0);
      }
    } catch (error) {
      console.error('Error al actualizar el dashboard:', error);
    } finally {
      dashboardSections.forEach(el => el.classList.remove('dashboard-live-loading'));
    }
  }

  refreshDashboard();
  setInterval(refreshDashboard, 15000);
</script>

<?php endif; ?>
</body>
</html> 