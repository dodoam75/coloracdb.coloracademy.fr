<?php
require_once __DIR__ . '/auth.php';
requireLogin();
?>
<html lang="fr">

<head>
    <link rel="stylesheet" href="style.css">
    <meta charset="UTF-8">
    <title>Signature</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
        }

        #signature-pad {
            border: 2px solid #333;
            width: 400px;
            height: 200px;
            cursor: crosshair;
        }

        .buttons {
            margin-top: 10px;
        }
    </style>
</head>

<body>

    <h2>Signer la fiche d'intervention</h2>

    <canvas id="signature-pad" width="400" height="200"></canvas>

    <div class="buttons">
        <button onclick="clearSignature();">Effacer</button>
        <button onclick="saveSignature();window.location.href='intervention.php';">Enregistrer la signature</button>
        <button onclick="window.location.href='newfit.php';">Annuler</button>
    </div>

    <p id="resultat" style="margin-top:15px;color:green;"></p>

    <script>
        // Récupère automatiquement l'ID depuis l'URL
        function getFicheIdFromURL() {
            const params = new URLSearchParams(window.location.search);
            return params.get('id');
        }

        const idFiche = getFicheIdFromURL();

        const canvas = document.getElementById("signature-pad");
        const ctx = canvas.getContext("2d");
        let drawing = false;

        canvas.addEventListener("mousedown", () => drawing = true);
        canvas.addEventListener("mouseup", () => {
            drawing = false;
            ctx.beginPath();
        });
        canvas.addEventListener("mouseout", () => drawing = false);
        canvas.addEventListener("mousemove", draw);

        function draw(e) {
            if (!drawing) return;
            const rect = canvas.getBoundingClientRect();
            ctx.lineWidth = 2;
            ctx.lineCap = "round";
            ctx.strokeStyle = "#000";
            ctx.lineTo(e.clientX - rect.left, e.clientY - rect.top);
            ctx.stroke();
            ctx.beginPath();
            ctx.moveTo(e.clientX - rect.left, e.clientY - rect.top);
        }

        function clearSignature() {
            ctx.fillStyle = "#fff"; // Fond blanc
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            ctx.beginPath();
            document.getElementById("resultat").innerText = '';
        }

        // Initialiser le canvas avec un fond blanc au chargement
        window.onload = () => {
            clearSignature();
        };

        function saveSignature() {
            if (!idFiche) {
                alert("ID de fiche manquant dans l'URL.");
                return;
            }

            // Convertit le canvas en blob PNG (image binaire)
            canvas.toBlob(function(blob) {
                if (!blob) {
                    alert("Erreur lors de la conversion de la signature.");
                    return;
                }

                const formData = new FormData();
                formData.append('signature', blob, 'signature.png');
                formData.append('idFiche', idFiche);

                fetch("enregistrer_signature.php", {
                        method: "POST",
                        body: formData
                    })
                    .then(response => response.text())
                    .then(text => {
                        document.getElementById("resultat").innerText = text;
                        // Si tu veux rediriger après succès, décommente la ligne suivante :
                        // window.location.href = 'newfit.php';
                    })
                    .catch(err => {
                        alert("Erreur : " + err);
                    });
            }, 'image/png');
        }
    </script>

</body>

</html>