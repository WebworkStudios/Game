/**
 * Debug Tools für Team Overview - Nur im Development Mode aktiv
 *
 * Funktionalität:
 * - Debug-Konsole für Filter-Informationen
 * - Performance-Monitoring
 * - DOM-Element-Inspektion
 * - Spieler-Statistiken Übersicht
 * - Test-Funktionen für Filter
 */

class TeamDebugTools {
    constructor() {
        this.config = this.getConfig();
        this.debugOutputElement = null;
        this.isDebugMode = this.config.debug_mode;

        if (this.isDebugMode) {
            this.init();
        }
    }

    /**
     * Lädt Debug-Konfiguration
     */
    getConfig() {
        const defaultConfig = {
            debug_mode: false,
            filter_settings: {
                enable_debug_alerts: false
            }
        };

        if (typeof window.js_config !== 'undefined') {
            return { ...defaultConfig, ...window.js_config };
        }

        return defaultConfig;
    }

    /**
     * Initialisiert Debug-Tools
     */
    init() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.setup());
        } else {
            this.setup();
        }
    }

    /**
     * Setup Debug-Interface
     */
    setup() {
        this.debugOutputElement = document.getElementById('debug-output');
        this.bindDebugButtons();
        this.setupConsoleLogging();
        this.logSystemInfo();

        console.log('🔧 TeamDebugTools aktiviert');
        console.log('Verfügbare Befehle:', this.getAvailableCommands());
    }

    /**
     * Bindet Debug-Button Events
     */
    bindDebugButtons() {
        // Debug Info Button
        const debugInfoBtn = document.getElementById('debug-info-btn');
        if (debugInfoBtn) {
            debugInfoBtn.addEventListener('click', () => {
                this.showDebugInfo();
            });
        }

        // Filter Test Button
        const testFiltersBtn = document.getElementById('test-filters-btn');
        if (testFiltersBtn) {
            testFiltersBtn.addEventListener('click', () => {
                this.runFilterTests();
            });
        }

        // Player Stats Button
        const playerStatsBtn = document.getElementById('player-stats-btn');
        if (playerStatsBtn) {
            playerStatsBtn.addEventListener('click', () => {
                this.showPlayerStatistics();
            });
        }
    }

    /**
     * Zeigt umfassende Debug-Informationen
     */
    showDebugInfo() {
        const debugInfo = this.collectDebugInfo();
        const formattedInfo = this.formatDebugInfo(debugInfo);

        this.outputToConsole('=== DEBUG INFO ===');
        this.outputToConsole(formattedInfo);
        this.outputToDebugPanel(formattedInfo);

        // Alert für schnelle Übersicht
        if (this.config.filter_settings.enable_debug_alerts) {
            alert(`Debug Info:\n${this.formatDebugInfoForAlert(debugInfo)}`);
        }
    }

    /**
     * Sammelt alle Debug-Informationen
     */
    collectDebugInfo() {
        const playerCards = document.querySelectorAll('.player-card');
        const positionSections = document.querySelectorAll('.position-section');
        const filterElements = {
            position: document.getElementById('position-filter'),
            status: document.getElementById('status-filter'),
            age: document.getElementById('age-filter')
        };

        return {
            timestamp: new Date().toISOString(),
            page: {
                title: document.title,
                url: window.location.href,
                userAgent: navigator.userAgent
            },
            viewport: {
                width: window.innerWidth,
                height: window.innerHeight,
                devicePixelRatio: window.devicePixelRatio || 1
            },
            elements: {
                playerCards: {
                    total: playerCards.length,
                    visible: Array.from(playerCards).filter(card =>
                        card.style.display !== 'none' && !card.classList.contains('hidden')
                    ).length,
                    hidden: Array.from(playerCards).filter(card =>
                        card.style.display === 'none' || card.classList.contains('hidden')
                    ).length
                },
                positionSections: {
                    total: positionSections.length,
                    visible: Array.from(positionSections).filter(section =>
                        section.style.display !== 'none' && !section.classList.contains('hidden')
                    ).length
                },
                filters: {
                    position: {
                        found: !!filterElements.position,
                        value: filterElements.position?.value || 'N/A',
                        options: filterElements.position?.children.length || 0
                    },
                    status: {
                        found: !!filterElements.status,
                        value: filterElements.status?.value || 'N/A',
                        options: filterElements.status?.children.length || 0
                    },
                    age: {
                        found: !!filterElements.age,
                        value: filterElements.age?.value || 'N/A',
                        options: filterElements.age?.children.length || 0
                    }
                }
            },
            filters: window.TeamPlayerFilters ? window.TeamPlayerFilters.getActiveFilters() : 'N/A',
            performance: this.getPerformanceMetrics(),
            external_libraries: this.checkExternalLibraries()
        };
    }

    /**
     * Formatiert Debug-Info für Konsole
     */
    formatDebugInfo(debugInfo) {
        return `
Timestamp: ${debugInfo.timestamp}
Viewport: ${debugInfo.viewport.width}x${debugInfo.viewport.height} (${debugInfo.viewport.devicePixelRatio}x)

SPIELER-ELEMENTE:
- Gesamt: ${debugInfo.elements.playerCards.total}
- Sichtbar: ${debugInfo.elements.playerCards.visible}
- Versteckt: ${debugInfo.elements.playerCards.hidden}

POSITION-SEKTIONEN:
- Gesamt: ${debugInfo.elements.positionSections.total}
- Sichtbar: ${debugInfo.elements.positionSections.visible}

FILTER-ELEMENTE:
- Position: ${debugInfo.elements.filters.position.found ? 'GEFUNDEN' : 'FEHLT'} (${debugInfo.elements.filters.position.options} Optionen)
- Status: ${debugInfo.elements.filters.status.found ? 'GEFUNDEN' : 'FEHLT'} (${debugInfo.elements.filters.status.options} Optionen)
- Alter: ${debugInfo.elements.filters.age.found ? 'GEFUNDEN' : 'FEHLT'} (${debugInfo.elements.filters.age.options} Optionen)

AKTIVE FILTER:
${debugInfo.filters !== 'N/A' ? JSON.stringify(debugInfo.filters, null, 2) : 'Filter-System nicht verfügbar'}

PERFORMANCE:
${debugInfo.performance}
        `.trim();
    }

    /**
     * Formatiert Debug-Info für Alert
     */
    formatDebugInfoForAlert(debugInfo) {
        return `Spieler: ${debugInfo.elements.playerCards.visible}/${debugInfo.elements.playerCards.total} sichtbar
Sektionen: ${debugInfo.elements.positionSections.visible}/${debugInfo.elements.positionSections.total}
Filter: Position=${debugInfo.elements.filters.position.value}, Status=${debugInfo.elements.filters.status.value}, Alter=${debugInfo.elements.filters.age.value}`;
    }

    /**
     * Führt Filter-Tests durch
     */
    runFilterTests() {
        this.outputToConsole('=== FILTER TESTS ===');

        if (!window.TeamPlayerFilters) {
            this.outputToConsole('❌ TeamPlayerFilters nicht verfügbar');
            return;
        }

        const filters = window.TeamPlayerFilters;
        const testScenarios = [
            { position: 'all', status: 'all', age: 'all' },
            { position: 'forwards', status: 'all', age: 'all' },
            { position: 'all', status: 'injured', age: 'all' },
            { position: 'all', status: 'all', age: 'veteran' },
            { position: 'midfielders', status: 'available', age: 'prime' }
        ];

        const initialFilters = filters.getActiveFilters();

        testScenarios.forEach((scenario, index) => {
            this.outputToConsole(`\nTest ${index + 1}: ${JSON.stringify(scenario)}`);

            // Filter anwenden (simuliert)
            const testResults = this.simulateFilterApplication(scenario);
            this.outputToConsole(`Ergebnis: ${testResults.players} Spieler, ${testResults.sections} Sektionen`);
        });

        // Filter zurücksetzen
        filters.resetAllFilters();
        this.outputToConsole('\n✅ Filter-Tests abgeschlossen, Filter zurückgesetzt');

        this.outputToDebugPanel('Filter-Tests abgeschlossen. Details in der Konsole.');
    }

    /**
     * Simuliert Filter-Anwendung für Tests
     */
    simulateFilterApplication(scenario) {
        const playerCards = document.querySelectorAll('.player-card');
        let visiblePlayers = 0;
        let visibleSections = 0;

        // Simuliere Filter-Logik
        playerCards.forEach(card => {
            const cardPosition = card.dataset.position;
            const cardStatus = card.dataset.status || 'available';
            const cardAge = parseInt(card.dataset.age) || 0;
            const cardAgeGroup = this.getAgeGroup(cardAge);

            const matchesPosition = scenario.position === 'all' || cardPosition === scenario.position;
            const matchesStatus = scenario.status === 'all' || cardStatus === scenario.status;
            const matchesAge = scenario.age === 'all' || cardAgeGroup === scenario.age;

            if (matchesPosition && matchesStatus && matchesAge) {
                visiblePlayers++;
            }
        });

        // Sektionen zählen
        const positionSections = document.querySelectorAll('.position-section');
        positionSections.forEach(section => {
            const sectionPosition = section.dataset.position;
            if (scenario.position === 'all' || sectionPosition === scenario.position) {
                visibleSections++;
            }
        });

        return { players: visiblePlayers, sections: visibleSections };
    }

    /**
     * Zeigt detaillierte Spieler-Statistiken
     */
    showPlayerStatistics() {
        this.outputToConsole('=== SPIELER STATISTIKEN ===');

        const playerCards = document.querySelectorAll('.player-card');
        const stats = {
            total: playerCards.length,
            byPosition: {},
            byAge: { young: 0, prime: 0, veteran: 0 },
            byStatus: { available: 0, injured: 0 },
            byNationality: {}
        };

        playerCards.forEach(card => {
            const position = card.dataset.position;
            const age = parseInt(card.dataset.age) || 0;
            const ageGroup = this.getAgeGroup(age);
            const status = card.dataset.status || 'available';
            const nationality = card.dataset.nationality || 'unknown';

            // Nach Position
            stats.byPosition[position] = (stats.byPosition[position] || 0) + 1;

            // Nach Altersgruppe
            stats.byAge[ageGroup]++;

            // Nach Status
            stats.byStatus[status] = (stats.byStatus[status] || 0) + 1;

            // Nach Nationalität
            stats.byNationality[nationality] = (stats.byNationality[nationality] || 0) + 1;
        });

        const formattedStats = `
Gesamt: ${stats.total} Spieler

Nach Position:
${Object.entries(stats.byPosition).map(([pos, count]) => `  ${pos}: ${count}`).join('\n')}

Nach Altersgruppe:
  Nachwuchs (U21): ${stats.byAge.young}
  Prime (21-30): ${stats.byAge.prime}
  Veteran (30+): ${stats.byAge.veteran}

Nach Status:
${Object.entries(stats.byStatus).map(([status, count]) => `  ${status}: ${count}`).join('\n')}

Top Nationalitäten:
${Object.entries(stats.byNationality)
            .sort(([,a], [,b]) => b - a)
            .slice(0, 5)
            .map(([nat, count]) => `  ${nat}: ${count}`)
            .join('\n')}
        `.trim();

        this.outputToConsole(formattedStats);
        this.outputToDebugPanel(formattedStats);
    }

    /**
     * Hilfsmethode: Altersgruppe bestimmen
     */
    getAgeGroup(age) {
        if (age < 21) return 'young';
        if (age <= 30) return 'prime';
        return 'veteran';
    }

    /**
     * Performance-Metriken sammeln
     */
    getPerformanceMetrics() {
        if (!window.performance) return 'Performance API nicht verfügbar';

        const navigation = performance.getEntriesByType('navigation')[0];
        if (!navigation) return 'Navigation Timing nicht verfügbar';

        return `- DOM Load: ${Math.round(navigation.domContentLoadedEventEnd - navigation.domContentLoadedEventStart)}ms
- Page Load: ${Math.round(navigation.loadEventEnd - navigation.loadEventStart)}ms
- Memory: ${navigator.deviceMemory || 'N/A'} GB`;
    }

    /**
     * Prüft externe Bibliotheken
     */
    checkExternalLibraries() {
        const libraries = [];

        if (window.jQuery) libraries.push(`jQuery ${window.jQuery.fn.jquery}`);
        if (window.Vue) libraries.push(`Vue ${window.Vue.version}`);
        if (window.React) libraries.push('React');
        if (window.TeamPlayerFilters) libraries.push('TeamPlayerFilters ✅');
        if (window.TeamOverviewUtils) libraries.push('TeamOverviewUtils ✅');

        return libraries.length > 0 ? libraries.join(', ') : 'Keine erkannt';
    }

    /**
     * Console-Logging Setup
     */
    setupConsoleLogging() {
        // Erweiterte Console-Methoden für Debug
        window.debugTeam = {
            info: () => this.showDebugInfo(),
            filters: () => this.runFilterTests(),
            stats: () => this.showPlayerStatistics(),
            performance: () => console.log(this.getPerformanceMetrics()),
            clear: () => this.clearDebugOutput(),
            help: () => console.log(this.getAvailableCommands())
        };
    }

    /**
     * Verfügbare Debug-Befehle
     */
    getAvailableCommands() {
        return `
Verfügbare Debug-Befehle:
- debugTeam.info()        → Zeigt Debug-Informationen
- debugTeam.filters()     → Führt Filter-Tests durch
- debugTeam.stats()       → Zeigt Spieler-Statistiken
- debugTeam.performance() → Performance-Metriken
- debugTeam.clear()       → Löscht Debug-Ausgabe
- debugTeam.help()        → Zeigt diese Hilfe
        `.trim();
    }

    /**
     * Ausgabe in Konsole
     */
    outputToConsole(message) {
        console.log(message);
    }

    /**
     * Ausgabe im Debug-Panel
     */
    outputToDebugPanel(message) {
        if (this.debugOutputElement) {
            this.debugOutputElement.textContent = message;
        }
    }

    /**
     * Debug-Ausgabe leeren
     */
    clearDebugOutput() {
        if (this.debugOutputElement) {
            this.debugOutputElement.textContent = '';
        }
        console.clear();
        console.log('🔧 Debug-Ausgabe geleert');
    }

    /**
     * Loggt System-Informationen beim Start
     */
    logSystemInfo() {
        const systemInfo = `
🔧 Team Debug Tools gestartet
Browser: ${navigator.userAgent}
Viewport: ${window.innerWidth}x${window.innerHeight}
Pixel Ratio: ${window.devicePixelRatio || 1}
Speicher: ${navigator.deviceMemory || 'N/A'} GB
Online: ${navigator.onLine}
JavaScript: ${this.config.debug_mode ? 'Debug Mode' : 'Production Mode'}
        `.trim();

        console.log(systemInfo);
    }
}

// Auto-Initialisierung nur im Debug-Modus
if (window.js_config?.debug_mode) {
    const teamDebugTools = new TeamDebugTools();
    window.TeamDebugTools = teamDebugTools;
}