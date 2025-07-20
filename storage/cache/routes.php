<?php

declare(strict_types=1);

return array (
  0 => 
  array (
    'pattern' => '#^/$#',
    'methods' => 
    array (
      0 => 'GET',
    ),
    'action' => 'App\\Actions\\HomeAction',
    'middlewares' => 
    array (
    ),
    'name' => 'home',
    'parameters' => 
    array (
    ),
  ),
  1 => 
  array (
    'pattern' => '#^/team$#',
    'methods' => 
    array (
      0 => 'GET',
    ),
    'action' => 'App\\Actions\\TeamOverviewAction',
    'middlewares' => 
    array (
    ),
    'name' => 'team.overview',
    'parameters' => 
    array (
    ),
  ),
  2 => 
  array (
    'pattern' => '#^/test/templates$#',
    'methods' => 
    array (
      0 => 'GET',
    ),
    'action' => 'App\\Actions\\TemplateTestAction',
    'middlewares' => 
    array (
    ),
    'name' => 'test.templates',
    'parameters' => 
    array (
    ),
  ),
);
