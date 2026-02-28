<?php
// 1. Démarrage de la session en tout premier
session_start();

// 2. Inclusions
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db_config.php'; // Ici, $conn est un objet mysqli
requireLogin();

$success_message = null;
$error_message = null;

// 3. Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');

    if (!empty($nom)) {
        // LOGIQUE POUR TROUVER LE PROCHAIN ID (mysqli)
        // Recherche du plus petit ID manquant
        $sql_id = "SELECT (t1.id + 1) AS next_id
                   FROM clients t1
                   WHERE NOT EXISTS (SELECT 1 FROM clients t2 WHERE t2.id = t1.id + 1)
                   ORDER BY t1.id ASC
                   LIMIT 1";

        $res_id = $conn->query($sql_id);
        $row = $res_id->fetch_assoc();
        $next_id = $row ? $row['next_id'] : 1;

        // Vérification si l'ID 1 est libre
        $check_one = $conn->query("SELECT id FROM clients WHERE id = 1");
        if ($check_one->num_rows == 0) {
            $next_id = 1;
        }

        $date_now = date('Y-m-d H:i:s');

        // 4. Préparation de l'insertion (Sécurité contre injections SQL)
        $sql_insert = "INSERT INTO clients (
            id, nom, blacklist, client_ca, cc_email, cc_fax, cc_mobile,
            cc_nom, cc_prenom, cc_tel, ct_email, ct_fax, ct_mobile, ct_nom,
            ct_prenom, ct_tel, adresse, cp, indications, ville, notes,
            crea_date, crea_auteur, modif_date, modif_auteur, exp_maintenance, global_modif
        ) VALUES (
            ?, ?, 0, '', '', '', '', '', '', '', '', '', '', '', '', '',
            '', '', '', '', '', ?, '', ?, '', '', ?
        )";

        $stmt = $conn->prepare($sql_insert);

        if ($stmt) {
            // "issss" signifie : integer, string, string, string, string
            $stmt->bind_param("issss", $next_id, $nom, $date_now, $date_now, $date_now);

            if ($stmt->execute()) {
                $success_message = "Client ajouté avec succès ! (ID: $next_id)";
                // Redirection vers client.php avec le nom du client en paramètre
                $client_name_encoded = urlencode($nom);
                header("refresh:2;url=client.php?client_name=$client_name_encoded");
            } else {
                $error_message = "Erreur lors de l'insertion : " . $conn->error;
            }
            $stmt->close();
        } else {
            $error_message = "Erreur de préparation : " . $conn->error;
        }
    } else {
        $error_message = "Le nom est obligatoire.";
    }
}

// Inclusion de la navbar après le PHP
include 'navbar.php';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Ajouter un client</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body { font-family: Arial, sans-serif; background-color: #f5f5f5; margin: 0; display: flex; flex-direction: column; height: 100vh; }
        .main-container { flex: 1; display: flex; justify-content: center; align-items: center; }
        .form-box { background: white; padding: 40px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); width: 400px; text-align: center; }
        .form-group { text-align: left; margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .btn-group { display: flex; gap: 10px; }
        .btn-save { background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; flex: 1; }
        .btn-back { background: #6c757d; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; flex: 1; text-decoration: none; display: inline-block; }
        .alert { padding: 10px; border-radius: 4px; margin-bottom: 20px; font-weight: bold; }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-error { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>

<div class="main-container">
    <div class="form-box">
        <h2>Nouveau Client</h2>

        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-error"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Nom du client *</label>
                <input type="text" name="nom" required value="<?php echo isset($_POST['nom']) ? htmlspecialchars($_POST['nom']) : ''; ?>">
            </div>

            <div class="btn-group">
                <button type="submit" class="btn-save">Enregistrer</button>
                <a href="index.php" class="btn-back">Annuler</a>
            </div>
        </form>
    </div>
</div>

</body>
</html>