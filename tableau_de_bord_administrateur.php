<?php
require_once __DIR__ . '/configuration.php';
require_once __DIR__ . '/core/session_auth.php';
require_once __DIR__ . '/core/mongodb_logger.php';

start_secure_session();

if (!is_logged_in() || !verify_role_access(['admin'])) {
    add_error("Accès non autorisé. Veuillez vous connecter en tant qu'administrateur.");
    redirect_to(SITE_URL . 'vue_authentification.php');
}

$userId = get_logged_in_user_info('user_id');
$username = get_logged_in_user_info('username');

$errorMessages = get_and_clear_errors();
$successMessage = get_and_clear_success_message();

//fonction création compte employé
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_employee') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null, 'create_employee_form')) {
        add_error("Erreur de sécurité : Jeton CSRF invalide ou expiré.");
    } else {
        $employeeUsername = sanitize_html_output(trim($_POST['employee_username'] ?? ''));
        $employeeEmail = trim($_POST['employee_email'] ?? '');
        $employeePassword = $_POST['employee_password'] ?? '';

        if (empty($employeeUsername) || empty($employeeEmail) || empty($employeePassword) || !validate_email($employeeEmail) || !validate_password($employeePassword)) {
            add_error("Détails d'employé invalides. Le pseudo, un email valide et un mot de passe fort sont requis.");
        } else {
            try {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
                $stmt->execute([$employeeUsername, $employeeEmail]);
                if ($stmt->fetchColumn() > 0) {
                    add_error("Pseudo ou email déjà utilisé pour un compte employé.");
                } else {
                    $passwordHash = password_hash($employeePassword, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, 'employee')");
                    $stmt->execute([$employeeUsername, $employeeEmail, $passwordHash]);
                    add_success_message("Compte employé pour " . $employeeUsername . " créé avec succès !");
                }
            } catch (PDOException $e) {
                add_error("Erreur lors de la création du compte employé : " . $e->getMessage());
                error_log("Create Employee PDO Error: " . $e->getMessage());
            }
        }
    }
    redirect_to(SITE_URL . 'tableau_de_bord_administrateur.php#manage-employees');
}

// fonction supprime un utilisateur
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_user') {
    $targetUserId = (int)($_POST['target_user_id'] ?? 0);
    $csrfTokenAction = $_POST['csrf_token'] ?? null;

    if ($targetUserId > 0 && verify_csrf_token($csrfTokenAction, 'delete_user_form_' . $targetUserId)) {
        try {
            if ($targetUserId === $userId) {
                throw new Exception("Vous ne pouvez pas supprimer votre propre compte administrateur.");
            }
            
            $stmtCheckRole = $pdo->prepare("SELECT role FROM users WHERE id = ?");
            $stmtCheckRole->execute([$targetUserId]);
            $targetRole = $stmtCheckRole->fetchColumn();

            if ($targetRole === 'admin') {
                throw new Exception("Vous ne pouvez pas supprimer un autre compte administrateur.");
            }
            if ($targetRole === false) {
                throw new Exception("Utilisateur introuvable.");
            }

            $pdo->beginTransaction();

            $stmtDeleteUser = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmtDeleteUser->execute([$targetUserId]);
            
            if ($stmtDeleteUser->rowCount() > 0) {
                add_success_message("Compte utilisateur supprimé définitivement avec succès.");
            } else {
                add_error("Utilisateur introuvable");
            }
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            add_error("Erreur lors de la suppression du compte : " . $e->getMessage());
            error_log("Delete User Error: " . $e->getMessage());
        } catch (PDOException $e) {
            $pdo->rollBack();
            add_error("Une erreur de la base de données est survenue lors de la suppression du compte.");
            error_log("Delete User PDO Error: " . $e->getMessage());
        }
    } else {
        add_error("Erreur de sécurité : Jeton CSRF invalide pour l'action de suppression.");
    }
    redirect_to(SITE_URL . 'tableau_de_bord_administrateur.php#manage-users');
}

//récupère la liste de tous les utilisateurs
$allUsers = [];
try {
    $stmt = $pdo->prepare("SELECT id, username, email, role, credits, registration_date FROM users ORDER BY registration_date DESC");
    $stmt->execute();
    $allUsers = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Erreur lors du chargement de la liste des utilisateurs.");
    add_error("Erreur lors du chargement de la liste des utilisateurs.");
}

