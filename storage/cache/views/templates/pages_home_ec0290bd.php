<?php

// Template cache for: pages/home
// Generated at: 2025-07-13 17:41:31
// DO NOT EDIT - This file is auto-generated

return array (
  'version' => '1.0',
  'template' => 'pages/home',
  'template_path' => 'E:\\xampp\\htdocs\\kickerscup\\public\\..\\app\\Views\\pages\\home.html',
  'compiled_at' => 1752428491,
  'dependencies' => 
  array (
    'E:\\xampp\\htdocs\\kickerscup\\public\\..\\app\\Views\\pages\\home.html' => 1752349682,
  ),
  'compiled' => 
  array (
    'tokens' => 
    array (
      0 => 
      array (
        'type' => 'text',
        'content' => '<!-- app/Views/pages/home.html - Vollständige Demo -->
',
      ),
      1 => 
      array (
        'type' => 'extends',
        'template' => 'layouts/base',
      ),
      2 => 
      array (
        'type' => 'text',
        'content' => '

',
      ),
      3 => 
      array (
        'type' => 'block',
        'name' => 'title',
      ),
      4 => 
      array (
        'type' => 'variable',
        'variable_data' => 
        array (
          'type' => 'simple',
          'name' => 'welcome_message',
        ),
      ),
      5 => 
      array (
        'type' => 'text',
        'content' => ' - ',
      ),
      6 => 
      array (
        'type' => 'variable',
        'variable_data' => 
        array (
          'type' => 'simple',
          'name' => 'app_name',
        ),
      ),
      7 => 
      array (
        'type' => 'endblock',
      ),
      8 => 
      array (
        'type' => 'text',
        'content' => '

',
      ),
      9 => 
      array (
        'type' => 'block',
        'name' => 'content',
      ),
      10 => 
      array (
        'type' => 'text',
        'content' => '
<h1>🚀 ',
      ),
      11 => 
      array (
        'type' => 'variable',
        'variable_data' => 
        array (
          'type' => 'simple',
          'name' => 'welcome_message',
        ),
      ),
      12 => 
      array (
        'type' => 'text',
        'content' => '</h1>

',
      ),
      13 => 
      array (
        'type' => 'if',
        'condition' => 'user',
      ),
      14 => 
      array (
        'type' => 'text',
        'content' => '
<div style="background: #e8f5e8; padding: 15px; border-radius: 5px; margin: 20px 0;">
    <strong>Welcome ',
      ),
      15 => 
      array (
        'type' => 'variable',
        'variable_data' => 
        array (
          'type' => 'simple',
          'name' => 'user.name',
        ),
      ),
      16 => 
      array (
        'type' => 'text',
        'content' => '!</strong> (',
      ),
      17 => 
      array (
        'type' => 'variable',
        'variable_data' => 
        array (
          'type' => 'simple',
          'name' => 'user.email',
        ),
      ),
      18 => 
      array (
        'type' => 'text',
        'content' => ')
    ',
      ),
      19 => 
      array (
        'type' => 'if',
        'condition' => 'user.team',
      ),
      20 => 
      array (
        'type' => 'text',
        'content' => '
    <br>You are managing <strong>',
      ),
      21 => 
      array (
        'type' => 'variable',
        'variable_data' => 
        array (
          'type' => 'simple',
          'name' => 'user.team.name',
        ),
      ),
      22 => 
      array (
        'type' => 'text',
        'content' => '</strong> in ',
      ),
      23 => 
      array (
        'type' => 'variable',
        'variable_data' => 
        array (
          'type' => 'simple',
          'name' => 'user.team.league',
        ),
      ),
      24 => 
      array (
        'type' => 'text',
        'content' => '.
    ',
      ),
      25 => 
      array (
        'type' => 'endif',
      ),
      26 => 
      array (
        'type' => 'text',
        'content' => '
    ',
      ),
      27 => 
      array (
        'type' => 'if',
        'condition' => 'user.isAdmin',
      ),
      28 => 
      array (
        'type' => 'text',
        'content' => '
    <br><em>You have admin privileges</em>
    ',
      ),
      29 => 
      array (
        'type' => 'endif',
      ),
      30 => 
      array (
        'type' => 'text',
        'content' => '
</div>
',
      ),
      31 => 
      array (
        'type' => 'endif',
      ),
      32 => 
      array (
        'type' => 'text',
        'content' => '

<div style="background: #f0f8ff; padding: 20px; border-radius: 10px; margin: 20px 0;">
    <h3>🎨 Template Engine Features Demo</h3>
    <ul style="list-style: none; padding-left: 0;">
        <li>✅ <strong>Variables:</strong> ',
      ),
      33 => 
      array (
        'type' => 'variable',
        'variable_data' => 
        array (
          'type' => 'simple',
          'name' => 'user.name',
        ),
      ),
      34 => 
      array (
        'type' => 'text',
        'content' => ', ',
      ),
      35 => 
      array (
        'type' => 'variable',
        'variable_data' => 
        array (
          'type' => 'simple',
          'name' => 'app_name',
        ),
      ),
      36 => 
      array (
        'type' => 'text',
        'content' => '</li>
        <li>✅ <strong>Nested Variables:</strong> ',
      ),
      37 => 
      array (
        'type' => 'variable',
        'variable_data' => 
        array (
          'type' => 'simple',
          'name' => 'user.team.name',
        ),
      ),
      38 => 
      array (
        'type' => 'text',
        'content' => ' in ',
      ),
      39 => 
      array (
        'type' => 'variable',
        'variable_data' => 
        array (
          'type' => 'simple',
          'name' => 'user.team.league',
        ),
      ),
      40 => 
      array (
        'type' => 'text',
        'content' => '</li>
        <li>✅ <strong>Conditionals:</strong> ',
      ),
      41 => 
      array (
        'type' => 'if',
        'condition' => 'user.isAdmin',
      ),
      42 => 
      array (
        'type' => 'text',
        'content' => 'Admin privileges shown',
      ),
      43 => 
      array (
        'type' => 'endif',
      ),
      44 => 
      array (
        'type' => 'text',
        'content' => '</li>
        <li>✅ <strong>Loops:</strong> ',
      ),
      45 => 
      array (
        'type' => 'variable',
        'variable_data' => 
        array (
          'type' => 'simple',
          'name' => 'features',
        ),
        'filters' => 
        array (
          0 => 
          array (
            'name' => 'length',
            'parameters' => 
            array (
            ),
          ),
        ),
      ),
      46 => 
      array (
        'type' => 'text',
        'content' => ' features rendered below</li>
        <li>✅ <strong>Inheritance:</strong> This page extends layouts/base.html</li>
        <li>✅ <strong>Blocks:</strong> title and content blocks override parent</li>
    </ul>
</div>

<h2>🎯 Framework Features</h2>
',
      ),
      47 => 
      array (
        'type' => 'for',
        'expression' => 'feature in features',
      ),
      48 => 
      array (
        'type' => 'text',
        'content' => '
<div style="margin: 15px 0; padding: 20px; background: ',
      ),
      49 => 
      array (
        'type' => 'if',
        'condition' => 'feature.active',
      ),
      50 => 
      array (
        'type' => 'text',
        'content' => '#f0f8f0',
      ),
      51 => 
      array (
        'type' => 'else',
      ),
      52 => 
      array (
        'type' => 'text',
        'content' => '#f8f8f8',
      ),
      53 => 
      array (
        'type' => 'endif',
      ),
      54 => 
      array (
        'type' => 'text',
        'content' => '; border-radius: 8px; border-left: 4px solid ',
      ),
      55 => 
      array (
        'type' => 'if',
        'condition' => 'feature.active',
      ),
      56 => 
      array (
        'type' => 'text',
        'content' => '#28a745',
      ),
      57 => 
      array (
        'type' => 'else',
      ),
      58 => 
      array (
        'type' => 'text',
        'content' => '#6c757d',
      ),
      59 => 
      array (
        'type' => 'endif',
      ),
      60 => 
      array (
        'type' => 'text',
        'content' => ';">
    <h3>',
      ),
      61 => 
      array (
        'type' => 'variable',
        'variable_data' => 
        array (
          'type' => 'simple',
          'name' => 'feature.icon',
        ),
      ),
      62 => 
      array (
        'type' => 'text',
        'content' => ' ',
      ),
      63 => 
      array (
        'type' => 'variable',
        'variable_data' => 
        array (
          'type' => 'simple',
          'name' => 'feature.title',
        ),
      ),
      64 => 
      array (
        'type' => 'text',
        'content' => '</h3>
    <p>',
      ),
      65 => 
      array (
        'type' => 'variable',
        'variable_data' => 
        array (
          'type' => 'simple',
          'name' => 'feature.description',
        ),
      ),
      66 => 
      array (
        'type' => 'text',
        'content' => '</p>
    ',
      ),
      67 => 
      array (
        'type' => 'if',
        'condition' => 'feature.active',
      ),
      68 => 
      array (
        'type' => 'text',
        'content' => '
    <small style="color: #28a745; font-weight: bold;">✅ Active</small>
    ',
      ),
      69 => 
      array (
        'type' => 'else',
      ),
      70 => 
      array (
        'type' => 'text',
        'content' => '
    <small style="color: #6c757d;">⏳ Coming Soon</small>
    ',
      ),
      71 => 
      array (
        'type' => 'endif',
      ),
      72 => 
      array (
        'type' => 'text',
        'content' => '
</div>
',
      ),
      73 => 
      array (
        'type' => 'endfor',
      ),
      74 => 
      array (
        'type' => 'text',
        'content' => '
',
      ),
      75 => 
      array (
        'type' => 'endblock',
      ),
    ),
    'blocks' => 
    array (
      'title' => 
      array (
        0 => 
        array (
          'type' => 'variable',
          'variable_data' => 
          array (
            'type' => 'simple',
            'name' => 'welcome_message',
          ),
        ),
        1 => 
        array (
          'type' => 'text',
          'content' => ' - ',
        ),
        2 => 
        array (
          'type' => 'variable',
          'variable_data' => 
          array (
            'type' => 'simple',
            'name' => 'app_name',
          ),
        ),
      ),
      'content' => 
      array (
        0 => 
        array (
          'type' => 'text',
          'content' => '
<h1>🚀 ',
        ),
        1 => 
        array (
          'type' => 'variable',
          'variable_data' => 
          array (
            'type' => 'simple',
            'name' => 'welcome_message',
          ),
        ),
        2 => 
        array (
          'type' => 'text',
          'content' => '</h1>

',
        ),
        3 => 
        array (
          'type' => 'if',
          'condition' => 'user',
        ),
        4 => 
        array (
          'type' => 'text',
          'content' => '
<div style="background: #e8f5e8; padding: 15px; border-radius: 5px; margin: 20px 0;">
    <strong>Welcome ',
        ),
        5 => 
        array (
          'type' => 'variable',
          'variable_data' => 
          array (
            'type' => 'simple',
            'name' => 'user.name',
          ),
        ),
        6 => 
        array (
          'type' => 'text',
          'content' => '!</strong> (',
        ),
        7 => 
        array (
          'type' => 'variable',
          'variable_data' => 
          array (
            'type' => 'simple',
            'name' => 'user.email',
          ),
        ),
        8 => 
        array (
          'type' => 'text',
          'content' => ')
    ',
        ),
        9 => 
        array (
          'type' => 'if',
          'condition' => 'user.team',
        ),
        10 => 
        array (
          'type' => 'text',
          'content' => '
    <br>You are managing <strong>',
        ),
        11 => 
        array (
          'type' => 'variable',
          'variable_data' => 
          array (
            'type' => 'simple',
            'name' => 'user.team.name',
          ),
        ),
        12 => 
        array (
          'type' => 'text',
          'content' => '</strong> in ',
        ),
        13 => 
        array (
          'type' => 'variable',
          'variable_data' => 
          array (
            'type' => 'simple',
            'name' => 'user.team.league',
          ),
        ),
        14 => 
        array (
          'type' => 'text',
          'content' => '.
    ',
        ),
        15 => 
        array (
          'type' => 'endif',
        ),
        16 => 
        array (
          'type' => 'text',
          'content' => '
    ',
        ),
        17 => 
        array (
          'type' => 'if',
          'condition' => 'user.isAdmin',
        ),
        18 => 
        array (
          'type' => 'text',
          'content' => '
    <br><em>You have admin privileges</em>
    ',
        ),
        19 => 
        array (
          'type' => 'endif',
        ),
        20 => 
        array (
          'type' => 'text',
          'content' => '
</div>
',
        ),
        21 => 
        array (
          'type' => 'endif',
        ),
        22 => 
        array (
          'type' => 'text',
          'content' => '

<div style="background: #f0f8ff; padding: 20px; border-radius: 10px; margin: 20px 0;">
    <h3>🎨 Template Engine Features Demo</h3>
    <ul style="list-style: none; padding-left: 0;">
        <li>✅ <strong>Variables:</strong> ',
        ),
        23 => 
        array (
          'type' => 'variable',
          'variable_data' => 
          array (
            'type' => 'simple',
            'name' => 'user.name',
          ),
        ),
        24 => 
        array (
          'type' => 'text',
          'content' => ', ',
        ),
        25 => 
        array (
          'type' => 'variable',
          'variable_data' => 
          array (
            'type' => 'simple',
            'name' => 'app_name',
          ),
        ),
        26 => 
        array (
          'type' => 'text',
          'content' => '</li>
        <li>✅ <strong>Nested Variables:</strong> ',
        ),
        27 => 
        array (
          'type' => 'variable',
          'variable_data' => 
          array (
            'type' => 'simple',
            'name' => 'user.team.name',
          ),
        ),
        28 => 
        array (
          'type' => 'text',
          'content' => ' in ',
        ),
        29 => 
        array (
          'type' => 'variable',
          'variable_data' => 
          array (
            'type' => 'simple',
            'name' => 'user.team.league',
          ),
        ),
        30 => 
        array (
          'type' => 'text',
          'content' => '</li>
        <li>✅ <strong>Conditionals:</strong> ',
        ),
        31 => 
        array (
          'type' => 'if',
          'condition' => 'user.isAdmin',
        ),
        32 => 
        array (
          'type' => 'text',
          'content' => 'Admin privileges shown',
        ),
        33 => 
        array (
          'type' => 'endif',
        ),
        34 => 
        array (
          'type' => 'text',
          'content' => '</li>
        <li>✅ <strong>Loops:</strong> ',
        ),
        35 => 
        array (
          'type' => 'variable',
          'variable_data' => 
          array (
            'type' => 'simple',
            'name' => 'features',
          ),
          'filters' => 
          array (
            0 => 
            array (
              'name' => 'length',
              'parameters' => 
              array (
              ),
            ),
          ),
        ),
        36 => 
        array (
          'type' => 'text',
          'content' => ' features rendered below</li>
        <li>✅ <strong>Inheritance:</strong> This page extends layouts/base.html</li>
        <li>✅ <strong>Blocks:</strong> title and content blocks override parent</li>
    </ul>
</div>

<h2>🎯 Framework Features</h2>
',
        ),
        37 => 
        array (
          'type' => 'for',
          'expression' => 'feature in features',
        ),
        38 => 
        array (
          'type' => 'text',
          'content' => '
<div style="margin: 15px 0; padding: 20px; background: ',
        ),
        39 => 
        array (
          'type' => 'if',
          'condition' => 'feature.active',
        ),
        40 => 
        array (
          'type' => 'text',
          'content' => '#f0f8f0',
        ),
        41 => 
        array (
          'type' => 'else',
        ),
        42 => 
        array (
          'type' => 'text',
          'content' => '#f8f8f8',
        ),
        43 => 
        array (
          'type' => 'endif',
        ),
        44 => 
        array (
          'type' => 'text',
          'content' => '; border-radius: 8px; border-left: 4px solid ',
        ),
        45 => 
        array (
          'type' => 'if',
          'condition' => 'feature.active',
        ),
        46 => 
        array (
          'type' => 'text',
          'content' => '#28a745',
        ),
        47 => 
        array (
          'type' => 'else',
        ),
        48 => 
        array (
          'type' => 'text',
          'content' => '#6c757d',
        ),
        49 => 
        array (
          'type' => 'endif',
        ),
        50 => 
        array (
          'type' => 'text',
          'content' => ';">
    <h3>',
        ),
        51 => 
        array (
          'type' => 'variable',
          'variable_data' => 
          array (
            'type' => 'simple',
            'name' => 'feature.icon',
          ),
        ),
        52 => 
        array (
          'type' => 'text',
          'content' => ' ',
        ),
        53 => 
        array (
          'type' => 'variable',
          'variable_data' => 
          array (
            'type' => 'simple',
            'name' => 'feature.title',
          ),
        ),
        54 => 
        array (
          'type' => 'text',
          'content' => '</h3>
    <p>',
        ),
        55 => 
        array (
          'type' => 'variable',
          'variable_data' => 
          array (
            'type' => 'simple',
            'name' => 'feature.description',
          ),
        ),
        56 => 
        array (
          'type' => 'text',
          'content' => '</p>
    ',
        ),
        57 => 
        array (
          'type' => 'if',
          'condition' => 'feature.active',
        ),
        58 => 
        array (
          'type' => 'text',
          'content' => '
    <small style="color: #28a745; font-weight: bold;">✅ Active</small>
    ',
        ),
        59 => 
        array (
          'type' => 'else',
        ),
        60 => 
        array (
          'type' => 'text',
          'content' => '
    <small style="color: #6c757d;">⏳ Coming Soon</small>
    ',
        ),
        61 => 
        array (
          'type' => 'endif',
        ),
        62 => 
        array (
          'type' => 'text',
          'content' => '
</div>
',
        ),
        63 => 
        array (
          'type' => 'endfor',
        ),
        64 => 
        array (
          'type' => 'text',
          'content' => '
',
        ),
      ),
    ),
    'parent_template' => 'layouts/base',
    'template_path' => 'E:\\xampp\\htdocs\\kickerscup\\public\\..\\app\\Views\\pages\\home.html',
    'dependencies' => 
    array (
      0 => 'E:\\xampp\\htdocs\\kickerscup\\public\\..\\app\\Views\\pages\\home.html',
    ),
  ),
  'stats' => 
  array (
    'tokens' => 76,
    'blocks' => 2,
    'memory_usage' => 1875424,
  ),
);
