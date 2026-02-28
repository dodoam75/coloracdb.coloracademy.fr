<?php
require_once __DIR__ . '/auth.php';
requireLogin();
?>
<?php
// recherche.php

// Inclure la connexion à la base de données
require_once 'db_config.php';

// Fonction générique pour récupérer des valeurs DISTINCT dans une table/colonne
function getDistinctValues($conn, $table, $column)
{
    $values = [];
    $sql = "SELECT DISTINCT $column FROM $table WHERE $column IS NOT NULL AND $column != '' ORDER BY $column";
    $result = $conn->query($sql);

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $values[] = $row[$column];
        }
        $result->free();
    }
    return $values;
}

// Récupérer les listes globales depuis la table ordis
$marques = getDistinctValues($conn, 'ordis', 'marque');
$os_list = getDistinctValues($conn, 'ordis', 'os');
$versions_os = getDistinctValues($conn, 'ordis', 'version_os');
$ordinateurs = getDistinctValues($conn, 'ordis', 'nom');

// Récupérer les listes depuis la table rips (id_ordi et id_dongle)
$id_ordis = getDistinctValues($conn, 'rips', 'id_ordi');
$id_dongles = getDistinctValues($conn, 'rips', 'id_dongle');