//calcul des credits gangés si un voyage est confirmé
$totalPlatformCreditsMysql = 0;
try {
    $stmt = $pdo->prepare("SELECT SUM(platform_fee) FROM trips WHERE trip_status = 'completed'");
    $totalPlatformCreditsMysql = $stmt->fetchColumn();
    if (!$totalPlatformCreditsMysql) $totalPlatformCreditsMysql = 0;
} catch (PDOException $e) {
    error_log("Erreur lors du calcul du total des crédits plateforme (MySQL): " . $e->getMessage());
    add_error("Erreur lors du calcul du total des crédits plateforme.");
}

$visitsPerDay = MongoDBLogger::aggregate_data('visits', [
    ['$group' => [
        '_id' => ['$dateToString' => ['format' => '%Y-%m-%d', 'date' => ['$toDate' => '$timestamp']]],
        'count' => ['$sum' => 1]
    ]],
    ['$sort' => ['_id' => 1]],
    ['$limit' => 30]
]);

//calcule des transactions journalières et des montants et des frais de plateforme prélevés sur les paiements aux conducteurs.
$creditTransactionsPerDay = MongoDBLogger::aggregate_data('credit_transactions', [
    ['$group' => [
        '_id' => ['$dateToString' => ['format' => '%Y-%m-%d', 'date' => ['$toDate' => '$timestamp']]],
        'total_amount' => ['$sum' => '$amount'],
        'platform_fee_sum' => ['$sum' => ['$cond' => [['$eq' => ['$type', 'driver_payout']], '$platform_fee_deducted', 0]] ]
    ]],
    ['$sort' => ['_id' => 1]],
    ['$limit' => 30]
]);


//boucle pour les graphiques
$chartVisitsLabels = [];
$chartVisitsData = [];
foreach ($visitsPerDay as $data) {
    $chartVisitsLabels[] = $data['_id'];
    $chartVisitsData[] = $data['count'];
}

$chartEarningsLabels = [];
$chartEarningsData = [];
$chartPlatformFeesData = [];
foreach ($creditTransactionsPerDay as $data) {
    $chartEarningsLabels[] = $data['_id'];
    $chartEarningsData[] = $data['total_amount'];
    $chartPlatformFeesData[] = $data['platform_fee_sum'];
}

//calculer les frais de plateforme perçus sur les paiements aux conducteurs (commission).
$totalPlatformCreditsMongodbAgg = MongoDBLogger::aggregate_data('credit_transactions', [
    ['$match' => ['type' => 'driver_payout']],
    ['$group' => [
        '_id' => null,
        'total' => ['$sum' => '$platform_fee_deducted']
    ]]
]);
$totalPlatformCreditsMongodb = $totalPlatformCreditsMongodbAgg[0]['total'] ?? 0;


