<?php

namespace App\Livewire\Settings;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePreview extends Component
{
    public string $q = '';

    public function mount(): void
    {
        if (! (auth()->user()?->can('settings.manage') ?? false)) {
            abort(403);
        }
    }

    public function render(): View
    {
        $roleConfig = collect((array) config('permissions_ui.roles', []));
        $moduleConfig = collect((array) config('permissions_ui.modules', []));
        $search = mb_strtolower(trim($this->q));

        /** @var Collection<string, Role> $rolesByName */
        $rolesByName = Role::query()
            ->whereIn('name', $roleConfig->keys()->all())
            ->with('permissions:id,name')
            ->get()
            ->keyBy('name');

        $mappedPermissions = $moduleConfig
            ->flatMap(fn (array $module): array => array_keys((array) ($module['permissions'] ?? [])))
            ->unique()
            ->values();

        $allPermissions = Permission::query()
            ->orderBy('name')
            ->pluck('name');

        $otherPermissions = $allPermissions
            ->reject(fn (string $name): bool => $mappedPermissions->contains($name))
            ->values();

        $roles = $roleConfig
            ->map(function (array $roleMeta, string $roleName) use ($rolesByName, $moduleConfig, $otherPermissions, $search): array {
                $role = $rolesByName->get($roleName);
                $granted = $role?->permissions?->pluck('name')->flip() ?? collect();

                $modules = $moduleConfig
                    ->map(function (array $module) use ($granted, $search): ?array {
                        $rows = collect((array) ($module['permissions'] ?? []))
                            ->map(function (string $label, string $permission) use ($granted): array {
                                return [
                                    'permission' => $permission,
                                    'label' => $label,
                                    'allowed' => $granted->has($permission),
                                ];
                            });

                        if ($search !== '') {
                            $moduleText = mb_strtolower((string) ($module['label'] ?? ''));
                            $rows = $rows->filter(function (array $row) use ($search, $moduleText): bool {
                                return str_contains(mb_strtolower($row['label']), $search)
                                    || str_contains(mb_strtolower($row['permission']), $search)
                                    || str_contains($moduleText, $search);
                            });
                        }

                        if ($rows->isEmpty()) {
                            return null;
                        }

                        return [
                            'key' => (string) ($module['key'] ?? ''),
                            'label' => (string) ($module['label'] ?? ''),
                            'permissions' => $rows->values()->all(),
                        ];
                    })
                    ->filter()
                    ->values();

                $otherRows = $otherPermissions
                    ->map(fn (string $permission): array => [
                        'permission' => $permission,
                        'label' => $permission,
                        'allowed' => $granted->has($permission),
                    ])
                    ->when($search !== '', fn (Collection $collection): Collection => $collection->filter(
                        fn (array $row): bool => str_contains(mb_strtolower($row['permission']), $search)
                            || str_contains(mb_strtolower($row['label']), $search)
                            || str_contains('otros permisos', $search)
                    ))
                    ->values();

                if ($otherRows->isNotEmpty()) {
                    $modules->push([
                        'key' => 'other',
                        'label' => 'Otros permisos',
                        'permissions' => $otherRows->all(),
                    ]);
                }

                $totalPermissions = $modules->sum(
                    fn (array $module): int => count((array) ($module['permissions'] ?? []))
                );

                $allowedPermissions = $modules->sum(function (array $module): int {
                    return collect((array) ($module['permissions'] ?? []))
                        ->where('allowed', true)
                        ->count();
                });

                return [
                    'name' => $roleName,
                    'label' => (string) ($roleMeta['label'] ?? $roleName),
                    'description' => (string) ($roleMeta['description'] ?? ''),
                    'modules' => $modules->all(),
                    'allowed_permissions' => $allowedPermissions,
                    'total_permissions' => $totalPermissions,
                ];
            })
            ->values();

        return view('livewire.settings.role-preview', [
            'roles' => $roles,
        ])->layout('layouts.app', [
            'title' => 'Roles y permisos',
        ]);
    }
}
