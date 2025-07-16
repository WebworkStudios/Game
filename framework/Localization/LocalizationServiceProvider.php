<?php

declare(strict_types=1);

namespace Framework\Localization;

use Framework\Core\AbstractServiceProvider;
use Framework\Security\Session;

/**
 * Localization Service Provider - Registriert Mehrsprachigkeits-Services
 *
 * KORRIGIERTE VERSION: Verwendet Locale-Codes statt Display-Namen für Verzeichnisse
 */
class LocalizationServiceProvider extends AbstractServiceProvider
{
    private const string CONFIG_PATH = 'app/Config/localization.php';
    private const array REQUIRED_KEYS = ['default_locale', 'fallback_locale', 'supported_locales', 'languages_path'];

    /**
     * Validiert Localization-spezifische Abhängigkeiten
     */
    protected function validateDependencies(): void
    {
        // Prüfe ob mbstring extension verfügbar ist
        if (!extension_loaded('mbstring')) {
            throw new \RuntimeException('mbstring extension is required for localization functionality');
        }

        // Language-Verzeichnisse erstellen
        $config = $this->getConfig(self::CONFIG_PATH, fn() => $this->getDefaultLocalizationConfig(), self::REQUIRED_KEYS);
        $this->ensureLanguageDirectories($config);
    }

    /**
     * Registriert alle Localization Services
     */
    protected function registerServices(): void
    {
        $this->registerTranslator();
        $this->registerLanguageDetector();
        $this->registerMiddlewares();
    }

    /**
     * Registriert Translator als Singleton
     */
    private function registerTranslator(): void
    {
        $this->singleton(Translator::class, function () {
            $config = $this->getConfig(self::CONFIG_PATH, fn() => $this->getDefaultLocalizationConfig(), self::REQUIRED_KEYS);

            $languagesPath = $this->basePath($config['languages_path']);
            $this->ensureLanguageFiles($languagesPath, array_keys($config['supported_locales'])); // FIX: Verwende Locale-Codes

            $translator = new Translator($languagesPath);
            $translator->setLocale($config['default_locale']);
            $translator->setFallbackLocale($config['fallback_locale']);

            return $translator;
        });
    }

    /**
     * Registriert Language Detector als Singleton
     */
    private function registerLanguageDetector(): void
    {
        $this->singleton(LanguageDetector::class, function () {
            $config = $this->getLocalizationConfig();

            return new LanguageDetector(
                session: $this->get(Session::class),
                supportedLocales: array_keys($config['supported_locales']),
                defaultLocale: $config['default_locale']
            );
        });
    }

    /**
     * Registriert Localization Middlewares
     */
    private function registerMiddlewares(): void
    {
        $this->transient(LanguageMiddleware::class, function () {
            return new LanguageMiddleware(
                detector: $this->get(LanguageDetector::class),
                translator: $this->get(Translator::class)
            );
        });
    }

    /**
     * Bindet Localization-Interfaces
     */
    protected function bindInterfaces(): void
    {
        // Hier können Localization-Interfaces gebunden werden
        // $this->bind(TranslatorInterface::class, Translator::class);
    }

    /**
     * Holt Localization-Konfiguration
     */
    private function getLocalizationConfig(): array
    {
        return $this->getConfig(
            configPath: self::CONFIG_PATH,
            defaultProvider: fn() => $this->getDefaultLocalizationConfig(),
            requiredKeys: self::REQUIRED_KEYS
        );
    }

    /**
     * Erstellt Standard-Sprachdateien
     *
     * FIX: Verwendet jetzt Locale-Codes statt Display-Namen
     */
    private function ensureLanguageFiles(string $languagesPath, array $supportedLocales): void
    {
        $defaultFiles = ['auth.php', 'validation.php', 'game.php'];

        foreach ($supportedLocales as $locale) { // FIX: $locale ist jetzt der Code, nicht der Name
            $localePath = $languagesPath . '/' . $locale;

            // Stelle sicher, dass das Verzeichnis existiert
            if (!is_dir($localePath)) {
                if (!mkdir($localePath, 0755, true)) {
                    throw new \RuntimeException("Cannot create locale directory: {$localePath}");
                }
            }

            foreach ($defaultFiles as $file) {
                $filePath = $localePath . '/' . $file;

                if (!file_exists($filePath)) {
                    $content = $this->getDefaultLanguageContent($locale, pathinfo($file, PATHINFO_FILENAME));

                    // Stelle sicher, dass file_put_contents erfolgreich ist
                    if (file_put_contents($filePath, $content) === false) {
                        throw new \RuntimeException("Cannot write language file: {$filePath}");
                    }
                }
            }
        }
    }

