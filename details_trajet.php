<?php
require_once __DIR__ . '/configuration.php';
require_once __DIR__ . '/core/session_auth.php';

start_secure_session();

if (!is_logged_in() || !verify_role_access(['traveler'])) {
    add_error("Accès non autorisé. Veuillez vous connecter en tant que voyageur.");
    redirect_to(SITE_URL . 'vue_authentification.php');
}

$userId = get_logged_in_user_info('user_id');
$username = get_logged_in_user_info('username');
$userCredits = get_user_credits($pdo, $userId);

$trip = null;
$tripId = $_GET['id'] ?? 0;
//tableau de conditon si un voyage est disponible
if ($tripId > 0) {
    try {
        //recupère les informations
        $stmt = $pdo->prepare("
            SELECT t.*, u.username AS driver_username, u.profile_picture AS driver_profile_picture,
                   v.make AS vehicle_make, v.model AS vehicle_model, v.energy AS vehicle_energy, v.license_plate
            FROM trips t
            JOIN users u ON t.driver_id = u.id
            JOIN vehicles v ON t.vehicle_id = v.id
            WHERE t.id = ? AND t.departure_datetime >= NOW()
        ");
        $stmt->execute([$tripId]);
        $trip = $stmt->fetch();

        if (!$trip) {
            add_error("Trajet non trouvé ou déjà passé.");
            redirect_to(SITE_URL . 'vue_trajets.php');
        }

        if ($trip['driver_id'] === $userId) {
            add_error("Vous ne pouvez pas réserver votre propre trajet.");
            redirect_to(SITE_URL . 'vue_trajets.php');
        }

    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des détails du trajet : " . $e->getMessage());
        add_error("Une erreur est survenue lors du chargement des détails du trajet.");
        redirect_to(SITE_URL . 'vue_trajets.php');
    }
} else {
    add_error("ID de trajet non spécifié.");
    redirect_to(SITE_URL . 'vue_trajets.php');
}

$errorMessages = get_and_clear_errors();
$successMessage = get_and_clear_success_message();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'book_trip') {
    $numSeats = (int)($_POST['number_of_seats'] ?? 0);
    $csrfToken = $_POST['csrf_token'] ?? null;

    if (!verify_csrf_token($csrfToken, 'book_trip_form_' . $tripId)) {
        add_error("Erreur de sécurité : Jeton CSRF invalide ou expiré.");
    } elseif ($numSeats <= 0) {
        add_error("Le nombre de places doit être supérieur à zéro.");
    } elseif ($numSeats > $trip['available_seats']) {
        add_error("Pas assez de places disponibles pour votre réservation.");
    } else {
        $totalCost = $numSeats * $trip['price'];
        if ($userCredits < $totalCost) {
            add_error("Crédits insuffisants pour cette réservation. Coût total : " . number_format($totalCost, 2) . " crédits.");
        } else {
            try {
                $pdo->beginTransaction();

                $stmtDeduct = $pdo->prepare("UPDATE users SET credits = credits - ? WHERE id = ?");
                $stmtDeduct->execute([$totalCost, $userId]);

                $stmtBook = $pdo->prepare("INSERT INTO bookings (trip_id, traveler_id, number_of_seats, status) VALUES (?, ?, ?, 'pending_validation')");
                $stmtBook->execute([$tripId, $userId, $numSeats]);

                $stmtUpdateSeats = $pdo->prepare("UPDATE trips SET available_seats = available_seats - ? WHERE id = ?");
                $stmtUpdateSeats->execute([$numSeats, $tripId]);

                $pdo->commit();
                add_success_message("Votre réservation pour {$numSeats} place(s) a été envoyée. En attente de validation par le conducteur.");
                $userCredits = get_user_credits($pdo, $userId);
                $stmt = $pdo->prepare("
                    SELECT t.*, u.username AS driver_username, u.profile_picture AS driver_profile_picture,
                           v.make AS vehicle_make, v.model AS vehicle_model, v.energy AS vehicle_energy, v.license_plate
                    FROM trips t
                    JOIN users u ON t.driver_id = u.id
                    JOIN vehicles v ON t.vehicle_id = v.id
                    WHERE t.id = ?
                ");
                $stmt->execute([$tripId]);
                $trip = $stmt->fetch();

            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("Erreur lors de la réservation du trajet : " . $e->getMessage());
                add_error("Une erreur est survenue lors de la réservation. Vos crédits n'ont pas été débités. Veuillez réessayer.");
            }
        }
    }
    $errorMessages = get_and_clear_errors();
    $successMessage = get_and_clear_success_message();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails du Trajet - EcoRide</title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <div class="logo-container">
        <a href="<?= SITE_URL ?>index.php" class="logo">Ecoride</a>
        </div>
        <nav class="navbar">
            <ul class="nav-links">
                <li><a href="<?= SITE_URL ?>vue_trajets.php">Covoiturages</a></li>
                <?php if (is_logged_in()): ?>
                    <li><a href="<?= SITE_URL ?>tableau_de_bord.php">Tableau de bord</a></li>
                    <li><a href="<?= SITE_URL ?>deconnexion.php">Déconnexion</a></li>
                    <li class="credits">Crédits: <?= number_format($userCredits, 2) ?></li>
                <?php else: ?>
                    <li><a href="<?= SITE_URL ?>vue_authentification.php">Connexion / Inscription</a></li>
                <?php endif; ?>
                <li><a href="#">Contact</a></li>
            </ul>
            <button class="burger">
                <div class="line"></div>
                <div class="line"></div>
                <div class="line"></div>
            </button>
        </nav>
    </header>

    <div class="container">
        <?php if (!empty($errorMessages)): ?>
            <div class="message error-message-container">
                <?php foreach ($errorMessages as $msg): ?>
                    <p><?= sanitize_html_output($msg) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($successMessage)): ?>
            <div class="message success-message-container">
                <p><?= sanitize_html_output($successMessage) ?></p>
            </div>
        <?php endif; ?>

        <?php if ($trip): ?>
            <h1>Détails du Trajet</h1>
            <div class="trip-details">
                <div class="detail-item"><strong>Départ:</strong> <?= sanitize_html_output($trip['departure_city']) ?></div>
                <div class="detail-item"><strong>Arrivée:</strong> <?= sanitize_html_output($trip['arrival_city']) ?></div>
                <div class="detail-item"><strong>Date & Heure:</strong> <?= date('d/m/Y H:i', strtotime($trip['departure_datetime'])) ?></div>
                <div class="detail-item"><strong>Prix par place:</strong> <?= number_format($trip['price'], 2) ?> crédits</div>
                <div class="detail-item"><strong>Places disponibles:</strong> <?= $trip['available_seats'] ?></div>
                <div class="detail-item"><strong>Description:</strong> <?= nl2br(sanitize_html_output($trip['description'])) ?></div>

                <div class="trip-driver-info">
                    <img src="<?= sanitize_html_output($trip['driver_profile_picture']) ?>" alt="Photo de profil du conducteur">
                    <div>
                        <p><strong>Conducteur:</strong> <?= sanitize_html_output($trip['driver_username']) ?></p>
                        <p><strong>Véhicule:</strong> <?= sanitize_html_output($trip['vehicle_make'] . ' ' . $trip['vehicle_model']) ?> (Plaque: <?= sanitize_html_output($trip['license_plate']) ?>)</p>
                        <p><strong>Énergie:</strong> <?= sanitize_html_output(ucfirst($trip['vehicle_energy'])) ?> (<?= $trip['vehicle_energy'] === 'electric' ? 'Véhicule Écologique' : 'Non Écologique' ?>)</p>
                    </div>
                </div>
            </div>

            <div class="booking-section">
                <h3>Réserver une place</h3>
                <form action="<?= SITE_URL ?>details_trajet.php?id=<?= $tripId ?>" method="post" class="booking-form">
                    <input type="hidden" name="action" value="book_trip">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token('book_trip_form_' . $tripId) ?>">
                    <div class="input-group">
                        <label for="number_of_seats">Nombre de places :</label>
                        <input type="number" id="number_of_seats" name="number_of_seats" min="1" max="<?= $trip['available_seats'] ?>" value="1" required
                               <?= ($trip['available_seats'] <= 0 || $userCredits < $trip['price']) ? 'disabled' : '' ?>>
                    </div>
                    <p>Coût estimé : <span id="estimated-cost"><?= number_format($trip['price'], 2) ?></span> crédits</p>
                    <button type="submit" <?= ($trip['available_seats'] <= 0 || $userCredits < $trip['price']) ? 'disabled' : '' ?>>
                        Réserver maintenant
                    </button>
                    <?php if ($trip['available_seats'] <= 0): ?>
                        <p class="error-message">Plus de places disponibles pour ce trajet.</p>
                    <?php elseif ($userCredits < $trip['price']): ?>
                        <p class="error-message">Crédits insuffisants pour au moins une place (vous avez <?= number_format($userCredits, 2) ?> crédits).</p>
                    <?php endif; ?>
                </form>
            </div>
        <?php endif; ?>
        <p><a href="<?= SITE_URL ?>vue_trajets.php">Retour à la liste des trajets</a></p>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const placesInput = document.getElementById('number_of_seats');
            const estimatedCostSpan = document.getElementById('estimated-cost');
            const pricePerPlace = parseFloat("<?= ($trip['price'] ?? 0) ?>");

            if (placesInput && estimatedCostSpan) {
                placesInput.addEventListener('input', function() {
                    const numPlaces = parseInt(this.value);
                    const totalCost = (isNaN(numPlaces) || numPlaces < 1) ? 0 : numPlaces * pricePerPlace;
                    estimatedCostSpan.textContent = totalCost.toFixed(2);
                });
            }
        });
    </script>
    <script src="app.js"></script>
</body>
</html>
