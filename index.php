<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/configuration.php';
require_once __DIR__ . '/core/session_auth.php';
require_once __DIR__ . '/core/mongodb_logger.php';

start_secure_session();

MongoDBLogger::log_event('visits', [
    'page' => 'index_page',
    'user_id' => get_logged_in_user_info('user_id'),
    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN'
]);

if (is_logged_in()) {
    redirect_to(SITE_URL . 'tableau_de_bord.php');
}

$error_messages = get_and_clear_errors();
$success_message = get_and_clear_success_message();
?>
<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Page d'accueil - Covoiturage</title>
  <link rel="stylesheet" href="index.css"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.css">
</head>

<body>

  <header>
    <nav class="navbar">
      <div class="logo-container">
        <a href="<?= SITE_URL ?>index.php" class="logo">Ecoride</a>
      </div>

      <button class="burger">
        <div class="line"></div>
        <div class="line"></div>
        <div class="line"></div>
      </button>

      <form class="search-container" action="<?= SITE_URL ?>vue_trajets.php" method="get">
  <div class="form-group">
    <label for="ville_depart">Départ</label>
    <input type="text" id="ville_depart" name="ville_depart" placeholder="Ville de départ" required>
  </div>
  <div class="form-group">
    <label for="ville_arrivee">Arrivée</label>
    <input type="text" id="ville_arrivee" name="ville_arrivee" placeholder="Ville d'arrivée" required>
  </div>
  <div class="form-group">
    <label for="date_depart">Date</label>
    <input type="date" id="date_depart" name="date_depart" required>
  </div>
  <button type="submit" class="search-button">Rechercher</button>
</form>

      <ul class="nav-links">
        <li><a href="<?= SITE_URL ?>contact/contact.php">Contact</a></li>
        <li><a href="<?= SITE_URL ?>vue_authentification.php">Connexion</a></li>
        <li><a href="<?= SITE_URL ?>vue_authentification.php">Inscription</a></li>
        <li><a href="<?= SITE_URL ?>vue_trajets.php">Trajets</a></li>
      </ul>
    </nav>
  </header>

  <section class="hero">
    <img src="<?= SITE_URL ?>images/background_ecoride.png" alt="Image de fond" class="hero-image">
    <div class="hero-overlay">
      <div class="hero-content">
        <h1>N°1 des sites d'Écovoiturages</h1>
        <p>réservez facilement vos trajets en 3 clics !</p>
      </div>
    </div>
  </section>

  <section id="comment-section">
    <div class="testimonials-container">
      <h2>Ce que nos utilisateurs disent de nous</h2>
      <div class="testimonial-grid">
        <div class="testimonial-card">
          <p class="quote">"EcoRide a rendu mes trajets quotidiens tellement plus simples et économiques. Je suis ravie de contribuer à la protection de l'environnement en même temps !"</p>
          <p class="author">- Marie, Voyageuse</p>
        </div>
        <div class="testimonial-card">
          <p class="quote">"En tant que conducteur, c'est génial de partager mes frais et de rencontrer de nouvelles personnes. L'application est intuitive et la communauté est super."</p>
          <p class="author">- David, Conducteur</p>
        </div>
        <div class="testimonial-card">
          <p class="quote">"J'utilise EcoRide pour tous mes déplacements longue distance. C'est fiable, abordable et écologique. Je recommande vivement !"</p>
          <p class="author">- Sophie, Voyageuse</p>
        </div>
      </div>
    </div>
  </section>

<section class="presentation">
    <h1>À propos de nous</h1>
    <p>
      Chez Ecoride, nous offrons une solution de covoiturage vert et pas cher. Notre mission est de réduire l'empreinte carbone en partageant les trajets de nos usagers, tout en les responsabilisant sur les enjeux de demain. Rejoignez-nous pour un avenir plus vert et des déplacements plus conviviaux..
    </p>
    <div class="container">

      <div class="images-container">
        <div class="icon-block">
          <i class="fa-solid fa-lock fa-5x"></i>
          <h2>La sécurité et le bien-être de nos clients</h2>
          <p>Chez Ecoride, votre sécurité et confort sont notre priorité. Nos chauffeurs, rigoureusement sélectionnés,
            assurent un service de qualité avec des véhicules modernes et bien entretenus. Des protocoles stricts
            garantissent une expérience sereine et agréable.</p>
        </div>
    
        <div class="icon-block">
          <i class="fa-solid fa-bolt fa-5x"></i>
          <h2>Rapidité et efficacité de nos services</h2>
          <p>Nous valorisons votre temps. Nos processus optimisés et notre technologie avancée vous offrent des
            trajets rapides et ponctuels. Avec Ecoride, voyagez rapidement et en toute fluidité.</p>
        </div>
    
        <div class="icon-block">
          <i class="fa-solid fa-user fa-5x"></i>
          <h2>L'écoute de nos clients</h2>
          <p>Votre satisfaction est notre succès. Nous écoutons attentivement vos retours pour améliorer nos services.
            Notre équipe est à votre disposition pour répondre à vos besoins et garantir une expérience agréable à
            chaque trajet.</p>
        </div>
      </div>
    </div>
</section>  
  <footer>
    <p class="pf">Contactez-nous:<a href="mailto:Ecoride-france@gmail.com"> Ecoride-france@gmail.com</a></p>
    <p><a href="#">Mentions légales</a></p>
    <p>©2025 Ecoride.com</p>
  </footer>

  <script src="app.js"></script>
</body>

</html>