<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> - <?= htmlspecialchars($app_name) ?></title>
    <link rel="stylesheet" href="/css/main.css">
    <link rel="stylesheet" href="/css/legal.css">
</head>
<body>
    <div class="container-xs">
        <header class="legal-header">
            <nav class="breadcrumb">
                <a href="/"><?= htmlspecialchars($app_name) ?></a>
                <span class="separator">›</span>
                <span class="current"><?= htmlspecialchars($page_title) ?></span>
            </nav>
        </header>

        <main class="legal-content">
            <h1 class="legal-title"><?= htmlspecialchars($page_title) ?></h1>

            <section class="legal-section">
                <h2>Angaben gemäß § 5 TMG</h2>
                <div class="info-block">
                    <p class="company-name"><?= htmlspecialchars($company_name) ?></p>
                    <p class="address">
                        <?= htmlspecialchars($address['street']) ?><br>
                        <?= htmlspecialchars($address['postal_code']) ?> <?= htmlspecialchars($address['city']) ?><br>
                        <?= htmlspecialchars($address['country']) ?>
                    </p>
                </div>
            </section>

            <section class="legal-section">
                <h2>Kontakt</h2>
                <div class="contact-grid">
                    <div class="contact-item">
                        <span class="contact-label">Telefon:</span>
                        <a href="tel:<?= htmlspecialchars(str_replace([' ', '(', ')', '-'], '', $contact['phone'])) ?>" class="contact-value">
                            <?= htmlspecialchars($contact['phone']) ?>
                        </a>
                    </div>
                    <div class="contact-item">
                        <span class="contact-label">Telefax:</span>
                        <span class="contact-value"><?= htmlspecialchars($contact['fax']) ?></span>
                    </div>
                    <div class="contact-item">
                        <span class="contact-label">E-Mail:</span>
                        <a href="mailto:<?= htmlspecialchars($contact['email']) ?>" class="contact-value">
                            <?= htmlspecialchars($contact['email']) ?>
                        </a>
                    </div>
                </div>
            </section>

            <section class="legal-section">
                <h2>Registereintrag</h2>
                <div class="info-block">
                    <p><strong>Vertretungsberechtigter Geschäftsführer:</strong><br>
                    <?= htmlspecialchars($legal['managing_director']) ?></p>

                    <p><strong>Registergericht:</strong> <?= htmlspecialchars($legal['register_court']) ?><br>
                    <strong>Registernummer:</strong> <?= htmlspecialchars($legal['register_number']) ?></p>
                </div>
            </section>

            <section class="legal-section">
                <h2>Umsatzsteuer-Identifikationsnummer</h2>
                <div class="info-block">
                    <p>Umsatzsteuer-Identifikationsnummer gemäß § 27 a Umsatzsteuergesetz:<br>
                    <strong><?= htmlspecialchars($legal['vat_id']) ?></strong></p>
                </div>
            </section>

            <section class="legal-section">
                <h2>Verantwortlich für den Inhalt nach § 55 Abs. 2 RStV</h2>
                <div class="info-block">
                    <p><?= htmlspecialchars($responsible_content['name']) ?><br>
                    <?= htmlspecialchars($responsible_content['address']) ?></p>
                </div>
            </section>

            <section class="legal-section">
                <h2>Streitschlichtung</h2>
                <div class="info-block">
                    <p>Die Europäische Kommission stellt eine Plattform zur Online-Streitbeilegung (OS) bereit:
                    <a href="https://ec.europa.eu/consumers/odr/" target="_blank" rel="noopener noreferrer" class="external-link">
                        https://ec.europa.eu/consumers/odr/
                    </a></p>

                    <p>Unsere E-Mail-Adresse finden Sie oben im Impressum.</p>

                    <p>Wir sind nicht bereit oder verpflichtet, an Streitbeilegungsverfahren vor einer
                    Verbraucherschlichtungsstelle teilzunehmen.</p>
                </div>
            </section>

            <section class="legal-section">
                <h2>Haftung für Inhalte</h2>
                <div class="info-block">
                    <p>Als Diensteanbieter sind wir gemäß § 7 Abs.1 TMG für eigene Inhalte auf diesen Seiten nach den
                    allgemeinen Gesetzen verantwortlich. Nach §§ 8 bis 10 TMG sind wir als Diensteanbieter jedoch nicht
                    unter der Verpflichtung, übermittelte oder gespeicherte fremde Informationen zu überwachen oder nach
                    Umständen zu forschen, die auf eine rechtswidrige Tätigkeit hinweisen.</p>

                    <p>Verpflichtungen zur Entfernung oder Sperrung der Nutzung von Informationen nach den allgemeinen
                    Gesetzen bleiben hiervon unberührt. Eine diesbezügliche Haftung ist jedoch erst ab dem Zeitpunkt der
                    Kenntnis einer konkreten Rechtsverletzung möglich. Bei Bekanntwerden von entsprechenden
                    Rechtsverletzungen werden wir diese Inhalte umgehend entfernen.</p>
                </div>
            </section>

            <section class="legal-section">
                <h2>Haftung für Links</h2>
                <div class="info-block">
                    <p>Unser Angebot enthält Links zu externen Websites Dritter, auf deren Inhalte wir keinen Einfluss haben.
                    Deshalb können wir für diese fremden Inhalte auch keine Gewähr übernehmen. Für die Inhalte der verlinkten
                    Seiten ist stets der jeweilige Anbieter oder Betreiber der Seiten verantwortlich.</p>

                    <p>Die verlinkten Seiten wurden zum Zeitpunkt der Verlinkung auf mögliche Rechtsverstöße überprüft.
                    Rechtswidrige Inhalte waren zum Zeitpunkt der Verlinkung nicht erkennbar.</p>
                </div>
            </section>

            <section class="legal-section">
                <h2>Urheberrecht</h2>
                <div class="info-block">
                    <p>Die durch die Seitenbetreiber erstellten Inhalte und Werke auf diesen Seiten unterliegen dem
                    deutschen Urheberrecht. Die Vervielfältigung, Bearbeitung, Verbreitung und jede Art der Verwertung
                    außerhalb der Grenzen des Urheberrechtes bedürfen der schriftlichen Zustimmung des jeweiligen Autors
                    bzw. Erstellers.</p>

                    <p>Downloads und Kopien dieser Seite sind nur für den privaten, nicht kommerziellen Gebrauch gestattet.
                    Soweit die Inhalte auf dieser Seite nicht vom Betreiber erstellt wurden, werden die Urheberrechte
                    Dritter beachtet. Insbesondere werden Inhalte Dritter als solche gekennzeichnet.</p>
                </div>
            </section>
        </main>

        <footer class="legal-footer">
            <nav class="footer-nav">
                <a href="/" class="btn btn-primary">Zurück zur Startseite</a>
                <a href="/datenschutz" class="footer-link">Datenschutzerklärung</a>
                <a href="/agb" class="footer-link">AGB</a>
            </nav>
        </footer>
    </div>
</body>
</html>