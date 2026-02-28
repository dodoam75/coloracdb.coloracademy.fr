<?php
/**
 * AUTH.PHP - Gestion de la sécurité et des sessions
 */

// 1. Configuration et démarrage de la session
if (session_status() === PHP_SESSION_NONE) {
    // On définit les paramètres de sécurité AVANT le démarrage
    session_set_cookie_params([
        'lifetime' => 0,          // Expire à la fermeture du navigateur
        'path' => '/',
        'secure' => false,        // TRUE uniquement si tu es en HTTPS
        'httponly' => true,       // Protection contre le vol de session par JS
        'samesite' => 'Strict'    // Protection contre les attaques CSRF
    ]);

    session_start();
}

// 2. Vérifier si l'utilisateur est connecté
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['username']);
}

// 3. Protection des pages : Rediriger vers login si pas connecté
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

// 4. Fonction de connexion
function loginUser($username, $password) {
    // Simulation d'une base de données
    $users = [
        ['id' => 1, 'username' => '*******', 'hash' => '******', 'role' => 'admin'],
        ['id' => 2, 'username' => '******',     'hash' => '*******', 'role' => 'admin'],
        ['id' => 3, 'username' => '******',     'hash' => '*******', 'role' => 'admin']
    ];

    $username = trim($username);

    if (empty($username) || empty($password)) {
        return ['success' => false, 'message' => 'Veuillez remplir tous les champs'];
    }

    foreach ($users as $user) {
        if ($user['username'] === $username) {
            // Vérification sécurisée du mot de passe haché
            if (password_verify($password, $user['hash'])) {

                // CRUCIAL : On change l'ID de session après connexion (anti-hijacking)
                session_regenerate_id(true);

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['login_time'] = time();

                return ['success' => true, 'message' => 'Connexion réussie'];
            }
        }
    }

    return ['success' => false, 'message' => 'Identifiants invalides'];
}

// 5. Déconnexion
function logoutUser() {
    // On vide la session
    $_SESSION = array();
    // On détruit le cookie de session
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    // On détruit la session sur le serveur
    session_destroy();
    header('Location: login.php');
    exit;
}
?>