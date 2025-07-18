/**
 * Team Overview Utilities - Allgemeine Team-Funktionalität
 *
 * Enthält zusätzliche Funktionen für die Team-Übersicht:
 * - Spieler-Detailansicht
 * - Statistik-Animationen
 * - Responsive Helpers
 * - Keyboard Navigation
 */

class TeamOverviewUtils {
    constructor() {
        this.config = this.getConfig();
        this.animationQueue = [];
        this.isAnimating = false;

        this.init();
    }

    /**
     * Lädt Konfiguration
     */
    getConfig() {
        const defaultConfig = {
            debug_mode: false,
            animations: {
                enabled: true,
                duration: 300,
                easing: 'ease-out'
            }
        };

        if (typeof window.js_config !== 'undefined') {
            return { ...defaultConfig, ...window.js_config };
        }

        return defaultConfig;
    }

    /**
     * Initialisierung
     */
    init() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.setup());
        } else {
            this.setup();
        }
    }

    /**
     * Setup aller Features
     */
    setup() {
        this.setupPlayerCardHovers();
        this.setupKeyboardNavigation();
        this.setupResponsiveFeatures();
        this.setupStatisticAnimations();

        if (this.config.debug_mode) {
            console.log('TeamOverviewUtils initialisiert');
        }
    }

    /**
     * Verbesserte Hover-Effekte für Spielerkarten
     */
    setupPlayerCardHovers() {
        const playerCards = document.querySelectorAll('.player-card');

        playerCards.forEach(card => {
            // Smooth hover animations
            card.addEventListener('mouseenter', (e) => {
                this.animateCardHover(e.target, true);
            });

            card.addEventListener('mouseleave', (e) => {
                this.animateCardHover(e.target, false);
            });

            // Click-to-expand functionality
            card.addEventListener('click', (e) => {
                if (!e.target.closest('button')) {
                    this.togglePlayerDetails(card);
                }
            });
        });
    }

    /**
     * Animiert Spielerkarten-Hover
     */
    animateCardHover(card, isHover) {
        if (!this.config.animations.enabled) return;

        const scale = isHover ? 'scale(1.02)' : 'scale(1)';
        const shadow = isHover ? '0 8px 25px rgba(0,0,0,0.15)' : '0 2px 10px rgba(0,0,0,0.1)';

        card.style.transition = `transform ${this.config.animations.duration}ms ${this.config.animations.easing}, box-shadow ${this.config.animations.duration}ms ${this.config.animations.easing}`;
        card.style.transform = scale;
        card.style.boxShadow = shadow;
    }

    /**
     * Toggle für erweiterte Spieler-Details
     */
    togglePlayerDetails(card) {
        const isExpanded = card.classList.contains('expanded');

        // Alle anderen Karten schließen
        document.querySelectorAll('.player-card.expanded').forEach(otherCard => {
            if (otherCard !== card) {
                this.collapsePlayerCard(otherCard);
            }
        });

        if (isExpanded) {
            this.collapsePlayerCard(card);
        } else {
            this.expandPlayerCard(card);
        }
    }

    /**
     * Erweitert eine Spielerkarte mit zusätzlichen Details
     */
    expandPlayerCard(card) {
        card.classList.add('expanded');

        // Erstelle erweiterte Details falls noch nicht vorhanden
        if (!card.querySelector('.extended-details')) {
            const playerId = card.dataset.playerId;
            const extendedDetails = this.createExtendedPlayerDetails(playerId);
            card.appendChild(extendedDetails);
        }

        // Animation
        const details = card.querySelector('.extended-details');
        if (details && this.config.animations.enabled) {
            details.style.maxHeight = '0px';
            details.style.opacity = '0';
            details.style.overflow = 'hidden';
            details.style.transition = `max-height ${this.config.animations.duration}ms ease-out, opacity ${this.config.animations.duration}ms ease-out`;

            // Force reflow
            details.offsetHeight;

            details.style.maxHeight = '200px';
            details.style.opacity = '1';
        }

        if (this.config.debug_mode) {
            console.log('Spielerkarte erweitert:', card.dataset.playerId);
        }
    }

    /**
     * Kollabiert eine erweiterte Spielerkarte
     */
    collapsePlayerCard(card) {
        card.classList.remove('expanded');

        const details = card.querySelector('.extended-details');
        if (details && this.config.animations.enabled) {
            details.style.maxHeight = '0px';
            details.style.opacity = '0';

            setTimeout(() => {
                if (!card.classList.contains('expanded')) {
                    details.remove();
                }
            }, this.config.animations.duration);
        } else if (details) {
            details.remove();
        }
    }

    /**
     * Erstellt erweiterte Spieler-Details
     */
    createExtendedPlayerDetails(playerId) {
        const details = document.createElement('div');
        details.className = 'extended-details';

        // Hier könnten zusätzliche Spielerdaten per AJAX geladen werden
        // Für jetzt nur statischer Platzhalter
        details.innerHTML = `
            <div class="extended-stats">
                <h5>Erweiterte Statistiken</h5>
                <div class="stat-grid">
                    <div class="stat-item">
                        <span class="stat-value">89%</span>
                        <span class="stat-label">Passgenauigkeit</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value">7.2</span>
                        <span class="stat-label">Durchschnittsnote</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value">12</span>
                        <span class="stat-label">Zweikämpfe/Spiel</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value">2.1</span>
                        <span class="stat-label">Schüsse/Spiel</span>
                    </div>
                </div>
                <div class="action-buttons">
                    <button class="btn-small" onclick="alert('Spieler-Profil öffnen')">Profil</button>
                    <button class="btn-small" onclick="alert('Transfer-Optionen')">Transfer</button>
                </div>
            </div>
        `;

        return details;
    }

    /**
     * Keyboard Navigation Setup
     */
    setupKeyboardNavigation() {
        document.addEventListener('keydown', (e) => {
            switch(e.key) {
                case 'Escape':
                    // Alle erweiterten Karten schließen
                    document.querySelectorAll('.player-card.expanded').forEach(card => {
                        this.collapsePlayerCard(card);
                    });
                    break;

                case 'r':
                case 'R':
                    if (e.ctrlKey || e.metaKey) {
                        e.preventDefault();
                        // Filter reset via Keyboard
                        if (window.TeamPlayerFilters) {
                            window.TeamPlayerFilters.resetAllFilters();
                        }
                    }
                    break;
            }
        });
    }

    /**
     * Responsive Features
     */
    setupResponsiveFeatures() {
        // Mobile Touch Optimizations
        if ('ontouchstart' in window) {
            document.querySelectorAll('.player-card').forEach(card => {
                card.style.cursor = 'pointer';

                // Touch feedback
                card.addEventListener('touchstart', (e) => {
                    card.style.backgroundColor = '#f0f0f0';
                });

                card.addEventListener('touchend', (e) => {
                    setTimeout(() => {
                        card.style.backgroundColor = '';
                    }, 150);
                });
            });
        }

        // Viewport-optimierte Layouts
        this.handleViewportChanges();
        window.addEventListener('resize', () => {
            this.handleViewportChanges();
        });
    }

    /**
     * Behandelt Viewport-Änderungen
     */
    handleViewportChanges() {
        const viewport = {
            width: window.innerWidth,
            height: window.innerHeight,
            isMobile: window.innerWidth < 768,
            isTablet: window.innerWidth >= 768 && window.innerWidth < 1024
        };

        // Mobile-spezifische Anpassungen
        if (viewport.isMobile) {
            this.enableMobileOptimizations();
        } else {
            this.disableMobileOptimizations();
        }

        if (this.config.debug_mode) {
            console.log('Viewport geändert:', viewport);
        }
    }

    /**
     * Mobile Optimierungen aktivieren
     */
    enableMobileOptimizations() {
        document.body.classList.add('mobile-layout');

        // Kompakte Spielerkarten auf Mobile
        document.querySelectorAll('.player-card').forEach(card => {
            card.classList.add('mobile-compact');
        });
    }

    /**
     * Mobile Optimierungen deaktivieren
     */
    disableMobileOptimizations() {
        document.body.classList.remove('mobile-layout');

        document.querySelectorAll('.player-card').forEach(card => {
            card.classList.remove('mobile-compact');
        });
    }

    /**
     * Statistik-Animationen
     */
    setupStatisticAnimations() {
        const statCards = document.querySelectorAll('.stat-card');

        // Intersection Observer für animate-on-scroll
        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        this.animateStatisticCard(entry.target);
                        observer.unobserve(entry.target);
                    }
                });
            }, {
                threshold: 0.1,
                rootMargin: '50px'
            });

            statCards.forEach(card => {
                observer.observe(card);
            });
        } else {
            // Fallback für ältere Browser
            statCards.forEach(card => {
                this.animateStatisticCard(card);
            });
        }
    }

    /**
     * Animiert eine Statistik-Karte
     */
    animateStatisticCard(card) {
        if (!this.config.animations.enabled) return;

        const statValue = card.querySelector('.stat-value');
        if (!statValue) return;

        const finalValue = statValue.textContent;
        const numericValue = parseFloat(finalValue.replace(/[^\d.-]/g, ''));

        if (isNaN(numericValue)) return;

        // Counter-Animation für Zahlen
        let currentValue = 0;
        const increment = numericValue / 30; // 30 Frames für Animation
        const duration = this.config.animations.duration;
        const frameTime = duration / 30;

        statValue.textContent = '0';

        const counter = setInterval(() => {
            currentValue += increment;

            if (currentValue >= numericValue) {
                currentValue = numericValue;
                clearInterval(counter);
            }

            // Formatierung beibehalten
            if (finalValue.includes('€') || finalValue.includes('M')) {
                statValue.textContent = this.formatCurrency(currentValue);
            } else if (finalValue.includes('%')) {
                statValue.textContent = Math.round(currentValue) + '%';
            } else {
                statValue.textContent = Math.round(currentValue);
            }
        }, frameTime);

        // Erscheinungs-Animation
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        card.style.transition = `opacity ${duration}ms ease-out, transform ${duration}ms ease-out`;

        requestAnimationFrame(() => {
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        });
    }

    /**
     * Formatiert Währungswerte
     */
    formatCurrency(value) {
        if (value >= 1000000) {
            return (value / 1000000).toFixed(1) + 'M €';
        } else if (value >= 1000) {
            return (value / 1000).toFixed(0) + 'K €';
        }
        return Math.round(value) + ' €';
    }

    /**
     * Scroll-to-Section Funktionalität
     */
    scrollToPosition(position) {
        const section = document.querySelector(`[data-position="${position}"]`);
        if (section) {
            section.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });

            // Kurze Highlight-Animation
            if (this.config.animations.enabled) {
                section.style.backgroundColor = '#e3f2fd';
                section.style.transition = 'background-color 2s ease-out';

                setTimeout(() => {
                    section.style.backgroundColor = '';
                }, 2000);
            }
        }
    }

    /**
     * Performance-Monitor (nur im Debug-Modus)
     */
    startPerformanceMonitoring() {
        if (!this.config.debug_mode || !window.performance) return;

        const observer = new PerformanceObserver((list) => {
            const entries = list.getEntries();
            entries.forEach(entry => {
                if (entry.duration > 100) { // Nur langsame Operationen loggen
                    console.warn('Langsame Operation:', entry.name, entry.duration + 'ms');
                }
            });
        });

        observer.observe({ entryTypes: ['measure'] });
    }

    /**
     * Utility: Smooth Scroll zu Element
     */
    smoothScrollTo(element, offset = 0) {
        const targetPosition = element.offsetTop - offset;

        window.scrollTo({
            top: targetPosition,
            behavior: 'smooth'
        });
    }

    /**
     * Public API
     */
    getViewportInfo() {
        return {
            width: window.innerWidth,
            height: window.innerHeight,
            isMobile: window.innerWidth < 768,
            isTablet: window.innerWidth >= 768 && window.innerWidth < 1024,
            isDesktop: window.innerWidth >= 1024
        };
    }

    // Erweiterte Spieler-Suche
    searchPlayers(query) {
        const playerCards = document.querySelectorAll('.player-card');
        const normalizedQuery = query.toLowerCase().trim();

        if (!normalizedQuery) {
            // Alle Spieler anzeigen wenn Suche leer
            playerCards.forEach(card => {
                card.style.display = 'block';
                card.classList.remove('search-hidden');
            });
            return;
        }

        let foundCount = 0;

        playerCards.forEach(card => {
            const playerName = card.querySelector('.player-name')?.textContent?.toLowerCase() || '';
            const playerNationality = card.dataset.nationality?.toLowerCase() || '';
            const playerPosition = card.dataset.position?.toLowerCase() || '';

            const matches = playerName.includes(normalizedQuery) ||
                playerNationality.includes(normalizedQuery) ||
                playerPosition.includes(normalizedQuery);

            if (matches) {
                card.style.display = 'block';
                card.classList.remove('search-hidden');
                foundCount++;
            } else {
                card.style.display = 'none';
                card.classList.add('search-hidden');
            }
        });

        if (this.config.debug_mode) {
            console.log(`Spieler-Suche "${query}": ${foundCount} Treffer`);
        }

        return foundCount;
    }

    /**
     * Debug-Hilfsmethoden
     */
    getDebugInfo() {
        return {
            config: this.config,
            viewport: this.getViewportInfo(),
            playerCards: document.querySelectorAll('.player-card').length,
            expandedCards: document.querySelectorAll('.player-card.expanded').length,
            animations: {
                enabled: this.config.animations.enabled,
                queue: this.animationQueue.length,
                isAnimating: this.isAnimating
            }
        };
    }

    // Performance-Hilfsmethoden
    measurePerformance(name, fn) {
        if (!this.config.debug_mode || !window.performance) {
            return fn();
        }

        const startMark = `${name}-start`;
        const endMark = `${name}-end`;
        const measureName = `${name}-duration`;

        performance.mark(startMark);
        const result = fn();
        performance.mark(endMark);
        performance.measure(measureName, startMark, endMark);

        const measure = performance.getEntriesByName(measureName)[0];
        console.log(`Performance ${name}:`, measure.duration.toFixed(2) + 'ms');

        return result;
    }
}

