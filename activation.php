<?php
require_once __DIR__ . '/auth.php';
requireLogin();
?>
<?php
include 'navbar.php';
require_once __DIR__ . '/OVHEmailService.php';

// Vérifier connexion
if (!$conn) {
    die("Erreur de connexion : " . mysqli_connect_error());
}

// --- FONCTION D'ENVOI MAIL ---
function sendActivationMail($nom_client, $id_activation)
{
    try {
        $emailService = new OVHEmailService();

        return $emailService->sendActivationEmail(
            $nom_client,
            '123'
        );
    } catch (Exception $e) {
        error_log("Erreur envoi email activation : " . $e->getMessage());
        return false;
    }
}

// --- TRAITEMENT DU FORMULAIRE D'AJOUT ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nom_client'], $_POST['editeur'], $_POST['type'])) {
    $nom_client = trim($_POST['nom_client']);
    $editeur = trim($_POST['editeur']);
    $type = trim($_POST['type']);

    // --- Récupérer ou créer le client ---
    $id_client = 0;
    $stmt = $conn->prepare("SELECT id FROM clients WHERE nom = ?");
    $stmt->bind_param("s", $nom_client);
    $stmt->execute();
    $stmt->bind_result($id_client);
    $stmt->fetch();
    $stmt->close();

    if ($id_client == 0) {
        $stmt = $conn->prepare("INSERT INTO clients (nom) VALUES (?)");
        $stmt->bind_param("s", $nom_client);
        $stmt->execute();
        $id_client = $stmt->insert_id;
        $stmt->close();
    }

    // --- Pièce jointe ---
    $pj_file = '';
    if (isset($_FILES['pj']) && $_FILES['pj']['error'] == 0) {
        $upload_dir = "data/activation/$id_client/";
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

        $pj_file = basename($_FILES['pj']['name']);
        $target_file = $upload_dir . $pj_file;
        move_uploaded_file($_FILES['pj']['tmp_name'], $target_file);
    }

    // --- Forcer les types ---
    $id_client   = (int)$id_client;
    $editeur     = (string)$editeur;
    $type        = (string)$type;
    $pj_file     = (string)$pj_file;
    $state       = 0;
    $crea_auteur = '';
    $close_auteur = '';

    // --- Insertion sécurisée ---
    $stmt = $conn->prepare("INSERT INTO activation (id_client, editeur, type, pj, state, crea_date, crea_auteur, close_date, close_auteur) VALUES (?, ?, ?, ?, ?, NOW(), ?, '', '')");
    if ($stmt === false) die("Erreur prepare INSERT activation: " . $conn->error);
    $stmt->bind_param("isssis", $id_client, $editeur, $type, $pj_file, $state, $crea_auteur);
    $stmt->execute();
    $stmt->close();

    header("Location: ".$_SERVER['PHP_SELF']);
    exit;
}

// --- GESTION DE LA VUE ---
$vue = isset($_GET['vue']) ? $_GET['vue'] : 'attente';

// --- Actions ---
if (isset($_GET['action'], $_GET['id'])) {
    $id = intval($_GET['id']);
    switch ($_GET['action']) {
        case 'cloturer':
            $stmt = $conn->prepare("UPDATE activation SET state = 1, close_date = NOW() WHERE id = ?");
            break;
        case 'reactiver':
            $stmt = $conn->prepare("UPDATE activation SET state = 0, close_date = '', close_auteur = '' WHERE id = ?");
            break;
        case 'supprimer':
            $stmt = $conn->prepare("DELETE FROM activation WHERE id = ?");
            break;
        case 'sendmail':
            // ✅ Récupérer le nom du client avant d'envoyer l'email
            $stmt_client = $conn->prepare("SELECT clients.nom FROM activation JOIN clients ON activation.id_client = clients.id WHERE activation.id = ?");
            $stmt_client->bind_param("i", $id);
            $stmt_client->execute();
            $stmt_client->bind_result($nom_client);
            $stmt_client->fetch();
            $stmt_client->close();

            // ✅ Envoyer l'email
            if (!empty($nom_client)) {
                sendActivationMail($nom_client, $id);
            }
            header("Location: ".$_SERVER['PHP_SELF']."?vue=$vue");
            exit;
        default:
            $stmt = null;
    }
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: ".$_SERVER['PHP_SELF']."?vue=$vue");
    exit;
}

