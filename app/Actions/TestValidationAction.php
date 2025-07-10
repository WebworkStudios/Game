<?php

declare(strict_types=1);

namespace App\Actions;

use Framework\Core\Application;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Routing\Route;
use Framework\Validation\ValidatesRequest;

/**
 * Test Action for Validation Layer - Now with Framework Integration
 */
#[Route(path: '/test/validation', methods: ['GET', 'POST'], name: 'test.validation')]
class TestValidationAction
{
    use ValidatesRequest;

    public function __construct(
        private readonly Application $app
    ) {}

    public function __invoke(Request $request): Response
    {
        if ($request->isPost()) {
            return $this->handleValidation($request);
        }

        // GET: Show validation test form
        return Response::ok($this->renderTestForm());
    }

    /**
     * Handle form validation - Multiple examples
     */
    private function handleValidation(Request $request): Response
    {
        // Example 1: Using Application helper
        $validator = $this->app->validate($request->all(), [
            'name' => 'required|string|min:2|max:50',
            'email' => 'required|email|unique:users,email',
            'age' => 'numeric|min:18|max:99',
            'user_id' => 'exists:users,id',
            'role' => 'in:admin,user,moderator',
            'website' => 'nullable|url',
            'active' => 'boolean',
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return Response::json([
                'success' => false,
                'errors' => $validator->errors()->toArray(),
                'message' => 'Validation failed',
                'method' => 'Application helper'
            ], 422);
        }

        return Response::json([
            'success' => true,
            'validated' => $validator->validated(),
            'message' => 'Validation passed!',
            'method' => 'Application helper'
        ]);
    }

    /**
     * Alternative: Using trait method
     */
    private function handleValidationWithTrait(Request $request): Response
    {
        // Example 2: Using ValidatesRequests trait
        $result = $this->validateWithResponse($request, [
            'name' => 'required|string|min:2|max:50',
            'email' => 'required|email',
            'age' => 'numeric|min:18',
        ]);

        // If validation failed, $result is a Response
        if ($result instanceof Response) {
            return $result;
        }

        // If validation passed, $result is a Validator
        return Response::json([
            'success' => true,
            'validated' => $result->validated(),
            'message' => 'Validation passed with trait!',
            'method' => 'ValidatesRequests trait'
        ]);
    }

    /**
     * Alternative: Using validateOrFail
     */
    private function handleValidationOrFail(Request $request): Response
    {
        try {
            // Example 3: validateOrFail throws exception on failure
            $validated = $this->app->validateOrFail($request->all(), [
                'name' => 'required|string|min:2|max:50',
                'email' => 'required|email',
            ]);

            return Response::json([
                'success' => true,
                'validated' => $validated,
                'message' => 'Validation passed with validateOrFail!',
                'method' => 'validateOrFail'
            ]);

        } catch (\Framework\Validation\ValidationFailedException $e) {
            return Response::json([
                'success' => false,
                'errors' => $e->getErrorsArray(),
                'message' => $e->getMessage(),
                'method' => 'validateOrFail (exception caught)'
            ], $e->getCode());
        }
    }

    /**
     * Render enhanced test form
     */
    private function renderTestForm(): string
    {
        return "
        <!DOCTYPE html>
        <html lang=de>
        <head>
            <title>Validation Test - Framework Integration</title>
            <meta charset='utf-8'>
            <meta name='csrf-token' content='" . $this->app->getCsrf()->getToken() . "'>
            <style>
                body { font-family: Arial, sans-serif; margin: 40px; max-width: 900px; }
                .form-group { margin: 15px 0; }
                label { display: block; margin-bottom: 5px; font-weight: bold; }
                input[type='text'], input[type='email'], input[type='number'], input[type='password'], input[type='url'], select {
                    width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 5px;
                }
                button { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; margin: 5px; }
                button:hover { background: #0056b3; }
                .result { margin: 20px 0; padding: 15px; border-radius: 4px; }
                .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
                .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
                .info { background: #e2e3e5; color: #383d41; border: 1px solid #d6d8db; }
                pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; font-size: 12px; }
                .rule-examples { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin: 20px 0; }
                .rule-box { background: #f8f9fa; padding: 15px; border-radius: 5px; border-left: 4px solid #007bff; }
            </style>
        </head>
        <body>
            <h1>🔍 Validation Test - Framework Integration</h1>
            
            <form id='validation-form'>
                <div class='form-group'>
                    <label>Name (required, string, min:2, max:50):</label>
                    <input type='text' name='name' placeholder='Enter your name'>
                </div>
                
                <div class='form-group'>
                    <label>Email (required, email, unique in users table):</label>
                    <input type='email' name='email' placeholder='Enter your email'>
                </div>
                
                <div class='form-group'>
                    <label>Age (numeric, min:18, max:99):</label>
                    <input type='number' name='age' placeholder='Enter your age'>
                </div>
                
                <div class='form-group'>
                    <label>User ID (must exist in users table):</label>
                    <input type='number' name='user_id' placeholder='Enter existing user ID'>
                </div>
                
                <div class='form-group'>
                    <label>Role (in: admin,user,moderator):</label>
                    <select name='role'>
                        <option value=''>Select role...</option>
                        <option value='admin'>Admin</option>
                        <option value='user'>User</option>
                        <option value='moderator'>Moderator</option>
                        <option value='invalid'>Invalid Role</option>
                    </select>
                </div>
                
                <div class='form-group'>
                    <label>Website (nullable, url):</label>
                    <input type='url' name='website' placeholder='https://example.com'>
                </div>
                
                <div class='form-group'>
                    <label>Active (boolean):</label>
                    <select name='active'>
                        <option value=''>Select...</option>
                        <option value='1'>Yes</option>
                        <option value='0'>No</option>
                        <option value='true'>True</option>
                        <option value='false'>False</option>
                    </select>
                </div>
                
                <div class='form-group'>
                    <label>Password (nullable, min:8, confirmed):</label>
                    <input type='password' name='password' placeholder='Enter password'>
                </div>
                
                <div class='form-group'>
                    <label>Password Confirmation:</label>
                    <input type='password' name='password_confirmation' placeholder='Confirm password'>
                </div>
                
                <button type='submit'>Test Validation</button>
            </form>
            
            <div id='result'></div>
            
            <div class='rule-examples'>
                <div class='rule-box'>
                    <h3>📋 All Available Rules</h3>
                    <strong>Basic:</strong> required, nullable, string, numeric, integer, boolean, email, url<br>
                    <strong>Size:</strong> min:x, max:x<br>
                    <strong>Lists:</strong> in:a,b,c<br>
                    <strong>Text:</strong> alpha, alpha_num, regex:/pattern/<br>
                    <strong>Database:</strong> unique:table,col, exists:table,col<br>
                    <strong>Special:</strong> confirmed, date
                </div>
                
                <div class='rule-box'>
                    <h3>🚀 Integration Methods</h3>
                    <strong>Application Helper:</strong><br>
                    <code>\$app->validate(\$data, \$rules)</code><br><br>
                    <strong>ValidatesRequests Trait:</strong><br>
                    <code>\$this->validate(\$request, \$rules)</code><br><br>
                    <strong>Exception Style:</strong><br>
                    <code>\$app->validateOrFail(\$data, \$rules)</code>
                </div>
            </div>

            <script>
                const form = document.getElementById('validation-form');
                const result = document.getElementById('result');
                const csrfToken = document.querySelector('meta[name=\"csrf-token\"]').getAttribute('content');

                form.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    
                    const formData = new FormData(form);
                    const data = Object.fromEntries(formData.entries());
                    data._token = csrfToken;

                    try {
                        const response = await fetch('/test/validation', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken
                            },
                            body: JSON.stringify(data)
                        });

                        const json = await response.json();
                        
                        if (json.success) {
                            result.innerHTML = `
                                <div class='result success'>
                                    <h3>✅ Validation Passed!</h3>
                                    <p><strong>Method:</strong> \${json.method}</p>
                                    <p><strong>Message:</strong> \${json.message}</p>
                                    <p><strong>Validated Data:</strong></p>
                                    <pre>\${JSON.stringify(json.validated, null, 2)}</pre>
                                </div>
                            `;
                        } else {
                            const errorList = Object.entries(json.errors)
                                .map(([field, messages]) => 
                                    `<li><strong>\${field}:</strong> \${messages.join(', ')}</li>`
                                ).join('');
                            
                            result.innerHTML = `
                                <div class='result error'>
                                    <h3>❌ Validation Failed</h3>
                                    <p><strong>Method:</strong> \${json.method}</p>
                                    <p><strong>Message:</strong> \${json.message}</p>
                                    <ul>\${errorList}</ul>
                                </div>
                            `;
                        }
                    } catch (error) {
                        result.innerHTML = `
                            <div class='result error'>
                                <h3>💥 Request Failed</h3>
                                <p>Error: \${error.message}</p>
                            </div>
                        `;
                    }
                });
            </script>
        </body>
        </html>";
    }
}