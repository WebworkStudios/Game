<?php
declare(strict_types=1);

namespace Framework\Mail;

/**
 * Mail Queue Processor - Für Cron-Jobs zur Queue-Verarbeitung
 */
readonly class MailQueueProcessor
{
    public function __construct(
        private MailService $mailService
    )
    {
    }

    /**
     * Verarbeitet Mail-Queue (für Cron-Job)
     */
    public function process(?int $batchSize = null): array
    {
        return $this->mailService->processQueue($batchSize);
    }

    /**
     * Bereinigt alte Queue-Einträge
     */
    public function cleanup(int $daysOld = 7): int
    {
        return $this->mailService->cleanupQueue($daysOld);
    }

    /**
     * Holt Queue-Statistiken
     */
    public function getStats(): array
    {
        return $this->mailService->getQueueStats();
    }
}
