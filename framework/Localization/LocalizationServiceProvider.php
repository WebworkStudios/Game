<?php

declare(strict_types=1);

namespace Framework\Localization;

use Framework\Core\Application;
use Framework\Core\ServiceContainer;
use Framework\Security\Session;
use InvalidArgumentException;

/**
 * Localization Service Provider - Registriert Mehrsprachigkeits-Services
 */
class LocalizationServiceProvider
{
    private const string DEFAULT_CONFIG_PATH = 'app/Config/localization.php';

    public function __construct(
        private readonly ServiceContainer $container,
        private readonly Application      $app,
    )
    {
    }

    /**
     * Registriert alle Localization Services
     */
    public function register(): void
    {
        $this->registerTranslator();
        $this->registerLanguageDetector();
        $this->registerMiddlewares();
        $this->bindInterfaces();
    }

    /**
     * Registriert Translator als Singleton
     */
    private function registerTranslator(): void
    {
        $this->container->singleton(Translator::class, function () {
            $config = $this->loadLocalizationConfig();

            $languagesPath = $this->app->getBasePath() . '/' . $config['languages_path'];

            // Create language directories and files if they don't exist
            $this->ensureLanguageFiles($languagesPath, $config['supported_locales']);

            $translator = new Translator($languagesPath);

            // Set default and fallback locales
            $translator->setLocale($config['default_locale']);
            $translator->setFallbackLocale($config['fallback_locale']);

            return $translator;
        });
    }

    /**
     * Lädt Localization-Konfiguration mit Caching
     */
    private function loadLocalizationConfig(): array
    {
        static $config = null;

        if ($config !== null) {
            return $config;
        }

        $configPath = $this->app->getBasePath() . '/' . self::DEFAULT_CONFIG_PATH;

        // Create config file if it doesn't exist
        if (!file_exists($configPath)) {
            if (!self::publishConfig($this->app->getBasePath())) {
                throw new InvalidArgumentException("Failed to create localization config at: {$configPath}");
            }
        }

        $config = require $configPath;

        if (!is_array($config)) {
            throw new InvalidArgumentException('Localization config must return array');
        }

        // Validate required config keys
        $required = ['default_locale', 'fallback_locale', 'supported_locales', 'languages_path'];
        foreach ($required as $key) {
            if (!isset($config[$key])) {
                throw new InvalidArgumentException("Missing required config key: {$key}");
            }
        }

        return $config;
    }

    /**
     * Erstellt Standard-Konfigurationsdatei
     */
    public static function publishConfig(string $basePath): bool
    {
        $configPath = $basePath . '/' . self::DEFAULT_CONFIG_PATH;
        $configDir = dirname($configPath);

        if (!is_dir($configDir) && !mkdir($configDir, 0755, true)) {
            return false;
        }

        $content = <<<'PHP'
<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Localization Configuration
    |--------------------------------------------------------------------------
    */
    
    'default_locale' => 'de',
    'fallback_locale' => 'de',
    
    'supported_locales' => [
        'de' => 'Deutsch',
        'en' => 'English', 
        'fr' => 'Français',
        'es' => 'Español',
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Language Files Path
    |--------------------------------------------------------------------------
    */
    
    'languages_path' => 'app/Languages',
    
    /*
    |--------------------------------------------------------------------------
    | Detection Configuration
    |--------------------------------------------------------------------------
    */
    
    'detection' => [
        'session_key' => 'locale',
        'cookie_name' => 'app_locale',
        'cookie_lifetime' => 60 * 60 * 24 * 365, // 1 year
        'url_parameter' => 'lang', // ?lang=en
    ],
];
PHP;

        return file_put_contents($configPath, $content) !== false;
    }

    /**
     * Ensure language directories and basic files exist
     */
    private function ensureLanguageFiles(string $languagesPath, array $supportedLocales): void
    {
        foreach ($supportedLocales as $locale => $name) {
            $localePath = $languagesPath . '/' . $locale;

            // Create directory
            if (!is_dir($localePath)) {
                mkdir($localePath, 0755, true);
            }

            // Create basic language files if they don't exist
            $this->createBasicLanguageFile($localePath, $locale, 'common');
            $this->createBasicLanguageFile($localePath, $locale, 'auth');
            $this->createBasicLanguageFile($localePath, $locale, 'game');
            $this->createBasicLanguageFile($localePath, $locale, 'match');
        }
    }

