<?php
require_once __DIR__ . '/auth.php';
requireLogin();
require_once 'db_config.php';

global $conn;

$string = trim(empty($_POST['search']) ? ($_GET['search'] ?? '') : $_POST['search']);

if (!isset($_SESSION)) session_start();
$_SESSION['search'] = $string;

// Tables exclues (données techniques internes sans intérêt pour la recherche)
$excluded = ['papiers', 'certif', 'moniteurs', 'inter', 'fiches'];

$tables = [];
$res = $conn->query("SHOW TABLES");
while ($row = $res->fetch_row()) {
    if (!in_array($row[0], $excluded)) $tables[] = $row[0];
}

function searchTable(string $table, string $search): array {
    global $conn;
    $res  = $conn->query("SHOW COLUMNS FROM `$table`");
    $cols = [];
    while ($row = $res->fetch_row()) $cols[] = $row[0];
    if (empty($cols)) return [];

    $safeSearch = $conn->real_escape_string($search);
    $conditions = array_map(fn($c) => "`$c` LIKE '%$safeSearch%'", $cols);
    $result     = $conn->query("SELECT * FROM `$table` WHERE " . implode(' OR ', $conditions));
    if (!$result) return [];

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $matched = [];
        foreach ($cols as $col)
            if (isset($row[$col]) && stripos($row[$col], $search) !== false)
                $matched[] = ['field' => $col, 'value' => $row[$col]];
        $row['_matched'] = $matched;
        $rows[] = $row;
    }
    return $rows;
}

function buildDirectUrl(string $table, array $row, string $clientNom): string {
    $name = urlencode($clientNom);
    switch ($table) {
        case 'clients':     return "client.php?client_name=$name&page=infos";
        case 'ordis':       return "client.php?client_name=$name&page=ordinateurs&id=" . (int)($row['id'] ?? 0);
        case 'imprimantes': return "client.php?client_name=$name&page=imprimantes&id=" . (int)($row['id'] ?? 0);
        case 'lic':         return "client.php?client_name=$name&page=licences";
        case 'rips':        return "client.php?client_name=$name&page=rips";
        case 'stockage':    return "client.php?client_name=$name&page=stockage";
        case 'fiches_test': return "client.php?client_name=$name&page=interventions";
        case 'produits':    return "produits.php";
        case 'activation':  return "activation.php?vue=tout";
        case 'contrats':    return "client.php?client_name=$name&page=contrats";
        default:            return "client.php?client_name=$name&page=infos";
    }
}

function highlight(string $value, string $search): string {
    if ($search === '') return htmlspecialchars($value);
    return preg_replace(
        '/(' . preg_quote(htmlspecialchars($search), '/') . ')/i',
        '<mark>$1</mark>',
        htmlspecialchars($value)
    );
}

function tableLabel(string $table): array {
    $map = [
        'clients'         => ['Clients',          '👤'],
        'fiches_test'     => ['Interventions',    '🔧'],
        'imprimantes'     => ['Imprimantes',      '🖨️'],
        'lic'             => ['Licences',         '🔑'],
        'ordis'           => ['Ordinateurs',      '💻'],
        'rips'            => ['RIPs',             '📡'],
        'odm'             => ['Outils de mesure', '📏'],
        'stockage'        => ['Stockage',         '💾'],
        'support'         => ['Object tickets',   '🎫'],
        'support_details' => ['Contenu tickets',  '📝'],
        'produits'        => ['Produits',         '📦'],
        'activation'      => ['Activations',      '⚡'],
        'contrats'        => ['Contrats',         '📄'],
    ];
    return $map[$table] ?? [ucfirst($table), '📋'];
}

// Résoudre le nom du client selon la table
function resolveClientName(string $table, array $row): string {
    global $conn;

    // Table clients : le nom est directement dans la ligne
    if ($table === 'clients') {
        return $row['nom'] ?? '(inconnu)';
    }

    // Table produits : pas de client lié
    if ($table === 'produits') {
        return '—';
    }

    // Toutes les autres tables ont id_client
    $clientId = (int)($row['id_client'] ?? 0);
    if ($clientId === 0) return '(inconnu)';

    $clientRes = $conn->query("SELECT nom FROM clients WHERE id = $clientId");
    return ($clientRes && $clientRes->num_rows > 0)
        ? $clientRes->fetch_row()[0]
        : '(inconnu)';
}

