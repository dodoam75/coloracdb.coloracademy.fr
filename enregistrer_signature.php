<?php
require_once __DIR__ . '/auth.php';
requireLogin();
?>
<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Inclure la connexion à la base de données
require_once 'db_config.php';

if (!isset($_FILES['signature']) || !isset($_POST['idFiche'])) {
    exit("Données incomplètes.");
}

$idFiche = intval($_POST['idFiche']);

// Récupérer infos client/date
$stmt = $conn->prepare("SELECT id_client, ladate FROM fiches_test WHERE id = ?");
$stmt->bind_param("i", $idFiche);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    exit("Fiche introuvable.");
}

$row = $result->fetch_assoc();
$id_client = $row['id_client'];
$ladate = preg_replace("/[^0-9]/", "", $row['ladate']);

$imageName = $id_client . '-' . $idFiche . '-' . $ladate;
$cheminDossier = __DIR__ . '/data/signatures/';

if (!file_exists($cheminDossier)) {
    mkdir($cheminDossier, 0777, true);
}

$destination = $cheminDossier . $imageName . '.png';

if (!move_uploaded_file($_FILES['signature']['tmp_name'], $destination)) {
    exit("Échec lors de l'enregistrement du fichier.");
}

// Mise à jour de la base
$stmt = $conn->prepare("UPDATE fiches_test SET sign = 1 WHERE id = ?");
$stmt->bind_param("i", $idFiche);
$stmt->execute();

echo "Signature enregistrée avec succès.";
?>