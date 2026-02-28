<?php
require_once __DIR__ . '/auth.php';
requireLogin();
?>
<?php
include 'navbar.php';

// Mise à jour des contrats expirés EN PREMIER, avant tout SELECT
if ($conn) {
    $sqlUpdate = "UPDATE contrats SET state = 0 WHERE end < CURDATE() AND state = 1";
    if (!$conn->query($sqlUpdate)) {
        echo "<!-- Erreur mise à jour contrats: " . $conn->error . " -->";
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'create' && isset($_POST['id_client'])) {
        $id_client = $conn->real_escape_string($_POST['id_client']);
        $type = $conn->real_escape_string($_POST['type']);
        $tele = $conn->real_escape_string($_POST['tele']);
        $sur_site = $conn->real_escape_string($_POST['sur_site']);
        $passages = $conn->real_escape_string($_POST['passages']);
        $start = $conn->real_escape_string($_POST['start']);
        $end = $conn->real_escape_string($_POST['end']);
        $notes = $conn->real_escape_string($_POST['notes']);

        $sqlInsert = "INSERT INTO contrats (id_client, type, tele, sur_site, passages, start, end, notes, state) VALUES ('$id_client', '$type', '$tele', '$sur_site', '$passages', '$start', '$end', '$notes', 1)";
        if ($conn->query($sqlInsert) === TRUE) {
            echo "<div style='color: green; padding: 10px; margin: 10px 0; background-color: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px;'>Contrat créé avec succès.</div>";
        } else {
            echo "<div style='color: red; padding: 10px; margin: 10px 0; background-color: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px;'>Erreur lors de la création.</div>";
        }
    }
    elseif ($_POST['action'] == 'delete' && isset($_POST['id'])) {
        $id = intval($_POST['id']);
        $sqlDelete = "DELETE FROM contrats WHERE id = $id";
        if ($conn->query($sqlDelete) === TRUE) {
            echo "<div style='color: green; padding: 10px; margin: 10px 0; background-color: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px;'>Contrat supprimé avec succès.</div>";
        } else {
            echo "<div style='color: red; padding: 10px; margin: 10px 0; background-color: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px;'>Erreur lors de la suppression.</div>";
        }
    }
    elseif ($_POST['action'] == 'update' && isset($_POST['id'])) {
        $id = intval($_POST['id']);
        $id_client = $conn->real_escape_string($_POST['id_client']);
        $type = $conn->real_escape_string($_POST['type']);
        $tele = $conn->real_escape_string($_POST['tele']);
        $sur_site = $conn->real_escape_string($_POST['sur_site']);
        $passages = $conn->real_escape_string($_POST['passages']);
        $start = $conn->real_escape_string($_POST['start']);
        $end = $conn->real_escape_string($_POST['end']);
        $notes = $conn->real_escape_string($_POST['notes']);

        $sqlUpdate = "UPDATE contrats SET id_client='$id_client', type='$type', tele='$tele', sur_site='$sur_site', passages='$passages', start='$start', end='$end', notes='$notes' WHERE id=$id";
        if ($conn->query($sqlUpdate) === TRUE) {
            echo "<div style='color: green; padding: 10px; margin: 10px 0; background-color: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px;'>Contrat mis à jour avec succès.</div>";
        } else {
            echo "<div style='color: red; padding: 10px; margin: 10px 0; background-color: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px;'>Erreur lors de la mise à jour.</div>";
        }
    }
}

$sql = "SELECT c.id, c.id_client, cl.nom, c.type, c.start, c.end, c.sur_site, c.passages, c.tele, c.state, c.notes FROM contrats c LEFT JOIN clients cl ON c.id_client = cl.id ORDER BY c.end DESC";
$result = $conn->query($sql);

$sqlClients = "SELECT id, nom FROM clients";
$resultClients = $conn->query($sqlClients);
$clients = [];
if ($resultClients->num_rows > 0) {
    while($row = $resultClients->fetch_assoc()) {
        $clients[$row['id']] = $row['nom'];
    }
}

