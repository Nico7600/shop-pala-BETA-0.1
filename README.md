# Shop

Shop est une application web complète permettant la gestion d'une boutique en ligne. Elle offre une expérience utilisateur moderne pour la consultation de produits, la gestion du panier, la commande et l'administration.

## Description

Ce projet vise à fournir une solution e-commerce simple et évolutive. Il permet aux utilisateurs de parcourir un catalogue de produits, d’ajouter des articles à leur panier, de passer commande et de suivre leurs achats. Les administrateurs peuvent gérer les produits, les stocks et les commandes via une interface dédiée.

## Fonctionnalités

- Affichage dynamique du catalogue de produits
- Recherche et filtrage des produits
- Ajout, modification et suppression d’articles dans le panier
- Validation et paiement des commandes
- Gestion des utilisateurs (clients et administrateurs)
- Tableau de bord d’administration (gestion des produits, commandes, utilisateurs)
- Historique des commandes pour chaque utilisateur

## Technologies utilisées

- **Frontend** : HTML5, CSS3, JavaScript, React.js, Tailwind CSS
- **Backend** : Node.js, Express, PHP (pour certaines API ou scripts serveur)
- **Base de données** : MongoDB, MySQL (optionnel selon configuration PHP)
- **Styles** : CSS, SCSS, Tailwind CSS
- **Templates** : HTML, JSX
- **Tests** : Jest, React Testing Library

## Installation

1. Clonez le dépôt :
   ```bash
   git clone <url-du-repo>
   ```
2. Installez les dépendances du frontend et du backend :
   ```bash
   cd shop
   npm install
   cd backend
   npm install
   ```
   Si vous utilisez PHP/MySQL :
   - Configurez votre serveur PHP (ex : XAMPP, WAMP)
   - Importez la base de données via le fichier `/backend/database.sql`
3. Configurez les variables d’environnement dans `.env` (exemple fourni dans `.env.example`).
4. Lancez le backend Node.js :
   ```bash
   npm run dev
   ```
   Ou lancez le serveur PHP :
   ```bash
   php -S localhost:8000 -t backend
   ```
5. Lancez le frontend :
   ```bash
   cd ../
   npm start
   ```

## Exemple d’utilisation

- Inscrivez-vous ou connectez-vous.
- Parcourez le catalogue, ajoutez des produits au panier.
- Accédez au panier, modifiez les quantités ou retirez des articles.
- Passez commande et effectuez le paiement.
- Consultez l’historique de vos commandes dans votre espace utilisateur.

## Structure du projet

- `/src` : Code source du frontend (React, HTML, CSS, JS, Tailwind)
  - `/components` : Composants réutilisables
  - `/pages` : Pages principales (Accueil, Catalogue, Panier, Profil, Admin)
  - `/redux` : Gestion d’état
  - `/services` : Appels API
  - `/styles` : Fichiers CSS/Tailwind
- `/backend` : Code source du backend (Express, PHP)
  - `/models` : Modèles de données (produits, utilisateurs, commandes)
  - `/routes` : Routes API (Node.js ou PHP)
  - `/controllers` : Logique métier
  - `/database.sql` : Structure MySQL (optionnel)
- `/public` : Fichiers statiques (HTML, images, favicon)
- `/docs` : Documentation technique et fonctionnelle

## Contribution

Les contributions sont encouragées ! Pour proposer une amélioration :
- Forkez le projet
- Créez une branche
- Soumettez une pull request

## Licence

Ce projet est sous licence MIT.
