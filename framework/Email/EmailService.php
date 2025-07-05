<?php
/**
 * Email Service - Complete Fixed Version
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

        // Validate required fields
        if (empty($to) || empty($subject)) {
            error_log("Email error: Missing required fields (to/subject)");
            return false;
        }

        // Validate email address
        if (!$this->validateEmail($to)) {
            error_log("Email error: Invalid recipient email address: {$to}");
            return false;
        }

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

        // Add some basic formatting
        $text = preg_replace('/\s*\n\s*/', "\n", $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

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
            'log' => $this->sendViaLog($to, $toName, $subject, $htmlBody, $textBody),
            default => false
        };
    }

    /**
     * Send email via SMTP - Fixed version with better error handling
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

        // Validate email addresses
        if (!$this->validateEmail($to)) {
            error_log("Invalid recipient email: {$to}");
            return false;
        }

        if (!$this->validateEmail($fromAddress)) {
            error_log("Invalid sender email: {$fromAddress}");
            return false;
        }

        // Create socket connection with proper error handling
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
                'crypto_method' => STREAM_CRYPTO_METHOD_TLS_CLIENT
            ]
        ]);

        $errno = 0;
        $errstr = '';
        $connection = @stream_socket_client(
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

        // Set timeout for the connection
        stream_set_timeout($connection, $timeout);

        try {
            // SMTP conversation with better error handling
            $this->expectSmtpResponse($connection, '220');

            $serverName = $_SERVER['SERVER_NAME'] ?? 'localhost';
            $this->sendSmtpCommand($connection, "EHLO {$serverName}", '250');

            if ($encryption === 'tls') {
                $this->sendSmtpCommand($connection, "STARTTLS", '220');

                if (!stream_socket_enable_crypto($connection, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new \Exception('Failed to enable TLS encryption');
                }

                $this->sendSmtpCommand($connection, "EHLO {$serverName}", '250');
            }

            if ($username && $password) {
                $this->sendSmtpCommand($connection, "AUTH LOGIN", '334');
                $this->sendSmtpCommand($connection, base64_encode($username), '334');
                $this->sendSmtpCommand($connection, base64_encode($password), '235');
            }

            $this->sendSmtpCommand($connection, "MAIL FROM: <{$fromAddress}>", '250');
            $this->sendSmtpCommand($connection, "RCPT TO: <{$to}>", '250');
            $this->sendSmtpCommand($connection, "DATA", '354');

            // Build and send email
            $email = $this->buildEmailMessage($to, $toName, $fromAddress, $fromName, $subject, $htmlBody, $textBody);

            $this->sendSmtpCommand($connection, $email . "\r\n.", '250');
            $this->sendSmtpCommand($connection, "QUIT", '221');

            return true;

        } catch (\Throwable $e) {
            error_log("SMTP error: " . $e->getMessage());
            return false;
        } finally {
            if (is_resource($connection)) {
                fclose($connection);
            }
        }
    }

    /**
     * Expect SMTP response - Fixed version
     */
    private function expectSmtpResponse($connection, string $expectedCode): void
    {
        if (!is_resource($connection)) {
            throw new \Exception("Invalid SMTP connection");
        }

        $response = fgets($connection, 512);

        if ($response === false) {
            throw new \Exception("Failed to read SMTP response");
        }

        $response = trim($response);

        if (!str_starts_with($response, $expectedCode)) {
            throw new \Exception("Unexpected SMTP response: {$response} (expected: {$expectedCode})");
        }
    }

    /**
     * Send SMTP command and expect response - Fixed version
     */
    private function sendSmtpCommand($connection, string $command, string $expectedCode): void
    {
        if (!is_resource($connection)) {
            throw new \Exception("Invalid SMTP connection");
        }

        $bytesWritten = fwrite($connection, $command . "\r\n");

        if ($bytesWritten === false || $bytesWritten === 0) {
            throw new \Exception("Failed to send SMTP command: {$command}");
        }

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
        $email[] = "X-Mailer: Football Manager v1.0";
        $email[] = "MIME-Version: 1.0";

        if ($htmlBody && $textBody) {
            $boundary = uniqid('boundary_');
            $email[] = "Content-Type: multipart/alternative; boundary=\"{$boundary}\"";
            $email[] = "";

            $email[] = "--{$boundary}";
            $email[] = "Content-Type: text/plain; charset=UTF-8";
            $email[] = "Content-Transfer-Encoding: 8bit";
            $email[] = "";
            $email[] = $textBody;
            $email[] = "";

            $email[] = "--{$boundary}";
            $email[] = "Content-Type: text/html; charset=UTF-8";
            $email[] = "Content-Transfer-Encoding: 8bit";
            $email[] = "";
            $email[] = $htmlBody;
            $email[] = "";

            $email[] = "--{$boundary}--";
        } else {
            $body = $htmlBody ?: $textBody;
            $email[] = $htmlBody ?
                "Content-Type: text/html; charset=UTF-8" :
                "Content-Type: text/plain; charset=UTF-8";
            $email[] = "Content-Transfer-Encoding: 8bit";
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
        $headers[] = "X-Mailer: Football Manager v1.0";
        $headers[] = "MIME-Version: 1.0";

        if ($htmlBody && $textBody) {
            $boundary = uniqid('boundary_');
            $headers[] = "Content-Type: multipart/alternative; boundary=\"{$boundary}\"";

            $body = "--{$boundary}\r\n";
            $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
            $body .= $textBody . "\r\n\r\n";

            $body .= "--{$boundary}\r\n";
            $body .= "Content-Type: text/html; charset=UTF-8\r\n";
            $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
            $body .= $htmlBody . "\r\n\r\n";

            $body .= "--{$boundary}--";
        } else {
            $body = $htmlBody ?: $textBody;
            $headers[] = $htmlBody ?
                "Content-Type: text/html; charset=UTF-8" :
                "Content-Type: text/plain; charset=UTF-8";
            $headers[] = "Content-Transfer-Encoding: 8bit";
        }

        try {
            return mail($to, $subject, $body, implode("\r\n", $headers));
        } catch (\Throwable $e) {
            error_log("Mail function error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send email via log (for development/testing)
     */
    private function sendViaLog(string $to, string $toName, string $subject, ?string $htmlBody, ?string $textBody): bool
    {
        $logPath = $this->config['log_path'] ?? __DIR__ . '/../../logs/emails.log';
        $logDir = dirname($logPath);

        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'to' => $to,
            'to_name' => $toName,
            'subject' => $subject,
            'html_body' => $htmlBody,
            'text_body' => $textBody
        ];

        $logLine = date('Y-m-d H:i:s') . " [EMAIL] To: {$to} | Subject: {$subject}\n";
        $logLine .= "HTML: " . ($htmlBody ? "Yes (" . strlen($htmlBody) . " chars)" : "No") . "\n";
        $logLine .= "Text: " . ($textBody ? "Yes (" . strlen($textBody) . " chars)" : "No") . "\n";
        $logLine .= str_repeat('-', 80) . "\n";

        try {
            file_put_contents($logPath, $logLine, FILE_APPEND | LOCK_EX);
            return true;
        } catch (\Throwable $e) {
            error_log("Email log error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Validate email address - Enhanced version
     */
    public function validateEmail(string $email): bool
    {
        // Basic validation
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        // Additional checks
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return false;
        }

        [$localPart, $domain] = $parts;

        // Check local part length
        if (strlen($localPart) > 64) {
            return false;
        }

        // Check domain part
        if (strlen($domain) > 253) {
            return false;
        }

        // Check for valid domain format
        if (!preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?)*$/', $domain)) {
            return false;
        }

        // Check for common disposable email domains (optional)
        $disposableDomains = [
            '10minutemail.com', 'tempmail.org', 'guerrillamail.com',
            'mailinator.com', 'temp-mail.org', 'throwaway.email'
        ];

        if (in_array(strtolower($domain), $disposableDomains)) {
            return false;
        }

        return true;
    }

    /**
     * Queue email for background processing
     */
    public function queue(array $emailData, int $priority = 5, ?\DateTime $scheduledAt = null): bool
    {
        if (!isset($this->config['queue']['enabled']) || !$this->config['queue']['enabled']) {
            // If queue is disabled, send immediately
            return $this->send($emailData);
        }

        // This would integrate with a proper queue system
        // For now, we'll simulate queuing by logging
        $queueData = [
            'email_data' => $emailData,
            'priority' => $priority,
            'scheduled_at' => $scheduledAt ? $scheduledAt->format('Y-m-d H:i:s') : date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s')
        ];

        $queueFile = $this->config['queue']['file'] ?? __DIR__ . '/../../storage/email_queue.json';
        $queueDir = dirname($queueFile);

        if (!is_dir($queueDir)) {
            mkdir($queueDir, 0755, true);
        }

        try {
            $existingQueue = [];
            if (file_exists($queueFile)) {
                $existingQueue = json_decode(file_get_contents($queueFile), true) ?: [];
            }

            $existingQueue[] = $queueData;

            file_put_contents($queueFile, json_encode($existingQueue, JSON_PRETTY_PRINT), LOCK_EX);
            return true;
        } catch (\Throwable $e) {
            error_log("Email queue error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Process email queue (would be called by a background worker)
     */
    public function processQueue(int $batchSize = 10): array
    {
        $queueFile = $this->config['queue']['file'] ?? __DIR__ . '/../../storage/email_queue.json';

        if (!file_exists($queueFile)) {
            return ['processed' => 0, 'failed' => 0];
        }

        try {
            $queue = json_decode(file_get_contents($queueFile), true) ?: [];
            $processed = 0;
            $failed = 0;
            $remaining = [];

            foreach ($queue as $index => $queueItem) {
                if ($processed >= $batchSize) {
                    $remaining[] = $queueItem;
                    continue;
                }

                // Check if email should be sent now
                $scheduledAt = new \DateTime($queueItem['scheduled_at']);
                if ($scheduledAt > new \DateTime()) {
                    $remaining[] = $queueItem;
                    continue;
                }

                // Try to send email
                if ($this->send($queueItem['email_data'])) {
                    $processed++;
                } else {
                    $failed++;
                    // Could implement retry logic here
                }
            }

            // Update queue file with remaining items
            file_put_contents($queueFile, json_encode($remaining, JSON_PRETTY_PRINT), LOCK_EX);

            return ['processed' => $processed, 'failed' => $failed, 'remaining' => count($remaining)];

        } catch (\Throwable $e) {
            error_log("Email queue processing error: " . $e->getMessage());
            return ['processed' => 0, 'failed' => 0, 'error' => $e->getMessage()];
        }
    }

    /**
     * Test email configuration
     */
    public function testConfiguration(): array
    {
        $results = [
            'driver' => $this->config['driver'] ?? 'not_set',
            'from_address' => $this->config['from']['address'] ?? 'not_set',
            'from_name' => $this->config['from']['name'] ?? 'not_set',
            'template_engine' => $this->templateEngine ? 'available' : 'not_available',
            'smtp_config' => [],
            'errors' => []
        ];

        // Validate from address
        if (!$this->validateEmail($results['from_address'])) {
            $results['errors'][] = 'Invalid from email address';
        }

        // Test SMTP configuration if using SMTP
        if ($results['driver'] === 'smtp') {
            $smtp = $this->config['smtp'] ?? [];
            $results['smtp_config'] = [
                'host' => $smtp['host'] ?? 'not_set',
                'port' => $smtp['port'] ?? 'not_set',
                'username' => $smtp['username'] ? 'set' : 'not_set',
                'password' => $smtp['password'] ? 'set' : 'not_set',
                'encryption' => $smtp['encryption'] ?? 'not_set'
            ];

            // Test SMTP connection
            try {
                $host = $smtp['host'] ?? 'localhost';
                $port = $smtp['port'] ?? 587;
                $timeout = $smtp['timeout'] ?? 5;

                $connection = @stream_socket_client(
                    "{$host}:{$port}",
                    $errno,
                    $errstr,
                    $timeout
                );

                if ($connection) {
                    $results['smtp_connection'] = 'success';
                    fclose($connection);
                } else {
                    $results['smtp_connection'] = 'failed';
                    $results['errors'][] = "SMTP connection failed: {$errstr} ({$errno})";
                }
            } catch (\Throwable $e) {
                $results['smtp_connection'] = 'error';
                $results['errors'][] = "SMTP test error: " . $e->getMessage();
            }
        }

        return $results;
    }

    /**
     * Get email statistics
     */
    public function getStats(): array
    {
        $stats = [
            'driver' => $this->config['driver'] ?? 'unknown',
            'queue_enabled' => $this->config['queue']['enabled'] ?? false,
            'emails_in_queue' => 0
        ];

        // Count queued emails if queue is enabled
        if ($stats['queue_enabled']) {
            $queueFile = $this->config['queue']['file'] ?? __DIR__ . '/../../storage/email_queue.json';
            if (file_exists($queueFile)) {
                try {
                    $queue = json_decode(file_get_contents($queueFile), true) ?: [];
                    $stats['emails_in_queue'] = count($queue);
                } catch (\Throwable $e) {
                    $stats['queue_error'] = $e->getMessage();
                }
            }
        }

        return $stats;
    }
}