function getInterventions($id_contrat, $id_client, $conn) {
    $id_contrat = intval($id_contrat);
    $sqlInter = "SELECT id, id_client, id_contrat, ladate, hasfile, tech FROM inter WHERE id_contrat = $id_contrat ORDER BY ladate DESC";
    $resultInter = $conn->query($sqlInter);

    $interventions = [];
    if ($resultInter && $resultInter->num_rows > 0) {
        while($inter = $resultInter->fetch_assoc()) {
            $interventions[] = $inter;
        }
    }
    return $interventions;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="stylesheet" href="style.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contrats de maintenance</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/3.3.7/css/bootstrap.min.css">
    <style>
body {
    font-family: Arial, sans-serif;
    margin: 0;
    padding: 0;
}

.content {
    margin-top: 50px;
    padding: 20px;
}

.header-section {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}

.header-section h2 {
    margin: 0;
    color: #333;
}

.btn-add {
    background-color: #28a745;
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-weight: 600;
    font-size: 14px;
    transition: background-color 0.3s;
}

.btn-add:hover {
    background-color: #218838;
}

.search-bar {
    margin-bottom: 25px;
    padding: 15px;
    background-color: #f8f9fa;
    border-radius: 6px;
    border: 1px solid #dee2e6;
}

.search-bar label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #333;
}

.search-bar input {
    width: 100%;
    padding: 12px 15px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
    box-sizing: border-box;
}

.search-bar input:focus {
    outline: none;
    border-color: #007bff;
    box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
}

.filter-bar {
    display: flex;
    gap: 10px;
    margin-bottom: 25px;
    padding: 15px;
    background-color: #f8f9fa;
    border-radius: 6px;
    border: 1px solid #dee2e6;
}

.filter-bar p {
    margin: 0;
    padding: 8px 0;
    font-weight: 600;
    color: #333;
    margin-right: 15px;
}

.filter-btn {
    padding: 8px 20px;
    border: 2px solid #dee2e6;
    background-color: white;
    color: #333;
    cursor: pointer;
    border-radius: 4px;
    font-weight: 600;
    font-size: 14px;
    transition: all 0.3s;
}

.filter-btn:hover {
    border-color: #007bff;
    color: #007bff;
    background-color: #f0f8ff;
}

.filter-btn.active {
    background-color: #007bff;
    color: white;
    border-color: #007bff;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin: 20px 0;
}

table, th, td {
    border: 1px solid #ddd;
}

th, td {
    padding: 12px;
    text-align: left;
}

th {
    background-color: #f4f4f4;
}

tr:nth-child(even) {
    background-color: #f9f9f9;
}

.btn {
    padding: 6px 10px;
    border: none;
    cursor: pointer;
    text-align: center;
    border-radius: 4px;
    display: inline-block;
    font-size: 18px;
    transition: opacity 0.3s;
    min-width: 40px;
    height: 40px;
    line-height: 28px;
    margin-right: 5px;
}

.btn-blue {
    background-color: #007bff;
    color: white;
}

.btn-red {
    background-color: #dc3545;
    color: white;
}

.status-active {
    color: green;
    font-weight: bold;
}

.status-expired {
    color: red;
    font-weight: bold;
}

td:last-child {
    min-width: 100px;
}

.interventions-list {
    max-height: 200px;
    overflow-y: auto;
    background-color: #f9f9f9;
    padding: 8px;
    border-radius: 4px;
}

.intervention-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #eee;
    font-size: 13px;
}

.intervention-item:last-child {
    border-bottom: none;
}

.intervention-date {
    font-weight: bold;
    color: #333;
}

.intervention-date-link {
    font-weight: bold;
    color: #007bff;
    text-decoration: none;
    cursor: pointer;
    padding: 4px 8px;
    border-radius: 3px;
    transition: background-color 0.2s;
}

