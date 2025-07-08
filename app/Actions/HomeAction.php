<?php


declare(strict_types=1);

namespace App\Actions;

use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Routing\Route;

/**
 * Home-Action für Startseite
 */
#[Route(path: '/', methods: ['GET'], name: 'home')]
#[Route(path: '/welcome', methods: ['GET'], name: 'welcome')]
class HomeAction
{
    public function __invoke(Request $request): Response
    {
        $html = $this->renderWelcomePage();
        return Response::ok($html);
    }

    private function renderWelcomePage(): string
    {
        return "
        <!DOCTYPE html>
        <html lang=de>
        <head>
            <title>Welcome to Custom Framework</title>
            <meta charset='utf-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1'>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { 
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                .container {
                    text-align: center;
                    max-width: 600px;
                    padding: 2rem;
                }
                .logo {
                    font-size: 3rem;
                    font-weight: bold;
                    margin-bottom: 1rem;
                    text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
                }
                .subtitle {
                    font-size: 1.2rem;
                    margin-bottom: 2rem;
                    opacity: 0.9;
                }
                .features {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                    gap: 1rem;
                    margin-top: 2rem;
                }
                .feature {
                    background: rgba(255,255,255,0.1);
                    padding: 1.5rem;
                    border-radius: 10px;
                    backdrop-filter: blur(10px);
                }
                .feature h3 {
                    margin-bottom: 0.5rem;
                }
                .links {
                    margin-top: 2rem;
                }
                .links a {
                    color: white;
                    text-decoration: none;
                    background: rgba(255,255,255,0.2);
                    padding: 0.8rem 1.5rem;
                    border-radius: 25px;
                    margin: 0 0.5rem;
                    display: inline-block;
                    transition: background 0.3s;
                }
                .links a:hover {
                    background: rgba(255,255,255,0.3);
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='logo'>🚀 Custom Framework</div>
                <div class='subtitle'>Modern PHP Framework mit Attribute-basiertem Routing</div>
                
                <div class='features'>
                    <div class='feature'>
                        <h3>⚡ Performance</h3>
                        <p>Route-Caching und Lazy Loading für maximale Geschwindigkeit</p>
                    </div>
                    <div class='feature'>
                        <h3>🎯 Modern</h3>
                        <p>PHP 8.4 Features, Attributes und strikte Typisierung</p>
                    </div>
                    <div class='feature'>
                        <h3>🔧 Flexible</h3>
                        <p>Dependency Injection und Middleware-Support</p>
                    </div>
                </div>
                
                <div class='links'>
                    <a href='/users/123'>User Profile</a>
                    <a href='/api/users/123'>API Demo</a>
                </div>
            </div>
        </body>
        </html>";
    }
}