# 🍫 Chocolates Xime - Guía de Instalación y Configuración

## ✅ Requisitos Previos

- **PHP 7.4+** (con soporte para PDO MySQL)
- **MySQL 5.7+** o **MariaDB 10.2+**
- **Servidor web** (Apache, Nginx, etc.)
- **Navegador moderno** (Chrome, Firefox, Safari, Edge)

---

## 📦 Paso 1: Preparar la Base de Datos

### Opción A: phpMyAdmin
1. Abre **phpMyAdmin** en tu panel de hosting
2. Copia el contenido de `schema.sql`
3. Pega en la pestaña "SQL" y ejecuta
4. Verifica que se creó la BD `chocolates_xime`

### Opción B: Línea de comandos MySQL
```bash
mysql -u root -p < schema.sql
```

---

## ⚙️ Paso 2: Configurar la Conexión a BD

### Editar `conexion.php`

Abre el archivo y modifica estas líneas según tu hosting:

```php
$host       = 'localhost';      // Cambiar si tu BD está en otro servidor
$db_name    = 'chocolates_xime'; // Nombre de tu BD
$username   = 'root';            // Usuario de MySQL
$password   = '';                // Contraseña de MySQL
$port       = 3306;              // Puerto de MySQL (usualmente 3306)
```

**Ejemplos comunes:**
- **Hosting compartido (Bluehost, SiteGround, etc.):** Pregunta a soporte por el host
- **Localhost:** `localhost`
- **Docker/Contenedores:** `mysql-container`

### Configurar Credenciales del Admin

En la misma archivo `conexion.php`, cambia:

```php
$username = 'admin_xime';           // Tu usuario de admin
$password = 'ChocolatesXime2024';  // TU CONTRASEÑA SEGURA (⚠️ CAMBIA ESTO)
```

⚠️ **IMPORTANTE:** Cambia la contraseña a algo seguro y única.

---

## 🌐 Paso 3: Subir Archivos al Servidor

Sube estos archivos a la raíz de tu sitio:

```
/public_html/
├── admin.php          ✅
├── app.js             ✅
├── conexion.php       ✅ (Recién creado)
├── get_productos.php  ✅ (Recién creado)
├── index.html         ⚠️ (Necesita reconstrucción)
├── schema.sql         (Solo para referencia)
└── README.md          
```

---

## 🚀 Paso 4: Acceder al Panel Admin

1. Abre en tu navegador: `https://tu-dominio.cl/admin.php`
2. Usa las credenciales configuradas en `conexion.php`:
   - **Usuario:** `admin_xime` (o la que configuraste)
   - **Contraseña:** La que configuraste en conexion.php

3. Deberías ver el Dashboard

---

## 🛠️ Paso 5: Crear Productos

1. En el admin, ve a **Tienda → Nuevo Producto**
2. Rellena el formulario:
   - **Nombre:** Ej: "Trufa de Frambuesa"
   - **Categoría:** Puedes escribir cualquier categoría (Trufa, Barra, Bombón, etc.)
   - **Precio:** El precio actual
   - **Precio original (opcional):** Para mostrar descuento
   - **Stock:** Unidades disponibles
   - **URL de imagen:** Link a la imagen (Ej: `https://ejemplo.com/imagen.jpg`)
   - **Descripción:** Texto corto visible en las tarjetas

3. Haz clic en **"Guardar Producto"**

---

## 🔗 Paso 6: Verificar Conexión con Index

### Probar que index.html obtiene los productos:

1. Abre **Inspector de navegador** (F12)
2. Ve a la pestaña **Console**
3. Deberías ver que cargó los productos desde `get_productos.php`
4. Si hay error, revisar:
   - ¿Existe `get_productos.php`?
   - ¿Está en la misma carpeta que `index.html`?
   - ¿Está activa la conexión a BD en `conexion.php`?

---

## 📝 Problemas Comunes

### ❌ "Error: No se pudieron leer las credenciales en conexion.php"
**Solución:** Verifica que:
- Existe el archivo `conexion.php`
- Las credenciales están correctas
- No hay espacios en blanco al inicio del archivo

### ❌ "Error de conexión a BD: Access denied"
**Solución:**
- Verifica usuario/contraseña en `conexion.php`
- En hosting compartido, pregunta a soporte las credenciales correctas

### ❌ "No carga el catálogo en index.html"
**Solución:**
- Abre **Inspector (F12) → Console**
- Busca errores de red
- Verifica que `get_productos.php` devuelva JSON válido
- Prueba accediendo a: `https://tu-dominio.cl/get_productos.php`

### ❌ "CORS error al conectar"
**Solución:** Si tienes backend separado, configura CORS:
```php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
```

---

## 🔐 Seguridad Recomendada

### 1. Cambiar contraseñas por defecto
En `conexion.php`:
```php
$password = 'MiContraseñaSegura2024'; // Cambia esto
```

### 2. Proteger archivos sensibles
Si usas Apache, crea `.htaccess`:
```apache
<FilesMatch "\.(php|sql|env)$">
    deny from all
</FilesMatch>
```

### 3. Usar HTTPS
- Activa SSL en tu hosting
- Todos los usuarios accederán por `https://`

---

## 📦 Funcionalidades Incluidas

✅ **Admin Panel:**
- Dashboard con estadísticas
- Crear productos libremente
- Editar/eliminar productos
- Gestión de pedidos (estructura lista)
- Configuración del sistema

✅ **Frontend (Index):**
- Catálogo dinámico desde BD
- Carrito de compras
- Integración MercadoPago
- Reseñas de clientes

✅ **API:**
- `get_productos.php` - Obtiene productos en JSON

---

## 🔮 Próximos Pasos (Opcionales)

1. **Configurar MercadoPago:**
   - Crea cuenta en https://www.mercadopago.cl
   - Obtén tu Access Token
   - Configura backend en Node.js

2. **Crear Backend (Node.js):**
   - Para procesar pagos de MercadoPago
   - Webhook para recibir confirmaciones
   - Envío de emails

3. **Mejorar UI:**
   - Reconstruir `index.html` con HTML semántico
   - Agregar más CSS responsive

---

## 📞 Soporte

Si tienes problemas:
1. Verifica que todos los pasos estén completos
2. Revisa la consola del navegador (F12)
3. Consulta con el soporte de tu hosting

¡Éxito con tu tienda de chocolates! 🍫✨

---

**Última actualización:** 2026-06-17
