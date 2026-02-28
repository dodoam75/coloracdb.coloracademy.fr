<?php
require_once __DIR__ . '/auth.php';
requireLogin();
?>
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
?>
<?php
// save_imprimante.php

// Inclure la connexion à la base de données
require_once 'db_config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_POST['id'];

    // Récupération sécurisée des champs
    $nom = $_POST['nom'] ?? '';
    $marque = $_POST['marque'] ?? '';
    $modele = $_POST['modele'] ?? '';
    $serial = $_POST['serial'] ?? '';
    $emplacement = $_POST['emplacement'] ?? '';
    $connexion = $_POST['connexion'] ?? '';
    $ip = $_POST['ip'] ?? '';
    $mac = $_POST['mac'] ?? '';
    $firmware = $_POST['firmware'] ?? '';
    $mdp = $_POST['mdp'] ?? '';
    $installation = $_POST['installation'] ?? null;
    $garantie = $_POST['garantie'] !== '' ? (int)$_POST['garantie'] : null;
    $sous_garantie = $_POST['sous_garantie'] ?? '';
    $extension = $_POST['extension'] !== '' ? (int)$_POST['extension'] : 0;
    $fournie_par = isset($_POST['fournie_par']) ? (int)$_POST['fournie_par'] : 0;
    $notes = $_POST['notes'] ?? '';

    $itemsToAdd = json_decode($_POST['items_to_add'] ?? '[]', true);
    $itemsToRemove = json_decode($_POST['items_to_remove'] ?? '[]', true);

    // Modif_auteur par défaut
    $modif_auteur = !empty($_SESSION['username']) ? $_SESSION['username'] : 'Admin';

    // Mettre à jour imprimante
    $stmt = $conn->prepare("
        UPDATE imprimantes SET
            nom=?, marque=?, modele=?, serial=?, emplacement=?,
            connexion=?, ip=?, mac=?, firmware=?, password=?,
            date_install=?, garantie=?, sous_garantie=?, ext_garantie=?,
            fourni_ca=?, notes=?, modif_date=NOW(), modif_auteur=?
        WHERE id=?
    ");

    $stmt->bind_param(
        "sssssssssssiissssi",
        $nom, $marque, $modele, $serial, $emplacement,
        $connexion, $ip, $mac, $firmware, $mdp,
        $installation, $garantie, $sous_garantie, $extension,
        $fournie_par, $notes, $modif_auteur,
        $id
    );

    $stmt->execute();

    // Gestion des associations (papiers, ordis, rips)
    foreach ($itemsToAdd as $item) {
        if ($item['type'] === 'papier') {
            $conn->query("INSERT IGNORE INTO imprimante_papiers (id_imprimante, id_papier) VALUES ($id, " . (int)$item['id'] . ")");
        }
        if ($item['type'] === 'ordi') {
            $conn->query("INSERT IGNORE INTO imprimante_ordis (id_imprimante, id_ordi) VALUES ($id, " . (int)$item['id'] . ")");
        }
        if ($item['type'] === 'rip') {
            $conn->query("INSERT IGNORE INTO imprimante_rips (id_imprimante, id_rip) VALUES ($id, " . (int)$item['id'] . ")");
        }
    }

    foreach ($itemsToRemove as $item) {
        if ($item['type'] === 'papier') {
            $conn->query("DELETE FROM imprimante_papiers WHERE id_imprimante=$id AND id_papier=" . (int)$item['id']);
        }
        if ($item['type'] === 'ordi') {
            $conn->query("DELETE FROM imprimante_ordis WHERE id_imprimante=$id AND id_ordi=" . (int)$item['id']);
        }
        if ($item['type'] === 'rip') {
            $conn->query("DELETE FROM imprimante_rips WHERE id_imprimante=$id AND id_rip=" . (int)$item['id']);
        }
    }

    // Redirection vers la page imprimantes
    $client_name = $_GET['client_name'] ?? '';
    header("Location: ?client_name=" . urlencode($client_name) . "&page=imprimantes&id=$id");
    exit;
}
$client_name = $_POST['client_name'] ?? '';
$id = (int)$_POST['id'];

header("Location: ?client_name=" . urlencode($client_name) . "&page=imprimantes&id=$id");
exit;
?>