    /**
     * Create basic language file if it doesn't exist
     */
    private function createBasicLanguageFile(string $localePath, string $locale, string $namespace): void
    {
        $filePath = $localePath . '/' . $namespace . '.php';

        if (file_exists($filePath)) {
            return; // File already exists
        }

        $translations = $this->getBasicTranslations($locale, $namespace);
        $content = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($translations, true) . ";\n";

        file_put_contents($filePath, $content);
    }

    /**
     * Get basic translations for a locale and namespace
     */
    private function getBasicTranslations(string $locale, string $namespace): array
    {
        return match ([$locale, $namespace]) {
            ['de', 'common'] => [
                'welcome' => 'Willkommen',
                'navigation' => [
                    'home' => 'Startseite',
                    'team' => 'Team',
                    'matches' => 'Spiele',
                    'league' => 'Liga',
                    'profile' => 'Profil',
                ],
                'actions' => [
                    'save' => 'Speichern',
                    'cancel' => 'Abbrechen',
                    'delete' => 'Löschen',
                    'edit' => 'Bearbeiten',
                ],
                'languages' => [
                    'de' => 'Deutsch',
                    'en' => 'English',
                    'fr' => 'Français',
                    'es' => 'Español',
                ],
            ],
            ['en', 'common'] => [
                'welcome' => 'Welcome',
                'navigation' => [
                    'home' => 'Home',
                    'team' => 'Team',
                    'matches' => 'Matches',
                    'league' => 'League',
                    'profile' => 'Profile',
                ],
                'actions' => [
                    'save' => 'Save',
                    'cancel' => 'Cancel',
                    'delete' => 'Delete',
                    'edit' => 'Edit',
                ],
                'languages' => [
                    'de' => 'German',
                    'en' => 'English',
                    'fr' => 'French',
                    'es' => 'Spanish',
                ],
            ],
            ['fr', 'common'] => [
                'welcome' => 'Bienvenue',
                'navigation' => [
                    'home' => 'Accueil',
                    'team' => 'Équipe',
                    'matches' => 'Matchs',
                    'league' => 'Ligue',
                    'profile' => 'Profil',
                ],
                'actions' => [
                    'save' => 'Enregistrer',
                    'cancel' => 'Annuler',
                    'delete' => 'Supprimer',
                    'edit' => 'Modifier',
                ],
                'languages' => [
                    'de' => 'Allemand',
                    'en' => 'Anglais',
                    'fr' => 'Français',
                    'es' => 'Espagnol',
                ],
            ],
            ['es', 'common'] => [
                'welcome' => 'Bienvenido',
                'navigation' => [
                    'home' => 'Inicio',
                    'team' => 'Equipo',
                    'matches' => 'Partidos',
                    'league' => 'Liga',
                    'profile' => 'Perfil',
                ],
                'actions' => [
                    'save' => 'Guardar',
                    'cancel' => 'Cancelar',
                    'delete' => 'Eliminar',
                    'edit' => 'Editar',
                ],
                'languages' => [
                    'de' => 'Alemán',
                    'en' => 'Inglés',
                    'fr' => 'Francés',
                    'es' => 'Español',
                ],
            ],
            ['de', 'auth'] => [
                'login' => 'Anmelden',
                'logout' => 'Abmelden',
                'register' => 'Registrieren',
                'password' => 'Passwort',
                'email' => 'E-Mail',
                'username' => 'Benutzername',
            ],
            ['en', 'auth'] => [
                'login' => 'Login',
                'logout' => 'Logout',
                'register' => 'Register',
                'password' => 'Password',
                'email' => 'Email',
                'username' => 'Username',
            ],
            ['fr', 'auth'] => [
                'login' => 'Connexion',
                'logout' => 'Déconnexion',
                'register' => 'S\'inscrire',
                'password' => 'Mot de passe',
                'email' => 'Email',
                'username' => 'Nom d\'utilisateur',
            ],
            ['es', 'auth'] => [
                'login' => 'Iniciar sesión',
                'logout' => 'Cerrar sesión',
                'register' => 'Registrarse',
                'password' => 'Contraseña',
                'email' => 'Correo',
                'username' => 'Usuario',
            ],
            ['de', 'game'] => [
                'goals' => [
                    'singular' => '{count} Tor',
                    'plural' => '{count} Tore',
                ],
                'assists' => [
                    'singular' => '{count} Vorlage',
                    'plural' => '{count} Vorlagen',
                ],
                'players' => [
                    'singular' => '{count} Spieler',
                    'plural' => '{count} Spieler',
                ],
            ],
            ['en', 'game'] => [
                'goals' => [
                    'singular' => '{count} Goal',
                    'plural' => '{count} Goals',
                ],
                'assists' => [
                    'singular' => '{count} Assist',
                    'plural' => '{count} Assists',
                ],
                'players' => [
                    'singular' => '{count} Player',
                    'plural' => '{count} Players',
                ],
            ],
            ['fr', 'game'] => [
                'goals' => [
                    'singular' => '{count} But',
                    'plural' => '{count} Buts',
                ],
                'assists' => [
                    'singular' => '{count} Passe',
                    'plural' => '{count} Passes',
                ],
                'players' => [
                    'singular' => '{count} Joueur',
                    'plural' => '{count} Joueurs',
                ],
            ],
            ['es', 'game'] => [
                'goals' => [
                    'singular' => '{count} Gol',
                    'plural' => '{count} Goles',
                ],
                'assists' => [
                    'singular' => '{count} Asistencia',
                    'plural' => '{count} Asistencias',
                ],
                'players' => [
                    'singular' => '{count} Jugador',
                    'plural' => '{count} Jugadores',
                ],
            ],
            ['de', 'match'] => [
                'live_ticker' => 'Live-Ticker',
                'goal_scored' => '{player} erzielt ein Tor in Minute {minute}!',
                'match_started' => 'Das Spiel hat begonnen!',
                'match_ended' => 'Das Spiel ist beendet!',
            ],
            ['en', 'match'] => [
                'live_ticker' => 'Live Ticker',
                'goal_scored' => '{player} scores a goal in minute {minute}!',
                'match_started' => 'The match has started!',
                'match_ended' => 'The match has ended!',
            ],
            ['fr', 'match'] => [
                'live_ticker' => 'Ticker en direct',
                'goal_scored' => '{player} marque un but à la {minute}e minute !',
                'match_started' => 'Le match a commencé !',
                'match_ended' => 'Le match est terminé !',
            ],
            ['es', 'match'] => [
                'live_ticker' => 'Ticker en vivo',
                'goal_scored' => '¡{player} anota un gol en el minuto {minute}!',
                'match_started' => '¡El partido ha comenzado!',
                'match_ended' => '¡El partido ha terminado!',
            ],
            default => [],
        };
    }

    /**
     * Registriert Language Detector
     */
    private function registerLanguageDetector(): void
    {
        $this->container->singleton(LanguageDetector::class, function (ServiceContainer $container) {
            $config = $this->loadLocalizationConfig();

            return new LanguageDetector(
                session: $container->get(Session::class),
                supportedLocales: array_keys($config['supported_locales']),
                defaultLocale: $config['default_locale']
            );
        });
    }

    private function registerMiddlewares(): void
    {
        $this->container->transient(LanguageMiddleware::class, function (ServiceContainer $container) {
            return new LanguageMiddleware(
                detector: $container->get(LanguageDetector::class),
                translator: $container->get(Translator::class)
            );
        });
    }

    /**
     * Bindet Interfaces (für zukünftige Erweiterungen)
     */
    private function bindInterfaces(): void
    {
        // Placeholder für Localization-Interfaces
        // $this->container->bind(TranslatorInterface::class, Translator::class);
        // $this->container->bind(LanguageDetectorInterface::class, LanguageDetector::class);
    }
}