<?php
require_once __DIR__ . '/auth.php';
requireLogin();
?>
<?php
// Inclure la connexion à la base de données
require_once 'db_config.php';

// Récupérer l'ID du produit à supprimer
$id = $_GET['id'];

// Requête pour supprimer le produit de la base de données
$sql = "DELETE FROM produits WHERE id = '$id'";

// Exécuter la requête
if ($conn->query($sql) === TRUE) {
    // Rediriger vers la page principale après suppression
    header("Location: produits.php");
    exit();
} else {
    echo "Erreur: " . $conn->error;
}

$conn->close();
