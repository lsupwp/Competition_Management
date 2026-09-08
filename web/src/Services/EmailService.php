<?php

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailService
{
    private PHPMailer $mail;

    public function __construct()
    {
        $this->mail = new PHPMailer(true);
        $this->configure();
    }

    private function configure(): void
    {
        $this->mail->isSMTP();
        $this->mail->Host = $_ENV['SMTP_HOST'];
        $this->mail->SMTPAuth = true;
        $this->mail->Username = $_ENV['SMTP_USERNAME'];
        $this->mail->Password = $_ENV['SMTP_PASSWORD'];
        $this->mail->SMTPSecure = $_ENV['SMTP_ENCRYPTION'] ?? PHPMailer::ENCRYPTION_STARTTLS;
        $this->mail->Port = (int)($_ENV['SMTP_PORT'] ?? 587);
        $this->mail->CharSet = 'UTF-8';

        $this->mail->setFrom($_ENV['MAIL_FROM_ADDRESS'], $_ENV['MAIL_FROM_NAME']);
    }

    public function sendVerificationEmail(string $toEmail, string $toName, string $token): bool
    {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($toEmail, $toName);

            $verificationUrl = rtrim($_ENV['APP_URL'], '/') . '/auth/verify?token=' . urlencode($token);

            $this->mail->isHTML(true);
            $this->mail->Subject = 'Verify Your Email - Team Competition';
            $this->mail->Body = $this->getVerificationEmailTemplate($toName, $verificationUrl);
            $this->mail->AltBody = "Hello $toName,\n\nPlease click the link below to verify your email:\n$verificationUrl\n\nThis link will expire in 24 hours.\n\nThank you";

            return $this->mail->send();
        } catch (Exception $e) {
            error_log("Email send failed: " . $this->mail->ErrorInfo);
            return false;
        }
    }

    private function getVerificationEmailTemplate(string $name, string $url): string
    {
        return "
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Verify Email</title>
        </head>
        <body style='font-family: sans-serif; background-color: #f4f4f4; padding: 40px 0; margin: 0;'>
            <div style='max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden;'>
                <div style='background-color: #0ea5e9; padding: 30px; text-align: center;'>
                    <h1 style='color: #ffffff; margin: 0; font-size: 24px;'>Team Competition</h1>
                </div>
                <div style='padding: 40px 30px;'>
                    <h2 style='color: #333333; margin-top: 0;'>Verify Your Email</h2>
                    <p style='color: #555555; line-height: 1.6;'>Hello $name,</p>
                    <p style='color: #555555; line-height: 1.6;'>Thank you for registering with us. Please click the button below to verify your email address.</p>
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='$url' style='background-color: #0ea5e9; color: #ffffff; padding: 12px 30px; text-decoration: none; border-radius: 6px; font-weight: bold; display: inline-block;'>Verify Email</a>
                    </div>
                    <p style='color: #888888; font-size: 14px; line-height: 1.6;'>Or copy this link and paste it in your browser:</p>
                    <p style='color: #0ea5e9; font-size: 14px; word-break: break-all;'>$url</p>
                    <p style='color: #888888; font-size: 14px; line-height: 1.6;'>This link will expire in 24 hours.</p>
                </div>
                <div style='background-color: #f9f9f9; padding: 20px 30px; text-align: center;'>
                    <p style='color: #888888; font-size: 12px; margin: 0;'>Team Competition Management System</p>
                </div>
            </div>
        </body>
        </html>";
    }
}
