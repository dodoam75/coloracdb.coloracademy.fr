<?php
require_once __DIR__ . '/auth.php';
requireLogin();
?>
<?php
include 'navbar.php';
$conn->set_charset("utf8");

use Dompdf\Dompdf;
use Dompdf\Options;

// ----------------- SUPPRESSION FICHE + PDF -----------------
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);

    $sql_info = "SELECT id_client, id_contrat, ladate FROM fiches_test WHERE id = ?";
    $stmt_info = $conn->prepare($sql_info);
    $stmt_info->bind_param("i", $id);
    $stmt_info->execute();
    $stmt_info->bind_result($id_client, $id_contrat, $ladate);
    $stmt_info->fetch();
    $stmt_info->close();

    if ($id_client && $ladate) {
        $dossier = __DIR__ . "/data/inter/$id_client";
        $id_contrat = $id_contrat ?? 0;
        $pattern = "$id_contrat-$id_client" . "_".str_replace(['/', ' ', ':'], '-', $ladate);
        if (is_dir($dossier)) {
            $fichiers = scandir($dossier);
            foreach ($fichiers as $fichier) {
                if (strpos($fichier, $pattern) !== false && pathinfo($fichier, PATHINFO_EXTENSION) === 'pdf') {
                    @unlink("$dossier/$fichier");
                }
            }
        }
    }

    $sql_delete = "DELETE FROM fiches_test WHERE id = ?";
    $stmt = $conn->prepare($sql_delete);
    $stmt->bind_param("i", $id);
    $stmt->execute();

    header("Location: intervention.php?deleted=1");
    exit;
}

// ----------------- UPLOAD PIÈCE JOINTE PDF -----------------
if (isset($_POST['upload_pj']) && isset($_FILES['pj_file']) && isset($_POST['id_fiche'])) {
    $id_fiche = intval($_POST['id_fiche']);
    $file = $_FILES['pj_file'];

    $sql_fiche = $conn->prepare("SELECT id_client, id_contrat, ladate FROM fiches_test WHERE id=?");
    $sql_fiche->bind_param("i", $id_fiche);
    $sql_fiche->execute();
    $sql_fiche->bind_result($id_client, $id_contrat, $ladate);
    $sql_fiche->fetch();
    $sql_fiche->close();

    // Vérifier que c'est un fichier PDF
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if ($id_client && $file['error'] == 0 && $file_ext === 'pdf') {
        $dossier = __DIR__ . "/data/inter/$id_client";
        if (!is_dir($dossier)) mkdir($dossier, 0777, true);

        // Renommer le fichier au format: id_contrat-id_client_date_id_fiche.pdf
        $id_contrat = $id_contrat ?? 0;
        $ladate_clean = str_replace(['/', ' ', ':'], '-', $ladate);
        $filename = "$id_contrat-$id_client" . "_" . $ladate_clean . "_pj_$id_fiche.pdf";

        $filepath = "$dossier/$filename";

        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            $_GET['uploaded'] = 1;
        } else {
            $_GET['upload_error'] = 1;
        }
    } else {
        $_GET['upload_error'] = 1;
    }

    header("Location: intervention.php?" . (isset($_GET['uploaded']) ? 'uploaded=1' : 'upload_error=1'));
    exit;
}

// ----------------- LISTE DES FICHES AVEC FILTRES -----------------
$sql = "SELECT fiches_test.*, clients.nom AS client_nom FROM fiches_test
        LEFT JOIN clients ON fiches_test.id_client = clients.id
        WHERE 1=1";

// Filtre par client
$search_client = isset($_GET['search_client']) ? trim($_GET['search_client']) : '';
if (!empty($search_client)) {
    $search_client_param = "%$search_client%";
    $sql .= " AND clients.nom LIKE ?";
}

// Filtre par plage de dates
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
if (!empty($date_from)) {
    $sql .= " AND fiches_test.ladate >= ?";
}
if (!empty($date_to)) {
    $sql .= " AND fiches_test.ladate <= ?";
}

$sql .= " ORDER BY fiches_test.ladate DESC";

// Préparation de la requête avec les paramètres
$stmt = $conn->prepare($sql);

if (!empty($search_client) && !empty($date_from) && !empty($date_to)) {
    $stmt->bind_param("sss", $search_client_param, $date_from, $date_to);
} elseif (!empty($search_client) && !empty($date_from)) {
    $stmt->bind_param("ss", $search_client_param, $date_from);
} elseif (!empty($search_client) && !empty($date_to)) {
    $stmt->bind_param("ss", $search_client_param, $date_to);
} elseif (!empty($date_from) && !empty($date_to)) {
    $stmt->bind_param("ss", $date_from, $date_to);
} elseif (!empty($search_client)) {
    $stmt->bind_param("s", $search_client_param);
} elseif (!empty($date_from)) {
    $stmt->bind_param("s", $date_from);
} elseif (!empty($date_to)) {
    $stmt->bind_param("s", $date_to);
}

