<?php
require_once __DIR__ . '/auth.php';
requireLogin();
?>

<?php
ini_set('display_errors', 1);
ob_start();
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db_config.php';
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    .navbar {
        background-color: #333 !important;
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        width: 100% !important;
        z-index: 9999 !important;
        display: flex !important;
        justify-content: flex-start !important;
        align-items: center !important;
        height: 50px !important;
        padding: 0 1vw !important;
        margin: 0 !important;
    }

    .navbar a {
        color: #fff !important;
        text-decoration: none !important;
        padding: 0 clamp(4px, 1vw, 15px) !important;
        height: 50px !important;
        display: flex !important;
        align-items: center !important;
        font-size: clamp(10px, 1.5vw, 18px) !important;
        transition: all 0.2s ease-in-out !important;
    }

    .navbar a:hover {
        background-color: #555 !important;
    }

    body {
        margin: 0 !important;
        padding: 0 !important;
        padding-top: 50px !important;
    }

    .newfit-button {
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        width: 30px !important;
        height: 30px !important;
        color: white !important;
        text-decoration: none !important;
        transition: all 0.2s ease-in-out !important;
    }

    .newfit-button:hover {
        color: #ddd !important;
    }

    .user-info {
        display: flex !important;
        align-items: center !important;
        gap: 15px !important;
        margin-left: auto !important;
        padding-right: 20px !important;
    }

    .user-info span {
        color: #fff !important;
        font-size: clamp(10px, 1.5vw, 16px) !important;
    }

    .logout-link {
        background-color: #e74c3c !important;
        padding: 8px 15px !important;
        border-radius: 5px !important;
        color: white !important;
        text-decoration: none !important;
        font-size: clamp(10px, 1.5vw, 14px) !important;
        transition: background-color 0.3s !important;
    }

    .logout-link:hover {
        background-color: #c0392b !important;
    }

    /* ── Bouton loupe ───────────────────────────── */
    .search-navbar-btn {
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        width: 30px !important;
        height: 30px !important;
        color: white !important;
        cursor: pointer !important;
        background: none !important;
        border: none !important;
        padding: 0 !important;
        transition: color 0.2s !important;
        font-size: 15px !important;
    }
    .search-navbar-btn:hover {
        color: #ddd !important;
        background: none !important;
        transform: none !important;
        box-shadow: none !important;
    }

    /* ── Modal recherche ────────────────────────── */
    .search-modal-overlay {
        display: none;
        position: fixed;
        top: 0; left: 0;
        width: 100%; height: 100%;
        background: rgba(0,0,0,0.55);
        z-index: 99999;
        justify-content: center;
        align-items: flex-start;
        padding-top: 90px;
    }
    .search-modal-overlay.active { display: flex; }

    .search-modal {
        background: white;
        border-radius: 8px;
        width: 90%;
        max-width: 540px;
        box-shadow: 0 8px 30px rgba(0,0,0,0.25);
        overflow: hidden;
        animation: searchSlideDown 0.2s ease;
    }
    @keyframes searchSlideDown {
        from { transform: translateY(-15px); opacity: 0; }
        to   { transform: translateY(0);     opacity: 1; }
    }

    .search-modal-header {
        background: #333;
        padding: 13px 18px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .search-modal-header span {
        color: white;
        font-size: 14px;
        font-weight: 600;
    }
    .search-modal-close {
        color: #aaa;
        font-size: 20px;
        cursor: pointer;
        background: none;
        border: none;
        padding: 0;
        line-height: 1;
        transition: color 0.2s;
    }
    .search-modal-close:hover {
        color: white;
        background: none !important;
        transform: none !important;
        box-shadow: none !important;
    }

    .search-modal-body {
        padding: 18px;
        display: flex;
        gap: 10px;
    }
    .search-modal-body input[type="text"] {
        flex: 1;
        padding: 10px 14px;
        border: 1px solid #ddd;
        border-radius: 5px;
        font-size: 15px;
        outline: none;
        transition: border-color 0.2s, box-shadow 0.2s;
        color: #2c3e50;
    }
    .search-modal-body input[type="text"]:focus {
        border-color: #3498db;
        box-shadow: 0 0 0 3px rgba(52,152,219,0.12);
    }
    .search-modal-submit {
        background: #3498db !important;
        color: white !important;
        border: none !important;
        border-radius: 5px !important;
        padding: 10px 18px !important;
        font-size: 14px !important;
        font-weight: 600 !important;
        cursor: pointer !important;
        transition: background 0.2s !important;
        white-space: nowrap !important;
    }
    .search-modal-submit:hover {
        background: #2980b9 !important;
        transform: none !important;
        box-shadow: none !important;
    }
</style>

<div class="navbar">
    <a href="index.php">Client</a>
    <a href="produits.php">Produits</a>
    <a href="licenses.php">Licences</a>
    <a href="activation.php">Activation</a>
    <a href="intervention.php">Intervention</a>
    <a href="contrats.php">Contrats</a>

    <?php if (isLoggedIn()): ?>
        <div class="user-info">
            <!-- Bouton loupe recherche -->
            <button class="search-navbar-btn" id="openSearchModal" title="Recherche globale">
                <i class="fas fa-search"></i>
            </button>
            <span>|</span>
            <!-- Bouton nouvelle fiche -->
            <a href="newfit.php" class="newfit-button" title="Nouvelle fiche">
                <i class="fas fa-file-alt"></i>
            </a>
            <span>|</span>
            <span><?php echo htmlspecialchars($_SESSION['username']); ?></span>
            <a href="logout.php" class="logout-link">Déconnexion</a>
        </div>
    <?php endif; ?>
</div>

<!-- ── Modal de recherche globale ──────────────────── -->
<div class="search-modal-overlay" id="searchModalOverlay">
    <div class="search-modal">
        <div class="search-modal-header">
            <span><i class="fas fa-search"></i> &nbsp;Recherche globale</span>
            <button class="search-modal-close" id="closeSearchModal">&times;</button>
        </div>
        <div class="search-modal-body">
            <form method="GET" action="search.php" style="display:flex;gap:10px;width:100%;">
                <input type="hidden" name="t" value="s">
                <input type="text" name="search" id="searchModalInput" placeholder="Rechercher dans toutes les tables…" autocomplete="off">
                <button type="submit" class="search-modal-submit">
                    <i class="fas fa-search"></i> Rechercher
                </button>
            </form>
        </div>
    </div>
</div>

<script>
(function() {
    var overlay = document.getElementById('searchModalOverlay');
    var input   = document.getElementById('searchModalInput');

    // Ouvrir
    document.getElementById('openSearchModal').addEventListener('click', function() {
        overlay.classList.add('active');
        setTimeout(function() { input.focus(); }, 100);
    });

    // Fermer via ×
    document.getElementById('closeSearchModal').addEventListener('click', function() {
        overlay.classList.remove('active');
    });

    // Fermer en cliquant hors du modal
    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) overlay.classList.remove('active');
    });

    // Fermer avec Échap
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') overlay.classList.remove('active');
    });
})();
</script>