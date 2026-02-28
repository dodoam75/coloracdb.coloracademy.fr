<?php
require_once __DIR__ . '/auth.php';
requireLogin();
?>
<?php
ini_set('display_errors', 1);
ob_start();
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Inclure la connexion à la base de données
require_once 'db_config.php';


// === ENSUITE : TOUS LES HEADERS ET REDIRECTIONS (avant tout HTML) ===

// Recherche client (insensible à la casse)
$client_name = $_GET['client_name'] ?? '';
$client = null;
if ($client_name) {
    $client_name_escaped = $conn->real_escape_string($client_name);
    $sql = "SELECT * FROM clients WHERE LOWER(nom) = LOWER('$client_name_escaped') LIMIT 1";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $client = $result->fetch_assoc();
    }
}

if (isset($_GET['delete_client'])) {
    $id_client = intval($_GET['delete_client']);
    $stmt = $conn->prepare("DELETE FROM clients WHERE id = ?");
    $stmt->bind_param("i", $id_client);
    $stmt->execute();
    header("Location: client.php?deleted=1");
    exit;
}

$page = $_GET['page'] ?? 'infos';

// === GESTION DES REDIRECTIONS ORDINATEURS (AVANT TOUT CONTENU HTML) ===
if ($page === 'ordinateurs' && isset($client)) {
    $client_id = $client['id'];

    // Gestion du formulaire POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
        $id = intval($_POST['id']);
        $nom           = $_POST['nom'] ?? '';
        $marque        = $_POST['marque'] ?? '';
        $os            = $_POST['os'] ?? '';
        $version_os    = $_POST['version_os'] ?? '';
        $serial        = $_POST['serial'] ?? '';
        $emplacement   = $_POST['emplacement'] ?? '';
        $ip            = $_POST['ip'] ?? '';
        $mac           = $_POST['mac'] ?? '';
        $date_install  = $_POST['date_install'] ?? null;
        $garantie      = ($_POST['garantie'] !== '') ? intval($_POST['garantie']) : null;
        $ext_garantie  = ($_POST['ext_garantie'] !== '') ? intval($_POST['ext_garantie']) : 0;
        $fourni_ca     = isset($_POST['fourni_ca']) ? $_POST['fourni_ca'] : 'non';
        $notes         = $_POST['notes'] ?? '';
        $modif_date    = date("Y-m-d H:i:s");
        $modif_auteur = htmlspecialchars($_SESSION['username']);

        $sql = "UPDATE ordis SET
            nom = ?, marque = ?, os = ?, version_os = ?, serial = ?, emplacement = ?, ip = ?, mac = ?,
            date_install = ?, garantie = ?, ext_garantie = ?, fourni_ca = ?, notes = ?, modif_date = ?, modif_auteur = ?
        WHERE id = ? AND id_client = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "sssssssssiiisssii",
            $nom, $marque, $os, $version_os, $serial, $emplacement, $ip, $mac,
            $date_install, $garantie, $ext_garantie, $fourni_ca, $notes, $modif_date, $modif_auteur,
            $id, $client_id
        );
        $stmt->execute();
        $stmt->close();

        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }

    // Vérification de la redirection vers le premier ordinateur
    $res = $conn->query("SELECT * FROM ordis WHERE id_client = $client_id ORDER BY id");
    $ordinateurs = [];
    while ($row = $res->fetch_assoc()) {
        $ordinateurs[] = $row;
    }

    if (!empty($ordinateurs) && !isset($_GET['id'])) {
        $first_id = $ordinateurs[0]['id'];
        header("Location: ?client_name=" . urlencode($client_name) . "&page=ordinateurs&id=" . $first_id);
        exit;
    }
}

// === MAINTENANT ON PEUT AFFICHER DU CONTENU HTML ===
include 'navbar.php';
?>

<head>
    <link rel="stylesheet" href="style.css">
    <meta charset="UTF-8">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <title>Client</title>
    <style>
        body {
            display: flex;
            margin: 0;
            font-family: Arial, sans-serif;
        }

        .sidebar {
            width: 250px;
            background: #3498db;
            color: white;
            padding: 50px 20px 20px;
            min-height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            overflow-y: auto;
            max-height: 100vh;
            z-index: 1000;
            box-sizing: border-box;
}

        .sidebar a {
            display: block;
            padding: 10px 15px;
            margin: 5px 0;
            background: #2980b9;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background 0.3s;
        }

        .sidebar a:hover {
            background: #1f618d;
        }

        .main-container {
            flex: 1;
            padding: 50px 20px 20px;
            margin-left: 250px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            font-size: 18px;
            text-align: left;
        }

        th, td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
        }

        th {
            background: #f2f2f2;
        }

        h2 {
            margin-bottom: 20px;
        }

        .search-container {
            text-align: center;
            position: relative;
            margin-bottom: 30px;
        }

        .search-container h2 {
            font-size: 20px;
            margin-bottom: 15px;
        }

        .search-container input[type="text"] {
            width: 100%;
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #ddd;
            font-size: 14px;
            box-sizing: border-box;
        }

        .search-container button {
            width: 100%;
            padding: 10px;
            font-size: 14px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 8px;
        }

        .search-container button:hover {
            background: #0056b3;
        }

        .suggestions {
            border: 1px solid #ddd;
            max-height: 250px;
            overflow-y: auto;
            background-color: white;
            position: absolute;
            width: 100%;
            z-index: 1000;
            top: 100%;
            left: 0;
            margin-top: 5px;
            display: none;
            border-radius: 5px;
            box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.2);
        }

        .suggestion-item {
            padding: 10px;
            cursor: pointer;
            text-align: left;
            border-bottom: 1px solid #eee;
            color: #333;
            transition: background 0.2s;
        }

        .suggestion-item:hover {
            background-color: #f1f1f1;
        }

        .suggestion-item:last-child {
            border-bottom: none;
        }

        .delete-btn {
            margin-top: 20px;
        }

        .delete-btn a {
            padding: 10px 20px !important;
            background: #e74c3c !important;
            margin: 0 !important;
            display: inline-block;
        }

        .delete-btn a:hover {
            background: #c0392b !important;
        }

        .unsaved-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 9999;
    animation: fadeIn 0.3s ease;
}

.unsaved-modal {
    background: white;
    border-radius: 10px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
    max-width: 450px;
    width: 90%;
    overflow: hidden;
    animation: slideUp 0.3s ease;
}

.unsaved-modal-header {
    background: #fff3cd;
    color: #856404;
    padding: 20px;
    font-weight: 600;
    font-size: 16px;
    border-bottom: 1px solid #ffeaa7;
}

.unsaved-modal-body {
    padding: 20px;
    color: #333;
    line-height: 1.6;
}

.unsaved-modal-body p {
    margin: 0;
}

.unsaved-modal-footer {
    display: flex;
    gap: 10px;
    padding: 20px;
    border-top: 1px solid #eee;
    justify-content: flex-end;
}

.unsaved-btn {
    padding: 10px 20px;
    border: none;
    border-radius: 5px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 14px;
}

.unsaved-btn-secondary {
    background: #6c757d;
    color: white;
}

.unsaved-btn-secondary:hover {
    background: #5a6268;
}

.unsaved-btn-danger {
    background: #dc3545;
    color: white;
}

.unsaved-btn-danger:hover {
    background: #c82333;
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

@keyframes slideUp {
    from {
        transform: translateY(30px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

@media (max-width: 600px) {
    .unsaved-modal {
        max-width: 95%;
    }

    .unsaved-modal-footer {
        flex-direction: column-reverse;
    }

    .unsaved-btn {
        width: 100%;
    }
}
    </style>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<script>
(function() {
    let formModified = false;

    // Détecter les modifications dans les formulaires
    function initFormTracking() {
        // Infos client
        const infosForm = document.querySelector('form[method="POST"]');
        if (infosForm && infosForm.closest('form')?.querySelector('[name="nom"]')) {
            const inputs = infosForm.querySelectorAll('input, textarea, select');
            inputs.forEach(input => {
                input.addEventListener('change', function() {
                    formModified = true;
                });
                input.addEventListener('input', function() {
                    formModified = true;
                });
            });
        }

        // Tous les autres formulaires
        document.querySelectorAll('form').forEach(form => {
            const inputs = form.querySelectorAll('input, textarea, select');
            inputs.forEach(input => {
                // Garder la valeur initiale
                input.dataset.initialValue = input.value;

                input.addEventListener('change', function() {
                    if (this.value !== this.dataset.initialValue) {
                        formModified = true;
                    }
                });
                input.addEventListener('input', function() {
                    if (this.value !== this.dataset.initialValue) {
                        formModified = true;
                    }
                });
            });

            // Détecter la soumission du formulaire
            form.addEventListener('submit', function() {
                formModified = false;
            });
        });
    }

    // Avertissement personnalisé avant de changer de page/onglet
    document.addEventListener('DOMContentLoaded', function() {
        initFormTracking();

        // Intercepter tous les liens internes
        document.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', function(e) {
                // Ignorer les liens de téléchargement, ancres, et les liens externes
                if (this.hasAttribute('download') ||
                    this.href.startsWith('#') ||
                    this.target === '_blank' ||
                    !this.href.includes(window.location.hostname)) {
                    return;
                }

                if (formModified) {
                    e.preventDefault();

                    const modal = document.createElement('div');
                    modal.className = 'unsaved-modal-overlay';
                    modal.innerHTML = `
                        <div class="unsaved-modal">
                            <div class="unsaved-modal-header">
                                ⚠️ Modifications non enregistrées
                            </div>
                            <div class="unsaved-modal-body">
                                <p>Vous avez des modifications non enregistrées. Êtes-vous sûr de vouloir quitter cette page?</p>
                                <p style="font-size: 12px; color: #666; margin-top: 10px;">Cliquez sur "Enregistrer et quitter" pour sauvegarder avant de partir.</p>
                            </div>
                            <div class="unsaved-modal-footer">
                                <button class="unsaved-btn unsaved-btn-secondary" onclick="this.closest('.unsaved-modal-overlay').remove()">
                                    Rester sur la page
                                </button>
                                <button class="unsaved-btn unsaved-btn-danger" onclick="document.location.href='${this.href}'">
                                    Quitter sans enregistrer
                                </button>
                            </div>
                        </div>
                    `;

                    document.body.appendChild(modal);
                }
            });
        });
    });
})();
</script>
<body>
    <div class="sidebar">
        <div class="search-container">
            <h2 style="color: black;">Chercher un client</h2>
            <form action="client.php" method="GET" id="searchForm">
                <input type="text" id="client_name" name="client_name" placeholder="Nom du client..." value="<?= htmlspecialchars($client_name) ?>" autocomplete="off">
                <div class="suggestions" id="suggestions"></div>
                <button type="submit">Recherche</button>
            </form>
        </div>

        <?php foreach (
            [
                'dashboard' => "Vue d'ensemble",
                'infos' => 'Informations client',
                'ordinateurs' => 'Ordinateurs',
                'rips' => 'Logiciels',
                'imprimantes' => 'Imprimantes',
                'licences' => 'Licences',
                'papiers' => 'Papiers',
                'stockage' => 'Stockage',
                'interventions' => 'Interventions'
            ] as $key => $label
        ): ?>
            <a href="?client_name=<?= urlencode($client_name) ?>&page=<?= $key ?>"><?= $label ?></a>
        <?php endforeach; ?>

        <?php if ($client): ?>
            <div class="delete-btn">
                <a href="client.php?delete_client=<?= $client['id'] ?>"
                   onclick="return confirm('⚠️ Voulez-vous vraiment supprimer ce client et toutes ses données ? Cette action est irréversible.');">
                    Supprimer le client
                </a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        $(document).ready(function() {
            // Écouteur d'événement sur le champ de recherche
            $('#client_name').on('input', function() {
                var query = $(this).val();
                if (query.length > 0) {
                    $.ajax({
                        url: 'search_client.php',
                        method: 'POST',
                        data: { query: query },
                        success: function(data) {
                            $('#suggestions').html(data);
                            $('#suggestions').fadeIn();
                        }
                    });
                } else {
                    $('#suggestions').fadeOut();
                }
            });

            // Rediriger directement quand on clique sur une suggestion
            $(document).on('click', '.suggestion-item', function() {
                var clientName = $(this).data('name');
                window.location.href = 'client.php?client_name=' + encodeURIComponent(clientName);
            });

            // Fermer les suggestions si on clique ailleurs
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.search-container').length) {
                    $('#suggestions').fadeOut();
                }
            });
        });
    </script>

    <div class="main-container">
        <h2>Client : <?= htmlspecialchars($client_name) ?></h2>

        <?php if (!$client): ?>
            <p>Aucun client trouvé.</p>
        <?php else: ?>
            <?php if ($page === 'dashboard'): ?>
                <h3>Vue d'ensemble</h3>
                <p>Ici c'est l'accueil et j'ai eu la flemme de le coder maintenant, donc attend c'est pas urgent</p>

