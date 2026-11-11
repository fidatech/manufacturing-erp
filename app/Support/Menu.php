<?php

namespace App\Support;

use App\Models\User;

/** Builds the sidebar from config/menu.php, leaving out what the user may not open. */
class Menu
{
    public static function for(User $user): array
    {
        $menu = [];
        foreach (config('menu') as $item) {
            if (isset($item['children'])) {
                $item['children'] = array_values(array_filter($item['children'], fn (array $child) => self::allowed($user, $child)));
                if ($item['children']) {
                    $menu[] = $item;
                }
            } elseif (isset($item['heading']) || self::allowed($user, $item)) {
                $menu[] = $item;
            }
        }

        // drop headings that have nothing under them
        $clean = [];
        foreach ($menu as $index => $item) {
            $next = $menu[$index + 1] ?? null;
            if (isset($item['heading']) && ($next === null || isset($next['heading']))) {
                continue;
            }
            $clean[] = $item;
        }

        return $clean;
    }

    public static function url(array $item): string
    {
        return route($item['route'], $item['params'] ?? []);
    }

    public static function isActive(array $item): bool
    {
        if (! request()->routeIs($item['active'] ?? $item['route'])) {
            return false;
        }
        // entries that share a route (accounts/{type}, items/{type}, money/{kind}) differ by parameter
        foreach ($item['params'] ?? [] as $key => $value) {
            if ((string) (request()->route($key) ?? request()->query($key)) !== (string) $value) {
                return false;
            }
        }

        return true;
    }

    private static function allowed(User $user, array $item): bool
    {
        return empty($item['can']) || $user->can($item['can']);
    }
}
