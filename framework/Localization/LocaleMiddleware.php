<?php
declare(strict_types=1);

namespace Framework\Localization;

class LocaleMiddleware
{
    private LocalizationService $localization;

    public function __construct(LocalizationService $localization)
    {
        $this->localization = $localization;
    }

    public function handle(): bool
    {
        // Set locale in session if provided via URL
        if (isset($_GET['lang']) && in_array($_GET['lang'], $this->localization->supportedLocales)) {
            if (session_status() === PHP_SESSION_ACTIVE) {
                $_SESSION['locale'] = $_GET['lang'];
            }
            $this->localization->currentLocale = $_GET['lang'];
        }

        return true;
    }
}