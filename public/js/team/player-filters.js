/**
 * Team Player Filters - Externe JavaScript Datei
 *
 * Ersetzt das Inline-JavaScript im Template durch saubere,
 * wiederverwendbare und testbare Funktionen.
 *
 * Funktionalität:
 * - Position Filter
 * - Status Filter (Verfügbar/Verletzt)
 * - Altersgruppen Filter
 * - Filter Reset
 * - Performance-optimiert mit Event Delegation
 */

class TeamPlayerFilters {
    constructor() {
        this.config = this.getConfig();
        this.filters = {
            position: 'all',
            status: 'all',
            age: 'all'
        };

        this.elements = {
            positionFilter: null,
            statusFilter: null,
            ageFilter: null,
            resetBtn: null,
            playerCards: [],
            positionSections: []
        };

        this.init();
    }

    /**
     * Lädt Konfiguration aus dem vom Backend bereitgestellten js_config
     */
    getConfig() {
        // JavaScript-Konfiguration vom Backend (via Template)
        const defaultConfig = {
            debug_mode: false,
            filter_settings: {
                animation_duration: 300,
                enable_debug_alerts: false,
                auto_reset_timeout: 30000
            },
            player_data: {
                total_count: 0,
                positions: []
            }
        };

        // Versuche Konfiguration aus globalem js_config zu laden
        if (typeof window.js_config !== 'undefined') {
            return { ...defaultConfig, ...window.js_config };
        }

        return defaultConfig;
    }

