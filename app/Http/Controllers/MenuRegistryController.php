<?php

namespace App\Http\Controllers;

use App\Models\MenuItem;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\AuthorizationService;
use App\Services\MenuRegistryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MenuRegistryController extends Controller
{
    public function __construct(
        private readonly MenuRegistryService $menus,
        private readonly AuthorizationService $authorization,
        private readonly ActivityLogService $activityLog,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $this->developer($request);

        return response()->json(['data' => $this->menus->list()]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $this->developer($request);
        $data = $this->validated($request);
        $item = DB::transaction(function () use ($data, $user): MenuItem {
            $created = $this->menus->create($data);
            $this->activityLog->recordForUser($user, 'menu_item.created', 'system', "Menu item {$created->label} created.", $created, null, $created->only(['key', 'label', 'route', 'icon', 'permission', 'module_id', 'is_visible', 'developer_only', 'sort_order', 'parent_id']));

            return $created;
        });

        return response()->json(['data' => $item], 201);
    }

    public function update(Request $request, MenuItem $menuItem): JsonResponse
    {
        $user = $this->developer($request);
        $data = $this->validated($request, $menuItem);
        $item = DB::transaction(function () use ($menuItem, $data, $user): MenuItem {
            $old = $menuItem->only(['key', 'label', 'route', 'icon', 'permission', 'module_id', 'is_visible', 'developer_only', 'sort_order', 'parent_id']);
            $updated = $this->menus->update($menuItem, $data);
            $this->activityLog->recordForUser($user, 'menu_item.updated', 'system', "Menu item {$updated->label} updated.", $updated, $old, $updated->only(array_keys($old)));

            return $updated;
        });

        return response()->json(['data' => $item]);
    }

    public function destroy(Request $request, MenuItem $menuItem): JsonResponse
    {
        $user = $this->developer($request);
        abort_if($menuItem->is_system, 403, 'System menu items cannot be deleted.');
        DB::transaction(function () use ($menuItem, $user): void {
            $old = $menuItem->only(['key', 'label', 'route', 'icon', 'permission', 'module_id', 'is_visible', 'developer_only', 'sort_order', 'parent_id']);
            $this->menus->delete($menuItem);
            $this->activityLog->recordForUser($user, 'menu_item.deleted', 'system', "Menu item {$menuItem->label} deleted.", null, $old, null);
        });

        return response()->json(['message' => 'Menu item deleted.']);
    }

    /** @return array{key:string,label:string,route?:string|null,icon?:string,permission?:string|null,module_id?:int|null,parent_id?:int|null,sort_order?:int,is_visible?:bool,developer_only?:bool} */
    private function validated(Request $request, ?MenuItem $menuItem = null): array
    {
        return $request->validate([
            'key' => ['required', 'string', 'max:150', 'regex:/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/', Rule::unique('menu_items', 'key')->ignore($menuItem?->id)],
            'label' => ['required', 'string', 'max:120'],
            'route' => ['nullable', 'string', 'max:255', 'regex:/^\/[a-zA-Z0-9_\-\/{}]*$/'],
            'icon' => ['sometimes', 'string', 'max:80'],
            'permission' => ['required_with:route', 'nullable', 'string', 'max:150', 'exists:permissions,slug'],
            'module_id' => ['nullable', 'integer', 'exists:modules,id'],
            'parent_id' => ['nullable', 'integer', Rule::exists('menu_items', 'id')->whereNull('parent_id')->where(fn ($query) => $menuItem === null ? $query : $query->where('id', '!=', $menuItem->id))],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_visible' => ['sometimes', 'boolean'],
            'developer_only' => ['sometimes', 'boolean'],
        ]);
    }

    private function developer(Request $request): User
    {
        $user = $request->user();
        abort_unless($user !== null, 401);
        abort_unless($this->authorization->isDeveloper($user), 403);

        return $user;
    }
}
