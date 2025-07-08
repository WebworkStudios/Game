<?php


declare(strict_types=1);

namespace App\Actions;

use Framework\Core\Application;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Routing\Route;

#[Route(path: '/debug/database', methods: ['GET'])]
class DebugDatabaseAction
{
    public function __construct(
        private readonly Application $app
    )
    {
    }

    public function __invoke(Request $request): Response
    {
        $debug = [];

        try {
            // 1. Direkte PDO-Verbindung testen
            $pdo = new \PDO('mysql:host=localhost;dbname=kickerscup', 'root', '');
            $stmt = $pdo->query('SELECT COUNT(*) as count FROM users');
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            $debug['direct_pdo_count'] = $result['count'];

            // 2. Framework Connection testen
            $connectionManager = $this->app->getDatabase();
            $connection = $connectionManager->getConnection('default');
            $stmt2 = $connection->query('SELECT COUNT(*) as count FROM users');
            $result2 = $stmt2->fetch(\PDO::FETCH_ASSOC);
            $debug['framework_connection_count'] = $result2['count'];

            // 3. QueryBuilder Debug-Modus
            $qb = $this->app->query()->debug(true);
            $count = $qb->table('users')->count();
            $debug['querybuilder_count'] = $count;

            // 4. Raw SQL ausgeben
            $sql = $qb->table('users')->toSql();
            $debug['generated_sql'] = $sql;

            // 5. Alle User holen
            $users = $this->app->query()->table('users')->get();
            $debug['users_data'] = $users->all();
            $debug['users_query_info'] = $users->getQueryInfo();

        } catch (\Exception $e) {
            $debug['error'] = $e->getMessage();
            $debug['trace'] = $e->getTraceAsString();
        }

        return Response::json($debug);
    }
}