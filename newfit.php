<?php
require_once __DIR__ . '/auth.php';
requireLogin();
?>
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Définir le fuseau horaire pour la France
date_default_timezone_set('Europe/Paris');

// Inclure la connexion à la base de données
require_once 'db_config.php';

// API JSON : adresse client par id
if (isset($_GET['client_id'])) {
    $id = (int)$_GET['client_id'];
    $res = $conn->query("SELECT adresse FROM clients WHERE id=$id LIMIT 1");
    echo json_encode($res->fetch_assoc());
    exit;
}

// API JSON : contrats client
if (isset($_GET['contrats'])) {
    $id = (int) $_GET['contrats'];
    $res = $conn->query("SELECT id, type FROM contrats WHERE id_client=$id AND state=1");
    $rows = [];
    while ($row = $res->fetch_assoc()) $rows[] = $row;
    echo json_encode($rows);
    exit;
}

// Déterminer le mode : création ou modification
$mode = 'create';
$fiche_id = 0;
$fiche = null;
$inter = null;
$client_nom = '';

if (isset($_GET['id'])) {
    $mode = 'edit';
    $fiche_id = (int)$_GET['id'];

    // Récupérer les données de la fiche
    $stmt = $conn->prepare("SELECT ft.* FROM fiches_test ft
                             INNER JOIN inter i ON ft.id = i.id
                             WHERE ft.id=?");
    $stmt->bind_param("i", $fiche_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $fiche = $result->fetch_assoc();

    if (!$fiche) {
        die("Fiche introuvable.");
    }

    // Récupérer les données de la table inter
    $stmt_inter = $conn->prepare("SELECT * FROM inter WHERE id=?");
    $stmt_inter->bind_param("i", $fiche_id);
    $stmt_inter->execute();
    $result_inter = $stmt_inter->get_result();
    $inter = $result_inter->fetch_assoc();

    // Récupérer le nom du client
    if ($fiche['id_client']) {
        $stmt_client = $conn->prepare("SELECT nom FROM clients WHERE id=?");
        $stmt_client->bind_param("i", $fiche['id_client']);
        $stmt_client->execute();
        $result_client = $stmt_client->get_result();
        if ($row_client = $result_client->fetch_assoc()) {
            $client_nom = $row_client['nom'];
        }
    }
}

// Traitement formulaire POST
$feedback = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $logo = $_POST['logo'] ?? '';
    $nom = $_POST['nom'] ?? '';
    $id_client = $_POST['id_client'] ?? '';
    $adresse = $_POST['adresse'] ?? '';
    $ladate = $_POST['ladate'] ?: null;
    $start = $_POST['start'] ?: null;
    $end = $_POST['end'] ?: null;
    $tech = $_POST['tech'] ?? '';
    $facturable = $_POST['facturable'] ?? '1';
    $id_contrat = ($facturable === '0') ? (isset($_POST['id_contrat']) && $_POST['id_contrat'] !== '' ? (int)$_POST['id_contrat'] : 0) : 0;
    $descr = $_POST['descr_inter'] ?? '';

    if (isset($_POST['fiche_id']) && $_POST['fiche_id'] > 0) {
        // MODE MODIFICATION
        $fiche_id = (int)$_POST['fiche_id'];
        $stmt = $conn->prepare("UPDATE fiches_test
                                  SET logo=?, id_client=?, nom=?, adresse=?, id_contrat=?, ladate=?, start=?, end=?, tech=?, facturable=?, descr_inter=?
                                  WHERE id=?");
        if (!$stmt) {
            die("Erreur préparation requête : " . $conn->error);
        }

        $stmt->bind_param(
            "sississssisi",
            $logo,
            $id_client,
            $nom,
            $adresse,
            $id_contrat,
            $ladate,
            $start,
            $end,
            $tech,
            $facturable,
            $descr,
            $fiche_id
        );

        if ($stmt->execute()) {
            // Mettre à jour la table inter aussi
            $facturable_int = (int)$facturable;
            $stmt_inter = $conn->prepare("UPDATE inter
                                           SET id_client=?, id_contrat=?, ladate=?, tech=?, facturable=?
                                           WHERE id=?");
            $stmt_inter->bind_param("isssii", $id_client, $id_contrat, $ladate, $tech, $facturable_int, $fiche_id);
            $stmt_inter->execute();
            $stmt_inter->close();

            $feedback = "<p style='color:green;'>Fiche modifiée avec succès !</p>";
            header("Location: intervention.php");
            exit;
        } else {
            $feedback = "<p style='color:red;'>Erreur lors de la modification : " . $stmt->error . "</p>";
        }
    } else {
        // MODE CRÉATION
        $sign = 0;
        $saved = 0;

        $stmt = $conn->prepare("INSERT INTO fiches_test (logo, id_client, nom, adresse, id_contrat, ladate, start, end, tech, facturable, descr_inter, sign, saved)
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            die("Erreur préparation requête : " . $conn->error);
        }

        $stmt->bind_param(
            "sississssisii",
            $logo,
            $id_client,
            $nom,
            $adresse,
            $id_contrat,
            $ladate,
            $start,
            $end,
            $tech,
            $facturable,
            $descr,
            $sign,
            $saved
        );

        if ($stmt->execute()) {
            $new_fiche_id = $conn->insert_id;

            // Insérer dans la table inter
            $facturable_int = (int)$facturable;
            $hasfile = 0;
            $facture = 0;

            $stmt_inter = $conn->prepare("INSERT INTO inter (id_client, id_contrat, ladate, hasfile, tech, facturable, facture)
                                           VALUES (?, ?, ?, ?, ?, ?, ?)");
            if (!$stmt_inter) {
                die("Erreur préparation requête inter : " . $conn->error);
            }

            $stmt_inter->bind_param(
                "isssiii",
                $id_client,
                $id_contrat,
                $ladate,
                $hasfile,
                $tech,
                $facturable_int,
                $facture
            );

            if ($stmt_inter->execute()) {
                $feedback = "<p style='color:green;'>Fiche enregistrée avec succès !</p>";
            } else {
                $feedback = "<p style='color:red;'>Erreur lors de l'enregistrement dans inter : " . $stmt_inter->error . "</p>";
            }
            $stmt_inter->close();
        } else {
            $feedback = "<p style='color:red;'>Erreur lors de l'enregistrement : " . $stmt->error . "</p>";
        }
    }
}

// Message de succès après redirection
if (isset($_GET['success']) && $_GET['success'] === 'modified') {
    $feedback = "<p style='color:green;'>Fiche modifiée avec succès !</p>";
}

// Récupérer les fiches des dernières 24h (uniquement en mode création)
$fiches_recents = [];
if ($mode === 'create') {
    $sql_24h = "
        SELECT f.id, f.nom, f.ladate, f.start, c.nom AS client_nom
        FROM fiches_test f
        INNER JOIN inter i ON f.id = i.id
        LEFT JOIN clients c ON f.id_client = c.id
        WHERE f.ladate >= DATE_SUB(NOW(), INTERVAL 1 DAY)
        ORDER BY f.ladate DESC, f.start DESC
    ";
    $res = $conn->query($sql_24h);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $fiches_recents[] = $row;
        }
    }
}

?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <link rel="stylesheet" href="style.css">
    <meta charset="UTF-8">
    <title><?= $mode === 'edit' ? 'Modifier' : 'Nouvelle' ?> Fiche Intervention</title>
    <style>
        body {
            font-family: Arial;
            background: #f4f4f4;
            padding: 20px;
        }

        .container {
            background: white;
            padding: 20px;
            max-width: 800px;
            margin: auto;
            border-radius: 10px;
            box-shadow: 0 0 10px #ccc;
        }

        h2 {
            background: <?= $mode === 'edit' ? '#ffc107' : '#007BFF' ?>;
            color: white;
            padding: 10px;
            border-radius: 5px;
        }

        .section {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-top: 10px;
        }

        input,
        select,
        textarea {
            width: 100%;
            padding: 8px;
            margin-top: 5px;
            border: 1px solid #ccc;
            border-radius: 5px;
            box-sizing: border-box;
        }

        button {
            background: #28a745;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
        }

        button:hover {
            background: #218838;
        }
    </style>
</head>

<body>
    <div class="container">
        <?php if ($mode === 'create' && !empty($fiches_recents)) : ?>
            <h2 style="background:#17a2b8;">Fiches créées dans les dernières 24h</h2>
            <table style="width:100%; border-collapse: collapse; margin-bottom: 30px;">
                <thead>
                    <tr style="background: #007BFF; color: white;">
                        <th style="padding:10px; border:1px solid #ccc;">Client</th>
                        <th style="padding:10px; border:1px solid #ccc;">Date / Heure</th>
                        <th style="padding:10px; border:1px solid #ccc;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($fiches_recents as $fiche_item) : ?>
                        <tr style="background: #f9f9f9;">
                            <td style="padding:10px; border:1px solid #ccc;"><?= htmlspecialchars($fiche_item['client_nom'] ?? $fiche_item['nom']) ?></td>
                            <td style="padding:10px; border:1px solid #ccc;"><?= htmlspecialchars($fiche_item['ladate']) ?> <?= htmlspecialchars($fiche_item['start']) ?></td>
                            <td style="padding:10px; border:1px solid #ccc;">
                                <a href="newfit.php?id=<?= $fiche_item['id'] ?>">Modifier</a> |
                                <a href="signature.php?id=<?= $fiche_item['id'] ?>">Signer</a> |
                                <a href="supprimer_fiche.php?id=<?= $fiche_item['id'] ?>" onclick="return confirm('Supprimer cette fiche ?')">Supprimer</a>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        <?php elseif ($mode === 'create') : ?>
            <p style="background:#ffc107; padding:10px; border-radius:5px;">Aucune fiche créée dans les 24 dernières heures.</p>
        <?php endif ?>

        <h2><?= $mode === 'edit' ? "Modifier la Fiche d'Intervention #$fiche_id" : "Nouvelle Fiche d'Intervention" ?></h2>
        <?= $feedback ?>

        <form method="POST" autocomplete="off" onsubmit="return validateForm()">
            <?php if ($mode === 'edit') : ?>
                <input type="hidden" name="fiche_id" value="<?= $fiche_id ?>">
            <?php endif; ?>

            <div class="section">
                <label for="logo">Logo :</label>
                <select name="logo" id="logo" required onchange="updateLogo()">
                    <option value="">-- Sélectionnez un logo --</option>
                    <option value="colorac" <?= ($fiche && $fiche['logo'] === 'colorac') ? 'selected' : '' ?>>Coloracademy</option>
                    <option value="pommes" <?= ($fiche && $fiche['logo'] === 'pommes') ? 'selected' : '' ?>>Pommes</option>
                    <option value="adc" <?= ($fiche && $fiche['logo'] === 'adc') ? 'selected' : '' ?>>Une Affaire De Couleurs</option>
                    <option value="aucun" <?= ($fiche && $fiche['logo'] === 'aucun') ? 'selected' : '' ?>>Aucun</option>
                </select>
                <div id="logoPreview" style="margin-top: 10px;">
                    <!-- L'image s'affichera ici -->
                </div>
            </div>

            <div class="section">
                <label>Nom du client :</label>
                <input list="clients" name="nom" id="nom" value="<?= $fiche ? htmlspecialchars($client_nom) : '' ?>" oninput="fetchAdresse()" required>
                <datalist id="clients">
                    <?php
                    $res = $conn->query("SELECT id, nom FROM clients ORDER BY nom");
                    while ($row = $res->fetch_assoc()) {
                        $nom_client = htmlspecialchars($row['nom'], ENT_QUOTES);
                        echo "<option value=\"$nom_client\" data-id=\"{$row['id']}\"></option>";
                    }
                    ?>
                </datalist>
                <input type="hidden" name="id_client" id="id_client" value="<?= $fiche ? $fiche['id_client'] : '' ?>" required>

                <label>Adresse :</label>
                <input type="text" name="adresse" id="adresse" value="<?= $fiche ? htmlspecialchars($fiche['adresse']) : '' ?>" readonly required>
            </div>

            <div class="section">
                <label>Date :</label>
                <input type="date" name="ladate" value="<?= $fiche ? $fiche['ladate'] : date('Y-m-d') ?>" required>
                <label>Arrivée :</label>
                <input type="time" name="start" value="<?= $fiche ? $fiche['start'] : '09:30' ?>" required>
                <label>Départ :</label>
                <input type="time" name="end" value="<?= $fiche ? $fiche['end'] : date('H:i') ?>" required>
            </div>

            <div class="section">
                <label>Technicien :</label>
                <select name="tech" required>
                    <option value="">-- Choisir un technicien --</option>
                    <option <?= ($fiche && $fiche['tech'] === 'Laurent') ? 'selected' : '' ?>>Laurent</option>
                    <option <?= ($fiche && $fiche['tech'] === 'Yann') ? 'selected' : '' ?>>Yann</option>
                    <option <?= ($fiche && $fiche['tech'] === 'Thuyer') ? 'selected' : '' ?>>Thuyer</option>
                    <option <?= ($fiche && $fiche['tech'] === 'Olivier') ? 'selected' : '' ?>>Olivier</option>
                </select>

                <label>Facturable :</label>
                <select name="facturable" id="facturable" onchange="toggleContrat()" required>
                    <option value="1" <?= ($fiche && $fiche['facturable'] == 1) ? 'selected' : '' ?>>Oui</option>
                    <option value="0" <?= ($fiche && $fiche['facturable'] == 0) ? 'selected' : '' ?>>Non</option>
                </select>

                <div id="contratSection" style="display:<?= ($fiche && $fiche['facturable'] == 0) ? 'block' : 'none' ?>; margin-top:10px;">
                    <label>Contrat :</label>
                    <select name="id_contrat" id="id_contrat">
                        <?php if ($fiche && $fiche['id_contrat'] > 0): ?>
                            <option value="<?= $fiche['id_contrat'] ?>" selected>Contrat actuel</option>
                        <?php endif; ?>
                    </select>
                </div>
            </div>

            <div class="section">
                <label>Intervention :</label>
                <textarea name="descr_inter" rows="15" required><?= $fiche ? htmlspecialchars($fiche['descr_inter']) : '' ?></textarea>
            </div>

            <div style="margin-top: 20px;">
                <button type="submit"><?= $mode === 'edit' ? 'Enregistrer les modifications' : 'Valider' ?></button>
                <button type="button" onclick="window.location.href='intervention.php'" style="background: #dc3545; margin-left: 10px;">Annuler</button>
            </div>
        </form>
    </div>

    <script>
        const MODE = '<?= $mode ?>';
        const CURRENT_CONTRAT = <?= ($fiche && $fiche['id_contrat']) ? $fiche['id_contrat'] : 0 ?>;

        window.onload = function() {
            updateLogo();
            if (document.getElementById('facturable').value === '0') {
                toggleContrat();
            }
        };

        function fetchAdresse() {
            let input = document.getElementById('nom');
            let datalist = document.getElementById('clients');
            let value = input.value.trim();
            let options = datalist.options;
            let clientId = '';

            for (let i = 0; i < options.length; i++) {
                if (options[i].value === value) {
                    clientId = options[i].getAttribute('data-id');
                    break;
                }
            }

            if (!clientId) {
                document.getElementById('adresse').value = '';
                document.getElementById('id_client').value = '';
                toggleContrat();
                return;
            }

            document.getElementById('id_client').value = clientId;

            fetch('newfit.php?client_id=' + clientId)
                .then(res => res.json())
                .then(data => {
                    if (data && data.adresse) {
                        document.getElementById('adresse').value = data.adresse;
                    } else {
                        document.getElementById('adresse').value = '';
                    }
                    toggleContrat();
                }).catch(() => {
                    document.getElementById('adresse').value = '';
                    toggleContrat();
                });
        }

        function toggleContrat() {
            let facturable = document.getElementById('facturable').value;
            let idClient = document.getElementById('id_client').value;
            let section = document.getElementById('contratSection');
            let select = document.getElementById('id_contrat');

            if (facturable === "0" && idClient) {
                section.style.display = 'block';
                fetch('newfit.php?contrats=' + idClient)
                    .then(res => res.json())
                    .then(data => {
                        select.innerHTML = '';
                        if (data.length === 0) {
                            let opt = document.createElement('option');
                            opt.text = "Aucun contrat actif";
                            opt.value = "";
                            select.appendChild(opt);
                        } else {
                            data.forEach(c => {
                                let opt = document.createElement('option');
                                opt.value = c.id;
                                opt.text = c.type;
                                if (MODE === 'edit' && c.id == CURRENT_CONTRAT) {
                                    opt.selected = true;
                                }
                                select.appendChild(opt);
                            });
                        }
                    }).catch(() => {
                        select.innerHTML = '<option value="">Erreur chargement</option>';
                    });
            } else {
                section.style.display = 'none';
                select.innerHTML = '';
            }
        }

        function validateForm() {
            const id_client = document.getElementById('id_client').value;
            if (!id_client) {
                alert("Veuillez sélectionner un client valide dans la liste.");
                return false;
            }
            return true;
        }

        function updateLogo() {
            const select = document.getElementById("logo");
            const preview = document.getElementById("logoPreview");
            const value = select.value;

            let imgSrc = "";
            switch (value) {
                case "colorac":
                    imgSrc = "img/colorac_logo_small.png";
                    break;
                case "pommes":
                    imgSrc = "img/pommes_logo_small.png";
                    break;
                case "adc":
                    imgSrc = "img/adc_logo_small.png";
                    break;
                case "aucun":
                    imgSrc = "img/aucun.png";
                    break;
                default:
                    preview.innerHTML = "";
                    return;
            }

            preview.innerHTML = `<img src="${imgSrc}" alt="Logo sélectionné" style="max-height: 100px;">`;
        }
    </script>
</body>

</html>