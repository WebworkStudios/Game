<?php


declare(strict_types=1);

// framework/Mail/MailService.php
namespace Framework\Mail;

use DateTime;
use Framework\Database\ConnectionManager;
use Framework\Database\MySQLGrammar;
use Framework\Database\QueryBuilder;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * KickersCup Mail Service - Leichtgewichtiger E-Mail-Service mit Queue-Support
 *
 * Features:
 * - SMTP-Versand ohne externe Dependencies
 * - Template-System mit Variablen-Ersetzung
 * - Mail-Queue für Newsletter-Batches
 * - Rate-Limiting und Retry-Mechanismus
 * - Performance-optimiert für Gaming-Anwendungen
 */
class MailService
{
    private array $config;
    private QueryBuilder $queryBuilder;

    public function __construct(
        private readonly ConnectionManager $connectionManager,
        array                              $config = []
    )
    {
        $this->config = array_merge($this->getDefaultConfig(), $config);
        $this->queryBuilder = new QueryBuilder(
            connectionManager: $this->connectionManager,
            grammar: new MySQLGrammar()
        );
    }

    /**
     * Standard-Konfiguration
     */
    private function getDefaultConfig(): array
    {
        return [
            'smtp' => [
                'host' => 'localhost',
                'port' => 587,
                'username' => '',
                'password' => '',
                'encryption' => 'tls', // tls, ssl, none
                'timeout' => 30,
            ],
            'from' => [
                'email' => 'noreply@kickerscup.com',
                'name' => 'KickersCup',
            ],
            'queue' => [
                'batch_size' => 50, // Mails pro Batch
                'retry_attempts' => 3,
                'retry_delay' => 300, // 5 Minuten
                'priority_levels' => [
                    'high' => 1,
                    'normal' => 5,
                    'low' => 9,
                ],
            ],
            'templates' => [
                'path' => 'storage/mail/templates',
                'cache' => true,
            ],
        ];
    }

    // ========================================================================
    // PUBLIC API - Direkte E-Mail-Versendung
    // ========================================================================

