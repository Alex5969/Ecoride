<?php
require_once __DIR__ . '/configuration.php';
require_once __DIR__ . '/core/session_auth.php';

start_secure_session();

if (!is_logged_in()) {
    redirect_to(SITE_URL . 'vue_authentification.php');
}
//analyse le role et le nom pour renvoyer au bon tableau de bord
$role = get_logged_in_user_info('role');
$username = get_logged_in_user_info('username');

if ($role === 'driver') {
    redirect_to(SITE_URL . 'tableau_de_bord_conducteur.php');
} elseif ($role === 'traveler') {
    redirect_to(SITE_URL . 'tableau_de_bord_voyageur.php');
} elseif ($role === 'employee') {
    redirect_to(SITE_URL . 'tableau_de_bord_employe.php');
} elseif ($role === 'admin') {
    redirect_to(SITE_URL . 'tableau_de_bord_administrateur.php');
} elseif ($role === 'suspended') {
    add_error("Votre compte est suspendu. Veuillez contacter l'administrateur.");
    logout_user();
    redirect_to(SITE_URL . 'vue_authentification.php');
} else {
    add_error("Rôle utilisateur inconnu. Veuillez contacter l'administrateur.");
    logout_user();
    redirect_to(SITE_URL . 'vue_authentification.php');
}
?>
