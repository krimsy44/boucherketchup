<?php
declare(strict_types=1);
header("Cache-Control: no-store");
?><!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Chez Ketchup - Administration</title>
  <meta name="robots" content="noindex, nofollow">
  <meta name="description" content="Commandez en ligne chez Chez Ketchup à Yoff, Dakar. Fast Food, Pizzeria, Gastro, Lounge et livraison.">
  <link rel="icon" href="assets/img/chez-ketchup-logo.jpeg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
  <link rel="me" href="https://www.instagram.com/boucher_ketchup/">
  <meta property="og:site_name" content="Chez Ketchup">
  <meta property="og:title" content="Chez Ketchup - Commande en ligne à Dakar">
  <meta property="og:description" content="Commandez en ligne chez Chez Ketchup et suivez Boucher Ketchup sur ses réseaux sociaux.">
  <meta name="theme-color" content="#c9292f">
  <link rel="stylesheet" href="assets/css/app.css?v=20261012-9">
</head>
<body data-app="admin">
  <header class="topbar">
    <a class="brand" href="admin.php" aria-label="Accueil administration Chez Ketchup"><img class="brand-logo" src="assets/img/chez-ketchup-logo.jpeg" alt=""> <span>Chez <strong>Ketchup</strong> &mdash; Administration</span></a>
    <a class="secondary" href="index.php">Voir le site public</a>
  </header>
  <main>
    <section class="page active" id="page-admin">
      <section class="admin-shell">
        <aside class="admin-side" id="admin-sidebar">
          <h2>Gestion</h2>
          <p id="admin-user">Connectez-vous pour gérer les commandes.</p>
          <button id="logout-admin" class="hidden">Se déconnecter</button>
          <div class="admin-nav hidden" id="admin-nav">
            <button data-admin-tab="dashboard" class="active">Tableau de bord</button>
            <button data-admin-tab="pos" data-permission="can_use_pos">Caisse</button>
            <button data-admin-tab="orders">Commandes</button>
            <button data-admin-tab="kitchen">Cuisine</button>
            <button data-admin-tab="menu">Menu</button>
            <button data-admin-tab="delivery">Livreurs</button>
            <button data-admin-tab="operations">Operations</button>
            <button data-admin-tab="stats">Statistiques</button>
            <button data-admin-tab="notifications">Notifications</button>
            <button data-admin-tab="audit" data-permission="can_view_stats">Journal</button>
            <button data-admin-tab="clients">Clients</button>
            <button data-admin-tab="messages">Messages</button>
            <button data-admin-tab="reviews">Avis</button>
            <button data-admin-tab="users" data-permission="can_manage_users">Utilisateurs</button>
          </div>
        </aside>
        <div class="admin-main">
          <form class="panel form" id="login-form">
            <h3>Connexion back-office</h3>
            <label>Email
              <input type="email" name="email" value="admin@chezketchup.com" required>
            </label>
            <label>Mot de passe
              <input type="password" name="password" required>
            </label>
            <button class="primary" type="submit">Se connecter</button>
          </form>

          <div class="admin-content hidden" id="admin-content">
            <p class="panel" id="admin-order-alert" role="status" aria-live="polite" hidden></p>
            <div class="admin-tab active" id="admin-dashboard">
              <div class="kpi-grid" id="kpi-grid"></div>
              <div class="panel">
                <h3>Dernières commandes</h3>
                <div class="table-wrap"><table id="dashboard-orders"></table></div>
              </div>
            </div>
            <div class="admin-tab" id="admin-pos">
              <div class="pos-shell">
                <section class="panel pos-catalog">
                  <div class="section-head">
                    <h3>Caisse sur place</h3>
                    <div class="toolbar">
                      <input id="pos-search" type="search" placeholder="Rechercher un plat">
                      <select id="pos-category" aria-label="Filtrer la caisse"></select>
                    </div>
                  </div>
                  <div class="pos-grid" id="pos-grid"></div>
                </section>
                <form class="panel form pos-ticket" id="pos-form">
                  <h3>Ticket caisse</h3>
                  <div class="form-grid">
                    <label>Client
                      <input name="name" required>
                    </label>
                    <label>Téléphone
                      <input name="phone" type="tel" required>
                    </label>
                  </div>
                  <label>Note
                    <textarea name="note" rows="2" placeholder="Table, précision cuisine..."></textarea>
                  </label>
                  <div class="stack" id="pos-lines"></div>
                  <div class="two">
                    <label>Paiement
                      <select name="payment_method">
                        <option value="cash">Espèces</option>
                        <option value="orange_money">Orange Money</option>
                        <option value="wave">Wave</option>
                      </select>
                    </label>
                    <label>Statut
                      <select name="status">
                        <option value="prep">Envoyer en cuisine</option>
                        <option value="done">Vente directe</option>
                        <option value="pending">En attente</option>
                      </select>
                    </label>
                  </div>
                  <label>Montant reçu
                    <input name="amount_received" id="pos-amount-received" type="number" min="0" step="100">
                  </label>
                  <div class="total small-total"><span>Total</span><strong id="pos-total">0 CFA</strong></div>
                  <div class="total small-total"><span>Rendu</span><strong id="pos-change">0 CFA</strong></div>
                  <div class="checkout-notice" id="pos-notice" role="status" aria-live="polite"></div>
                  <div class="actions">
                    <button class="primary" type="submit">Encaisser</button>
                    <button class="secondary danger" type="button" data-pos-clear>Vider</button>
                  </div>
                </form>
              </div>
            </div>
            <div class="admin-tab" id="admin-orders">
              <div class="panel">
                <h3>Gestion des commandes</h3>
                <div class="table-wrap"><table id="admin-orders-table"></table></div>
              </div>
            </div>
            <div class="admin-tab" id="admin-kitchen">
              <div class="panel">
                <div class="section-head">
                  <h3>Ecran cuisine</h3>
                  <button class="secondary small" data-kitchen-refresh>Rafraichir</button>
                </div>
                <div class="grid kitchen-grid" id="kitchen-grid"></div>
              </div>
            </div>
            <div class="admin-tab" id="admin-menu">
              <form class="panel form menu-editor" id="menu-form">
                <div class="section-head">
                  <h3 id="menu-form-title">Ajouter un plat</h3>
                  <span class="badge pay-pending" id="daily-special-count">0/3 plats du jour affichés</span>
                </div>
                <input type="hidden" name="id">
                <div class="form-grid">
                  <label>Nom du plat
                    <input name="name" required>
                  </label>
                  <label>Catégorie
                    <select name="category" id="menu-form-category" required></select>
                  </label>
                  <label>Prix de base
                    <input name="price" type="number" min="1" step="1" required>
                  </label>
                  <label>Prix max / grande taille
                    <input name="max_price" type="number" min="1" step="1">
                  </label>
                  <label>Stock
                    <input name="stock_qty" type="number" min="0" step="1" value="0">
                  </label>
                  <label>Alerte stock bas
                    <input name="low_stock_threshold" type="number" min="0" step="1" value="3">
                  </label>
                  <label>Emoji
                    <input name="emoji" maxlength="8" placeholder="🍽️">
                  </label>
                  <label>URL image
                    <input name="image_url" type="text" placeholder="https://... ou uploads/menu/image.jpg">
                  </label>
                  <label>Image du plat
                    <input name="image_file" type="file" accept="image/jpeg,image/png,image/webp">
                  </label>
                </div>
                <div class="menu-image-preview" id="menu-image-preview" hidden>
                  <img src="" alt="Apercu image du plat">
                  <span class="muted" id="menu-image-preview-text"></span>
                </div>
                <label>Description
                  <textarea name="description" rows="3"></textarea>
                </label>
                <label>Options du plat
                  <textarea name="options_text" rows="3" placeholder="Grande taille|1000&#10;Sauce supplementaire|300"></textarea>
                </label>
                <div class="checks">
                  <label><input name="available" type="checkbox" checked> Disponible</label>
                  <label><input name="popular" type="checkbox"> Incontournable</label>
                  <label><input name="daily_special" type="checkbox"> Plat du jour accueil</label>
                  <label><input name="stock_enabled" type="checkbox"> Suivre le stock</label>
                </div>
                <div class="actions">
                  <button class="primary" type="submit">Enregistrer</button>
                  <button class="secondary" type="button" id="menu-form-reset">Annuler</button>
                </div>
              </form>
              <div class="grid admin-menu" id="admin-menu-grid"></div>
            </div>
            <div class="admin-tab" id="admin-delivery">
              <form class="panel form menu-editor" id="delivery-form">
                <h3 id="delivery-form-title">Ajouter un livreur</h3>
                <input type="hidden" name="id">
                <div class="form-grid">
                  <label>Nom
                    <input name="name" required>
                  </label>
                  <label>Téléphone
                    <input name="phone" type="tel" required>
                  </label>
                  <label>Mot de passe
                    <input name="password" type="password" minlength="8" autocomplete="new-password" required placeholder="Mot de passe du livreur">
                  </label>
                  <label>Statut
                    <select name="status">
                      <option value="available">Disponible</option>
                      <option value="busy">En course</option>
                      <option value="absent">Absent</option>
                    </select>
                  </label>
                </div>
                <div class="checks">
                  <label><input name="active" type="checkbox" checked> Actif</label>
                </div>
                <div class="actions">
                  <button class="primary" type="submit">Enregistrer</button>
                  <button class="secondary" type="button" id="delivery-form-reset">Annuler</button>
                </div>
              </form>
              <div class="grid admin-menu" id="delivery-grid"></div>
            </div>
            <div class="admin-tab" id="admin-operations">
              <form class="panel form menu-editor" id="settings-form">
                <h3>Parametres restaurant</h3>
                <div class="form-grid">
                  <label>Nom <input name="restaurant_name"></label>
                  <label>Telephone <input name="restaurant_phone"></label>
                  <label>WhatsApp <input name="restaurant_whatsapp"></label>
                  <label>Temps retrait <input name="pickup_eta_minutes" type="number" min="5" step="5"></label>
                  <label>Instagram <input name="social_instagram" type="url" placeholder="https://www.instagram.com/..."></label>
                  <label>TikTok <input name="social_tiktok" type="url" placeholder="https://www.tiktok.com/@..."></label>
                  <label>Facebook <input name="social_facebook" type="url" placeholder="https://www.facebook.com/..."></label>
                  <label>YouTube <input name="social_youtube" type="url" placeholder="https://www.youtube.com/@..."></label>
                </div>
                <label>Adresse <input name="restaurant_address"></label>
                <div class="checks">
                  <label><input name="orders_paused" type="checkbox"> Pause commandes en ligne</label>
                </div>
                <button class="primary" type="submit">Enregistrer</button>
              </form>
              <form class="panel form menu-editor" id="hours-form">
                <h3>Horaires</h3>
                <div class="hours-grid" id="hours-grid"></div>
                <button class="primary" type="submit">Enregistrer les horaires</button>
              </form>
              <form class="panel form menu-editor" id="zone-form">
                <h3>Zone de livraison</h3>
                <input type="hidden" name="id">
                <div class="form-grid">
                  <label>Zone <input name="name" required></label>
                  <label>Frais <input name="fee" type="number" min="0" step="100" required></label>
                  <label>Minimum <input name="minimum_order" type="number" min="0" step="100"></label>
                  <label>Delai minutes <input name="estimated_minutes" type="number" min="5" step="5"></label>
                </div>
                <div class="checks">
                  <label><input name="active" type="checkbox" checked> Active</label>
                </div>
                <div class="actions">
                  <button class="primary" type="submit">Enregistrer</button>
                  <button class="secondary" type="button" id="zone-form-reset">Annuler</button>
                </div>
              </form>
              <div class="grid admin-menu" id="zones-grid"></div>
              <form class="panel form menu-editor" id="promo-form">
                <h3>Code promo</h3>
                <input type="hidden" name="id">
                <div class="form-grid">
                  <label>Code <input name="code" required></label>
                  <label>Libelle <input name="label" required></label>
                  <label>Type
                    <select name="type">
                      <option value="fixed">Montant fixe</option>
                      <option value="percent">Pourcentage</option>
                    </select>
                  </label>
                  <label>Valeur <input name="value" type="number" min="1" step="1" required></label>
                  <label>Minimum <input name="minimum_order" type="number" min="0" step="100"></label>
                </div>
                <div class="checks">
                  <label><input name="active" type="checkbox" checked> Actif</label>
                </div>
                <div class="actions">
                  <button class="primary" type="submit">Enregistrer</button>
                  <button class="secondary" type="button" id="promo-form-reset">Annuler</button>
                </div>
              </form>
              <div class="table-wrap panel"><table id="promos-table"></table></div>
            </div>
            <div class="admin-tab" id="admin-stats">
              <div class="panel">
                <div class="section-head">
                  <h3>Rapports</h3>
                  <div class="actions">
                    <button class="secondary small" data-export="orders">Commandes CSV</button>
                    <button class="secondary small" data-export="clients">Clients CSV</button>
                    <button class="secondary small" data-export="payments">Paiements CSV</button>
                    <button class="secondary small" data-export="menu">Menu CSV</button>
                    <button class="secondary small" data-export="notifications">Notifications CSV</button>
                    <button class="secondary small" data-export-pdf="summary">Rapport PDF</button>
                  </div>
                </div>
                <div class="kpi-grid" id="stats-kpis"></div>
              </div>
              <div class="charts-grid">
                <div class="panel">
                  <h3>Revenus sur 14 jours</h3>
                  <div class="bar-chart" id="stats-revenue"></div>
                </div>
                <div class="panel">
                  <h3>Top plats</h3>
                  <div class="table-wrap"><table id="stats-top-items"></table></div>
                </div>
                <div class="panel">
                  <h3>Ventes par catégorie</h3>
                  <div class="table-wrap"><table id="stats-categories"></table></div>
                </div>
                <div class="panel">
                  <h3>Paiements</h3>
                  <div class="table-wrap"><table id="stats-payments"></table></div>
                </div>
              </div>
            </div>
            <div class="admin-tab" id="admin-notifications">
              <form class="panel form menu-editor" id="notification-test-form">
                <h3>Test fournisseur</h3>
                <div class="form-grid">
                  <label>Canal
                    <select name="channel">
                      <option value="whatsapp">WhatsApp</option>
                      <option value="sms">SMS</option>
                    </select>
                  </label>
                  <label>Destinataire
                    <input name="recipient" type="tel" placeholder="+221..." required>
                  </label>
                </div>
                <label>Message
                  <textarea name="message" rows="3" required>Test Chez Ketchup: notification automatique bien recue.</textarea>
                </label>
                <button class="primary" type="submit">Envoyer le test</button>
              </form>
              <div class="panel">
                <h3>Notifications WhatsApp / SMS</h3>
                <p class="muted">Préparez les messages clients. L'envoi automatique peut maintenant passer par webhook, Twilio ou Meta WhatsApp selon la configuration.</p>
                <div class="table-wrap"><table id="notifications-table"></table></div>
              </div>
            </div>
            <div class="admin-tab" id="admin-clients">
              <div class="panel">
                <h3>Clients</h3>
                <div class="table-wrap"><table id="clients-table"></table></div>
              </div>
            </div>
            <div class="admin-tab" id="admin-audit">
              <div class="panel">
                <div class="section-head">
                  <h3>Journal des actions</h3>
                  <button class="secondary small" data-audit-refresh>Rafraichir</button>
                </div>
                <p class="muted">Historique des 80 derni?res actions enregistr?es, de la plus r?cente ? la plus ancienne.</p>
                <div class="toolbar audit-filters">
                  <label>Rechercher <input id="audit-search" type="search" placeholder="Utilisateur, cible ou d?tail"></label>
                  <label>Action <select id="audit-action"><option value="">Toutes les actions</option></select></label>
                  <label>Cible <select id="audit-entity"><option value="">Toutes les cibles</option></select></label>
                </div>
                <p id="audit-count" class="muted" role="status" aria-live="polite"></p>
                <div class="table-wrap"><table id="audit-table"></table></div>
              </div>
            </div>
            <div class="admin-tab" id="admin-messages">
              <div class="panel">
                <h3>Messages contact</h3>
                <div class="table-wrap"><table id="messages-table"></table></div>
              </div>
            </div>
            <div class="admin-tab" id="admin-reviews">
              <div class="panel">
                <h3>Avis clients</h3>
                <div class="table-wrap"><table id="reviews-table"></table></div>
              </div>
            </div>
            <div class="admin-tab" id="admin-users">
              <form class="panel form menu-editor" id="user-form">
                <h3 id="user-form-title">Ajouter un utilisateur</h3>
                <input type="hidden" name="id">
                <div class="form-grid">
                  <label>Nom
                    <input name="name" required>
                  </label>
                  <label>Email
                    <input name="email" type="email" required>
                  </label>
                  <label>Rôle
                    <select name="role">
                      <option value="cashier">Caissier</option>
                      <option value="manager">Gérant</option>
                      <option value="super_admin">Super Admin</option>
                    </select>
                  </label>
                  <label>Mot de passe
                    <input name="password" type="password" minlength="8" placeholder="Laisser vide pour ne pas changer">
                  </label>
                </div>
                <div class="checks">
                  <label><input name="active" type="checkbox" checked> Actif</label>
                </div>
                <div class="actions">
                  <button class="primary" type="submit">Enregistrer</button>
                  <button class="secondary" type="button" data-generate-user-password>Generer un mot de passe</button>
                  <button class="secondary" type="button" id="user-form-reset">Annuler</button>
                </div>
              </form>
              <div class="panel">
                <h3>Comptes back-office</h3>
                <div class="table-wrap"><table id="users-table"></table></div>
              </div>
            </div>
          </div>
        </div>
      </section>
    </section>

  </main>
  <script src="assets/js/app.js?v=20261012-9"></script>
</body>
</html>
