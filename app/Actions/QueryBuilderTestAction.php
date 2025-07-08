<?php


declare(strict_types=1);

namespace App\Actions;

use Framework\Core\Application;
use Framework\Database\Enums\OrderDirection;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Routing\Route;

/**
 * QueryBuilder Test Action - Demonstriert alle Database-Features
 */
#[Route(path: '/test/querybuilder', methods: ['GET'], name: 'test.querybuilder')]
class QueryBuilderTestAction
{
    public function __construct(
        private readonly Application $app
    )
    {
    }

    public function __invoke(Request $request): Response
    {
        $testResults = [];
        $errors = [];

        try {
            // Test 1: Basic SELECT
            $testResults['basic_select'] = $this->testBasicSelect();

            // Test 2: WHERE Conditions
            $testResults['where_conditions'] = $this->testWhereConditions();

            // Test 3: Advanced WHERE
            $testResults['advanced_where'] = $this->testAdvancedWhere();

            // Test 4: Ordering & Limiting
            $testResults['ordering_limiting'] = $this->testOrderingLimiting();

            // Test 5: Aggregations
            $testResults['aggregations'] = $this->testAggregations();

            // Test 6: INSERT Operation
            $testResults['insert_operation'] = $this->testInsertOperation();

            // Test 7: UPDATE Operation
            $testResults['update_operation'] = $this->testUpdateOperation();

            // Test 8: DELETE Operation
            $testResults['delete_operation'] = $this->testDeleteOperation();

            // Test 9: Transaction Test
            $testResults['transaction_test'] = $this->testTransaction();

            // Test 10: Performance Test
            $testResults['performance_test'] = $this->testPerformance();

        } catch (\Exception $e) {
            $errors[] = "Test execution failed: " . $e->getMessage();
        }

        // TEMPORÄR: Immer HTML zurückgeben (entferne die JSON-Bedingung)
        return Response::ok($this->renderTestPage($testResults, $errors));

        // Diese Zeilen auskommentieren:
        /*
        if ($request->expectsJson()) {
            return Response::json([...]);
        }

        return Response::ok($this->renderTestPage($testResults, $errors));
        */
    }

    /**
     * Test 1: Basic SELECT Queries
     */
    private function testBasicSelect(): array
    {
        $results = [];

        // Alle Users
        $allUsers = $this->app->query()
            ->table('users')
            ->get();

        $results['all_users'] = [
            'query' => 'SELECT * FROM users',
            'count' => $allUsers->count(),
            'execution_time_ms' => round($allUsers->getExecutionTime() * 1000, 2),
            'sample_data' => $allUsers->first(),
        ];

        // Bestimmte Spalten
        $selectedColumns = $this->app->query()
            ->table('users')
            ->select('id', 'trainer_name', 'email', 'role')
            ->limit(3)
            ->get();

        $results['selected_columns'] = [
            'query' => 'SELECT id, trainer_name, email, role FROM users LIMIT 3',
            'data' => $selectedColumns->all(),
            'execution_time_ms' => round($selectedColumns->getExecutionTime() * 1000, 2),
        ];

        return $results;
    }

    /**
     * Test 2: WHERE Conditions
     */
    private function testWhereConditions(): array
    {
        $results = [];

        // Basic WHERE
        $admins = $this->app->query()
            ->table('users')
            ->where('role', 'admin')
            ->get();

        $results['admin_users'] = [
            'query' => "WHERE role = 'admin'",
            'count' => $admins->count(),
            'data' => $admins->all(),
        ];

        // Multiple WHERE
        $verifiedUsers = $this->app->query()
            ->table('users')
            ->where('is_email_verified', 1)
            ->where('newsletter_subscribed', 1)
            ->select('trainer_name', 'email')
            ->get();

        $results['verified_newsletter_users'] = [
            'query' => "WHERE is_email_verified = 1 AND newsletter_subscribed = 1",
            'count' => $verifiedUsers->count(),
            'data' => $verifiedUsers->all(),
        ];

        // WHERE with operators
        $richUsers = $this->app->query()
            ->table('users')
            ->where('action_dollars', '>', 1500.00)
            ->select('trainer_name', 'action_dollars')
            ->orderByDesc('action_dollars')
            ->get();

        $results['rich_users'] = [
            'query' => "WHERE action_dollars > 1500.00 ORDER BY action_dollars DESC",
            'count' => $richUsers->count(),
            'data' => $richUsers->all(),
        ];

        return $results;
    }

