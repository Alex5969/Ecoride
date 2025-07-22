<?php

function start_secure_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    session_regenerate_id(true);
}

function is_logged_in(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function login_user(int $userId, string $username, string $role): void {
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['username'] = $username;
    $_SESSION['role'] = $role;
}

function logout_user(): void {
    $_SESSION = [];
    session_destroy();
}

function get_logged_in_user_info(string $key = null) {
    if (!is_logged_in()) {
        return null;
    }
    if ($key === null) {
        return $_SESSION;
    }
    return $_SESSION[$key] ?? null;
}

function verify_role_access(array $allowedRoles): bool {
    if (!is_logged_in()) {
        return false;
    }
    $userRole = get_logged_in_user_info('role');
    return in_array($userRole, $allowedRoles);
}

function generate_csrf_token(string $formName = 'default_form'): string {
    if (empty($_SESSION['csrf_tokens'])) {
        $_SESSION['csrf_tokens'] = [];
    }
    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_tokens'][$formName] = $token;
    return $token;
}

function verify_csrf_token(string $submittedToken, string $formName = 'default_form'): bool {
    if (empty($_SESSION['csrf_tokens'][$formName]) || !$submittedToken) {
        unset($_SESSION['csrf_tokens'][$formName]);
        return false;
    }
    $valid = ($_SESSION['csrf_tokens'][$formName] === $submittedToken);
    unset($_SESSION['csrf_tokens'][$formName]);
    return $valid;
}

function redirect_to(string $url): void {
    header("Location: " . $url);
    exit;
}

function sanitize_html_output(string $string): string {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

function validate_email(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validate_password(string $password): bool {
    $regex = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/';
    return preg_match($regex, $password) === 1;
}

function add_error(string $message): void {
    if (!isset($_SESSION['errors'])) {
        $_SESSION['errors'] = [];
    }
    $_SESSION['errors'][] = $message;
}

function get_and_clear_errors(): array {
    $errors = $_SESSION['errors'] ?? [];
    unset($_SESSION['errors']);
    return $errors;
}

function add_success_message(string $message): void {
    $_SESSION['success_message'] = $message;
}

function get_and_clear_success_message(): ?string {
    $message = $_SESSION['success_message'] ?? null;
    unset($_SESSION['success_message']);
    return $message;
}

function get_user_credits(PDO $pdo, int $userId): float {
    try {
        $stmt = $pdo->prepare("SELECT credits FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        return $result['credits'] ?? 0.00;
    } catch (PDOException $e) {
        error_log("Error retrieving user credits (ID: {$userId}): " . $e->getMessage());
        return 0.00;
    }
}

