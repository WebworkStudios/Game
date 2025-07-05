<?php

/**
 * Email Service
 * Email sending service with template support
 *
 * File: framework/Email/EmailService.php
 * Directory: /framework/Email/
 */

declare(strict_types=1);

namespace Framework\Email;

use Framework\Core\TemplateEngine;

class EmailService
{
    private array $config;
    private TemplateEngine $templateEngine;

    public function __construct(array $config, TemplateEngine $templateEngine)
    {
        $this->config = $config;
        $this->templateEngine = $templateEngine;
    }

    /**
     * Send email
     */
    public function send(array $emailData): bool
    {
        $to = $emailData['to'];
        $toName = $emailData['to_name'] ?? '';
        $subject = $emailData['subject'];
        $template = $emailData['template'] ?? null;
        $data = $emailData['data'] ?? [];
        $htmlBody = $emailData['html_body'] ?? null;
        $textBody = $emailData['text_body'] ?? null;

        // Generate body from template if provided
        if ($template) {
            try {
                $htmlBody = $this->templateEngine->getContent("email/{$template}", $data);
                $textBody = $this->generateTextFromHtml($htmlBody);
            } catch (\Exception $e) {
                error_log("Email template error: " . $e->getMessage());
                return false;
            }
        }

        if (!$htmlBody && !$textBody) {
            error_log("Email error: No body content provided");
            return false;
        }

        return $this->sendEmail($to, $toName, $subject, $htmlBody, $textBody);
    }

    /**
     * Generate text version from HTML
     */
    private function generateTextFromHtml(string $html): string
    {
        // Remove HTML tags and decode entities
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');

        // Clean up whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);

        return $text;
    }

    /**
     * Send email using configured method
     */
    private function sendEmail(string $to, string $toName, string $subject, ?string $htmlBody, ?string $textBody): bool
    {
        $driver = $this->config['driver'] ?? 'smtp';

        return match ($driver) {
            'smtp' => $this->sendViaSmtp($to, $toName, $subject, $htmlBody, $textBody),
            'mail' => $this->sendViaMail($to, $toName, $subject, $htmlBody, $textBody),
            default => false
        };
    }

