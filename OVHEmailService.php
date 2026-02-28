<?php
/**
 * Service Email OVH - Alerte Expiration Licence
 * Compatible avec PHPMailer pour OVH
 */

// Charger PHPMailer sans Composer
require_once __DIR__ . '/phpmailer/src/Exception.php';
require_once __DIR__ . '/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class OVHEmailService
{
    private $mailer;
    private $config;

    // ✅ CONFIG PAR DÉFAUT INTÉGRÉE
    private static $defaultConfig = [
        'host'       => '*****',
        'port'       => 666,
        'username'   => '******',
        'password'   => '*******',
        'from_email' => '********',
        'from_name'  => '*******',
        'to_default' => '********'
    ];

    public function __construct($ovhConfig = [])
    {
        $this->mailer = new PHPMailer(true);

        // ✅ Fusionner avec la config par défaut
        $this->config = array_merge(self::$defaultConfig, $ovhConfig);

        $this->setupMailer();
    }

    private function setupMailer()
    {
        try {
            $this->mailer->isSMTP();
            $this->mailer->Host = $this->config['host'];
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = $this->config['username'];
            $this->mailer->Password = $this->config['password'];
            $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $this->mailer->Port = $this->config['port'];
            $this->mailer->CharSet = 'UTF-8';
            $this->mailer->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );
        } catch (Exception $e) {
            error_log("Erreur configuration mailer : {$e->getMessage()}");
        }
    }

    // ✅ MÉTHODE POUR ENVOYER EMAIL D'EXPIRATION (licence.php)
    public function sendExpirationEmail($editeur, $modele, $version, $date, $dongle_id, $to = null)
    {
        // ✅ Utiliser l'adresse par défaut si aucune n'est fournie
        if ($to === null) {
            $to = $this->config['to_default'];
        }

        try {
            // Réinitialiser les destinataires précédents
            $this->mailer->clearAllRecipients();

            // Expéditeur
            $this->mailer->setFrom($this->config['from_email'], $this->config['from_name']);
            $this->mailer->addAddress($to);

            // Objet et contenu
            $this->mailer->Subject = '⏰ Alerte : Licence expire dans 10 jours';
            $this->mailer->isHTML(true);
            $this->mailer->Body = $this->getExpirationEmailTemplate($editeur, $modele, $version, $date, $dongle_id);
            $this->mailer->AltBody = $this->getExpirationPlainTextTemplate($editeur, $modele, $version, $date, $dongle_id);

            return $this->mailer->send();
        } catch (Exception $e) {
            error_log("Erreur envoi email : {$this->mailer->ErrorInfo}");
            return false;
        }
    }

    // ✅ NOUVELLE MÉTHODE POUR ENVOYER EMAIL D'ACTIVATION (activation.php)
    public function sendActivationEmail($nom_client, $code = '123', $to = null)
    {
        // ✅ Utiliser l'adresse par défaut si aucune n'est fournie
        if ($to === null) {
            $to = $this->config['to_default'];
        }

        try {
            // Réinitialiser les destinataires précédents
            $this->mailer->clearAllRecipients();

            // Expéditeur
            $this->mailer->setFrom($this->config['from_email'], $this->config['from_name']);
            $this->mailer->addAddress($to);

            // Objet et contenu
            $this->mailer->Subject = '📧 Activation Client';
            $this->mailer->isHTML(true);
            $this->mailer->Body = $this->getActivationEmailTemplate($nom_client, $code);
            $this->mailer->AltBody = $this->getActivationPlainTextTemplate($nom_client, $code);

            return $this->mailer->send();
        } catch (Exception $e) {
            error_log("Erreur envoi email activation : {$this->mailer->ErrorInfo}");
            return false;
        }
    }

    // ✅ TEMPLATE HTML POUR EMAIL D'EXPIRATION
    private function getExpirationEmailTemplate($editeur, $modele, $version, $date, $dongle_id)
    {
        $currentDate = date('d/m/Y H:i');

        return "
        <html>
            <head>
                <meta charset='UTF-8'>
                <title>Alerte Expiration Licence</title>
                <style>
                    body {
                        font-family: Arial, sans-serif;
                        margin: 0;
                        padding: 0;
                        background: #f4f6f8;
                    }
                    .container {
                        max-width: 600px;
                        margin: 0 auto;
                        padding: 20px;
                    }
                    .alert {
                        background: #fff3cd;
                        border: 1px solid #ffc107;
                        padding: 20px;
                        border-radius: 5px;
                        border-left: 4px solid #e67e22;
                    }
                    .alert h2 {
                        color: #e67e22;
                        margin-top: 0;
                        font-size: 24px;
                    }
                    .info {
                        margin: 15px 0;
                        background: #ffffff;
                        padding: 15px;
                        border-left: 4px solid #e67e22;
                        border-radius: 3px;
                    }
                    .label {
                        font-weight: bold;
                        color: #333;
                        display: inline-block;
                        min-width: 120px;
                    }
                    .value {
                        color: #555;
                    }
                    .footer {
                        margin-top: 30px;
                        font-size: 12px;
                        color: #999;
                        text-align: center;
                        border-top: 1px solid #ddd;
                        padding-top: 15px;
                    }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='alert'>
                        <h2>⏰ Alerte d'expiration de licence</h2>
                        <p style='margin: 10px 0; color: #333;'>Une licence expire dans 10 jours. Action urgente recommandée.</p>

                        <div class='info'>
                            <p>
                                <span class='label'>Logiciel :</span>
                                <span class='value'>$editeur $modele $version</span>
                            </p>
                        </div>

                        <div class='info'>
                            <p>
                                <span class='label'>Dongle ID :</span>
                                <span class='value'>$dongle_id</span>
                            </p>
                        </div>

                        <div class='info'>
                            <p>
                                <span class='label'>Expiration :</span>
                                <span class='value'>$date</span>
                            </p>
                        </div>

                        <p style='margin-top: 20px; color: #333; line-height: 1.6;'>
                            Veuillez <strong>vérifier et renouveler</strong> cette licence dès que possible pour éviter toute interruption de service.
                        </p>
                    </div>

                    <div class='footer'>
                        <p>Email généré le $currentDate</p>
                        <p>Message automatique - Ne pas répondre à cet email</p>
                    </div>
                </div>
            </body>
        </html>
        ";
    }

    // ✅ TEMPLATE TEXTE POUR EMAIL D'EXPIRATION
    private function getExpirationPlainTextTemplate($editeur, $modele, $version, $date, $dongle_id)
    {
        return "⏰ ALERTE D'EXPIRATION DE LICENCE\n\n" .
               "Une licence expire dans 10 jours.\n\n" .
               "=====================\n" .
               "Logiciel : $editeur $modele $version\n" .
               "Dongle ID : $dongle_id\n" .
               "Date d'expiration : $date\n" .
               "=====================\n\n" .
               "Veuillez vérifier et renouveler cette licence dès que possible.\n\n" .
               "Message automatique - Ne pas répondre à cet email\n";
    }

    // ✅ TEMPLATE HTML POUR EMAIL D'ACTIVATION
    private function getActivationEmailTemplate($nom_client, $code)
    {
        $currentDate = date('d/m/Y H:i');

        return "
        <html>
            <head>
                <meta charset='UTF-8'>
                <title>Activation Client</title>
                <style>
                    body {
                        font-family: Arial, sans-serif;
                        margin: 0;
                        padding: 0;
                        background: #f4f6f8;
                    }
                    .container {
                        max-width: 600px;
                        margin: 0 auto;
                        padding: 20px;
                    }
                    .alert {
                        background: #d4edda;
                        border: 1px solid #28a745;
                        padding: 20px;
                        border-radius: 5px;
                        border-left: 4px solid #28a745;
                    }
                    .alert h2 {
                        color: #28a745;
                        margin-top: 0;
                        font-size: 24px;
                    }
                    .info {
                        margin: 15px 0;
                        background: #ffffff;
                        padding: 15px;
                        border-left: 4px solid #28a745;
                        border-radius: 3px;
                    }
                    .code {
                        font-size: 32px;
                        font-weight: bold;
                        color: #28a745;
                        text-align: center;
                        padding: 20px;
                        background: #f0f0f0;
                        border-radius: 5px;
                    }
                    .footer {
                        margin-top: 30px;
                        font-size: 12px;
                        color: #999;
                        text-align: center;
                        border-top: 1px solid #ddd;
                        padding-top: 15px;
                    }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='alert'>
                        <h2>📧 Activation Client</h2>
                        <p style='margin: 10px 0; color: #333;'>Bienvenue <strong>$nom_client</strong></p>

                        <div class='info'>
                            <p style='color: #333;'>Votre code d'activation :</p>
                            <div class='code'>$code</div>
                        </div>

                        <p style='margin-top: 20px; color: #333; line-height: 1.6;'>
                            Utilisez ce code pour activer votre compte.
                        </p>
                    </div>

                    <div class='footer'>
                        <p>Email généré le $currentDate</p>
                        <p>Message automatique - Ne pas répondre à cet email</p>
                    </div>
                </div>
            </body>
        </html>
        ";
    }

    // ✅ TEMPLATE TEXTE POUR EMAIL D'ACTIVATION
    private function getActivationPlainTextTemplate($nom_client, $code)
    {
        return "📧 ACTIVATION CLIENT\n\n" .
               "Bienvenue $nom_client\n\n" .
               "Votre code d'activation : $code\n\n" .
               "Utilisez ce code pour activer votre compte.\n\n" .
               "Message automatique - Ne pas répondre à cet email\n";
    }

    // ✅ Getter pour accéder à la config (optionnel)
    public function getConfig()
    {
        return $this->config;
    }
}
?>