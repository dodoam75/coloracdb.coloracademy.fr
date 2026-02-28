<?php
require_once __DIR__ . '/auth.php';
requireLogin();

require_once 'db_config.php';

$id = intval($_GET['id'] ?? 0);
$redirect = $_GET['redirect'] ?? 'client.php';

if ($id > 0) {
    // Vérifier que l'imprimante appartient au client ou que l'utilisateur a les droits
    $stmt = $conn->prepare("DELETE FROM imprimantes WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
}

header("Location: " . $redirect);
exit;
?>