    /**
     * Test 3: Advanced WHERE Conditions
     */
    private function testAdvancedWhere(): array
    {
        $results = [];

        // WHERE IN
        $specificUsers = $this->app->query()
            ->table('users')
            ->whereIn('role', ['admin', 'user'])
            ->whereIn('id', [1, 2, 3, 4, 5])
            ->select('id', 'trainer_name', 'role')
            ->get();

        $results['where_in'] = [
            'query' => "WHERE role IN ('admin', 'user') AND id IN (1,2,3,4,5)",
            'count' => $specificUsers->count(),
            'data' => $specificUsers->all(),
        ];

        // WHERE NULL
        $usersWithoutToken = $this->app->query()
            ->table('users')
            ->whereNull('email_verification_token')
            ->select('id', 'trainer_name', 'email_verification_token')
            ->limit(5)
            ->get();

        $results['where_null'] = [
            'query' => "WHERE email_verification_token IS NULL LIMIT 5",
            'count' => $usersWithoutToken->count(),
            'data' => $usersWithoutToken->all(),
        ];

        // WHERE NOT NULL
        $usersWithToken = $this->app->query()
            ->table('users')
            ->whereNotNull('email_verification_token')
            ->select('id', 'trainer_name', 'email_verification_token')
            ->limit(3)
            ->get();

        $results['where_not_null'] = [
            'query' => "WHERE email_verification_token IS NOT NULL LIMIT 3",
            'count' => $usersWithToken->count(),
            'data' => $usersWithToken->all(),
        ];

        // WHERE BETWEEN
        $moderateUsers = $this->app->query()
            ->table('users')
            ->whereBetween('action_dollars', 800.00, 1200.00)
            ->select('trainer_name', 'action_dollars')
            ->orderBy('action_dollars')
            ->get();

        $results['where_between'] = [
            'query' => "WHERE action_dollars BETWEEN 800.00 AND 1200.00 ORDER BY action_dollars",
            'count' => $moderateUsers->count(),
            'data' => $moderateUsers->all(),
        ];

        return $results;
    }

    /**
     * Test 4: Ordering & Limiting
     */
    private function testOrderingLimiting(): array
    {
        $results = [];

        // ORDER BY ASC
        $oldestUsers = $this->app->query()
            ->table('users')
            ->select('id', 'trainer_name', 'created_at')
            ->orderBy('created_at', OrderDirection::ASC)
            ->limit(5)
            ->get();

        $results['oldest_users'] = [
            'query' => "ORDER BY created_at ASC LIMIT 5",
            'data' => $oldestUsers->all(),
        ];

        // ORDER BY DESC
        $newestUsers = $this->app->query()
            ->table('users')
            ->select('id', 'trainer_name', 'created_at')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $results['newest_users'] = [
            'query' => "ORDER BY created_at DESC LIMIT 5",
            'data' => $newestUsers->all(),
        ];

        // Multiple ORDER BY
        $sortedUsers = $this->app->query()
            ->table('users')
            ->select('trainer_name', 'role', 'action_dollars')
            ->orderBy('role')
            ->orderByDesc('action_dollars')
            ->limit(10)
            ->get();

        $results['multiple_order_by'] = [
            'query' => "ORDER BY role ASC, action_dollars DESC LIMIT 10",
            'data' => $sortedUsers->all(),
        ];

        // Pagination
        $paginatedUsers = $this->app->query()
            ->table('users')
            ->select('id', 'trainer_name', 'email')
            ->paginate(3, 2) // 3 per page, page 2
            ->get();

        $results['pagination'] = [
            'query' => "LIMIT 3 OFFSET 3 (Page 2, 3 per page)",
            'data' => $paginatedUsers->all(),
            'pagination_info' => $paginatedUsers->paginate(3, 2),
        ];

        return $results;
    }

