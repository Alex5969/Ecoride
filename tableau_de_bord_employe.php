<?php
require_once __DIR__ . '/configuration.php';
require_once __DIR__ . '/core/session_auth.php';
require_once __DIR__ . '/core/mongodb_logger.php';

start_secure_session();

if (!is_logged_in() || !verify_role_access(['employee', 'admin'])) {
    add_error("Accès non autorisé. Veuillez vous connecter en tant qu'employé ou administrateur.");
    redirect_to(SITE_URL . 'vue_authentification.php');
}

$userId = get_logged_in_user_info('user_id');
$username = get_logged_in_user_info('username');

$errorMessages = get_and_clear_errors();
$successMessage = get_and_clear_success_message();

// Traitement pour approuver ou refuser un avis
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && ($_POST['action'] === 'approve_review' || $_POST['action'] === 'reject_review')) {
    $reviewId = (int)($_POST['review_id'] ?? 0);
    $actionType = $_POST['action'];
    $csrfTokenAction = $_POST['csrf_token'] ?? null;

    if ($reviewId > 0 && verify_csrf_token($csrfTokenAction, 'review_action_form_' . $reviewId)) {
        try {
            $newStatus = ($actionType === 'approve_review') ? 'approved' : 'rejected';
            $stmt = $pdo->prepare("UPDATE reviews SET status = ? WHERE id = ? AND status = 'pending'");
            $stmt->execute([$newStatus, $reviewId]);

            if ($stmt->rowCount() > 0) {
                add_success_message("Avis " . ($actionType === 'approve_review' ? "approuvé" : "refusé") . " avec succès.");
            } else {
                add_error("Avis introuvable ou déjà traité.");
            }
        } catch (PDOException $e) {
            add_error("Erreur lors du traitement de l'avis : " . $e->getMessage());
            error_log("Employee Review Action Error: " . $e->getMessage());
        }
    } else {
        add_error("Erreur de sécurité : Jeton CSRF invalide pour l'action d'avis.");
    }
    redirect_to(SITE_URL . 'tableau_de_bord_employe.php#manage-reviews');
}

// Traitement pour la résolution des problèmes signalés
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'resolve_issue') {
    $reportId = (int)($_POST['report_id'] ?? 0);
    $csrfTokenAction = $_POST['csrf_token'] ?? null;

    if ($reportId > 0 && verify_csrf_token($csrfTokenAction, 'issue_action_form_' . $reportId)) {
        try {
            $stmt = $pdo->prepare("UPDATE issue_reports SET status = 'resolved' WHERE id = ? AND status = 'new'");
            $stmt->execute([$reportId]);
            if ($stmt->rowCount() > 0) {
                add_success_message("Problème signalé comme résolu.");
            } else {
                add_error("Problème signalé introuvable ou déjà résolu.");
            }
        } catch (PDOException $e) {
            add_error("Erreur lors de la résolution du problème : " . $e->getMessage());
            error_log("Employee Resolve Issue Error: " . $e->getMessage());
        }
    } else {
        add_error("Erreur de sécurité : Jeton CSRF invalide pour l'action de résolution de problème.");
    }
    redirect_to(SITE_URL . 'tableau_de_bord_employe.php#view-issues');
}

// Récupere les avis en attente de traitement
$pendingReviews = [];
try {
    $stmt = $pdo->prepare("SELECT r.*, t.departure_city, t.arrival_city, t.departure_datetime, u_reviewer.username AS reviewer_username, u_driver.username AS driver_username FROM reviews r JOIN trips t ON r.trip_id = t.id JOIN users u_reviewer ON r.reviewer_id = u_reviewer.id JOIN users u_driver ON r.driver_id = u_driver.id WHERE r.status = 'pending' ORDER BY r.submission_date ASC");
    $stmt->execute();
    $pendingReviews = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error retrieving pending reviews: " . $e->getMessage());
    add_error("Erreur lors du chargement des avis en attente.");
}

