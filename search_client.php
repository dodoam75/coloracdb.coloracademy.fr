<?php
require_once __DIR__ . '/auth.php';
requireLogin();
?>
<?php
// Inclure la connexion à la base de données
require_once 'db_config.php';

// Vérifier si une requête a été envoyée
if (isset($_POST['query'])) {
    $client_name = $conn->real_escape_string($_POST['query']);

    // Requête pour chercher des correspondances partielles avec l'ID
    $sql = "SELECT id, nom FROM clients WHERE nom LIKE '%$client_name%' LIMIT 5";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        // Affichage des résultats
        while ($row = $result->fetch_assoc()) {
            echo '<div class="suggestion-item" data-name="' . htmlspecialchars($row['nom']) . '">'
                 . htmlspecialchars($row['nom'])
                 . '</div>';
        }
    } else {
        echo '<div style="padding: 10px; color: #999;">Aucun résultat trouvé</div>';
    }
}

$conn->close();
?>