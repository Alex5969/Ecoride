<?php
require_once __DIR__ . '/configuration.php';
require_once __DIR__ . '/core/session_auth.php';

start_secure_session();

if (!is_logged_in() || !verify_role_access(['driver'])) {
    add_error("Accès non autorisé. Veuillez vous connecter en tant que conducteur.");
    redirect_to(SITE_URL . 'vue_authentification.php');
}

$userId = get_logged_in_user_info('user_id');
$username = get_logged_in_user_info('username');
$credits = get_user_credits($pdo, $userId);

$errorMessages = get_and_clear_errors();
$successMessage = get_and_clear_success_message();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $csrfToken = $_POST['csrf_token'] ?? null;

//fomulaire creation de covoiturage
    if ($action === 'add_trip') {
        if (!verify_csrf_token($csrfToken, 'add_trip_form')) {
            add_error("Erreur de sécurité : Jeton CSRF invalide ou expiré.");
        } else {
            $departureCity = sanitize_html_output(trim($_POST['departure_city'] ?? ''));
            $arrivalCity = sanitize_html_output(trim($_POST['arrival_city'] ?? ''));
            $departureDatetime = trim($_POST['departure_datetime'] ?? '');
            $availableSeats = (int)($_POST['available_seats'] ?? 0);
            $price = (float)($_POST['price'] ?? 0.0);
            $description = sanitize_html_output(trim($_POST['description'] ?? ''));
            $vehicleId = (int)($_POST['vehicle_id'] ?? 0);

            if (empty($departureCity) || empty($arrivalCity) || empty($departureDatetime) || $availableSeats <= 0 || $price <= 0 || $vehicleId <= 0) {
                add_error("Tous les champs obligatoires doivent être remplis et valides.");
            } elseif (strtotime($departureDatetime) < time()) {
                add_error("La date et l'heure de départ ne peuvent pas être dans le passé.");
            } else {
                try {
                    $stmtCheckVehicle = $pdo->prepare("SELECT COUNT(*) FROM vehicles WHERE id = ? AND user_id = ?");
                    $stmtCheckVehicle->execute([$vehicleId, $userId]);
                    if ($stmtCheckVehicle->fetchColumn() === 0) {
                        add_error("Le véhicule sélectionné n'est pas valide ou ne vous appartient pas.");
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO trips (driver_id, vehicle_id, departure_city, arrival_city, departure_datetime, available_seats, price, description, trip_status, platform_fee) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'scheduled', 0.00)");
                        $stmt->execute([$userId, $vehicleId, $departureCity, $arrivalCity, $departureDatetime, $availableSeats, $price, $description]);
                        add_success_message("Votre trajet a été ajouté avec succès !");
                    }
                } catch (PDOException $e) {
                    add_error("Erreur lors de l'ajout du trajet : " . $e->getMessage());
                    error_log("Add Trip PDO Error: " . $e->getMessage());
                }
            }
        }