<?php elseif ($page === 'infos'): ?>
    <style>
        .infos-container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .infos-container h3 {
            color: #2c3e50;
            border-bottom: 3px solid #3498db;
            padding-bottom: 10px;
            margin-bottom: 30px;
        }

        .infos-form {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }

        .info-card {
            flex: 1;
            min-width: 250px;
            background: linear-gradient(135deg, #f9f9f9 0%, #f0f0f0 100%);
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .info-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .info-card h4 {
            color: #34495e;
            margin: 0 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #3498db;
            font-size: 18px;
        }

        .info-card.special {
            background: linear-gradient(135deg, #eaf1f8 0%, #d5e5f5 100%);
        }

        .info-card label {
            display: block;
            margin-bottom: 15px;
            color: #555;
            font-weight: 500;
        }

        .info-card input[type="text"],
        .info-card input[type="email"],
        .info-card textarea {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s, box-shadow 0.3s;
            box-sizing: border-box;
        }

        .info-card input[type="text"]:focus,
        .info-card input[type="email"]:focus,
        .info-card textarea:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .info-card textarea {
            resize: vertical;
            min-height: 80px;
        }

        .radio-group {
            margin: 10px 0;
        }

        .radio-group input[type="radio"] {
            margin-right: 8px;
            cursor: pointer;
        }

        .contrat-status {
            font-size: 20px;
            letter-spacing: 3px;
            padding: 10px;
            background: white;
            border-radius: 6px;
            text-align: center;
            margin: 10px 0;
        }

        .meta-info {
            background: #f8f8f8;
            padding: 8px 12px;
            border-radius: 6px;
            margin: 5px 0;
            font-size: 13px;
            color: #666;
            border-left: 3px solid #3498db;
        }

        .meta-label {
            display: block;
            font-weight: 600;
            color: #34495e;
            margin-top: 15px;
            margin-bottom: 5px;
        }

        .notes-section {
            width: 100%;
            margin-top: 20px;
        }

        .notes-section h4 {
            color: #34495e;
            margin-bottom: 10px;
        }

        .notes-section textarea {
            width: 100%;
            height: 120px;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            resize: vertical;
            box-sizing: border-box;
        }

        .notes-section textarea:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .submit-section {
            width: 100%;
            margin-top: 20px;
            text-align: center;
        }

        .submit-btn {
            padding: 12px 30px;
            font-size: 16px;
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(52, 152, 219, 0.3);
        }

        .submit-btn:hover {
            background: linear-gradient(135deg, #2980b9 0%, #21618c 100%);
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.4);
            transform: translateY(-2px);
        }

        .submit-btn:active {
            transform: translateY(0);
        }

        .no-contrat {
            color: #999;
            font-style: italic;
        }
    </style>

    <div class="infos-container">
        <h3>Informations client</h3>
        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $client['id'];
            $updates = [];

            // Liste des champs à mettre à jour (on exclut les champs de création et modification)
            $allowed_fields = [
                'nom', 'adresse', 'cp', 'ville', 'indications',
                'cc_prenom', 'cc_nom', 'cc_email', 'cc_tel', 'cc_mobile',
                'ct_prenom', 'ct_nom', 'ct_email', 'ct_tel', 'ct_mobile',
                'client_ca', 'notes'
            ];

            foreach ($allowed_fields as $field) {
                if (isset($_POST[$field])) {
                    $value = trim($_POST[$field]);
                    if ($value !== '') {
                        $value = $conn->real_escape_string($value);
                        $updates[] = "$field = '$value'";
                    }
                }
            }

            // Ajouter les informations de modification
            date_default_timezone_set('Europe/Paris');
            $modif_date = date('d/m/Y H:i:s');
            $modif_auteur = htmlspecialchars($_SESSION['username']);

            $updates[] = "modif_date = '$modif_date'";
            $updates[] = "modif_auteur = '$modif_auteur'";

            if ($updates) {
                $conn->query("UPDATE clients SET " . implode(", ", $updates) . " WHERE id = $id");
                $client = $conn->query("SELECT * FROM clients WHERE id = $id")->fetch_assoc();
            }
        }

        // Récupérer les contrats de maintenance du client
        $contrats = $conn->query("SELECT * FROM contrats WHERE id_client = " . $client['id'])->fetch_all(MYSQLI_ASSOC);
        ?>
        <form method="POST" class="infos-form">
            <!-- Coordonnées -->
            <div class="info-card">
                <h4>📍 Coordonnées</h4>
                <label>Société<br><input type="text" name="nom" value="<?= htmlspecialchars($client['nom']) ?>"></label>
                <label>Adresse<br><input type="text" name="adresse" value="<?= htmlspecialchars($client['adresse']) ?>"></label>
                <label>Code postal<br><input type="text" name="cp" value="<?= htmlspecialchars($client['cp']) ?>"></label>
                <label>Ville<br><input type="text" name="ville" value="<?= htmlspecialchars($client['ville']) ?>"></label>
                <label>Indications<br><textarea name="indications"><?= htmlspecialchars($client['indications']) ?></textarea></label>
            </div>

            <!-- Contact commercial -->
            <div class="info-card">
                <h4>💼 Contact commercial</h4>
                <label>Prénom<br><input type="text" name="cc_prenom" value="<?= htmlspecialchars($client['cc_prenom']) ?>"></label>
                <label>Nom<br><input type="text" name="cc_nom" value="<?= htmlspecialchars($client['cc_nom']) ?>"></label>
                <label>Email<br><input type="email" name="cc_email" value="<?= htmlspecialchars($client['cc_email']) ?>"></label>
                <label>Tel<br><input type="text" name="cc_tel" value="<?= htmlspecialchars($client['cc_tel']) ?>"></label>
                <label>Portable<br><input type="text" name="cc_mobile" value="<?= htmlspecialchars($client['cc_mobile']) ?>"></label>
            </div>

            <!-- Contact technique -->
            <div class="info-card">
                <h4>🔧 Contact technique</h4>
                <label>Prénom<br><input type="text" name="ct_prenom" value="<?= htmlspecialchars($client['ct_prenom']) ?>"></label>
                <label>Nom<br><input type="text" name="ct_nom" value="<?= htmlspecialchars($client['ct_nom']) ?>"></label>
                <label>Email<br><input type="email" name="ct_email" value="<?= htmlspecialchars($client['ct_email']) ?>"></label>
                <label>Tel<br><input type="text" name="ct_tel" value="<?= htmlspecialchars($client['ct_tel']) ?>"></label>
                <label>Portable<br><input type="text" name="ct_mobile" value="<?= htmlspecialchars($client['ct_mobile']) ?>"></label>
            </div>

<!-- Informations client -->
            <div class="info-card special">
                <h4>ℹ️ Informations</h4>
                <label><strong>Client</strong>
                    <div class="radio-group">
                        <input type="radio" name="client_ca" value="1" <?= ($client['client_ca'] == 1) ? 'checked' : '' ?>> Color Academy<br>
                        <input type="radio" name="client_ca" value="2" <?= ($client['client_ca'] == 2) ? 'checked' : '' ?>> Pomme's<br>
                        <input type="radio" name="client_ca" value="0" <?= ($client['client_ca'] == 0) ? 'checked' : '' ?>> Autre
                    </div>
                </label>

<span class="meta-label">Contrat(s) de maintenance</span>
<?php
if (empty($contrats)) {
    echo '<div class="contrat-status"><span class="no-contrat">Aucun contrat</span></div>';
} else {
    echo '<div class="contrat-status">';
    $lastContrat = end($contrats);
    echo ($lastContrat['state'] == 1) ? '✅' : '❌';
    echo '</div>';
}
?>

                <span class="meta-label">Création</span>
                <div class="meta-info"><?= htmlspecialchars($client['crea_date']) ?></div>
                <div class="meta-info"><?= htmlspecialchars($client['crea_auteur']) ?></div>

                <span class="meta-label">Modification</span>
                <div class="meta-info"><?= htmlspecialchars($client['modif_date']) ?></div>
                <div class="meta-info"><?= htmlspecialchars($client['modif_auteur']) ?></div>
            </div>

            <!-- Notes -->
            <div class="notes-section">
                <h4>📝 Notes</h4>
                <textarea name="notes"><?= htmlspecialchars($client['notes']) ?></textarea>
            </div>

            <div class="submit-section">
                <button type="submit" class="submit-btn">💾 Sauvegarder</button>
            </div>
        </form>
    </div>
<?php ?>

<?php elseif ($page === 'ordinateurs'): ?>
    <style>
        .ordinateurs-container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .ordinateurs-container h3 {
            color: #2c3e50;
            border-bottom: 3px solid #3498db;
            padding-bottom: 10px;
            margin-bottom: 30px;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-warning {
            background: linear-gradient(135deg, #fff3cd 0%, #ffe69c 100%);
            color: #856404;
            border-left: 4px solid #ffc107;
        }

        .nav-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 0;
            margin-bottom: 30px;
        }

        .nav-item {
            margin-bottom: -2px;
        }

        .nav-item.ms-auto {
            margin-left: auto !important;
        }

        .nav-link {
            padding: 12px 20px;
            color: #555;
            text-decoration: none;
            border-radius: 8px 8px 0 0;
            background: #f5f5f5;
            border: 1px solid #ddd;
            border-bottom: none;
            transition: all 0.3s;
            font-weight: 500;
        }

        .nav-link:hover {
            background: #e8e8e8;
            color: #2c3e50;
        }

        .nav-link.active {
            background: white;
            color: #3498db;
            border-color: #e0e0e0;
            border-bottom: 2px solid white;
            font-weight: 600;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
            text-align: center;
        }

        .btn-success {
            background: linear-gradient(135deg, #28a745 0%, #218838 100%);
            color: white;
            box-shadow: 0 2px 8px rgba(40, 167, 69, 0.3);
        }

        .btn-success:hover {
            background: linear-gradient(135deg, #218838 0%, #1e7e34 100%);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.4);
            transform: translateY(-2px);
        }

        .btn-primary {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            box-shadow: 0 2px 8px rgba(52, 152, 219, 0.3);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #2980b9 0%, #21618c 100%);
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.4);
            transform: translateY(-2px);
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            box-shadow: 0 2px 8px rgba(220, 53, 69, 0.3);
            padding: 8px 16px;
            font-size: 13px;
        }

        .btn-danger:hover {
            background: linear-gradient(135deg, #c82333 0%, #bd2130 100%);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.4);
            transform: translateY(-2px);
        }

        .ordi-form {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        }

        .row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -10px;
        }

        .col-md-4 {
            flex: 0 0 33.333%;
            max-width: 33.333%;
            padding: 0 10px;
        }

        @media (max-width: 992px) {
            .col-md-4 {
                flex: 0 0 100%;
                max-width: 100%;
                margin-bottom: 20px;
            }
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #34495e;
            font-weight: 600;
            font-size: 14px;
        }

        .form-control,
        .form-group select,
        .form-group input[type="text"],
        .form-group input[type="date"],
        .form-group input[type="number"],
        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.3s;
            box-sizing: border-box;
            background: white;
        }

        .form-control:focus,
        .form-group select:focus,
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
            font-family: inherit;
        }

        .form-group select {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23333' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 35px;
        }

        .mt-3 {
            margin-top: 20px;
        }

        .mt-4 {
            margin-top: 30px;
        }

        .button-group {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-top: 25px;
            padding-top: 25px;
            border-top: 2px solid #f0f0f0;
        }

        .column-header {
            color: #3498db;
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e8f4f8;
        }
        input[type="date"] {
  color: #000;
}

input[type="date"]::-webkit-calendar-picker-indicator {
  cursor: pointer;
}
    </style>

    <div class="ordinateurs-container">
        <?php
        $client_id = $client['id'];

        // === Gestion du formulaire d'enregistrement ===
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
            $id = intval($_POST['id']);
            $nom           = $_POST['nom'] ?? '';
            $marque        = $_POST['marque'] ?? '';
            $os            = $_POST['os'] ?? '';
            $version_os    = $_POST['version_os'] ?? '';
            $serial        = $_POST['serial'] ?? '';
            $emplacement   = $_POST['emplacement'] ?? '';
            $ip            = $_POST['ip'] ?? '';
            $mac           = $_POST['mac'] ?? '';
            $date_install  = $_POST['date_install'] ?? null;
            $garantie      = ($_POST['garantie'] !== '') ? intval($_POST['garantie']) : null;
            $ext_garantie  = ($_POST['ext_garantie'] !== '') ? intval($_POST['ext_garantie']) : 0;
            $fourni_ca     = isset($_POST['fourni_ca']) ? $_POST['fourni_ca'] : 'non';
            $notes         = $_POST['notes'] ?? '';
            $modif_date    = date("Y-m-d H:i:s");
            $modif_auteur = htmlspecialchars($_SESSION['username']);

            $sql = "UPDATE ordis SET
                nom = ?, marque = ?, os = ?, version_os = ?, serial = ?, emplacement = ?, ip = ?, mac = ?,
                date_install = ?, garantie = ?, ext_garantie = ?, fourni_ca = ?, notes = ?, modif_date = ?, modif_auteur = ?
            WHERE id = ? AND id_client = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param(
                "sssssssssiissssii",
                $nom,
                $marque,
                $os,
                $version_os,
                $serial,
                $emplacement,
                $ip,
                $mac,
                $date_install,
                $garantie,
                $ext_garantie,
                $fourni_ca,
                $notes,
                $modif_date,
                $modif_auteur,
                $id,
                $client_id
            );
            $stmt->execute();
            $stmt->close();

            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;
        }
        // === Fin gestion du formulaire d'enregistrement ===

        $res = $conn->query("SELECT * FROM ordis WHERE id_client = $client_id ORDER BY id");

        $ordinateurs = [];
        while ($row = $res->fetch_assoc()) {
            $ordinateurs[] = $row;
        }

        // VÉRIFICATION AVANT TOUT CONTENU HTML
        if (!empty($ordinateurs) && !isset($_GET['id'])) {
            $first_id = $ordinateurs[0]['id'];
            header("Location: ?client_name=" . urlencode($client_name) . "&page=ordinateurs&id=" . $first_id);
            exit;
        }
        ?>

        <h3>💻 Ordinateurs</h3>
        <?php

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'new_ordi') {
    $nom = $_POST['new_ordi_nom'] ?? 'Nouvel ordinateur';
    $marque = $_POST['new_ordi_marque'] ?? 'Apple';
    $auteur = htmlspecialchars($_SESSION['username']);
    date_default_timezone_set('Europe/Paris');
    $crea_date = date('d/m/Y H:i:s');

    $stmt = $conn->prepare("
        INSERT INTO ordis (id_client, nom, marque, os, version_os, serial, emplacement, ip, mac,
                           date_install, garantie, ext_garantie, fourni_ca, notes,
                           crea_date, crea_auteur, modif_date, modif_auteur)
        VALUES (?, ?, ?, '', '', '', '', '', '', '', 0, 0, 'non', '', ?, ?, NOW(), ?)
    ");

    $stmt->bind_param("isssss", $client_id, $nom, $marque, $crea_date, $auteur, $auteur);
    $stmt->execute();
    $new_id = $stmt->insert_id;
    $stmt->close();

    header("Location: ?client_name=" . urlencode($client_name) . "&page=ordinateurs&id=" . $new_id);
    exit;
}

if (empty($ordinateurs)) {
    echo '<div class="alert alert-warning">⚠️ Pas d\'ordinateur créé.</div>';
    echo '<form method="post" style="display: inline;">';
    echo '<input type="hidden" name="action" value="new_ordi">';
    echo '<input type="hidden" name="new_ordi_nom" value="Nouvel ordinateur">';
    echo '<input type="hidden" name="new_ordi_marque" value="Apple">';
    echo '<button type="submit" class="btn btn-success">➕ Ajouter un ordinateur</button>';
    echo '</form>';
} else {
            echo '<ul class="nav nav-tabs">';
            foreach ($ordinateurs as $ordi) {
                $active = ($_GET['id'] == $ordi['id']) ? 'active' : '';
                echo '<li class="nav-item">';
                echo '<a class="nav-link ' . $active . '" href="?client_name=' . urlencode($client_name) . '&page=ordinateurs&id=' . $ordi['id'] . '">🖥️ ' . htmlspecialchars($ordi['nom']) . '</a>';
                echo '</li>';
            }
echo '<li class="nav-item ms-auto">';
echo '<form method="post" style="display: inline;">';
echo '<input type="hidden" name="action" value="new_ordi">';
echo '<input type="hidden" name="new_ordi_nom" value="Nouvel ordinateur">';
echo '<input type="hidden" name="new_ordi_marque" value="Apple">';
echo '<button type="submit" class="btn btn-success">➕ Ajouter</button>';
echo '</form>';
echo '</li>';
            echo '</ul>';

            if (isset($_GET['id'])):
                $id = (int)$_GET['id'];
                $q = $conn->prepare("SELECT * FROM ordis WHERE id = ? AND id_client = ?");
                $q->bind_param("ii", $id, $client_id);
                $q->execute();
                $ordi = $q->get_result()->fetch_assoc();

                if ($ordi):

                    // Dropdowns dynamiques
                    $marques_res = $conn->prepare("SELECT DISTINCT marque FROM ordis WHERE marque IS NOT NULL AND marque != '' ORDER BY marque COLLATE utf8_general_ci");
                    $marques_res->execute();
                    $marques = $marques_res->get_result();

                    $marque_selectionnee = isset($_GET['marque']) ? $_GET['marque'] : $ordi['marque'];

                    $os_res = $conn->prepare("SELECT DISTINCT os FROM ordis WHERE marque = ? AND os IS NOT NULL AND os != '' ORDER BY os COLLATE utf8_general_ci");
                    $os_res->bind_param("s", $marque_selectionnee);
                    $os_res->execute();
                    $os_list = $os_res->get_result();

                    $os_selectionne = isset($_GET['os']) ? $_GET['os'] : $ordi['os'];

                    $version_os_res = $conn->prepare("SELECT DISTINCT version_os FROM ordis WHERE marque = ? AND os = ? AND version_os IS NOT NULL AND version_os != '' ORDER BY version_os COLLATE utf8_general_ci");
                    $version_os_res->bind_param("ss", $marque_selectionnee, $os_selectionne);
                    $version_os_res->execute();
                    $versions_os = $version_os_res->get_result();
                ?>
                    <form method="post" action="" class="ordi-form mt-4">
                        <input type="hidden" name="id" value="<?= $ordi['id'] ?>">

                        <div class="row">
                            <!-- Colonne 1 -->
                            <div class="col-md-4">
                                <div class="column-header">📋 Informations générales</div>

                                <div class="form-group">
                                    <label>Nom de l'ordinateur</label>
                                    <input type="text" name="nom" class="form-control" value="<?= htmlspecialchars($ordi['nom']) ?>">
                                </div>

                                <div class="form-group">
                                    <label>Marque</label>
                                    <select name="marque" onchange="window.location.href='?client_name=<?= urlencode($client_name) ?>&page=ordinateurs&id=<?= $ordi['id'] ?>&marque='+encodeURIComponent(this.value);">
                                        <?php while ($marque = $marques->fetch_assoc()): ?>
                                            <option value="<?= htmlspecialchars($marque['marque']) ?>" <?= ($marque_selectionnee == $marque['marque'] ? 'selected' : '') ?>>
                                                <?= htmlspecialchars($marque['marque']) ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label>Système d'exploitation (OS)</label>
                                    <select name="os" onchange="window.location.href='?client_name=<?= urlencode($client_name) ?>&page=ordinateurs&id=<?= $ordi['id'] ?>&marque=<?= urlencode($marque_selectionnee) ?>&os='+encodeURIComponent(this.value);">
                                        <?php while ($os = $os_list->fetch_assoc()): ?>
                                            <option value="<?= htmlspecialchars($os['os']) ?>" <?= ($os_selectionne == $os['os'] ? 'selected' : '') ?>>
                                                <?= htmlspecialchars($os['os']) ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label>Version OS</label>
                                    <select name="version_os">
                                        <?php while ($version = $versions_os->fetch_assoc()): ?>
                                            <option value="<?= htmlspecialchars($version['version_os']) ?>" <?= ($ordi['version_os'] == $version['version_os'] ? 'selected' : '') ?>>
                                                <?= htmlspecialchars($version['version_os']) ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label>Numéro de série</label>
                                    <input type="text" name="serial" class="form-control" value="<?= htmlspecialchars($ordi['serial']) ?>">
                                </div>
                            </div>

                            <!-- Colonne 2 -->
                            <div class="col-md-4">
                                <div class="column-header">🔌 Configuration réseau</div>

                                <div class="form-group">
                                    <label>Emplacement</label>
                                    <input type="text" name="emplacement" class="form-control" value="<?= htmlspecialchars($ordi['emplacement']) ?>">
                                </div>
                                <div class="form-group">
                                    <label>Adresse IP</label>
                                    <input type="text" name="ip" class="form-control" value="<?= htmlspecialchars($ordi['ip']) ?>">
                                </div>
                                <div class="form-group">
                                    <label>Adresse MAC</label>
                                    <input type="text" name="mac" class="form-control" value="<?= htmlspecialchars($ordi['mac']) ?>">
                                </div>
                            </div>

                            <!-- Colonne 3 -->
                            <div class="col-md-4">
                                <div class="column-header">🛡️ Garantie & Infos</div>

                                <div class="form-group">
                                    <label>Date d'installation</label>
                                    <input type="date" name="date_install" class="form-control" value="<?= !empty($ordi['date_install']) ? implode('-', array_reverse(explode('/', $ordi['date_install']))) : ''?>">
                                </div>
                                <div class="form-group">
                                    <label>Garantie (ans)</label>
                                    <input type="number" name="garantie" class="form-control" value="<?= htmlspecialchars($ordi['garantie']) ?>">
                                </div>
                                <div class="form-group">
                                    <label>Extension garantie (ans)</label>
                                    <input type="number" name="ext_garantie" class="form-control" value="<?= htmlspecialchars($ordi['ext_garantie']) ?>">
                                </div>
                                <div class="form-group">
                                    <label>Fournie par Color Academy</label>
                                    <select name="fourni_ca" class="form-control">
                                        <option value="oui" <?= ($ordi['fourni_ca'] == 'oui' ? 'selected' : '') ?>>✅ Oui</option>
                                        <option value="non" <?= ($ordi['fourni_ca'] == 'non' ? 'selected' : '') ?>>❌ Non</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="form-group mt-3">
                            <label>📝 Notes</label>
                            <textarea name="notes" class="form-control"><?= htmlspecialchars($ordi['notes']) ?></textarea>
                        </div>

                        <div class="button-group">
                            <button type="submit" class="btn btn-primary">💾 Enregistrer</button>
                            <a href="delete_ordis.php?id=<?= $ordi['id'] ?>&redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="btn btn-danger" onclick="return confirm('⚠️ Êtes-vous sûr de vouloir supprimer cet ordinateur ?');">🗑️ Supprimer</a>
                        </div>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        <?php } ?>
    </div>
<?php ?>

<?php elseif ($page === 'rips'): ?> <!-- logiciels -->
    <style>
        .rips-container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .rips-container h3 {
            color: #2c3e50;
            border-bottom: 3px solid #3498db;
            padding-bottom: 10px;
            margin-bottom: 30px;
        }

        .rips-nav {
            margin-bottom: 30px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }

        .rip-tab {
            padding: 12px 20px;
            border-radius: 8px;
            background: #3498db;
            color: white;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            box-shadow: 0 2px 8px rgba(52, 152, 219, 0.3);
        }

        .rip-tab:hover {
            background: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.4);
        }

        .rip-tab.active {
            background: linear-gradient(135deg, #1abc9c 0%, #16a085 100%);
            box-shadow: 0 4px 12px rgba(26, 188, 156, 0.4);
        }

        .btn-add-rip {
            margin-left: auto;
            padding: 12px 24px;
            background: linear-gradient(135deg, #28a745 0%, #218838 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
            box-shadow: 0 2px 8px rgba(40, 167, 69, 0.3);
        }

        .btn-add-rip:hover {
            background: linear-gradient(135deg, #218838 0%, #1e7e34 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.4);
        }

        .rip-form {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        }

        .rip-sections {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }

        .rip-section {
            flex: 1;
            min-width: 300px;
            padding: 20px;
            border-radius: 12px;
            background: linear-gradient(135deg, #f9f9f9 0%, #f0f0f0 100%);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .rip-section:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .rip-section h4 {
            color: #34495e;
            margin: 0 0 20px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #3498db;
            font-size: 18px;
        }

        .rip-section label {
            display: block;
            margin-bottom: 15px;
            color: #555;
            font-weight: 600;
            font-size: 14px;
        }

        .rip-section input,
        .rip-section select,
        .rip-section textarea {
            width: 100%;
            padding: 10px 12px;
            margin-top: 5px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.3s;
            box-sizing: border-box;
            font-family: inherit;
        }

        .rip-section input:focus,
        .rip-section select:focus,
        .rip-section textarea:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .rip-section select {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23333' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 35px;
            background-color: white;
        }

        .rip-section textarea {
            resize: vertical;
            min-height: 250px;
        }

        .status-selector {
            background: #f8f8f8;
            border: 2px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .status-selector > div:first-child {
            font-weight: 600;
            font-size: 14px;
            display: block;
            margin-bottom: 12px;
            color: #34495e;
        }

        .status-toggle-btn {
            width: 50%;
            padding: 10px 15px;
            border-radius: 6px;
            border: 2px solid #bdc3c7;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 14px;
            font-weight: 600;
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
        }

        .status-toggle-btn.active {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
            border-color: #27ae60;
        }

        .status-toggle-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }

        .btn-submit {
            margin-top: 25px;
            padding: 12px 30px;
            font-size: 16px;
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            box-shadow: 0 2px 8px rgba(52, 152, 219, 0.3);
        }

        .btn-submit:hover {
            background: linear-gradient(135deg, #2980b9 0%, #21618c 100%);
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.4);
            transform: translateY(-2px);
        }

        .btn-delete {
            margin-top: 25px;
            margin-left: 10px;
            padding: 12px 30px;
            font-size: 16px;
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            box-shadow: 0 2px 8px rgba(231, 76, 60, 0.3);
        }

        .btn-delete:hover {
            background: linear-gradient(135deg, #c0392b 0%, #a93226 100%);
            box-shadow: 0 4px 12px rgba(231, 76, 60, 0.4);
            transform: translateY(-2px);
        }

        .no-rip-message {
            padding: 20px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-left: 4px solid #6c757d;
            border-radius: 8px;
            color: #495057;
            font-size: 16px;
        }

        @media (max-width: 992px) {
            .rip-section {
                min-width: 100%;
            }
        }
        .date-input {
  color: #000 !important;
}

.date-input::-webkit-calendar-picker-indicator {
  cursor: pointer;
  filter: invert(0.8);
}
    </style>

    <div class="rips-container">
        <h3>🖨️ Logiciels</h3>

        <?php
        $client_id = $client['id'];

        // Récupérer tous les rips du client
        $rips_sql = "SELECT * FROM rips WHERE id_client = $client_id ORDER BY id";
        $rips_result = $conn->query($rips_sql);
        $rips = [];
        if ($rips_result) {
            while ($row = $rips_result->fetch_assoc()) {
                $rips[] = $row;
            }
        }

        // Récupérer les ordinateurs du client
        $ordis_result = $conn->query("SELECT id, nom FROM ordis WHERE id_client = $client_id ORDER BY nom");
        $ordis = [];
        while ($row = $ordis_result->fetch_assoc()) {
            $ordis[] = $row;
        }

        // Récupérer les Dongle du client (uniquement ceux du client, sans blancs ni doublons)
        $dongles_result = $conn->query("
            SELECT MIN(lic.id) as id, lic.dongle_id
            FROM lic
            WHERE lic.id_client = $client_id
            AND lic.dongle_id IS NOT NULL
            AND lic.dongle_id != ''
            GROUP BY lic.dongle_id
            ORDER BY CAST(lic.dongle_id AS UNSIGNED) ASC
        ");
        $dongles = [];
        if ($dongles_result) {
            while ($row = $dongles_result->fetch_assoc()) {
                $dongles[] = [
                    'id' => $row['id'],
                    'dongle_id' => $row['dongle_id']
                ];
            }
        }

        // Récupérer toutes les marques, modèles, versions dans la table rips
        // Sans doublons, sans blancs, en ordre ASCII
        $lic_result = $conn->query("
            SELECT DISTINCT marque, modele, version
            FROM rips
            WHERE marque IS NOT NULL AND marque != ''
            AND modele IS NOT NULL AND modele != ''
            AND version IS NOT NULL AND version != ''
            ORDER BY marque ASC, modele ASC, version ASC
        ");
        $lic_data = [];
        while ($row = $lic_result->fetch_assoc()) {
            $marque = trim($row['marque']);
            $modele = trim($row['modele']);
            $version = trim($row['version']);

            // Vérifier que les valeurs ne sont pas vides après trim
            if (empty($marque) || empty($modele) || empty($version)) {
                continue;
            }

            if (!isset($lic_data[$marque])) {
                $lic_data[$marque] = [];
            }
            if (!isset($lic_data[$marque][$modele])) {
                $lic_data[$marque][$modele] = [];
            }
            // Ajouter seulement si pas de doublon
            if (!in_array($version, $lic_data[$marque][$modele])) {
                $lic_data[$marque][$modele][] = $version;
            }
        }

        // Trier les marques en ordre ASCII (insensible à la casse)
        uksort($lic_data, 'strcasecmp');
        foreach ($lic_data as &$marque_data) {
            uksort($marque_data, 'strcasecmp');
            foreach ($marque_data as &$versions) {
                usort($versions, 'strcasecmp');
            }
        }

        $lic_json = json_encode($lic_data);

        // Gestion du POST pour suppression
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_rip'])) {
            $id_rip_to_delete = (int)$_POST['id_rip'];
            $sql_delete_rip = "DELETE FROM rips WHERE id = $id_rip_to_delete";
            $conn->query($sql_delete_rip);
            header("Location: ?client_name=" . urlencode($client_name) . "&page=rips");
            exit;
        }

        // Gestion du POST pour mise à jour
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_rip'])) {
            $id_rip = (int)$_POST['id_rip'];

            $fields = [
                'id_ordi',
                'id_dongle',
                'marque',
                'modele',
                'version',
                'sp',
                'date_install',
                'garantie',
                'ext_garantie',
                'sous_garantie',
                'fourni_ca',
                'notes',
                'actif'
            ];

            $updates = [];
            foreach ($fields as $field) {
                $value = $_POST[$field] ?? '';

                // id_dongle : envoyer 0 si vide
                if ($field === 'id_dongle') {
                    $value = ($value === '') ? 0 : (int)$value;
                    $updates[] = "$field = $value";
                }
                // id_ordi : obligatoire, donc forcer entier
                elseif ($field === 'id_ordi') {
                    $updates[] = "$field = " . (int)$value;
                }
                // les autres champs : chaînes
                else {
                    $updates[] = "$field = '" . $conn->real_escape_string($value) . "'";
                }
            }

            $modif_auteur = htmlspecialchars($_SESSION['username']);
            $sql_update_rip = "UPDATE rips SET " . implode(', ', $updates) . ", modif_date = NOW(), modif_auteur = '?' WHERE id = $id_rip";
            $conn->query($sql_update_rip);

            header("Location: ?client_name=" . urlencode($client_name) . "&page=rips&rip=" . $current_rip_index);
            exit;
        }

        // Déterminer le rip sélectionné à afficher
        $total_rips = count($rips);
        $current_rip_index = isset($_GET['rip']) ? (int)$_GET['rip'] : 0;
        if ($current_rip_index < 0) $current_rip_index = 0;
        if ($current_rip_index >= $total_rips) $current_rip_index = $total_rips - 1;
        $rip = $rips[$current_rip_index] ?? null;
        ?>

        <!-- Navigation entre les RIPs -->
        <div class="rips-nav">
            <?php foreach ($rips as $index => $item): ?>
                <a href="?client_name=<?= urlencode($client_name) ?>&page=rips&rip=<?= $index ?>"
                   class="rip-tab <?= $index === $current_rip_index ? 'active' : '' ?>">
                    <?= htmlspecialchars($item['modele']) ?>
                </a>
            <?php endforeach; ?>
            <a href="ajouter_rip.php?client_name=<?= urlencode($client_name) ?>" class="btn-add-rip">
                ➕ Ajouter un RIP
            </a>
        </div>

        <?php if ($rip): ?>
            <form method="POST" class="rip-form">
                <input type="hidden" name="id_rip" value="<?= $rip['id'] ?>">
                <input type="hidden" name="update_rip" value="1">
                <input type="hidden" id="actif_hidden" name="actif" value="<?= $rip['actif'] ?>">

                <div class="rip-sections">
                    <!-- Informations générales -->
                    <div class="rip-section">
                        <h4>📋 Informations générales</h4>

                        <label>Ordinateur
                            <select name="id_ordi" required>
                                <option value="">-- Choisir un ordinateur --</option>
                                <?php foreach ($ordis as $ordi): ?>
                                    <option value="<?= $ordi['id'] ?>" <?= ($ordi['id'] == $rip['id_ordi']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($ordi['nom']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label>ID Dongle
                            <select id="id_dongle" name="id_dongle">
                                <option value="">-- Choisir un dongle --</option>
                                <?php foreach ($dongles as $dongle): ?>
                                    <option value="<?= $dongle['id'] ?>" <?= ($dongle['id'] == $rip['id_dongle']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($dongle['dongle_id']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label>Marque
                            <select id="marque" name="marque">
                                <option value="">-- Choisir une marque --</option>
                                <?php foreach (array_keys($lic_data) as $marque): ?>
                                    <option value="<?= htmlspecialchars($marque) ?>" <?= ($marque == $rip['marque']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($marque) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label>Modèle
                            <select id="modele" name="modele">
                                <option value="">-- Choisir un modèle --</option>
                                <?php
                                if (!empty($rip['marque']) && isset($lic_data[$rip['marque']])) {
                                    foreach ($lic_data[$rip['marque']] as $modele => $versions_arr) {
                                        $selected = ($modele == $rip['modele']) ? 'selected' : '';
                                        echo "<option value=\"" . htmlspecialchars($modele) . "\" $selected>" . htmlspecialchars($modele) . "</option>";
                                    }
                                }
                                ?>
                            </select>
                        </label>

                        <label>Version
                            <select id="version" name="version" data-selected-version="<?= htmlspecialchars($rip['version']) ?>">
                                <option value="">-- Choisir une version --</option>
                                <?php
                                if (!empty($rip['marque']) && !empty($rip['modele']) && isset($lic_data[$rip['marque']][$rip['modele']])) {
                                    foreach ($lic_data[$rip['marque']][$rip['modele']] as $version) {
                                        $selected = ($version == $rip['version']) ? 'selected' : '';
                                        echo "<option value=\"" . htmlspecialchars($version) . "\" $selected>" . htmlspecialchars($version) . "</option>";
                                    }
                                }
                                ?>
                            </select>
                        </label>

                        <label>SP
                            <input name="sp" value="<?= htmlspecialchars($rip['sp']) ?>">
                        </label>
                    </div>

                    <!-- Dates et garantie -->
                    <div class="rip-section">
                        <h4>🛡️ Garantie</h4>
                        <label>Date d'installation
                            <input name="date_install" type="date" value="<?=!empty($rip['date_install']) ? implode('-', array_reverse(explode('/', $rip['date_install']))) : '' ?>">
                        </label>
                        <label>Garantie (ans)
                            <input name="garantie" type="number" value="<?= $rip['garantie'] ?>">
                        </label>
                        <label>extension de garantie
                            <select name="sous_garantie">
                                <option value="1" <?= ($rip['sous_garantie'] == 1) ? 'selected' : '' ?>>✅ Oui</option>
                                <option value="" <?= ($rip['sous_garantie'] == '') ? 'selected' : '' ?>>❌ Non</option>
                            </select>
                        </label>
                        <label>Extension garantie (ans)
                            <input name="ext_garantie" type="number" value="<?= $rip['ext_garantie'] ?>">
                        </label>
                        <label>Fourni Color Academy
                            <select name="fourni_ca">
                                <option value="Oui" <?= ($rip['fourni_ca'] === 'Oui') ? 'selected' : '' ?>>✅ Oui</option>
                                <option value="Non" <?= ($rip['fourni_ca'] === 'Non') ? 'selected' : '' ?>>❌ Non</option>
                            </select>
                        </label>
                    </div>

                    <!-- Notes et Actif -->
                    <div class="rip-section">
                        <h4>📝 Notes et Statut</h4>
                        <label>Notes
                            <textarea name="notes"><?= htmlspecialchars($rip['notes']) ?></textarea>
                        </label>

                        <!-- Sélecteur Actif - Bouton unique -->
                        <div class="status-selector">
                            <div>Statut du RIP</div>
                            <button type="button" id="status-toggle" class="status-toggle-btn <?= ($rip['actif'] == 1) ? 'active' : '' ?>">
                                <?= ($rip['actif'] == 1) ? '✓ ACTIF' : '✗ INACTIF' ?>
                            </button>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-submit">💾 Enregistrer</button>
            </form>

            <!-- Formulaire suppression -->
            <form method="POST" onsubmit="return confirm('⚠️ Êtes-vous sûr de vouloir supprimer ce RIP ?');" style="display:inline;">
                <input type="hidden" name="id_rip" value="<?= $rip['id'] ?>">
                <button type="submit" name="delete_rip" class="btn-delete">
                    🗑️ Supprimer ce RIP
                </button>
            </form>

            <script>
                const licData = <?= $lic_json ?>;
                const marqueSelect = document.getElementById('marque');
                const modeleSelect = document.getElementById('modele');
                const versionSelect = document.getElementById('version');
                const statusToggleBtn = document.getElementById('status-toggle');
                const actifHidden = document.getElementById('actif_hidden');

                function clearOptions(select) {
                    while (select.options.length > 1) {
                        select.remove(1);
                    }
                }

                function populateModeles(marque) {
                    clearOptions(modeleSelect);
                    clearOptions(versionSelect);

                    if (marque && licData[marque]) {
                        const modeles = Object.keys(licData[marque]);
                        modeles.forEach(modele => {
                            const option = new Option(modele, modele);
                            modeleSelect.add(option);
                        });
                    }
                }

                function populateVersions(marque, modele) {
                    clearOptions(versionSelect);

                    if (marque && modele && licData[marque][modele]) {
                        licData[marque][modele].forEach(version => {
                            const option = new Option(version, version);
                            versionSelect.add(option);
                        });
                    }
                }

                marqueSelect.addEventListener('change', () => {
                    populateModeles(marqueSelect.value);
                });

                modeleSelect.addEventListener('change', () => {
                    populateVersions(marqueSelect.value, modeleSelect.value);
                });

                // Gestion du bouton de statut
                statusToggleBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const currentStatus = parseInt(actifHidden.value);
                    const newStatus = currentStatus === 1 ? 0 : 1;

                    actifHidden.value = newStatus;

                    if (newStatus === 1) {
                        statusToggleBtn.classList.add('active');
                        statusToggleBtn.textContent = '✓ ACTIF';
                    } else {
                        statusToggleBtn.classList.remove('active');
                        statusToggleBtn.textContent = '✗ INACTIF';
                    }
                });

                window.addEventListener('DOMContentLoaded', () => {
                    const selectedMarque = marqueSelect.value;
                    const selectedModele = modeleSelect.value;
                    const selectedVersion = versionSelect.getAttribute('data-selected-version');

                    if (selectedMarque) {
                        populateModeles(selectedMarque);

                        if (selectedModele) {
                            modeleSelect.value = selectedModele;
                            populateVersions(selectedMarque, selectedModele);

                            if (selectedVersion) {
                                versionSelect.value = selectedVersion;
                            }
                        }
                    }
                });
            </script>

        <?php else: ?>
            <p class="no-rip-message">ℹ️ Aucun RIP trouvé pour ce client.</p>
        <?php endif; ?>
    </div>
<?php ?>

<?php elseif ($page === 'imprimantes'): ?>
    <style>
        .imprimantes-container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .imprimantes-container h3 {
            color: #2c3e50;
            border-bottom: 3px solid #3498db;
            padding-bottom: 10px;
            margin-bottom: 30px;
        }

        .btn-add-printer {
            padding: 12px 24px;
            background: linear-gradient(135deg, #28a745 0%, #218838 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 2px 8px rgba(40, 167, 69, 0.3);
            margin-bottom: 20px;
        }

        .btn-add-printer:hover {
            background: linear-gradient(135deg, #218838 0%, #1e7e34 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.4);
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-warning {
            background: linear-gradient(135deg, #fff3cd 0%, #ffe69c 100%);
            color: #856404;
            border-left: 4px solid #ffc107;
        }

        .nav-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 0;
            margin-bottom: 30px;
        }

        .nav-item {
            margin-bottom: -2px;
        }

        .nav-link {
            padding: 12px 20px;
            color: #555;
            text-decoration: none;
            border-radius: 8px 8px 0 0;
            background: #f5f5f5;
            border: 1px solid #ddd;
            border-bottom: none;
            transition: all 0.3s;
            font-weight: 500;
        }

        .nav-link:hover {
            background: #e8e8e8;
            color: #2c3e50;
        }

        .nav-link.active {
            background: white;
            color: #3498db;
            border-color: #e0e0e0;
            border-bottom: 2px solid white;
            font-weight: 600;
        }

        .printer-form {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        }

        .row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -10px;
        }

        .col-md-4 {
            flex: 0 0 33.333%;
            max-width: 33.333%;
            padding: 0 10px;
        }

        @media (max-width: 992px) {
            .col-md-4 {
                flex: 0 0 100%;
                max-width: 100%;
                margin-bottom: 20px;
            }
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #34495e;
            font-weight: 600;
            font-size: 14px;
        }

        .form-control,
        .form-group select,
        .form-group input[type="text"],
        .form-group input[type="password"],
        .form-group input[type="date"],
        .form-group input[type="number"],
        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.3s;
            box-sizing: border-box;
            background: white;
        }

        .form-control:focus,
        .form-group select:focus,
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 200px;
            font-family: inherit;
        }

        .form-group select,
        .form-control-sm {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23333' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 35px;
        }

        .mt-3 {
            margin-top: 20px;
        }

        .mt-4 {
            margin-top: 30px;
        }

        .mb-2 {
            margin-bottom: 10px;
        }

        .mb-3 {
            margin-bottom: 20px;
        }

        .m-1 {
            margin: 5px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
            text-align: center;
        }

        .btn-primary {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            box-shadow: 0 2px 8px rgba(52, 152, 219, 0.3);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #2980b9 0%, #21618c 100%);
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.4);
            transform: translateY(-2px);
        }

        .btn-danger {
            background: linear-gradient(135deg, #e74c3c 0%, #c82333 100%);
            color: white;
            box-shadow: 0 2px 8px rgba(220, 53, 69, 0.3);
        }

        .btn-danger:hover {
            background: linear-gradient(135deg, #c82333 0%, #bd2130 100%);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.4);
            transform: translateY(-2px);
        }

        .btn-success {
            background: linear-gradient(135deg, #28a745 0%, #218838 100%);
            color: white;
            box-shadow: 0 2px 8px rgba(40, 167, 69, 0.3);
        }

        .btn-success:hover {
            background: linear-gradient(135deg, #218838 0%, #1e7e34 100%);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.4);
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
            color: white;
            box-shadow: 0 2px 8px rgba(108, 117, 125, 0.3);
        }

        .btn-secondary:hover {
            background: linear-gradient(135deg, #5a6268 0%, #545b62 100%);
            box-shadow: 0 4px 12px rgba(108, 117, 125, 0.4);
            transform: translateY(-2px);
        }

        .btn-sm {
            padding: 8px 16px;
            font-size: 13px;
        }

        .section-header {
            background: linear-gradient(135deg, #f9f9f9 0%, #f0f0f0 100%);
            padding: 15px 20px;
            border-radius: 10px;
            margin-top: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .section-header strong {
            color: #34495e;
            font-size: 16px;
            font-weight: 700;
        }

        .items-container {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            min-height: 60px;
            border: 2px dashed #ddd;
            margin-top: 10px;
        }

        .text-muted {
            color: #6c757d;
            font-style: italic;
        }

        .d-flex {
            display: flex;
        }

        .align-items-center {
            align-items: center;
        }

        .mr-2 {
            margin-right: 10px;
        }

        .d-inline-block {
            display: inline-block;
        }

        .item-toggle {
            transition: all 0.3s;
        }

        .select-container {
            background: #fff;
            padding: 15px;
            border-radius: 8px;
            border: 2px solid #3498db;
            margin-bottom: 15px;
        }

        @media (max-width: 768px) {
            .d-flex {
                flex-direction: column;
            }

            .mr-2 {
                margin-right: 0;
                margin-bottom: 10px;
            }
        }
    </style>

    <div class="imprimantes-container">
        <h3>🖨️ Imprimantes</h3>

        <?php
        $client_id = $client['id'];

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
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
            $installation = $_POST['installation'] ?? '';
            $garantie = $_POST['garantie'] !== '' ? (int)$_POST['garantie'] : 0;
            $sous_garantie = $_POST['sous_garantie'] ?? '';
            $extension = isset($_POST['extension']) && $_POST['extension'] !== '' ? (int)$_POST['extension'] : 0;
            $fournie_par = isset($_POST['fournie_par']) ? (int)$_POST['fournie_par'] : 0;
            $notes = $_POST['notes'] ?? '';
            $modif_auteur = htmlspecialchars($_SESSION['username']);

            $itemsToRemove = json_decode($_POST['items_to_remove'] ?? '[]', true);
            $itemsToAdd = json_decode($_POST['items_to_add'] ?? '[]', true);

            // UPDATE imprimante
            $stmt = $conn->prepare("
                UPDATE imprimantes SET
                    nom=?, marque=?, modele=?, serial=?, emplacement=?,
                    connexion=?, ip=?, mac=?, firmware=?, password=?,
                    date_install=?, garantie=?, sous_garantie=?, ext_garantie=?,
                    fourni_ca=?, notes=?, modif_date=NOW(), modif_auteur=?
                WHERE id=? AND id_client=?
            ");

            $stmt->bind_param(
                "sssssssssssisisssii",
                $nom, $marque, $modele, $serial, $emplacement,
                $connexion, $ip, $mac, $firmware, $mdp,
                $installation, $garantie, $sous_garantie, $extension,
                $fournie_par, $notes, $modif_auteur,
                $id, $client_id
            );
            $stmt->execute();
            $stmt->close();

            // Gestion des ajouts de serveurs et RIPs
            foreach ($itemsToAdd as $item) {
                if ($item['type'] === 'ordi') {
                    $res = $conn->query("SELECT serveurs FROM imprimantes WHERE id = $id");
                    if ($row = $res->fetch_assoc()) {
                        $serveurs = $row['serveurs'];
                        $serveurs_array = !empty($serveurs) ? explode('*', $serveurs) : [];
                        if (!in_array($item['id'], $serveurs_array)) {
                            $serveurs_array[] = $item['id'];
                            $new_serveurs = implode('*', $serveurs_array);
                            $stmt_upd = $conn->prepare("UPDATE imprimantes SET serveurs=? WHERE id=?");
                            $stmt_upd->bind_param("si", $new_serveurs, $id);
                            $stmt_upd->execute();
                            $stmt_upd->close();
                        }
                    }
                }
                if ($item['type'] === 'rip') {
                    $res = $conn->query("SELECT rip FROM imprimantes WHERE id = $id");
                    if ($row = $res->fetch_assoc()) {
                        $rips = $row['rip'];
                        $rips_array = !empty($rips) ? explode('*', $rips) : [];
                        if (!in_array($item['id'], $rips_array)) {
                            $rips_array[] = $item['id'];
                            $new_rips = implode('*', $rips_array);
                            $stmt_upd = $conn->prepare("UPDATE imprimantes SET rip=? WHERE id=?");
                            $stmt_upd->bind_param("si", $new_rips, $id);
                            $stmt_upd->execute();
                            $stmt_upd->close();
                        }
                    }
                }
            }

            // Gestion des suppressions de serveurs et RIPs
            foreach ($itemsToRemove as $item) {
                if ($item['type'] === 'ordi') {
                    $res = $conn->query("SELECT serveurs FROM imprimantes WHERE id = $id");
                    if ($row = $res->fetch_assoc()) {
                        $serveurs = $row['serveurs'];
                        if (!empty($serveurs)) {
                            $serveurs_array = explode('*', $serveurs);
                            $serveurs_array = array_filter($serveurs_array, function($srv_id) use ($item) {
                                return (int)$srv_id !== (int)$item['id'];
                            });
                            $new_serveurs = implode('*', $serveurs_array);
                            $stmt_upd = $conn->prepare("UPDATE imprimantes SET serveurs=? WHERE id=?");
                            $stmt_upd->bind_param("si", $new_serveurs, $id);
                            $stmt_upd->execute();
                            $stmt_upd->close();
                        }
                    }
                }
                if ($item['type'] === 'rip') {
                    $res = $conn->query("SELECT rip FROM imprimantes WHERE id = $id");
                    if ($row = $res->fetch_assoc()) {
                        $rips = $row['rip'];
                        if (!empty($rips)) {
                            $rips_array = explode('*', $rips);
                            $rips_array = array_filter($rips_array, function($rip_id) use ($item) {
                                return (int)$rip_id !== (int)$item['id'];
                            });
                            $new_rips = implode('*', $rips_array);
                            $stmt_upd = $conn->prepare("UPDATE imprimantes SET rip=? WHERE id=?");
                            $stmt_upd->bind_param("si", $new_rips, $id);
                            $stmt_upd->execute();
                            $stmt_upd->close();
                        }
                    }
                }
            }

            header("Location: ?client_name=" . urlencode($client_name) . "&page=imprimantes&id=$id");
            exit;
        }

        // Gestion de l'ajout d'une nouvelle imprimante
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'new_printer') {
            $nom = $_POST['new_printer_nom'] ?? 'Nouvelle imprimante';
            $marque = $_POST['new_printer_marque'] ?? '';
            $modele = $_POST['new_printer_modele'] ?? '';
            $auteur = htmlspecialchars($_SESSION['username']);
            date_default_timezone_set('Europe/Paris');
            $crea_date = date('d/m/Y H:i:s');

            $stmt = $conn->prepare("
                INSERT INTO imprimantes (id_client, nom, marque, modele, serial, emplacement, connexion, ip, mac, firmware, password, date_install, garantie, sous_garantie, ext_garantie, fourni_ca, notes, login, papiers, serveurs, rip, crea_date, crea_auteur, modif_date, modif_auteur)
                VALUES (?, ?, ?, ?, '', '', '', '', '', '', '', '', 0, '', '', '', '', '', '', '', '', ?, ?, NOW(), ?)
            ");

            $stmt->bind_param("issssss", $client_id, $nom, $marque, $modele, $crea_date, $auteur, $auteur);
            $stmt->execute();
            $new_id = $stmt->insert_id;
            $stmt->close();

            header("Location: ?client_name=" . urlencode($client_name) . "&page=imprimantes&id=$new_id");
            exit;
        }

        $res = $conn->query("SELECT * FROM imprimantes WHERE id_client = $client_id ORDER BY id");

        $imprimantes = [];
        while ($row = $res->fetch_assoc()) {
            $imprimantes[] = $row;
        }

        // Récupérer toutes les marques et modèles uniques sans doublons ni blancs
        $marques_modeles = [];
        $res_marques = $conn->query("
            SELECT DISTINCT marque, modele
            FROM imprimantes
            WHERE marque IS NOT NULL AND marque != ''
            AND modele IS NOT NULL AND modele != ''
            ORDER BY marque, modele
        ");

        if ($res_marques) {
            while ($row = $res_marques->fetch_assoc()) {
                $marque = trim($row['marque']);
                $modele = trim($row['modele']);

                if (!empty($marque) && !empty($modele)) {
                    if (!isset($marques_modeles[$marque])) {
                        $marques_modeles[$marque] = [];
                    }
                    if (!in_array($modele, $marques_modeles[$marque])) {
                        $marques_modeles[$marque][] = $modele;
                    }
                }
            }
        }

        // Bouton pour ajouter une nouvelle imprimante
        echo '<div class="mb-3">';
        echo '<form method="post" action="" style="display: inline;">';
        echo '<input type="hidden" name="action" value="new_printer">';
        echo '<input type="hidden" name="new_printer_nom" value="Nouvelle imprimante">';
        echo '<input type="hidden" name="new_printer_marque" value="">';
        echo '<input type="hidden" name="new_printer_modele" value="">';
        echo '<button type="submit" class="btn-add-printer">➕ Ajouter une nouvelle imprimante</button>';
        echo '</form>';
        echo '</div>';

        if (empty($imprimantes)) {
            echo '<div class="alert alert-warning">⚠️ Pas d\'imprimante créée.</div>';
        } else {
            if (!isset($_GET['id'])) {
                header("Location: ?client_name=" . urlencode($client_name) . "&page=imprimantes&id=" . $imprimantes[0]['id']);
                exit;
            }

            // Onglets imprimantes
            echo '<ul class="nav nav-tabs">';
            foreach ($imprimantes as $imp) {
                $active = ($_GET['id'] == $imp['id']) ? 'active' : '';
                echo '<li class="nav-item"><a class="nav-link ' . $active . '" href="?client_name=' . urlencode($client_name) . '&page=imprimantes&id=' . $imp['id'] . '">🖨️ ' . htmlspecialchars($imp['nom']) . '</a></li>';
            }
            echo '</ul>';

            // Affichage du formulaire
            $id = (int)$_GET['id'];
            $q = $conn->prepare("SELECT * FROM imprimantes WHERE id = ? AND id_client = ?");
            $q->bind_param("ii", $id, $client_id);
            $q->execute();
            $printer = $q->get_result()->fetch_assoc();

            if ($printer):
                // Récupérer tous les serveurs du client
                $all_serveurs = [];
                $res_serveurs = $conn->query("SELECT id, nom FROM ordis WHERE id_client = $client_id ORDER BY nom");
                while ($srv = $res_serveurs->fetch_assoc()) {
                    $all_serveurs[] = $srv;
                }

                // Récupérer tous les RIPs du client
                $all_rips = [];
                $res_rips = $conn->query("SELECT id, modele, version FROM rips WHERE id_client = $client_id ORDER BY modele");
                while ($rip = $res_rips->fetch_assoc()) {
                    $all_rips[] = $rip;
                }

                // Récupérer les serveurs actuellement associés
                $current_serveurs = [];
                if (!empty($printer['serveurs'])) {
                    $current_serveurs = array_map('intval', explode('*', $printer['serveurs']));
                }

                // Récupérer les RIPs actuellement associés
                $current_rips = [];
                if (!empty($printer['rip'])) {
                    $current_rips = array_map('intval', explode('*', $printer['rip']));
                }

                // Convertir en JSON pour JavaScript
                $marques_modeles_json = json_encode($marques_modeles);
                ?>
                <form method="post" action="" class="printer-form mt-4" id="printerForm">
                    <input type="hidden" name="id" value="<?= $printer['id'] ?>">
                    <input type="hidden" name="client_name" value="<?= htmlspecialchars($client_name) ?>">
                    <input type="hidden" name="items_to_remove" id="itemsToRemove" value="[]">
                    <input type="hidden" name="items_to_add" id="itemsToAdd" value="[]">

                    <div class="row">
                        <!-- Colonne 1 -->
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Nom de l'imprimante</label>
                                <input type="text" name="nom" class="form-control" value="<?= htmlspecialchars($printer['nom']) ?>">
                            </div>
                            <div class="form-group">
                                <label>Marque</label>
                                <select name="marque" id="marqueSelect" class="form-control" required>
                                    <option value="">-- Choisir une marque --</option>
                                    <?php foreach (array_keys($marques_modeles) as $m): ?>
                                        <option value="<?= htmlspecialchars($m) ?>" <?= ($printer['marque'] === $m ? 'selected' : '') ?>>
                                            <?= htmlspecialchars($m) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Modèle</label>
                                <select name="modele" id="modeleSelect" class="form-control" required>
                                    <option value="">-- Choisir un modèle --</option>
                                    <?php
                                    if (!empty($printer['marque']) && isset($marques_modeles[$printer['marque']])) {
                                        foreach ($marques_modeles[$printer['marque']] as $mod) {
                                            echo '<option value="' . htmlspecialchars($mod) . '" ' . ($printer['modele'] === $mod ? 'selected' : '') . '>' . htmlspecialchars($mod) . '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Numéro de série</label>
                                <input type="text" name="serial" class="form-control" value="<?= htmlspecialchars($printer['serial']) ?>">
                            </div>
                        </div>

                        <!-- Colonne 2 -->
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Emplacement</label>
                                <input type="text" name="emplacement" class="form-control" value="<?= htmlspecialchars($printer['emplacement']) ?>">
                            </div>
                            <div class="form-group">
                                <label>Connexion</label>
                                <select name="connexion" class="form-control">
                                    <option value="Ethernet" <?= ($printer['connexion']==='Ethernet'?'selected':'')?>>Ethernet</option>
                                    <option value="USB" <?= ($printer['connexion']==='USB'?'selected':'')?>>USB</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Adresse IP</label>
                                <input type="text" name="ip" class="form-control" value="<?= htmlspecialchars($printer['ip']) ?>">
                            </div>
                            <div class="form-group">
                                <label>Adresse MAC</label>
                                <input type="text" name="mac" class="form-control" value="<?= htmlspecialchars($printer['mac']) ?>">
                            </div>
                        </div>

                        <!-- Colonne 3 -->
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Version firmware</label>
                                <input type="text" name="firmware" class="form-control" value="<?= htmlspecialchars($printer['firmware']) ?>">
                            </div>
                            <div class="form-group">
                                <label>Mot de passe</label>
                                <input type="password" name="mdp" class="form-control" value="<?= htmlspecialchars($printer['password']) ?>">
                            </div>
                            <div class="form-group">
                                <label>Date d'installation</label>
                                <input type="date" name="installation" class="form-control" value="<?=!empty($printer['date_install']) ? implode('-', array_reverse(explode('/', $printer['date_install']))) : '' ?>">
                            </div>
                            <div class="form-group">
                                <label>Garantie (ans)</label>
                                <input type="number" name="garantie" class="form-control" value="<?= htmlspecialchars($printer['garantie']) ?>">
                            </div>
                        </div>
                    </div>

                    <div class="form-group mt-3">
                        <label>📝 Notes</label>
                        <textarea name="notes" class="form-control"><?= htmlspecialchars($printer['notes']) ?></textarea>
                    </div>

                    <!-- Serveurs avec boutons interactifs -->
                    <div class="section-header mt-3">
                        <div class="d-flex align-items-center">
                            <strong class="mr-2">🖥️ Serveurs</strong>
                            <button type="button" class="btn btn-success btn-sm" id="addServeurBtn">➕ Ajouter</button>
                        </div>
                    </div>

                    <!-- Menu déroulant pour ajouter un serveur -->
                    <div id="serveurSelectContainer" style="display: none;" class="select-container">
                        <select id="serveurSelect" class="form-control form-control-sm d-inline-block" style="width: auto;">
                            <option value="">-- Choisir un serveur --</option>
                            <?php foreach ($all_serveurs as $srv): ?>
                                <?php if (!in_array($srv['id'], $current_serveurs)): ?>
                                    <option value="<?= $srv['id'] ?>"><?= htmlspecialchars($srv['nom']) ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="btn btn-sm btn-primary" id="confirmServeurBtn">✓ Confirmer</button>
                        <button type="button" class="btn btn-sm btn-secondary" id="cancelServeurBtn">✗ Annuler</button>
                    </div>

                    <div id="serveursContainer" class="items-container">
                        <?php
                        $has_serveurs = false;
                        if (!empty($printer['serveurs'])) {
                            $serveurs_ids = explode('*', $printer['serveurs']);
                            foreach ($serveurs_ids as $srv_id) {
                                $srv_id = (int)$srv_id;
                                if ($srv_id > 0) {
                                    $res = $conn->query("SELECT nom FROM ordis WHERE id = $srv_id");
                                    if ($row = $res->fetch_assoc()) {
                                        echo '<button type="button" class="btn btn-primary btn-sm m-1 item-toggle" data-type="ordi" data-id="' . $srv_id . '" data-name="' . htmlspecialchars($row['nom']) . '">' . htmlspecialchars($row['nom']) . '</button>';
$has_serveurs = true;
}
}
}
}
if (!$has_serveurs) {
echo '<span class="text-muted" id="noServeur">Aucun serveur associé</span>';
}
?>
</div><!-- RIPs avec boutons interactifs -->
                <div class="section-header mt-3">
                    <div class="d-flex align-items-center">
                        <strong class="mr-2">🖨️ RIPs</strong>
                        <button type="button" class="btn btn-success btn-sm" id="addRipBtn">➕ Ajouter</button>
                    </div>
                </div>

                <!-- Menu déroulant pour ajouter un RIP -->
                <div id="ripSelectContainer" style="display: none;" class="select-container">
                    <select id="ripSelect" class="form-control form-control-sm d-inline-block" style="width: auto;">
                        <option value="">-- Choisir un RIP --</option>
                        <?php foreach ($all_rips as $rip): ?>
                            <?php if (!in_array($rip['id'], $current_rips)): ?>
                                <option value="<?= $rip['id'] ?>" data-name="<?= htmlspecialchars($rip['modele'] . ' ' . $rip['version']) ?>">
                                    <?= htmlspecialchars($rip['modele'] . ' ' . $rip['version']) ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="btn btn-sm btn-primary" id="confirmRipBtn">✓ Confirmer</button>
                    <button type="button" class="btn btn-sm btn-secondary" id="cancelRipBtn">✗ Annuler</button>
                </div>

                <div id="ripsContainer" class="items-container">
                    <?php
                    $has_rips = false;
                    if (!empty($printer['rip'])) {
                        $rips_ids = explode('*', $printer['rip']);
                        foreach ($rips_ids as $rip_id) {
                            $rip_id = (int)$rip_id;
                            if ($rip_id > 0) {
                                $res = $conn->query("SELECT modele, version FROM rips WHERE id = $rip_id");
                                if ($row = $res->fetch_assoc()) {
                                    $rip_name = $row['modele'] . ' ' . $row['version'];
                                    echo '<button type="button" class="btn btn-primary btn-sm m-1 item-toggle" data-type="rip" data-id="' . $rip_id . '" data-name="' . htmlspecialchars($rip_name) . '">' . htmlspecialchars($rip_name) . '</button>';
                                    $has_rips = true;
                                }
                            }
                        }
                    }
                    if (!$has_rips) {
                        echo '<span class="text-muted" id="noRip">Aucun RIP associé</span>';
                    }
                    ?>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
    <button type="submit" class="btn btn-primary">💾 Enregistrer</button>
    <a href="delete_imprimante.php?id=<?= $printer['id'] ?>&redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>"
       class="btn btn-danger"
       onclick="return confirm('⚠️ Êtes-vous sûr de vouloir supprimer cette imprimante ?');">
        🗑️ Supprimer l'imprimante
    </a>
</div>
            </form>

            <script>
            // Données des marques et modèles
            const marquesModeles = <?= $marques_modeles_json ?>;

            document.addEventListener('DOMContentLoaded', function() {
                const itemsToRemove = [];
                const itemsToAdd = [];

                // Gestion du changement de marque
                document.getElementById('marqueSelect').addEventListener('change', function() {
                    const marque = this.value;
                    const modeleSelect = document.getElementById('modeleSelect');
                    modeleSelect.innerHTML = '<option value="">-- Choisir un modèle --</option>';

                    if (marque && marquesModeles[marque]) {
                        marquesModeles[marque].forEach(modele => {
                            const option = document.createElement('option');
                            option.value = modele;
                            option.textContent = modele;
                            modeleSelect.appendChild(option);
                        });
                    }
                });

                // Gestion des clics sur les boutons serveurs/RIPs existants
                function setupToggleButtons() {
                    document.querySelectorAll('.item-toggle').forEach(function(btn) {
                        // Retirer les anciens événements pour éviter les doublons
                        const newBtn = btn.cloneNode(true);
                        btn.parentNode.replaceChild(newBtn, btn);

                        newBtn.addEventListener('click', function() {
                            const itemId = parseInt(this.getAttribute('data-id'));
                            const itemType = this.getAttribute('data-type');
                            const isNewItem = this.hasAttribute('data-new-item');

                            if (this.classList.contains('btn-primary')) {
                                // Passer en rouge (marquer pour suppression)
                                this.classList.remove('btn-primary');
                                this.classList.add('btn-danger');

                                if (isNewItem) {
                                    // Si c'est un nouvel item, le retirer de la liste d'ajout
                                    const index = itemsToAdd.findIndex(item => item.id === itemId && item.type === itemType);
                                    if (index > -1) {
                                        itemsToAdd.splice(index, 1);
                                    }
                                    // Et supprimer le bouton
                                    this.remove();
                                } else {
                                    // Si c'est un item existant, l'ajouter à la liste de suppression
                                    const index = itemsToRemove.findIndex(item => item.id === itemId && item.type === itemType);
                                    if (index === -1) {
                                        itemsToRemove.push({id: itemId, type: itemType});
                                    }
                                }
                            } else {
                                // Repasser en bleu (annuler la suppression)
                                this.classList.remove('btn-danger');
                                this.classList.add('btn-primary');

                                // Retirer de la liste de suppression
                                const index = itemsToRemove.findIndex(item => item.id === itemId && item.type === itemType);
                                if (index > -1) {
                                    itemsToRemove.splice(index, 1);
                                }
                            }

                            // Mettre à jour l'affichage "Aucun ... associé"
                            updateEmptyMessage();
                        });
                    });
                }

                setupToggleButtons();

                function updateEmptyMessage() {
                    const serveursContainer = document.getElementById('serveursContainer');
                    const ripsContainer = document.getElementById('ripsContainer');

                    // Serveurs
                    const serveurButtons = serveursContainer.querySelectorAll('.item-toggle');
                    const noServeurMsg = document.getElementById('noServeur');
                    if (serveurButtons.length === 0 && !noServeurMsg) {
                        serveursContainer.innerHTML = '<span class="text-muted" id="noServeur">Aucun serveur associé</span>';
                    } else if (serveurButtons.length > 0 && noServeurMsg) {
                        noServeurMsg.remove();
                    }

                    // RIPs
                    const ripButtons = ripsContainer.querySelectorAll('.item-toggle');
                    const noRipMsg = document.getElementById('noRip');
                    if (ripButtons.length === 0 && !noRipMsg) {
                        ripsContainer.innerHTML = '<span class="text-muted" id="noRip">Aucun RIP associé</span>';
                    } else if (ripButtons.length > 0 && noRipMsg) {
                        noRipMsg.remove();
                    }
                }

                // Gestion de l'ajout de serveurs
                document.getElementById('addServeurBtn').addEventListener('click', function() {
                    document.getElementById('serveurSelectContainer').style.display = 'block';
                });

                document.getElementById('cancelServeurBtn').addEventListener('click', function() {
                    document.getElementById('serveurSelectContainer').style.display = 'none';
                    document.getElementById('serveurSelect').value = '';
                });

                document.getElementById('confirmServeurBtn').addEventListener('click', function() {
                    const select = document.getElementById('serveurSelect');
                    const selectedId = parseInt(select.value);
                    const selectedName = select.options[select.selectedIndex].text;

                    if (selectedId) {
                        // Retirer le message "Aucun serveur"
                        const noServeur = document.getElementById('noServeur');
                        if (noServeur) noServeur.remove();

                        // Ajouter le bouton
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'btn btn-primary btn-sm m-1 item-toggle';
                        btn.setAttribute('data-type', 'ordi');
                        btn.setAttribute('data-id', selectedId);
                        btn.setAttribute('data-name', selectedName);
                        btn.setAttribute('data-new-item', 'true');
                        btn.textContent = selectedName;

                        document.getElementById('serveursContainer').appendChild(btn);

                        // Ajouter à la liste des items à ajouter
                        itemsToAdd.push({id: selectedId, type: 'ordi'});

                        // Retirer l'option du select
                        select.options[select.selectedIndex].remove();

                        // Réinitialiser et cacher
                        select.value = '';
                        document.getElementById('serveurSelectContainer').style.display = 'none';

                        // Réattacher les événements
                        setupToggleButtons();
                    }
                });

                // Gestion de l'ajout de RIPs
                document.getElementById('addRipBtn').addEventListener('click', function() {
                    document.getElementById('ripSelectContainer').style.display = 'block';
                });

                document.getElementById('cancelRipBtn').addEventListener('click', function() {
                    document.getElementById('ripSelectContainer').style.display = 'none';
                    document.getElementById('ripSelect').value = '';
                });

                document.getElementById('confirmRipBtn').addEventListener('click', function() {
                    const select = document.getElementById('ripSelect');
                    const selectedId = parseInt(select.value);
                    const selectedName = select.options[select.selectedIndex].getAttribute('data-name');

                    if (selectedId) {
                        // Retirer le message "Aucun RIP"
                        const noRip = document.getElementById('noRip');
                        if (noRip) noRip.remove();

                        // Ajouter le bouton
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'btn btn-primary btn-sm m-1 item-toggle';
                        btn.setAttribute('data-type', 'rip');
                        btn.setAttribute('data-id', selectedId);
                        btn.setAttribute('data-name', selectedName);
                        btn.setAttribute('data-new-item', 'true');
                        btn.textContent = selectedName;

                        document.getElementById('ripsContainer').appendChild(btn);

                        // Ajouter à la liste des items à ajouter
                        itemsToAdd.push({id: selectedId, type: 'rip'});

                        // Retirer l'option du select
                        select.options[select.selectedIndex].remove();

                        // Réinitialiser et cacher
                        select.value = '';
                        document.getElementById('ripSelectContainer').style.display = 'none';

                        // Réattacher les événements
                        setupToggleButtons();
                    }
                });

                // Avant la soumission du formulaire
                document.getElementById('printerForm').addEventListener('submit', function(e) {
                    document.getElementById('itemsToRemove').value = JSON.stringify(itemsToRemove);
                    document.getElementById('itemsToAdd').value = JSON.stringify(itemsToAdd);
                });
            });
            </script>
        <?php endif; ?>
    <?php } ?>
</div>

<?php elseif ($page === 'licences'): ?>
<h3>Licences</h3>
<?php
$client_id = $client['id'];

$base_path = __DIR__ . "/data";

// Chemins complets
$upload_dir_lic  = "$base_path/inter/$client_id/";
$upload_dir_cert = "$base_path/certif/$client_id/";

// Création des dossiers
if (!is_dir($upload_dir_lic)) {
    mkdir($upload_dir_lic, 0777, true);
}

if (!is_dir($upload_dir_cert)) {
    mkdir($upload_dir_cert, 0777, true);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_full_lic'])) {
    $id_lic = intval($_POST['id_lic']);

    // Suppression des fichiers
    $lic_file = $upload_dir_lic . "lic_$id_lic.lic";
    $cert_file = $upload_dir_cert . "cert_$id_lic.pdf";

    if(file_exists($lic_file)) unlink($lic_file);
    if(file_exists($cert_file)) unlink($cert_file);

    // Suppression SQL
    $stmt = $conn->prepare("DELETE FROM lic WHERE id=?");
    $stmt->bind_param("i", $id_lic);
    $stmt->execute();
    $stmt->close();

    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}
// --- GESTION CRÉATION NOUVELLE LICENCE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_licence'])) {
    $id_serveur = 0;
    $dongle_type = 'Dongle HASP';

    $stmt = $conn->prepare("INSERT INTO lic (id_client, id_serveur, temp, editeur, modele, opt, version, dongle_type, dongle_id, eac, date_expiration, notes, modif_date, modif_auteur, cron) VALUES (?, ?, 'non', '', '', '', '', ?, '', '', '', '', NOW(), 'admin', 0)");
    $stmt->bind_param("iis", $client_id, $id_serveur, $dongle_type);
    $stmt->execute();
    $stmt->close();

    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

// --- MISE À JOUR RAPIDE DU CHAMP TEMP (AJAX) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['temp_only'])) {
    $id_lic = intval($_POST['id_lic'] ?? 0);
    $temp = $_POST['temp'] ?? 'non';
    $modif_auteur = htmlspecialchars($_SESSION['username']);

    $stmt = $conn->prepare("UPDATE lic SET temp=?, modif_date=NOW(), modif_auteur='?' WHERE id=?");
    $stmt->bind_param("si", $temp, $id_lic);
    $stmt->execute();
    $stmt->close();

    http_response_code(200);
    exit;
}

// --- GESTION UPLOAD / SUPPRESSION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['update_licence']) && !isset($_POST['add_licence'])) {
    $id_lic = intval($_POST['id_lic'] ?? 0);

    if(isset($_POST['action'])){
        switch($_POST['action']){
            case 'upload_lic':
                if(isset($_FILES['lic_file']) && $_FILES['lic_file']['error'] === UPLOAD_ERR_OK){
                    move_uploaded_file($_FILES['lic_file']['tmp_name'], $upload_dir_lic . "lic_$id_lic.lic");
                }
                break;
            case 'delete_lic':
                $file = $upload_dir_lic . "lic_$id_lic.lic";
                if(file_exists($file)) unlink($file);
                break;
            case 'upload_cert':
                if(isset($_FILES['cert_file']) && $_FILES['cert_file']['error'] === UPLOAD_ERR_OK){
                    move_uploaded_file($_FILES['cert_file']['tmp_name'], $upload_dir_cert . "cert_$id_lic.pdf");
                }
                break;
            case 'delete_cert':
                $file = $upload_dir_cert . "cert_$id_lic.pdf";
                if(file_exists($file)) unlink($file);
                break;
        }
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }
}

// --- MISE À JOUR DE LA LICENCE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_licence'])) {
    $id_lic = intval($_POST['id_lic']);
    $temp = ($_POST['temp'] === 'temporaire') ? 'oui' : 'non';
    $editeur = $_POST['editeur'] ?? '';
    $modele = $_POST['modele'] ?? '';
    $opt = $_POST['opt'] ?? '';
    $version = $_POST['version'] ?? '';
    $dongle_type = $_POST['dongle_type'] ?? '';
    $dongle_id = $_POST['dongle_id'] ?? '';
    $eac = $_POST['eac'] ?? '';
    $id_serveur = intval($_POST['serveur'] ?? 0);
    $date_expiration = !empty($_POST['date_expiration']) ? $_POST['date_expiration'] : '';
    $modif_auteur = htmlspecialchars($_SESSION['username']);

    $stmt = $conn->prepare("UPDATE lic SET temp=?, editeur=?, modele=?, opt=?, version=?, date_expiration=?, dongle_type=?, dongle_id=?, eac=?, id_serveur=?, modif_date=NOW(), modif_auteur='?' WHERE id=?");
    $stmt->bind_param("sssssssssii", $temp, $editeur, $modele, $opt, $version, $date_expiration, $dongle_type, $dongle_id, $eac, $id_serveur, $id_lic);
    $stmt->execute();
    $stmt->close();

    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

// --- RÉCUPÉRATION DES LICENCES DU CLIENT ---
$res = $conn->query("SELECT * FROM lic WHERE id_client = $client_id ORDER BY id");

// --- RÉCUPÉRATION DES DONNÉES STRUCTURÉES POUR LES LISTES ---
$lic_data_res = $conn->query("SELECT editeur, modele, opt, version FROM lic");
$lic_structure = []; // Éditeur → Modèle → Options/Versions
$editeurs = [];

while ($row = $lic_data_res->fetch_assoc()) {
    $ed = trim($row['editeur']);
    $mod = trim($row['modele']);
    $opt = trim($row['opt']);
    $ver = trim($row['version']);

    // Ajouter seulement si non vide
    if (!empty($ed) && !in_array($ed, $editeurs)) {
        $editeurs[] = $ed;
    }

    if (!empty($ed)) {
        if (!isset($lic_structure[$ed])) $lic_structure[$ed] = [];
        if (!empty($mod)) {
            if (!isset($lic_structure[$ed][$mod])) $lic_structure[$ed][$mod] = ['opt' => [], 'version' => []];

            if (!empty($opt) && !in_array($opt, $lic_structure[$ed][$mod]['opt'])) {
                $lic_structure[$ed][$mod]['opt'][] = $opt;
            }
            if (!empty($ver) && !in_array($ver, $lic_structure[$ed][$mod]['version'])) {
                $lic_structure[$ed][$mod]['version'][] = $ver;
            }
        }
    }
}

// Trier les éditeurs en ASCII insensible à la casse
usort($editeurs, 'strcasecmp');

// Trier les modèles, options et versions pour chaque éditeur
foreach ($editeurs as $ed) {
    if (isset($lic_structure[$ed])) {
        // Trier les modèles
        $modeles = array_keys($lic_structure[$ed]);
        usort($modeles, 'strcasecmp');

        $sorted_structure = [];
        foreach ($modeles as $mod) {
            // Trier les options et versions
            $opts = $lic_structure[$ed][$mod]['opt'];
            $vers = $lic_structure[$ed][$mod]['version'];

            usort($opts, 'strcasecmp');
            usort($vers, 'strcasecmp');

            $sorted_structure[$mod] = ['opt' => $opts, 'version' => $vers];
        }
        $lic_structure[$ed] = $sorted_structure;
    }
}

// --- RÉCUPÉRATION DES ORDIS DU CLIENT ---
$ordis_res = $conn->query("SELECT id, nom FROM ordis WHERE id_client = $client_id ORDER BY nom");
$ordis = [];
while ($row = $ordis_res->fetch_assoc()) {
    $ordis[$row['id']] = $row['nom'];
}

// --- BOUTON AJOUTER LICENCE ---
?>
<div style="margin-bottom: 20px;">
    <form method="post" style="display: inline;">
        <input type="hidden" name="add_licence" value="1">
        <button type="submit" class="btn btn-primary">➕ Ajouter une licence</button>
    </form>
</div>

<?php
if ($res->num_rows === 0): ?>
    <div class="alert alert-warning">Aucune licence enregistrée.</div>
<?php else:
    while ($lic = $res->fetch_assoc()):
        $bgColor = ($lic['temp'] === 'oui') ? '#fff9c4' : '#e6f4e6';

        // URL pour le navigateur
        $lic_url = "/data/inter/$client_id/lic_{$lic['id']}.lic";
        $cert_url = "/data/certif/$client_id/cert_{$lic['id']}.pdf";

        // Chemin physique pour vérifier l'existence
        $lic_file = $upload_dir_lic . "lic_{$lic['id']}.lic";
        $cert_file = $upload_dir_cert . "cert_{$lic['id']}.pdf";
?>
<form method="post" style="background-color: <?= $bgColor ?>; padding: 15px; margin-bottom: 15px; border-radius: 5px; border: 1px solid #ddd;" enctype="multipart/form-data" onsubmit="copySelectValues(event, <?= $lic['id'] ?>)">
    <input type="hidden" name="id_lic" value="<?= $lic['id'] ?>">
    <input type="hidden" name="update_licence" value="1">
    <input type="hidden" id="modele_hidden_<?= $lic['id'] ?>" value="<?= htmlspecialchars($lic['modele']) ?>">
    <input type="hidden" id="opt_hidden_<?= $lic['id'] ?>" value="<?= htmlspecialchars($lic['opt']) ?>">
    <input type="hidden" id="version_hidden_<?= $lic['id'] ?>" value="<?= htmlspecialchars($lic['version']) ?>">
    <input type="hidden" name="modele" id="modele_input_<?= $lic['id'] ?>" value="">
    <input type="hidden" name="opt" id="opt_input_<?= $lic['id'] ?>" value="">
    <input type="hidden" name="version" id="version_input_<?= $lic['id'] ?>" value="">

    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px;">
        <div style="display: flex; align-items: center; gap: 10px;">
            <strong style="color: green; font-size: 16px;">★<?= htmlspecialchars($lic['editeur']) ?></strong>
            <select name="temp" class="form-control custom-select temp-select" style="width: auto; display: inline-block;" data-lic-id="<?= $lic['id'] ?>">
                <option value="definitive" <?= $lic['temp'] === 'non' ? 'selected' : '' ?>>définitive</option>
                <option value="temporaire" <?= $lic['temp'] === 'oui' ? 'selected' : '' ?>>temporaire</option>
            </select>
        </div>
<div style="display: flex; gap: 10px; align-items: center;">
    <button type="submit" class="btn btn-success">Enregistrer</button>

    <!-- Bouton de suppression : crée et soumet un form POST sans imbriquer -->
    <button type="button" class="btn btn-danger" onclick="confirmAndDeleteLicence(<?= $lic['id'] ?>)">
        🗑️ Supprimer
    </button>
</div>
    </div>

    <!-- LOGICIEL -->
    <div style="margin-bottom: 15px;">
        <label><strong>Logiciel</strong></label><br>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <!-- ÉDITEUR -->
            <select name="editeur" id="editeur_<?= $lic['id'] ?>" class="form-control custom-select editeur-select" style="flex: 1; min-width: 150px;" data-lic-id="<?= $lic['id'] ?>">
                <option value="">-- Éditeur --</option>
                <?php foreach ($editeurs as $ed): ?>
                    <option value="<?= htmlspecialchars($ed) ?>" <?= ($lic['editeur'] === $ed) ? 'selected' : '' ?>><?= htmlspecialchars($ed) ?></option>
                <?php endforeach; ?>
            </select>

            <!-- MODÈLE (dépend de l'éditeur) -->
            <select id="modele_<?= $lic['id'] ?>" class="form-control custom-select modele-select" style="flex: 1; min-width: 150px;" data-lic-id="<?= $lic['id'] ?>">
                <option value="">-- Modèle --</option>
            </select>

            <!-- OPTION (dépend du modèle) -->
            <select id="opt_<?= $lic['id'] ?>" class="form-control custom-select opt-select" style="flex: 1; min-width: 150px;" data-lic-id="<?= $lic['id'] ?>">
                <option value="">-- Option --</option>
            </select>

            <!-- VERSION (dépend du modèle) -->
            <select id="version_<?= $lic['id'] ?>" class="form-control custom-select version-select" style="flex: 1; min-width: 150px;" data-lic-id="<?= $lic['id'] ?>">
                <option value="">-- Version --</option>
            </select>
        </div>
    </div>

    <!-- DATE EXPIRATION SI TEMPORAIRE -->
    <?php if($lic['temp'] === 'oui'): ?>
    <div class="date-expiration" style="margin-bottom: 15px;">
        <label><strong>Date d'expiration</strong></label><br>
        <input type="date" name="date_expiration" class="form-control" value="<?= htmlspecialchars($lic['date_expiration'] ?? '') ?>" style="width: auto;">
    </div>
    <?php endif; ?>

    <!-- ACTIVATION -->
    <div style="margin-bottom: 15px;">
        <label><strong>Activation</strong></label><br>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <select name="dongle_type" class="form-control custom-select" style="flex: 1; min-width: 150px;">
                <?php
                $types = ["Aucun", "Clef USB", "Dongle HASP", "Dongle HASP HL", "Dongle crypToken", "Host", "Périphérique", "Sentinel"];
                foreach ($types as $type) {
                    $selected = ($lic['dongle_type'] === $type) ? 'selected' : '';
                    echo "<option value=\"" . htmlspecialchars($type) . "\" $selected>" . htmlspecialchars($type) . "</option>";
                }
                ?>
            </select>
            <input type="text" name="dongle_id" class="form-control" value="<?= htmlspecialchars($lic['dongle_id']) ?>" style="flex: 1; min-width: 150px;" placeholder="ID Dongle" />
            <input type="text" name="eac" class="form-control" value="<?= htmlspecialchars($lic['eac']) ?>" style="flex: 1; min-width: 150px;" placeholder="Code Activation" />

            <!-- MENU DEROULANT ORDI AVEC NOM DES ORDIS -->
            <select name="serveur" class="form-control custom-select" style="flex: 1; min-width: 150px;">
                <option value="">-- Sélectionner ordinateur --</option>
                <?php foreach($ordis as $id_ordi => $nom_ordi): ?>
                    <option value="<?= $id_ordi ?>" <?= ($lic['id_serveur'] ?? '') == $id_ordi ? 'selected' : '' ?>>
                        <?= htmlspecialchars($nom_ordi) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- BOUTONS LIC / CERT -->
    <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
        <div>
            <label><strong>Lic</strong></label><br>
            <input type="file" name="lic_file" style="display:none;" id="lic_file_input_<?= $lic['id'] ?>" onchange="submitUploadForm(event, 'upload_lic', <?= $lic['id'] ?>)">
            <button type="button" class="btn btn-info btn-sm" onclick="document.getElementById('lic_file_input_<?= $lic['id'] ?>').click();" title="Ajouter/Uploader licence">⬆️</button>
            <?php if(file_exists($lic_file)): ?>
                <a href="<?= $lic_url ?>" class="btn btn-success btn-sm" download title="Télécharger licence">⬇️</a>
                <button type="button" class="btn btn-danger btn-sm" onclick="submitUploadForm(null, 'delete_lic', <?= $lic['id'] ?>);" title="Supprimer licence">🗑️</button>
            <?php endif; ?>
        </div>

        <div>
            <label><strong>Cert</strong></label><br>
            <input type="file" name="cert_file" style="display:none;" id="cert_file_input_<?= $lic['id'] ?>" onchange="submitUploadForm(event, 'upload_cert', <?= $lic['id'] ?>)">
            <button type="button" class="btn btn-info btn-sm" onclick="document.getElementById('cert_file_input_<?= $lic['id'] ?>').click();" title="Ajouter/Uploader certificat">⬆️</button>
            <?php if(file_exists($cert_file)): ?>
                <a href="<?= $cert_url ?>" class="btn btn-success btn-sm" download title="Télécharger certificat">⬇️</a>
                <button type="button" class="btn btn-danger btn-sm" onclick="submitUploadForm(null, 'delete_cert', <?= $lic['id'] ?>);" title="Supprimer certificat">🗑️</button>
            <?php endif; ?>
        </div>
    </div>

</form>
<?php endwhile; ?>

<style>
.custom-select {
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    background-color: #fff;
    border: 1px solid #ccc;
    padding: 6px 8px;
    border-radius: 4px;
    position: relative;
}
.custom-select:focus {
    outline: none;
    border-color: #4CAF50;
}
</style>

<script>
// Structure des données côté client (générée par PHP)
const licStructure = <?= json_encode($lic_structure) ?>;

// Évenement changement d'éditeur → remplir les modèles
document.querySelectorAll('.editeur-select').forEach(select => {
    select.addEventListener('change', function() {
        const licId = this.dataset.licId;
        const editeur = this.value;
        const modeleSelect = document.getElementById('modele_' + licId);
        const optSelect = document.getElementById('opt_' + licId);
        const versionSelect = document.getElementById('version_' + licId);

        // Réinitialiser les selects
        modeleSelect.innerHTML = '<option value="">-- Modèle --</option>';
        optSelect.innerHTML = '<option value="">-- Option --</option>';
        versionSelect.innerHTML = '<option value="">-- Version --</option>';

        if (editeur && licStructure[editeur]) {
            // Remplir les modèles
            Object.keys(licStructure[editeur]).forEach(modele => {
                const option = document.createElement('option');
                option.value = modele;
                option.textContent = modele;
                modeleSelect.appendChild(option);
            });
        }
    });
});

// Évenement changement de modèle → remplir options et versions
document.querySelectorAll('.modele-select').forEach(select => {
    select.addEventListener('change', function() {
        const licId = this.dataset.licId;
        const editeur = document.getElementById('editeur_' + licId).value;
        const modele = this.value;
        const optSelect = document.getElementById('opt_' + licId);
        const versionSelect = document.getElementById('version_' + licId);

        // Réinitialiser
        optSelect.innerHTML = '<option value="">-- Option --</option>';
        versionSelect.innerHTML = '<option value="">-- Version --</option>';

        if (editeur && modele && licStructure[editeur] && licStructure[editeur][modele]) {
            const data = licStructure[editeur][modele];

            // Remplir les options
            data.opt.forEach(opt => {
                const option = document.createElement('option');
                option.value = opt;
                option.textContent = opt;
                optSelect.appendChild(option);
            });

            // Remplir les versions
            data.version.forEach(ver => {
                const option = document.createElement('option');
                option.value = ver;
                option.textContent = ver;
                versionSelect.appendChild(option);
            });
        }
    });
});

// Initialiser les selects au chargement de la page
document.querySelectorAll('.editeur-select').forEach(select => {
    select.dispatchEvent(new Event('change'));
});

// Restaurer les valeurs sauvegardées depuis la base de données
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.editeur-select').forEach(editeurSelect => {
        const licId = editeurSelect.dataset.licId;
        const editeur = editeurSelect.value;
        const modeleHidden = document.getElementById('modele_hidden_' + licId);
        const optHidden = document.getElementById('opt_hidden_' + licId);
        const versionHidden = document.getElementById('version_hidden_' + licId);

        const savedModele = modeleHidden.value;
        const savedOpt = optHidden.value;
        const savedVersion = versionHidden.value;

        if (editeur && savedModele) {
            // Remplir le select modèle
            const modeleSelect = document.getElementById('modele_' + licId);
            modeleSelect.innerHTML = '<option value="">-- Modèle --</option>';

            if (licStructure[editeur]) {
                Object.keys(licStructure[editeur]).forEach(modele => {
                    const option = document.createElement('option');
                    option.value = modele;
                    option.textContent = modele;
                    modeleSelect.appendChild(option);
                });
                modeleSelect.value = savedModele;
            }

            // Remplir les selects opt et version
            if (licStructure[editeur] && licStructure[editeur][savedModele]) {
                const data = licStructure[editeur][savedModele];
                const optSelect = document.getElementById('opt_' + licId);
                const versionSelect = document.getElementById('version_' + licId);

                optSelect.innerHTML = '<option value="">-- Option --</option>';
                data.opt.forEach(opt => {
                    const option = document.createElement('option');
                    option.value = opt;
                    option.textContent = opt;
                    optSelect.appendChild(option);
                });
                optSelect.value = savedOpt;

                versionSelect.innerHTML = '<option value="">-- Version --</option>';
                data.version.forEach(ver => {
                    const option = document.createElement('option');
                    option.value = ver;
                    option.textContent = ver;
                    versionSelect.appendChild(option);
                });
                versionSelect.value = savedVersion;
            }
        }
    });
});

function copySelectValues(event, licId) {
    const modeleSelect = document.getElementById('modele_' + licId);
    const optSelect = document.getElementById('opt_' + licId);
    const versionSelect = document.getElementById('version_' + licId);

    // Copier les valeurs dans les inputs qui seront envoyés
    document.getElementById('modele_input_' + licId).value = modeleSelect.value;
    document.getElementById('opt_input_' + licId).value = optSelect.value;
    document.getElementById('version_input_' + licId).value = versionSelect.value;

    return true;
}

// Auto-update du changement temp (definitive/temporaire) - AJAX
document.querySelectorAll('.temp-select').forEach(select => {
    select.addEventListener('change', function() {
        const licId = this.dataset.licId;
        const tempValue = this.value === 'temporaire' ? 'oui' : 'non';
        const form = this.closest('form');

        // Préparer les données
        const formData = new FormData();
        formData.append('id_lic', licId);
        formData.append('temp_only', '1');
        formData.append('temp', tempValue);

        // Envoyer en AJAX
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (response.ok) {
                // Changer la couleur de fond du formulaire
                form.style.backgroundColor = tempValue === 'oui' ? '#fff9c4' : '#e6f4e6';

                // Afficher/masquer le champ date d'expiration dynamiquement
                const dateDiv = form.querySelector('.date-expiration');
                if (tempValue === 'oui') {
                    // Créer le champ date s'il n'existe pas
                    if (!dateDiv) {
                        const newDiv = document.createElement('div');
                        newDiv.className = 'date-expiration';
                        newDiv.style.marginBottom = '15px';
                        newDiv.innerHTML = '<label><strong>Date d\'expiration</strong></label><br><input type="date" name="date_expiration" class="form-control" style="width: auto;">';
                        form.querySelector('[name="dongle_type"]').parentElement.parentElement.parentElement.insertBefore(newDiv, form.querySelector('[name="dongle_type"]').parentElement.parentElement);
                    } else {
                        dateDiv.style.display = 'block';
                    }
                } else {
                    // Masquer le champ date
                    if (dateDiv) {
                        dateDiv.style.display = 'none';
                    }
                }
            }
        });
    });
});
function confirmAndDeleteLicence(licId) {
    if (confirm('Êtes-vous sûr de vouloir supprimer cette licence ? Cette action est irréversible.')) {
        const form = document.createElement('form');
        form.method = 'POST';

        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'id_lic';
        idInput.value = licId;
        form.appendChild(idInput);

        const deleteInput = document.createElement('input');
        deleteInput.type = 'hidden';
        deleteInput.name = 'delete_full_lic';
        deleteInput.value = '1';
        form.appendChild(deleteInput);

        document.body.appendChild(form);
        form.submit();
    }
}

function submitUploadForm(event, action, licId) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.enctype = 'multipart/form-data';

    const idInput = document.createElement('input');
    idInput.type = 'hidden';
    idInput.name = 'id_lic';
    idInput.value = licId;
    form.appendChild(idInput);

    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = action;
    form.appendChild(actionInput);

    if (action.includes('upload') && event) {
        const fileInput = document.createElement('input');
        fileInput.type = 'file';
        fileInput.name = action === 'upload_lic' ? 'lic_file' : 'cert_file';

        const file = event.target.files[0];
        if (file) {
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(file);
            fileInput.files = dataTransfer.files;
        }
        form.appendChild(fileInput);
    }

    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}
</script>

<?php endif; ?>


            <?php elseif ($page === 'papiers'): ?>
                <h3>Papiers</h3>

                <?php
                $client_id = $client['id'];
                $formats = ['A4', 'A4+', 'A3', 'A3+', 'A2', 'A2+', '14"', '17"', '24"', '36"', '42"', '44"'];

                // AJOUTER UN NOUVEAU PAPIER VIDE
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_papier') {
                    $modif_auteur = htmlspecialchars($_SESSION['username']);
                    $sqlInsert = "INSERT INTO papiers (id_client, marque, modele, taille, id_imp, notes, crea_date, crea_auteur, modif_date, modif_auteur)
                      VALUES (?, '', '', '', '', '', '0000-00-00', 1, NOW(), '?')";
                    $stmtInsert = $conn->prepare($sqlInsert);
                    $stmtInsert->bind_param("i", $client_id);
                    $stmtInsert->execute();
                    $stmtInsert->close();

                    header("Location: " . $_SERVER['REQUEST_URI']);
                    exit;
                }

                // ENREGISTREMENT FORMULAIRE DE MODIFICATION
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id']) && !isset($_POST['action'])) {
                    $id = intval($_POST['id']);
                    $marque = $_POST['marque'] ?? '';
                    $modele = $_POST['modele'] ?? '';
                    $notes = $_POST['notes'] ?? '';
                    $id_imprimantes = $_POST['id_imp'] ?? []; // Tableau d'imprimantes liées (plusieurs)

                    // On stocke les id imprimantes séparés par une étoile, exemple : "3*7*12"
                    if (is_array($id_imprimantes)) {
                        $id_imp_str = implode('*', array_filter($id_imprimantes, fn($v) => $v !== ''));
                    } else {
                        $id_imp_str = ($id_imprimantes !== '') ? $id_imprimantes : '';
                    }

                    $tailles_array = $_POST['tailles'] ?? [];
                    $taille = implode('*', $tailles_array);

                    $modif_date = date("Y-m-d H:i:s");
                    $modif_auteur = htmlspecialchars($_SESSION['username']);

                    $sql = "UPDATE papiers
                SET marque=?, modele=?, taille=?, id_imp=?, notes=?, modif_date=?, modif_auteur=?
                WHERE id=? AND id_client=?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ssssssiii", $marque, $modele, $taille, $id_imp_str, $notes, $modif_date, $modif_auteur, $id, $client_id);
                    $stmt->execute();
                    $stmt->close();

                    header("Location: " . $_SERVER['REQUEST_URI']);
                    exit;
                }

                // CHARGEMENT DES PAPIERS
                $res = $conn->query("SELECT * FROM papiers WHERE id_client = $client_id ORDER BY id DESC");

                if ($res->num_rows === 0): ?>
                    <div class="alert alert-warning">Aucun papier enregistré.</div>
                        <form method="post" style="margin-bottom: 15px;">
                        <input type="hidden" name="action" value="add_papier">
                        <button type="submit" class="btn btn-success">+ Ajouter un papier</button>
                    </form>
                <?php else:
                    // Imprimantes disponibles pour ce client
                    $imp_res = $conn->prepare("SELECT id, nom FROM imprimantes WHERE id_client = ? ORDER BY nom");
                    $imp_res->bind_param("i", $client_id);
                    $imp_res->execute();
                    $imprimantes = $imp_res->get_result();
                    $imps = [];
                    while ($row = $imprimantes->fetch_assoc()) {
                        $imps[] = $row;
                    }
                    $imp_res->close();

                    // Marques disponibles
                    $marques_res = $conn->query("SELECT DISTINCT marque FROM papiers WHERE marque != '' AND marque NOT LIKE '%*%' ORDER BY marque");
                    $marques = [];
                    while ($row = $marques_res->fetch_assoc()) {
                        $marques[] = $row['marque'];
                    }
                ?>

                    <form method="post" style="margin-bottom: 15px;">
                        <input type="hidden" name="action" value="add_papier">
                        <button type="submit" class="btn btn-success">+ Ajouter un papier</button>
                    </form>

                    <?php while ($papier = $res->fetch_assoc()):
                        $selected_sizes = explode('*', $papier['taille']);
                        $selected_imps = ($papier['id_imp'] !== '') ? explode('*', $papier['id_imp']) : [];

                        // Produits liés à la marque
                        $produits_stmt = $conn->prepare("SELECT id, modele FROM produits WHERE marque = ? ORDER BY modele");
                        $produits_stmt->bind_param("s", $papier['marque']);
                        $produits_stmt->execute();
                        $produits = $produits_stmt->get_result();
                    ?>

                        <div class="card my-3" style="border: 1px solid #ddd; padding: 15px; border-radius: 8px; background: #f9f9f9;">
                            <div class="card-body">
                                <form method="post" action="">
                                    <input type="hidden" name="id" value="<?= $papier['id'] ?>">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <label>Marque</label>
                                            <select name="marque" class="form-control" onchange="this.form.submit();">
                                                <?php foreach ($marques as $marque): ?>
                                                    <option value="<?= htmlspecialchars($marque) ?>" <?= ($papier['marque'] == $marque ? 'selected' : '') ?>>
                                                        <?= htmlspecialchars($marque) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
<div class="col-md-3">
    <label>Modèle</label>
    <select name="modele" class="form-control">
        <?php
            $modeles_affiches = array();
            while ($prod = $produits->fetch_assoc()):
                if (!in_array($prod['modele'], $modeles_affiches)):
                    $modeles_affiches[] = $prod['modele'];
        ?>
            <option value="<?= htmlspecialchars($prod['modele']) ?>" <?= ($papier['modele'] == $prod['modele'] ? 'selected' : '') ?>>
                <?= htmlspecialchars($prod['modele']) ?>
            </option>
        <?php
                endif;
            endwhile;
        ?>
    </select>
</div>

                                        <div class="col-md-3">
                                            <label>Taille</label>
                                            <div id="selected-formats-<?= $papier['id'] ?>" style="display: flex; flex-wrap: wrap; gap: 5px; min-height: 36px; border: 1px solid #ccc; padding: 5px; border-radius: 4px; background: #f9f9f9;">
                                                <?php foreach ($selected_sizes as $selected): if (trim($selected) !== ''): ?>
                                                        <span class="badge badge-primary selected-format"
                                                            data-value="<?= htmlspecialchars($selected) ?>"
                                                            style="cursor: pointer; padding: 5px 10px; border-radius: 4px; user-select: none; background-color: #007bff; color: white;">
                                                            <?= htmlspecialchars($selected) ?> ×
                                                            <input type="hidden" name="tailles[]" value="<?= htmlspecialchars($selected) ?>">
                                                        </span>
                                                <?php endif;
                                                endforeach; ?>
                                            </div>
                                            <select id="formats-select-<?= $papier['id'] ?>" style="margin-top: 5px;" class="form-control">
                                                <option value="">-- Choisir une taille --</option>
                                                <?php foreach ($formats as $format): ?>
                                                    <?php if (!in_array($format, $selected_sizes)): ?>
                                                        <option value="<?= htmlspecialchars($format) ?>"><?= htmlspecialchars($format) ?></option>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="col-md-3">
                                            <label>Imprimantes liées</label>
                                            <div id="selected-imps-<?= $papier['id'] ?>" style="display: flex; flex-wrap: wrap; gap: 5px; min-height: 36px; border: 1px solid #ccc; padding: 5px; border-radius: 4px; background: #f9f9f9;">
                                                <?php foreach ($selected_imps as $imp_id):
                                                    $imp_name = '';
                                                    foreach ($imps as $imp) {
                                                        if ($imp['id'] == $imp_id) {
                                                            $imp_name = $imp['nom'];
                                                            break;
                                                        }
                                                    }
                                                    if ($imp_name !== ''):
                                                ?>
                                                        <span class="badge badge-success selected-imp"
                                                            data-id="<?= $imp_id ?>"
                                                            style="cursor: pointer; padding: 5px 10px; border-radius: 4px; user-select: none; background-color: #28a745; color: white;">
                                                            <?= htmlspecialchars($imp_name) ?> ×
                                                            <input type="hidden" name="id_imp[]" value="<?= $imp_id ?>">
                                                        </span>
                                                <?php
                                                    endif;
                                                endforeach; ?>
                                            </div>
                                            <select id="imps-select-<?= $papier['id'] ?>" style="margin-top: 5px;" class="form-control">
                                                <option value="">-- Choisir une imprimante --</option>
                                                <?php foreach ($imps as $imp): ?>
                                                    <?php if (!in_array($imp['id'], $selected_imps)): ?>
                                                        <option value="<?= $imp['id'] ?>"><?= htmlspecialchars($imp['nom']) ?></option>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="col-md-12 mt-2">
                                            <label>Notes</label>
                                            <input type="text" name="notes" class="form-control" value="<?= htmlspecialchars($papier['notes']) ?>">
                                        </div>
                                    </div>

                                    <div class="mt-2 d-flex" style="justify-content: space-between;">
                                        <button type="submit" class="btn btn-primary">Enregistrer</button>
                                        <a href="delete_papier.php?id=<?= $papier['id'] ?>&redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>"
                                            class="btn btn-danger btn-sm"
                                            onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce papier ?');">
                                            Supprimer
                                        </a>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <script>
                            (function() {
                                const selectSizes = document.getElementById('formats-select-<?= $papier['id'] ?>');
                                const selectedSizesContainer = document.getElementById('selected-formats-<?= $papier['id'] ?>');

                                const selectImps = document.getElementById('imps-select-<?= $papier['id'] ?>');
                                const selectedImpsContainer = document.getElementById('selected-imps-<?= $papier['id'] ?>');

                                function createBadge(value) {
                                    const span = document.createElement('span');
                                    span.className = 'badge badge-primary selected-format';
                                    span.dataset.value = value;
                                    span.style.cursor = 'pointer';
                                    span.style.padding = '5px 10px';
                                    span.style.borderRadius = '4px';
                                    span.style.userSelect = 'none';
                                    span.style.backgroundColor = '#007bff';
                                    span.style.color = 'white';
                                    span.textContent = value + ' ×';

                                    const input = document.createElement('input');
                                    input.type = 'hidden';
                                    input.name = 'tailles[]';
                                    input.value = value;
                                    span.appendChild(input);

                                    span.addEventListener('click', () => {
                                        if (span.classList.contains('to-delete')) {
                                            span.classList.remove('to-delete');
                                            span.style.backgroundColor = '#007bff';
                                            span.style.color = 'white';
                                            if (!span.querySelector('input')) {
                                                const inp = document.createElement('input');
                                                inp.type = 'hidden';
                                                inp.name = 'tailles[]';
                                                inp.value = value;
                                                span.appendChild(inp);
                                            }
                                        } else {
                                            span.classList.add('to-delete');
                                            span.style.backgroundColor = '#dc3545';
                                            span.style.color = 'white';
                                            const inp = span.querySelector('input');
                                            if (inp) inp.remove();
                                        }
                                    });

                                    return span;
                                }

                                function createImpBadge(id, name) {
                                    const span = document.createElement('span');
                                    span.className = 'badge badge-success selected-imp';
                                    span.dataset.id = id;
                                    span.style.cursor = 'pointer';
                                    span.style.padding = '5px 10px';
                                    span.style.borderRadius = '4px';
                                    span.style.userSelect = 'none';
                                    span.style.backgroundColor = '#28a745';
                                    span.style.color = 'white';
                                    span.textContent = name + ' ×';

                                    const input = document.createElement('input');
                                    input.type = 'hidden';
                                    input.name = 'id_imp[]';
                                    input.value = id;
                                    span.appendChild(input);

                                    span.addEventListener('click', () => {
                                        if (span.classList.contains('to-delete')) {
                                            span.classList.remove('to-delete');
                                            span.style.backgroundColor = '#28a745';
                                            span.style.color = 'white';
                                            if (!span.querySelector('input')) {
                                                const inp = document.createElement('input');
                                                inp.type = 'hidden';
                                                inp.name = 'id_imp[]';
                                                inp.value = id;
                                                span.appendChild(inp);
                                            }
                                        } else {
                                            span.classList.add('to-delete');
                                            span.style.backgroundColor = '#dc3545';
                                            span.style.color = 'white';
                                            const inp = span.querySelector('input');
                                            if (inp) inp.remove();
                                        }
                                    });

                                    return span;
                                }

                                selectSizes.addEventListener('change', () => {
                                    const val = selectSizes.value;
                                    if (!val) return;
                                    const badge = createBadge(val);
                                    selectedSizesContainer.appendChild(badge);

                                    const optToRemove = Array.from(selectSizes.options).find(o => o.value === val);
                                    if (optToRemove) optToRemove.remove();

                                    selectSizes.value = '';
                                });

                                selectImps.addEventListener('change', () => {
                                    const val = selectImps.value;
                                    if (!val) return;
                                    const name = selectImps.options[selectImps.selectedIndex].text;
                                    const badge = createImpBadge(val, name);
                                    selectedImpsContainer.appendChild(badge);

                                    const optToRemove = Array.from(selectImps.options).find(o => o.value === val);
                                    if (optToRemove) optToRemove.remove();

                                    selectImps.value = '';
                                });

                                Array.from(selectedSizesContainer.querySelectorAll('.selected-format')).forEach(badge => {
                                    badge.addEventListener('click', () => {
                                        if (badge.classList.contains('to-delete')) {
                                            badge.classList.remove('to-delete');
                                            badge.style.backgroundColor = '#007bff';
                                            badge.style.color = 'white';
                                            if (!badge.querySelector('input')) {
                                                const inp = document.createElement('input');
                                                inp.type = 'hidden';
                                                inp.name = 'tailles[]';
                                                inp.value = badge.dataset.value;
                                                badge.appendChild(inp);
                                            }
                                        } else {
                                            badge.classList.add('to-delete');
                                            badge.style.backgroundColor = '#dc3545';
                                            badge.style.color = 'white';
                                            const inp = badge.querySelector('input');
                                            if (inp) inp.remove();
                                        }
                                    });
                                });

                                Array.from(selectedImpsContainer.querySelectorAll('.selected-imp')).forEach(badge => {
                                    badge.addEventListener('click', () => {
                                        if (badge.classList.contains('to-delete')) {
                                            badge.classList.remove('to-delete');
                                            badge.style.backgroundColor = '#28a745';
                                            badge.style.color = 'white';
                                            if (!badge.querySelector('input')) {
                                                const inp = document.createElement('input');
                                                inp.type = 'hidden';
                                                inp.name = 'id_imp[]';
                                                inp.value = badge.dataset.id;
                                                badge.appendChild(inp);
                                            }
                                        } else {
                                            badge.classList.add('to-delete');
                                            badge.style.backgroundColor = '#dc3545';
                                            badge.style.color = 'white';
                                            const inp = badge.querySelector('input');
                                            if (inp) inp.remove();
                                        }
                                    });
                                });

                                const form = selectedSizesContainer.closest('form');
                                form.addEventListener('submit', () => {
                                    selectedSizesContainer.querySelectorAll('.to-delete').forEach(badge => {
                                        const val = badge.dataset.value;
                                        if (!Array.from(selectSizes.options).some(opt => opt.value === val)) {
                                            const option = document.createElement('option');
                                            option.value = val;
                                            option.text = val;
                                            selectSizes.appendChild(option);
                                        }
                                        badge.remove();
                                    });

                                    selectedImpsContainer.querySelectorAll('.to-delete').forEach(badge => {
                                        const id = badge.dataset.id;
                                        const name = badge.textContent.slice(0, -2);
                                        if (!Array.from(selectImps.options).some(opt => opt.value === id)) {
                                            const option = document.createElement('option');
                                            option.value = id;
                                            option.text = name;
                                            selectImps.appendChild(option);
                                        }
                                        badge.remove();
                                    });
                                });
                            })();
                        </script>

                <?php
                    endwhile;
                endif;
                ?>

<?php elseif ($page === 'stockage'):
    // Fonctions de conversion de dates
    function convertDateToInput($dateStr) {
        if (empty($dateStr) || $dateStr === ' ') {
            return '';
        }
        // Extraire juste la date (avant l'heure s'il y en a)
        $parts = explode(' ', $dateStr);
        $dateOnly = $parts[0];

        $date = \DateTime::createFromFormat('d/m/Y', $dateOnly);
        if ($date) {
            return $date->format('Y-m-d');
        }
        $date = \DateTime::createFromFormat('Y-m-d', $dateOnly);
        if ($date) {
            return $date->format('Y-m-d');
        }
        return '';
    }

    function convertInputToDate($dateStr) {
        if (empty($dateStr)) {
            return ' ';
        }
        $date = \DateTime::createFromFormat('Y-m-d', $dateStr);
        if ($date) {
            // Ajouter l'heure actuelle au format d/m/Y H:i:s
            return $date->format('d/m/Y') . ' ' . date('H:i:s');
        }
        return ' ';
    }

    // Traiter les requêtes AJAX directement
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        header('Content-Type: application/json; charset=utf-8');
        ob_clean();

        if ($_POST['action'] === 'save_stockage') {
            try {
                $id = $_POST['id'] ?? null;
                $nom = $_POST['nom'] ?? ' ';
                $marque = $_POST['marque'] ?? ' ';
                $modele = $_POST['modele'] ?? ' ';
                $id_ordi = $_POST['id_ordi'] ?? 0;
                $capacite = $_POST['capacite'] ?? ' ';
                $disques = $_POST['disques'] ?? ' ';
                $connectique = $_POST['connectique'] ?? ' ';
                $ip = $_POST['ip'] ?? ' ';
                $serial = $_POST['serial'] ?? ' ';
                $firmware = $_POST['firmware'] ?? ' ';
                $date_install_raw = $_POST['date_install'] ?? '';
                $date_install = convertInputToDate($date_install_raw);
                $notes = $_POST['notes'] ?? ' ';

                $id_ordi = $id_ordi ? (int)$id_ordi : 0;
                $crea_auteur = $_SESSION['user']['email'] ?? $_SESSION['user']['nom'] ?? 'system';
                $now = date('d/m/Y H:i:s');

                if ($id === 'new' || $id === '0' || $id === null) {
    $stmt = $conn->prepare("
        INSERT INTO stockage (id_client, id_ordi, nom, marque, modele, capacite, disques, connectique, ip, serial, firmware, date_install, notes, crea_date, crea_auteur, modif_date, modif_auteur)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        'iisssssssssssssss',
        $client['id'],
        $id_ordi,
        $nom,
        $marque,
        $modele,
        $capacite,
        $disques,
        $connectique,
        $ip,
        $serial,
        $firmware,
        $date_install,
        $notes,
        $now,
        $crea_auteur,
        $now,
        $crea_auteur
    );

    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'id' => $stmt->insert_id,
            'message' => 'Stockage créé avec succès'
        ]);
    } else {
        throw new Exception($stmt->error);
    }
    $stmt->close();
} else {
                    $id = (int)$id;

                    $check = $conn->query("SELECT id FROM stockage WHERE id = $id AND id_client = {$client['id']}");
                    if ($check->num_rows === 0) {
                        http_response_code(403);
                        echo json_encode(['error' => 'Accès refusé']);
                        exit;
                    }

                    $modif_auteur = $_SESSION['user']['email'] ?? $_SESSION['user']['nom'] ?? 'system';

                    // UPDATE - 14 colonnes à updater + 2 WHERE = 16 variables
                    $stmt = $conn->prepare("
                        UPDATE stockage
                        SET id_ordi = ?, nom = ?, marque = ?, modele = ?, capacite = ?,
                            disques = ?, connectique = ?, ip = ?, serial = ?, firmware = ?,
                            date_install = ?, notes = ?, modif_date = ?, modif_auteur = ?
                        WHERE id = ? AND id_client = ?
                    ");

                    $stmt->bind_param(
                        'isssssssssssssii',
                        $id_ordi,             // i
                        $nom,                 // s
                        $marque,              // s
                        $modele,              // s
                        $capacite,            // s
                        $disques,             // s
                        $connectique,         // s
                        $ip,                  // s
                        $serial,              // s
                        $firmware,            // s
                        $date_install,        // s
                        $notes,               // s
                        $now,                 // s (modif_date)
                        $modif_auteur,        // s
                        $id,                  // i
                        $client['id']         // i
                    );

                    if ($stmt->execute()) {
                        echo json_encode([
                            'success' => true,
                            'id' => $id,
                            'message' => 'Stockage mis à jour avec succès'
                        ]);
                    } else {
                        throw new Exception($stmt->error);
                    }
                    $stmt->close();
                }
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['error' => $e->getMessage()]);
            }
            exit;
        } elseif ($_POST['action'] === 'delete_stockage') {
            try {
                $id = (int)($_POST['id'] ?? 0);

                $check = $conn->query("SELECT id FROM stockage WHERE id = $id AND id_client = {$client['id']}");
                if ($check->num_rows === 0) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Accès refusé']);
                    exit;
                }

                $stmt = $conn->prepare("DELETE FROM stockage WHERE id = ? AND id_client = ?");
                $stmt->bind_param('ii', $id, $client['id']);

                if ($stmt->execute()) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Stockage supprimé avec succès'
                    ]);
                } else {
                    throw new Exception($stmt->error);
                }
                $stmt->close();
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['error' => $e->getMessage()]);
            }
            exit;
        }
    }