// Construire un résumé contextuel selon la table pour enrichir l'affichage
function buildContextBadges(string $table, array $row): string {
    $badges = '';
    switch ($table) {
        case 'lic':
            $parts = array_filter([
                $row['editeur'] ?? '',
                $row['modele']  ?? '',
                $row['version'] ?? '',
            ]);
            if ($parts) $badges .= '<span class="ctx-badge ctx-blue">🔑 ' . htmlspecialchars(implode(' ', $parts)) . '</span>';
            if (!empty($row['date_expiration']))
                $badges .= '<span class="ctx-badge ctx-orange">📅 Exp : ' . htmlspecialchars($row['date_expiration']) . '</span>';
            break;

        case 'activation':
            if (!empty($row['editeur']))
                $badges .= '<span class="ctx-badge ctx-blue">⚡ ' . htmlspecialchars($row['editeur']) . '</span>';
            if (!empty($row['type']))
                $badges .= '<span class="ctx-badge ctx-gray">' . htmlspecialchars($row['type']) . '</span>';
            $stateLabel = isset($row['state']) ? ($row['state'] == 1 ? '✅ Terminé' : '⏳ En attente') : '';
            if ($stateLabel) $badges .= '<span class="ctx-badge ' . ($row['state'] == 1 ? 'ctx-green' : 'ctx-orange') . '">' . $stateLabel . '</span>';
            break;

        case 'contrats':
            if (!empty($row['type']))
                $badges .= '<span class="ctx-badge ctx-blue">📄 ' . htmlspecialchars($row['type']) . '</span>';
            if (!empty($row['start']) && !empty($row['end']))
                $badges .= '<span class="ctx-badge ctx-gray">📅 ' . htmlspecialchars($row['start']) . ' → ' . htmlspecialchars($row['end']) . '</span>';
            $stateLabel = isset($row['state']) ? ($row['state'] == 1 ? '✅ Actif' : '❌ Expiré') : '';
            if ($stateLabel) $badges .= '<span class="ctx-badge ' . ($row['state'] == 1 ? 'ctx-green' : 'ctx-red') . '">' . $stateLabel . '</span>';
            break;

        case 'fiches_test':
            if (!empty($row['ladate']))
                $badges .= '<span class="ctx-badge ctx-gray">📅 ' . htmlspecialchars($row['ladate']) . '</span>';
            if (!empty($row['objet']))
                $badges .= '<span class="ctx-badge ctx-blue">🔧 ' . htmlspecialchars(mb_substr($row['objet'], 0, 60)) . (mb_strlen($row['objet'] ?? '') > 60 ? '…' : '') . '</span>';
            break;

        case 'produits':
            $parts = array_filter([
                $row['cat']     ?? '',
                $row['marque']  ?? '',
                $row['modele']  ?? '',
                $row['version'] ?? '',
            ]);
            if ($parts) $badges .= '<span class="ctx-badge ctx-blue">📦 ' . htmlspecialchars(implode(' · ', $parts)) . '</span>';
            break;
    }
    return $badges;
}

$allResults = [];
foreach ($tables as $table) $allResults[$table] = searchTable($table, $string);

include 'navbar.php';
?>

<link rel="stylesheet" href="style.css">

<style>
/* ─── Layout ────────────────────────────────────────── */
.search-page {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}

