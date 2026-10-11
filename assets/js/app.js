const API = 'api/index.php';
const isAdminPage = document.body.dataset.app === 'admin';

const state = {
  categories: [],
  menu: [],
  currentCategory: 'all',
  sort: 'popular',
  cart: [],
  posCart: [],
  posCategory: 'all',
  posSearch: '',
  deliveryStaff: [],
  driverPhone: localStorage.getItem('ck_driver_phone') || '',
  driverPassword: '',
  adminOrders: [],
  notifications: [],
  contactMessages: [],
  customers: [],
  users: [],
  auditLogs: [],
  operations: null,
  kitchenOrders: [],
  reviews: [],
  stats: null,
  checks: [],
  token: localStorage.getItem('ck_token') || '',
  user: JSON.parse(localStorage.getItem('ck_user') || 'null'),
};

const money = (value) => `${Number(value).toLocaleString('fr-FR')} CFA`;
const qs = (selector, root = document) => root.querySelector(selector);
const qsa = (selector, root = document) => [...root.querySelectorAll(selector)];
let toastTimer = null;
let menuPreviewObjectUrl = '';
let adminOrdersTimer = null;
let adminOrdersRefreshing = false;
let latestAdminOrderId = null;

function startAdminOrderUpdates() {
  if (!isAdminPage || adminOrdersTimer) return;
  adminOrdersTimer = setInterval(refreshAdminOrders, 5000);
}

async function refreshAdminOrders() {
  if (!state.token || document.hidden || adminOrdersRefreshing || qs('#admin-content')?.classList.contains('hidden')) return;
  // Preserve the selection while the admin is changing an order.
  if (document.activeElement?.closest('#dashboard-orders, #admin-orders-table')) return;
  adminOrdersRefreshing = true;
  const token = state.token;
  try {
    const payload = await api('admin/summary', { cache: 'no-store' });
    if (state.token !== token) return;
    const latestId = Math.max(0, ...payload.orders.map((order) => Number(order.id)));
    if (latestAdminOrderId !== null && latestId > latestAdminOrderId) {
      const newOrders = payload.orders.filter((order) => Number(order.id) > latestAdminOrderId);
      toast(`${newOrders.length} nouvelle(s) commande(s) reçue(s).`, 'success');
      const alert = qs('#admin-order-alert');
      if (alert) {
        alert.hidden = false;
        alert.textContent = `Nouvelle commande reçue : ${newOrders[0].number}. Consultez l’onglet Commandes.`;
      }
    }
    latestAdminOrderId = latestId;
    state.deliveryStaff = payload.delivery_staff || [];
    renderAdminTables(payload.orders);
    renderKitchenOrders(payload.kitchen_orders || []);
    renderAdminOrderSummary(payload.summary);
  } catch (error) {
    const alert = qs('#admin-order-alert');
    if (alert) {
      alert.hidden = false;
      alert.textContent = 'Actualisation des commandes interrompue. Vérifiez la connexion ou reconnectez-vous.';
    }
  } finally {
    adminOrdersRefreshing = false;
  }
}

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str ?? '';
  return div.innerHTML;
}

function resetForm(selectorOrForm) {
  const form = typeof selectorOrForm === 'string' ? qs(selectorOrForm) : selectorOrForm;
  if (!form || typeof form.reset !== 'function') return null;
  form.reset();
  return form;
}

function setMenuImagePreview(src = '', label = '') {
  const preview = qs('#menu-image-preview');
  const image = qs('#menu-image-preview img');
  const text = qs('#menu-image-preview-text');
  if (!preview || !image || !text) return;

  if (menuPreviewObjectUrl && menuPreviewObjectUrl !== src) {
    URL.revokeObjectURL(menuPreviewObjectUrl);
    menuPreviewObjectUrl = '';
  }

  const cleanSrc = String(src || '').trim();
  preview.hidden = cleanSrc === '';
  image.src = cleanSrc;
  text.textContent = cleanSrc ? label : '';
}

function syncMenuImagePreview() {
  const form = qs('#menu-form');
  if (!form) return;

  const file = form.elements.image_file?.files?.[0];
  if (file) {
    if (menuPreviewObjectUrl) URL.revokeObjectURL(menuPreviewObjectUrl);
    menuPreviewObjectUrl = URL.createObjectURL(file);
    setMenuImagePreview(menuPreviewObjectUrl, file.name);
    return;
  }

  const url = form.elements.image_url?.value || '';
  setMenuImagePreview(url, url);
}

async function api(resource, options = {}) {
  const headers = { 'Content-Type': 'application/json', ...(options.headers || {}) };
  if (isAdminPage && state.token) headers.Authorization = `Bearer ${state.token}`;

  const response = await fetch(`${API}?resource=${resource}`, { ...options, headers });
  const payload = await response.json();

  if (!response.ok || payload.ok === false) {
    throw new Error(payload.message || 'Une erreur est survenue.');
  }

  return payload;
}

function toast(message, type = 'info') {
  const el = qs('#toast');
  if (!el) {
    console[type === 'error' ? 'error' : 'log'](message);
    return;
  }
  el.textContent = message;
  el.classList.remove('success', 'error', 'info');
  el.classList.add(type);
  el.classList.add('show');
  if (toastTimer) clearTimeout(toastTimer);
  toastTimer = setTimeout(() => el.classList.remove('show'), type === 'error' ? 5200 : 3600);
}

function checkoutNotice(message = '', type = 'info') {
  const el = qs('#checkout-notice');
  if (!el) return;
  el.textContent = message;
  el.classList.remove('show', 'success', 'error', 'info');
  if (message) el.classList.add('show', type);
}

const socialPlatforms = [
  ['restaurant_whatsapp', 'WhatsApp', 'WA', 'assets/img/social-whatsapp.svg', (value) => `https://wa.me/${value.replace(/\D/g, '')}`],
  ['social_instagram', 'Instagram', 'IG', 'assets/img/social-instagram.svg'],
  ['social_tiktok', 'TikTok', 'TT'],
  ['social_facebook', 'Facebook', 'f'],
  ['social_youtube', 'YouTube', 'YT'],
];

function inlineNotice(selector, message = '', type = 'info') {
  const el = qs(selector);
  if (!el) return;
  el.textContent = message;
  el.classList.remove('show', 'success', 'error', 'info');
  if (message) el.classList.add('show', type);
}

function renderSocialLinks(settings = state.operations?.settings || {}) {
  const targets = qsa('[data-social-links]');
  if (!targets.length) return;
  const links = socialPlatforms
    .map(([key, label, logo, icon, resolver]) => {
      const value = String(settings[key] || '').trim();
      const url = typeof resolver === 'function' ? resolver(value) : value;
      return { label, logo, icon, url };
    })
    .filter((entry) => entry.url && entry.url !== 'https://wa.me/');
  const html = links.length
    ? links.map((entry) => `<a href="${escapeHtml(entry.url)}" target="_blank" rel="noopener noreferrer" aria-label="${escapeHtml(entry.label)}" title="${escapeHtml(entry.label)}" data-logo="${entry.icon ? '' : entry.logo}">${entry.icon ? `<img src="${entry.icon}" alt="" loading="lazy">` : escapeHtml(entry.label)}</a>`).join('')
    : '<span class="muted">Réseaux sociaux bientôt disponibles.</span>';
  targets.forEach((target) => {
    target.innerHTML = html;
  });
}

function showPage(page) {
  if (!qs(`#page-${page}`)) return;
  closeMobileMenu();
  qsa('.page').forEach((el) => el.classList.remove('active'));
  qsa('[data-page]').forEach((el) => el.classList.toggle('active', el.dataset.page === page));
  qs(`#page-${page}`).classList.add('active');

  if (page === 'menu') renderMenu();
  if (page === 'admin' && state.token) loadAdmin();
  if (page === 'driver' && state.driverPhone && state.driverPassword) loadDriverDashboard().catch((error) => toast(error.message));
  window.scrollTo({ top: 0, behavior: 'smooth' });

  const url = new URL(window.location);
  url.searchParams.set('page', page);
  history.pushState(null, '', url);
}

function showLogin() {
  clearInterval(adminOrdersTimer);
  adminOrdersTimer = null;
  latestAdminOrderId = null;
  if (qs('#admin-order-alert')) qs('#admin-order-alert').hidden = true;
  state.token = '';
  state.user = null;
  localStorage.removeItem('ck_token');
  localStorage.removeItem('ck_user');
  qs('#login-form').classList.remove('hidden');
  qs('#admin-content').classList.add('hidden');
  qs('#logout-admin').classList.add('hidden');
  qs('#admin-nav')?.classList.add('hidden');
  qs('#admin-user').textContent = 'Connectez-vous pour gérer les commandes.';
}

async function logoutAdmin() {
  try {
    if (state.token) {
      await api('auth/logout', { method: 'POST' });
    }
  } catch (error) {
    console.warn(error);
  } finally {
    showLogin();
    toast('Session fermee.');
  }
}

function can(permission) {
  return Boolean(state.user?.permissions?.[permission]);
}

function applyRoleUI() {
  qsa('[data-permission]').forEach((el) => {
    el.classList.toggle('hidden', !can(el.dataset.permission));
  });
  qs('[data-admin-tab="menu"]')?.classList.toggle('hidden', !can('can_manage_menu'));
  qs('[data-admin-tab="delivery"]')?.classList.toggle('hidden', !can('can_manage_delivery'));
  qs('[data-admin-tab="operations"]')?.classList.toggle('hidden', !can('can_manage_operations'));
  qs('[data-admin-tab="stats"]')?.classList.toggle('hidden', !can('can_view_stats'));
  qs('[data-admin-tab="audit"]')?.classList.toggle('hidden', !can('can_view_stats'));
  qs('[data-admin-tab="users"]')?.classList.toggle('hidden', !can('can_manage_users'));
}

function categoryLabel(id) {
  return state.categories.find((category) => category.id === id)?.name || id;
}

function priceLabel(item) {
  return item.max_price ? `${money(item.price)} - ${money(item.max_price)}` : money(item.price);
}

function sortedItems(items) {
  return [...items].sort((a, b) => {
    if (state.sort === 'price-asc') return a.price - b.price;
    if (state.sort === 'price-desc') return b.price - a.price;
    if (Number(b.daily_special) !== Number(a.daily_special)) return Number(b.daily_special) - Number(a.daily_special);
    return Number(b.popular) - Number(a.popular);
  });
}

function foodCard(item) {
  const options = (item.options || []).length
    ? `<div class="option-list">${item.options.map((option) => `
        <label><input type="checkbox" data-option="${item.id}" value="${option.id}"> ${escapeHtml(option.name)}${Number(option.price_delta) ? ` +${money(option.price_delta)}` : ''}</label>
      `).join('')}</div>`
    : '';
  const media = item.image_url
    ? `<img class="food-img" src="${escapeHtml(item.image_url)}" alt="${escapeHtml(item.name)}" loading="lazy">`
    : `<div class="food-emoji">${escapeHtml(item.emoji)}</div>`;
  const action = item.available
    ? `<button class="add" data-add="${item.id}" aria-label="Ajouter ${escapeHtml(item.name)} au panier">+</button>`
    : '<span class="unavailable-badge">Indisponible</span>';

  return `
    <article class="food-card ${item.available ? '' : 'is-unavailable'}">
      ${media}
      <div class="food-body">
        <div class="food-meta">${item.daily_special ? '<span class="daily-badge">Plat du jour</span>' : escapeHtml(categoryLabel(item.category))}</div>
        <strong>${escapeHtml(item.name)}</strong>
        <p>${escapeHtml(item.description)}</p>
        ${item.stock_enabled && item.stock_qty <= item.low_stock_threshold ? '<span class="badge pay-pending">Stock limite</span>' : ''}
        ${options}
        <div class="food-actions">
          <span class="price">${priceLabel(item)}</span>
          ${action}
        </div>
      </div>
    </article>`;
}