    /**
     * Generiert Standard-Sprachdatei-Inhalt
     */
    private function getDefaultLanguageContent(string $locale, string $group): string
    {
        $content = match ($group) {
            'auth' => [
                'login' => 'Login',
                'logout' => 'Logout',
                'register' => 'Register',
                'password' => 'Password',
                'email' => 'Email',
                'remember_me' => 'Remember Me',
                'failed' => 'These credentials do not match our records.',
                'password_reset' => 'Password Reset',
            ],
            'validation' => [
                'required' => 'The :field field is required.',
                'email' => 'The :field must be a valid email address.',
                'unique' => 'The :field has already been taken.',
                'min' => 'The :field must be at least :min characters.',
                'max' => 'The :field may not be greater than :max characters.',
            ],
            'game' => [
                'goals' => 'Goals',
                'assists' => 'Assists',
                'yellow_cards' => 'Yellow Cards',
                'red_cards' => 'Red Cards',
                'minutes_played' => 'Minutes Played',
                'team' => 'Team',
                'player' => 'Player',
                'match' => 'Match',
                'season' => 'Season',
            ],
            default => ['placeholder' => 'Placeholder text'],
        };

        $exportedContent = var_export($content, true);

        return <<<PHP
<?php

declare(strict_types=1);

// Auto-generated language file for locale: {$locale}
// Group: {$group}

return {$exportedContent};
PHP;
    }

    /**
     * Erstellt Language-Verzeichnisse falls nötig
     *
     * FIX: Verwendet jetzt Locale-Codes statt Display-Namen
     */
    private function ensureLanguageDirectories(array $config): void
    {
        $languagesPath = $this->basePath($config['languages_path']);

        if (!is_dir($languagesPath) && !mkdir($languagesPath, 0755, true)) {
            throw new \RuntimeException("Cannot create languages directory: {$languagesPath}");
        }

        // FIX: Verwende array_keys() um die Locale-Codes zu bekommen
        foreach (array_keys($config['supported_locales']) as $locale) {
            $localePath = $languagesPath . '/' . $locale;
            if (!is_dir($localePath) && !mkdir($localePath, 0755, true)) {
                throw new \RuntimeException("Cannot create locale directory: {$localePath}");
            }
        }
    }

    /**
     * Default Localization Konfiguration
     */
    private function getDefaultLocalizationConfig(): array
    {
        return [
            'default_locale' => 'de', // FIX: Deutsch als Standard
            'fallback_locale' => 'de',
            'supported_locales' => [
                'de' => 'Deutsch',
                'en' => 'English',
                'fr' => 'Français',
                'es' => 'Español'
            ],
            'languages_path' => 'app/Languages',
            'auto_detect' => true,
            'detection_methods' => [
                'session',
                'header',
                'subdomain',
                'query_parameter',
            ],
            'session_key' => 'locale',
            'query_parameter' => 'lang',
            'subdomain_mapping' => [
                'en' => 'www',
                'de' => 'de',
                'fr' => 'fr',
                'es' => 'es',
            ],
            'rtl_locales' => ['ar', 'he', 'fa'],
            'date_formats' => [
                'en' => 'Y-m-d',
                'de' => 'd.m.Y',
                'fr' => 'd/m/Y',
                'es' => 'd/m/Y',
            ],
            'pluralization' => [
                'separator' => '|',
                'rules' => [
                    'en' => 'english',
                    'de' => 'german',
                    'fr' => 'french',
                    'es' => 'spanish',
                ],
            ],
        ];
    }
}