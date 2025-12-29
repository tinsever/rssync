<?php

declare(strict_types=1);

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class EmailService
{
    private string $host;
    private int $port;
    private string $username;
    private string $password;
    private string $fromAddress;
    private string $fromName;
    private string $appUrl;
    
    public function __construct()
    {
        $this->host = $_ENV['MAIL_HOST'] ?? 'localhost';
        $this->port = (int) ($_ENV['MAIL_PORT'] ?? 587);
        $this->username = $_ENV['MAIL_USERNAME'] ?? '';
        $this->password = $_ENV['MAIL_PASSWORD'] ?? '';
        $this->fromAddress = $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@rssync.local';
        $this->fromName = $_ENV['MAIL_FROM_NAME'] ?? 'RSSync';
        $this->appUrl = $_ENV['APP_URL'] ?? 'http://localhost:8080';
    }
    
    public function sendVerificationEmail(string $to, string $token): bool
    {
        $verifyUrl = $this->appUrl . '/verify/' . $token;
        
        $subject = 'E-Mail-Adresse bestätigen - RSSync';
        $body = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .button { display: inline-block; padding: 12px 24px; background-color: #1976d2; color: white; text-decoration: none; border-radius: 4px; }
        .footer { margin-top: 30px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Willkommen bei RSSync!</h1>
        <p>Vielen Dank für Ihre Registrierung. Bitte bestätigen Sie Ihre E-Mail-Adresse, indem Sie auf den folgenden Button klicken:</p>
        <p><a href="{$verifyUrl}" class="button">E-Mail bestätigen</a></p>
        <p>Oder kopieren Sie diesen Link in Ihren Browser:</p>
        <p><a href="{$verifyUrl}">{$verifyUrl}</a></p>
        <div class="footer">
            <p>Falls Sie sich nicht bei RSSync registriert haben, können Sie diese E-Mail ignorieren.</p>
        </div>
    </div>
</body>
</html>
HTML;
        
        return $this->send($to, $subject, $body);
    }
    
    public function sendPasswordResetEmail(string $to, string $token): bool
    {
        $resetUrl = $this->appUrl . '/reset-password/' . $token;
        
        $subject = 'Passwort zurücksetzen - RSSync';
        $body = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .button { display: inline-block; padding: 12px 24px; background-color: #1976d2; color: white; text-decoration: none; border-radius: 4px; }
        .footer { margin-top: 30px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Passwort zurücksetzen</h1>
        <p>Sie haben angefordert, Ihr Passwort zurückzusetzen. Klicken Sie auf den folgenden Button, um ein neues Passwort festzulegen:</p>
        <p><a href="{$resetUrl}" class="button">Passwort zurücksetzen</a></p>
        <p>Oder kopieren Sie diesen Link in Ihren Browser:</p>
        <p><a href="{$resetUrl}">{$resetUrl}</a></p>
        <p><strong>Dieser Link ist 2 Stunden gültig.</strong></p>
        <div class="footer">
            <p>Falls Sie kein neues Passwort angefordert haben, können Sie diese E-Mail ignorieren.</p>
        </div>
    </div>
</body>
</html>
HTML;
        
        return $this->send($to, $subject, $body);
    }
    
    private function send(string $to, string $subject, string $htmlBody): bool
    {
        $mail = new PHPMailer(true);
        
        try {
            // Server settings
            if (!empty($this->username) && !empty($this->host)) {
                $mail->isSMTP();
                $mail->Host = $this->host;
                $mail->SMTPAuth = true;
                $mail->Username = $this->username;
                $mail->Password = $this->password;
                $mail->Port = $this->port;
                
                // Determine encryption based on port
                if ($this->port === 465) {
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                } elseif ($this->port === 587) {
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                } else {
                    // Port 25 or others - no encryption
                    $mail->SMTPSecure = '';
                    $mail->SMTPAutoTLS = false;
                }
                
                // Debug mode in development
                if (($_ENV['APP_DEBUG'] ?? 'false') === 'true') {
                    $mail->SMTPDebug = 0; // Set to 2 for verbose debug output
                }
            } else {
                // Use PHP's mail() function as fallback
                $mail->isMail();
            }
            
            // Recipients
            $mail->setFrom($this->fromAddress, $this->fromName);
            $mail->addAddress($to);
            
            // Content
            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody));
            
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log('Email send error: ' . $mail->ErrorInfo);
            return false;
        }
    }
}

