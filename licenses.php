<?php
require_once __DIR__ . '/auth.php';
requireLogin();
include 'navbar.php';
require_once __DIR__ . '/OVHEmailService.php';

function checkDateInTenDays($date)
{
    $currentDate = new DateTime();
    $targetDate = DateTime::createFromFormat('d/m/Y', $date);

    // Si le format d/m/Y ne marche pas, essayer Y-m-d
    if (!$targetDate) {
        $targetDate = DateTime::createFromFormat('Y-m-d', $date);
    }

    if (!$targetDate) return 0;

    $interval = $currentDate->diff($targetDate);
    return ($interval->days <= 10 && $interval->invert == 0) ? 1 : 0;
}

function sendExpirationEmail($editeur, $modele, $version, $date, $dongle_id)
{
    try {
        // ✅ Utiliser directement la classe OVHEmailService
        $emailService = new OVHEmailService();

        // ✅ La méthode utilise l'email par défaut de la classe
        return $emailService->sendExpirationEmail(
            $editeur,
            $modele,
            $version,
            $date,
            $dongle_id
        );
    } catch (Exception $e) {
        error_log("Erreur envoi email : " . $e->getMessage());
        return false;
    }
}

$filter = $_POST['filter'] ?? 'not_expire_soon';
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <link rel="stylesheet" href="style.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Filtrage des licences</title>
    <style>
        body {
            font-family: "Segoe UI", Roboto, Arial, sans-serif;
            background: #f4f6f8;
            color: #333;
            margin: 0;
            padding: 0;
        }

        h2 {
            text-align: center;
            margin-top: 40px;
            color: #2c3e50;
        }

        form {
            display: flex;
            justify-content: center;
            margin: 20px 0;
            gap: 15px;
        }

        button {
            background: #e0e0e0;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 15px;
        }

        button:hover {
            background: #d0d0d0;
        }

        button.active {
            background: #3498db;
            color: #fff;
            font-weight: bold;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
        }

        hr {
            width: 80%;
            margin: 20px auto;
            border: 0;
            border-top: 2px solid #ddd;
        }

        ul {
            list-style: none;
            padding: 0;
            max-width: 700px;
            margin: 30px auto;
        }

        li span {
            font-size: 14px;
            color: #666;
        }

        li strong {
            font-size: 14px;
        }

        li {
            background: #fff;
            margin: 10px 0;
            padding: 15px 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 14px;
        }

        .client-link {
            font-size: 14px;
            color: #2c3e50;
            text-decoration: none;
            transition: all 0.2s ease;
            border-bottom: 2px solid transparent;
        }

        .client-link:hover {
            font-size: 14px;
            color: #3498db;
            border-bottom: 2px solid #3498db;
        }

        .no-data {
            text-align: center;
            color: #888;
            font-style: italic;
            margin-top: 30px;
        }

        .email-badge {
            display: inline-block;
            background: #27ae60;
            color: #fff;
            font-size: 11px;
            padding: 4px 8px;
            border-radius: 4px;
            margin-left: 10px;
            font-weight: bold;
        }

        footer {
            text-align: center;
            color: #999;
            font-size: 13px;
            margin-top: 40px;
            padding-bottom: 20px;
        }
    </style>
</head>

<body>
<br>
<br>
<br>
    <form method="post">
        <button type="submit" name="filter" value="not_expire_soon"
            class="<?= $filter === 'not_expire_soon' ? 'active' : '' ?>">
            Licences valides
        </button>

        <button type="submit" name="filter" value="expire_soon"
            class="<?= $filter === 'expire_soon' ? 'active' : '' ?>">
            Licences expirant dans 10 jours
        </button>
    </form>

    <hr>

    <?php
    $sql = "SELECT lic.id, lic.id_client, lic.date_expiration, lic.temp, lic.dongle_id, lic.modele, lic.editeur, lic.version, lic.cron, clients.nom
            FROM lic
            LEFT JOIN clients ON lic.id_client = clients.id";
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        echo "<ul>";
        while ($row = $result->fetch_assoc()) {
            $id = $row['id'];
            $id_client = isset($row['id_client']) ? $row['id_client'] : '-';
            $client_nom = isset($row['nom']) ? $row['nom'] : 'Client inconnu';
            $date = $row['date_expiration'];
            $editeur = $row['editeur'];
            $dongle = isset($row['dongle_id']) ? $row['dongle_id'] : '-';
            $modele = isset($row['modele']) ? $row['modele'] : '';
            $version = isset($row['version']) ? $row['version'] : '';
            $cron = $row['cron'];

            // Ignorer les licences sans date
            if (empty($date)) continue;

            $currentDate = new DateTime();

            // Essayer les deux formats : d/m/Y ou Y-m-d
            $expirationDate = DateTime::createFromFormat('d/m/Y', $date);
            if (!$expirationDate) {
                $expirationDate = DateTime::createFromFormat('Y-m-d', $date);
            }

            // Si date invalide, on l'ignore
            if (!$expirationDate) continue;

            $isIn10Days = checkDateInTenDays($date);
            $isExpired = ($expirationDate < $currentDate);

            // 🚀 Envoyer email si la licence expire dans 10 jours ET email pas encore envoyé (cron == 0)
            $emailSent = false;
            if ($isIn10Days && !$isExpired && $cron == 0) {
                $emailSent = sendExpirationEmail($editeur, $modele, $version, $date, $dongle);

                if ($emailSent) {
                    // Marquer que l'email a été envoyé en mettant cron à 1
                    $updateSql = "UPDATE lic SET cron = 1 WHERE id = " . intval($id);
                    $conn->query($updateSql);
                }
            }

            // Afficher selon le filtre
            if (
                ($filter === 'expire_soon' && $isIn10Days && !$isExpired) ||
                ($filter === 'not_expire_soon' && !$isIn10Days && !$isExpired)
            ) {
                $color = $isIn10Days ? "#e67e22" : "#2ecc71";
                $status = $isIn10Days ? "⏰ Expire bientôt" : "✅ Valide";
                $emailBadge = ($isIn10Days && $cron == 1) ? '<span class="email-badge">📧 Email envoyé</span>' : '';

                $client_url = urlencode($client_nom);
                echo "<li>
                        <div>
                            <span><a href='client.php?client_name=$client_url&page=licences' class='client-link'>Client: <strong>$client_nom</strong></a></span><br>
                            <span>Logiciel : </span><strong>$editeur $modele $version</strong><span> | Dongle ID : </span><strong>$dongle</strong><br>
                            <span>Expiration : $date</span>
                        </div>
                        <div>
                            <span style='color:$color;font-weight:bold;'>$status</span>
                            $emailBadge
                        </div>
                      </li>";
            }
        }
        echo "</ul>";
    } else {
        echo "<p class='no-data'>Aucune donnée trouvée.</p>";
    }
    ?>

</body>
</html>

<?php
$conn->close();
?>