    /**
     * Initialisiert die Filter-Funktionalität
     */
    init() {
        // Warten bis DOM geladen ist
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.setup());
        } else {
            this.setup();
        }
    }

    /**
     * Setup-Methode - sammelt Elemente und bindet Events
     */
    setup() {
        this.collectElements();
        this.bindEvents();
        this.setupAutoReset();

        if (this.config.debug_mode) {
            console.log('TeamPlayerFilters initialisiert:', this.elements);
        }
    }

    /**
     * Sammelt alle relevanten DOM-Elemente
     */
    collectElements() {
        this.elements = {
            positionFilter: document.getElementById('position-filter'),
            statusFilter: document.getElementById('status-filter'),
            ageFilter: document.getElementById('age-filter'),
            resetBtn: document.getElementById('filter-reset-btn'),
            playerCards: document.querySelectorAll('.player-card'),
            positionSections: document.querySelectorAll('.position-section')
        };

        // Validierung
        const requiredElements = ['positionFilter', 'statusFilter', 'ageFilter', 'resetBtn'];
        const missingElements = requiredElements.filter(key => !this.elements[key]);

        if (missingElements.length > 0) {
            console.error('TeamPlayerFilters: Fehlende Elemente:', missingElements);
            return false;
        }

        return true;
    }

    /**
     * Bindet Event-Listener an Filterelemente
     */
    bindEvents() {
        // Position Filter
        this.elements.positionFilter?.addEventListener('change', (e) => {
            this.filters.position = e.target.value;
            this.applyFilters();
            this.logFilterAction('position', e.target.value);
        });

        // Status Filter
        this.elements.statusFilter?.addEventListener('change', (e) => {
            this.filters.status = e.target.value;
            this.applyFilters();
            this.logFilterAction('status', e.target.value);
        });

        // Altersgruppen Filter
        this.elements.ageFilter?.addEventListener('change', (e) => {
            this.filters.age = e.target.value;
            this.applyFilters();
            this.logFilterAction('age', e.target.value);
        });

        // Reset Button
        this.elements.resetBtn?.addEventListener('click', () => {
            this.resetAllFilters();
        });
    }

    /**
     * Wendet alle aktiven Filter an
     */
    applyFilters() {
        let visiblePlayersCount = 0;
        let visibleSectionsCount = 0;

        // Position-Sektionen filtern
        this.elements.positionSections.forEach(section => {
            const sectionPosition = section.dataset.position;
            const shouldShowSection = this.filters.position === 'all' ||
                sectionPosition === this.filters.position;

            if (shouldShowSection) {
                section.style.display = 'block';
                section.classList.remove('hidden');
                visibleSectionsCount++;

                // Spieler innerhalb der Sektion filtern
                const sectionPlayers = section.querySelectorAll('.player-card');
                let visibleInSection = 0;

                sectionPlayers.forEach(playerCard => {
                    if (this.shouldShowPlayer(playerCard)) {
                        playerCard.style.display = 'block';
                        playerCard.classList.remove('hidden');
                        visiblePlayersCount++;
                        visibleInSection++;
                    } else {
                        playerCard.style.display = 'none';
                        playerCard.classList.add('hidden');
                    }
                });

                // Section verstecken wenn keine Spieler sichtbar
                if (visibleInSection === 0 && this.filters.position === 'all') {
                    section.style.display = 'none';
                    section.classList.add('hidden');
                    visibleSectionsCount--;
                }
            } else {
                section.style.display = 'none';
                section.classList.add('hidden');
            }
        });

        // Debug-Ausgabe
        if (this.config.debug_mode) {
            console.log(`Filter angewendet: ${visiblePlayersCount} Spieler, ${visibleSectionsCount} Sektionen sichtbar`);

            if (this.config.filter_settings.enable_debug_alerts) {
                alert(`Filter angewendet!\nSichtbare Spieler: ${visiblePlayersCount}\nSichtbare Sektionen: ${visibleSectionsCount}`);
            }
        }

        return { players: visiblePlayersCount, sections: visibleSectionsCount };
    }

    /**
     * Prüft ob ein Spieler angezeigt werden soll
     */
    shouldShowPlayer(playerCard) {
        // Status Filter
        if (this.filters.status !== 'all') {
            const playerStatus = playerCard.dataset.status || 'available';
            if (playerStatus !== this.filters.status) {
                return false;
            }
        }

        // Altersgruppen Filter
        if (this.filters.age !== 'all') {
            const playerAge = parseInt(playerCard.dataset.age) || 0;
            const ageGroup = this.getAgeGroup(playerAge);
            if (ageGroup !== this.filters.age) {
                return false;
            }
        }

        return true;
    }

    /**
     * Bestimmt Altersgruppe basierend auf Alter
     */
    getAgeGroup(age) {
        if (age < 21) return 'young';
        if (age <= 30) return 'prime';
        return 'veteran';
    }

    /**
     * Setzt alle Filter zurück
     */
    resetAllFilters() {
        // Filter-Werte zurücksetzen
        this.filters = {
            position: 'all',
            status: 'all',
            age: 'all'
        };

        // Form-Elemente zurücksetzen
        if (this.elements.positionFilter) this.elements.positionFilter.value = 'all';
        if (this.elements.statusFilter) this.elements.statusFilter.value = 'all';
        if (this.elements.ageFilter) this.elements.ageFilter.value = 'all';

        // Alle Elemente sichtbar machen
        this.elements.playerCards.forEach(card => {
            card.style.display = 'block';
            card.classList.remove('hidden');
        });

        this.elements.positionSections.forEach(section => {
            section.style.display = 'block';
            section.classList.remove('hidden');
        });

        if (this.config.debug_mode) {
            console.log('Alle Filter zurückgesetzt');

            if (this.config.filter_settings.enable_debug_alerts) {
                alert('Alle Filter zurückgesetzt!');
            }
        }
    }

    /**
     * Automatisches Reset nach Timeout (falls konfiguriert)
     */
    setupAutoReset() {
        if (this.config.filter_settings.auto_reset_timeout > 0) {
            setTimeout(() => {
                // Nur resetten wenn Filter aktiv sind
                const hasActiveFilters = Object.values(this.filters).some(value => value !== 'all');

                if (hasActiveFilters && this.config.debug_mode) {
                    console.log('Auto-Reset nach Timeout ausgeführt');
                    this.resetAllFilters();
                }
            }, this.config.filter_settings.auto_reset_timeout);
        }
    }

    /**
     * Loggt Filter-Aktionen für Debugging
     */
    logFilterAction(filterType, value) {
        if (this.config.debug_mode) {
            console.log(`Filter ${filterType} geändert zu: ${value}`);
            console.log('Aktive Filter:', this.filters);
        }
    }

    /**
     * Public API für externe Verwendung
     */
    getActiveFilters() {
        return { ...this.filters };
    }

    getVisiblePlayersCount() {
        return this.elements.playerCards.length -
            document.querySelectorAll('.player-card.hidden').length;
    }

    // Debug-Hilfsmethoden
    getDebugInfo() {
        return {
            config: this.config,
            filters: this.filters,
            elements: {
                positionFilter: !!this.elements.positionFilter,
                statusFilter: !!this.elements.statusFilter,
                ageFilter: !!this.elements.ageFilter,
                playerCards: this.elements.playerCards.length,
                positionSections: this.elements.positionSections.length
            },
            visiblePlayers: this.getVisiblePlayersCount()
        };
    }
}

// Auto-Initialisierung
const teamPlayerFilters = new TeamPlayerFilters();

// Global verfügbar machen für Debug-Zwecke
if (window.js_config?.debug_mode) {
    window.TeamPlayerFilters = teamPlayerFilters;
}