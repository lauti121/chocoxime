/* ============================================================
   CHOCOLATES XIME — app.js (REVISADO Y COMPLETO)
   Lógica del sitio: productos, carrito, navegación, MercadoPago
============================================================ */

/* ── ESTADO GLOBAL ───────────────────────────────────────── */
let PRODUCTS = []; 
let cart = [];
let currentProduct = null;
let currentQty = 1;
let selectedVariant = "Única";
let selectedStar = 0;
let currentUser = null;
let history = [];

const BACKEND_URL = 'https://tu-backend.cl'; // ⚠️ Cambia por la URL real de tu API/Server Node

/* ── UTILIDADES ──────────────────────────────────────────── */
const fmt = (n) => '$' + Number(n).toLocaleString('es-CL');

function showToast(msg) {
  const t = document.getElementById('toast');
  if(!t) return;
  t.textContent = msg;
  t.classList.remove('hidden');
  clearTimeout(window._toastTimer);
  window._toastTimer = setTimeout(() => t.classList.add('hidden'), 3000);
}

/* ── CONEXIÓN CON LA BASE DE DATOS ───────────────────────── */
async function loadProductsFromDB() {
  try {
    const response = await fetch('get_productos.php'); 
    const dataFromDB = await response.json();
    
    if (dataFromDB.error) {
      console.error(dataFromDB.error);
      return;
    }

    // Traducimos los datos del Backend al formato del Frontend dinámicamente
    PRODUCTS = dataFromDB.map(dbProd => ({
      id: Number(dbProd.id),
      name: dbProd.nombre,
      category: dbProd.categoria,
      price: Number(dbProd.precio),
      original: dbProd.precio_original ? Number(dbProd.precio_original) : null,
      stock: Number(dbProd.stock),
      img: dbProd.imagen || 'https://via.placeholder.com/400',
      desc: dbProd.descripcion || '',
      fullDesc: dbProd.descripcion || '',
      variants: ['Única'], 
      imgs: [dbProd.imagen || 'https://via.placeholder.com/400'], 
      comments: []
    }));
    
    renderHomeGrid();
    renderFullCatalog('all');
  } catch (error) {
    console.error("Error al cargar los productos:", error);
    showToast("Hubo un problema al cargar el catálogo.");
  }
}