function renderFeatured() {
  if (!qs('#featured-grid')) return;
  const featured = sortedItems(state.menu.filter((item) => item.popular)).slice(0, 8);
  qs('#featured-grid').innerHTML = featured.map(foodCard).join('');
  const boardItems = sortedItems(state.menu.filter((item) => item.daily_special || item.popular)).slice(0, 4);
  qs('#featured-board').innerHTML = boardItems.map((item) => `
    <button class="hero-card" data-add="${item.id}">
      <span class="emoji">${escapeHtml(item.emoji)}</span>
      <span>
        <small class="muted">${item.daily_special ? 'Plat du jour' : escapeHtml(categoryLabel(item.category))}</small>
        <strong>${escapeHtml(item.name)}</strong>
        <span class="price">${priceLabel(item)}</span>
      </span>
    </button>
  `).join('');
}

function renderDailySpecials() {
  const section = qs('#daily-specials-grid')?.closest('.section');
  const grid = qs('#daily-specials-grid');
  if (!section || !grid) return;
  const specials = sortedItems(state.menu.filter((item) => item.daily_special && item.available)).slice(0, 3);
  section.classList.toggle('hidden', specials.length === 0);
  grid.innerHTML = specials.map(foodCard).join('');
}

function renderCategories() {
  const categories = [{ id: 'all', name: 'Tous' }, ...state.categories];
  qs('#category-list').innerHTML = categories.map((category) => `
    <button class="${state.currentCategory === category.id ? 'active' : ''}" data-category="${category.id}">
      ${escapeHtml(category.name)}
    </button>
  `).join('');
}

function renderMenu() {
  if (!qs('#menu-grid')) return;
  renderCategories();
  const items = state.currentCategory === 'all'
    ? state.menu
    : state.menu.filter((item) => item.category === state.currentCategory);
  qs('#menu-grid').innerHTML = sortedItems(items).map(foodCard).join('');
}

function cartKey(id, options = []) {
  return `${id}:${[...options].map(Number).sort((a, b) => a - b).join(',')}`;
}

function selectedOptionsFor(id, source) {
  const card = source?.closest?.('.food-card');
  if (!card) return [];
  return qsa(`[data-option="${id}"]:checked`, card).map((input) => Number(input.value));
}

function addToCart(id, source = null) {
  const item = state.menu.find((entry) => Number(entry.id) === Number(id));
  if (!item || !item.available) return;

  const options = selectedOptionsFor(id, source);
  const optionRows = options.map((optionId) => (item.options || []).find((option) => Number(option.id) === Number(optionId))).filter(Boolean);
  const price = item.price + optionRows.reduce((sum, option) => sum + Number(option.price_delta), 0);
  const key = cartKey(id, options);
  const existing = state.cart.find((line) => line.key === key);
  if (existing) existing.qty += 1;
  else state.cart.push({ key, id: item.id, name: item.name, price, qty: 1, options, option_names: optionRows.map((option) => option.name) });

  renderCart();
  toast(`${item.name} ajouté au panier.`);
}

function updateQty(key, delta) {
  const line = state.cart.find((item) => item.key === key);
  if (!line) return;
  line.qty += delta;
  if (line.qty <= 0) state.cart = state.cart.filter((item) => item.key !== key);
  renderCart();
}

function cartTotal() {
  return state.cart.reduce((sum, item) => sum + item.price * item.qty, 0);
}

function posTotal() {
  return state.posCart.reduce((sum, item) => sum + item.price * item.qty, 0);
}

function selectedZone() {
  const id = Number(qs('#delivery-zone')?.value || 0);
  return (state.operations?.zones || []).find((zone) => Number(zone.id) === id) || null;
}

function currentFulfillment() {
  return qs('#checkout-form')?.elements.fulfillment?.value || 'delivery';
}

