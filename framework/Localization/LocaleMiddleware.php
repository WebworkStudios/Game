<?php
declare(strict_types=1);

namespace Framework\Localization;

use Framework\Core\SessionManagerInterface;

class LocaleMiddleware
{
    private LocalizationService $localization;
    private SessionManagerInterface $session; // Add session property

    public function __construct(LocalizationService $localization, SessionManagerInterface $session)
    {
        $this->localization = $localization;
        $this->session = $session; // Initialize session
    }

    public function handle(): bool
    {
        // Set locale in session if provided via URL
        if (isset($_GET['lang']) && in_array($_GET['lang'], $this->localization->supportedLocales)) {
            $this->session->set('locale', $_GET['lang']); // Use session manager
            $this->localization->currentLocale = $_GET['lang'];
        }

        return true;
    }
}