$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

// ----------------- FONCTION GÉNÉRER PDF -----------------
function generatePDF($fiche) {
    $id_client = $fiche['id_client'] ?? 0;
    $id_contrat = $fiche['id_contrat'] ?? 0;
    $id_fiche = $fiche['id'] ?? 0;
    $ladate = $fiche['ladate'] ?? '';

    $dossier = __DIR__ . "/data/inter/$id_client";
    if (!is_dir($dossier)) mkdir($dossier, 0777, true);

    $ladate_clean = str_replace(['/', ' ', ':'], '-', $ladate);
    $fichier_pdf = "$dossier/$id_contrat-$id_client" . "_$ladate_clean.pdf";

    if (!file_exists($fichier_pdf)) {

        require_once 'dompdf/autoload.inc.php';

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $dompdf = new Dompdf($options);

        $logo_code = strtolower($fiche['logo'] ?? 'colorac');
        $logos = [
            'colorac' => __DIR__ . "/img/colorac_logo_small.png",
            'pommes'  => __DIR__ . "/img/pommes_logo_small.png",
            'adc'     => __DIR__ . "/img/adc_logo_small.png",
            'aucun'   => __DIR__ . "/img/aucun_logo_small.png"
        ];

        $logo_file = $logos[$logo_code] ?? $logos['colorac'];
        $logo_file = str_replace('\\', '/', $logo_file);

        $logo_html = '';
        if (file_exists($logo_file)) {
            $imageData = base64_encode(file_get_contents($logo_file));
            $logo_html = '<img src="data:image/png;base64,'.$imageData.'" style="height:70px;">';
        }

        // Récupérer la signature
        $signature_html = '';
        $ladate_clean_sig = preg_replace("/[^0-9]/", "", $ladate);
        $signature_file = __DIR__ . "/data/signatures/$id_client-$id_fiche-$ladate_clean_sig.png";

        if (file_exists($signature_file)) {
            $signatureData = base64_encode(file_get_contents($signature_file));
            $signature_html = '<img src="data:image/png;base64,'.$signatureData.'" style="height:80px; max-width:300px;">';
        }

        $client_nom    = htmlspecialchars($fiche['client_nom'] ?? '');
        $adresse       = htmlspecialchars($fiche['adresse'] ?? '');
        $ladate_display= htmlspecialchars($fiche['ladate'] ?? '');
        $start         = htmlspecialchars($fiche['start'] ?? '');
        $end           = htmlspecialchars($fiche['end'] ?? '');
        $descr_inter   = htmlspecialchars($fiche['descr_inter'] ?? '');
        $tech          = htmlspecialchars($fiche['tech'] ?? '');

        $html = '
        <style>
            body { font-family: Arial, sans-serif; font-size:13px; }
            .table { width:100%; border-collapse: collapse; }
            .table td, .table th { border:1px solid #000; padding:6px; }
            .section-title { background:#e6e6e6; border:1px solid #000; font-weight:bold; padding:6px; margin-top:20px; }
            .footer { position: fixed; bottom: 20px; width: 100%; text-align:center; font-size:12px; }
        </style>

        <table width="100%" style="margin-bottom:20px;">
            <tr>
                <td style="width:50%;">'.$logo_html.'</td>
                <td style="text-align:right; font-size:18px; font-weight:bold;">
                    FICHE D\'INTERVENTION<br>TECHNIQUE
                </td>
            </tr>
        </table>

        <div class="section-title">CLIENT</div>
        <table class="table">
            <tr><td style="width:120px; font-weight:bold;">Nom</td><td>'.$client_nom.'</td></tr>
            <tr><td style="font-weight:bold;">Adresse</td><td>'.nl2br($adresse).'</td></tr>
        </table>

        <div class="section-title">INTERVENTION</div>
        <table class="table">
            <tr>
                <td style="width:150px; font-weight:bold;">Date : '.$ladate_display.'</td>
                <td style="width:200px; font-weight:bold;">Heure d\'arrivée : '.$start.'</td>
                <td style="width:200px; font-weight:bold;">Heure de départ : '.$end.'</td>
            </tr>
        </table>

        <table class="table"><tr><td style="height:200px;">'.nl2br($descr_inter).'</td></tr></table>

        <table class="table" style="margin-top:0;">
            <tr><td style="width:120px; font-weight:bold;">Technicien</td><td>'.$tech.'</td></tr>
        </table>

        <div class="section-title">Signature du client</div>
        <table class="table">
            <tr>
                <td style="height:120px; text-align:center; vertical-align:middle;">
                    '.$signature_html.'
                </td>
            </tr>
        </table>

        <div class="footer">
            <div style="border-top:1px solid #999; width:95%; margin:0 auto 10px auto;"></div>
            Color Academy - 2 rue de Paris, 94100 Saint-Maur-des-Fossés<br>
            01 42 49 68 17 - <a href="https://www.coloracademy.fr">www.coloracademy.fr</a>
        </div>
        ';

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4','portrait');
        $dompdf->render();
        file_put_contents($fichier_pdf, $dompdf->output());
    }

    return $fichier_pdf;
}

// ----------------- STATUT FACTURATION -----------------
function getFacturationStatus($facturable, $saved) {
    if ($facturable == 0) {
        return ['label' => 'Non facturable', 'class' => 'badge-non'];
    } elseif ($facturable == 1 && $saved == 0) {
        return ['label' => 'À facturer', 'class' => 'badge-warning'];
    } elseif ($facturable == 1 && $saved == 1) {
        return ['label' => 'Facturé', 'class' => 'badge-success'];
    }
}

// ----------------- MARQUER COMME FACTURÉ -----------------
if (isset($_GET['mark_factured'])) {
    $id = intval($_GET['mark_factured']);
    $sql_update = "UPDATE fiches_test SET saved = 1 WHERE id = ?";
    $stmt = $conn->prepare($sql_update);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    header("Location: intervention.php?marked_factured=1");
    exit;
}

// ----------------- BASCULER STATUT FACTURÉ ↔ À FACTURER -----------------
if (isset($_GET['toggle_factured'])) {
    $id = intval($_GET['toggle_factured']);

    $sql_get = "SELECT saved FROM fiches_test WHERE id = ?";
    $stmt_get = $conn->prepare($sql_get);
    $stmt_get->bind_param("i", $id);
    $stmt_get->execute();
    $stmt_get->bind_result($saved);
    $stmt_get->fetch();
    $stmt_get->close();

    $new_saved = ($saved == 1) ? 0 : 1;

    $sql_update = "UPDATE fiches_test SET saved = ? WHERE id = ?";
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bind_param("ii", $new_saved, $id);
    $stmt_update->execute();
    $stmt_update->close();

    header("Location: intervention.php?toggled_factured=1");
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<link rel="stylesheet" href="style.css">
<meta charset="UTF-8">
<title>Fiches d'intervention</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
<!-- Remplacer le style existant par celui-ci -->
<style>
.action-icons i { margin-right: 8px; cursor: pointer; }
.badge-facturable { background-color: orange; }
.badge-non { background-color: grey; }
.badge-warning { background-color: #ffc107; color: #000; }
.badge-success { background-color: #28a745; }
body { padding-top: 60px; }

/* Styles pour le modal PDF fullscreen - CENTRÉ */
#previewModal .modal-dialog {
    max-width: 100%;
    margin: 0;
    width: 100%;
    height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
}

#previewModal .modal-content {
    height: 100vh;
    border-radius: 0;
    display: flex;
    flex-direction: column;
    width: 100%;
}

#previewModal .modal-header {
    flex-shrink: 0;
    border-bottom: 1px solid #dee2e6;
}

#previewModal .modal-body {
    background-color: #f5f5f5;
    padding: 10px;
    height: calc(100vh - 120px);
    overflow: hidden;
    flex: 1;
    display: flex;
    justify-content: center;
    align-items: center;
}

#previewModal .modal-footer {
    flex-shrink: 0;
    border-top: 1px solid #dee2e6;
}