//formulaire ajout de vehicule
    } elseif ($action === 'add_vehicle') {
        if (!verify_csrf_token($csrfToken, 'add_vehicle_form')) {
            add_error("Erreur de sécurité : Jeton CSRF invalide ou expiré.");
        } else {
            $make = sanitize_html_output(trim($_POST['make'] ?? ''));
            $model = sanitize_html_output(trim($_POST['model'] ?? ''));
            $licensePlate = sanitize_html_output(trim($_POST['license_plate'] ?? ''));
            $registrationDate = trim($_POST['registration_date'] ?? '');
            $color = sanitize_html_output(trim($_POST['color'] ?? ''));
            $energy = sanitize_html_output(trim($_POST['energy'] ?? ''));
            $seatsAvailable = (int)($_POST['vehicle_seats_available'] ?? 0);

            if (empty($make) || empty($model) || empty($licensePlate) || empty($registrationDate) || empty($energy) || $seatsAvailable <= 0) {
                add_error("Tous les champs obligatoires du véhicule doivent être remplis et valides.");
            } else {
                try {
                    $stmt = $pdo->prepare("INSERT INTO vehicles (user_id, make, model, license_plate, registration_date, color, energy, seats_available) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$userId, $make, $model, $licensePlate, $registrationDate, $color, $energy, $seatsAvailable]);
                    add_success_message("Votre véhicule a été ajouté avec succès !");
                } catch (PDOException $e) {
                    add_error("Erreur lors de l'ajout du véhicule : " . $e->getMessage());
                    error_log("Add Vehicle PDO Error: " . $e->getMessage());
                }
            }
        }
//fonciton préférences de voyages
    } elseif ($action === 'update_preferences') {
        if (!verify_csrf_token($csrfToken, 'preferences_form')) {
            add_error("Erreur de sécurité : Jeton CSRF invalide ou expiré.");
        } else {
            $smokingAllowed = isset($_POST['smoking_allowed']) ? 1 : 0;
            $animalsAllowed = isset($_POST['animals_allowed']) ? 1 : 0;
            $customPreference = sanitize_html_output(trim($_POST['custom_preference'] ?? ''));

            try {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM driver_preferences WHERE user_id = ?");
                $stmt->execute([$userId]);
                if ($stmt->fetchColumn() > 0) {
                    $stmt = $pdo->prepare("UPDATE driver_preferences SET smoking_allowed = ?, animals_allowed = ?, custom_preference = ? WHERE user_id = ?");
                    $stmt->execute([$smokingAllowed, $animalsAllowed, $customPreference, $userId]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO driver_preferences (user_id, smoking_allowed, animals_allowed, custom_preference) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$userId, $smokingAllowed, $animalsAllowed, $customPreference]);
                }
                add_success_message("Vos préférences ont été mises à jour !");
            } catch (PDOException $e) {
                add_error("Erreur lors de la mise à jour des préférences : " . $e->getMessage());
                error_log("Update Preferences PDO Error: " . $e->getMessage());
            }
        }
// statut d'un trajet et gère les logiques de remboursements, notifications
    } elseif ($action === 'start_trip' || $action === 'complete_trip' || $action === 'cancel_trip') {
        $tripIdAction = (int)($_POST['trip_id'] ?? 0);
        if (!verify_csrf_token($csrfToken, 'trip_action_form_' . $tripIdAction)) {
            add_error("Erreur de sécurité : Jeton CSRF invalide ou expiré.");
        } else {
            try {
                $currentTripStatus = null;
                $stmtStatus = $pdo->prepare("SELECT trip_status FROM trips WHERE id = ? AND driver_id = ?");
                $stmtStatus->execute([$tripIdAction, $userId]);
                $statusResult = $stmtStatus->fetchColumn();
                if ($statusResult) {
                    $currentTripStatus = $statusResult;
                } else {
                    throw new Exception("Trajet introuvable ou vous n'êtes pas le conducteur.");
                }

                $pdo->beginTransaction();

                if ($action === 'start_trip' && $currentTripStatus === 'scheduled') {
                    $stmt = $pdo->prepare("UPDATE trips SET trip_status = 'started' WHERE id = ? AND driver_id = ?");
                    $stmt->execute([$tripIdAction, $userId]);
                    add_success_message("Trajet démarré !");
                } elseif ($action === 'complete_trip' && $currentTripStatus === 'started') {
                    $stmt = $pdo->prepare("UPDATE trips SET trip_status = 'completed' WHERE id = ? AND driver_id = ?");
                    $stmt->execute([$tripIdAction, $userId]);

                    $stmtTravelers = $pdo->prepare("SELECT traveler_id, id AS booking_id FROM bookings WHERE trip_id = ? AND status = 'confirmed'");
                    $stmtTravelers->execute([$tripIdAction]);
                    $travelerBookings = $stmtTravelers->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($travelerBookings as $booking) {
                        $stmtUpdateBookingStatus = $pdo->prepare("UPDATE bookings SET status = 'pending_validation' WHERE id = ?");
                        $stmtUpdateBookingStatus->execute([$booking['booking_id']]);
                        error_log("Notification au voyageur " . $booking['traveler_id'] . " pour le trajet " . $tripIdAction . " : Veuillez valider votre trajet.");
                    }

                    add_success_message("Trajet terminé ! Les voyageurs seront invités à valider et noter.");
                } elseif ($action === 'cancel_trip' && ($currentTripStatus === 'scheduled' || $currentTripStatus === 'started')) {
                    $stmtBookings = $pdo->prepare("SELECT traveler_id, number_of_seats, price, id FROM bookings WHERE trip_id = ? AND (status = 'confirmed' OR status = 'pending_validation')");
                    $stmtBookings->execute([$tripIdAction]);
                    $affectedBookings = $stmtBookings->fetchAll();

                    foreach ($affectedBookings as $booking) {
                        $refundAmount = $booking['number_of_seats'] * $booking['price'];
                        $stmtRefund = $pdo->prepare("UPDATE users SET credits = credits + ? WHERE id = ?");
                        $stmtRefund->execute([$refundAmount, $booking['traveler_id']]);
                        
                        $stmtUpdateBooking = $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?");
                        $stmtUpdateBooking->execute([$booking['id']]);
                        
                        error_log("Remboursement de " . $refundAmount . " crédits au voyageur " . $booking['traveler_id'] . " pour l'annulation du trajet " . $tripIdAction);
                    }

                    $stmt = $pdo->prepare("UPDATE trips SET trip_status = 'cancelled' WHERE id = ? AND driver_id = ?");
                    $stmt->execute([$tripIdAction, $userId]);

                    add_success_message("Trajet annulé. Les voyageurs ont été remboursés.");
                } else {
                    throw new Exception("Action invalide ou statut de trajet incorrect pour cette opération.");
                }
                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
                add_error("Erreur lors du traitement de l'action du trajet : " . $e->getMessage());
                error_log("Trip Action Error: " . $e->getMessage());
            } catch (PDOException $e) {
                $pdo->rollBack();
                add_error("Une erreur de base de données est survenue lors de la traitement de l'action du trajet.");
                error_log("Trip Action PDO Error: " . $e->getMessage());
            }
        }
    }
    redirect_to(SITE_URL . 'tableau_de_bord_conducteur.php#my-trips');
}

