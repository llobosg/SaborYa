<?php
// src/Utils/BrevoMailer.php

namespace Saborya\Utils;

class BrevoMailer {
    private string $apiKey;
    private string $fromEmail;
    private string $fromName;
    
    public function __construct() {
        $this->apiKey = defined('BREVO_API_KEY') ? BREVO_API_KEY : '';
        $this->fromEmail = defined('BREVO_FROM_EMAIL') ? BREVO_FROM_EMAIL : 'no-reply@saborya.app';
        $this->fromName = defined('BREVO_FROM_NAME') ? BREVO_FROM_NAME : 'SaborYa';
        
        if (empty($this->apiKey)) {
            error_log('BrevoMailer: BREVO_API_KEY no configurada');
        }
    }
    
    /**
     * Enviar código de registro con template HTML responsive
     */
    public function sendRegistrationCode(string $toEmail, string $code, string $userName = 'Amigo'): bool {
        $subject = 'Tu código de verificación SaborYa 🔐';
        $htmlContent = $this->renderRegistrationTemplate($code, $userName);
        $textContent = $this->renderRegistrationText($code, $userName);
        
        $payload = [
            'sender' => ['name' => $this->fromName, 'email' => $this->fromEmail],
            'to' => [['email' => $toEmail]],
            'subject' => $subject,
            'htmlContent' => $htmlContent,
            'textContent' => $textContent,
            'headers' => [
                'X-Mailin-custom' => json_encode([
                    'email-attr:tag' => 'registration',
                    'email-attr:category' => 'transactional'
                ])
            ]
        ];
        
        return $this->sendApiRequest($payload);
    }
    
    /**
     * Template HTML responsive con branding SaborYa
     */
    private function renderRegistrationTemplate(string $code, string $userName): string {
        $appUrl = defined('APP_URL') ? APP_URL : 'https://saborya.up.railway.app';
        $primaryColor = '#FF6B35';
        
        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación SaborYa</title>
</head>
<body style="margin:0;padding:0;font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#FFF9F5">
    <table width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:0 auto">
        <tr>
            <td style="background:{$primaryColor};padding:24px;text-align:center">
                <h1 style="color:white;margin:0;font-size:24px">🍽️ SaborYa</h1>
                <p style="color:rgba(255,255,255,0.9);margin:8px 0 0">Alimentos preparados, delivered con amor</p>
            </td>
        </tr>
        <tr>
            <td style="padding:32px 24px">
                <h2 style="color:#2C3E50;margin:0 0 16px">¡Hola, {$userName}! 👋</h2>
                <p style="color:#555;line-height:1.6;margin:0 0 24px">
                    Gracias por registrarte en SaborYa. Para completar tu cuenta y comenzar a pedir, 
                    por favor verifica tu correo con el siguiente código:
                </p>
                <div style="background:#F8F9FA;border:2px dashed {$primaryColor};border-radius:12px;padding:20px;text-align:center;margin:24px 0">
                    <span style="font-size:32px;font-weight:800;letter-spacing:8px;color:#2C3E50">{$code}</span>
                </div>
                <p style="color:#666;font-size:14px;margin:0 0 24px">
                    🔒 Este código expira en <strong>5 minutos</strong><br>
                    ✋ No lo compartas con nadie
                </p>
                <p style="margin:0 0 16px">
                    <a href="{$appUrl}/registro.php?verified=1" 
                       style="display:inline-block;background:{$primaryColor};color:white;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:600">
                        Ir a SaborYa →
                    </a>
                </p>
                <p style="color:#999;font-size:12px;margin:0">
                    ¿No solicitaste este código? Ignora este email.
                </p>
            </td>
        </tr>
        <tr>
            <td style="background:#F8F9FA;padding:16px 24px;text-align:center;font-size:12px;color:#666">
                <p style="margin:0 0 8px">© 2026 SaborYa • Hecho con 🍕 en Chile</p>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }
    
    private function renderRegistrationText(string $code, string $userName): string {
        return "Hola {$userName},\n\nTu código de verificación SaborYa es: {$code}\n\nVálido por 5 minutos.\n\n---\nSaborYa";
    }
    
    private function sendApiRequest(array $payload): bool {
        if (empty($this->apiKey)) {
            // Modo desarrollo: loguear en lugar de enviar
            if (defined('APP_ENV') && APP_ENV === 'development') {
                error_log("Brevo MOCK: " . json_encode($payload));
                return true;
            }
            return false;
        }
        
        $ch = curl_init('https://api.brevo.com/v3/smtp/email');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'api-key: ' . $this->apiKey,
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if (defined('APP_ENV') && APP_ENV === 'development') {
            error_log("Brevo API: HTTP {$httpCode} - " . substr($response ?? '', 0, 200));
        }
        
        return $httpCode === 201;
    }
    
    /**
     * Enviar email transaccional genérico
     */
    public function sendTransactional(string $toEmail, string $subject, string $htmlContent, string $textContent = ''): bool {
        $payload = [
            'sender' => ['name' => $this->fromName, 'email' => $this->fromEmail],
            'to' => [['email' => $toEmail]],
            'subject' => $subject,
            'htmlContent' => $htmlContent,
            'textContent' => $textContent ?: strip_tags($htmlContent),
            'headers' => ['X-Mailin-custom' => json_encode(['email-attr:category' => 'transactional'])]
        ];
        return $this->sendApiRequest($payload);
    }
}