<?php

declare(strict_types=1);

namespace Framework\Localization;

use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Routing\MiddlewareInterface;

readonly class LanguageMiddleware implements MiddlewareInterface
{
    public function __construct(
        private LanguageDetector $detector,
        private Translator       $translator
    )
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        // 1. Detect locale (respects session priority)
        $detectedLocale = $this->detector->detectLocale($request);

        // 2. Ensure translator is synced with detected locale
        $currentTranslatorLocale = $this->translator->getLocale();

        if ($detectedLocale !== $currentTranslatorLocale) {
            $this->translator->setLocale($detectedLocale);
            $this->translator->clearCache(); // Clear cache when locale changes
        }

        return $next($request);
    }
}