// --- REQUÊTE PRINCIPALE ---
$sql = "SELECT activation.*, clients.nom AS nom_client
        FROM activation
        JOIN clients ON activation.id_client = clients.id";

if ($vue === 'termine') $sql .= " WHERE activation.state = 1";
elseif ($vue === 'attente') $sql .= " WHERE activation.state = 0";

$result = $conn->query($sql);

// --- Récupérer tous les clients pour le datalist ---
$clients_options = '';
$res_clients = $conn->query("SELECT nom FROM clients ORDER BY nom ASC");
while($c = $res_clients->fetch_assoc()) {
    $clients_options .= "<option value=\"" . htmlspecialchars($c['nom']) . "\"></option>";
}

// --- Récupérer tous les éditeurs pour le datalist ---
$editeurs_options = '';
$res_editeurs = $conn->query("SELECT DISTINCT editeur FROM lic ORDER BY editeur ASC");
while($e = $res_editeurs->fetch_assoc()) {
    $editeurs_options .= "<option value=\"" . htmlspecialchars($e['editeur']) . "\"></option>";
}

// --- TITRE ---
$titre = match ($vue) {
    'termine' => "VUE : Activations Terminées",
    'attente' => "VUE : Activations en Attente",
    default => "VUE : Toutes les Activations"
};
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<link rel="stylesheet" href="style.css">
<meta charset="UTF-8">
<title>Liste des Activations</title>
<style>
body { font-family: Arial, sans-serif; margin:0; padding:0;}
.content { margin-top:70px; padding:20px; }
h2 { display:flex; justify-content:space-between; align-items:center; }
.vue-buttons a { display:inline-block; margin-right:10px; padding:8px 16px; background-color:#007BFF; color:white; text-decoration:none; border-radius:4px; }
.vue-buttons a:hover { background-color:#0056b3; }
table { width:100%; border-collapse:collapse; margin-top:10px; }
th, td { padding:12px; border:1px solid #ddd; text-align:left; }
th { background-color:#f4f4f4; }
tr:nth-child(even) { background-color:#f9f9f9; }
button { padding:6px 12px; border:none; border-radius:4px; cursor:pointer; margin:2px; }
a.client-link { color:#007BFF; text-decoration:none; }
a.client-link:hover { text-decoration:underline; }
.btn-download { background-color:#28a745; color:white; }
.btn-download:hover { background-color:#218838; }
.btn-mail { background-color:#17a2b8; color:white; }
.btn-mail:hover { background-color:#117a8b; }
.btn-cloturer { background-color:#ffc107; color:black; }
.btn-cloturer:hover { background-color:#e0a800; }
.btn-reactiver { background-color:#007bff; color:white; }
.btn-reactiver:hover { background-color:#0056b3; }
.btn-supprimer { background-color:#dc3545; color:white; }
.btn-supprimer:hover { background-color:#c82333; }
#btnAddActivation { padding:4px 8px; font-size:12px; background-color:#28a745; color:white; border:none; border-radius:4px; cursor:pointer; }
#modalAdd { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); justify-content:center; align-items:center; }
#modalAdd div { background:white; padding:20px; border-radius:8px; width:400px; max-width:90%; box-sizing:border-box; position:relative; }
#closeModal { position:absolute; top:10px; right:15px; cursor:pointer; font-weight:bold; }
input[type=text], input[type=file], select { width:100%; box-sizing:border-box; padding:6px; margin-bottom:10px; }
</style>
</head>
<body>

<div class="content">
    <h2>
        <?php echo $titre; ?>
        <button id="btnAddActivation">Ajouter</button>
    </h2>

    <div class="vue-buttons">
        <a href="?vue=tout">Tout</a>
        <a href="?vue=termine">Terminé</a>
        <a href="?vue=attente">En attente</a>
    </div>

    <?php
    if ($result->num_rows > 0) {
        echo "<table>
            <tr>
                <th>Etat</th>
                <th>Client</th>
                <th>Éditeur / Type</th>
                <th>PJ</th>
                <th>Créa date / Auteur</th>
                <th>Close date / Auteur</th>
                <th>Action</th>
            </tr>";

        while ($row = $result->fetch_assoc()) {
            $pj_file = htmlspecialchars($row["pj"]);
            $id_client = intval($row["id_client"]);
            $file_path = "data/activation/$id_client/$pj_file";
            $pj_button = (!empty($pj_file) && file_exists($file_path))
                ? "<a href='$file_path' download><button class='btn-download'>Télécharger</button></a>"
                : "Aucun fichier";

            if ($row["state"] == 0) {
                $action_buttons = "
                    <a href='?action=sendmail&id={$row['id']}'><button class='btn-mail'>Mail</button></a>
                    <a href='?action=cloturer&id={$row['id']}'><button class='btn-cloturer'>Clôturer</button></a>
                    <a href='?action=supprimer&id={$row['id']}' onclick=\"return confirm('Êtes-vous sûr de vouloir supprimer cette activation ?');\">
                        <button class='btn-supprimer'>🗑</button>
                    </a>";
            } else {
                $action_buttons = "
                    <a href='?action=reactiver&id={$row['id']}'><button class='btn-reactiver'>Réactiver</button></a>
                    <a href='?action=supprimer&id={$row['id']}' onclick=\"return confirm('Êtes-vous sûr de vouloir supprimer cette activation ?');\">
                        <button class='btn-supprimer'>🗑</button>
                    </a>";
            }

            $client_link = "http://localhost/client.php?client_name=" . urlencode($row["nom_client"]);

            echo "<tr>
                <td>" . ($row["state"] == 1 ? "Terminé" : "En attente") . "</td>
                <td><a class='client-link' href='$client_link'>" . htmlspecialchars($row["nom_client"]) . "</a></td>
                <td>" . htmlspecialchars($row["editeur"]) . " / " . htmlspecialchars($row["type"]) . "</td>
                <td>$pj_button</td>
                <td>" . htmlspecialchars($row["crea_date"]) . " / " . htmlspecialchars($row["crea_auteur"]) . "</td>
                <td>" . htmlspecialchars($row["close_date"]) . " / " . htmlspecialchars($row["close_auteur"]) . "</td>
                <td>$action_buttons</td>
            </tr>";
        }

        echo "</table>";
    } else {
        echo "<p>Aucune activation trouvée pour cette vue.</p>";
    }

    $conn->close();
    ?>
</div>

<!-- MODALE AJOUT -->
<div id="modalAdd">
    <div>
        <span id="closeModal">&times;</span>
        <h3>Ajouter une activation</h3>
        <form method="post" enctype="multipart/form-data">
            <!-- Nom client -->
            <label>Nom client</label>
            <input type="text" name="nom_client" list="clientsList" required>
            <datalist id="clientsList">
                <?php echo $clients_options; ?>
            </datalist>

            <!-- Éditeur -->
            <label>Éditeur</label>
            <input type="text" name="editeur" list="editeursList" required>
            <datalist id="editeursList">
                <?php echo $editeurs_options; ?>
            </datalist>

            <!-- Type -->
            <label>Type</label>
            <select name="type" required>
                <option value="Définitive">Définitive</option>
                <option value="Temporaire">Temporaire</option>
                <option value="NFR">NFR</option>
            </select>

            <!-- Pièce jointe -->
            <label>Pièce jointe</label><input type="file" name="pj">
            <button type="submit">Ajouter</button>
        </form>
    </div>
</div>

<script>
document.getElementById('btnAddActivation').addEventListener('click', function() {
    document.getElementById('modalAdd').style.display='flex';
});
document.getElementById('closeModal').addEventListener('click', function() {
    document.getElementById('modalAdd').style.display='none';
});
window.addEventListener('click', function(e){
    if(e.target==document.getElementById('modalAdd')) document.getElementById('modalAdd').style.display='none';
});
</script>

</body>
</html>