//Charge les véhicules enregistrés par l'utilisateur
$myVehicles = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM vehicles WHERE user_id = ?");
    $stmt->execute([$userId]);
    $myVehicles = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error retrieving driver's vehicles: " . $e->getMessage());
    add_error("Erreur lors du chargement de vos véhicules.");
}

//Charge les préférences du conducteur dans les formulaires
$driverPreferences = [
    'smoking_allowed' => false,
    'animals_allowed' => false,
    'custom_preference' => ''
];
try {
    $stmt = $pdo->prepare("SELECT smoking_allowed, animals_allowed, custom_preference FROM driver_preferences WHERE user_id = ?");
    $stmt->execute([$userId]);
    $prefs = $stmt->fetch();
    if ($prefs) {
        $driverPreferences = $prefs;
    }
} catch (PDOException $e) {
    error_log("Error retrieving driver preferences: " . $e->getMessage());
}

// Charge tous les trajets créés par le conducteur
$myTrips = [];
try {
    $stmt = $pdo->prepare("SELECT t.*, v.make, v.model, v.energy FROM trips t LEFT JOIN vehicles v ON t.vehicle_id = v.id WHERE t.driver_id = ? ORDER BY t.departure_datetime DESC");
    $stmt->execute([$userId]);
    $myTrips = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error retrieving driver's trips: " . $e->getMessage());
    add_error("Erreur lors du chargement de vos trajets.");
}

