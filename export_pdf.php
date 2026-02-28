<?php
require_once __DIR__ . '/auth.php';
requireLogin();
?>
<?php
require_once 'dompdf/autoload.inc.php';
use Dompdf\Dompdf;

if (!isset($_GET['id'])) {
    die("Fiche manquante.");
}

$id = intval($_GET['id']);
include 'navbar.php'; // ou ta connexion existante
$conn->set_charset("utf8");

// Récupération de la fiche
$stmt = $conn->prepare("SELECT content FROM fiches WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->bind_result($content);
$stmt->fetch();
$stmt->close();
$conn->close();

// Instanciation de Dompdf
$dompdf = new Dompdf();
$dompdf->loadHtml($content);

// Optionnel : définir le format de page
$dompdf->setPaper('A4', 'portrait');

// Rendu
$dompdf->render();

// Téléchargement PDF
$dompdf->stream("fiche_intervention_$id.pdf", ["Attachment" => false]);
exit;