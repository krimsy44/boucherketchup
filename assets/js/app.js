const API = 'api/index.php';

const state = {
  categories: [],
  menu: [],
  currentCategory: 'all',
  sort: 'popular',
  cart: [],
  deliveryStaff: [],
  adminOrders: [],
  notifications: [],
  contactMessages: [],
  customers: [],
  users: [],
  stats: null,
  token: localStorage.getItem('ck_token') || '',
  user: JSON.parse(localStorage.getItem('ck_user') || 'null'),
};

const money = (value) => `${Number(value).toLocaleString('fr-FR')} CFA`;
const qs = (selector, root = document) => root.querySelector(selector);
const qsa = (selector, root = document) => [...root.querySelectorAll(selector)];

async function api(resource, options = {}) {
  const headers = { 'Content-Type': 'application/json', ...(options.headers || {}) };
  if (state.token) headers.Authorization = `Bearer ${state.token}`;

  const response = await fetch(`${API}?resource=${resource}`, { ...options, headers });
  const payload = await response.json();

  if (!response.ok || payload.ok === false) {
    throw new Error(payload.message || 'Une erreur est survenue.');
  }

  return payload;
}

function toast(message) {
  const el = qs('#toast');
  el.textContent = message;
  el.classList.add('show');
  setTimeout(() => el.classList.remove('show'), 3200);
}

function showPage(page) {
  qsa('.page').forEach((el) => el.classList.remove('active'));
  qsa('[data-page]').forEach((el) => el.classList.toggle('active', el.dataset.page === page));
  qs(`#page-${page}`).classList.add('active');

  if (page === 'menu') renderMenu();
  if (page === 'admin' && state.token) loadAdmin();
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function showLogin() {
  state.token = '';
  state.user = null;
  localStorage.removeItem('ck_token');
  localStorage.removeItem('ck_user');
  qs('#login-form').classList.remove('hidden');
  qs('#admin-content').classList.add('hidden');
  qs('#logout-admin').classList.add('hidden');
  qs('#admin-user').textContent = 'Connectez-vous pour gérer les commandes.';
}

function can(permission) {
  return Boolean(state.user?.permissions?.[permission]);
}

function applyRoleUI() {
  qsa('[data-permission]').forEach((el) => {
    el.classList.toggle('hidden', !can(el.dataset.permission));
  });
  qs('[data-admin-tab="menu"]').classList.toggle('hidden', !can('can_manage_menu'));
  qs('[data-admin-tab="delivery"]').classList.toggle('hidden', !can('can_manage_delivery'));
  qs('[data-admin-tab="stats"]').classList.toggle('hidden', !can('can_view_stats'));
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
    return Number(b.popular) - Number(a.popular);
  });
}

function foodCard(item) {
  const disabled = item.available ? '' : 'disabled';
  const buttonText = item.available ? '+' : 'Indispo';
  const media = item.image_url
    ? `<img class="food-img" src="${item.image_url}" alt="${item.name}" loading="lazy">`
    : `<div class="food-emoji">${item.emoji}</div>`;

  return `
    <article class="food-card">
      ${media}
      <div class="food-body">
        <div class="food-meta">${categoryLabel(item.category)}</div>
        <strong>${item.name}</strong>
        <p>${item.description}</p>
        <div class="food-actions">
          <span class="price">${priceLabel(item)}</span>
          <button class="add" data-add="${item.id}" ${disabled}>${buttonText}</button>
        </div>
      </div>
    </article>`;
}

function renderFeatured() {
  const featured = state.menu.filter((item) => item.popular).slice(0, 8);
  qs('#featured-grid').innerHTML = featured.map(foodCard).join('');
  qs('#featured-board').innerHTML = featured.slice(0, 4).map((item) => `
    <button class="hero-card" data-add="${item.id}">
      <span class="emoji">${item.emoji}</span>
      <span>
        <small class="muted">${categoryLabel(item.category)}</small>
        <strong>${item.name}</strong>
        <span class="price">${priceLabel(item)}</span>
      </span>
    </button>
  `).join('');
}

