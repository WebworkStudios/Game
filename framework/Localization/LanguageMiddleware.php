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
        // Detect and set locale for this request
        $locale = $this->detector->detectLocale($request);
        $this->translator->setLocale($locale);

        return $next($request);
    }
}