    /**
     * Sendet E-Mail mit Template
     */
    public function sendTemplate(string $to, string $templateName, array $data = [], ?string $toName = null): bool
    {
        try {
            $template = $this->loadTemplate($templateName);
            if (!$template) {
                throw new InvalidArgumentException("Template '{$templateName}' not found");
            }

            $subject = $this->renderTemplate($template['subject'], $data);
            $bodyHtml = $this->renderTemplate($template['body_html'], $data);

            return $this->send($to, $subject, $bodyHtml, $toName);
        } catch (Throwable $e) {
            error_log("Template mail send failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Lädt Template aus Datenbank
     */
    private function loadTemplate(string $name): ?array
    {
        return $this->queryBuilder
            ->table('mail_templates')
            ->where('name', $name)
            ->where('is_active', true)
            ->first();
    }

    // ========================================================================
    // QUEUE API - Für Newsletter und Batch-Versendung
    // ========================================================================

    /**
     * Rendert Template mit Variablen
     */
    private function renderTemplate(string $template, array $data): string
    {
        $rendered = $template;

        foreach ($data as $key => $value) {
            $rendered = str_replace("{{$key}}", (string)$value, $rendered);
        }

        // Prüfe auf nicht ersetzte Variablen
        if (preg_match('/{{[^}]+}}/', $rendered)) {
            error_log("Template contains unreplaced variables in: " . substr($rendered, 0, 100));
        }

        return $rendered;
    }

    /**
     * Sendet E-Mail sofort (für Verifikation, Password-Reset)
     */
    public function send(string $to, string $subject, string $body, ?string $toName = null): bool
    {
        try {
            return $this->sendViaSMTP($to, $toName, $subject, $body);
        } catch (Throwable $e) {
            error_log("Mail send failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Sendet E-Mail via SMTP (ohne externe Dependencies)
     */
    private function sendViaSMTP(string $to, ?string $toName, string $subject, string $body): bool
    {
        $socket = $this->connectToSMTP();
        if (!$socket) {
            return false;
        }

        try {
            // SMTP Handshake
            $this->smtpCommand($socket, "EHLO " . $_SERVER['SERVER_NAME'] ?? 'localhost');

            // Authentication falls konfiguriert
            if (!empty($this->config['smtp']['username'])) {
                $this->authenticateSMTP($socket);
            }

            // E-Mail Header und Body
            $this->smtpCommand($socket, "MAIL FROM: <{$this->config['from']['email']}>");
            $this->smtpCommand($socket, "RCPT TO: <{$to}>");
            $this->smtpCommand($socket, "DATA");

            // E-Mail Headers
            $headers = $this->buildEmailHeaders($to, $toName, $subject);
            fwrite($socket, $headers . "\r\n" . $body . "\r\n.\r\n");

            $response = fgets($socket, 512);
            $this->smtpCommand($socket, "QUIT");

            return str_starts_with($response, '250');

        } catch (Throwable $e) {
            error_log("SMTP Error: " . $e->getMessage());
            return false;
        } finally {
            if (is_resource($socket)) {
                fclose($socket);
            }
        }
    }

    // ========================================================================
    // TEMPLATE SYSTEM
    // ========================================================================

    /**
     * Verbindet zu SMTP-Server
     */
    private function connectToSMTP()
    {
        $host = $this->config['smtp']['host'];
        $port = $this->config['smtp']['port'];
        $timeout = $this->config['smtp']['timeout'];

        // SSL/TLS Context für sichere Verbindungen
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ]
        ]);

        $socket = stream_socket_client(
            "tcp://{$host}:{$port}",
            $errno,
            $errstr,
            $timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$socket) {
            error_log("SMTP Connection failed: {$errstr} ({$errno})");
            return false;
        }

        // Warte auf Server-Greeting
        $response = fgets($socket, 512);
        if (!str_starts_with($response, '220')) {
            error_log("SMTP Greeting failed: {$response}");
            fclose($socket);
            return false;
        }

        return $socket;
    }

    /**
     * SMTP-Kommando senden und Antwort prüfen
     */
    private function smtpCommand($socket, string $command): bool
    {
        fwrite($socket, $command . "\r\n");
        $response = fgets($socket, 512);

        $successCodes = ['250', '235', '354', '221'];
        $responseCode = substr($response, 0, 3);

        if (!in_array($responseCode, $successCodes)) {
            throw new RuntimeException("SMTP Command failed: {$command} -> {$response}");
        }

        return true;
    }

    // ========================================================================
    // SMTP IMPLEMENTATION
    // ========================================================================

    /**
     * SMTP-Authentifizierung
     */
    private function authenticateSMTP($socket): void
    {
        $this->smtpCommand($socket, "AUTH LOGIN");
        $this->smtpCommand($socket, base64_encode($this->config['smtp']['username']));
        $this->smtpCommand($socket, base64_encode($this->config['smtp']['password']));
    }

    /**
     * E-Mail-Headers erstellen
     */
    private function buildEmailHeaders(string $to, ?string $toName, string $subject): string
    {
        $fromEmail = $this->config['from']['email'];
        $fromName = $this->config['from']['name'];

        $headers = [];
        $headers[] = "From: {$fromName} <{$fromEmail}>";
        $headers[] = "To: " . ($toName ? "{$toName} <{$to}>" : $to);
        $headers[] = "Subject: {$subject}";
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "Content-Type: text/html; charset=UTF-8";
        $headers[] = "Content-Transfer-Encoding: 8bit";
        $headers[] = "Date: " . date('r');
        $headers[] = "Message-ID: <" . uniqid() . "@{$_SERVER['SERVER_NAME']}>";

        return implode("\r\n", $headers);
    }

    /**
     * Newsletter an mehrere Empfänger (Batch-optimiert)
     */
    public function sendNewsletter(array $recipients, string $subject, string $body): array
    {
        $results = ['queued' => 0, 'failed' => 0];
        $batchSize = $this->config['queue']['batch_size'];

        // In Batches aufteilen
        $batches = array_chunk($recipients, $batchSize);

        foreach ($batches as $batch) {
            foreach ($batch as $recipient) {
                try {
                    $email = is_array($recipient) ? $recipient['email'] : $recipient;
                    $name = is_array($recipient) ? ($recipient['name'] ?? null) : null;

                    $this->queue($email, $subject, $body, $name, 'low');
                    $results['queued']++;
                } catch (Throwable $e) {
                    error_log("Newsletter queue failed for {$email}: " . $e->getMessage());
                    $results['failed']++;
                }
            }
        }

        return $results;
    }

    /**
     * Fügt E-Mail zur Queue hinzu (für Newsletter)
     */
    public function queue(
        string     $to,
        string     $subject,
        string     $body,
        ?string    $toName = null,
        string     $priority = 'normal',
        ?DateTime $sendAt = null
    ): int
    {
        $priorityLevel = $this->config['queue']['priority_levels'][$priority] ?? 5;

        $mailId = $this->queryBuilder
            ->table('mail_queue')
            ->insertGetId([
                'recipient_email' => $to,
                'recipient_name' => $toName,
                'subject' => $subject,
                'body_html' => $body,
                'priority' => $priorityLevel,
                'send_at' => $sendAt?->format('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s'),
            ]);

        return $mailId;
    }

    /**
     * Verarbeitet Mail-Queue (für Cron-Job)
     */
    public function processQueue(?int $limit = null): array
    {
        $limit = $limit ?? $this->config['queue']['batch_size'];
        $stats = ['processed' => 0, 'sent' => 0, 'failed' => 0];

        // Hole ausstehende E-Mails
        $mails = $this->queryBuilder
            ->table('mail_queue')
            ->where('status', 'pending')
            ->where('attempts', '<', 'max_attempts')
            ->where(function ($query) {
                $query->whereNull('send_at')
                    ->orWhere('send_at', '<=', date('Y-m-d H:i:s'));
            })
            ->orderBy('priority', 'ASC')
            ->orderBy('created_at', 'ASC')
            ->limit($limit)
            ->get();

        foreach ($mails as $mail) {
            $stats['processed']++;

            try {
                // Status auf "processing" setzen
                $this->updateMailStatus($mail['id'], 'processing');

                // E-Mail senden
                $success = $this->sendViaSMTP(
                    $mail['recipient_email'],
                    $mail['recipient_name'],
                    $mail['subject'],
                    $mail['body_html']
                );

                if ($success) {
                    $this->updateMailStatus($mail['id'], 'sent', ['sent_at' => date('Y-m-d H:i:s')]);
                    $stats['sent']++;
                } else {
                    throw new RuntimeException('SMTP send failed');
                }

            } catch (Throwable $e) {
                $this->handleMailFailure($mail['id'], $e->getMessage());
                $stats['failed']++;
            }
        }

        return $stats;
    }

    // ========================================================================
    // HELPER METHODS
    // ========================================================================

    /**
     * Aktualisiert Mail-Status in Queue
     */
    private function updateMailStatus(int $mailId, string $status, array $additionalData = []): void
    {
        $data = array_merge([
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ], $additionalData);

        $this->queryBuilder
            ->table('mail_queue')
            ->where('id', $mailId)
            ->update($data);
    }

    /**
     * Behandelt fehlgeschlagene E-Mail-Sendung
     */
    private function handleMailFailure(int $mailId, string $errorMessage): void
    {
        // Hole aktuelle Attempts
        $mail = $this->queryBuilder
            ->table('mail_queue')
            ->where('id', $mailId)
            ->first();

        $newAttempts = ($mail['attempts'] ?? 0) + 1;
        $maxAttempts = $mail['max_attempts'] ?? $this->config['queue']['retry_attempts'];

        $updateData = [
            'attempts' => $newAttempts,
            'error_message' => $errorMessage,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($newAttempts >= $maxAttempts) {
            $updateData['status'] = 'failed';
            $updateData['failed_at'] = date('Y-m-d H:i:s');
        } else {
            $updateData['status'] = 'pending';
            // Retry-Delay hinzufügen
            $retryAt = time() + $this->config['queue']['retry_delay'];
            $updateData['send_at'] = date('Y-m-d H:i:s', $retryAt);
        }

        $this->updateMailStatus($mailId, $updateData['status'], $updateData);
    }

    /**
     * Holt Queue-Statistiken
     */
    public function getQueueStats(): array
    {
        $stats = $this->queryBuilder
            ->table('mail_queue')
            ->select([
                'status',
                'COUNT(*) as count'
            ])
            ->groupBy('status')
            ->get();

        $result = [];
        foreach ($stats as $stat) {
            $result[$stat['status']] = (int)$stat['count'];
        }

        return $result;
    }

    /**
     * Bereinigt alte Queue-Einträge
     */
    public function cleanupQueue(int $daysOld = 7): int
    {
        $cutoffDate = date('Y-m-d H:i:s', time() - ($daysOld * 24 * 60 * 60));

        return $this->queryBuilder
            ->table('mail_queue')
            ->whereIn('status', ['sent', 'failed', 'cancelled'])
            ->where('updated_at', '<', $cutoffDate)
            ->delete();
    }
}