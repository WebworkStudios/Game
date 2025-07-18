<?php
// Auto-generated route cache file
// Generated: 2025-07-18 15:19:44

return array (
  0 => 
  array (
    'pattern' => '#^/test/filters$#',
    'methods' => 
    array (
      0 => 'GET',
    ),
    'action' => 'App\\Actions\\FilterDemoAction',
    'middlewares' => 
    array (
    ),
    'name' => 'test.filters',
    'parameters' => 
    array (
    ),
  ),
  1 => 
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
  2 => 
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
  3 => 
  array (
    'pattern' => '#^/team/overview$#',
    'methods' => 
    array (
      0 => 'GET',
    ),
    'action' => 'App\\Actions\\TeamOverviewAction',
    'middlewares' => 
    array (
    ),
    'name' => 'team.overview.full',
    'parameters' => 
    array (
    ),
  ),
  4 => 
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
