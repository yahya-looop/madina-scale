<?php

namespace App\Services;

use App\Models\MenuItem;
use App\Models\Module;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class MenuRegistryService
{
    public function __construct(private readonly AuthorizationService $authorization) {}

    /** @return Collection<int, MenuItem> */
    public function list(): Collection
    {
        return MenuItem::query()
            ->with(['module:id,name,slug', 'parent:id,label'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  array<int, string>|null  $grantedPermissionSlugs
     * @return array<int, array<string, mixed>>
     */
    public function navigationFor(User $user, ?array $grantedPermissionSlugs = null): array
    {
        $items = MenuItem::query()
            ->with(['module:id,slug,state,is_active', 'children.module:id,slug,state,is_active'])
            ->whereNull('parent_id')
            ->where('is_visible', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $permissionSlugs = $items->flatMap(function (MenuItem $item): array {
            return [$item->permission, ...$item->children->pluck('permission')->all()];
        })->filter()->unique()->values()->all();
        $permissionRecords = Permission::query()
            ->with('module:id,state,is_active')
            ->whereIn('slug', $permissionSlugs)
            ->get()
            ->keyBy('slug');

        $isDeveloper = $this->authorization->isDeveloper($user);
        $permissionSet = $isDeveloper
            ? []
            : array_fill_keys(
                $grantedPermissionSlugs ?? $user->roles()
                    ->where('roles.is_active', true)
                    ->with(['permissions.module'])
                    ->get()
                    ->flatMap(fn ($role) => $role->permissions)
                    ->filter(fn (Permission $permission): bool => $this->permissionAvailable($permission))
                    ->pluck('slug')
                    ->all(),
                true,
            );

        return $items->map(function (MenuItem $item) use ($isDeveloper, $permissionSet, $permissionRecords): ?array {
            if (! $this->isVisibleTo($item, $isDeveloper, $permissionSet, $permissionRecords)) {
                return null;
            }

            $children = $item->children
                ->filter(fn (MenuItem $child): bool => $this->isVisibleTo($child, $isDeveloper, $permissionSet, $permissionRecords))
                ->map(fn (MenuItem $child): array => $this->toNavigationItem($child))
                ->values()
                ->all();

            if ($item->route === null && $children === []) {
                return null;
            }

            return [...$this->toNavigationItem($item), 'expandable' => $children !== [], 'children' => $children];
        })->filter()->values()->all();
    }

    /** @param array{key:string,label:string,route?:string|null,icon?:string,permission?:string|null,module_id?:int|null,parent_id?:int|null,sort_order?:int,is_visible?:bool,developer_only?:bool} $data */
    public function create(array $data): MenuItem
    {
        return DB::transaction(fn (): MenuItem => MenuItem::query()->create([...$data, 'is_system' => false]));
    }

    /** @param array{key:string,label:string,route?:string|null,icon?:string,permission?:string|null,module_id?:int|null,parent_id?:int|null,sort_order?:int,is_visible?:bool,developer_only?:bool} $data */
    public function update(MenuItem $item, array $data): MenuItem
    {
        if ($item->is_system) {
            $protectedFields = ['key', 'route', 'parent_id', 'module_id', 'developer_only'];
            foreach ($protectedFields as $field) {
                if (array_key_exists($field, $data) && $data[$field] !== $item->{$field}) {
                    abort(403, 'Core menu structure is protected; labels, icons, permissions and visibility remain configurable.');
                }
            }
        }
        abort_if(($data['parent_id'] ?? null) === $item->id, 422, 'A menu item cannot be its own parent.');

        abort_if(
            ($data['parent_id'] ?? null) !== null && $item->children()->exists(),
            422,
            'A menu group with child items cannot be nested inside another group.',
        );

        return DB::transaction(function () use ($item, $data): MenuItem {
            $item->update($data);

            return $item->refresh();
        });
    }

    public function delete(MenuItem $item): void
    {
        abort_if($item->is_system, 403, 'System menu items cannot be deleted.');
        $item->delete();
    }

    /**
     * @param  array<string, bool>  $permissionSet
     * @param  Collection<string, Permission>  $permissionRecords
     */
    private function isVisibleTo(
        MenuItem $item,
        bool $isDeveloper,
        array $permissionSet,
        Collection $permissionRecords,
    ): bool {
        if (! $item->is_visible || ($item->developer_only && ! $isDeveloper)) {
            return false;
        }

        if ($item->module !== null && in_array($this->moduleState($item->module), ['disabled', 'hidden'], true)) {
            return false;
        }

        if ($item->permission === null) {
            return true;
        }

        $permission = $permissionRecords->get($item->permission);
        if (! $permission instanceof Permission || ! $this->permissionAvailable($permission)) {
            return false;
        }

        return $isDeveloper || isset($permissionSet[$item->permission]);
    }

    private function permissionAvailable(Permission $permission): bool
    {
        if ($permission->module === null) {
            return false;
        }

        $state = $this->moduleState($permission->module);
        if ($state === 'disabled') {
            return false;
        }

        return $state !== 'locked' || preg_match('/\.(view|read)$/', $permission->slug) === 1;
    }

    private function moduleState(Module $module): string
    {
        return ! $module->is_active || $module->state === 'disabled'
            ? 'disabled'
            : ($module->state ?: 'enabled');
    }

    /** @return array{label:string,href:?string,icon:string,permission:?string} */
    private function toNavigationItem(MenuItem $item): array
    {
        return [
            'label' => $item->label,
            'href' => $item->route,
            'icon' => $item->icon,
            'permission' => $item->permission,
        ];
    }
}
