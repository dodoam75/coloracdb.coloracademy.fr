<?php
require_once __DIR__ . '/auth.php';
requireLogin();
?>
<?php
// Inclure la connexion à la base de données
require_once 'db_config.php';

// Vérification des paramètres GET
if (!isset($_GET['id']) || !isset($_GET['redirect'])) {
    die("Paramètres manquants.");
}

$id = intval($_GET['id']);
$redirect = $_GET['redirect'];

// Préparation de la requête SQL sécurisée pour supprimer une feuille dans la table papiers
$stmt = $conn->prepare("DELETE FROM papiers WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    // Redirection après suppression réussie
    header("Location: " . $redirect);
    exit();
} else {
    echo "Erreur : " . $stmt->error;
}

$stmt->close();
$conn->close();
?>
