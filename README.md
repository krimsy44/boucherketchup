# Chez Ketchup

Base de demarrage pour la plateforme web de commande et de gestion du restaurant Chez Ketchup.

## Lancer en local avec XAMPP

1. Placer le dossier dans `C:\xampp\htdocs\boucherketchup`.
2. Demarrer Apache depuis le panneau XAMPP.
3. Ouvrir `http://localhost/boucherketchup/`.

Au premier appel API, l'application cree automatiquement la base MySQL `chez_ketchup`, installe les tables et ajoute les donnees de depart. Les tokens de session admin restent stockes localement dans `storage/sessions.json`, ignore par Git.

Le dossier `database/` contient le schema SQL de reference.

## Comptes de demonstration

- Super Admin: `admin@chezketchup.com` / `ChangeMe123!`
- Gerant: `gerant@chezketchup.com` / `ChangeMe123!`
- Caissier: `caisse@chezketchup.com` / `ChangeMe123!`

## Modules poses

- Site vitrine responsive
- Menu par categories
- Panier et creation de commande
- Suivi par numero de commande ou telephone
- Back-office avec connexion, commandes, menu, clients et indicateurs
- Gestion des livreurs internes et assignation aux commandes
- Ajout, modification, activation et indisponibilite des livreurs
- Annulation de commande avec motif obligatoire
- Ticket commande imprimable depuis le back-office
- Gestion admin du menu : ajout, modification, suppression, prix, categorie, image, disponibilite
- Upload JPG, PNG ou WebP des photos de plats depuis l'admin
- Paiements Orange Money et Wave avec reference et statut admin
- Statistiques admin et exports CSV : commandes, clients, paiements, menu
- Export PDF du rapport de synthese
- Notifications preparatoires WhatsApp/SMS : modeles commande, journal, lien WhatsApp manuel
- Notifications automatiques preparees a la creation, au changement de statut et a la confirmation paiement
- Formulaire contact relie au back-office avec suivi traite / nouveau
- Liste clients complete avec adresse, nombre de commandes et total depense
- Gestion utilisateurs et rôles : Super Admin, Gérant, Caissier
- API PHP sans dependance externe
- Persistance MySQL via PDO
- Installation automatique du schema au demarrage

## Prochaines etapes

- Brancher l'envoi automatique WhatsApp/SMS via Twilio, Meta WhatsApp Cloud API ou un agregateur local.
- Ameliorer le PDF avec un gabarit visuel complet si une bibliotheque PDF est ajoutee.

## Paiements

La version actuelle accepte Orange Money et Wave. Chaque commande recoit une reference interne et un statut (`pending`, `paid`, `failed`, `refunded`). Le back-office peut confirmer, echouer ou rembourser le paiement.

## Notifications

Le back-office prepare des messages WhatsApp/SMS a partir des commandes et conserve un journal (`queued`, `sent`, `failed`). L'envoi WhatsApp est manuel via un lien `wa.me` pour la phase de test. La table `notifications` est prete pour brancher Twilio, Meta WhatsApp Cloud API ou un agregateur local.

## Roles back-office

- Super Admin : acces complet, gestion des utilisateurs.
- Gerant : commandes, menu, livreurs, paiements, statistiques et exports.
- Caissier : commandes du jour, suivi, notifications et tickets. Pas d'acces aux stats, exports, menu, paiements ni utilisateurs.