function renderCategories() {
  const categories = [{ id: 'all', name: 'Tous' }, ...state.categories];
  qs('#category-list').innerHTML = categories.map((category) => `
    <button class="${state.currentCategory === category.id ? 'active' : ''}" data-category="${category.id}">
      ${category.name}
    </button>
  `).join('');
}

function renderMenu() {
  renderCategories();
  const items = state.currentCategory === 'all'
    ? state.menu
    : state.menu.filter((item) => item.category === state.currentCategory);
  qs('#menu-grid').innerHTML = sortedItems(items).map(foodCard).join('');
}

function addToCart(id) {
  const item = state.menu.find((entry) => Number(entry.id) === Number(id));
  if (!item || !item.available) return;

  const existing = state.cart.find((line) => Number(line.id) === Number(id));
  if (existing) existing.qty += 1;
  else state.cart.push({ id: item.id, name: item.name, price: item.price, qty: 1 });

  renderCart();
  toast(`${item.name} ajouté au panier.`);
}

function updateQty(id, delta) {
  const line = state.cart.find((item) => Number(item.id) === Number(id));
  if (!line) return;
  line.qty += delta;
  if (line.qty <= 0) state.cart = state.cart.filter((item) => Number(item.id) !== Number(id));
  renderCart();
}

function cartTotal() {
  return state.cart.reduce((sum, item) => sum + item.price * item.qty, 0);
}

function renderCart() {
  qs('#cart-count').textContent = state.cart.reduce((sum, item) => sum + item.qty, 0);
  qs('#cart-total').textContent = money(cartTotal());

  qs('#cart-items').innerHTML = state.cart.length
    ? state.cart.map((item) => `
      <div class="cart-line">
        <div>
          <strong>${item.name}</strong>
          <div class="muted">${money(item.price * item.qty)}</div>
        </div>
        <div class="qty">
          <button data-qty="${item.id}" data-delta="-1">-</button>
          <span>${item.qty}</span>
          <button data-qty="${item.id}" data-delta="1">+</button>
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
  return `${paymentMethodLabel(order.payment_method)} sélectionné. Paiement à confirmer par le restaurant. Référence ${order.payment_reference || '-'}.`;
}

function orderItemsText(order) {
  return order.items.map((item) => `${item.name} x${item.qty}`).join(', ');
}

function orderCard(order) {
  const steps = ['pending', 'prep', 'delivery', 'done'];
  const activeIndex = Math.max(0, steps.indexOf(order.status));

  return `
    <article class="panel">
      <div class="section-head">
        <div>
          <h3>${order.number}</h3>
          <p class="muted">${order.customer_name} - ${order.phone}</p>
        </div>
        <span class="badge ${order.status}">${statusLabel(order.status)}</span>
      </div>
      <p>${orderItemsText(order)}</p>
      <strong class="price">${money(order.total)}</strong>
      <p><span class="badge pay-${order.payment_status}">${paymentStatusLabel(order.payment_status)}</span></p>
      <p class="muted">${paymentHint(order)}</p>
      <div class="timeline">
        ${steps.map((step, index) => `<span class="step ${index < activeIndex ? 'done' : index === activeIndex ? 'active' : ''}" title="${statusLabel(step)}"></span>`).join('')}
      </div>
    </article>
  `;
}

async function submitOrder(event) {
  event.preventDefault();
  if (state.cart.length === 0) {
    toast('Votre panier est vide.');
    return;
  }

  const form = new FormData(event.currentTarget);
  const body = {
    customer: {
      name: form.get('name'),
      phone: form.get('phone'),
      address: form.get('address'),
      note: form.get('note'),
    },
    fulfillment: form.get('fulfillment'),
    payment_method: form.get('payment_method'),
    items: state.cart.map((item) => ({ id: item.id, qty: item.qty })),
  };

  try {
    const payload = await api('orders', { method: 'POST', body: JSON.stringify(body) });
    state.cart = [];
    renderCart();
    event.currentTarget.reset();
    toggleCart(false);
    toast(`Commande ${payload.order.number} enregistrée.`);
    showPage('tracking');
    qs('#tracking-results').innerHTML = orderCard(payload.order);
  } catch (error) {
    toast(error.message);
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
        <select class="status-select" data-delivery-id="${order.id}">
          ${deliveryOptions(order.delivery_staff_id)}
        </select>
        <select class="status-select" data-payment-id="${order.id}">
          ${['pending', 'paid', 'failed', 'refunded'].map((status) => `
            <option value="${status}" ${order.payment_status === status ? 'selected' : ''}>${paymentStatusLabel(status)}</option>
          `).join('')}
        </select>
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
  qs('#admin-menu-grid').innerHTML = state.menu.map((item) => `
    <article class="panel">
      ${item.image_url ? `<img class="admin-food-img" src="${item.image_url}" alt="${item.name}" loading="lazy">` : `<div class="food-emoji">${item.emoji}</div>`}
      <h3>${item.name}</h3>
      <p class="muted">${categoryLabel(item.category)}</p>
      <p class="muted">${item.description || ''}</p>
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
  const form = qs('#menu-form');
  form.reset();
  form.elements.id.value = '';
  form.elements.available.checked = true;
  form.elements.image_file.value = '';
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
  form.elements.emoji.value = item.emoji || '';
  form.elements.image_url.value = item.image_url || '';
  form.elements.image_file.value = '';
  form.elements.description.value = item.description || '';
  form.elements.available.checked = Boolean(item.available);
  form.elements.popular.checked = Boolean(item.popular);
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
    emoji: data.get('emoji') || '🍽️',
    image_url: data.get('image_url') || '',
    description: data.get('description') || '',
    available: form.elements.available.checked,
    popular: form.elements.popular.checked,
  };
}