// Récupération des problèmes non résolus dans un tableau
$unresolvedIssues = [];
try {
    $stmt = $pdo->prepare("
        SELECT ir.*, t.departure_city, t.arrival_city, t.departure_datetime, u_reporter.username AS reporter_username, u_reporter.email AS reporter_email, u_driver.username AS driver_username, u_driver.email AS driver_email
        FROM issue_reports ir
        JOIN trips t ON ir.trip_id = t.id
        JOIN users u_reporter ON ir.reporter_id = u_reporter.id
        JOIN users u_driver ON t.driver_id = u_driver.id
        WHERE ir.status = 'new'
        ORDER BY ir.report_date ASC
    ");
    $stmt->execute();
    $unresolvedIssues = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error retrieving unresolved issues: " . $e->getMessage());
    add_error("Erreur lors du chargement des problèmes non résolus.");
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord Employé - EcoRide</title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <div class="logo-container">
        <a href="<?= SITE_URL ?>index.php" class="logo">EcoRide</a>
        </div>
        <nav class="navbar">
            <ul class="nav-links">
                <li><a href="<?= SITE_URL ?>vue_trajets.php">Covoiturages</a></li>
                <?php if (is_logged_in()): ?>
                    <li><a href="<?= SITE_URL ?>tableau_de_bord.php">Tableau de bord</a></li>
                    <li><a href="<?= SITE_URL ?>deconnexion.php">Déconnexion</a></li>
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
        <h1>Tableau de Bord Employé</h1>

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
            <button class="tab-button active" data-tab="manage-reviews">Valider les Avis</button>
            <button class="tab-button" data-tab="view-issues">Voir les Problèmes Signalés</button>
        </div>

        <div id="manage-reviews" class="tab-content active">
            <div class="section pending-reviews">
                <h2>Avis en attente de validation</h2>
                <?php if (empty($pendingReviews)): ?>
                    <p>Aucun avis en attente pour le moment.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Trajet</th>
                                <th>Évaluateur</th>
                                <th>Conducteur</th>
                                <th>Note</th>
                                <th>Commentaire</th>
                                <th>Date Soumission</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendingReviews as $review): ?>
                                <tr>
                                    <td><?= sanitize_html_output($review['departure_city']) ?> à <?= sanitize_html_output($review['arrival_city']) ?> (<?= date('d/m/Y', strtotime($review['departure_datetime'])) ?>)</td>
                                    <td><?= sanitize_html_output($review['reviewer_username']) ?></td>
                                    <td><?= sanitize_html_output($review['driver_username']) ?></td>
                                    <td><?= $review['rating'] ?>/5</td>
                                    <td><?= nl2br(sanitize_html_output($review['comment'])) ?></td>
                                    <td><?= date('d/m/Y H:i', strtotime($review['submission_date'])) ?></td>
                                    <td>
                                        <form action="<?= SITE_URL ?>tableau_de_bord_employe.php" method="post" style="display:inline;">
                                            <input type="hidden" name="action" value="approve_review">
                                            <input type="hidden" name="review_id" value="<?= $review['id'] ?>">
                                            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token('review_action_form_' . $review['id']) ?>">
                                            <button type="submit" class="button approve-button">Approuver</button>
                                        </form>
                                        <form action="<?= SITE_URL ?>tableau_de_bord_employe.php" method="post" style="display:inline;">
                                            <input type="hidden" name="action" value="reject_review">
                                            <input type="hidden" name="review_id" value="<?= $review['id'] ?>">
                                            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token('review_action_form_' . $review['id']) ?>">
                                            <button type="submit" class="button cancel-button">Refuser</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <div id="view-issues" class="tab-content">
            <div class="section unresolved-issues">
                <h2>Problèmes signalés non résolus</h2>
                <?php if (empty($unresolvedIssues)): ?>
                    <p>Aucun problème non résolu pour le moment.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID Trajet</th>
                                <th>Voyageur</th>
                                <th>Email Voyageur</th>
                                <th>Conducteur</th>
                                <th>Email Conducteur</th>
                                <th>Trajet Détails</th>
                                <th>Description du Problème</th>
                                <th>Date Signalement</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($unresolvedIssues as $issue): ?>
                                <tr>
                                    <td><?= $issue['trip_id'] ?></td>
                                    <td><?= sanitize_html_output($issue['reporter_username']) ?></td>
                                    <td><?= sanitize_html_output($issue['reporter_email']) ?></td>
                                    <td><?= sanitize_html_output($issue['driver_username']) ?></td>
                                    <td><?= sanitize_html_output($issue['driver_email']) ?></td>
                                    <td>
                                        De: <?= sanitize_html_output($issue['departure_city']) ?><br>
                                        À: <?= sanitize_html_output($issue['arrival_city']) ?><br>
                                        Le: <?= date('d/m/Y H:i', strtotime($issue['departure_datetime'])) ?>
                                    </td>
                                    <td><?= nl2br(sanitize_html_output($issue['issue_description'])) ?></td>
                                    <td><?= date('d/m/Y H:i', strtotime($issue['report_date'])) ?></td>
                                    <td>
                                        <form action="<?= SITE_URL ?>tableau_de_bord_employe.php" method="post" style="display:inline;">
                                            <input type="hidden" name="action" value="resolve_issue">
                                            <input type="hidden" name="report_id" value="<?= $issue['id'] ?>">
                                            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token('issue_action_form_' . $issue['id']) ?>">
                                            <button type="submit" class="button complete-button" onclick="return confirm('Confirmez-vous que ce problème est résolu et que le conducteur a été contacté si nécessaire ?');">Résolu</button>
                                        </form>
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
