<?php
require_once __DIR__ . '/auth.php';
requireLogin();
?>
<?php include 'navbar.php'; ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="stylesheet" href="style.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chercher un client</title>
    <style>
        body, html {
            height: 100%;
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
        }

        .search-container {
            text-align: center;
            background-color: white;
            padding: 50px;
            border-radius: 10px;
            box-shadow: 0px 4px 12px rgba(0, 0, 0, 0.1);
            position: relative;
        }

        .search-input-wrapper {
            position: relative;
            width: 300px;
            margin: 0 auto;
        }

        .search-container input[type="text"] {
            width: 100%;
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            border: 1px solid #ddd;
            font-size: 16px;
            box-sizing: border-box;
        }

        .suggestions {
            border: 1px solid #ddd;
            border-top: none;
            max-height: 200px;
            overflow-y: auto;
            background-color: white;
            position: absolute;
            width: 100%;
            top: 100%;
            left: 0;
            right: 0;
            z-index: 1000;
            display: none;
            border-radius: 0 0 5px 5px;
        }

        .suggestion-item {
            padding: 10px;
            cursor: pointer;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .suggestion-item:hover {
            background-color: #f1f1f1;
        }

        .suggestion-item.active {
            background-color: #e7f3ff;
            border-left: 4px solid #007bff;
            padding-left: 6px;
        }

        .suggestion-item:last-child {
            border-bottom: none;
        }

        .search-container button {
            padding: 15px 30px;
            font-size: 16px;
            color: white;
            background-color: #007bff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .search-container button:hover {
            background-color: #0056b3;
        }

        .add-client {
            margin-top: 20px;
        }
    </style>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>

    <div class="search-container">
        <h2>Chercher un client</h2>
        <!-- Formulaire pour chercher un client -->
        <form action="client.php" method="GET" id="searchForm">
            <div class="search-input-wrapper">
                <input type="text" id="client_name" name="client_name" placeholder="Entrez un nom de client..." autocomplete="off">
                <div class="suggestions" id="suggestions"></div>
            </div>
            <br>
            <!-- Bouton de recherche -->
            <button type="submit">Recherche</button>
        </form>

        <!-- Bouton Ajouter un client -->
        <div class="add-client">
            <button onclick="window.location.href='ajouter_client.php'">Ajouter un nouveau client</button>
        </div>
    </div>

    <script>
        let currentIndex = -1;

        $(document).ready(function() {
            // Écouteur d'événement sur le champ de recherche
            $('#client_name').on('input', function() {
                var query = $(this).val();
                currentIndex = -1;
                if (query !== '') {
                    $.ajax({
                        url: 'search_client.php',
                        method: 'POST',
                        data: { query: query },
                        success: function(data) {
                            $('#suggestions').fadeIn();
                            $('#suggestions').html(data);
                        }
                    });
                } else {
                    $('#suggestions').fadeOut();
                }
            });

            // Navigation au clavier
            $('#client_name').on('keydown', function(e) {
                var suggestions = $('#suggestions .suggestion-item');
                var count = suggestions.length;

                if (count === 0) return;

                switch(e.key) {
                    case 'ArrowDown':
                        e.preventDefault();
                        currentIndex = Math.min(currentIndex + 1, count - 1);
                        updateActive(suggestions);
                        break;
                    case 'ArrowUp':
                        e.preventDefault();
                        currentIndex = Math.max(currentIndex - 1, -1);
                        updateActive(suggestions);
                        break;
                    case 'Enter':
                        e.preventDefault();
                        if (currentIndex >= 0) {
                            $(suggestions[currentIndex]).click();
                        } else {
                            $('#searchForm').submit();
                        }
                        break;
                    case 'Escape':
                        e.preventDefault();
                        $('#suggestions').fadeOut();
                        currentIndex = -1;
                        break;
                }
            });

            // Fonction pour mettre à jour l'élément actif
            function updateActive(suggestions) {
                suggestions.removeClass('active');
                if (currentIndex >= 0) {
                    $(suggestions[currentIndex]).addClass('active');
                    // Scroll pour garder l'élément visible
                    $(suggestions[currentIndex])[0].scrollIntoView({ block: 'nearest' });
                }
            }

            // Rediriger directement quand on clique sur une suggestion
            $(document).on('click', '.suggestion-item', function() {
                var clientId = $(this).data('id');
                var clientName = $(this).text();

                // Redirection directe vers la page du client
                window.location.href = 'client.php?client_name=' + encodeURIComponent(clientName) + '&id=' + clientId;
            });

            // Fermer les suggestions si on clique ailleurs
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.search-input-wrapper').length && !$(e.target).closest('#client_name').length) {
                    $('#suggestions').fadeOut();
                }
            });

            // Réinitialiser l'index au survol de la souris
            $(document).on('mouseenter', '.suggestion-item', function() {
                currentIndex = $(this).index();
                $('#suggestions .suggestion-item').removeClass('active');
                $(this).addClass('active');
            });
        });
    </script>

</body>
</html>