/* ─── En-tête résultats ─────────────────────────────── */
.search-page > h3.search-title {
    color: #2c3e50;
    border-bottom: 3px solid #3498db;
    padding-bottom: 10px;
    margin-bottom: 25px;
    font-size: 22px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.search-subtitle {
    font-size: 13px;
    color: #95a5a6;
    font-weight: 400;
    margin-left: auto;
}

/* ─── Pills navigation ──────────────────────────────── */
.pills-container {
    background: linear-gradient(135deg, #f9f9f9 0%, #f0f0f0 100%);
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 15px 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}
.pills-label {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    color: #95a5a6;
    margin-bottom: 10px;
}
.pills-list {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    list-style: none;
    margin: 0;
    padding: 0;
}
.pills-list li a {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 6px 14px;
    background: #2980b9;
    color: white;
    border-radius: 5px;
    font-size: 13px;
    font-weight: 500;
    text-decoration: none;
    transition: background 0.3s;
}
.pills-list li a:hover { background: #1f618d; color: white; }
.pills-list li a .count {
    background: rgba(255,255,255,0.25);
    border-radius: 10px;
    padding: 1px 7px;
    font-size: 11px;
    font-weight: 700;
}
.pills-list li a.empty {
    background: #bdc3c7;
    opacity: 0.6;
    pointer-events: none;
}

/* ─── Section résultats ─────────────────────────────── */
.result-section {
    background: linear-gradient(135deg, #f9f9f9 0%, #f0f0f0 100%);
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    margin-bottom: 20px;
    overflow: hidden;
    transition: transform 0.2s, box-shadow 0.2s;
}
.result-section:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.result-section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 15px 20px;
    border-bottom: 2px solid #3498db;
    background: white;
    position: relative;
}
.result-section-header h4 {
    color: #34495e;
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
}
.result-count-badge {
    background: #3498db;
    color: white;
    padding: 3px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}
.totop-btn {
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
    background: #f0f0f0;
    color: #555;
    border: 1px solid #ddd;
    padding: 4px 12px;
    border-radius: 5px;
    font-size: 12px;
    font-weight: 500;
    text-decoration: none;
    transition: background 0.3s;
}
.totop-btn:hover { background: #e0e0e0; color: #2c3e50; }

/* ─── Cards résultats ───────────────────────────────── */
.result-cards {
    display: flex;
    flex-direction: column;
    padding: 5px 0;
}
.result-card {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 13px 20px;
    border-bottom: 1px solid #ddd;
    text-decoration: none;
    color: #2c3e50;
    background: white;
    transition: all 0.2s;
}
.result-card:last-child { border-bottom: none; }
.result-card:hover {
    background: #eaf1f8;
    padding-left: 28px;
    color: #2c3e50;
    text-decoration: none;
}
.result-card-icon {
    font-size: 1.3rem;
    flex-shrink: 0;
    margin-top: 2px;
}
.result-card-body { flex: 1; min-width: 0; }
.result-card-top {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 6px;
    flex-wrap: wrap;
}
.client-name {
    font-weight: 600;
    font-size: 14px;
    color: #3498db;
    transition: color 0.2s;
}
.result-card:hover .client-name { color: #1f618d; }
.goto-badge {
    font-size: 11px;
    background: #d5e5f5;
    color: #1f618d;
    padding: 2px 10px;
    border-radius: 5px;
    font-weight: 600;
    opacity: 0;
    transition: opacity 0.2s;
}
.result-card:hover .goto-badge { opacity: 1; }

/* ─── Context badges (résumé rapide) ────────────────── */
.ctx-badges {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
    margin-bottom: 6px;
}
.ctx-badge {
    display: inline-block;
    font-size: 11px;
    padding: 2px 9px;
    border-radius: 20px;
    font-weight: 500;
    white-space: nowrap;
    max-width: 320px;
    overflow: hidden;
    text-overflow: ellipsis;
}
.ctx-blue   { background: #dbeafe; color: #1e40af; }
.ctx-green  { background: #dcfce7; color: #166534; }
.ctx-orange { background: #fef3c7; color: #92400e; }
.ctx-red    { background: #fee2e2; color: #991b1b; }
.ctx-gray   { background: #f1f5f9; color: #475569; }

/* ─── Match tags ────────────────────────────────────── */
.match-tags { display: flex; flex-wrap: wrap; gap: 5px; }
.match-tag {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #f5f5f5;
    border: 1px solid #ddd;
    border-radius: 5px;
    padding: 3px 10px;
    font-size: 12px;
}
.match-tag .field-name {
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #95a5a6;
}
.match-tag .field-value {
    color: #2c3e50;
    font-size: 13px;
    max-width: 300px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.match-tag mark {
    background: #fff3cd;
    color: #856404;
    border-radius: 3px;
    padding: 0 3px;
    font-weight: 700;
    font-style: normal;
}
.no-result {
    padding: 18px 20px;
    color: #95a5a6;
    font-style: italic;
    font-size: 14px;
    background: white;
}

/* ─── Responsive ────────────────────────────────────── */
@media (max-width: 768px) {
    .search-page { padding: 15px; }
    .result-card { padding: 11px 15px; }
    .result-card:hover { padding-left: 20px; }
    .match-tag .field-value { max-width: 160px; }
    .search-page > h3.search-title { font-size: 18px; flex-wrap: wrap; }
}
</style>

<div class="search-page">

    <!-- ── Titre ──────────────────────────────────── -->
    <h3 class="search-title">
        🔍 Résultats pour "<?= htmlspecialchars($string) ?>"
        <span class="search-subtitle">
            <?php
                $total             = array_sum(array_map('count', $allResults));
                $tablesWithResults = count(array_filter($allResults, fn($r) => !empty($r)));
            ?>
            <?= $total ?> résultat<?= $total > 1 ? 's' : '' ?>
            &nbsp;·&nbsp;
            <?= $tablesWithResults ?> table<?= $tablesWithResults > 1 ? 's' : '' ?>
        </span>
    </h3>

    <!-- ── Pills navigation ───────────────────────── -->
    <div class="pills-container">
        <div class="pills-label">Aller à une section</div>
        <ul class="pills-list">
            <?php foreach ($tables as $table):
                [$label, $icon] = tableLabel($table);
                $totable        = str_replace([' ', '/'], '-', $label);
                $count          = count($allResults[$table]);
            ?>
            <li>
                <a class="scrollto <?= $count === 0 ? 'empty' : '' ?>" href="#<?= $totable ?>">
                    <?= $icon ?> <?= htmlspecialchars($label) ?>
                    <span class="count"><?= $count ?></span>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <!-- ── Résultats par table ────────────────────── -->
    <?php foreach ($tables as $table):
        $results        = $allResults[$table];
        [$label, $icon] = tableLabel($table);
        $totable        = str_replace([' ', '/'], '-', $label);
    ?>
    <div class="result-section" id="<?= $totable ?>">

        <div class="result-section-header">
            <h4><?= $icon ?> <?= htmlspecialchars($label) ?></h4>
            <a href="#" class="totop-btn scrolltop">↑ Haut</a>
        </div>

        <?php if (empty($results)): ?>
            <div class="no-result">Aucun résultat pour cette catégorie.</div>
        <?php else: ?>
            <div class="result-cards">
                <?php foreach ($results as $result): ?>
                    <?php
                        $clientNom  = resolveClientName($table, $result);
                        $directUrl  = buildDirectUrl($table, $result, $clientNom);
                        $ctxBadges  = buildContextBadges($table, $result);
                        $isNoClient = ($table === 'produits');
                    ?>
                    <a href="<?= $directUrl ?>" class="result-card">
                        <div class="result-card-icon"><?= $icon ?></div>
                        <div class="result-card-body">
                            <div class="result-card-top">
                                <?php if ($isNoClient): ?>
                                    <span class="client-name">📦 Catalogue produits</span>
                                <?php else: ?>
                                    <span class="client-name">👤 <?= htmlspecialchars($clientNom) ?></span>
                                <?php endif; ?>
                                <span class="goto-badge">→ Ouvrir</span>
                            </div>

                            <?php if ($ctxBadges): ?>
                                <div class="ctx-badges"><?= $ctxBadges ?></div>
                            <?php endif; ?>

                            <div class="match-tags">
                                <?php foreach ($result['_matched'] as $match): ?>
                                    <span class="match-tag">
                                        <span class="field-name"><?= htmlspecialchars($match['field']) ?></span>
                                        <span class="field-value"><?= highlight($match['value'], $string) ?></span>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
    <?php endforeach; ?>

</div>

<script>
    var $root = $('html, body');
    $('a.scrollto').click(function(e) {
        var t = $.attr(this, 'href');
        if (t.startsWith('#') && $(t).length) {
            e.preventDefault();
            $root.animate({ scrollTop: $(t).offset().top - 60 }, 400);
        }
    });
    $('a.scrolltop').click(function(e) {
        e.preventDefault();
        $root.animate({ scrollTop: 0 }, 400);
    });
</script>