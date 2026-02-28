<?php
// On charge auth.php en premier : il gère déjà la session et la sécurité
require_once __DIR__ . '/auth.php';

// Si déjà connecté, rediriger
if (isLoggedIn()) { // On utilise notre fonction isLoggedIn() au lieu de regarder $_SESSION directement
    header('Location: index.php');
    exit;
}

$error = '';

// Traiter la soumission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/auth.php';

    $result = loginUser($_POST['username'] ?? '', $_POST['password'] ?? '');

    if ($result['success']) {
        header('Location: index.php');
        exit;
    } else {
        $error = $result['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="stylesheet" href="style.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .login-container {
            background: white;
            padding: 50px 40px;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
            width: 100%;
            max-width: 450px;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .login-container:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.2);
        }

        h1 {
            color: #2c3e50;
            margin-bottom: 30px;
            text-align: center;
            font-size: 28px;
            font-weight: 700;
            border-bottom: 3px solid #3498db;
            padding-bottom: 15px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #34495e;
            font-weight: 600;
            font-size: 14px;
        }

        input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 15px;
            transition: all 0.3s;
            background: white;
        }

        input:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
            box-shadow: 0 2px 8px rgba(52, 152, 219, 0.3);
        }

        button:hover {
            background: linear-gradient(135deg, #2980b9 0%, #21618c 100%);
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.4);
            transform: translateY(-2px);
        }

        button:active {
            transform: translateY(0);
        }

        .error {
            color: #c0392b;
            padding: 15px;
            background: linear-gradient(135deg, #fadbd8 0%, #f5c6cb 100%);
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            border-left: 4px solid #e74c3c;
            font-weight: 500;
        }

        .info {
            background: linear-gradient(135deg, #f9f9f9 0%, #f0f0f0 100%);
            padding: 20px;
            border-radius: 8px;
            margin-top: 25px;
            border-left: 4px solid #3498db;
        }

        .info strong {
            display: block;
            color: #2c3e50;
            font-size: 14px;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .info-content {
            color: #555;
            font-size: 13px;
            line-height: 1.8;
        }

        .account-item {
            padding: 8px 12px;
            background: white;
            border-radius: 6px;
            margin: 8px 0;
            border: 1px solid #ddd;
            font-family: 'Courier New', monospace;
            color: #34495e;
            font-weight: 500;
        }

        .login-icon {
            font-size: 32px;
            text-align: center;
            margin-bottom: 5px;
        }

        @media (max-width: 480px) {
            .login-container {
                padding: 40px 30px;
            }

            h1 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-icon">🔐</div>
        <h1>Connexion</h1>

        <?php if ($error): ?>
            <div class="error">⚠️ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="username">Nom d'utilisateur</label>
                <input type="text" id="username" name="username" required autofocus placeholder="Entrez votre nom d'utilisateur">
            </div>

            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input type="password" id="password" name="password" required placeholder="Entrez votre mot de passe">
            </div>

            <button type="submit">✓ Se connecter</button>
        </form>

    </div>
</body>
</html>