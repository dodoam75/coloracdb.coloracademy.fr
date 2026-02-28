<?php
// Paramètres de connexion à la base de données
$servername = "********";
$username = "*******";
$password = "*******";
$dbname = "********";

// Créer la connexion
$conn = new mysqli($servername, $username, $password, $dbname);

// Vérifier la connexion
if ($conn->connect_error) {
    die("Connexion échouée : " . $conn->connect_error);
}

// Optionnel : Forcer l'encodage UTF-8 pour éviter les problèmes d'accents
$conn->set_charset("utf8");
?>