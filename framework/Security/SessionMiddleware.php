<?php


declare(strict_types=1);

namespace Framework\Security;

use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Routing\MiddlewareInterface;

/**
 * Session Middleware - Startet automatisch Sessions für alle Requests
 */
class SessionMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly Session $session
    )
    {
    }

    /**
     * Verarbeitet Request und startet Session
     */
    public function handle(Request $request, callable $next): Response
    {
        // Session starten
        $this->session->start();

        // Session-ID regenerieren bei unsicheren Requests (Security)
        if ($this->shouldRegenerateId($request)) {
            $this->session->regenerate();
        }

        // Request weiterleiten
        $response = $next($request);

        // Flash-Messages für nächsten Request vorbereiten
        $this->handleFlashMessages();

        return $response;
    }

    /**
     * Prüft ob Session-ID regeneriert werden soll
     */
    private function shouldRegenerateId(Request $request): bool
    {
        // Bei Login/Logout würde man hier spezifische Logik implementieren
        // Für jetzt: nicht bei GET-Requests regenerieren (Performance)
        return !$request->isGet();
    }

    /**
     * Verwaltet Flash-Messages (werden nach einem Request automatisch gelöscht)
     */
    private function handleFlashMessages(): void
    {
        // Flash-Messages bleiben automatisch nur für einen Request bestehen
        // Die Session-Klasse entfernt sie beim nächsten getFlash() Aufruf
    }
}