#pdfFrame {
    width: 100%;
    height: 100%;
    border: none;
    display: block;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.dropdown-list {
    list-style: none;
    padding: 0;
    margin: 0;
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border: 1px solid #ddd;
    border-top: none;
    max-height: 250px;
    overflow-y: auto;
    z-index: 1000;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}
.dropdown-list li {
    padding: 10px 12px;
    cursor: pointer;
    border-bottom: 1px solid #f0f0f0;
}
.dropdown-list li:hover {
    background-color: #f8f9fa;
}
.dropdown-list li.selected {
    background-color: #e3f2fd;
}
</style>

<!-- Modal Prévisualisation PDF -->
<div class="modal fade" id="previewModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Prévisualisation PDF</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <iframe id="pdfFrame"></iframe>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
      </div>
    </div>
  </div>
</div>
</head>
<body>
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4>Fiches d'intervention</h4>
        <a href="newfit.php" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Ajouter</a>
    </div>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-success">✅ Fiche supprimée</div>
    <?php endif; ?>
    <?php if (isset($_GET['uploaded'])): ?>
        <div class="alert alert-success">✅ Pièce jointe PDF ajoutée avec succès</div>
    <?php endif; ?>
    <?php if (isset($_GET['upload_error'])): ?>
        <div class="alert alert-danger">❌ Erreur : Veuillez sélectionner un fichier PDF valide</div>
    <?php endif; ?>
    <?php if (isset($_GET['marked_factured'])): ?>
        <div class="alert alert-success">✅ Fiche marquée comme facturée</div>
    <?php endif; ?>
    <?php if (isset($_GET['toggled_factured'])): ?>
        <div class="alert alert-success">✅ Statut de facturation modifié</div>
    <?php endif; ?>

    <!-- BARRE DE RECHERCHE ET FILTRAGE -->
    <form method="get" class="card p-3 mb-4">
        <div class="row g-2">
            <div class="col-md-4">
                <label for="search_client" class="form-label">🔍 Chercher un client</label>
                <div style="position: relative;">
                    <input type="text" class="form-control" id="search_client" name="search_client"
                           placeholder="Nom du client..." value="<?= htmlspecialchars($search_client) ?>"
                           autocomplete="off" oninput="filterClients(); filterTable()">
                    <ul id="client_dropdown" class="dropdown-list" style="display: none;">
                    </ul>
                </div>
            </div>
            <div class="col-md-3">
                <label for="date_from" class="form-label">📅 Du</label>
                <input type="date" class="form-control" id="date_from" name="date_from"
                       value="<?= htmlspecialchars($date_from) ?>">
            </div>
            <div class="col-md-3">
                <label for="date_to" class="form-label">📅 Au</label>
                <input type="date" class="form-control" id="date_to" name="date_to"
                       value="<?= htmlspecialchars($date_to) ?>">
            </div>
            <div class="col-md-2 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary w-100" style="display:none;"><i class="bi bi-search"></i> Filtrer</button>
                <a href="intervention.php" class="btn btn-secondary"><i class="bi bi-arrow-clockwise"></i></a>
            </div>
        </div>
    </form>

    <table class="table table-hover table-striped align-middle">
        <thead class="table-primary">
        <tr>
            <th>Date</th><th>Client</th><th>Sous contrat</th><th>Fiche</th><th>Tech</th><th>Facturable</th><th>Actions</th>
        </tr>
        </thead>

        <tbody>
        <?php if($result && $result->num_rows>0): ?>
            <?php while($fiche = $result->fetch_assoc()):
                $pdfFile = generatePDF($fiche);
                $id_contrat = $fiche['id_contrat'] ?? 0;
                $pdfWeb = "data/inter/{$fiche['id_client']}/$id_contrat-{$fiche['id_client']}_"
                          . str_replace(['/', ' ', ':'], '-', $fiche['ladate'])
                          . ".pdf";
                $status = getFacturationStatus($fiche['facturable'] ?? 0, $fiche['saved'] ?? 0);
            ?>
            <tr>
                <td><?= htmlspecialchars($fiche['ladate'] ?? '') ?></td>
                <td><?= htmlspecialchars($fiche['client_nom'] ?? '') ?></td>
                <td><?= ($fiche['id_contrat'] ?? 0)==0 ? '<span class="badge badge-non">Non</span>' : '<span class="badge bg-primary">Oui</span>' ?></td>
                <td>
                    <i class="bi bi-eye text-success" data-bs-toggle="modal" data-bs-target="#previewModal"
                       onclick="document.getElementById('pdfFrame').src='<?= htmlspecialchars($pdfWeb) ?>'"></i>
                    <a href="<?= htmlspecialchars($pdfWeb) ?>" target="_blank"><i class="bi bi-file-earmark-pdf-fill text-danger"></i></a>
                    <i class="bi bi-paperclip text-primary" data-bs-toggle="modal" data-bs-target="#pjModal"
                       onclick="document.getElementById('id_fiche_input').value='<?= htmlspecialchars($fiche['id'] ?? '') ?>'"></i>
                </td>
                <td><?= htmlspecialchars($fiche['tech'] ?? '') ?></td>
                <td>
                    <?php if ($status['class'] === 'badge-warning' || $status['class'] === 'badge-success'): ?>
                        <a href="intervention.php?toggle_factured=<?= $fiche['id'] ?>" style="text-decoration: none;" onclick="return confirm('Êtes-vous sûr de vouloir changer le statut de facturation ?');">
                            <span class="badge <?= $status['class'] ?>" style="cursor: pointer; display: inline-block;">
                                <?= $status['label'] ?>
                            </span>
                        </a>
                    <?php else: ?>
                        <span class="badge <?= $status['class'] ?>"><?= $status['label'] ?></span>
                    <?php endif; ?>
                </td>
                <td class="action-icons">
                    <a href="newfit.php?id=<?= $fiche['id'] ?? 0 ?>"><i class="bi bi-pencil text-primary"></i></a>
                    <a href="intervention.php?delete=<?= $fiche['id'] ?? 0 ?>" onclick="return confirm('Supprimer cette fiche ?')">
                        <i class="bi bi-trash text-danger"></i></a>
                </td>
            </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="7" class="text-center">Aucune fiche trouvée.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal Prévisualisation PDF -->
