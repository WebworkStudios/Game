<?php

// Auto-generated route cache - DO NOT EDIT
// Generated at: 2025-07-13 13:59:15

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
    'pattern' => '#^/users/(?P<id>\\d+)$#',
    'methods' => 
    array (
      0 => 'GET',
    ),
    'action' => 'App\\Actions\\GetUserAction',
    'middlewares' => 
    array (
    ),
    'name' => 'user.show',
    'parameters' => 
    array (
      0 => 'id',
    ),
  ),
  2 => 
  array (
    'pattern' => '#^/api/users/(?P<id>\\d+)$#',
    'methods' => 
    array (
      0 => 'GET',
    ),
    'action' => 'App\\Actions\\GetUserAction',
    'middlewares' => 
    array (
    ),
    'name' => 'api.user.show',
    'parameters' => 
    array (
      0 => 'id',
    ),
  ),
  3 => 
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
  4 => 
  array (
    'pattern' => '#^/test/querybuilder$#',
    'methods' => 
    array (
      0 => 'GET',
    ),
    'action' => 'App\\Actions\\QueryBuilderTestAction',
    'middlewares' => 
    array (
    ),
    'name' => 'test.querybuilder',
    'parameters' => 
    array (
    ),
  ),
  5 => 
  array (
    'pattern' => '#^/security-demo$#',
    'methods' => 
    array (
      0 => 'GET',
      1 => 'POST',
    ),
    'action' => 'App\\Actions\\SecurityDemoAction',
    'middlewares' => 
    array (
    ),
    'name' => NULL,
    'parameters' => 
    array (
    ),
  ),
  6 => 
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
  7 => 
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
  8 => 
  array (
    'pattern' => '#^/test/template-cache$#',
    'methods' => 
    array (
      0 => 'GET',
      1 => 'POST',
    ),
    'action' => 'App\\Actions\\TemplateCacheTestAction',
    'middlewares' => 
    array (
    ),
    'name' => 'test.template.cache',
    'parameters' => 
    array (
    ),
  ),
  9 => 
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
  10 => 
  array (
    'pattern' => '#^/test/security$#',
    'methods' => 
    array (
      0 => 'GET',
      1 => 'POST',
    ),
    'action' => 'App\\Actions\\TestSecurityAction',
    'middlewares' => 
    array (
    ),
    'name' => 'test.security',
    'parameters' => 
    array (
    ),
  ),
  11 => 
  array (
    'pattern' => '#^/test/template-functions$#',
    'methods' => 
    array (
      0 => 'GET',
    ),
    'action' => 'App\\Actions\\TestTemplateFunctionsAction',
    'middlewares' => 
    array (
    ),
    'name' => 'test.template.functions',
    'parameters' => 
    array (
    ),
  ),
  12 => 
  array (
    'pattern' => '#^/test/validation$#',
    'methods' => 
    array (
      0 => 'GET',
      1 => 'POST',
    ),
    'action' => 'App\\Actions\\TestValidationAction',
    'middlewares' => 
    array (
    ),
    'name' => 'test.validation',
    'parameters' => 
    array (
    ),
  ),
);