?>

<div class="stockage-container">
    <div class="stockage-header">
        <h2>Stockage</h2>
        <button class="btn-add-icon" onclick="addStockageCard()">+</button>
    </div>

    <div id="stockages-list">
        <?php
        $res = $conn->query("SELECT * FROM stockage WHERE id_client = {$client['id']} ORDER BY id");
        $storages = $res->fetch_all(MYSQLI_ASSOC);

        $fabricants = $conn->query("SELECT DISTINCT marque FROM stockage WHERE marque IS NOT NULL AND marque != '' ORDER BY marque")->fetch_all(MYSQLI_ASSOC);
        $ordis = $conn->query("SELECT id, nom FROM ordis WHERE id_client = {$client['id']} ORDER BY nom")->fetch_all(MYSQLI_ASSOC);

        $modelesByFabricant = [];
        foreach ($fabricants as $fab) {
            $modeles = $conn->query("SELECT DISTINCT modele FROM stockage WHERE marque = '{$fab['marque']}' AND modele IS NOT NULL AND modele != '' ORDER BY modele")->fetch_all(MYSQLI_ASSOC);
            $modelesByFabricant[$fab['marque']] = array_column($modeles, 'modele');
        }

        foreach ($storages as $index => $st):
        ?>
            <div class="stockage-card">
                <div class="card-header">
                    <h3>Stockage #<?= $index + 1 ?></h3>
                    <div class="card-actions">
                        <button class="btn btn-success" onclick="saveStockage(<?= $st['id'] ?>)">Enregistrer</button>
                        <button class="btn btn-danger" onclick="deleteStockage(<?= $st['id'] ?>)">Supprimer</button>
                    </div>
                </div>

                <div class="card-content">
                    <form class="stockage-form" data-id="<?= $st['id'] ?>">
                        <div class="left-section">
                            <div class="form-group">
                                <label>Nom *</label>
                                <input type="text" name="nom" value="<?= htmlspecialchars($st['nom']) ?>" required>
                            </div>

                            <div class="form-group">
                                <label>Fabricant *</label>
                                <select name="marque" onchange="updateModeles(this)" required>
                                    <option value="">-- Sélectionner --</option>
                                    <?php foreach ($fabricants as $fab): ?>
                                        <option value="<?= htmlspecialchars($fab['marque']) ?>" <?= $st['marque'] === $fab['marque'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($fab['marque']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Modèle *</label>
                                <select name="modele" required>
                                    <option value="">-- Sélectionner --</option>
                                    <?php
                                    if ($st['marque'] && isset($modelesByFabricant[$st['marque']])) {
                                        foreach ($modelesByFabricant[$st['marque']] as $mod):
                                    ?>
                                        <option value="<?= htmlspecialchars($mod) ?>" <?= $st['modele'] === $mod ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($mod) ?>
                                        </option>
                                    <?php
                                        endforeach;
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Ordinateur associé *</label>
                                <select name="id_ordi" required>
                                    <option value="">-- Sélectionner --</option>
                                    <?php foreach ($ordis as $ordi): ?>
                                        <option value="<?= $ordi['id'] ?>" <?= $st['id_ordi'] == $ordi['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($ordi['nom']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Capacité utile</label>
                                <input type="text" name="capacite" value="<?= htmlspecialchars($st['capacite']) ?>">
                            </div>

                            <div class="form-group">
                                <label>Disques</label>
                                <input type="text" name="disques" value="<?= htmlspecialchars($st['disques']) ?>">
                            </div>
                        </div>

                        <div class="right-section">
                            <div class="form-group">
                                <label>Connectique *</label>
                                <select name="connectique" required>
                                    <option value="">-- Sélectionner --</option>
                                    <option value="USB 2" <?= $st['connectique'] === 'USB 2' ? 'selected' : '' ?>>USB 2</option>
                                    <option value="USB 3" <?= $st['connectique'] === 'USB 3' ? 'selected' : '' ?>>USB 3</option>
                                    <option value="ThunderBolt" <?= $st['connectique'] === 'ThunderBolt' ? 'selected' : '' ?>>ThunderBolt</option>
                                    <option value="Ethernet" <?= $st['connectique'] === 'Ethernet' ? 'selected' : '' ?>>Ethernet</option>
                                    <option value="Autre" <?= $st['connectique'] === 'Autre' ? 'selected' : '' ?>>Autre</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Adresse IP</label>
                                <input type="text" name="ip" value="<?= htmlspecialchars($st['ip']) ?>">
                            </div>

                            <div class="form-group">
                                <label>Numéro de série</label>
                                <input type="text" name="serial" value="<?= htmlspecialchars($st['serial']) ?>">
                            </div>

                            <div class="form-group">
                                <label>Firmware</label>
                                <input type="text" name="firmware" value="<?= htmlspecialchars($st['firmware']) ?>">
                            </div>

                            <div class="form-group">
                                <label>Date d'installation</label>
                                <input type="date" name="date_install" value="<?= convertDateToInput($st['date_install']) ?>">
                            </div>

                            <div class="form-group">
                                <label>Notes</label>
                                <textarea name="notes"><?= htmlspecialchars($st['notes']) ?></textarea>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<style>
/* ===== SECTION STOCKAGE ===== */

.stockage-container {
    max-width: 1400px;
    margin: 0 auto;
}

.stockage-container h2 {
    color: #2c3e50;
    border-bottom: 3px solid #3498db;
    padding-bottom: 10px;
    margin-bottom: 30px;
    font-size: 28px;
}

.stockage-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    gap: 20px;
}

.stockage-header h2 {
    margin: 0;
    font-size: 28px;
    color: #2c3e50;
}

.btn-add-icon {
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, #28a745 0%, #218838 100%);
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 28px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    line-height: 1;
    box-shadow: 0 2px 8px rgba(40, 167, 69, 0.3);
    transition: all 0.3s ease;
    flex-shrink: 0;
}

.btn-add-icon:hover {
    background: linear-gradient(135deg, #218838 0%, #1e7e34 100%);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(40, 167, 69, 0.4);
}

.btn-add-icon:active {
    transform: translateY(0);
}

#stockages-list {
    display: flex;
    flex-direction: column;
    gap: 25px;
}

.stockage-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
    overflow: hidden;
    transition: all 0.3s ease;
}

.stockage-card:hover {
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    border-bottom: 1px solid #e0e0e0;
    background: linear-gradient(135deg, #f9f9f9 0%, #f0f0f0 100%);
}

.card-header h3 {
    margin: 0;
    font-size: 20px;
    color: #34495e;
    font-weight: 600;
}

.card-actions {
    display: flex;
    gap: 12px;
    align-items: center;
}

.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
    display: inline-block;
    transition: all 0.3s ease;
    text-align: center;
}

.btn-success {
    background: linear-gradient(135deg, #28a745 0%, #218838 100%);
    color: white;
    box-shadow: 0 2px 8px rgba(40, 167, 69, 0.3);
}

.btn-success:hover {
    background: linear-gradient(135deg, #218838 0%, #1e7e34 100%);
    box-shadow: 0 4px 12px rgba(40, 167, 69, 0.4);
    transform: translateY(-2px);
}

.btn-success:active {
    transform: translateY(0);
}

.btn-danger {
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
    color: white;
    box-shadow: 0 2px 8px rgba(220, 53, 69, 0.3);
}

.btn-danger:hover {
    background: linear-gradient(135deg, #c82333 0%, #bd2130 100%);
    box-shadow: 0 4px 12px rgba(220, 53, 69, 0.4);
    transform: translateY(-2px);
}

.btn-danger:active {
    transform: translateY(0);
}

.btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none !important;
    box-shadow: none;
}

.card-content {
    padding: 30px;
}

.stockage-form {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 40px;
}

.left-section,
.right-section {
    display: flex;
    flex-direction: column;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    color: #34495e;
    font-weight: 600;
    font-size: 14px;
}

.form-group input[type="text"],
.form-group input[type="email"],
.form-group input[type="date"],
.form-group input[type="number"],
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
    font-family: inherit;
    box-sizing: border-box;
    background: white;
    transition: all 0.3s ease;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #3498db;
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
}

.form-group select {
    cursor: pointer;
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23333' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 12px center;
    padding-right: 35px;
}

.form-group textarea {
    resize: vertical;
    min-height: 100px;
    font-family: inherit;
}

.notification {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 15px 20px;
    border-radius: 8px;
    color: white;
    z-index: 9999;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    animation: slideIn 0.3s ease-out;
    font-weight: 600;
    font-size: 14px;
}

.notification-success {
    background: linear-gradient(135deg, #28a745 0%, #218838 100%);
}

.notification-error {
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
}

@keyframes slideIn {
    from {
        transform: translateX(400px);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes slideOut {
    from {
        transform: translateX(0);
        opacity: 1;
    }
    to {
        transform: translateX(400px);
        opacity: 0;
    }
}

/* Responsive Design */
@media (max-width: 992px) {
    .stockage-form {
        grid-template-columns: 1fr;
        gap: 20px;
    }

    .card-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }

    .card-actions {
        width: 100%;
    }

    .btn {
        flex: 1;
        min-width: 120px;
    }
}

@media (max-width: 768px) {
    .stockage-container {
        padding: 0 15px;
    }

    .card-content {
        padding: 20px;
    }

    .card-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .card-header h3 {
        font-size: 18px;
    }

    .stockage-header {
        flex-wrap: wrap;
    }

    .btn-add-icon {
        width: 45px;
        height: 45px;
        font-size: 24px;
    }
}

@media (max-width: 600px) {
    .card-actions {
        flex-direction: column;
    }

    .btn {
        width: 100%;
    }

    .stockage-header {
        flex-direction: column;
        align-items: stretch;
    }

    .btn-add-icon {
        width: 100%;
    }

    .notification {
        left: 10px;
        right: 10px;
        top: 10px;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
        font-size: 16px;
    }
}
</style>

<script>
let cardCount = <?= count($storages) ?>;
const modelesByFabricant = <?= json_encode($modelesByFabricant) ?>;
const ordisData = <?= json_encode($ordis) ?>;

function updateModeles(selectElement) {
    const fabricant = selectElement.value;
    const form = selectElement.closest('form');
    const modeleSelect = form.querySelector('select[name="modele"]');

    modeleSelect.innerHTML = '<option value="">-- Sélectionner --</option>';

    if (fabricant && modelesByFabricant[fabricant]) {
        modelesByFabricant[fabricant].forEach(modele => {
            const option = document.createElement('option');
            option.value = modele;
            option.textContent = modele;
            modeleSelect.appendChild(option);
        });
    }
}

function addStockageCard() {
    cardCount++;
    const newCard = document.createElement('div');
    newCard.className = 'stockage-card';
    newCard.innerHTML = `
        <div class="card-header">
            <h3>Stockage #${cardCount}</h3>
            <div class="card-actions">
                <button type="button" class="btn btn-success" onclick="saveStockage(0)">Enregistrer</button>
                <button type="button" class="btn btn-danger" onclick="deleteStockageCard(this)">Supprimer</button>
            </div>
        </div>

        <div class="card-content">
            <form class="stockage-form" data-id="new">
                <div class="left-section">
                    <div class="form-group">
                        <label>Nom *</label>
                        <input type="text" name="nom" value="" required>
                    </div>

                    <div class="form-group">
                        <label>Fabricant *</label>
                        <select name="marque" onchange="updateModeles(this)" required>
                            <option value="">-- Sélectionner --</option>
                            ${Object.keys(modelesByFabricant).map(fab => `<option value="${fab}">${fab}</option>`).join('')}
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Modèle *</label>
                        <select name="modele" required>
                            <option value="">-- Sélectionner --</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Ordinateur associé *</label>
                        <select name="id_ordi" required>
                            <option value="">-- Sélectionner --</option>
                            ${ordisData.map(ordi => `<option value="${ordi.id}">${ordi.nom}</option>`).join('')}
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Capacité utile</label>
                        <input type="text" name="capacite" value="">
                    </div>

                    <div class="form-group">
                        <label>Disques</label>
                        <input type="text" name="disques" value="">
                    </div>
                </div>

                <div class="right-section">
                    <div class="form-group">
                        <label>Connectique *</label>
                        <select name="connectique" required>
                            <option value="">-- Sélectionner --</option>
                            <option value="USB 2">USB 2</option>
                            <option value="USB 3">USB 3</option>
                            <option value="ThunderBolt">ThunderBolt</option>
                            <option value="Ethernet">Ethernet</option>
                            <option value="Autre">Autre</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Adresse IP</label>
                        <input type="text" name="ip" value="">
                    </div>

                    <div class="form-group">
                        <label>Numéro de série</label>
                        <input type="text" name="serial" value="">
                    </div>

                    <div class="form-group">
                        <label>Firmware</label>
                        <input type="text" name="firmware" value="">
                    </div>

                    <div class="form-group">
                        <label>Date d'installation</label>
                        <input type="date" name="date_install" value="">
                    </div>

                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes"></textarea>
                    </div>
                </div>
            </form>
        </div>
    `;
    document.getElementById('stockages-list').appendChild(newCard);
}

function deleteStockageCard(button) {
    button.closest('.stockage-card').remove();
}

function saveStockage(id) {
    const card = event.target.closest('.stockage-card');
    const form = card.querySelector('.stockage-form');
    const formData = new FormData(form);

    formData.append('action', 'save_stockage');
    formData.append('id', id || form.dataset.id);

    const btn = event.target;
    const originalText = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Enregistrement...';

    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(text => {
        try {
            const result = JSON.parse(text);
            if (result.success) {
                if (form.dataset.id === 'new') {
                    form.dataset.id = result.id;
                }
                showNotification('✓ ' + result.message, 'success');
            } else {
                showNotification('Erreur: ' + (result.error || 'Erreur inconnue'), 'error');
            }
        } catch (e) {
            showNotification('Erreur serveur', 'error');
            console.error('Erreur de parsing:', text);
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        showNotification('Erreur de communication: ' + error.message, 'error');
    })
    .finally(() => {
        btn.disabled = false;
        btn.textContent = originalText;
    });
}

function deleteStockage(id) {
    if (confirm('Êtes-vous sûr de vouloir supprimer ce stockage ?')) {
        const btn = event.target;
        btn.disabled = true;
        btn.textContent = 'Suppression...';

        const formData = new FormData();
        formData.append('action', 'delete_stockage');
        formData.append('id', id);

        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(text => {
            try {
                const result = JSON.parse(text);
                if (result.success) {
                    showNotification('✓ ' + result.message, 'success');
                    setTimeout(() => {
                        event.target.closest('.stockage-card').remove();
                    }, 300);
                } else {
                    showNotification('Erreur: ' + (result.error || 'Erreur inconnue'), 'error');
                    btn.disabled = false;
                    btn.textContent = 'Supprimer';
                }
            } catch (e) {
                showNotification('Erreur serveur', 'error');
                console.error('Erreur de parsing:', text);
                btn.disabled = false;
                btn.textContent = 'Supprimer';
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            showNotification('Erreur de communication: ' + error.message, 'error');
            btn.disabled = false;
            btn.textContent = 'Supprimer';
        });
    }
}

function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;

    document.body.appendChild(notification);

    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease-out forwards';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}
</script>

<?php elseif ($page === 'interventions'): ?>

<?php
// ----------------- UPLOAD PIÈCE JOINTE -----------------
if (isset($_POST['upload_pj']) && isset($_FILES['pj_file']) && isset($_POST['id_fiche'])) {
    $id_fiche = intval($_POST['id_fiche']);
    $file = $_FILES['pj_file'];

    $sql_fiche = $conn->prepare("SELECT id_client FROM fiches_test WHERE id=?");
    $sql_fiche->bind_param("i", $id_fiche);
    $sql_fiche->execute();
    $sql_fiche->bind_result($id_client);
    $sql_fiche->fetch();
    $sql_fiche->close();

    if ($id_client && $file['error'] == 0) {
        $dossier = __DIR__ . "/data/pj/$id_client/pj";
        if (!is_dir($dossier)) mkdir($dossier, 0777, true);
        $filename = basename($file['name']);
        move_uploaded_file($file['tmp_name'], "$dossier/$id_fiche-$filename");

        $stmt = $conn->prepare("INSERT INTO fichiers_intervention (id_fiche, filename) VALUES (?, ?)");
        $stmt->bind_param("is", $id_fiche, $filename);
        $stmt->execute();
    }
    header("Location: " . $_SERVER['PHP_SELF'] . "?uploaded=1");
    exit;
}
?>

<div class="interventions-container">
    <div class="interventions-header">
        <div class="header-left">
            <h1><?= htmlspecialchars($client['nom']) ?></h1>
        </div>
        <a href="intervention.php" class="btn-voir-toutes">
            <span>🔍</span> Voir toutes les interventions
        </a>
    </div>

    <?php
    if (isset($_GET['uploaded'])): ?>
        <div class="alert alert-success" role="alert">
            ✅ Pièce jointe ajoutée avec succès
        </div>
    <?php endif; ?>

    <?php
    $res = $conn->query("SELECT * FROM fiches_test WHERE id_client = {$client['id']} ORDER BY ladate DESC");
    $interventions = [];
    while ($int = $res->fetch_assoc()) {
        $interventions[] = $int;
    }

    // Afficher les 5 dernières interventions
    $interventions_affichees = array_slice($interventions, 0, 5);
    ?>

    <?php if (count($interventions) === 0): ?>
        <div class="no-results">
            <p>Aucun résultat trouvé</p>
        </div>
    <?php else: ?>
        <div class="interventions-table-wrapper">
            <table class="interventions-table">
                <thead>
                    <tr>
                        <th class="col-date">Date</th>
                        <th class="col-contract">Sous contrat</th>
                        <th class="col-fiche">Fiche d'intervention</th>
                        <th class="col-tech">Tech</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($interventions_affichees as $int):
                        $pdfWeb = "data/inter/{$int['id_client']}/{$int['id_contrat']}-{$int['id_client']}_"
                                  . str_replace(['/', ' ', ':'], '-', $int['ladate'])
                                  . ".pdf";
                    ?>
                        <tr>
                            <td class="col-date"><?= htmlspecialchars($int['ladate']) ?></td>
                            <td class="col-contract">
                                <?php if ($int['id_contrat'] == 0): ?>
                                    <span class="no-contract">Non</span>
                                <?php else: ?>
                                    <span class="with-contract">Oui</span>
                                <?php endif; ?>
                            </td>
                            <td class="col-fiche">
                                <i class="bi bi-eye text-success" data-bs-toggle="modal" data-bs-target="#previewModal"
                                   onclick="document.getElementById('pdfFrame').src='<?= htmlspecialchars($pdfWeb) ?>'"></i>
                                <a href="<?= htmlspecialchars($pdfWeb) ?>" target="_blank"><i class="bi bi-file-earmark-pdf-fill text-danger"></i></a>
                                <i class="bi bi-paperclip text-primary" data-bs-toggle="modal" data-bs-target="#pjModal"
                                   onclick="document.getElementById('id_fiche_input').value='<?= $int['id'] ?>'"></i>
                            </td>
                            <td class="col-tech"><?= htmlspecialchars($int['tech']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Modal Prévisualisation PDF -->
<div class="modal fade" id="previewModal" tabindex="-1" aria-labelledby="previewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="previewModalLabel">Prévisualisation PDF</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body">
                <iframe id="pdfFrame" style="width:100%; height:600px; border:none;"></iframe>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Ajouter Pièce Jointe -->
<div class="modal fade" id="pjModal" tabindex="-1" aria-labelledby="pjModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title" id="pjModalLabel">Ajouter pièce jointe</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id_fiche" id="id_fiche_input" value="">
                    <div class="mb-3">
                        <label for="pj_file" class="form-label">Sélectionner un fichier</label>
                        <input type="file" name="pj_file" id="pj_file" required class="form-control">
                        <small class="text-muted">Formats acceptés: PDF, images, documents</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" name="upload_pj" class="btn btn-primary">Uploader</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.interventions-container {
    background: #f5f5f5;
    padding: 30px;
    border-radius: 8px;
}

.interventions-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.header-left {
    display: flex;
    align-items: center;
    gap: 15px;
}

.header-left h1 {
    margin: 0;
    font-size: 24px;
    color: #333;
    font-weight: 500;
}

.btn-voir-toutes {
    background: #3498db;
    color: white;
    padding: 10px 20px;
    border-radius: 6px;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    transition: background 0.3s ease;
    border: none;
    cursor: pointer;
}

.btn-voir-toutes:hover {
    background: #2980b9;
    color: white;
    text-decoration: none;
}

.interventions-table-wrapper {
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.interventions-table {
    width: 100%;
    border-collapse: collapse;
    margin: 0;
}

.interventions-table thead {
    background: #f8f9fa;
    border-bottom: 2px solid #e0e0e0;
}

.interventions-table th {
    padding: 18px;
    text-align: left;
    font-weight: 600;
    color: #666;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.interventions-table td {
    padding: 16px 18px;
    border-bottom: 1px solid #f0f0f0;
    color: #333;
    font-size: 14px;
}

.interventions-table tbody tr {
    transition: background 0.2s ease;
}

.interventions-table tbody tr:hover {
    background: #f9f9f9;
}

.interventions-table tbody tr:last-child td {
    border-bottom: none;
}

.col-date {
    width: 15%;
}

.col-contract {
    width: 20%;
}

.col-fiche {
    width: 35%;
}

.fiche-actions {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 12px;
    margin: 0;
}

.col-tech {
    width: 30%;
}

.col-fiche i {
    margin-right: 8px;
    cursor: pointer;
    transition: transform 0.2s ease;
    font-size: 16px;
}

.col-fiche i:hover {
    transform: scale(1.2);
}

.col-fiche a {
    text-decoration: none;
}

.no-contract {
    background: #fee;
    color: #c33;
    padding: 4px 10px;
    border-radius: 4px;
    font-weight: 500;
    display: inline-block;
}

.with-contract {
    background: #efe;
    color: #3c3;
    padding: 4px 10px;
    border-radius: 4px;
    font-weight: 500;
    display: inline-block;
}

.no-results {
    background: white;
    padding: 50px;
    text-align: center;
    border-radius: 8px;
    color: #999;
    font-size: 16px;
    margin-top: 20px;
}

.alert {
    margin: 20px 0;
}

.mb-3 {
    margin-bottom: 1rem;
}

.form-label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
}

.text-muted {
    color: #6c757d;
    font-size: 12px;
}
</style>
                </ul>

            <?php else: ?>
                <p>Page inconnue.</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>