/* ── NAVEGACIÓN ──────────────────────────────────────────── */
function showPage(id) {
  ['page-home','page-catalog','page-product'].forEach(p => {
    const el = document.getElementById(p);
    if(el) el.classList.add('hidden');
  });
  const target = document.getElementById(id);
  if(target) target.classList.remove('hidden');
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function showHome() {
  history = [];
  showPage('page-home');
}

function showCatalog() {
  history.push('catalog');
  showPage('page-catalog');
}

function showProduct(id) {
  history.push('product');
  currentProduct = PRODUCTS.find(p => p.id === Number(id));
  if (!currentProduct) return;
  
  currentQty = 1;
  selectedVariant = currentProduct.variants[0] || 'Única';
  selectedStar = 0;
  renderProductPage();
  showPage('page-product');
}

function goBack() {
  if (history.length > 1) {
    history.pop();
    const prev = history[history.length - 1];
    if (prev === 'catalog') showCatalog();
    else showHome();
  } else {
    showHome();
  }
}

/* ── RENDERS DEL CATÁLOGO ────────────────────────────────── */
function renderHomeGrid() {
  const el = document.getElementById('home-catalog-grid');
  if (!el) return;
  // Mostramos los primeros 3 productos destacados en el index
  el.innerHTML = PRODUCTS.slice(0, 3).map(p => productCardHTML(p)).join('');
}

function renderFullCatalog(filter) {
  const el = document.getElementById('full-catalog-grid');
  if (!el) return;
  const list = filter === 'all' ? PRODUCTS : PRODUCTS.filter(p => p.category === filter);
  el.innerHTML = list.map(p => productCardHTML(p)).join('');
}

function productCardHTML(p) {
  const lowStock = p.stock < 6;
  return `
    <div class="product-card" onclick="showProduct(${p.id})">
      <div class="product-card-img">
        <img src="${p.img}" alt="${p.name}" loading="lazy"/>
      </div>
      <div class="product-card-body">
        <h3>${p.name}</h3>
        <p class="stock-label ${lowStock ? 'low' : ''}">
          ${p.stock === 0 ? '❌ Sin Stock' : (lowStock ? `⚠ Solo ${p.stock} disponibles` : `✔ En stock (${p.stock} uds)`)}
        </p>
        <div class="price-row">
          <span class="price-main">${fmt(p.price)}</span>
          ${p.original ? `<span class="price-original">${fmt(p.original)}</span>` : ''}
        </div>
        <button class="btn-primary" onclick="event.stopPropagation(); showProduct(${p.id})">Ver detalles</button>
      </div>
    </div>
  `;
}

function filterCatalog(filter, btn) {
  document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
  if(btn) btn.classList.add('active');
  renderFullCatalog(filter);
}

/* ── DETALLE DEL PRODUCTO ────────────────────────────────── */
function renderProductPage() {
  const p = currentProduct;
  if (!p) return;

  document.getElementById('pd-img').src = p.img;
  document.getElementById('pd-name').textContent = p.name;
  document.getElementById('pd-cat').textContent = p.category.toUpperCase();
  document.getElementById('pd-price').textContent = fmt(p.price);
  document.getElementById('pd-original').textContent = p.original ? fmt(p.original) : '';
  document.getElementById('pd-desc').textContent = p.desc;
  document.getElementById('pd-full-desc').textContent = p.fullDesc;
  
  const totalComments = p.comments ? p.comments.length : 0;
  document.getElementById('pd-reviews-count').textContent = `${totalComments} reseñas`;
  document.getElementById('review-count-tab').textContent = `(${totalComments})`;
  document.getElementById('qty-display').textContent = currentQty;

  // Lógica Visual de Stock
  const si = document.getElementById('pd-stock-indicator');
  const st = document.getElementById('pd-stock-text');
  if (p.stock === 0) {
    si.className = 'stock-indicator out';
    st.textContent = 'Sin stock disponible';
  } else if (p.stock < 6) {
    si.className = 'stock-indicator low';
    st.textContent = `¡Solo ${p.stock} unidades de reserva!`;
  } else {
    si.className = 'stock-indicator';
    st.textContent = `${p.stock} unidades en stock`;
  }

  // Miniaturas
  document.getElementById('pd-thumbs').innerHTML = p.imgs.map((img, i) =>
    `<img src="${img}" class="gallery-thumb ${i===0?'active':''}" onclick="changeMainImg(this,'${img}')"/>`
  ).join('');

  // Variantes
  document.getElementById('pd-variants').innerHTML = p.variants.map((v, i) =>
    `<div class="variant-chip ${i===0?'active':''}" onclick="selectVariant(this,'${v}')">${v}</div>`
  ).join('');

  renderComments();
}

function changeMainImg(thumb, src) {
  document.getElementById('pd-img').src = src;
  document.querySelectorAll('.gallery-thumb').forEach(t => t.classList.remove('active'));
  thumb.classList.add('active');
}

function selectVariant(el, v) {
  document.querySelectorAll('.variant-chip').forEach(c => c.classList.remove('active'));
  el.classList.add('active');
  selectedVariant = v;
}

function changeQty(delta) {
  if (!currentProduct) return;
  currentQty = Math.max(1, Math.min(currentProduct.stock, currentQty + delta));
  document.getElementById('qty-display').textContent = currentQty;
}

/* ── SISTEMA DE CARRITO DE COMPRAS ───────────────────────── */
function toggleCart() {
  const o = document.getElementById('cart-overlay');
  if(!o) return;
  o.classList.toggle('hidden');
  if (!o.classList.contains('hidden')) renderCartPanel();
}

function addCurrentToCart() {
  const p = currentProduct;
  if (!p || p.stock === 0) { showToast('Producto sin stock disponible.'); return; }
  
  const existing = cart.find(i => i.id === p.id && i.variant === selectedVariant);
  if (existing) {
    existing.qty = Math.min(existing.qty + currentQty, p.stock);
  } else {
    cart.push({ id: p.id, name: p.name, price: p.price, img: p.img, variant: selectedVariant, qty: currentQty });
  }
  updateCartBadge();
  showToast(`✔ "${p.name}" añadido al carrito`);
}

function updateCartBadge() {
  const total = cart.reduce((acc, item) => acc + item.qty, 0);
  document.getElementById('cart-badge').textContent = total;
}

function renderCartPanel() {
  const el = document.getElementById('cart-items-list');
  if (!el) return;
  
  if (cart.length === 0) {
    el.innerHTML = `<div class="cart-empty"><i class="fas fa-shopping-bag"></i><p>Tu carrito está vacío</p></div>`;
    document.getElementById('cart-total-display').textContent = '$0';
    return;
  }

  el.innerHTML = cart.map((item, idx) => `
    <div class="cart-item">
      <img src="${item.img}" alt="${item.name}"/>
      <div class="cart-item-info">
        <h4>${item.name}</h4>
        <p class="variant-tag">${item.variant}</p>
        <div class="cart-item-qty">
          <button onclick="changeCartQty(${idx},-1)">−</button>
          <span>${item.qty}</span>
          <button onclick="changeCartQty(${idx},1)">+</button>
          <span class="cart-item-remove" onclick="removeCartItem(${idx})"><i class="fas fa-trash-alt"></i></span>
        </div>
      </div>
      <div class="cart-item-price">${fmt(item.price * item.qty)}</div>
    </div>
  `).join('');

  const total = cart.reduce((acc, item) => acc + (item.price * item.qty), 0);
  document.getElementById('cart-total-display').textContent = fmt(total);
}

function changeCartQty(idx, delta) {
  const targetProduct = PRODUCTS.find(x => x.id === cart[idx].id);
  const maxStock = targetProduct ? targetProduct.stock : 99;
  cart[idx].qty = Math.max(1, Math.min(maxStock, cart[idx].qty + delta));
  renderCartPanel();
  updateCartBadge();
}

function removeCartItem(idx) {
  cart.splice(idx, 1);
  renderCartPanel();
  updateCartBadge();
}

/* ── INTEGRACIÓN MERCADOPAGO CHECKOUT PRO ────────────────── */
async function checkout() {
  if (cart.length === 0) { 
    showToast('Tu carrito está vacío.'); 
    return; 
  }
  
  if (!currentUser) {
    showToast('Inicia sesión para continuar con tu compra.');
    document.getElementById('cart-overlay').classList.add('hidden');
    toggleLogin();
    return;
  }

  try {
    showCheckoutLoading(true);

    // Mapeamos los datos limpios para enviar a tu API en Node.js
    const itemsToPay = cart.map(item => ({
      id:       item.id,
      name:     item.name,
      price:    item.price,        
      quantity: item.quantity,
      img:      item.img
    }));

    const payerData = {
      name:    currentUser.name,
      surname: '',
      email:   currentUser.email
    };

    const response = await fetch(`${BACKEND_URL}/api/create-preference`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ items: itemsToPay, payer: payerData }),
    });

    if (!response.ok) throw new Error('Error al generar orden en MercadoPago');

    const data = await response.json();
    
    // Redirección automática al Checkout Pro de MercadoPago
    // Usa 'data.sandbox_init_point' si estás haciendo pruebas locales de desarrollo.
    window.location.href = data.init_point; 

  } catch (error) {
    console.error('Error MP Checkout:', error);
    showCheckoutLoading(false);
    showToast('Error al conectar con MercadoPago. Reintenta.');
  }
}

