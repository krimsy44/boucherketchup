<?php
declare(strict_types=1);
if (($_GET["page"] ?? "") === "admin") {
    header("Location: admin.php", true, 302);
    exit;
}
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
  <link rel="me" href="https://www.instagram.com/boucher_ketchup/">
  <meta property="og:site_name" content="Chez Ketchup">
  <meta property="og:title" content="Chez Ketchup - Commande en ligne à Dakar">
  <meta property="og:description" content="Commandez en ligne chez Chez Ketchup et suivez Boucher Ketchup sur ses réseaux sociaux.">
  <link rel="manifest" href="manifest.webmanifest?v=20261010-2">
  <meta name="theme-color" content="#c9292f">
  <link rel="stylesheet" href="assets/css/app.css?v=20261011-1">
</head>
<body>
  <header class="topbar">
    <button class="brand" data-page="home" aria-label="Accueil Chez Ketchup">
      <img class="brand-logo" src="assets/img/chez-ketchup-logo.jpeg" alt="" loading="eager">
      <span>Chez <strong>Ketchup</strong></span>
    </button>
    <button class="menu-toggle" id="menu-toggle" type="button" aria-label="Ouvrir le menu" aria-controls="main-nav" aria-expanded="false">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
    </button>
    <nav class="nav" id="main-nav" aria-label="Navigation principale">
      <button data-page="home" class="active">Accueil</button>
      <button data-page="menu">Menu</button>
      <button data-page="tracking">Suivi</button>
      <button data-page="driver">Livreur</button>
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
          <h2>Plats du jour</h2>
          <button data-page="menu">Commander</button>
        </div>
        <div class="grid cards daily-grid" id="daily-specials-grid"></div>
      </section>
      <section class="section">
        <div class="section-head">
          <h2>Nos incontournables</h2>
          <button data-page="menu">Tout voir</button>
        </div>
        <div class="grid cards" id="featured-grid"></div>
      </section>
      <footer class="home-footer">
        <div>
          <h2>Chez Ketchup</h2>
          <p>19 Cité Alia Diene, Yoff, Dakar</p>
        </div>
        <div class="footer-contact">
          <a href="tel:+221338566253">+221 33 856 62 53</a>
          <a href="tel:+221781469090">+221 78 146 90 90</a>
        </div>
        <div class="social-links social-icons" data-social-links></div>
      </footer>
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
        <form class="panel form" id="review-form">
          <h3>Laisser un avis</h3>
          <div class="form-grid">
            <label>Commande <input name="order_number" placeholder="CK-2026-0001" required></label>
            <label>Telephone <input name="phone" type="tel" required></label>
            <label>Note
              <select name="rating">
                <option value="5">5 / 5</option>
                <option value="4">4 / 5</option>
                <option value="3">3 / 5</option>
                <option value="2">2 / 5</option>
                <option value="1">1 / 5</option>
              </select>
            </label>
          </div>
          <label>Commentaire <textarea name="comment" rows="3"></textarea></label>
          <button class="primary" type="submit">Envoyer l'avis</button>
        </form>
      </section>
    </section>

    <section class="page" id="page-driver">
      <section class="section narrow">
        <h2>Espace livreur</h2>
        <form class="panel form" id="driver-login-form">
          <label>Telephone livreur
            <input type="tel" name="phone" placeholder="+221..." required>
          </label>
          <label>Mot de passe
            <input type="password" name="password" autocomplete="current-password" required>
          </label>
          <button class="primary" type="submit">Voir mes courses</button>
        </form>
        <div class="stack" id="driver-dashboard"></div>
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
            <p><strong>Réseaux sociaux</strong><span class="social-links social-icons" data-social-links></span></p>
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
      <label id="delivery-zone-field">Zone de livraison
        <select name="delivery_zone_id" id="delivery-zone"></select>
      </label>
      <label>Note spéciale <textarea name="note" rows="1" placeholder="Sans oignons, sauce à part..."></textarea></label>
      <label>Code promo <input name="promo_code" placeholder="KETCHUP10"></label>
      <label>Heure souhaitée
        <select name="schedule_mode" id="schedule-mode">
          <option value="asap">Dès que possible</option>
          <option value="later">Programmer</option>
        </select>
      </label>
      <label id="scheduled-at-field" class="hidden">Date et heure
        <input name="scheduled_at" id="scheduled-at" type="datetime-local">
        <span class="field-hint">Minimum 30 minutes, maximum 7 jours.</span>
      </label>
      <div class="two">
        <label>Mode
          <select name="fulfillment">
            <option value="delivery">Livraison</option>
            <option value="pickup">Retrait</option>
          </select>
        </label>
        <label>Paiement
          <select name="payment_method">
            <option value="cash">À la livraison / au retrait</option>
            <option value="orange_money" disabled>Orange Money — bientôt disponible</option>
            <option value="wave" disabled>Wave — bientôt disponible</option>
          </select>
          <span class="field-hint">Le paiement en ligne sera disponible prochainement. Vous pouvez payer à la livraison ou au retrait.</span>
        </label>
      </div>
      <div class="total small-total"><span>Sous-total</span><strong id="cart-subtotal">0 CFA</strong></div>
      <div class="total small-total"><span>Livraison</span><strong id="cart-delivery-fee">0 CFA</strong></div>
      <div class="total"><span>Total</span><strong id="cart-total">0 CFA</strong></div>
      <div class="checkout-notice" id="checkout-notice" role="status" aria-live="polite"></div>
      <button class="primary wide" type="submit">Commander maintenant</button>
    </form>
  </aside>
  <div class="scrim" id="scrim"></div>
  <script src="assets/js/app.js?v=20261011-1"></script>
  <script>
    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.register('sw.js').catch(() => {});
    }
  </script>
</body>
</html>
