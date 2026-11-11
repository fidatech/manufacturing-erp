<?php

/*
|--------------------------------------------------------------------------
| Sidebar menu
|--------------------------------------------------------------------------
|
| One entry per line of the sidebar. Labels are translation keys (lang/en/app.php).
|
|   ['heading' => 'app.menu.main']
|   ['label' => 'app.menu.dashboard', 'icon' => 'gauge-high', 'route' => 'dashboard']
|   ['label' => '...', 'icon' => '...', 'children' => [
|       ['label' => '...', 'route' => 'items.index', 'active' => 'items.*'],
|   ]]
|
| "icon" is a Font Awesome solid icon name. "active" is the route-name pattern that
| highlights the entry (defaults to the route itself). After adding an icon run:
| php tools/build-assets.php
|
*/

return [
    ['heading' => 'app.menu.main'],
    ['label' => 'app.menu.dashboard', 'icon' => 'gauge-high', 'route' => 'dashboard'],
];