$csrfTokenAddTrip = generate_csrf_token('add_trip_form');
$csrfTokenAddVehicle = generate_csrf_token('add_vehicle_form');
$csrfTokenPreferences = generate_csrf_token('preferences_form');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord Conducteur - EcoRide</title>
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
                    <li class="credits">Crédits: <?= number_format($credits, 2) ?></li>
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
        <h1>Tableau de Bord Conducteur</h1>

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

        <div class="tabs">
            <button class="tab-button active" data-tab="add-trip">Proposer un Trajet</button>
            <button class="tab-button" data-tab="manage-vehicles">Mes Véhicules</button>
            <button class="tab-button" data-tab="preferences">Mes Préférences</button>
            <button class="tab-button" data-tab="my-trips">Mes Trajets</button>
        </div>

        <div id="add-trip" class="tab-content active">
            <div class="section">
                <h2>Proposer un nouveau trajet</h2>
                <form id="add-trip-form" action="<?= SITE_URL ?>tableau_de_bord_conducteur.php" method="post">
                    <input type="hidden" name="action" value="add_trip">
                    <input type="hidden" name="csrf_token" value="<?= sanitize_html_output($csrfTokenAddTrip) ?>">
                    <div class="input-group">
                        <label for="departure_city">Ville de départ</label>
                        <input type="text" id="departure_city" name="departure_city" required>
                    </div>
                    <div class="input-group">
                        <label for="arrival_city">Ville d'arrivée</label>
                        <input type="text" id="arrival_city" name="arrival_city" required>
                    </div>
                    <div class="input-group">
                        <label for="departure_datetime">Date et heure de départ</label>
                        <input type="datetime-local" id="departure_datetime" name="departure_datetime" required>
                    </div>
                    <div class="input-group">
                        <label for="available_seats">Nombre de places disponibles</label>
                        <input type="number" id="available_seats" name="available_seats" min="1" required>
                    </div>
                    <div class="input-group">
                        <label for="price">Prix par place (crédits)</label>
                        <input type="number" id="price" name="price" step="0.01" min="0.01" required>
                    </div>
                    <div class="input-group">
                        <label for="vehicle_id">Véhicule à utiliser</label>
                        <select id="vehicle_id" name="vehicle_id" required>
                            <option value="">Sélectionnez un véhicule</option>
                            <?php foreach ($myVehicles as $vehicle): ?>
                                <option value="<?= $vehicle['id'] ?>"><?= sanitize_html_output($vehicle['make'] . ' ' . $vehicle['model'] . ' (' . $vehicle['license_plate'] . ')') ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($myVehicles)): ?>
                            <p class="error-message">Vous devez ajouter un véhicule avant de proposer un trajet. <a href="#manage-vehicles" class="switch-tab-link" data-target-tab="manage-vehicles">Gérer mes véhicules</a></p>
                        <?php endif; ?>
                    </div>
                    <div class="input-group">
                        <label for="description">Description (optionnel)</label>
                        <textarea id="description" name="description"></textarea>
                    </div>
                    <button type="submit" <?= empty($myVehicles) ? 'disabled' : '' ?>>Ajouter le trajet</button>
                </form>
            </div>
        </div>

        <div id="manage-vehicles" class="tab-content">
            <div class="section">
                <h2>Ajouter un nouveau véhicule</h2>
                <form id="add-vehicle-form" action="<?= SITE_URL ?>tableau_de_bord_conducteur.php" method="post">
                    <input type="hidden" name="action" value="add_vehicle">
                    <input type="hidden" name="csrf_token" value="<?= sanitize_html_output($csrfTokenAddVehicle) ?>">
                    <div class="input-group">
                        <label for="make">Marque</label>
                        <input type="text" id="make" name="make" required>
                    </div>
                    <div class="input-group">
                        <label for="model">Modèle</label>
                        <input type="text" id="model" name="model" required>
                    </div>
                    <div class="input-group">
                        <label for="license_plate">Plaque d'immatriculation</label>
                        <input type="text" id="license_plate" name="license_plate" required>
                    </div>
                    <div class="input-group">
                        <label for="registration_date">Date de première immatriculation</label>
                        <input type="date" id="registration_date" name="registration_date" required>
                    </div>
                    <div class="input-group">
                        <label for="color">Couleur</label>
                        <input type="text" id="color" name="color">
                    </div>
                    <div class="input-group">
                        <label for="energy">Type d'énergie</label>
                        <select id="energy" name="energy" required>
                            <option value="">Sélectionnez...</option>
                            <option value="petrol">Essence</option>
                            <option value="diesel">Diesel</option>
                            <option value="electric">Électrique</option>
                            <option value="hybrid">Hybride</option>
                        </select>
                    </div>
                    <div class="input-group">
                        <label for="vehicle_seats_available">Nombre de places (capacité du véhicule)</label>
                        <input type="number" id="vehicle_seats_available" name="vehicle_seats_available" min="1" required>
                    </div>
                    <button type="submit">Ajouter le véhicule</button>
                </form>
            </div>

            <div class="section my-vehicles">
                <h2>Mes véhicules</h2>
                <?php if (empty($myVehicles)): ?>
                    <p>Vous n'avez pas encore ajouté de véhicules.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Marque</th>
                                <th>Modèle</th>
                                <th>Plaque</th>
                                <th>Énergie</th>
                                <th>Places</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($myVehicles as $vehicle): ?>
                                <tr>
                                    <td><?= sanitize_html_output($vehicle['make']) ?></td>
                                    <td><?= sanitize_html_output($vehicle['model']) ?></td>
                                    <td><?= sanitize_html_output($vehicle['license_plate']) ?></td>
                                    <td><?= sanitize_html_output(ucfirst($vehicle['energy'])) ?></td>
                                    <td><?= $vehicle['seats_available'] ?></td>
                                    <td>
                                        <a href="#" class="button view-button disabled">Modifier</a>
                                        <a href="#" class="button cancel-button disabled">Supprimer</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <div id="preferences" class="tab-content">
            <div class="section">
                <h2>Mes préférences de conducteur</h2>
                <form id="preferences-form" action="<?= SITE_URL ?>tableau_de_bord_conducteur.php" method="post">
                    <input type="hidden" name="action" value="update_preferences">
                    <input type="hidden" name="csrf_token" value="<?= sanitize_html_output($csrfTokenPreferences) ?>">
                    <div class="input-group checkbox-group">
                        <input type="checkbox" id="smoking_allowed" name="smoking_allowed" <?= $driverPreferences['smoking_allowed'] ? 'checked' : '' ?>>
                        <label for="smoking_allowed">Fumeur autorisé</label>
                    </div>
                    <div class="input-group checkbox-group">
                        <input type="checkbox" id="animals_allowed" name="animals_allowed" <?= $driverPreferences['animals_allowed'] ? 'checked' : '' ?>>
                        <label for="animals_allowed">Animaux autorisés</label>
                    </div>
                    <div class="input-group">
                        <label for="custom_preference">Autre préférence (ex: Musique calme, Silence...)</label>
                        <textarea id="custom_preference" name="custom_preference"><?= sanitize_html_output($driverPreferences['custom_preference']) ?></textarea>
                    </div>
                    <button type="submit">Mettre à jour les préférences</button>
                </form>
            </div>
        </div>

        <div id="my-trips" class="tab-content">
            <div class="section my-trips-list">
                <h2>Mes trajets proposés</h2>
                <?php if (empty($myTrips)): ?>
                    <p>Vous n'avez pas encore proposé de trajets.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Départ</th>
                                <th>Arrivée</th>
                                <th>Date</th>
                                <th>Places</th>
                                <th>Prix</th>
                                <th>Véhicule</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($myTrips as $trip): ?>
                                <tr>
                                    <td><?= sanitize_html_output($trip['departure_city']) ?></td>
                                    <td><?= sanitize_html_output($trip['arrival_city']) ?></td>
                                    <td><?= date('d/m/Y H:i', strtotime($trip['departure_datetime'])) ?></td>
                                    <td><?= $trip['available_seats'] ?></td>
                                    <td><?= number_format($trip['price'], 2) ?> crédits</td>
                                    <td><?= sanitize_html_output($trip['make'] . ' ' . $trip['model']) ?></td>
                                    <td><?= sanitize_html_output(ucfirst($trip['trip_status'])) ?></td>
                                    <td>
                                        <?php if ($trip['trip_status'] === 'scheduled' && strtotime($trip['departure_datetime']) <= time()): ?>
                                            <form action="<?= SITE_URL ?>tableau_de_bord_conducteur.php" method="post" style="display:inline;">
                                                <input type="hidden" name="action" value="start_trip">
                                                <input type="hidden" name="trip_id" value="<?= $trip['id'] ?>">
                                                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token('trip_action_form_' . $trip['id']) ?>">
                                                <button type="submit" class="button start-button">Démarrer</button>
                                            </form>
                                            <form action="<?= SITE_URL ?>tableau_de_bord_conducteur.php" method="post" style="display:inline;">
                                                <input type="hidden" name="action" value="cancel_trip">
                                                <input type="hidden" name="trip_id" value="<?= $trip['id'] ?>">
                                                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token('trip_action_form_' . $trip['id']) ?>">
                                                <button type="submit" class="button cancel-button" onclick="return confirm('Êtes-vous sûr de vouloir annuler ce trajet ? Tous les passagers seront remboursés.');">Annuler</button>
                                            </form>
                                        <?php elseif ($trip['trip_status'] === 'started'): ?>
                                            <form action="<?= SITE_URL ?>tableau_de_bord_conducteur.php" method="post" style="display:inline;">
                                                <input type="hidden" name="action" value="complete_trip">
                                                <input type="hidden" name="trip_id" value="<?= $trip['id'] ?>">
                                                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token('trip_action_form_' . $trip['id']) ?>">
                                                <button type="submit" class="button complete-button">Arrivé à destination</button>
                                            </form>
                                            <form action="<?= SITE_URL ?>tableau_de_bord_conducteur.php" method="post" style="display:inline;">
                                                <input type="hidden" name="action" value="cancel_trip">
                                                <input type="hidden" name="trip_id" value="<?= $trip['id'] ?>">
                                                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token('trip_action_form_' . $trip['id']) ?>">
                                                <button type="submit" class="button cancel-button" onclick="return confirm('Êtes-vous sûr de vouloir annuler ce trajet ? Tous les passagers seront remboursés.');">Annuler</button>
                                            </form>
                                        <?php else: ?>
                                            <a href="<?= SITE_URL ?>details_trajet.php?id=<?= $trip['id'] ?>" class="button view-button">Voir</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script src="app.js"></script>
</body>
</html>