// Auto-Initialisierung
const teamOverviewUtils = new TeamOverviewUtils();

// Global verfügbar machen für Debug-Zwecke und externe Nutzung
if (window.js_config?.debug_mode) {
    window.TeamOverviewUtils = teamOverviewUtils;
}

// CSS für erweiterte Funktionen (wird dynamisch eingefügt)
const additionalStyles = `
    .extended-details {
        border-top: 1px solid #e1e5e9;
        margin-top: 15px;
        padding-top: 15px;
    }

    .extended-stats h5 {
        margin: 0 0 10px 0;
        color: #2c3e50;
        font-size: 0.9rem;
        font-weight: 600;
    }

    .extended-details .stat-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 8px;
        margin-bottom: 15px;
    }

    .extended-details .stat-item {
        padding: 6px;
        font-size: 0.8rem;
    }

    .action-buttons {
        display: flex;
        gap: 8px;
    }

    .btn-small {
        padding: 4px 8px;
        font-size: 0.8rem;
        border: 1px solid #ddd;
        background: white;
        border-radius: 4px;
        cursor: pointer;
        transition: background-color 0.2s;
    }

    .btn-small:hover {
        background: #f8f9fa;
    }

    .player-card.mobile-compact {
        padding: 15px;
    }

    .player-card.mobile-compact .stats-row {
        grid-template-columns: repeat(2, 1fr);
    }

    .search-hidden {
        display: none !important;
    }
`;

// Styles injizieren
if (!document.getElementById('team-overview-utils-styles')) {
    const styleSheet = document.createElement('style');
    styleSheet.id = 'team-overview-utils-styles';
    styleSheet.textContent = additionalStyles;
    document.head.appendChild(styleSheet);
}