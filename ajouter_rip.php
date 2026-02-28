<?php
require_once __DIR__ . '/auth.php';
requireLogin();
?>
<?php
// ajouter_rip.php
ini_set('display_errors', 1);
error_reporting(E_ALL);
include 'navbar.php';

try {
    if (!isset($_GET['client_name']) || empty($_GET['client_name'])) {
        throw new Exception("Paramètre client_name manquant.");
    }

    $client_name = $_GET['client_name'];

    // Trouver le client_id via client_name
    $stmtClient = $conn->prepare("SELECT id FROM clients WHERE nom = ?");
    $stmtClient->bind_param("s", $client_name);
    $stmtClient->execute();
    $resultClient = $stmtClient->get_result();

    if ($resultClient->num_rows === 0) {
        throw new Exception("Client introuvable.");
    }

    $client = $resultClient->fetch_assoc();
    $client_id = $client['id'];

    // Valeurs par défaut / "au hasard" pour les dates
    $crea_date = '0000-00-00';
    $date_install = '0000-00-00';
    $modif_date = '0000-00-00';

    // Valeurs fixes sauf actif=0
    $id_ordi = '';
    $id_dongle = '';
    $emplacement = '';
    $marque = '';
    $modele = '';
    $version = '';
    $sp = '';
    $garantie = 0;
    $ext_garantie = 0;
    $sous_garantie = 0;
    $fourni_ca = "oui";
    $notes = '';
    $crea_auteur = 1;
    $modif_auteur = '';
    $actif = 0;

    $sql = "INSERT INTO rips
    (id_ordi, id_client, id_dongle, emplacement, marque, modele, version, sp, date_install, garantie, ext_garantie, sous_garantie, fourni_ca, notes, crea_date, crea_auteur, modif_date, modif_auteur, actif)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Erreur préparation requête : " . $conn->error);
    }

    $stmt->bind_param(
        "iiisssssiiiiiissisi",
        $id_ordi,
        $client_id,
        $id_dongle,
        $emplacement,
        $marque,
        $modele,
        $version,
        $sp,
        $date_install,
        $garantie,
        $ext_garantie,
        $sous_garantie,
        $fourni_ca,
        $notes,
        $crea_date,
        $crea_auteur,
        $modif_date,
        $modif_auteur,
        $actif
    );

    $stmt->execute();

    // Rediriger vers la page rips du client (page et client_name dans URL)
    header("Location: client.php?client_name=" . urlencode($client_name) . "&page=rips");
    exit;

} catch (Exception $e) {
    echo "Erreur : " . $e->getMessage();
}