<div class="modal fade" id="previewModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Prévisualisation PDF</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body"><iframe id="pdfFrame"></iframe></div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button></div>
    </div>
  </div>
</div>

<!-- Modal Ajouter Pièce Jointe -->
<div class="modal fade" id="pjModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" enctype="multipart/form-data">
        <div class="modal-header"><h5 class="modal-title">Ajouter pièce jointe (PDF)</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <input type="hidden" name="id_fiche" id="id_fiche_input">
            <div class="mb-3">
                <label for="pj_file" class="form-label">Sélectionner un fichier PDF</label>
                <input type="file" name="pj_file" id="pj_file" required class="form-control" accept=".pdf">
                <small class="text-muted">Seuls les fichiers PDF sont acceptés</small>
            </div>
        </div>
        <div class="modal-footer">
            <button type="submit" name="upload_pj" class="btn btn-primary">Uploader</button>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Récupérer tous les clients uniques du tableau
function getAllClients() {
    const clients = new Set();
    document.querySelectorAll('tbody tr td:nth-child(2)').forEach(td => {
        const clientName = td.textContent.trim();
        if (clientName && clientName !== '') {
            clients.add(clientName);
        }
    });
    return Array.from(clients).sort();
}

// Filtrer et afficher les clients correspondants
function filterClients() {
    const input = document.getElementById('search_client').value.toLowerCase();
    const dropdown = document.getElementById('client_dropdown');
    const allClients = getAllClients();

    if (input.length === 0) {
        dropdown.style.display = 'none';
        return;
    }

    const filtered = allClients.filter(client =>
        client.toLowerCase().includes(input)
    );

    dropdown.innerHTML = '';

    if (filtered.length === 0) {
        dropdown.style.display = 'none';
        return;
    }

    filtered.forEach(client => {
        const li = document.createElement('li');
        li.textContent = client;
        li.onclick = () => {
            document.getElementById('search_client').value = client;
            dropdown.style.display = 'none';
            filterTable();
        };
        dropdown.appendChild(li);
    });

    dropdown.style.display = 'block';
}

