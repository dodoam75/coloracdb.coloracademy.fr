<?php
require_once __DIR__ . '/auth.php';
requireLogin();
?>
<?php
// supprimer_fiche.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$servername = "localhost";
$username = "root";
$password = "root";
$dbname = "coloracdb";

$mysqli = new mysqli($servername, $username, $password, $dbname);
if ($mysqli->connect_error) {
    die("Connexion échouée : " . $mysqli->connect_error);
}
$mysqli->set_charset("utf8");

// Vérifier que l'id est fourni et valide
if (isset($_GET['id']) && ctype_digit($_GET['id'])) {
    $id = (int) $_GET['id'];

    // Préparer et exécuter la suppression
    $stmt = $mysqli->prepare("DELETE FROM fiches_test WHERE id = ?");
    if (!$stmt) {
        die("Erreur préparation requête : " . $mysqli->error);
    }
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        // Succès, rediriger vers la page principale (avec succès)
        header("Location: newfit.php?msg=deleted");
        exit;
    } else {
        die("Erreur lors de la suppression : " . $stmt->error);
    }
} else {
    // Id manquant ou invalide
    header("Location: newfit.php?msg=invalid_id");
    exit;
}