async function uploadMenuImage(file) {
  const formData = new FormData();
  formData.append('image', file);

  const headers = {};
  if (state.token) headers.Authorization = `Bearer ${state.token}`;

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
  const imageFile = event.currentTarget.elements.image_file.files[0];
  const isUpdate = Boolean(body.id);

  try {
    if (imageFile) {
      body.image_url = await uploadMenuImage(imageFile);
    }
    await api('admin/menu-items', {
      method: isUpdate ? 'PUT' : 'POST',
      body: JSON.stringify(body),
    });
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

function resetDeliveryForm() {
  const form = qs('#delivery-form');
  form.reset();
  form.elements.id.value = '';
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
    ['Date', 'Commande', 'Canal', 'Client', 'Message', 'Statut', 'Actions'],
    notifications.map((item) => [
      new Date(item.created_at).toLocaleString('fr-FR'),
      item.order_number || '-',
      item.channel,
      item.recipient,
      `<span class="truncate">${item.message}</span>`,
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
    ['Nom', 'Email', 'Rôle', 'Statut', 'Actions'],
    users.map((user) => [
      user.name,
      user.email,
      roleLabel(user.role),
      user.active ? '<span class="badge done">Actif</span>' : '<span class="badge pay-failed">Inactif</span>',
      `<button class="secondary small" data-edit-user="${user.id}">Modifier</button>`,
    ])
  );
}

function resetUserForm() {
  const form = qs('#user-form');
  form.reset();
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

async function loadStats() {
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

async function loadAdmin() {
  try {
    const payload = await api('admin/summary');
    qs('#login-form').classList.add('hidden');
    qs('#admin-content').classList.remove('hidden');
    qs('#logout-admin').classList.remove('hidden');
    qs('#admin-user').textContent = `${state.user.name} - ${state.user.role}`;
    applyRoleUI();
    state.deliveryStaff = payload.delivery_staff || [];

    qs('#kpi-grid').innerHTML = `
      <div class="kpi"><span>CA aujourd'hui</span><strong>${money(payload.summary.revenue_today)}</strong></div>
      <div class="kpi"><span>Commandes aujourd'hui</span><strong>${payload.summary.orders_today}</strong></div>
      <div class="kpi"><span>En attente</span><strong>${payload.summary.pending}</strong></div>
      <div class="kpi"><span>En livraison</span><strong>${payload.summary.delivery}</strong></div>
      <div class="kpi"><span>Paiements en attente</span><strong>${payload.summary.payments_pending}</strong></div>
      <div class="kpi"><span>Payé aujourd'hui</span><strong>${money(payload.summary.payments_paid_today)}</strong></div>
    `;
    renderAdminTables(payload.orders);
    renderAdminMenu();
    renderDeliveryStaff();
    await loadCustomers();
    await loadContactMessages();
    await loadStats();
    await loadNotifications();
    await loadUsers();
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
  renderFeatured();
  renderMenu();
}

function bindEvents() {
  document.addEventListener('click', (event) => {
    const pageButton = event.target.closest('[data-page]');
    if (pageButton) showPage(pageButton.dataset.page);

    const addButton = event.target.closest('[data-add]');
    if (addButton) addToCart(addButton.dataset.add);

    const categoryButton = event.target.closest('[data-category]');
    if (categoryButton) {
      state.currentCategory = categoryButton.dataset.category;
      renderMenu();
    }

    const qtyButton = event.target.closest('[data-qty]');
    if (qtyButton) updateQty(qtyButton.dataset.qty, Number(qtyButton.dataset.delta));

    const adminTab = event.target.closest('[data-admin-tab]');
    if (adminTab) {
      qsa('[data-admin-tab]').forEach((el) => el.classList.toggle('active', el === adminTab));
      qsa('.admin-tab').forEach((el) => el.classList.remove('active'));
      qs(`#admin-${adminTab.dataset.adminTab}`).classList.add('active');
      if (adminTab.dataset.adminTab === 'stats') loadStats();
      if (adminTab.dataset.adminTab === 'notifications') loadNotifications();
      if (adminTab.dataset.adminTab === 'clients') loadCustomers();
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

    const notifyButton = event.target.closest('[data-notify-order]');
    if (notifyButton) queueNotification(notifyButton.dataset.notifyOrder, notifyButton.dataset.template);

    const notificationStatusButton = event.target.closest('[data-notification-status]');
    if (notificationStatusButton) updateNotificationStatus(notificationStatusButton.dataset.notificationStatus, notificationStatusButton.dataset.status);

    const contactMessageButton = event.target.closest('[data-contact-message]');
    if (contactMessageButton) updateContactMessageStatus(contactMessageButton.dataset.contactMessage, contactMessageButton.dataset.handled);

    const editUserButton = event.target.closest('[data-edit-user]');
    if (editUserButton) editUser(editUserButton.dataset.editUser);
  });

  qs('#sort-menu').addEventListener('change', (event) => {
    state.sort = event.target.value;
    renderMenu();
  });
  qs('#open-cart').addEventListener('click', () => toggleCart(true));
  qs('#close-cart').addEventListener('click', () => toggleCart(false));
  qs('#scrim').addEventListener('click', () => toggleCart(false));
  qs('#logout-admin').addEventListener('click', () => {
    showLogin();
    toast('Session fermée.');
  });
  qs('#checkout-form').addEventListener('submit', submitOrder);
  qs('#tracking-form').addEventListener('submit', trackOrder);
  qs('#login-form').addEventListener('submit', login);
  qs('#menu-form').addEventListener('submit', saveMenuItem);
  qs('#menu-form-reset').addEventListener('click', resetMenuForm);
  qs('#delivery-form').addEventListener('submit', saveDeliveryStaff);
  qs('#delivery-form-reset').addEventListener('click', resetDeliveryForm);
  qs('#user-form').addEventListener('submit', saveUser);
  qs('#user-form-reset').addEventListener('click', resetUserForm);
  qs('#contact-form').addEventListener('submit', async (event) => {
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
      form.reset();
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
    const initialPage = new URLSearchParams(window.location.search).get('page');
    if (initialPage && qs(`#page-${initialPage}`)) showPage(initialPage);
  } catch (error) {
    toast(error.message);
  }
}

init();