    /**
     * Test 5: Aggregations
     */
    private function testAggregations(): array
    {
        $results = [];

        // COUNT
        $totalUsers = $this->app->query()
            ->table('users')
            ->count();

        $results['total_users'] = [
            'query' => "SELECT COUNT(*) FROM users",
            'result' => $totalUsers,
        ];

        // COUNT with WHERE
        $adminCount = $this->app->query()
            ->table('users')
            ->where('role', 'admin')
            ->count();

        $results['admin_count'] = [
            'query' => "SELECT COUNT(*) FROM users WHERE role = 'admin'",
            'result' => $adminCount,
        ];

        // Verified users count
        $verifiedCount = $this->app->query()
            ->table('users')
            ->where('is_email_verified', 1)
            ->count();

        $results['verified_count'] = [
            'query' => "SELECT COUNT(*) FROM users WHERE is_email_verified = 1",
            'result' => $verifiedCount,
        ];

        return $results;
    }

    /**
     * Test 6: INSERT Operation
     */
    private function testInsertOperation(): array
    {
        $results = [];

        try {
            // Single Insert
            $insertResult = $this->app->query()
                ->table('users')
                ->insert([
                    'trainer_name' => 'Test User ' . time(),
                    'email' => 'test' . time() . '@example.com',
                    'password_hash' => password_hash('testpassword', PASSWORD_DEFAULT),
                    'role' => 'user',
                    'action_dollars' => 1500.00,
                    'is_email_verified' => 0,
                    'newsletter_subscribed' => 1,
                ]);

            $results['single_insert'] = [
                'query' => "INSERT INTO users (...) VALUES (...)",
                'success' => $insertResult,
                'message' => $insertResult ? 'User inserted successfully' : 'Insert failed',
            ];

        } catch (\Exception $e) {
            $results['single_insert'] = [
                'query' => "INSERT INTO users (...) VALUES (...)",
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }

        return $results;
    }

    /**
     * Test 7: UPDATE Operation
     */
    private function testUpdateOperation(): array
    {
        $results = [];

        try {
            // Update specific user
            $updatedRows = $this->app->query()
                ->table('users')
                ->where('trainer_name', 'like', 'Test User%')
                ->update([
                    'action_dollars' => 2000.00,
                    'newsletter_subscribed' => 1,
                ]);

            $results['update_test_users'] = [
                'query' => "UPDATE users SET action_dollars = 2000.00 WHERE trainer_name LIKE 'Test User%'",
                'affected_rows' => $updatedRows,
                'message' => "Updated {$updatedRows} test users",
            ];

        } catch (\Exception $e) {
            $results['update_test_users'] = [
                'query' => "UPDATE users SET ... WHERE ...",
                'affected_rows' => 0,
                'error' => $e->getMessage(),
            ];
        }

        return $results;
    }

    /**
     * Test 8: DELETE Operation
     */
    private function testDeleteOperation(): array
    {
        $results = [];

        try {
            // Delete test users (safe deletion)
            $deletedRows = $this->app->query()
                ->table('users')
                ->where('trainer_name', 'like', 'Test User%')
                ->where('email', 'like', 'test%@example.com')
                ->delete();

            $results['delete_test_users'] = [
                'query' => "DELETE FROM users WHERE trainer_name LIKE 'Test User%' AND email LIKE 'test%@example.com'",
                'deleted_rows' => $deletedRows,
                'message' => "Deleted {$deletedRows} test users",
            ];

        } catch (\Exception $e) {
            $results['delete_test_users'] = [
                'query' => "DELETE FROM users WHERE ...",
                'deleted_rows' => 0,
                'error' => $e->getMessage(),
            ];
        }

        return $results;
    }

    /**
     * Test 9: Transaction Test
     */
    private function testTransaction(): array
    {
        $results = [];

        try {
            // Transaction Test
            $transactionResult = $this->app->transaction(function () {
                // Insert test user
                $this->app->query()->table('users')->insert([
                    'trainer_name' => 'Transaction Test User',
                    'email' => 'transaction@test.com',
                    'password_hash' => password_hash('test', PASSWORD_DEFAULT),
                    'role' => 'user',
                    'action_dollars' => 999.99,
                ]);

                // Immediately delete it
                return $this->app->query()
                    ->table('users')
                    ->where('email', 'transaction@test.com')
                    ->delete();
            });

            $results['transaction_success'] = [
                'query' => "BEGIN; INSERT ...; DELETE ...; COMMIT;",
                'success' => true,
                'deleted_rows' => $transactionResult,
                'message' => 'Transaction completed successfully',
            ];

        } catch (\Exception $e) {
            $results['transaction_failure'] = [
                'query' => "BEGIN; INSERT ...; DELETE ...; ROLLBACK;",
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Transaction rolled back due to error',
            ];
        }

        return $results;
    }

    /**
     * Test 10: Performance Test
     */
    private function testPerformance(): array
    {
        $results = [];

        // Simple query performance
        $startTime = microtime(true);
        $simpleQuery = $this->app->query()
            ->table('users')
            ->select('id', 'trainer_name')
            ->limit(100)
            ->get();
        $simpleTime = microtime(true) - $startTime;

        $results['simple_query'] = [
            'query' => "SELECT id, trainer_name FROM users LIMIT 100",
            'execution_time_ms' => round($simpleTime * 1000, 2),
            'query_builder_time_ms' => round($simpleQuery->getExecutionTime() * 1000, 2),
            'row_count' => $simpleQuery->count(),
        ];

        // Complex query performance
        $startTime = microtime(true);
        $complexQuery = $this->app->query()
            ->table('users')
            ->where('role', 'user')
            ->where('is_email_verified', 1)
            ->whereNotNull('email_verification_token')
            ->whereBetween('action_dollars', 500.00, 2000.00)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();
        $complexTime = microtime(true) - $startTime;

        $results['complex_query'] = [
            'query' => "Complex WHERE with multiple conditions + ORDER BY + LIMIT",
            'execution_time_ms' => round($complexTime * 1000, 2),
            'query_builder_time_ms' => round($complexQuery->getExecutionTime() * 1000, 2),
            'row_count' => $complexQuery->count(),
        ];

        return $results;
    }

    /**
     * Rendert Test-Seite
     */
    private function renderTestPage(array $results, array $errors): string
    {
        $errorHtml = '';
        if (!empty($errors)) {
            $errorList = implode('</li><li>', $errors);
            $errorHtml = "<div class='errors'><h3>Errors:</h3><ul><li>{$errorList}</li></ul></div>";
        }

        $resultsHtml = '';
        foreach ($results as $testName => $testData) {
            $resultsHtml .= "<div class='test-section'>";
            $resultsHtml .= "<h3>" . ucwords(str_replace('_', ' ', $testName)) . "</h3>";
            $resultsHtml .= "<pre>" . json_encode($testData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
            $resultsHtml .= "</div>";
        }

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <title>QueryBuilder Test Results</title>
            <meta charset='utf-8'>
            <style>
                body { font-family: monospace; margin: 20px; background: #f5f5f5; }
                .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; }
                .header { text-align: center; margin-bottom: 30px; }
                .test-section { margin-bottom: 30px; border: 1px solid #ddd; padding: 15px; border-radius: 5px; }
                .test-section h3 { margin-top: 0; color: #333; border-bottom: 2px solid #eee; padding-bottom: 10px; }
                pre { background: #f8f8f8; padding: 15px; border-radius: 5px; overflow-x: auto; line-height: 1.4; }
                .errors { background: #ffebee; border: 1px solid #f44336; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
                .errors h3 { color: #f44336; margin-top: 0; }
                .summary { background: #e8f5e8; border: 1px solid #4caf50; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🧪 QueryBuilder Test Results</h1>
                    <p>Framework Database Module Testing Suite</p>
                </div>
                
                <div class='summary'>
                    <strong>Test Summary:</strong> " . count($results) . " tests executed<br>
                    <strong>Timestamp:</strong> " . date('Y-m-d H:i:s') . "<br>
                    <strong>Status:</strong> " . (empty($errors) ? '✅ All tests completed' : '⚠️ Some tests had issues') . "
                </div>
                
                {$errorHtml}
                
                {$resultsHtml}
                
                <p><a href='/'>← Back to Home</a></p>
            </div>
        </body>
        </html>";
    }
}