<?php

namespace App\Services\Sidebar;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class SidebarBuilder
{
    public function build(Request $request): array
    {
        $items = config('sidebar', []);
        $user = $request->user();

        $built = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $parsed = $this->parseItem($item, $request, $user);

            if ($parsed !== null) {
                $built[] = $parsed;
            }
        }

        return $built;
    }

    private function parseItem(array $item, Request $request, ?User $user): ?array
    {
        $type = $item['type'] ?? null;

        if (! is_string($type)) {
            return null;
        }

        if (! $this->isAuthorized($item, $user)) {
            return null;
        }

        if ($type === 'header') {
            $title = $item['title'] ?? null;

            if (! is_string($title) || $title === '') {
                return null;
            }

            return [
                'type' => 'header',
                'title' => $title,
            ];
        }

        if ($type === 'link') {
            $title = $item['title'] ?? null;
            if (! is_string($title) || $title === '') {
                return null;
            }

            $href = $this->resolveHref($item);
            $isActive = $this->isActive($item['active'] ?? null, $request);

            return [
                'type' => 'link',
                'title' => $title,
                'icon' => $item['icon'] ?? null,
                'href' => $href,
                'isActive' => $isActive,
            ];
        }

        if ($type === 'group') {
            $title = $item['title'] ?? null;
            if (! is_string($title) || $title === '') {
                return null;
            }

            $children = [];
            foreach (($item['items'] ?? []) as $child) {
                if (! is_array($child)) {
                    continue;
                }

                $parsed = $this->parseItem($child, $request, $user);
                if ($parsed !== null) {
                    $children[] = $parsed;
                }
            }

            $children = array_values(array_filter($children, fn ($i) => ($i['type'] ?? null) !== 'header'));

            if (count($children) === 0) {
                return null;
            }

            $isActive = $this->isActive($item['active'] ?? null, $request)
                || collect($children)->contains(fn ($c) => (bool) ($c['isActive'] ?? false));

            return [
                'type' => 'group',
                'title' => $title,
                'icon' => $item['icon'] ?? null,
                'isActive' => $isActive,
                'items' => $children,
            ];
        }

        return null;
    }

    private function resolveHref(array $item): string
    {
        if (isset($item['url']) && is_string($item['url']) && $item['url'] !== '') {
            return $item['url'];
        }

        if (isset($item['route']) && is_string($item['route']) && $item['route'] !== '') {
            $parameters = [];
            if (isset($item['params']) && is_array($item['params'])) {
                $parameters = $item['params'];
            }

            if (Route::has($item['route'])) {
                return route($item['route'], $parameters);
            }
        }

        return '#';
    }

    private function isActive(mixed $active, Request $request): bool
    {
        if (is_string($active) && $active !== '') {
            return $request->routeIs($active);
        }

        if (is_array($active) && count($active) > 0) {
            foreach ($active as $pattern) {
                if (is_string($pattern) && $pattern !== '' && $request->routeIs($pattern)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function isAuthorized(array $item, ?User $user): bool
    {
        if (! isset($item['can'])) {
            return true;
        }

        $can = $item['can'];

        if (! is_array($can) || count($can) === 0) {
            return true;
        }

        if (! $user) {
            return false;
        }

        foreach ($can as $ability) {
            if (! is_string($ability) || $ability === '') {
                continue;
            }

            if ($ability === 'auth') {
                return true;
            }

            if (in_array($ability, ['staff', 'customer', 'provider'], true)) {
                return $user->user_type === $ability;
            }

            if (method_exists($user, 'can') && $user->can($ability)) {
                return true;
            }
        }

        return false;
    }
}