.intervention-date-link:hover {
    background-color: #e7f0ff;
    text-decoration: underline;
}

.intervention-file {
    color: #666;
    margin: 0 10px;
}

.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.4);
}

.modal-content {
    background-color: #fefefe;
    margin: 5% auto;
    padding: 30px;
    border: 1px solid #888;
    width: 90%;
    max-width: 600px;
    border-radius: 8px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    border-bottom: 1px solid #eee;
    padding-bottom: 15px;
}

.modal-header h2 {
    margin: 0;
    color: #333;
}

.close {
    color: #aaa;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

.close:hover {
    color: black;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    color: #333;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
    box-sizing: border-box;
}

.form-group textarea {
    resize: vertical;
    min-height: 100px;
}

.form-row {
    display: flex;
    gap: 15px;
}

.form-row .form-group {
    flex: 1;
}

.modal-buttons {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    margin-top: 25px;
    border-top: 1px solid #eee;
    padding-top: 15px;
}

.modal-buttons button {
    padding: 10px 25px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
}

.btn-confirm {
    background-color: #007bff;
    color: white;
}

.btn-confirm:hover {
    background-color: #0056b3;
}

.btn-cancel {
    background-color: #6c757d;
    color: white;
}

.btn-cancel:hover {
    background-color: #545b62;
}

.delete-modal-content {
    text-align: center;
}

.delete-modal-content p {
    margin: 20px 0;
    font-size: 16px;
}

.modal-buttons.delete-buttons {
    justify-content: center;
}

.btn-delete-confirm {
    background-color: #dc3545;
    color: white;
}

.btn-delete-confirm:hover {
    background-color: #c82333;
}

.no-interventions {
    color: #999;
    font-style: italic;
    padding: 8px;
}

.search-client-wrapper {
    position: relative;
    width: 100%;
}

.client-search-input {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
    box-sizing: border-box;
}

.client-dropdown-list {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background-color: white;
    border: 1px solid #ddd;
    border-top: none;
    border-radius: 0 0 4px 4px;
    max-height: 200px;
    overflow-y: auto;
    display: none;
    z-index: 1001;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.client-dropdown-list.active {
    display: block;
}

.client-dropdown-item {
    padding: 10px;
    cursor: pointer;
    border-bottom: 1px solid #eee;
}

.client-dropdown-item:last-child {
    border-bottom: none;
}

.client-dropdown-item:hover {
    background-color: #f0f8ff;
}

.no-results {
    padding: 10px;
    color: #999;
    text-align: center;
}

.checkbox-group {
    display: flex;
    gap: 20px;
    margin-top: 10px;
}

.checkbox-item {
    display: flex;
    align-items: center;
    gap: 8px;
}

.checkbox-item input[type="checkbox"] {
    width: auto;
    cursor: pointer;
}

.checkbox-item label {
    margin: 0;
    font-weight: normal;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 5px;
}

.selected-types {
    margin-top: 10px;
    padding: 10px;
    background-color: #f0f8ff;
    border-radius: 4px;
    border: 1px solid #ddd;
    min-height: 30px;
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
}

.type-badge {
    background-color: #007bff;
    color: white;
    padding: 4px 10px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 600;
}

.empty-selection {
    color: #999;
    font-style: italic;
}

.type-list {
    margin-left: 20px;
    margin-top: 5px;
    margin-bottom: 5px;
}

.type-list li {
    margin: 3px 0;
}
    </style>
</head>
<body>

<div class="content">
    <div class="header-section">
        <h2>Contrats de maintenance</h2>
        <button class="btn-add" onclick="openAddModal()">➕ Ajouter</button>
    </div>

    <div class="search-bar">
        <label for="searchInput">🔍 Rechercher</label>
        <input type="text" id="searchInput" placeholder="Rechercher par client, notes, type...">
    </div>

    <div class="filter-bar">
        <p>Filtrer :</p>
        <button class="filter-btn active" onclick="filterContrats('actifs')">✓ Actifs</button>
        <button class="filter-btn" onclick="filterContrats('expires')">✗ Expirés</button>
        <button class="filter-btn" onclick="filterContrats('tous')">Tous</button>
    </div>

    <?php
    if ($result->num_rows > 0) {
        echo "<table id='contractsTable'>
                <tr>
                    <th>Statut</th>
                    <th>Client</th>
                    <th>Début</th>
                    <th>Fin</th>
                    <th>Contrat</th>
                    <th>Interventions</th>
                    <th>Notes</th>
                    <th>Actions</th>
                </tr>";

        while($row = $result->fetch_assoc()) {
            $statusClass = ($row["state"] == 1) ? "status-active" : "status-expired";
            $statusText = ($row["state"] == 1) ? "Actif" : "Expiré";
            $filterClass = ($row["state"] == 1) ? "row-actif" : "row-expire";

            $interventions = getInterventions($row["id"], $row["id_client"], $conn);

            $interventionsHtml = "<div class='interventions-list'>";
            if (count($interventions) > 0) {
                foreach ($interventions as $inter) {
                    $baseDir = "data/inter/" . $inter["id_client"] . "/";
                    $dateFormatted = str_replace('/', '-', $inter["ladate"]);
                    $fileName = $inter["id_contrat"] . "-" . $inter["id_client"] . "_" . $dateFormatted . ".pdf";
                    $filePath = $baseDir . $fileName;

                    $interventionsHtml .= "<div class='intervention-item'>";

                    if ($inter["hasfile"] == 1 && file_exists($filePath)) {
                        $interventionsHtml .= "<a href='" . htmlspecialchars($filePath) . "' download class='intervention-date-link'>" . htmlspecialchars($inter["ladate"]) . "</a>";
                        $interventionsHtml .= "<span class='intervention-file'>" . htmlspecialchars($inter["tech"] ?? "N/A") . "</span>";
                    } else {
                        $interventionsHtml .= "<span class='intervention-date'>" . htmlspecialchars($inter["ladate"]) . "</span>";
                        $interventionsHtml .= "<span class='intervention-file' style='color: #999;'>❌ Pas de fiche</span>";
                    }
                    $interventionsHtml .= "</div>";
                }
            } else {
                $interventionsHtml .= "<div class='no-interventions'>Aucune intervention</div>";
            }
            $interventionsHtml .= "</div>";

            $searchableText = strtolower((isset($row["nom"]) ? $row["nom"] : "") . " " . $row["type"] . " " . $row["notes"]);

            // Échapper les notes pour éviter les problèmes dans l'attribut onclick
            $notesEscaped = htmlspecialchars(addslashes($row["notes"]), ENT_QUOTES);

            echo "<tr class='$filterClass' data-searchable='" . htmlspecialchars($searchableText) . "'>
                    <td class='" . $statusClass . "'>" . $statusText . "</td>
                    <td>" . htmlspecialchars($row["nom"] ?? "N/A") . "</td>
                    <td>" . htmlspecialchars($row["start"]) . "</td>
                    <td>" . htmlspecialchars($row["end"]) . "</td>
                    <td>" . htmlspecialchars($row["passages"]) . " passage/ans<br>";

            $types = explode('*', $row["type"]);
            $types = array_filter($types);
            if (count($types) > 0) {
                echo '<ul class="type-list">';
                foreach ($types as $t) {
                    echo '<li>' . htmlspecialchars(trim($t)) . '</li>';
                }
                echo '</ul>';
            }

            echo ($row["tele"] == "oui" ? '<span style="color:blue;">Télémaintenance</span>' : '') . "</td>
                    <td>" . $interventionsHtml . "</td>
                    <td>" . htmlspecialchars($row["notes"]) . "</td>
                    <td>
                        <button class='btn btn-blue' onclick='openEditModal(" . $row["id"] . ", \"" . htmlspecialchars($row["id_client"], ENT_QUOTES) . "\", \"" . htmlspecialchars($row["type"], ENT_QUOTES) . "\", \"" . $row["tele"] . "\", \"" . $row["sur_site"] . "\", \"" . htmlspecialchars($row["passages"], ENT_QUOTES) . "\", \"" . htmlspecialchars($row["start"], ENT_QUOTES) . "\", \"" . htmlspecialchars($row["end"], ENT_QUOTES) . "\", \"" . $notesEscaped . "\")' title='Modifier'>
                            ✏️
                        </button>
                        <button class='btn btn-red' onclick='openDeleteModal(" . $row["id"] . ")' title='Supprimer'>
                            🗑️
                        </button>
                    </td>
                  </tr>";
        }

        echo "</table>";
    } else {
        echo "<p>Aucun contrat trouvé.</p>";
    }

    $conn->close();
    ?>
</div>

<!-- Modal d'ajout -->
<div id="addModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Nouveau contrat de maintenance</h2>
            <span class="close" onclick="closeAddModal()">&times;</span>
        </div>
        <form method="POST" id="addForm">
            <input type="hidden" name="action" value="create">

            <div class="form-group">
                <label for="add_id_client">Client</label>
                <div class="search-client-wrapper">
                    <input type="text" id="add_client_search" class="client-search-input" placeholder="Rechercher un client..." autocomplete="off">
                    <div id="add_client_dropdown" class="client-dropdown-list"></div>
                    <input type="hidden" name="id_client" id="add_id_client" required>
                </div>
            </div>

            <div class="form-group">
                <label>Type</label>
                <div class="checkbox-group">
                    <div class="checkbox-item">
                        <input type="checkbox" id="add_type_informatique" name="type_informatique" value="informatique">
                        <label for="add_type_informatique">Informatique</label>
                    </div>
                    <div class="checkbox-item">
                        <input type="checkbox" id="add_type_stockage" name="type_stockage" value="stockage">
                        <label for="add_type_stockage">Stockage</label>
                    </div>
                    <div class="checkbox-item">
                        <input type="checkbox" id="add_type_ripflux" name="type_ripflux" value="RIP/flux">
                        <label for="add_type_ripflux">RIP/flux</label>
                    </div>
                </div>
                <div class="selected-types" id="add_selected_types">
                    <span class="empty-selection">Aucun type sélectionné</span>
                </div>
                <input type="hidden" name="type" id="add_type_hidden" required>
            </div>

            <div class="form-group">
                <label for="add_tele">TeamViewer</label>
                <select name="tele" id="add_tele" required>
                    <option value="oui">Oui</option>
                    <option value="non">Non</option>
                </select>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="add_sur_site">Sur site</label>
                    <select name="sur_site" id="add_sur_site" required>
                        <option value="oui">Oui</option>
                        <option value="non">Non</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="add_passages">Passages/an</label>
                    <input type="number" name="passages" id="add_passages" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="add_start">Début</label>
                    <input type="date" name="start" id="add_start" required>
                </div>
                <div class="form-group">
                    <label for="add_end">Fin</label>
                    <input type="date" name="end" id="add_end" required>
                </div>
            </div>

            <div class="form-group">
                <label for="add_notes">Notes</label>
                <textarea name="notes" id="add_notes"></textarea>
            </div>

            <div class="modal-buttons">
                <button type="button" class="btn-cancel" onclick="closeAddModal()">Annuler</button>
                <button type="submit" class="btn-confirm">Ajouter</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal d'édition -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Editer le contrat de maintenance</h2>
            <span class="close" onclick="closeEditModal()">&times;</span>
        </div>
        <form method="POST" id="editForm">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="editId">

            <div class="form-group">
                <label for="edit_id_client">Client</label>
                <div class="search-client-wrapper">
                    <input type="text" id="edit_client_search" class="client-search-input" placeholder="Rechercher un client..." autocomplete="off">
                    <div id="edit_client_dropdown" class="client-dropdown-list"></div>
                    <input type="hidden" name="id_client" id="edit_id_client" required>
                </div>
            </div>

            <div class="form-group">
                <label>Type</label>
                <div class="checkbox-group">
                    <div class="checkbox-item">
                        <input type="checkbox" id="edit_type_informatique" name="type_informatique" value="informatique">
                        <label for="edit_type_informatique">Informatique</label>
                    </div>
                    <div class="checkbox-item">
                        <input type="checkbox" id="edit_type_stockage" name="type_stockage" value="stockage">
                        <label for="edit_type_stockage">Stockage</label>
                    </div>
                    <div class="checkbox-item">
                        <input type="checkbox" id="edit_type_ripflux" name="type_ripflux" value="RIP/flux">
                        <label for="edit_type_ripflux">RIP/flux</label>
                    </div>
                </div>
                <div class="selected-types" id="edit_selected_types">
                    <span class="empty-selection">Aucun type sélectionné</span>
                </div>
                <input type="hidden" name="type" id="edit_type_hidden" required>
            </div>

            <div class="form-group">
                <label for="edit_tele">TeamViewer</label>
                <select name="tele" id="edit_tele" required>
                    <option value="oui">Oui</option>
                    <option value="non">Non</option>
                </select>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="edit_sur_site">Sur site</label>
                    <select name="sur_site" id="edit_sur_site" required>
                        <option value="oui">Oui</option>
                        <option value="non">Non</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit_passages">Passages/an</label>
                    <input type="number" name="passages" id="edit_passages" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="edit_start">Début</label>
                    <input type="date" name="start" id="edit_start" required>
                </div>
                <div class="form-group">
                    <label for="edit_end">Fin</label>
                    <input type="date" name="end" id="edit_end" required>
                </div>
            </div>

            <div class="form-group">
                <label for="edit_notes">Notes</label>
                <textarea name="notes" id="edit_notes"></textarea>
            </div>

            <div class="modal-buttons">
                <button type="button" class="btn-cancel" onclick="closeEditModal()">Annuler</button>
                <button type="submit" class="btn-confirm">Valider</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal de suppression -->
<div id="deleteModal" class="modal">
    <div class="modal-content delete-modal-content">
        <div class="modal-header">
            <h2>Confirmer la suppression</h2>
            <span class="close" onclick="closeDeleteModal()">&times;</span>
        </div>
        <p>Êtes-vous sûr de vouloir supprimer ce contrat ?</p>
        <form method="POST" id="deleteForm">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" id="deleteId">
            <div class="modal-buttons delete-buttons">
                <button type="button" class="btn-cancel" onclick="closeDeleteModal()">Annuler</button>
                <button type="submit" class="btn-delete-confirm">Supprimer</button>
            </div>
        </form>
    </div>
</div>

<script>
function filterContrats(filter) {
    const rows = document.querySelectorAll('tr[class^="row-"]');
    const buttons = document.querySelectorAll('.filter-btn');

    buttons.forEach(btn => btn.classList.remove('active'));

    buttons.forEach(btn => {
        if (btn.textContent.includes(filter === 'actifs' ? 'Actifs' : filter === 'expires' ? 'Expirés' : 'Tous')) {
            btn.classList.add('active');
        }
    });

    rows.forEach(row => {
        if (filter === 'tous') {
            row.style.display = '';
        } else if (filter === 'actifs') {
            row.style.display = row.classList.contains('row-actif') ? '' : 'none';
        } else if (filter === 'expires') {
            row.style.display = row.classList.contains('row-expire') ? '' : 'none';
        }
    });
}

function performSearch() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const rows = document.querySelectorAll('tr[class^="row-"]');

    rows.forEach(row => {
        const searchableText = row.getAttribute('data-searchable') || '';
        const isVisible = searchableText.includes(searchTerm);
        row.style.display = isVisible ? '' : 'none';
    });
}

document.getElementById('searchInput').addEventListener('input', performSearch);

document.addEventListener('DOMContentLoaded', function() {
    filterContrats('actifs');
});

function openAddModal() {
    document.getElementById('addForm').reset();
    document.getElementById('add_selected_types').innerHTML = '<span class="empty-selection">Aucun type sélectionné</span>';
    document.getElementById('add_type_hidden').value = '';
    document.getElementById('addModal').style.display = 'block';
}

function closeAddModal() {
    document.getElementById('addModal').style.display = 'none';
}

function openEditModal(id, client, type, tele, sur_site, passages, start, end, notes) {
    document.getElementById('editId').value = id;
    document.getElementById('edit_client_search').value = clientsData[client] || '';
    document.getElementById('edit_id_client').value = client;
    setTypeCheckboxes(type, 'edit_type_informatique', 'edit_type_stockage', 'edit_type_ripflux', 'edit_selected_types', 'edit_type_hidden');
    document.getElementById('edit_tele').value = tele;
    document.getElementById('edit_sur_site').value = sur_site;
    document.getElementById('edit_passages').value = passages;
    document.getElementById('edit_start').value = start;
    document.getElementById('edit_end').value = end;
    document.getElementById('edit_notes').value = notes;
    document.getElementById('editModal').style.display = 'block';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

function openDeleteModal(id) {
    document.getElementById('deleteId').value = id;
    document.getElementById('deleteModal').style.display = 'block';
}

function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
}

window.onclick = function(event) {
    var addModal = document.getElementById('addModal');
    var editModal = document.getElementById('editModal');
    var deleteModal = document.getElementById('deleteModal');
    if (event.target == addModal) {
        addModal.style.display = 'none';
    }
    if (event.target == editModal) {
        editModal.style.display = 'none';
    }
    if (event.target == deleteModal) {
        deleteModal.style.display = 'none';
    }
}

const clientsData = {
    <?php foreach($clients as $id => $nom): ?>
        '<?php echo htmlspecialchars($id, ENT_QUOTES); ?>': '<?php echo htmlspecialchars($nom, ENT_QUOTES); ?>',
    <?php endforeach; ?>
    }

function initClientSearch(searchInputId, dropdownId, clientIdInputId) {
    const searchInput = document.getElementById(searchInputId);
    const dropdown = document.getElementById(dropdownId);
    const clientIdInput = document.getElementById(clientIdInputId);

    if (!searchInput) return;

    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();

        if (searchTerm === '') {
            dropdown.classList.remove('active');
            return;
        }

        const filteredClients = Object.entries(clientsData).filter(([id, nom]) =>
            nom.toLowerCase().includes(searchTerm)
        );

        if (filteredClients.length === 0) {
            dropdown.innerHTML = '<div class="no-results">Aucun client trouvé</div>';
            dropdown.classList.add('active');
            return;
        }

        dropdown.innerHTML = filteredClients.map(([id, nom]) =>
            `<div class="client-dropdown-item" data-id="${id}" data-nom="${nom}">${nom}</div>`
        ).join('');

        dropdown.classList.add('active');

        document.querySelectorAll(`#${dropdownId} .client-dropdown-item`).forEach(item => {
            item.addEventListener('click', function() {
                const selectedId = this.getAttribute('data-id');
                const selectedNom = this.getAttribute('data-nom');
                searchInput.value = selectedNom;
                clientIdInput.value = selectedId;
                dropdown.classList.remove('active');
            });
        });
    });

    document.addEventListener('click', function(e) {
        if (e.target !== searchInput && e.target !== dropdown) {
            dropdown.classList.remove('active');
        }
    });

    searchInput.addEventListener('focus', function() {
        if (this.value === '' && Object.keys(clientsData).length > 0) {
            dropdown.innerHTML = Object.entries(clientsData).map(([id, nom]) =>
                `<div class="client-dropdown-item" data-id="${id}" data-nom="${nom}">${nom}</div>`
            ).join('');
            dropdown.classList.add('active');

            document.querySelectorAll(`#${dropdownId} .client-dropdown-item`).forEach(item => {
                item.addEventListener('click', function() {
                    const selectedId = this.getAttribute('data-id');
                    const selectedNom = this.getAttribute('data-nom');
                    searchInput.value = selectedNom;
                    clientIdInput.value = selectedId;
                    dropdown.classList.remove('active');
                });
            });
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    initClientSearch('add_client_search', 'add_client_dropdown', 'add_id_client');
    initClientSearch('edit_client_search', 'edit_client_dropdown', 'edit_id_client');
    initTypeCheckboxes('add_type_informatique', 'add_type_stockage', 'add_type_ripflux', 'add_selected_types', 'add_type_hidden');
    initTypeCheckboxes('edit_type_informatique', 'edit_type_stockage', 'edit_type_ripflux', 'edit_selected_types', 'edit_type_hidden');
});

function initTypeCheckboxes(checkboxInforId, checkboxStockageId, checkboxRipFluxId, displayId, hiddenInputId) {
    const checkboxInfor = document.getElementById(checkboxInforId);
    const checkboxStockage = document.getElementById(checkboxStockageId);
    const checkboxRipFlux = document.getElementById(checkboxRipFluxId);
    const displayDiv = document.getElementById(displayId);
    const hiddenInput = document.getElementById(hiddenInputId);

    function updateDisplay() {
        const selected = [];
        if (checkboxInfor.checked) selected.push('informatique');
        if (checkboxStockage.checked) selected.push('stockage');
        if (checkboxRipFlux.checked) selected.push('RIP/flux');

        if (selected.length === 0) {
            displayDiv.innerHTML = '<span class="empty-selection">Aucun type sélectionné</span>';
            hiddenInput.value = '';
        } else {
            displayDiv.innerHTML = selected.map(type => `<span class="type-badge">${type}</span>`).join('');
            hiddenInput.value = selected.join('*');
        }
    }

    checkboxInfor.addEventListener('change', updateDisplay);
    checkboxStockage.addEventListener('change', updateDisplay);
    checkboxRipFlux.addEventListener('change', updateDisplay);
}

function setTypeCheckboxes(typeString, checkboxInforId, checkboxStockageId, checkboxRipFluxId, displayId, hiddenInputId) {
    const checkboxInfor = document.getElementById(checkboxInforId);
    const checkboxStockage = document.getElementById(checkboxStockageId);
    const checkboxRipFlux = document.getElementById(checkboxRipFluxId);
    const displayDiv = document.getElementById(displayId);
    const hiddenInput = document.getElementById(hiddenInputId);

    checkboxInfor.checked = false;
    checkboxStockage.checked = false;
    checkboxRipFlux.checked = false;

    if (typeString.includes('informatique')) checkboxInfor.checked = true;
    if (typeString.includes('stockage')) checkboxStockage.checked = true;
    if (typeString.includes('RIP/flux')) checkboxRipFlux.checked = true;

    const selected = [];
    if (checkboxInfor.checked) selected.push('informatique');
    if (checkboxStockage.checked) selected.push('stockage');
    if (checkboxRipFlux.checked) selected.push('RIP/flux');

    if (selected.length === 0) {
        displayDiv.innerHTML = '<span class="empty-selection">Aucun type sélectionné</span>';
        hiddenInput.value = '';
    } else {
        displayDiv.innerHTML = selected.map(type => `<span class="type-badge">${type}</span>`).join('');
        hiddenInput.value = selected.join('*');
    }
}
</script>

</body>
</html>