$csrfTokenCreateEmployee = generate_csrf_token('create_employee_form');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord Administrateur - EcoRide</title>
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
        <h1>Tableau de Bord Administrateur</h1>

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
            <button class="tab-button active" data-tab="analytics">Statistiques de la Plateforme</button>
            <button class="tab-button" data-tab="manage-employees">Gérer les Employés</button>
            <button class="tab-button" data-tab="manage-users">Gérer les Utilisateurs</button>
        </div>

        <div id="analytics" class="tab-content active">
            <div class="section">
                <h2>Statistiques de la Plateforme (US 13)</h2>
                <p><strong>Total des crédits gagnés par la plateforme (MySQL):</strong> <?= number_format($totalPlatformCreditsMysql, 2) ?> crédits</p>
                <p><strong>Total des crédits gagnés par la plateforme (MongoDB):</strong> <?= number_format($totalPlatformCreditsMongodb, 2) ?> crédits</p>


                <div class="chart-container">
                    <h3>Nombre de visites de la page d'accueil par jour (MongoDB)</h3>
                    <canvas id="visitsPerDayChart"></canvas>
                </div>

                <div class="chart-container">
                    <h3>Gains de la plateforme par jour (crédits) (MongoDB)</h3>
                    <canvas id="platformEarningsPerDayChart"></canvas>
                </div>
            </div>
        </div>

        <div id="manage-employees" class="tab-content">
            <div class="section">
                <h2>Créer un compte employé (US 13)</h2>
                <form id="create-employee-form" action="<?= SITE_URL ?>tableau_de_bord_administrateur.php" method="post">
                    <input type="hidden" name="action" value="create_employee">
                    <input type="hidden" name="csrf_token" value="<?= sanitize_html_output($csrfTokenCreateEmployee) ?>">
                    <div class="input-group">
                        <label for="employee_username">Pseudo Employé</label>
                        <input type="text" id="employee_username" name="employee_username" required>
                    </div>
                    <div class="input-group">
                        <label for="employee_email">Email Employé</label>
                        <input type="email" id="employee_email" name="employee_email" required>
                    </div>
                    <div class="input-group">
                        <label for="employee_password">Mot de passe Employé</label>
                        <input type="password" id="employee_password" name="employee_password" required>
                    </div>
                    <button type="submit">Créer Employé</button>
                </form>
            </div>
            <div class="section">
                <h2>Liste des Employés</h2>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Pseudo</th>
                            <th>Email</th>
                            <th>Date Inscription</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allUsers as $user): ?>
                            <?php if ($user['role'] === 'employee'): ?>
                                <tr>
                                    <td><?= $user['id'] ?></td>
                                    <td><?= sanitize_html_output($user['username']) ?></td>
                                    <td><?= sanitize_html_output($user['email']) ?></td>
                                    <td><?= date('d/m/Y', strtotime($user['registration_date'])) ?></td>
                                    <td>
                                        <?php if ($user['id'] !== $userId): ?>
                                            <form action="<?= SITE_URL ?>tableau_de_bord_administrateur.php" method="post" style="display:inline;">
                                                <input type="hidden" name="action" value="delete_user">
                                                <input type="hidden" name="target_user_id" value="<?= $user['id'] ?>">
                                                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token('delete_user_form_' . $user['id']) ?>">
                                                <button type="submit" class="button cancel-button" onclick="return confirm('Êtes-vous sûr de vouloir supprimer définitivement ce compte employé ? Cette action est irréversible.');">Supprimer Définitivement</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="status-disabled">Action non disponible</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="manage-users" class="tab-content">
            <div class="section">
                <h2>Gérer tous les Utilisateurs (Voyageurs & Conducteurs)</h2>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Pseudo</th>
                            <th>Email</th>
                            <th>Rôle</th>
                            <th>Crédits</th>
                            <th>Date Inscription</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allUsers as $user): ?>
                            <?php if ($user['role'] !== 'employee' && $user['role'] !== 'admin'): ?>
                                <tr>
                                    <td><?= $user['id'] ?></td>
                                    <td><?= sanitize_html_output($user['username']) ?></td>
                                    <td><?= sanitize_html_output($user['email']) ?></td>
                                    <td><?= sanitize_html_output(ucfirst($user['role'])) ?></td>
                                    <td><?= number_format($user['credits'], 2) ?></td>
                                    <td><?= date('d/m/Y', strtotime($user['registration_date'])) ?></td>
                                    <td>
                                        <?php if ($user['id'] !== $userId && $user['role'] !== 'admin'): ?>
                                            <form action="<?= SITE_URL ?>tableau_de_bord_administrateur.php" method="post" style="display:inline;">
                                                <input type="hidden" name="action" value="delete_user">
                                                <input type="hidden" name="target_user_id" value="<?= $user['id'] ?>">
                                                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token('delete_user_form_' . $user['id']) ?>">
                                                <button type="submit" class="button cancel-button" onclick="return confirm('Êtes-vous sûr de vouloir supprimer définitivement ce compte utilisateur ? Cette action est irréversible.');">Supprimer Définitivement</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="status-disabled">Action non disponible</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script>
        const chartVisitsLabels = <?= json_encode($chartVisitsLabels) ?>;
        const chartVisitsData = <?= json_encode($chartVisitsData) ?>;
        const chartEarningsLabels = <?= json_encode($chartEarningsLabels) ?>;
        const chartEarningsData = <?= json_encode($chartEarningsData) ?>;
        const chartPlatformFeesData = <?= json_encode($chartPlatformFeesData) ?>;
    </script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script> 
    <script src="app.js"></script>
</body>
</html>