function showCheckoutLoading(isLoading) {
  const btn = document.querySelector('.btn-primary[onclick*="checkout"]');
  if (!btn) return;
  if (isLoading) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Conectando...';
  } else {
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-lock"></i> Finalizar Compra';
  }
}

/* ── TABS, COMENTARIOS Y LOGIN (RESTO DE COMPONENTES) ────── */
function switchTab(tab, btn) {
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  if(btn) btn.classList.add('active');
  document.getElementById('tab-details').classList.add('hidden');
  document.getElementById('tab-comments').classList.add('hidden');
  document.getElementById('tab-' + tab).classList.remove('hidden');
}

function renderComments() {
  const p = currentProduct;
  const el = document.getElementById('comments-list');
  if(!el) return;
  if (!p.comments || p.comments.length === 0) {
    el.innerHTML = '<p style="color:var(--text-muted);font-style:italic; padding:1rem 0;">Aún no hay reseñas. ¡Sé la primera persona en comentar!</p>';
    return;
  }
  el.innerHTML = p.comments.map(c => `
    <div class="comment-card">
      <div class="comment-header">
        <span class="comment-author">${c.author}</span>
        <span class="comment-stars">${'<i class="fas fa-star"></i>'.repeat(c.stars)}</span>
      </div>
      <p class="comment-text">"${c.text}"</p>
    </div>
  `).join('');
}

function selectStar(val) {
  selectedStar = val;
  document.querySelectorAll('#star-select i').forEach((s, i) => {
    s.classList.toggle('active', i < val);
  });
}

function submitComment() {
  const author = document.getElementById('comment-author').value.trim();
  const text = document.getElementById('comment-text').value.trim();
  if (!author || !text || !selectedStar) { showToast('Por favor completa todo el formulario.'); return; }
  
  if (!currentProduct.comments) currentProduct.comments = [];
  currentProduct.comments.push({ author, stars: selectedStar, text });
  
  document.getElementById('comment-author').value = '';
  document.getElementById('comment-text').value = '';
  selectStar(0);
  renderComments();
  showToast('¡Gracias por tu reseña! ✨');
}

function toggleLogin() {
  document.getElementById('login-overlay').classList.toggle('hidden');
}

function loginGoogle() {
  currentUser = { name: "Usuario Google", email: "usuario@gmail.com" };
  afterLogin();
}

function loginEmail() {
  const email = document.querySelector('.login-input[type="email"]')?.value || '';
  if (!email) { showToast('Ingresa tu correo electrónico.'); return; }
  currentUser = { name: email.split('@')[0], email: email };
  afterLogin();
}

function afterLogin() {
  document.getElementById('login-overlay').classList.add('hidden');
  document.getElementById('user-label').textContent = currentUser.name;
  showToast(`¡Bienvenido, ${currentUser.name}! 🍫`);
}

function toggleMobileNav() {
  document.getElementById('mobile-nav').classList.toggle('hidden');
}

function sendContact(e) {
  e.preventDefault();
  showToast('¡Mensaje enviado con éxito! 💌');
  e.target.reset();
}

/* ── INICIALIZACIÓN DE LA APLICACIÓN ────────────────────── */
window.addEventListener('DOMContentLoaded', () => {
  loadProductsFromDB(); 

  document.getElementById('cart-overlay').addEventListener('click', function(e) {
    if (e.target === this) toggleCart();
  });
  document.getElementById('login-overlay').addEventListener('click', function(e) {
    if (e.target === this) toggleLogin();
  });
});
