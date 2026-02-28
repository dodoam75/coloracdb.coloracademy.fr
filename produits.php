<?php
require_once __DIR__ . '/auth.php';
requireLogin();
?>
<?php
include 'navbar.php';

// Fonction pour trouver le plus petit ID disponible
function getSmallestAvailableID($conn) {
    $sql = "SELECT id FROM produits ORDER BY id ASC";
    $result = $conn->query($sql);

    $expected = 1;
    while ($row = $result->fetch_assoc()) {
        if ((int)$row['id'] != $expected) {
            return $expected;
        }
        $expected++;
    }
    return $expected; // Si aucun trou, retourne le suivant
}

// Vérifier si le formulaire a été soumis pour ajouter un produit
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $categorie = !empty($_POST['categorie']) ? $_POST['categorie'] : ' ';
    $marque = !empty($_POST['marque']) ? $_POST['marque'] : ' ';
    $modele = !empty($_POST['modele']) ? $_POST['modele'] : ' ';
    $option = !empty($_POST['option']) ? $_POST['option'] : ' ';
    $version = !empty($_POST['version']) ? $_POST['version'] : ' ';

    // Calculer le plus petit ID disponible
    $newID = getSmallestAvailableID($conn);

    // Préparer et exécuter la requête d'insertion
    $insertSQL = "INSERT INTO produits (id, cat, marque, modele, opt, version) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insertSQL);
    $stmt->bind_param("isssss", $newID, $categorie, $marque, $modele, $option, $version);

    if ($stmt->execute()) {
        header("Location: produits.php");
        exit();
    } else {
        echo "Erreur lors de l'ajout du produit : " . $stmt->error;
    }
    $stmt->close();
}

// Récupérer la liste des produits
$sql = "SELECT * FROM produits ORDER BY id ASC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="stylesheet" href="style.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liste des produits</title>

    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">

    <style>
        .content { margin-top: 70px; padding: 20px; }
        .table thead { background-color: #f4f4f4; }
        .btn-green {
            background-color: green; color: white; padding: 10px 20px;
            border: none; cursor: pointer; margin: 10px auto; border-radius: 5px;
        }
        .btn-green:hover { background-color: darkgreen; }
        .modal { display: none; position: fixed; z-index: 1; left: 0; top: 0;
                 width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); }
        .modal-content {
            background-color: white; margin: 10% auto; padding: 20px;
            width: 80%; max-width: 500px; border-radius: 8px; position: relative;
        }
        .close {
            color: #aaa; font-size: 28px; font-weight: bold;
            position: absolute; top: 5px; right: 10px;
        }
        .close:hover, .close:focus { color: black; cursor: pointer; }
        input { width: 100%; padding: 8px; margin-bottom: 10px; }
    </style>
</head>
<body>

<div class="container content">
    <h2 class="mb-4">Produits</h2>

    <button class="btn-green" id="addProductBtn">Ajouter un produit</button>

    <table id="produitsTable" class="table table-striped table-bordered">
        <thead>
            <tr>
                <th>Catégorie</th>
                <th>Marque</th>
                <th>Modèle</th>
                <th>Option</th>
                <th>Version</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()) { ?>
                <tr>
                    <td><?= htmlspecialchars($row["cat"]) ?></td>
                    <td><?= htmlspecialchars($row["marque"]) ?></td>
                    <td><?= htmlspecialchars($row["modele"]) ?></td>
                    <td><?= htmlspecialchars($row["opt"]) ?></td>
                    <td><?= htmlspecialchars($row["version"]) ?></td>
                    <td class="text-center">
                        <a href="delete.php?id=<?= urlencode($row['id']) ?>" onclick="return confirm('Supprimer ce produit ?');">🗑</a>
                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</div>

<!-- Modale d’ajout -->
<div id="addProductModal" class="modal">
    <div class="modal-content">
        <span class="close" id="closeModal">&times;</span>
        <h4>Ajouter un produit</h4>
        <form action="produits.php" method="POST">
            <input type="text" name="categorie" placeholder="Catégorie">
            <input type="text" name="marque" placeholder="Marque">
            <input type="text" name="modele" placeholder="Modèle">
            <input type="text" name="option" placeholder="Option">
            <input type="text" name="version" placeholder="Version">
            <button type="submit" class="btn-green">Ajouter le produit</button>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>

<script>
$(document).ready(function() {
    var table = $('#produitsTable').DataTable({
        paging: true,
        lengthMenu: [25, 50, 100],
        language: {
            lengthMenu: "Afficher _MENU_ lignes par page",
            zeroRecords: "Aucun produit trouvé",
            info: "Affichage des lignes _START_ à _END_ sur _TOTAL_",
            infoEmpty: "Aucune donnée disponible"
        },
    });

    // Gestion modale
    $("#addProductBtn").click(function() {
        $("#addProductModal").css("display", "block");
    });
    $("#closeModal").click(function() {
        $("#addProductModal").css("display", "none");
    });
    $(window).click(function(event) {
        if (event.target.id === "addProductModal") {
            $("#addProductModal").css("display", "none");
        }
    });
});
</script>

</body>
</html>

<?php
$conn->close();
?>
