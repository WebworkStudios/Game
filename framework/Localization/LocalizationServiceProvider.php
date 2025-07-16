<?php

declare(strict_types=1);

namespace Framework\Localization;

use Framework\Core\AbstractServiceProvider;
use Framework\Security\Session;

/**
 * Localization Service Provider - Registriert Mehrsprachigkeits-Services
 *
 * BEREINIGT: Keine Default-Provider mehr - Config-Dateien sind die einzige Quelle
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

        // Prüfe ob Config-Datei existiert
        if (!$this->configExists()) {
            throw new \RuntimeException(
                "Localization config file not found: " . self::CONFIG_PATH . "\n" .
                "Please create this file or run: php artisan config:publish localization"
            );
        }

        // Language-Verzeichnisse erstellen
        $config = $this->getConfig(self::CONFIG_PATH, requiredKeys: self::REQUIRED_KEYS);
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
            $config = $this->getConfig(self::CONFIG_PATH, requiredKeys: self::REQUIRED_KEYS);

            $languagesPath = $this->basePath($config['languages_path']);
            $this->ensureLanguageFiles($languagesPath, array_keys($config['supported_locales']));

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
            $config = $this->getConfig(self::CONFIG_PATH, requiredKeys: self::REQUIRED_KEYS);

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
     * Stellt sicher, dass Language-Verzeichnisse existieren
     */
    private function ensureLanguageDirectories(array $config): void
    {
        $languagesPath = $this->basePath($config['languages_path']);

        if (!is_dir($languagesPath) && !mkdir($languagesPath, 0755, true)) {
            throw new \RuntimeException("Cannot create languages directory: {$languagesPath}");
        }

        foreach (array_keys($config['supported_locales']) as $locale) {
            $localePath = $languagesPath . '/' . $locale;
            if (!is_dir($localePath) && !mkdir($localePath, 0755, true)) {
                throw new \RuntimeException("Cannot create locale directory: {$localePath}");
            }
        }
    }

    /**
     * Erstellt Standard-Sprachdateien
     */
    private function ensureLanguageFiles(string $languagesPath, array $supportedLocales): void
    {
        $defaultFiles = ['auth.php', 'validation.php', 'game.php'];

        foreach ($supportedLocales as $locale) {
            $localePath = $languagesPath . '/' . $locale;

            foreach ($defaultFiles as $file) {
                $filePath = $localePath . '/' . $file;

                if (!file_exists($filePath)) {
                    $content = $this->getDefaultLanguageContent($locale, pathinfo($file, PATHINFO_FILENAME));

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
                'min' => 'The :field must be at least :min characters.',
                'max' => 'The :field may not be greater than :max characters.',
                'numeric' => 'The :field must be a number.',
                'string' => 'The :field must be a string.',
            ],
            'game' => [
                'team' => 'Team',
                'player' => 'Player',
                'match' => 'Match',
                'goal' => 'Goal',
                'goals' => 'Goals',
                'win' => 'Win',
                'loss' => 'Loss',
                'draw' => 'Draw',
                'season' => 'Season',
                'league' => 'League',
            ],
            default => ['placeholder' => 'Placeholder content for ' . $group],
        };

        // Lokalisiere Inhalte basierend auf Sprache
        if ($locale === 'de') {
            $content = $this->getGermanTranslations($group, $content);
        }

        $exportedContent = var_export($content, true);

        return <<<PHP
<?php

declare(strict_types=1);

return {$exportedContent};
PHP;
    }

    /**
     * Deutsche Übersetzungen
     */
    private function getGermanTranslations(string $group, array $default): array
    {
        return match ($group) {
            'auth' => [
                'login' => 'Anmelden',
                'logout' => 'Abmelden',
                'register' => 'Registrieren',
                'password' => 'Passwort',
                'email' => 'E-Mail',
                'remember_me' => 'Angemeldet bleiben',
                'failed' => 'Diese Anmeldedaten stimmen nicht mit unseren Unterlagen überein.',
                'password_reset' => 'Passwort zurücksetzen',
            ],
            'validation' => [
                'required' => 'Das Feld :field ist erforderlich.',
                'email' => 'Das Feld :field muss eine gültige E-Mail-Adresse sein.',
                'min' => 'Das Feld :field muss mindestens :min Zeichen haben.',
                'max' => 'Das Feld :field darf maximal :max Zeichen haben.',
                'numeric' => 'Das Feld :field muss eine Zahl sein.',
                'string' => 'Das Feld :field muss ein Text sein.',
            ],
            'game' => [
                'team' => 'Team',
                'player' => 'Spieler',
                'match' => 'Spiel',
                'goal' => 'Tor',
                'goals' => 'Tore',
                'win' => 'Sieg',
                'loss' => 'Niederlage',
                'draw' => 'Unentschieden',
                'season' => 'Saison',
                'league' => 'Liga',
            ],
            default => $default,
        };
    }

    /**
     * Prüft ob Config-Datei existiert
     */
    private function configExists(): bool
    {
        return file_exists($this->basePath(self::CONFIG_PATH));
    }
}