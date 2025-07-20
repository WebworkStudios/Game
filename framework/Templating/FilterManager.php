<?php
declare(strict_types=1);

namespace Framework\Templating;

use Framework\Localization\Translator;
use Framework\Templating\Filters\DateFilters;
use Framework\Templating\Filters\FilterExecutor;
use Framework\Templating\Filters\FilterRegistry;
use Framework\Templating\Filters\JsonFilters;
use Framework\Templating\Filters\NumberFilters;
use Framework\Templating\Filters\TextFilters;
use Framework\Templating\Filters\TranslationFilters;
use Framework\Templating\Filters\UtilityFilters;

/**
 * FilterManager - KORRIGIERT für sichere Filter-Registrierung
 *
 * FIXES:
 * - Verwendet registerSafe() für besseres Error-Handling
 * - Robuste Registrierung mit Fallback bei Fehlern
 * - Debug-Informationen für Troubleshooting
 */
class FilterManager
{
    private FilterRegistry $registry;
    private FilterExecutor $executor;

    public function __construct(
        private readonly ?Translator $translator = null
    ) {
        $this->registry = new FilterRegistry();
        $this->executor = new FilterExecutor($this->registry);

        $this->registerDefaultFilters();
    }

    /**
     * KORRIGIERT: Sichere Registrierung aller Standard-Filter
     */
    private function registerDefaultFilters(): void
    {
        try {
            $this->registerTextFilters();
            $this->registerNumberFilters();
            $this->registerDateFilters();
            $this->registerUtilityFilters();
            $this->registerJsonFilters();
            $this->registerDebugFilters();

            if ($this->translator !== null) {
                $this->registerTranslationFilters();
            }

        } catch (\Throwable $e) {
            error_log("Critical error in FilterManager::registerDefaultFilters(): " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * KORRIGIERT: Text-Filter mit sicherer Registrierung
     */
    private function registerTextFilters(): void
    {
        $filters = [
            'upper' => [TextFilters::class, 'upper'],
            'lower' => [TextFilters::class, 'lower'],
            'title' => [TextFilters::class, 'title'],
            'capitalize' => [TextFilters::class, 'capitalize'],
            'reverse' => [TextFilters::class, 'reverse'],
            'length' => [TextFilters::class, 'length'],
            'truncate' => [TextFilters::class, 'truncate'],
            'slug' => [TextFilters::class, 'slug'],
            'nl2br' => [TextFilters::class, 'nl2br'],
            'strip_tags' => [TextFilters::class, 'stripTags'],
            'raw' => [TextFilters::class, 'raw'],
            'e' => [TextFilters::class, 'e'],
            'escape' => [TextFilters::class, 'escape'],
            'default' => [TextFilters::class, 'default'],
            'trim' => [TextFilters::class, 'trim'],
            'replace' => [TextFilters::class, 'replace'],
        ];

        $this->registerFilterBatch($filters, 'TextFilters');
    }

    /**
     * KORRIGIERT: Number-Filter mit sicherer Registrierung
     */
    private function registerNumberFilters(): void
    {
        $filters = [
            'number_format' => [NumberFilters::class, 'numberFormat'],
            'currency' => [NumberFilters::class, 'currency'],
            'percentage' => [NumberFilters::class, 'percentage'],
            'round' => [NumberFilters::class, 'round'],
            'ceil' => [NumberFilters::class, 'ceil'],
            'floor' => [NumberFilters::class, 'floor'],
            'abs' => [NumberFilters::class, 'abs'],
        ];

        $this->registerFilterBatch($filters, 'NumberFilters');
    }

    /**
     * KORRIGIERT: Date-Filter mit sicherer Registrierung
     */
    private function registerDateFilters(): void
    {
        $filters = [
            'date' => [DateFilters::class, 'date'],
            'date_format' => [DateFilters::class, 'dateFormat'],
            'time_ago' => [DateFilters::class, 'timeAgo'],
            'timestamp' => [DateFilters::class, 'timestamp'],
        ];

        $this->registerFilterBatch($filters, 'DateFilters');
    }

    /**
     * KORRIGIERT: Utility-Filter mit sicherer Registrierung
     */
    private function registerUtilityFilters(): void
    {
        $filters = [
            'length' => [UtilityFilters::class, 'length'],
            'count' => [UtilityFilters::class, 'count'],
            'first' => [UtilityFilters::class, 'first'],
            'last' => [UtilityFilters::class, 'last'],
            'is_empty' => [UtilityFilters::class, 'isEmpty'],
            'is_not_empty' => [UtilityFilters::class, 'isNotEmpty'],
            'type' => [UtilityFilters::class, 'type'],
            'plural' => [UtilityFilters::class, 'plural'],
            'array_get' => [UtilityFilters::class, 'arrayGet'],
            'object_get' => [UtilityFilters::class, 'objectGet'],
            'to_int' => [UtilityFilters::class, 'toInt'],
            'to_float' => [UtilityFilters::class, 'toFloat'],
            'to_bool' => [UtilityFilters::class, 'toBool'],
            'range' => [UtilityFilters::class, 'range'],
            'json' => [UtilityFilters::class, 'json'],
            'is_json' => [UtilityFilters::class, 'isJson'],
            'from_json' => [UtilityFilters::class, 'fromJson'],
        ];

        $this->registerFilterBatch($filters, 'UtilityFilters');
    }

    /**
     * HINZUGEFÜGT: JSON-Filter mit sicherer Registrierung
     */
    private function registerJsonFilters(): void
    {
        $filters = [
            'json_pretty' => [JsonFilters::class, 'jsonPretty'],
            'json_js' => [JsonFilters::class, 'jsonJs'],
            'json_minimal' => [JsonFilters::class, 'jsonMinimal'],
            'json_validate' => [JsonFilters::class, 'jsonValidate'],
            'ensure_json' => [JsonFilters::class, 'ensureJson'],
            'to_json' => [JsonFilters::class, 'json'],  // Alias
            'jsonify' => [JsonFilters::class, 'json'],  // Alias
        ];

        $this->registerFilterBatch($filters, 'JsonFilters');
    }

    /**
     * KORRIGIERT: Debug-Filter mit sicherer Registrierung
     */
    private function registerDebugFilters(): void
    {
        // Debug-Filter als Lazy-Filter registrieren (sicherer)
        $this->registry->registerLazy('debug', fn() => function(mixed $value): string {
            if (is_array($value) || is_object($value)) {
                return '<pre>' . htmlspecialchars(print_r($value, true)) . '</pre>';
            }
            return '<pre>' . htmlspecialchars(var_export($value, true)) . '</pre>';
        });

        $this->registry->registerLazy('json_debug', fn() => function(mixed $value): string {
            return '<pre>' . htmlspecialchars(JsonFilters::jsonPretty($value)) . '</pre>';
        });

        $this->registry->registerLazy('json_info', fn() => function(mixed $value): string {
            if (!is_string($value)) {
                return '<span>Not a string</span>';
            }

            $info = JsonFilters::jsonValidate($value);
            $status = $info['valid'] ? '✅ Valid' : '❌ Invalid';
            $error = $info['error'] ? " - {$info['error']}" : '';

            return "<span>{$status}{$error}</span>";
        });
    }

    /**
     * KORRIGIERT: Translation-Filter mit sicherer Registrierung
     */
    private function registerTranslationFilters(): void
    {
        $translationFilters = new TranslationFilters($this->translator);

        $filters = [
            't' => [$translationFilters, 'translate'],
            'translate' => [$translationFilters, 'translate'],
            'tp' => [$translationFilters, 'translatePlural'],
            'translate_plural' => [$translationFilters, 'translatePlural'],
            'has_translation' => [$translationFilters, 'hasTranslation'],
            'locale' => [$translationFilters, 'locale'],
            'translate_in' => [$translationFilters, 'translateIn'],
        ];

        $this->registerFilterBatch($filters, 'TranslationFilters');
    }

    /**
     * HINZUGEFÜGT: Batch-Registrierung mit Error-Handling
     */
    private function registerFilterBatch(array $filters, string $group): void
    {
        $errors = $this->registry->registerMultiple($filters);

        if (!empty($errors)) {
            $this->registrationErrors[$group] = $errors;
            error_log("Failed to register filters in group '{$group}': " . implode(', ', $errors));
        }
    }

    /**
     * Führt Filter aus (Hauptschnittstelle)
     */
    public function apply(string $filterName, mixed $value, array $parameters = []): mixed
    {
        return $this->executor->execute($filterName, $value, $parameters);
    }

    /**
     * Prüft ob Filter existiert
     */
    public function has(string $filterName): bool
    {
        return $this->executor->hasFilter($filterName);
    }

    /**
     * Gibt alle verfügbaren Filter zurück
     */
    public function getFilterNames(): array
    {
        return $this->executor->getAvailableFilters();
    }

    /**
     * Führt mehrere Filter nacheinander aus
     */
    public function applyPipeline(mixed $value, array $filterPipeline): mixed
    {
        return $this->executor->executePipeline($value, $filterPipeline);
    }

    /**
     * Entfernt einen Filter
     */
    public function remove(string $name): void
    {
        $this->registry->remove($name);
    }

    /**
     * Gibt Registry zurück (für erweiterte Nutzung)
     */
    public function getRegistry(): FilterRegistry
    {
        return $this->registry;
    }

    /**
     * Gibt Executor zurück (für erweiterte Nutzung)
     */
    public function getExecutor(): FilterExecutor
    {
        return $this->executor;
    }

}