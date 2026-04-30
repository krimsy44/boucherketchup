<?php
declare(strict_types=1);
?><!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Chez Ketchup - Commande en ligne à Dakar</title>
  <meta name="description" content="Commandez en ligne chez Chez Ketchup à Yoff, Dakar. Fast Food, Pizzeria, Gastro, Lounge et livraison.">
  <link rel="icon" href="assets/img/chez-ketchup-logo.jpeg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
  <header class="topbar">
    <button class="brand" data-page="home" aria-label="Accueil Chez Ketchup">
      <img class="brand-logo" src="assets/img/chez-ketchup-logo.jpeg" alt="" loading="eager">
      <span>Chez <strong>Ketchup</strong></span>
    </button>
    <nav class="nav" aria-label="Navigation principale">
      <button data-page="home" class="active">Accueil</button>
      <button data-page="menu">Menu</button>
      <button data-page="tracking">Suivi</button>
      <button data-page="driver">Livreur</button>
      <button data-page="admin">Gestion</button>
      <button data-page="contact">Contact</button>
    </nav>
    <button class="cart-button" id="open-cart">Panier <span id="cart-count">0</span></button>
  </header>

  <main>
    <section class="page active" id="page-home">
      <div class="hero">
        <video class="hero-video" autoplay muted loop playsinline preload="metadata" aria-hidden="true">
          <source src="assets/video/hero-chez-ketchup.mp4" type="video/mp4">
        </video>
        <div class="hero-copy">
          <p class="eyebrow"><span></span> Yoff, Dakar - Ouvert tous les jours</p>
          <h1>Le goût de Dakar dans votre assiette</h1>
          <p class="lead">Grill, Fast Food, Pizzeria, Gastro, Lounge et livraison à domicile. Une expérience portée par Boucher Ketchup et pensée pour commander vite, suivre clair, gérer mieux.</p>
          <div class="actions">
            <button class="primary" data-page="menu">Commander</button>
            <button class="secondary" data-page="tracking">Suivre ma commande</button>
          </div>
          <div class="stats">
            <div><strong>397K</strong><span>Instagram</span></div>
            <div><strong>1.1M</strong><span>TikTok</span></div>
            <div><strong>4.9</strong><span>Note clients</span></div>
          </div>
        </div>
        <div class="hero-board" id="featured-board"></div>
      </div>
      <section class="section">
        <div class="section-head">
          <h2>Nos incontournables</h2>
          <button data-page="menu">Tout voir</button>
        </div>
        <div class="grid cards" id="featured-grid"></div>
      </section>
    </section>

    <section class="page" id="page-menu">
      <section class="section">
        <div class="section-head">
          <h2>Menu</h2>
          <div class="toolbar">
            <select id="sort-menu" aria-label="Trier le menu">
              <option value="popular">Popularité</option>
              <option value="price-asc">Prix croissant</option>
              <option value="price-desc">Prix décroissant</option>
            </select>
          </div>
        </div>
        <div class="chips" id="category-list"></div>
        <div class="grid cards" id="menu-grid"></div>
      </section>
    </section>

    <section class="page" id="page-tracking">
      <section class="section narrow">
        <h2>Suivi de commande</h2>
        <form class="panel form" id="tracking-form">
          <label>Numéro de commande ou téléphone
            <input type="text" name="q" placeholder="CK-2026-0001 ou +221..." required>
          </label>
          <button class="primary" type="submit">Rechercher</button>
        </form>
        <div class="stack" id="tracking-results"></div>
      </section>
    </section>

    <section class="page" id="page-driver">
      <section class="section narrow">
        <h2>Espace livreur</h2>
        <form class="panel form" id="driver-login-form">
          <label>Telephone livreur
            <input type="tel" name="phone" placeholder="+221..." required>
          </label>
          <label>Code d'acces
            <input type="password" name="code" inputmode="numeric" minlength="4" required>
          </label>
          <button class="primary" type="submit">Voir mes courses</button>
        </form>
        <div class="stack" id="driver-dashboard"></div>
      </section>
    </section>

    <section class="page" id="page-admin">
      <section class="admin-shell">
        <aside class="admin-side">
          <h2>Gestion</h2>
          <p id="admin-user">Connectez-vous pour gérer les commandes.</p>
          <button id="logout-admin" class="hidden">Se déconnecter</button>
          <button data-admin-tab="dashboard" class="active">Tableau de bord</button>
          <button data-admin-tab="orders">Commandes</button>
          <button data-admin-tab="menu">Menu</button>
          <button data-admin-tab="delivery">Livreurs</button>
          <button data-admin-tab="stats">Statistiques</button>
          <button data-admin-tab="notifications">Notifications</button>
          <button data-admin-tab="audit" data-permission="can_view_stats">Journal</button>
          <button data-admin-tab="clients">Clients</button>
          <button data-admin-tab="messages">Messages</button>
          <button data-admin-tab="users" data-permission="can_manage_users">Utilisateurs</button>
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
            <div class="admin-tab active" id="admin-dashboard">
              <div class="kpi-grid" id="kpi-grid"></div>
              <div class="panel">
                <h3>Dernières commandes</h3>
                <div class="table-wrap"><table id="dashboard-orders"></table></div>
              </div>
            </div>
            <div class="admin-tab" id="admin-orders">
              <div class="panel">
                <h3>Gestion des commandes</h3>
                <div class="table-wrap"><table id="admin-orders-table"></table></div>
              </div>
            </div>
            <div class="admin-tab" id="admin-menu">
              <form class="panel form menu-editor" id="menu-form">
                <h3 id="menu-form-title">Ajouter un plat</h3>
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
                  <label>Emoji
                    <input name="emoji" maxlength="8" placeholder="🍽️">
                  </label>
                  <label>URL image
                    <input name="image_url" type="url" placeholder="https://...">
                  </label>
                  <label>Image du plat
                    <input name="image_file" type="file" accept="image/jpeg,image/png,image/webp">
                  </label>
                </div>
                <label>Description
                  <textarea name="description" rows="3"></textarea>
                </label>
                <div class="checks">
                  <label><input name="available" type="checkbox" checked> Disponible</label>
                  <label><input name="popular" type="checkbox"> Incontournable</label>
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
                  <label>Code d'accÃ¨s
                    <input name="access_code" type="password" minlength="4" placeholder="Laisser vide pour conserver">
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
                  <button class="secondary" type="button" data-generate-delivery-code>Generer un code</button>
                  <button class="secondary" type="button" id="delivery-form-reset">Annuler</button>
                </div>
              </form>
              <div class="grid admin-menu" id="delivery-grid"></div>
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
                  <h3>Journal d'audit</h3>
                  <button class="secondary small" data-audit-refresh>Rafraichir</button>
                </div>
                <div class="table-wrap"><table id="audit-table"></table></div>
              </div>
            </div>
            <div class="admin-tab" id="admin-messages">
              <div class="panel">
                <h3>Messages contact</h3>
                <div class="table-wrap"><table id="messages-table"></table></div>
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

    <section class="page" id="page-contact">
      <section class="section contact-grid">
        <div>
          <h2>Contact</h2>
          <div class="info-list">
            <p><strong>Adresse</strong><span>19 Cité Alia Diene, Yoff, Dakar</span></p>
            <p><strong>Téléphones</strong><span><a href="tel:+221338566253">+221 33 856 62 53</a> / <a href="tel:+221781469090">+221 78 146 90 90</a></span></p>
            <p><strong>WhatsApp</strong><span><a href="https://wa.me/221788289879">+221 78 828 98 79</a></span></p>
            <p><strong>Horaires</strong><span>Tous les jours, 8h00 - 23h00</span></p>
          </div>
        </div>
        <form class="panel form" id="contact-form">
          <h3>Envoyer un message</h3>
          <label>Nom <input name="name" type="text" required></label>
          <label>Téléphone <input name="phone" type="tel" required></label>
          <label>Message <textarea name="message" rows="5" required></textarea></label>
          <button class="primary" type="submit">Envoyer</button>
        </form>
      </section>
    </section>
  </main>

  <aside class="cart" id="cart">
    <div class="cart-head">
      <h2>Panier</h2>
      <button id="close-cart" aria-label="Fermer le panier">×</button>
    </div>
    <div class="cart-items" id="cart-items"></div>
    <form class="checkout" id="checkout-form">
      <label>Nom complet <input name="name" required></label>
      <label>Téléphone <input name="phone" type="tel" required></label>
      <label>Adresse <input name="address" placeholder="Quartier, rue ou point de repere"></label>
      <label>Note spéciale <textarea name="note" rows="1" placeholder="Sans oignons, sauce à part..."></textarea></label>
      <div class="two">
        <label>Mode
          <select name="fulfillment">
            <option value="delivery">Livraison</option>
            <option value="pickup">Retrait</option>
          </select>
        </label>
        <label>Paiement
          <select name="payment_method">
            <option value="orange_money">Orange Money</option>
            <option value="wave">Wave</option>
          </select>
        </label>
      </div>
      <div class="total"><span>Total</span><strong id="cart-total">0 CFA</strong></div>
      <button class="primary wide" type="submit">Commander maintenant</button>
    </form>
  </aside>
  <div class="scrim" id="scrim"></div>
  <div class="toast" id="toast"></div>

  <script src="assets/js/app.js"></script>
</body>
</html>
