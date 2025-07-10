<?php
declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Framework\Core\Application;

$app = new Application(__DIR__);

echo "🚀 Installing Framework...\n\n";

if ($app->install()) {
    echo "\n✅ Framework installed successfully!\n";
    echo "📁 Created configuration files:\n";
    echo "   - app/Config/app.php\n";
    echo "   - app/Config/database.php\n";
    echo "   - app/Config/security.php\n";
    echo "   - app/Config/templating.php\n";
    echo "\n🎯 Next steps:\n";
    echo "   1. Configure your database in app/Config/database.php\n";
    echo "   2. Start development server: composer serve\n";
} else {
    echo "\n❌ Installation failed!\n";
    exit(1);
}