function datetimeLocalValue(date) {
  const pad = (value) => String(value).padStart(2, '0');
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

function updateScheduleBounds() {
  const input = qs('#scheduled-at');
  if (!input) return;
  const min = new Date(Date.now() + 30 * 60 * 1000);
  const max = new Date(Date.now() + 7 * 24 * 60 * 60 * 1000);
  input.min = datetimeLocalValue(min);
  input.max = datetimeLocalValue(max);
}

function syncScheduleFields() {
  updateScheduleBounds();
  const mode = qs('#schedule-mode')?.value || 'asap';
  const field = qs('#scheduled-at-field');
  const input = qs('#scheduled-at');
  if (field) field.classList.toggle('hidden', mode !== 'later');
  if (input) {
    input.required = mode === 'later';
    if (mode !== 'later') input.value = '';
  }
}

function cartSummary() {
  const subtotal = cartTotal();
  const fulfillment = currentFulfillment();
  const zone = fulfillment === 'delivery' ? selectedZone() : null;
  const deliveryFee = zone ? Number(zone.fee) : 0;
  return { subtotal, deliveryFee, total: subtotal + deliveryFee };
}

function renderCart() {
  if (!qs('#cart')) return;
  const summary = cartSummary();
  qs('#cart-count').textContent = state.cart.reduce((sum, item) => sum + item.qty, 0);
  qs('#cart-total').textContent = money(summary.total);
  if (qs('#cart-subtotal')) qs('#cart-subtotal').textContent = money(summary.subtotal);
  if (qs('#cart-delivery-fee')) qs('#cart-delivery-fee').textContent = money(summary.deliveryFee);

  qs('#cart-items').innerHTML = state.cart.length
    ? state.cart.map((item) => `
      <div class="cart-line">
        <div>
          <strong>${escapeHtml(item.name)}</strong>
          ${item.option_names?.length ? `<div class="muted">${item.option_names.map((n) => escapeHtml(n)).join(', ')}</div>` : ''}
          <div class="muted">${money(item.price * item.qty)}</div>
        </div>
        <div class="qty">
          <button type="button" data-qty="${item.key}" data-delta="-1" aria-label="Diminuer la quantité">−</button>
          <span>${item.qty}</span>
          <button type="button" data-qty="${item.key}" data-delta="1" aria-label="Augmenter la quantité">+</button>
          <button type="button" data-qty="${item.key}" data-delta="${-item.qty}">Retirer</button>
        </div>
      </div>
    `).join('')
    : '<p class="muted">Votre panier est vide.</p>';
}

function toggleCart(open) {
  qs('#cart').classList.toggle('open', open);
  qs('#scrim').classList.toggle('open', open);
}

function statusLabel(status) {
  return {
    pending: 'En attente',
    prep: 'En préparation',
    delivery: 'En livraison',
    done: 'Livrée',
    cancelled: 'Annulée',
  }[status] || status;
}

function paymentMethodLabel(method) {
  return {
    cash: 'A la livraison',
    orange_money: 'Orange Money',
    wave: 'Wave',
  }[method] || method || 'Non défini';
}

function paymentStatusLabel(status) {
  return {
    pending: 'Paiement en attente',
    paid: 'Payé',
    failed: 'Échoué',
    refunded: 'Remboursé',
  }[status] || status || 'Non défini';
}

function paymentHint(order) {
  if (order.payment_method === 'cash') {
    return 'Paiement à remettre au livreur ou à la caisse.';
  }
  if (order.payment_status === 'paid') {
    return `Paiement confirmé. Référence ${order.payment_reference || '-'}.`;
  }
  if (paymentCheckoutUrl(order)) {
    return `Réglez votre commande avec ${paymentMethodLabel(order.payment_method)} en cliquant sur « Payer maintenant ».`;
  }
  return `${paymentMethodLabel(order.payment_method)} sélectionné. Paiement à confirmer par le restaurant. Référence ${order.payment_reference || '-'}.`;
}

function paymentCheckoutUrl(order) {
  if (order.payment_method === 'cash' || order.payment_status !== 'pending' || order.status === 'cancelled') return '';
  try {
    const url = new URL(order.payment_checkout_url);
    return url.protocol === 'https:' ? url.href : '';
  } catch {
    return '';
  }
}

function orderItemsText(order) {
  return order.items.map((item) => `${item.name}${item.options_text ? ` (${item.options_text})` : ''} x${item.qty}`).join(', ');
}

function renderPosCategories() {
  const select = qs('#pos-category');
  if (!select) return;
  const categories = [{ id: 'all', name: 'Toutes categories' }, ...state.categories];
  select.innerHTML = categories.map((category) => `
    <option value="${category.id}" ${state.posCategory === category.id ? 'selected' : ''}>${escapeHtml(category.name)}</option>
  `).join('');
}

function renderPos() {
  const grid = qs('#pos-grid');
  if (!grid) return;
  renderPosCategories();
  const query = state.posSearch.trim().toLowerCase();
  const items = sortedItems(state.menu)
    .filter((item) => item.available)
    .filter((item) => state.posCategory === 'all' || item.category === state.posCategory)
    .filter((item) => !query || `${item.name} ${item.description || ''}`.toLowerCase().includes(query));
  grid.innerHTML = items.length
    ? items.map((item) => `
      <button class="pos-item" type="button" data-pos-add="${item.id}">
        <strong>${escapeHtml(item.name)}</strong>
        <small class="muted">${escapeHtml(categoryLabel(item.category))}</small>
        <span class="price">${priceLabel(item)}</span>
      </button>
    `).join('')
    : '<p class="muted">Aucun plat disponible.</p>';
  renderPosTicket();
}

function renderPosTicket() {
  const lines = qs('#pos-lines');
  if (!lines) return;
  lines.innerHTML = state.posCart.length
    ? state.posCart.map((item) => `
      <div class="cart-line">
        <div>
          <strong>${escapeHtml(item.name)}</strong>
          <div class="muted">${money(item.price)} x ${item.qty}</div>
        </div>
        <div class="qty">
          <button type="button" data-pos-qty="${item.id}" data-delta="-1">-</button>
          <span>${item.qty}</span>
          <button type="button" data-pos-qty="${item.id}" data-delta="1">+</button>
        </div>
      </div>
    `).join('')
    : '<p class="muted">Aucun article sur le ticket.</p>';

  const total = posTotal();
  const received = Number(qs('#pos-amount-received')?.value || 0);
  if (qs('#pos-total')) qs('#pos-total').textContent = money(total);
  if (qs('#pos-change')) qs('#pos-change').textContent = money(Math.max(0, received - total));
}

function addToPos(id) {
  const item = state.menu.find((entry) => Number(entry.id) === Number(id));
  if (!item || !item.available) return;
  const existing = state.posCart.find((line) => Number(line.id) === Number(item.id));
  if (existing) existing.qty += 1;
  else state.posCart.push({ id: item.id, name: item.name, price: Number(item.price), qty: 1 });
  inlineNotice('#pos-notice');
  renderPosTicket();
}

function updatePosQty(id, delta) {
  const line = state.posCart.find((item) => Number(item.id) === Number(id));
  if (!line) return;
  line.qty += delta;
  if (line.qty <= 0) state.posCart = state.posCart.filter((item) => Number(item.id) !== Number(id));
  renderPosTicket();
}

function clearPosTicket() {
  state.posCart = [];
  resetForm('#pos-form');
  const form = qs('#pos-form');
  if (form) {
    form.elements.name.value = 'Client comptoir';
    form.elements.phone.value = '000000000';
    form.elements.status.value = 'prep';
    form.elements.payment_method.value = 'cash';
  }
  inlineNotice('#pos-notice');
  renderPosTicket();
}

function customerCancelToken(id) {
  try { return localStorage.getItem(`ck_cancel_${id}`) || ''; } catch { return ''; }
}

async function cancelCustomerOrder(button) {
  const id = button.dataset.cancelOrder;
  const reason = prompt('Pourquoi souhaitez-vous annuler cette commande ?');
  if (!reason?.trim()) return;
  button.disabled = true;
  try {
    const payload = await api('orders/cancel', {
      method: 'POST', body: JSON.stringify({ id: Number(id), token: customerCancelToken(id), reason: reason.trim() }),
    });
    button.closest('article').outerHTML = orderCard(payload.order);
    toast('Votre commande a été annulée.', 'success');
  } catch (error) {
    toast(error.message, 'error');
    button.disabled = false;
  }
}

function orderCard(order) {
  const steps = ['pending', 'prep', 'delivery', 'done'];
  const activeIndex = Math.max(0, steps.indexOf(order.status));

  return `
    <article class="panel">
      <div class="section-head">
        <div>
          <h3>${escapeHtml(order.number)}</h3>
          <p class="muted">${escapeHtml(order.customer_name)} - ${escapeHtml(order.phone)}</p>
        </div>
        <span class="badge ${order.status}">${statusLabel(order.status)}</span>
      </div>
      <p>${escapeHtml(orderItemsText(order))}</p>
      <strong class="price">${money(order.total)}</strong>
      ${order.scheduled_at ? `<p class="muted">Programme: ${new Date(order.scheduled_at).toLocaleString('fr-FR')}</p>` : ''}
      <p><span class="badge pay-${order.payment_status}">${paymentStatusLabel(order.payment_status)}</span></p>
      <p class="muted">${escapeHtml(paymentHint(order))}</p>
      ${order.status === 'pending' && customerCancelToken(order.id) ? `<button type="button" class="secondary danger" data-cancel-order="${order.id}">Annuler ma commande</button>` : ''}
      ${order.status === 'cancelled' ? `<p class="muted">Motif : ${escapeHtml(order.cancellation_reason || '')}</p>${order.payment_status === 'paid' ? '<p>Contactez le restaurant pour le remboursement.</p>' : ''}` : ''}
      ${paymentCheckoutUrl(order) ? `<a class="primary link-button" href="${escapeHtml(paymentCheckoutUrl(order))}" rel="noopener noreferrer">Payer maintenant — ${escapeHtml(paymentMethodLabel(order.payment_method))}</a>` : ''}
      <div class="timeline">
        ${steps.map((step, index) => `<span class="step ${index < activeIndex ? 'done' : index === activeIndex ? 'active' : ''}" title="${statusLabel(step)}"></span>`).join('')}
      </div>
    </article>
  `;
}

function driverOrderCard(order) {
  return `
    <article class="panel driver-order">
      <div class="section-head">
        <div>
          <h3>${order.number}</h3>
          <p class="muted">${new Date(order.created_at).toLocaleString('fr-FR')}</p>
        </div>
        <span class="badge ${order.status}">${statusLabel(order.status)}</span>
      </div>
      <p><strong>${order.customer_name}</strong> - <a href="tel:${order.phone}">${order.phone}</a></p>
      <p>${order.address}</p>
      ${order.note ? `<p class="muted">${order.note}</p>` : ''}
      <p>${orderItemsText(order)}</p>
      <p class="price">${money(order.total)}</p>
      <p class="muted">${paymentMethodLabel(order.payment_method)} - ${paymentStatusLabel(order.payment_status)}</p>
      <div class="actions">
        <a class="secondary small link-button" href="tel:${order.phone}">Appeler</a>
        <button class="secondary small" data-driver-status="${order.id}" data-status="delivery" ${order.status === 'delivery' ? 'disabled' : ''}>En livraison</button>
        <button class="primary small" data-driver-status="${order.id}" data-status="done">Livree</button>
      </div>
    </article>
  `;
}

function renderDriverDashboard(payload) {
  const orders = payload.orders || [];
  qs('#driver-dashboard').innerHTML = `
    <div class="kpi-grid">
      <div class="kpi"><span>Livreur</span><strong>${payload.driver.name}</strong></div>
      <div class="kpi"><span>Courses actives</span><strong>${payload.summary.assigned}</strong></div>
      <div class="kpi"><span>A recuperer</span><strong>${payload.summary.prep}</strong></div>
      <div class="kpi"><span>En livraison</span><strong>${payload.summary.delivery}</strong></div>
    </div>
    <div class="actions">
      <button class="secondary" type="button" data-driver-refresh>Rafraichir</button>
      <button class="secondary danger" type="button" data-driver-logout>Changer de livreur</button>
    </div>
    ${orders.length ? orders.map(driverOrderCard).join('') : '<p class="panel muted">Aucune course active pour ce livreur.</p>'}
  `;
}

async function loadDriverDashboard(phone = state.driverPhone, password = state.driverPassword) {
  if (!phone) return;
  const payload = await api('driver/dashboard', {
    method: 'POST',
    body: JSON.stringify({ phone, password }),
  });
  state.driverPhone = phone;
  state.driverPassword = password;
  localStorage.setItem('ck_driver_phone', phone);
  renderDriverDashboard(payload);
}

async function submitDriverLogin(event) {
  event.preventDefault();
  const form = new FormData(event.currentTarget);
  const phone = form.get('phone');
  const password = form.get('password');
  try {
    await loadDriverDashboard(phone, password);
    toast('Courses chargees.');
  } catch (error) {
    toast(error.message);
  }
}

async function updateDriverOrderStatus(orderId, status) {
  try {
    const payload = await api('driver/order-status', {
      method: 'PATCH',
      body: JSON.stringify({ phone: state.driverPhone, password: state.driverPassword, order_id: Number(orderId), status }),
    });
    renderDriverDashboard(payload);
    toast(status === 'done' ? 'Commande livree.' : 'Commande en livraison.');
  } catch (error) {
    toast(error.message);
  }
}

function logoutDriver() {
  state.driverPhone = '';
  state.driverPassword = '';
  localStorage.removeItem('ck_driver_phone');
  resetForm('#driver-login-form');
  qs('#driver-dashboard').innerHTML = '';
  toast('Espace livreur ferme.');
}

function renderCheckoutZones() {
  const select = qs('#delivery-zone');
  if (!select) return;
  const zones = (state.operations?.zones || []).filter((zone) => zone.active);
  select.disabled = zones.length === 0;
  select.innerHTML = zones.length
    ? zones.map((zone) => `
      <option value="${zone.id}">${zone.name} - ${money(zone.fee)}${zone.minimum_order ? ` min. ${money(zone.minimum_order)}` : ''}</option>
    `).join('')
    : '<option value="">Aucune zone active</option>';
  syncFulfillmentFields();
}

function syncFulfillmentFields() {
  const fulfillment = currentFulfillment();
  const zoneField = qs('#delivery-zone-field');
  const address = qs('#checkout-form')?.elements.address;
  if (zoneField) zoneField.classList.toggle('hidden', fulfillment !== 'delivery');
  if (address) {
    address.required = fulfillment === 'delivery';
    address.placeholder = fulfillment === 'delivery' ? 'Quartier, rue ou point de repere' : 'Retrait sur place';
  }
  syncScheduleFields();
  renderCart();
}

async function submitOrder(event) {
  event.preventDefault();
  checkoutNotice('');
  if (state.cart.length === 0) {
    checkoutNotice('Votre panier est vide.', 'error');
    toast('Votre panier est vide.', 'error');
    return;
  }

  const submitButton = event.currentTarget.querySelector('[type="submit"]');
  const form = new FormData(event.currentTarget);
  const fulfillment = form.get('fulfillment');
  const address = String(form.get('address') || '').trim();
  if (fulfillment === 'delivery' && address === '') {
    checkoutNotice('Adresse obligatoire pour une livraison.', 'error');
    toast('Adresse obligatoire pour une livraison.', 'error');
    return;
  }
  if (fulfillment === 'delivery' && !selectedZone()) {
    checkoutNotice('Choisissez une zone de livraison.', 'error');
    toast('Choisissez une zone de livraison.', 'error');
    return;
  }
  const scheduleMode = form.get('schedule_mode') || 'asap';
  const scheduledAt = scheduleMode === 'later' ? String(form.get('scheduled_at') || '') : '';
  if (scheduleMode === 'later') {
    const scheduledTime = new Date(scheduledAt).getTime();
    const minTime = Date.now() + 30 * 60 * 1000;
    const maxTime = Date.now() + 7 * 24 * 60 * 60 * 1000;
    if (!scheduledAt || Number.isNaN(scheduledTime) || scheduledTime < minTime || scheduledTime > maxTime) {
      checkoutNotice('Choisissez une date entre 30 minutes et 7 jours.', 'error');
      toast('Choisissez une date entre 30 minutes et 7 jours.', 'error');
      return;
    }
  }

  const body = {
    customer: {
      name: form.get('name'),
      phone: form.get('phone'),
      address: address || 'Retrait sur place',
      note: form.get('note'),
    },
    fulfillment,
    delivery_zone_id: fulfillment === 'delivery' ? Number(form.get('delivery_zone_id')) : null,
    promo_code: form.get('promo_code') || '',
    scheduled_at: scheduledAt,
    payment_method: form.get('payment_method'),
    items: state.cart.map((item) => ({ id: item.id, qty: item.qty, options: item.options || [] })),
  };

  try {
    if (submitButton) {
      submitButton.disabled = true;
      submitButton.textContent = 'Validation...';
    }
    checkoutNotice('Envoi de la commande...', 'info');
    toast('Envoi de la commande...', 'info');
    const payload = await api('orders', { method: 'POST', body: JSON.stringify(body) });
    if (payload.order.cancel_token) {
      try { localStorage.setItem(`ck_cancel_${payload.order.id}`, payload.order.cancel_token); }
      catch { toast('Le navigateur ne permet pas de conserver l’accès à l’annulation.', 'error'); }
    }
    state.cart = [];
    renderCart();
    resetForm(event.currentTarget);
    syncFulfillmentFields();
    syncScheduleFields();
    toggleCart(false);
    showPage('tracking');
    qs('#tracking-results').innerHTML = orderCard(payload.order);
    toast(`Commande ${payload.order.number} enregistrée.`, 'success');
  } catch (error) {
    const message = error.message || 'Commande impossible. Vérifiez les informations.';
    checkoutNotice(message, 'error');
    toast(message, 'error');
  } finally {
    if (submitButton) {
      submitButton.disabled = false;
      submitButton.textContent = 'Commander maintenant';
    }
  }
}

async function submitPosOrder(event) {
  event.preventDefault();
  inlineNotice('#pos-notice');
  if (state.posCart.length === 0) {
    inlineNotice('#pos-notice', 'Ajoutez au moins un article.', 'error');
    return;
  }

  const form = event.currentTarget;
  const data = new FormData(form);
  const total = posTotal();
  const received = Number(data.get('amount_received') || 0);
  const method = String(data.get('payment_method') || 'cash');
  if (method === 'cash' && received > 0 && received < total) {
    inlineNotice('#pos-notice', 'Le montant reçu est inférieur au total.', 'error');
    return;
  }

  const submitButton = form.querySelector('[type="submit"]');
  try {
    if (submitButton) {
      submitButton.disabled = true;
      submitButton.textContent = 'Encaissement...';
    }
    const payload = await api('admin/pos-order', {
      method: 'POST',
      body: JSON.stringify({
        customer: {
          name: data.get('name'),
          phone: data.get('phone'),
          address: 'Sur place',
          note: data.get('note'),
        },
        fulfillment: 'pickup',
        payment_method: method,
        paid: true,
        status: data.get('status'),
        items: state.posCart.map((item) => ({ id: item.id, qty: item.qty })),
      }),
    });
    inlineNotice('#pos-notice', `Commande ${payload.order.number} encaissée.`, 'success');
    const notice = qs('#pos-notice');
    if (notice) {
      notice.innerHTML = `Commande ${payload.order.number} encaissée. <button class="secondary small" type="button" data-print-order="${payload.order.id}">Imprimer le ticket</button>`;
    }
    state.posCart = [];
    renderPosTicket();
    renderAdminTables(payload.orders);
    renderCustomerList(payload.customers);
    await loadCatalog();
    renderPos();
    printOrder(payload.order.id);
  } catch (error) {
    inlineNotice('#pos-notice', error.message, 'error');
  } finally {
    if (submitButton) {
      submitButton.disabled = false;
      submitButton.textContent = 'Encaisser';
    }
  }
}

async function trackOrder(event) {
  event.preventDefault();
  const q = new FormData(event.currentTarget).get('q');

  try {
    const payload = await api(`orders&q=${encodeURIComponent(q)}`);
    qs('#tracking-results').innerHTML = payload.orders.length
      ? payload.orders.map(orderCard).join('')
      : '<p class="panel muted">Aucune commande trouvée.</p>';
  } catch (error) {
    toast(error.message);
  }
}

async function login(event) {
  event.preventDefault();
  const form = new FormData(event.currentTarget);

  try {
    const payload = await api('auth/login', {
      method: 'POST',
      body: JSON.stringify({ email: form.get('email'), password: form.get('password') }),
    });
    state.token = payload.token;
    state.user = payload.user;
    localStorage.setItem('ck_token', payload.token);
    localStorage.setItem('ck_user', JSON.stringify(payload.user));
    toast(`Bienvenue ${payload.user.name}.`);
    await loadAdmin();
  } catch (error) {
    toast(error.message);
  }
}

function renderAdminTables(orders) {
  state.adminOrders = orders;
  const canManageDelivery = can('can_manage_delivery');
  const canManagePayments = can('can_manage_payments');
  const deliveryOptions = (selectedId) => `
    <option value="">Non assignée</option>
    ${state.deliveryStaff.filter((driver) => driver.active && driver.status !== 'absent').map((driver) => `
      <option value="${driver.id}" ${Number(selectedId) === Number(driver.id) ? 'selected' : ''}>
        ${driver.name}
      </option>
    `).join('')}
  `;

  const rows = orders.map((order) => `
    <tr>
      <td><strong>${order.number}</strong><br><span class="muted">${new Date(order.created_at).toLocaleString('fr-FR')}</span></td>
      <td>${order.customer_name}<br><span class="muted">${order.phone}</span></td>
      <td>${orderItemsText(order)}</td>
      <td class="price">${money(order.total)}</td>
      <td>
        <span class="badge ${order.status}">${statusLabel(order.status)}</span>
        ${order.cancellation_reason ? `<p class="muted">${order.cancellation_reason}</p>` : ''}
        <p class="muted">${paymentMethodLabel(order.payment_method)}</p>
        <span class="badge pay-${order.payment_status}">${paymentStatusLabel(order.payment_status)}</span>
        <p class="muted">${order.payment_reference || ''}</p>
      </td>
      <td>
        <select class="status-select" data-status-id="${order.id}">
          ${['pending', 'prep', 'delivery', 'done', 'cancelled'].map((status) => `
            <option value="${status}" ${order.status === status ? 'selected' : ''}>${statusLabel(status)}</option>
          `).join('')}
        </select>
        ${canManageDelivery ? `
          <select class="status-select" data-delivery-id="${order.id}">
            ${deliveryOptions(order.delivery_staff_id)}
          </select>
        ` : ''}
        ${canManagePayments ? `
          <select class="status-select" data-payment-id="${order.id}">
            ${['pending', 'paid', 'failed', 'refunded'].map((status) => `
              <option value="${status}" ${order.payment_status === status ? 'selected' : ''}>${paymentStatusLabel(status)}</option>
            `).join('')}
          </select>
        ` : ''}
        <button class="secondary small" data-print-order="${order.id}">Ticket</button>
        <button class="secondary small" data-notify-order="${order.id}" data-template="confirmation">Notif</button>
      </td>
    </tr>
  `).join('');

  const table = `
    <thead><tr><th>Commande</th><th>Client</th><th>Articles</th><th>Total</th><th>Statut</th><th>Actions</th></tr></thead>
    <tbody>${rows}</tbody>
  `;
  qs('#dashboard-orders').innerHTML = table;
  qs('#admin-orders-table').innerHTML = table;
}

function renderAdminMenu() {
  renderMenuFormCategories();
  const dailyCount = state.menu.filter((item) => item.daily_special && item.available).length;
  if (qs('#daily-special-count')) {
    qs('#daily-special-count').textContent = `${Math.min(dailyCount, 3)}/3 plats du jour affichés`;
  }
  qs('#admin-menu-grid').innerHTML = state.menu.map((item) => `
    <article class="panel">
      ${item.image_url ? `<img class="admin-food-img" src="${item.image_url}" alt="${item.name}" loading="lazy">` : `<div class="food-emoji">${item.emoji}</div>`}
      <h3>${item.name}</h3>
      <p class="muted">${categoryLabel(item.category)}${item.daily_special ? ' - Plat du jour' : ''}</p>
      <p class="muted">${item.description || ''}</p>
      ${item.stock_enabled ? `<p class="muted">Stock: ${item.stock_qty} ${item.stock_qty <= item.low_stock_threshold ? '- alerte' : ''}</p>` : ''}
      <p class="price">${priceLabel(item)}</p>
      <label>
        Disponible
        <select data-availability="${item.id}">
          <option value="1" ${item.available ? 'selected' : ''}>Oui</option>
          <option value="0" ${!item.available ? 'selected' : ''}>Non</option>
        </select>
      </label>
      <div class="actions">
        <button class="secondary small" data-edit-menu="${item.id}">Modifier</button>
        <button class="secondary small danger" data-delete-menu="${item.id}">Supprimer</button>
      </div>
    </article>
  `).join('');
}

function renderMenuFormCategories() {
  const select = qs('#menu-form-category');
  select.innerHTML = state.categories.map((category) => `
    <option value="${category.id}">${category.name}</option>
  `).join('');
}

function resetMenuForm() {
  const form = resetForm('#menu-form');
  if (!form) return;
  form.elements.id.value = '';
  form.elements.available.checked = true;
  form.elements.daily_special.checked = false;
  form.elements.image_file.value = '';
  setMenuImagePreview();
  qs('#menu-form-title').textContent = 'Ajouter un plat';
}

function editMenuItem(id) {
  const item = state.menu.find((entry) => Number(entry.id) === Number(id));
  if (!item) return;

  const form = qs('#menu-form');
  form.elements.id.value = item.id;
  form.elements.name.value = item.name;
  form.elements.category.value = item.category;
  form.elements.price.value = item.price;
  form.elements.max_price.value = item.max_price || '';
  form.elements.stock_qty.value = item.stock_qty || 0;
  form.elements.low_stock_threshold.value = item.low_stock_threshold ?? 3;
  form.elements.emoji.value = item.emoji || '';
  form.elements.image_url.value = item.image_url || '';
  form.elements.image_file.value = '';
  form.elements.description.value = item.description || '';
  form.elements.options_text.value = (item.options || []).map((option) => `${option.name}|${option.price_delta}`).join('\n');
  form.elements.available.checked = Boolean(item.available);
  form.elements.popular.checked = Boolean(item.popular);
  form.elements.daily_special.checked = Boolean(item.daily_special);
  form.elements.stock_enabled.checked = Boolean(item.stock_enabled);
  syncMenuImagePreview();
  qs('#menu-form-title').textContent = `Modifier ${item.name}`;
  form.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function menuPayloadFromForm(form) {
  const data = new FormData(form);
  return {
    id: data.get('id') ? Number(data.get('id')) : undefined,
    name: data.get('name'),
    category: data.get('category'),
    price: Number(data.get('price')),
    max_price: data.get('max_price') ? Number(data.get('max_price')) : null,
    stock_qty: Number(data.get('stock_qty') || 0),
    low_stock_threshold: Number(data.get('low_stock_threshold') || 3),
    emoji: data.get('emoji') || '🍽️',
    image_url: data.get('image_url') || '',
    description: data.get('description') || '',
    options_text: data.get('options_text') || '',
    available: form.elements.available.checked,
    popular: form.elements.popular.checked,
    daily_special: form.elements.daily_special.checked,
    stock_enabled: form.elements.stock_enabled.checked,
  };
}

async function uploadMenuImage(file) {
  const formData = new FormData();
  formData.append('image', file);

  const headers = {};
  if (isAdminPage && state.token) headers.Authorization = `Bearer ${state.token}`;

  const response = await fetch(`${API}?resource=admin/menu-upload`, {
    method: 'POST',
    headers,
    body: formData,
  });
  const text = await response.text();
  const jsonStart = text.indexOf('{');
  const payload = JSON.parse(jsonStart >= 0 ? text.slice(jsonStart) : text);

  if (!response.ok || payload.ok === false) {
    throw new Error(payload.message || 'Upload impossible.');
  }

  return payload.url;
}

async function saveMenuItem(event) {
  event.preventDefault();
  const body = menuPayloadFromForm(event.currentTarget);
  if (body.daily_special) {
    const currentDailyCount = state.menu.filter((item) => item.daily_special && Number(item.id) !== Number(body.id || 0)).length;
    if (currentDailyCount >= 3) {
      toast('Maximum 3 plats du jour. Décochez un autre plat avant.', 'error');
      return;
    }
  }
  const imageFile = event.currentTarget.elements.image_file.files[0];
  const isUpdate = Boolean(body.id);

  try {
    if (imageFile) {
      body.image_url = await uploadMenuImage(imageFile);
      event.currentTarget.elements.image_url.value = body.image_url;
      event.currentTarget.elements.image_file.value = '';
      syncMenuImagePreview();
    }
    const saved = await api('admin/menu-items', {
      method: isUpdate ? 'PUT' : 'POST',
      body: JSON.stringify(body),
    });
    const menuItemId = body.id || saved.item?.id;
    const options = String(body.options_text || '').split('\n').map((line) => {
      const [name, price = '0'] = line.split('|');
      return { name: (name || '').trim(), price_delta: Number(price || 0) };
    }).filter((option) => option.name);
    if (menuItemId) {
      await api('admin/menu-options', {
        method: 'PUT',
        body: JSON.stringify({ menu_item_id: Number(menuItemId), options }),
      });
    }
    toast(isUpdate ? 'Plat modifié.' : 'Plat ajouté.');
    resetMenuForm();
    await loadCatalog();
    renderAdminMenu();
  } catch (error) {
    toast(error.message);
  }
}

async function deleteMenuItem(id) {
  const item = state.menu.find((entry) => Number(entry.id) === Number(id));
  if (!item || !confirm(`Supprimer ${item.name} du menu ?`)) return;

  try {
    await api(`admin/menu-items&id=${encodeURIComponent(id)}`, { method: 'DELETE' });
    toast('Plat supprimé du menu.');
    resetMenuForm();
    await loadCatalog();
    renderAdminMenu();
  } catch (error) {
    toast(error.message);
  }
}

function renderClients(orders) {
  const clients = new Map();
  orders.forEach((order) => {
    const key = order.phone;
    const current = clients.get(key) || { name: order.customer_name, phone: order.phone, orders: 0, spent: 0 };
    current.orders += 1;
    current.spent += Number(order.total);
    clients.set(key, current);
  });

  qs('#clients-table').innerHTML = `
    <thead><tr><th>Client</th><th>Téléphone</th><th>Commandes</th><th>Total dépensé</th></tr></thead>
    <tbody>${[...clients.values()].map((client) => `
      <tr><td>${client.name}</td><td>${client.phone}</td><td>${client.orders}</td><td class="price">${money(client.spent)}</td></tr>
    `).join('')}</tbody>
  `;
}

function renderCustomerList(customers = state.customers) {
  state.customers = customers;
  qs('#clients-table').innerHTML = tableHtml(
    ['Client', 'Telephone', 'Adresse', 'Commandes', 'Total depense', 'Derniere commande'],
    customers.map((client) => [
      client.name,
      client.phone,
      client.default_address || '-',
      client.orders_count,
      money(client.total_spent),
      client.last_order_at ? new Date(client.last_order_at).toLocaleString('fr-FR') : '-',
    ])
  );
}

function deliveryStatusLabel(status) {
  return {
    available: 'Disponible',
    busy: 'En course',
    absent: 'Absent',
  }[status] || status;
}

function renderDeliveryStaff() {
  qs('#delivery-grid').innerHTML = state.deliveryStaff.map((driver) => `
    <article class="panel">
      <h3>${driver.name}</h3>
      <p class="muted">${driver.phone}</p>
      <p><span class="badge ${driver.status === 'available' ? 'done' : driver.status === 'busy' ? 'delivery' : 'pending'}">${deliveryStatusLabel(driver.status)}</span></p>
      <p>${driver.active ? '<span class="badge done">Actif</span>' : '<span class="badge pay-failed">Inactif</span>'}</p>
      <div class="stats compact">
        <div><strong>${driver.deliveries_today}</strong><span>Livraisons aujourd'hui</span></div>
        <div><strong>${driver.active_deliveries}</strong><span>Courses actives</span></div>
      </div>
      <div class="actions">
        <button class="secondary small" data-edit-driver="${driver.id}">Modifier</button>
      </div>
    </article>
  `).join('');
}

const dayLabels = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];

function renderKitchenOrders(orders = state.kitchenOrders) {
  state.kitchenOrders = orders;
  const target = qs('#kitchen-grid');
  if (!target) return;
  target.innerHTML = orders.length ? orders.map((order) => `
    <article class="panel kitchen-ticket">
      <div class="section-head">
        <h3>${order.number}</h3>
        <span class="badge ${order.status}">${statusLabel(order.status)}</span>
      </div>
      <p class="muted">${new Date(order.created_at).toLocaleTimeString('fr-FR')} - ${order.customer_name}</p>
      <p>${orderItemsText(order)}</p>
      ${order.note ? `<p class="muted">${order.note}</p>` : ''}
      <div class="actions">
        <button class="secondary small" data-status-id="${order.id}" data-next-status="prep">En preparation</button>
        <button class="primary small" data-status-id="${order.id}" data-next-status="delivery">Prete</button>
      </div>
    </article>
  `).join('') : '<p class="muted">Aucune commande en cuisine.</p>';
}

function renderOperations(operations = state.operations) {
  state.operations = operations;
  if (!operations || !qs('#settings-form')) return;
  const settingsForm = qs('#settings-form');
  Object.entries(operations.settings || {}).forEach(([key, value]) => {
    if (settingsForm.elements[key]) settingsForm.elements[key].value = value;
  });
  if (settingsForm.elements.orders_paused) {
    settingsForm.elements.orders_paused.checked = (operations.settings?.orders_paused || '0') === '1';
  }
  qs('#hours-grid').innerHTML = (operations.hours || []).map((row, index) => `
    <div class="hour-line">
      <strong>${dayLabels[index] || row.weekday}</strong>
      <input type="time" data-hour="${row.weekday}" data-field="open_time" value="${row.open_time}">
      <input type="time" data-hour="${row.weekday}" data-field="close_time" value="${row.close_time}">
      <label><input type="checkbox" data-hour="${row.weekday}" data-field="active" ${row.active ? 'checked' : ''}> Ouvert</label>
    </div>
  `).join('');
  qs('#zones-grid').innerHTML = (operations.zones || []).map((zone) => `
    <article class="panel">
      <h3>${zone.name}</h3>
      <p class="price">${money(zone.fee)}</p>
      <p class="muted">Minimum ${money(zone.minimum_order)} - ${zone.estimated_minutes} min</p>
      <p>${zone.active ? '<span class="badge done">Active</span>' : '<span class="badge pay-failed">Inactive</span>'}</p>
      <button class="secondary small" data-edit-zone="${zone.id}">Modifier</button>
    </article>
  `).join('');
  qs('#promos-table').innerHTML = tableHtml(
    ['Code', 'Libelle', 'Reduction', 'Minimum', 'Statut', 'Actions'],
    (operations.promos || []).map((promo) => [
      promo.code,
      promo.label,
      promo.type === 'percent' ? `${promo.value}%` : money(promo.value),
      money(promo.minimum_order),
      promo.active ? '<span class="badge done">Actif</span>' : '<span class="badge pay-failed">Inactif</span>',
      `<button class="secondary small" data-edit-promo="${promo.id}">Modifier</button>`,
    ])
  );
  renderCheckoutZones();
  renderSocialLinks(operations.settings || {});
}

function renderReviews(reviews = state.reviews) {
  state.reviews = reviews;
  const target = qs('#reviews-table');
  if (!target) return;
  target.innerHTML = tableHtml(
    ['Date', 'Commande', 'Client', 'Note', 'Commentaire'],
    reviews.map((review) => [
      new Date(review.created_at).toLocaleString('fr-FR'),
      review.number,
      review.customer_name,
      `${review.rating}/5`,
      review.comment || '-',
    ])
  );
}

function generatedSecret(length = 12) {
  const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
  const bytes = new Uint32Array(length);
  if (window.crypto?.getRandomValues) crypto.getRandomValues(bytes);
  else bytes.forEach((_, index) => { bytes[index] = Math.floor(Math.random() * chars.length); });
  return [...bytes].map((value) => chars[value % chars.length]).join('');
}

function resetDeliveryForm() {
  const form = resetForm('#delivery-form');
  if (!form) return;
  form.elements.id.value = '';
  form.elements.password.value = '';
  form.elements.password.required = true;
  form.elements.password.placeholder = 'Mot de passe du livreur';
  form.elements.active.checked = true;
  qs('#delivery-form-title').textContent = 'Ajouter un livreur';
}

function editDeliveryStaff(id) {
  const driver = state.deliveryStaff.find((entry) => Number(entry.id) === Number(id));
  if (!driver) return;
  const form = qs('#delivery-form');
  form.elements.id.value = driver.id;
  form.elements.name.value = driver.name;
  form.elements.phone.value = driver.phone;
  form.elements.password.value = '';
  form.elements.password.required = false;
  form.elements.password.placeholder = 'Laisser vide pour conserver';
  form.elements.status.value = driver.status;
  form.elements.active.checked = Boolean(driver.active);
  qs('#delivery-form-title').textContent = `Modifier ${driver.name}`;
  form.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function notificationStatusLabel(status) {
  return {
    queued: 'À envoyer',
    sent: 'Envoyée',
    failed: 'Échouée',
  }[status] || status;
}

function renderNotifications(notifications = state.notifications) {
  state.notifications = notifications;
  qs('#notifications-table').innerHTML = tableHtml(
    ['Date', 'Commande', 'Canal', 'Client', 'Message', 'Provider', 'Statut', 'Actions'],
    notifications.map((item) => [
      new Date(item.created_at).toLocaleString('fr-FR'),
      item.order_number || '-',
      item.channel,
      item.recipient,
      `<span class="truncate">${item.message}</span>`,
      `${item.provider || 'manual'}${item.provider_reference ? `<p class="muted">${item.provider_reference}</p>` : ''}`,
      `<span class="badge ${item.status === 'sent' ? 'done' : item.status === 'failed' ? 'pay-failed' : 'pay-pending'}">${notificationStatusLabel(item.status)}</span>`,
      `<a class="secondary small link-button" href="${item.whatsapp_url}" target="_blank" rel="noreferrer">WhatsApp</a>
       <button class="secondary small" data-notification-status="${item.id}" data-status="sent">Envoyée</button>
       <button class="secondary small danger" data-notification-status="${item.id}" data-status="failed">Échec</button>`,
    ])
  );
}

function renderContactMessages(messages = state.contactMessages) {
  state.contactMessages = messages;
  qs('#messages-table').innerHTML = tableHtml(
    ['Date', 'Nom', 'TÃ©lÃ©phone', 'Message', 'Statut', 'Actions'],
    messages.map((item) => [
      new Date(item.created_at).toLocaleString('fr-FR'),
      item.name,
      item.phone,
      `<span class="truncate">${item.message}</span>`,
      item.handled ? '<span class="badge done">Traite</span>' : '<span class="badge pay-pending">Nouveau</span>',
      `<button class="secondary small" data-contact-message="${item.id}" data-handled="${item.handled ? '0' : '1'}">${item.handled ? 'Rouvrir' : 'Traiter'}</button>`,
    ])
  );
}

const auditActions = {
  create: 'Cr?ation', update: 'Modification', delete: 'Suppression',
  status: 'Changement de statut', assign_delivery: 'Affectation d?un livreur',
  availability: 'Disponibilit?', options: 'Options du plat', test: 'Test d?envoi',
};
const auditEntities = {
  order: 'Commande', pos_order: 'Commande caisse', user: 'Utilisateur',
  delivery_staff: 'Livreur', payment: 'Paiement', menu_item: 'Plat', notification: 'Notification',
};
const auditFields = {
  name: 'Nom', phone: 'T?l?phone', email: 'Email', role: 'R?le', status: 'Statut',
  reason: 'Motif', active: 'Compte actif', available: 'Disponible', category: 'Cat?gorie',
  daily_special: 'Plat du jour', number: 'Num?ro de commande', total: 'Montant',
  delivery_staff_id: 'Livreur affect?', channel: 'Canal', recipient: 'Destinataire',
};

function auditDetailValue(key, value, entity) {
  if (value === null || value === undefined || value === '') return 'Non renseign?';
  if (['active', 'available', 'daily_special'].includes(key)) {
    return [true, 1, '1', 'true'].includes(value) ? 'Oui' : 'Non';
  }
  if (key === 'total') return money(value);
  if (key === 'role') return roleLabel(value);
  if (key === 'category') return categoryLabel(value);
  if (key === 'delivery_staff_id') {
    const driver = state.deliveryStaff.find((item) => Number(item.id) === Number(value));
    return driver ? `${driver.name} (#${value})` : `Livreur #${value}`;
  }
  if (key === 'channel') return { whatsapp: 'WhatsApp', sms: 'SMS', email: 'Email' }[value] || value;
  if (key === 'status') {
    const labels = entity === 'delivery_staff'
      ? { available: 'Disponible', busy: 'En course', absent: 'Absent' }
      : entity === 'notification'
        ? { queued: 'En attente', sent: 'Envoy?e', failed: '?chec' }
        : entity === 'payment'
          ? { pending: 'En attente', paid: 'Pay?', failed: '?chec', refunded: 'Rembours?' }
          : { pending: 'En attente', prep: 'En pr?paration', delivery: 'En livraison', done: 'Termin?e', cancelled: 'Annul?e' };
    return labels[value] || value;
  }
  return typeof value === 'object' ? JSON.stringify(value) : String(value);
}

function renderAuditLogs(logs = state.auditLogs) {
  state.auditLogs = logs;
  for (const [selector, key, labels, title] of [
    ['#audit-action', 'action', auditActions, 'Toutes les actions'],
    ['#audit-entity', 'entity', auditEntities, 'Toutes les cibles'],
  ]) {
    const select = qs(selector);
    if (!select) continue;
    const selected = select.value;
    select.innerHTML = `<option value="">${title}</option>` + [...new Set(logs.map((log) => log[key]))]
      .map((value) => `<option value="${escapeHtml(value)}">${escapeHtml(labels[value] || value)}</option>`).join('');
    select.value = selected;
  }
  const query = (qs('#audit-search')?.value || '').trim().toLocaleLowerCase('fr');
  const action = qs('#audit-action')?.value;
  const entity = qs('#audit-entity')?.value;
  const filtered = logs.filter((log) => {
    const text = [log.user_name, auditActions[log.action] || log.action,
      auditEntities[log.entity] || log.entity, log.entity_id, log.ip_address,
      ...Object.entries(log.details || {}).map(([key, value]) => `${auditFields[key] || key} ${auditDetailValue(key, value, log.entity)}`)]
      .join(' ').toLocaleLowerCase('fr');
    return (!action || log.action === action) && (!entity || log.entity === entity) && (!query || text.includes(query));
  });
  qs('#audit-count').textContent = `${filtered.length} action(s) affich?e(s) sur ${logs.length} charg?e(s).`;
  qs('#audit-table').innerHTML = tableHtml(
    ['Date et heure', 'Utilisateur', 'Action', 'Cible', 'D?tails', 'Adresse IP'],
    filtered.map((log) => {
      const date = new Date(String(log.created_at).replace(' ', 'T'));
      const details = Object.entries(log.details || {}).map(([key, value]) =>
        `<div><dt>${escapeHtml(auditFields[key] || key)}</dt><dd>${escapeHtml(auditDetailValue(key, value, log.entity))}</dd></div>`).join('');
      return [
        escapeHtml(Number.isNaN(date.getTime()) ? log.created_at : date.toLocaleString('fr-FR')),
        escapeHtml(log.user_name || 'Utilisateur supprim? / syst?me'),
        `<span class="badge">${escapeHtml(auditActions[log.action] || log.action)}</span>`,
        escapeHtml(`${auditEntities[log.entity] || log.entity}${log.entity_id ? ` #${log.entity_id}` : ''}`),
        details ? `<dl class="audit-details">${details}</dl>` : '<span class="muted">Aucun d?tail enregistr?</span>',
        escapeHtml(log.ip_address || 'Non renseign?e'),
      ];
    })
  );
  if (!filtered.length) qs('#audit-table tbody').innerHTML = `<tr><td colspan="6" class="muted">${logs.length ? 'Aucune action ne correspond aux filtres.' : 'Aucune action enregistr?e pour le moment.'}</td></tr>`;
}

function roleLabel(role) {
  return {
    super_admin: 'Super Admin',
    manager: 'Gérant',
    cashier: 'Caissier',
  }[role] || role;
}

function renderUsers(users = state.users) {
  state.users = users;
  qs('#users-table').innerHTML = tableHtml(
    ['Nom', 'Email', 'Role', 'Securite', 'Statut', 'Actions'],
    users.map((user) => [
      user.name,
      user.email,
      roleLabel(user.role),
      user.uses_demo_password ? '<span class="badge pay-pending">Mot de passe demo</span>' : '<span class="badge done">OK</span>',
      user.active ? '<span class="badge done">Actif</span>' : '<span class="badge pay-failed">Inactif</span>',
      `<button class="secondary small" data-edit-user="${user.id}">Modifier</button>`,
    ])
  );
}

function resetUserForm() {
  const form = resetForm('#user-form');
  if (!form) return;
  form.elements.id.value = '';
  form.elements.active.checked = true;
  qs('#user-form-title').textContent = 'Ajouter un utilisateur';
}

function editUser(id) {
  const user = state.users.find((entry) => Number(entry.id) === Number(id));
  if (!user) return;
  const form = qs('#user-form');
  form.elements.id.value = user.id;
  form.elements.name.value = user.name;
  form.elements.email.value = user.email;
  form.elements.role.value = user.role;
  form.elements.password.value = '';
  form.elements.active.checked = Boolean(user.active);
  qs('#user-form-title').textContent = `Modifier ${user.name}`;
  form.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

async function loadUsers() {
  if (!can('can_manage_users')) return;
  try {
    const payload = await api('admin/users');
    renderUsers(payload.users);
  } catch (error) {
    toast(error.message);
  }
}

async function saveUser(event) {
  event.preventDefault();
  const form = event.currentTarget;
  const data = new FormData(form);
  const body = {
    id: data.get('id') ? Number(data.get('id')) : undefined,
    name: data.get('name'),
    email: data.get('email'),
    role: data.get('role'),
    password: data.get('password') || '',
    active: form.elements.active.checked,
  };
  try {
    const payload = await api('admin/users', {
      method: body.id ? 'PUT' : 'POST',
      body: JSON.stringify(body),
    });
    renderUsers(payload.users);
    resetUserForm();
    await loadSystemChecks();
    toast(body.id ? 'Utilisateur modifié.' : 'Utilisateur ajouté.');
  } catch (error) {
    toast(error.message);
  }
}

async function loadNotifications() {
  try {
    const payload = await api('admin/notifications');
    renderNotifications(payload.notifications);
  } catch (error) {
    toast(error.message);
  }
}

async function loadContactMessages() {
  try {
    const payload = await api('admin/contact-messages');
    renderContactMessages(payload.messages);
  } catch (error) {
    toast(error.message);
  }
}

async function loadCustomers() {
  try {
    const payload = await api('admin/customers');
    renderCustomerList(payload.customers);
  } catch (error) {
    toast(error.message);
  }
}

async function loadAuditLogs() {
  if (!can('can_view_stats')) return;
  try {
    const payload = await api('admin/audit-log');
    renderAuditLogs(payload.logs);
  } catch (error) {
    toast(error.message);
  }
}

async function loadOperations() {
  if (!can('can_manage_operations')) return;
  try {
    const payload = await api('admin/operations');
    renderOperations(payload.operations);
  } catch (error) {
    toast(error.message);
  }
}

async function loadKitchen() {
  try {
    const payload = await api('admin/kitchen');
    renderKitchenOrders(payload.orders);
  } catch (error) {
    toast(error.message);
  }
}

async function loadReviews() {
  try {
    const payload = await api('admin/reviews');
    renderReviews(payload.reviews);
  } catch (error) {
    toast(error.message);
  }
}

async function saveSettings(event) {
  event.preventDefault();
  const form = event.currentTarget;
  const data = new FormData(form);
  const payload = {};
  ['restaurant_name', 'restaurant_phone', 'restaurant_whatsapp', 'restaurant_address', 'pickup_eta_minutes', ...socialPlatforms.map(([key]) => key)].forEach((key) => {
    payload[key] = data.get(key) || '';
  });
  payload.orders_paused = form.elements.orders_paused.checked;
  const response = await api('admin/settings', { method: 'PATCH', body: JSON.stringify(payload) });
  renderOperations(response.operations);
  toast('Parametres enregistres.');
}

async function saveHours(event) {
  event.preventDefault();
  const hours = dayLabels.map((_, index) => {
    const weekday = index + 1;
    return {
      weekday,
      open_time: qs(`[data-hour="${weekday}"][data-field="open_time"]`).value,
      close_time: qs(`[data-hour="${weekday}"][data-field="close_time"]`).value,
      active: qs(`[data-hour="${weekday}"][data-field="active"]`).checked,
    };
  });
  const response = await api('admin/business-hours', { method: 'PUT', body: JSON.stringify({ hours }) });
  renderOperations(response.operations);
  toast('Horaires enregistres.');
}

async function saveZone(event) {
  event.preventDefault();
  const form = event.currentTarget;
  const data = new FormData(form);
  const body = {
    id: data.get('id') ? Number(data.get('id')) : undefined,
    name: data.get('name'),
    fee: Number(data.get('fee')),
    minimum_order: Number(data.get('minimum_order') || 0),
    estimated_minutes: Number(data.get('estimated_minutes') || 45),
    active: form.elements.active.checked,
  };
  const response = await api('admin/delivery-zones', { method: body.id ? 'PUT' : 'POST', body: JSON.stringify(body) });
  resetForm(form);
  form.elements.id.value = '';
  form.elements.active.checked = true;
  renderOperations(response.operations);
  toast('Zone enregistree.');
}

async function savePromo(event) {
  event.preventDefault();
  const form = event.currentTarget;
  const data = new FormData(form);
  const body = {
    id: data.get('id') ? Number(data.get('id')) : undefined,
    code: data.get('code'),
    label: data.get('label'),
    type: data.get('type'),
    value: Number(data.get('value')),
    minimum_order: Number(data.get('minimum_order') || 0),
    active: form.elements.active.checked,
  };
  const response = await api('admin/promo-codes', { method: body.id ? 'PUT' : 'POST', body: JSON.stringify(body) });
  resetForm(form);
  form.elements.id.value = '';
  form.elements.active.checked = true;
  renderOperations(response.operations);
  toast('Code promo enregistre.');
}

async function submitReview(event) {
  event.preventDefault();
  const data = new FormData(event.currentTarget);
  await api('reviews', {
    method: 'POST',
    body: JSON.stringify({
      order_number: data.get('order_number'),
      phone: data.get('phone'),
      rating: Number(data.get('rating')),
      comment: data.get('comment') || '',
    }),
  });
  resetForm(event.currentTarget);
  toast('Merci pour votre avis.');
}

async function updateContactMessageStatus(id, handled) {
  try {
    const payload = await api('admin/contact-message-status', {
      method: 'PATCH',
      body: JSON.stringify({ id: Number(id), handled: handled === '1' }),
    });
    renderContactMessages(payload.messages);
    toast(handled === '1' ? 'Message traite.' : 'Message rouvert.');
  } catch (error) {
    toast(error.message);
  }
}

async function saveDeliveryStaff(event) {
  event.preventDefault();
  const form = event.currentTarget;
  const data = new FormData(form);
  const body = {
    id: data.get('id') ? Number(data.get('id')) : undefined,
    name: data.get('name'),
    phone: data.get('phone'),
    password: data.get('password') || '',
    status: data.get('status'),
    active: form.elements.active.checked,
  };

  try {
    const payload = await api('admin/delivery-staff', {
      method: body.id ? 'PUT' : 'POST',
      body: JSON.stringify(body),
    });
    state.deliveryStaff = payload.delivery_staff;
    renderDeliveryStaff();
    resetDeliveryForm();
    await loadSystemChecks();
    toast(body.id ? 'Livreur modifie.' : 'Livreur ajoute.');
  } catch (error) {
    toast(error.message);
  }
}

function tableHtml(headers, rows) {
  return `
    <thead><tr>${headers.map((header) => `<th>${header}</th>`).join('')}</tr></thead>
    <tbody>${rows.map((row) => `<tr>${row.map((cell) => `<td>${cell}</td>`).join('')}</tr>`).join('')}</tbody>
  `;
}

function renderStats(stats) {
  state.stats = stats;
  const totalRevenue = stats.revenue_by_day.reduce((sum, row) => sum + Number(row.revenue), 0);
  const totalOrders = stats.revenue_by_day.reduce((sum, row) => sum + Number(row.orders_count), 0);
  const bestItem = stats.top_items[0]?.name || 'Aucun';
  const paidAmount = stats.payment_breakdown
    .filter((row) => row.status === 'paid')
    .reduce((sum, row) => sum + Number(row.amount), 0);

  qs('#stats-kpis').innerHTML = `
    <div class="kpi"><span>CA 14 jours</span><strong>${money(totalRevenue)}</strong></div>
    <div class="kpi"><span>Commandes 14 jours</span><strong>${totalOrders}</strong></div>
    <div class="kpi"><span>Meilleur plat</span><strong>${bestItem}</strong></div>
    <div class="kpi"><span>Paiements confirmés</span><strong>${money(paidAmount)}</strong></div>
  `;

  const maxRevenue = Math.max(1, ...stats.revenue_by_day.map((row) => Number(row.revenue)));
  qs('#stats-revenue').innerHTML = stats.revenue_by_day.map((row) => `
    <div class="bar-col">
      <div class="bar-val">${Math.round(Number(row.revenue) / 1000)}K</div>
      <div class="bar" style="height:${Math.max(8, Math.round(Number(row.revenue) / maxRevenue * 100))}%;"></div>
      <div class="bar-label">${String(row.day).slice(5)}</div>
    </div>
  `).join('');

  qs('#stats-top-items').innerHTML = tableHtml(
    ['Plat', 'Qté', 'CA'],
    stats.top_items.map((row) => [row.name, row.quantity, money(row.revenue)])
  );
  qs('#stats-categories').innerHTML = tableHtml(
    ['Catégorie', 'Qté', 'CA'],
    stats.category_sales.map((row) => [row.category, row.quantity, money(row.revenue)])
  );
  qs('#stats-payments').innerHTML = tableHtml(
    ['Méthode', 'Statut', 'Nb', 'Montant'],
    stats.payment_breakdown.map((row) => [paymentMethodLabel(row.method), paymentStatusLabel(row.status), row.count, money(row.amount)])
  );
}

function renderSystemChecks(checks = state.checks) {
  state.checks = checks || [];
  const target = qs('#system-checks');
  if (!target) return;
  target.innerHTML = state.checks.length
    ? state.checks.map((check) => `
      <div class="check-line ${check.status}">
        <span class="badge ${check.status === 'ok' ? 'done' : 'pay-pending'}">${check.status === 'ok' ? 'OK' : 'Action'}</span>
        <div>
          <strong>${check.label}</strong>
          <p class="muted">${check.message}</p>
        </div>
      </div>
    `).join('')
    : '<p class="muted">Controle non charge.</p>';
}

async function loadSystemChecks() {
  if (!can('can_view_stats')) return;
  try {
    const payload = await api('admin/system-check');
    renderSystemChecks(payload.checks);
    toast('Controle preproduction actualise.');
  } catch (error) {
    toast(error.message);
  }
}

async function loadStats() {
  if (!can('can_view_stats')) return;
  try {
    const payload = await api('admin/stats');
    renderStats(payload);
  } catch (error) {
    toast(error.message);
  }
}

async function exportCsv(type) {
  try {
    const response = await fetch(`${API}?resource=admin/export&type=${encodeURIComponent(type)}`, {
      headers: { Authorization: `Bearer ${state.token}` },
    });
    if (!response.ok) {
      const text = await response.text();
      throw new Error(text || 'Export impossible.');
    }

    const blob = await response.blob();
    const disposition = response.headers.get('Content-Disposition') || '';
    const match = disposition.match(/filename="([^"]+)"/);
    const filename = match ? match[1] : `chez-ketchup-${type}.csv`;
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
    toast('Export CSV généré.');
  } catch (error) {
    toast(error.message);
  }
}

async function exportPdf(type) {
  try {
    const response = await fetch(`${API}?resource=admin/export-pdf&type=${encodeURIComponent(type)}`, {
      headers: { Authorization: `Bearer ${state.token}` },
    });
    if (!response.ok) {
      const text = await response.text();
      throw new Error(text || 'Export PDF impossible.');
    }

    const blob = await response.blob();
    const disposition = response.headers.get('Content-Disposition') || '';
    const match = disposition.match(/filename="([^"]+)"/);
    const filename = match ? match[1] : `chez-ketchup-${type}.pdf`;
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
    toast('Rapport PDF gÃ©nÃ©rÃ©.');
  } catch (error) {
    toast(error.message);
  }
}

function renderAdminOrderSummary(summary) {
    qs('#kpi-grid').innerHTML = `
      <div class="kpi"><span>CA aujourd'hui</span><strong>${money(summary.revenue_today)}</strong></div>
      <div class="kpi"><span>Commandes aujourd'hui</span><strong>${summary.orders_today}</strong></div>
      <div class="kpi"><span>En attente</span><strong>${summary.pending}</strong></div>
      <div class="kpi"><span>En livraison</span><strong>${summary.delivery}</strong></div>
      <div class="kpi"><span>Paiements en attente</span><strong>${summary.payments_pending}</strong></div>
      <div class="kpi"><span>Payé aujourd'hui</span><strong>${money(summary.payments_paid_today)}</strong></div>
    `;
}

async function loadAdmin() {
  try {
    const payload = await api('admin/summary');
    qs('#login-form').classList.add('hidden');
    qs('#admin-content').classList.remove('hidden');
    qs('#logout-admin').classList.remove('hidden');
    qs('#admin-nav')?.classList.remove('hidden');
    qs('#admin-user').textContent = `${state.user.name} - ${roleLabel(state.user.role)}`;
    applyRoleUI();
    state.deliveryStaff = payload.delivery_staff || [];
    renderOperations(payload.operations || state.operations);
    renderKitchenOrders(payload.kitchen_orders || []);
    renderSystemChecks(payload.checks || []);

    renderAdminOrderSummary(payload.summary);
    renderAdminTables(payload.orders);
    latestAdminOrderId = Math.max(0, ...payload.orders.map((order) => Number(order.id)));
    startAdminOrderUpdates();
    renderPos();
    renderAdminMenu();
    renderDeliveryStaff();
    await loadCustomers();
    await loadContactMessages();
    if (can('can_manage_operations')) await loadOperations();
    await loadKitchen();
    await loadReviews();
    if (can('can_view_stats')) await loadStats();
    await loadNotifications();
    if (can('can_manage_users')) await loadUsers();
    if (can('can_view_stats')) await loadAuditLogs();
  } catch (error) {
    showLogin();
    toast(error.message);
  }
}

async function updateDeliveryAssignment(id, deliveryStaffId) {
  try {
    const payload = await api('admin/order-delivery', {
      method: 'PATCH',
      body: JSON.stringify({
        id: Number(id),
        delivery_staff_id: deliveryStaffId ? Number(deliveryStaffId) : null,
      }),
    });
    state.deliveryStaff = payload.delivery_staff || state.deliveryStaff;
    renderAdminTables(payload.orders);
    renderDeliveryStaff();
    await loadCustomers();
    toast('Livreur mis à jour.');
  } catch (error) {
    toast(error.message);
    await loadAdmin();
  }
}

async function updatePaymentStatus(id, status) {
  try {
    const payload = await api('admin/payment-status', {
      method: 'PATCH',
      body: JSON.stringify({ id: Number(id), status }),
    });
    renderAdminTables(payload.orders);
    await loadCustomers();
    toast('Statut paiement mis à jour.');
    await loadAdmin();
  } catch (error) {
    toast(error.message);
    await loadAdmin();
  }
}

async function queueNotification(orderId, template = 'confirmation') {
  try {
    const payload = await api('admin/notifications', {
      method: 'POST',
      body: JSON.stringify({ order_id: Number(orderId), template, channel: 'whatsapp' }),
    });
    renderNotifications(payload.notifications);
    toast('Notification préparée.');
    if (payload.notification.whatsapp_url) {
      window.open(payload.notification.whatsapp_url, '_blank', 'noopener,noreferrer');
    }
  } catch (error) {
    toast(error.message);
  }
}

async function sendNotificationTest(event) {
  event.preventDefault();
  const form = event.currentTarget;
  const data = new FormData(form);
  try {
    const payload = await api('admin/notification-test', {
      method: 'POST',
      body: JSON.stringify({
        channel: data.get('channel'),
        recipient: data.get('recipient'),
        message: data.get('message'),
      }),
    });
    renderNotifications(payload.notifications);
    toast(payload.notification.status === 'sent' ? 'Notification de test envoyee.' : 'Notification de test preparee.');
    if (payload.notification.whatsapp_url && payload.notification.provider === 'manual') {
      window.open(payload.notification.whatsapp_url, '_blank', 'noopener,noreferrer');
    }
  } catch (error) {
    toast(error.message);
  }
}

async function updateNotificationStatus(id, status) {
  try {
    const payload = await api('admin/notification-status', {
      method: 'PATCH',
      body: JSON.stringify({ id: Number(id), status }),
    });
    renderNotifications(payload.notifications);
    toast('Notification mise à jour.');
  } catch (error) {
    toast(error.message);
  }
}

function printOrder(id) {
  const order = state.adminOrders.find((entry) => Number(entry.id) === Number(id));
  if (!order) {
    toast('Commande introuvable.');
    return;
  }

  const popup = window.open('', 'ck-ticket', 'width=420,height=720');
  if (!popup) {
    toast('Autorisez les fenêtres pop-up pour imprimer le ticket.');
    return;
  }

  popup.document.write(`
    <!doctype html>
    <html lang="fr">
    <head>
      <meta charset="utf-8">
      <title>Ticket ${order.number}</title>
      <style>
        body { font-family: Arial, sans-serif; color: #111; margin: 0; padding: 18px; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        h2 { font-size: 16px; margin: 18px 0 8px; border-top: 1px dashed #888; padding-top: 12px; }
        p { margin: 4px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        td { padding: 6px 0; border-bottom: 1px dashed #ddd; vertical-align: top; }
        .right { text-align: right; }
        .total { font-size: 18px; font-weight: 700; padding-top: 10px; }
        .muted { color: #555; }
        @media print { button { display: none; } body { padding: 0; } }
      </style>
    </head>
    <body>
      <h1>Chez Ketchup</h1>
      <p>19 Cité Alia Diene, Yoff, Dakar</p>
      <p>+221 33 856 62 53</p>
      <h2>${order.number}</h2>
      <p><strong>Client:</strong> ${order.customer_name}</p>
      <p><strong>Téléphone:</strong> ${order.phone}</p>
      <p><strong>Adresse:</strong> ${order.address}</p>
      <p><strong>Mode:</strong> ${order.fulfillment === 'pickup' ? 'Retrait' : 'Livraison'}</p>
      <p><strong>Paiement:</strong> ${paymentMethodLabel(order.payment_method)} - ${paymentStatusLabel(order.payment_status)}</p>
      <p><strong>Référence:</strong> ${order.payment_reference || '-'}</p>
      <p><strong>Livreur:</strong> ${order.delivery_name || 'Non assigné'}</p>
      ${order.note ? `<p><strong>Note:</strong> ${order.note}</p>` : ''}
      <h2>Articles</h2>
      <table>
        ${order.items.map((item) => `
          <tr>
            <td>${item.name}<br><span class="muted">x${item.qty} - ${money(item.unit_price)}</span></td>
            <td class="right">${money(item.qty * item.unit_price)}</td>
          </tr>
        `).join('')}
      </table>
      <p class="total">Total <span class="right" style="float:right">${money(order.total)}</span></p>
      <p class="muted">${new Date(order.created_at).toLocaleString('fr-FR')}</p>
      <button onclick="window.print()">Imprimer</button>
    </body>
    </html>
  `);
  popup.document.close();
  popup.focus();
}

async function updateStatus(id, status) {
  try {
    let reason = '';
    if (status === 'cancelled') {
      reason = prompt('Motif d annulation ?') || '';
      if (!reason.trim()) {
        toast('Motif d annulation obligatoire.');
        await loadAdmin();
        return;
      }
    }
    await api('admin/order-status', {
      method: 'PATCH',
      body: JSON.stringify({ id: Number(id), status, reason }),
    });
    toast('Statut mis à jour.');
    await loadAdmin();
  } catch (error) {
    toast(error.message);
  }
}

async function updateAvailability(id, available) {
  try {
    await api('admin/menu-availability', {
      method: 'PATCH',
      body: JSON.stringify({ id: Number(id), available: available === '1' }),
    });
    toast('Disponibilité mise à jour.');
    await loadCatalog();
    renderAdminMenu();
    renderMenu();
  } catch (error) {
    toast(error.message);
  }
}

async function loadCatalog() {
  const payload = await api('catalog');
  state.categories = payload.categories;
  state.menu = payload.items;
  state.operations = {
    settings: payload.settings || {},
    hours: payload.hours || [],
    zones: payload.zones || [],
    open_state: payload.open_state || { open: true },
  };
  renderCheckoutZones();
  renderFeatured();
  renderDailySpecials();
  renderMenu();
  renderPos();
  renderSocialLinks(state.operations.settings);
}

function closeMobileMenu() {
  qs('#main-nav')?.classList.remove('is-open');
  const toggle = qs('#menu-toggle');
  toggle?.setAttribute('aria-expanded', 'false');
  toggle?.setAttribute('aria-label', 'Ouvrir le menu');
}

function bindEvents() {
  qs('#menu-toggle')?.addEventListener('click', () => {
    const open = qs('#main-nav').classList.toggle('is-open');
    qs('#menu-toggle').setAttribute('aria-expanded', String(open));
    qs('#menu-toggle').setAttribute('aria-label', open ? 'Fermer le menu' : 'Ouvrir le menu');
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && qs('#main-nav')?.classList.contains('is-open')) {
      closeMobileMenu();
      qs('#menu-toggle')?.focus();
    }
  });
  document.addEventListener('click', (event) => {
    if (!event.target.closest('.topbar')) closeMobileMenu();
  });
  qs('#audit-search')?.addEventListener('input', () => renderAuditLogs());
  qs('#audit-action')?.addEventListener('change', () => renderAuditLogs());
  qs('#audit-entity')?.addEventListener('change', () => renderAuditLogs());
  document.addEventListener('click', (event) => {
    const pageButton = event.target.closest('[data-page]');
    if (pageButton) showPage(pageButton.dataset.page);

    const addButton = event.target.closest('[data-add]');
    if (addButton) addToCart(addButton.dataset.add, addButton);

    const categoryButton = event.target.closest('[data-category]');
    if (categoryButton) {
      state.currentCategory = categoryButton.dataset.category;
      renderMenu();
    }

    const qtyButton = event.target.closest('[data-qty]');
    if (qtyButton) updateQty(qtyButton.dataset.qty, Number(qtyButton.dataset.delta));
    const cancelButton = event.target.closest('[data-cancel-order]');
    if (cancelButton && !cancelButton.disabled) cancelCustomerOrder(cancelButton);

    const posAddButton = event.target.closest('[data-pos-add]');
    if (posAddButton) addToPos(posAddButton.dataset.posAdd);

    const posQtyButton = event.target.closest('[data-pos-qty]');
    if (posQtyButton) updatePosQty(posQtyButton.dataset.posQty, Number(posQtyButton.dataset.delta));

    if (event.target.closest('[data-pos-clear]')) clearPosTicket();

    const adminTab = event.target.closest('[data-admin-tab]');
    if (adminTab) {
      qsa('[data-admin-tab]').forEach((el) => el.classList.toggle('active', el === adminTab));
      qsa('.admin-tab').forEach((el) => el.classList.remove('active'));
      qs(`#admin-${adminTab.dataset.adminTab}`).classList.add('active');
      if (adminTab.dataset.adminTab === 'stats') loadStats();
      if (adminTab.dataset.adminTab === 'pos') renderPos();
      if (adminTab.dataset.adminTab === 'notifications') loadNotifications();
      if (adminTab.dataset.adminTab === 'clients') loadCustomers();
      if (adminTab.dataset.adminTab === 'audit') loadAuditLogs();
      if (adminTab.dataset.adminTab === 'operations') loadOperations();
      if (adminTab.dataset.adminTab === 'kitchen') loadKitchen();
      if (adminTab.dataset.adminTab === 'reviews') loadReviews();
      if (adminTab.dataset.adminTab === 'messages') loadContactMessages();
      if (adminTab.dataset.adminTab === 'users') loadUsers();
    }

    const printButton = event.target.closest('[data-print-order]');
    if (printButton) printOrder(printButton.dataset.printOrder);

    const editMenuButton = event.target.closest('[data-edit-menu]');
    if (editMenuButton) editMenuItem(editMenuButton.dataset.editMenu);

    const editDriverButton = event.target.closest('[data-edit-driver]');
    if (editDriverButton) editDeliveryStaff(editDriverButton.dataset.editDriver);

    const deleteMenuButton = event.target.closest('[data-delete-menu]');
    if (deleteMenuButton) deleteMenuItem(deleteMenuButton.dataset.deleteMenu);

    const exportButton = event.target.closest('[data-export]');
    if (exportButton) exportCsv(exportButton.dataset.export);

    const exportPdfButton = event.target.closest('[data-export-pdf]');
    if (exportPdfButton) exportPdf(exportPdfButton.dataset.exportPdf);

    if (event.target.closest('[data-system-check]')) loadSystemChecks();

    if (event.target.closest('[data-audit-refresh]')) loadAuditLogs();
    if (event.target.closest('[data-kitchen-refresh]')) loadKitchen();

    const nextStatus = event.target.closest('[data-next-status]');
    if (nextStatus) updateStatus(nextStatus.dataset.statusId, nextStatus.dataset.nextStatus);

    const editZoneButton = event.target.closest('[data-edit-zone]');
    if (editZoneButton) {
      const zone = (state.operations?.zones || []).find((entry) => Number(entry.id) === Number(editZoneButton.dataset.editZone));
      const form = qs('#zone-form');
      if (zone && form) {
        form.elements.id.value = zone.id;
        form.elements.name.value = zone.name;
        form.elements.fee.value = zone.fee;
        form.elements.minimum_order.value = zone.minimum_order;
        form.elements.estimated_minutes.value = zone.estimated_minutes;
        form.elements.active.checked = Boolean(zone.active);
      }
    }

    const editPromoButton = event.target.closest('[data-edit-promo]');
    if (editPromoButton) {
      const promo = (state.operations?.promos || []).find((entry) => Number(entry.id) === Number(editPromoButton.dataset.editPromo));
      const form = qs('#promo-form');
      if (promo && form) {
        form.elements.id.value = promo.id;
        form.elements.code.value = promo.code;
        form.elements.label.value = promo.label;
        form.elements.type.value = promo.type;
        form.elements.value.value = promo.value;
        form.elements.minimum_order.value = promo.minimum_order;
        form.elements.active.checked = Boolean(promo.active);
      }
    }

    if (event.target.closest('[data-generate-user-password]')) {
      const password = generatedSecret(14);
      qs('#user-form').elements.password.value = password;
      toast(`Mot de passe genere: ${password}`);
    }

    const notifyButton = event.target.closest('[data-notify-order]');
    if (notifyButton) queueNotification(notifyButton.dataset.notifyOrder, notifyButton.dataset.template);

    const notificationStatusButton = event.target.closest('[data-notification-status]');
    if (notificationStatusButton) updateNotificationStatus(notificationStatusButton.dataset.notificationStatus, notificationStatusButton.dataset.status);

    const contactMessageButton = event.target.closest('[data-contact-message]');
    if (contactMessageButton) updateContactMessageStatus(contactMessageButton.dataset.contactMessage, contactMessageButton.dataset.handled);

    const editUserButton = event.target.closest('[data-edit-user]');
    if (editUserButton) editUser(editUserButton.dataset.editUser);

    const driverStatusButton = event.target.closest('[data-driver-status]');
    if (driverStatusButton) updateDriverOrderStatus(driverStatusButton.dataset.driverStatus, driverStatusButton.dataset.status);

    if (event.target.closest('[data-driver-refresh]')) {
      loadDriverDashboard().then(() => toast('Courses actualisees.')).catch((error) => toast(error.message));
    }

    if (event.target.closest('[data-driver-logout]')) logoutDriver();
  });

  qs('#sort-menu')?.addEventListener('change', (event) => {
    state.sort = event.target.value;
    renderMenu();
  });
  qs('#open-cart')?.addEventListener('click', () => toggleCart(true));
  qs('#close-cart')?.addEventListener('click', () => toggleCart(false));
  qs('#scrim')?.addEventListener('click', () => toggleCart(false));
  qs('#logout-admin')?.addEventListener('click', logoutAdmin);
  qs('#checkout-form')?.addEventListener('submit', submitOrder);
  qs('#checkout-form')?.elements.fulfillment.addEventListener('change', syncFulfillmentFields);
  qs('#delivery-zone')?.addEventListener('change', renderCart);
  qs('#schedule-mode')?.addEventListener('change', syncScheduleFields);
  qs('#scheduled-at')?.addEventListener('focus', updateScheduleBounds);
  qs('#tracking-form')?.addEventListener('submit', trackOrder);
  qs('#review-form')?.addEventListener('submit', (event) => submitReview(event).catch((error) => toast(error.message)));
  qs('#driver-login-form')?.addEventListener('submit', submitDriverLogin);
  qs('#login-form')?.addEventListener('submit', login);
  qs('#pos-form')?.addEventListener('submit', submitPosOrder);
  qs('#pos-search')?.addEventListener('input', (event) => {
    state.posSearch = event.target.value;
    renderPos();
  });
  qs('#pos-category')?.addEventListener('change', (event) => {
    state.posCategory = event.target.value;
    renderPos();
  });
  qs('#pos-amount-received')?.addEventListener('input', renderPosTicket);
  qs('#menu-form')?.addEventListener('submit', saveMenuItem);
  qs('#menu-form-reset')?.addEventListener('click', resetMenuForm);
  qs('#menu-form')?.elements.image_url.addEventListener('input', syncMenuImagePreview);
  qs('#menu-form')?.elements.image_file.addEventListener('change', syncMenuImagePreview);
  qs('#delivery-form')?.addEventListener('submit', saveDeliveryStaff);
  qs('#delivery-form-reset')?.addEventListener('click', resetDeliveryForm);
  qs('#settings-form')?.addEventListener('submit', (event) => saveSettings(event).catch((error) => toast(error.message)));
  qs('#hours-form')?.addEventListener('submit', (event) => saveHours(event).catch((error) => toast(error.message)));
  qs('#zone-form')?.addEventListener('submit', (event) => saveZone(event).catch((error) => toast(error.message)));
  qs('#zone-form-reset')?.addEventListener('click', () => {
    const form = resetForm('#zone-form');
    if (!form) return;
    form.elements.id.value = '';
    form.elements.active.checked = true;
  });
  qs('#promo-form')?.addEventListener('submit', (event) => savePromo(event).catch((error) => toast(error.message)));
  qs('#promo-form-reset')?.addEventListener('click', () => {
    const form = resetForm('#promo-form');
    if (!form) return;
    form.elements.id.value = '';
    form.elements.active.checked = true;
  });
  qs('#notification-test-form')?.addEventListener('submit', sendNotificationTest);
  qs('#user-form')?.addEventListener('submit', saveUser);
  qs('#user-form-reset')?.addEventListener('click', resetUserForm);
  qs('#contact-form')?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    const data = new FormData(form);
    try {
      await api('contact', {
        method: 'POST',
        body: JSON.stringify({
          name: data.get('name'),
          phone: data.get('phone'),
          message: data.get('message'),
        }),
      });
      resetForm(form);
      toast('Message reçu. Nous vous recontactons rapidement.');
    } catch (error) {
      toast(error.message);
    }
  });

  document.addEventListener('change', (event) => {
    if (event.target.matches('[data-status-id]')) {
      updateStatus(event.target.dataset.statusId, event.target.value);
    }
    if (event.target.matches('[data-delivery-id]')) {
      updateDeliveryAssignment(event.target.dataset.deliveryId, event.target.value);
    }
    if (event.target.matches('[data-payment-id]')) {
      updatePaymentStatus(event.target.dataset.paymentId, event.target.value);
    }
    if (event.target.matches('[data-availability]')) {
      updateAvailability(event.target.dataset.availability, event.target.value);
    }
  });
}

async function init() {
  bindEvents();
  renderCart();
  try {
    await loadCatalog();
    if (!isAdminPage && state.driverPhone) {
      qs('#driver-login-form').elements.phone.value = state.driverPhone;
    }
    if (isAdminPage) {
      if (state.token) await loadAdmin();
      return;
    }
    const initialPage = new URLSearchParams(window.location.search).get('page');
    if (initialPage && qs(`#page-${initialPage}`)) showPage(initialPage);
  } catch (error) {
    toast(error.message);
  }
}

init();