    /**
     * Send email via SMTP
     */
    private function sendViaSmtp(string $to, string $toName, string $subject, ?string $htmlBody, ?string $textBody): bool
    {
        $smtp = $this->config['smtp'] ?? [];

        $host = $smtp['host'] ?? 'localhost';
        $port = $smtp['port'] ?? 587;
        $username = $smtp['username'] ?? '';
        $password = $smtp['password'] ?? '';
        $encryption = $smtp['encryption'] ?? 'tls';
        $timeout = $smtp['timeout'] ?? 30;

        $fromAddress = $this->config['from']['address'] ?? 'noreply@localhost';
        $fromName = $this->config['from']['name'] ?? 'Football Manager';

        // Create socket connection
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ]);

        $connection = stream_socket_client(
            "{$host}:{$port}",
            $errno,
            $errstr,
            $timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$connection) {
            error_log("SMTP connection failed: {$errstr} ({$errno})");
            return false;
        }

        try {
            // SMTP conversation
            $this->expectSmtpResponse($connection, '220');

            $this->sendSmtpCommand($connection, "EHLO {$_SERVER['SERVER_NAME']}", '250');

            if ($encryption === 'tls') {
                $this->sendSmtpCommand($connection, "STARTTLS", '220');
                stream_socket_enable_crypto($connection, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                $this->sendSmtpCommand($connection, "EHLO {$_SERVER['SERVER_NAME']}", '250');
            }

            if ($username && $password) {
                $this->sendSmtpCommand($connection, "AUTH LOGIN", '334');
                $this->sendSmtpCommand($connection, base64_encode($username), '334');
                $this->sendSmtpCommand($connection, base64_encode($password), '235');
            }

            $this->sendSmtpCommand($connection, "MAIL FROM: <{$fromAddress}>", '250');
            $this->sendSmtpCommand($connection, "RCPT TO: <{$to}>", '250');
            $this->sendSmtpCommand($connection, "DATA", '354');

            // Build email
            $email = $this->buildEmailMessage($to, $toName, $fromAddress, $fromName, $subject, $htmlBody, $textBody);

            $this->sendSmtpCommand($connection, $email . "\r\n.", '250');
            $this->sendSmtpCommand($connection, "QUIT", '221');

            fclose($connection);
            return true;

        } catch (\Exception $e) {
            error_log("SMTP error: " . $e->getMessage());
            fclose($connection);
            return false;
        }
    }

    /**
     * Expect SMTP response
     */
    private function expectSmtpResponse($connection, string $expectedCode): void
    {
        $response = fgets($connection, 512);

        if (!$response || !str_starts_with($response, $expectedCode)) {
            throw new \Exception("Unexpected SMTP response: {$response}");
        }
    }

    /**
     * Send SMTP command and expect response
     */
    private function sendSmtpCommand($connection, string $command, string $expectedCode): void
    {
        fwrite($connection, $command . "\r\n");
        $this->expectSmtpResponse($connection, $expectedCode);
    }

    /**
     * Build email message
     */
    private function buildEmailMessage(string $to, string $toName, string $fromAddress, string $fromName, string $subject, ?string $htmlBody, ?string $textBody): string
    {
        $email = [];
        $email[] = "From: {$fromName} <{$fromAddress}>";
        $email[] = "To: {$toName} <{$to}>";
        $email[] = "Subject: {$subject}";
        $email[] = "Date: " . date('r');
        $email[] = "Message-ID: <" . uniqid() . "@{$_SERVER['SERVER_NAME']}>";
        $email[] = "X-Mailer: Football Manager";
        $email[] = "MIME-Version: 1.0";

        if ($htmlBody && $textBody) {
            $boundary = uniqid('boundary_');
            $email[] = "Content-Type: multipart/alternative; boundary=\"{$boundary}\"";
            $email[] = "";

            $email[] = "--{$boundary}";
            $email[] = "Content-Type: text/plain; charset=UTF-8";
            $email[] = "Content-Transfer-Encoding: 7bit";
            $email[] = "";
            $email[] = $textBody;
            $email[] = "";

            $email[] = "--{$boundary}";
            $email[] = "Content-Type: text/html; charset=UTF-8";
            $email[] = "Content-Transfer-Encoding: 7bit";
            $email[] = "";
            $email[] = $htmlBody;
            $email[] = "";

            $email[] = "--{$boundary}--";
        } else {
            $body = $htmlBody ?: $textBody;
            $email[] = $htmlBody ?
                "Content-Type: text/html; charset=UTF-8" :
                "Content-Type: text/plain; charset=UTF-8";
            $email[] = "Content-Transfer-Encoding: 7bit";
            $email[] = "";
            $email[] = $body;
        }

        return implode("\r\n", $email);
    }

    /**
     * Send email via PHP mail()
     */
    private function sendViaMail(string $to, string $toName, string $subject, ?string $htmlBody, ?string $textBody): bool
    {
        $fromAddress = $this->config['from']['address'] ?? 'noreply@localhost';
        $fromName = $this->config['from']['name'] ?? 'Football Manager';

        $headers = [];
        $headers[] = "From: {$fromName} <{$fromAddress}>";
        $headers[] = "Reply-To: {$fromAddress}";
        $headers[] = "X-Mailer: Football Manager";
        $headers[] = "MIME-Version: 1.0";

        if ($htmlBody && $textBody) {
            $boundary = uniqid('boundary_');
            $headers[] = "Content-Type: multipart/alternative; boundary=\"{$boundary}\"";

            $body = "--{$boundary}\r\n";
            $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
            $body .= $textBody . "\r\n\r\n";

            $body .= "--{$boundary}\r\n";
            $body .= "Content-Type: text/html; charset=UTF-8\r\n";
            $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
            $body .= $htmlBody . "\r\n\r\n";

            $body .= "--{$boundary}--";
        } else {
            $body = $htmlBody ?: $textBody;
            $headers[] = $htmlBody ?
                "Content-Type: text/html; charset=UTF-8" :
                "Content-Type: text/plain; charset=UTF-8";
        }

        return mail($to, $subject, $body, implode("\r\n", $headers));
    }

    /**
     * Validate email address
     */
    public function validateEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}