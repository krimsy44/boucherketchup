# Chez Ketchup

Base de demarrage pour la plateforme web de commande et de gestion du restaurant Chez Ketchup.

## Lancer en local avec XAMPP

1. Placer le dossier dans `C:\xampp\htdocs\boucherketchup`.
2. Demarrer Apache depuis le panneau XAMPP.
3. Ouvrir `http://localhost/boucherketchup/`.

Au premier appel API, l'application cree automatiquement la base MySQL `chez_ketchup`, installe les tables et ajoute les donnees de depart. Les tokens de session admin restent stockes localement dans `storage/sessions.json`, ignore par Git.

Le dossier `database/` contient le schema SQL de reference.

## Comptes locaux

Les comptes initiaux utilisent le mot de passe `ADMIN_PASSWORD` defini dans `.env`.

- Super Admin: `admin@chezketchup.com`
- Gerant: `gerant@chezketchup.com`
- Caissier: `caisse@chezketchup.com`

## Modules poses

- Site vitrine responsive
- Menu par categories
- Panier et creation de commande
- Suivi par numero de commande ou telephone
- Back-office avec connexion, commandes, menu, clients et indicateurs
- Systeme de caisse sur place pour saisir une vente comptoir, encaisser et imprimer le ticket
- Gestion des livreurs internes et assignation aux commandes
- Ajout, modification, activation et indisponibilite des livreurs
- Tableau de bord livreur dedie par telephone + code avec courses assignees et mise a jour livraison / livre
- Annulation de commande avec motif obligatoire
- Ticket commande imprimable depuis le back-office
- Gestion admin du menu : ajout, modification, suppression, prix, categorie, image, disponibilite
- Upload JPG, PNG ou WebP des photos de plats depuis l'admin
- Paiements Orange Money et Wave avec reference et statut admin
- Statistiques admin et exports CSV : commandes, clients, paiements, menu
- Export PDF du rapport de synthese avec gabarit visuel
- Notifications WhatsApp/SMS : modeles commande, journal, lien WhatsApp manuel et envoi automatique configurable
- Notifications automatiques a la creation, au changement de statut et a la confirmation paiement
- Controle preproduction dans le back-office : environnement, fournisseur notifications, codes livreurs, mots de passe demo, dossiers ecriture
- Verrouillage production : refus automatique des mots de passe et codes livreurs de demonstration quand `APP_ENV=production`
- Sessions admin avec expiration configurable et deconnexion serveur
- Limitation des tentatives de connexion admin et livreur avec blocage temporaire
- Journal d'audit back-office : utilisateurs, commandes, paiements, livreurs et notifications de test
- Formulaire contact relie au back-office avec suivi traite / nouveau
- Liens reseaux sociaux publics configurables : Instagram, TikTok, Facebook et YouTube
- Liste clients complete avec adresse, nombre de commandes et total depense
- Gestion utilisateurs et rôles : Super Admin, Gérant, Caissier
- Detection des mots de passe demo et generateurs de mots de passe / codes livreurs dans le back-office
- API PHP sans dependance externe
- Persistance MySQL via PDO
- Installation automatique du schema au demarrage

## Prochaines etapes

- Renseigner les vrais identifiants fournisseur dans `.env`, puis utiliser le formulaire "Test fournisseur" du back-office.
- Remplacer les codes livreurs de demonstration avant mise en production.
- Passer `APP_ENV=production`, puis verifier le bloc "Controle preproduction".

## Acces livreur

L'espace livreur est disponible via `?page=driver`. Les livreurs se connectent avec leur telephone et leur code d'acces. Les livreurs de demonstration utilisent le code `1234`, configurable avec `DELIVERY_DEFAULT_CODE` avant la premiere migration.
En production, les codes livreurs egaux a `DELIVERY_DEFAULT_CODE` sont refuses automatiquement.

## Paiements

La version actuelle accepte Orange Money et Wave. Chaque commande recoit une reference interne et un statut (`pending`, `paid`, `failed`, `refunded`). Le back-office peut confirmer, echouer ou rembourser le paiement.

## Notifications

Le back-office prepare des messages WhatsApp/SMS a partir des commandes et conserve un journal (`queued`, `sent`, `failed`). Par defaut, `NOTIFICATION_PROVIDER=manual` garde l'envoi WhatsApp manuel via un lien `wa.me`.

Pour activer l'envoi automatique, configurer `.env` avec `NOTIFICATION_PROVIDER=webhook`, `twilio` ou `meta`. Les notifications nouvellement creees sont alors envoyees automatiquement et la table garde le fournisseur ainsi que la reference retournee.
Le back-office contient aussi un formulaire "Test fournisseur" dans l'onglet Notifications pour envoyer un message de validation sans creer de commande.

- `webhook` : renseigner `NOTIFICATION_WEBHOOK_URL`. L'application envoie un JSON contenant `channel`, `recipient`, `message` et `order_number`.
- `twilio` : renseigner `TWILIO_ACCOUNT_SID`, `TWILIO_AUTH_TOKEN`, puis `TWILIO_SMS_FROM` et/ou `TWILIO_WHATSAPP_FROM`.
- `meta` : renseigner `META_WHATSAPP_PHONE_NUMBER_ID`, `META_WHATSAPP_ACCESS_TOKEN` et, si besoin, `META_WHATSAPP_API_VERSION`.

## Roles back-office

- Super Admin : acces complet, gestion des utilisateurs.
- Gerant : commandes, menu, livreurs, paiements, statistiques et exports.
<<<<<<< HEAD
- Caissier : commandes du jour, suivi, notifications et tickets. Pas d'acces aux stats, exports, menu, paiements ni utilisateurs.
=======
- Caissier : caisse sur place, commandes du jour, suivi, notifications et tickets. Pas d'acces aux stats, exports, menu, paiements ni utilisateurs.
>>>>>>> 5f92b50 (mise a jour)

## Sessions admin

Les sessions back-office expirent apres `SESSION_TTL_HOURS` heures, 12 heures par defaut. Le bouton de deconnexion revoque aussi le token cote serveur.

## Protection connexions

Les connexions admin et livreur sont limitees par `AUTH_MAX_ATTEMPTS` echecs pendant `AUTH_LOCK_MINUTES` minutes. Par defaut, 5 echecs bloquent temporairement la combinaison identifiant + adresse IP.