// Filtrer le tableau automatiquement
function filterTable() {
    const searchClient = document.getElementById('search_client').value.toLowerCase();
    const dateFrom = document.getElementById('date_from').value;
    const dateTo = document.getElementById('date_to').value;
    const rows = document.querySelectorAll('tbody tr');

    rows.forEach(row => {
        const clientCell = row.querySelector('td:nth-child(2)');
        const dateCell = row.querySelector('td:nth-child(1)');

        if (!clientCell || !dateCell) return;

        const clientName = clientCell.textContent.trim().toLowerCase();
        const rowDate = dateCell.textContent.trim();

        let showRow = true;

        if (searchClient && !clientName.includes(searchClient)) {
            showRow = false;
        }

        if (dateFrom && rowDate < dateFrom) {
            showRow = false;
        }

        if (dateTo && rowDate > dateTo) {
            showRow = false;
        }

        row.style.display = showRow ? '' : 'none';
    });

    const visibleRows = Array.from(rows).filter(row => row.style.display !== 'none');
    const emptyRow = document.querySelector('tbody tr td[colspan="7"]');
    if (emptyRow) {
        emptyRow.parentElement.style.display = visibleRows.length === 0 ? '' : 'none';
    }
}

// Fermer le dropdown quand on clique ailleurs
document.addEventListener('click', (e) => {
    const dropdown = document.getElementById('client_dropdown');
    const input = document.getElementById('search_client');
    if (!input.contains(e.target) && !dropdown.contains(e.target)) {
        dropdown.style.display = 'none';
    }
});
</script>
</html>

<?php $conn->close(); ?>