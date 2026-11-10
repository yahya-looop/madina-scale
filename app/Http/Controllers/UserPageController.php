<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Services\AuthorizationService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserPageController extends Controller
{
    public function __construct(
        private readonly AuthorizationService $authorization,
    ) {}

    /**
     * Display the users index page.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        abort_unless(
            $user !== null && $this->authorization->can($user, 'system.users.view'),
            403,
        );

        return Inertia::render('Users/Index', [
            'users' => $this->users(),
            'roles' => $this->roles(),
            'capabilities' => $this->capabilities($user),
            'userName' => $user->name,
            'userEmail' => $user->email,
        ]);
    }

    /**
     * Display the user creation page.
     */
    public function create(Request $request): Response
    {
        $user = $request->user();

        abort_unless(
            $user !== null && $this->authorization->can($user, 'system.users.create'),
            403,
        );

        return Inertia::render('Users/Create', [
            'roles' => $this->roles(),
            'capabilities' => $this->capabilities($user),
            'userName' => $user->name,
            'userEmail' => $user->email,
        ]);
    }

    /**
     * Display a single user's details page.
     */
    public function show(Request $request, User $user): Response
    {
        $currentUser = $request->user();

        abort_unless(
            $currentUser !== null && $this->authorization->can($currentUser, 'system.users.view'),
            403,
        );

        return Inertia::render('Users/Show', [
            'user' => $user->load([
                'roles:id,name,slug,is_system,is_active',
            ]),
            'capabilities' => $this->capabilities($currentUser),
            'userName' => $currentUser->name,
            'userEmail' => $currentUser->email,
        ]);
    }

    /**
     * Display the user edit page.
     */
    public function edit(Request $request, User $user): Response
    {
        $currentUser = $request->user();

        abort_unless(
            $currentUser !== null && $this->authorization->can($currentUser, 'system.users.update'),
            403,
        );

        return Inertia::render('Users/Edit', [
            'user' => $user->load([
                'roles:id,name,slug,is_system,is_active',
            ]),
            'roles' => $this->roles(),
            'capabilities' => $this->capabilities($currentUser),
            'userName' => $currentUser->name,
            'userEmail' => $currentUser->email,
        ]);
    }

    /**
     * Return active, non-system roles available to normal user management.
     *
     * @return Collection<int, Role>
     */
    private function roles(): Collection
    {
        return Role::query()
            ->where('is_system', false)
            ->where('is_active', true)
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'slug',
                'description',
                'is_system',
                'is_active',
            ]);
    }

    /**
     * Return users required by the users index page.
     *
     * @return Collection<int, User>
     */
    private function users(): Collection
    {
        return User::query()
            ->with([
                'roles:id,name,slug,is_system,is_active',
            ])
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'email',
                'status',
                'created_at',
                'updated_at',
            ]);
    }

    /**
     * Build the frontend capability contract from the central authorization
     * service so the UI cannot drift from backend permission decisions.
     *
     * @return array{canView: bool, canCreate: bool, canUpdate: bool, isDeveloper: bool}
     */
    private function capabilities(User $user): array
    {
        return [
            'canView' => $this->authorization->can($user, 'system.users.view'),
            'canCreate' => $this->authorization->can($user, 'system.users.create'),
            'canUpdate' => $this->authorization->can($user, 'system.users.update'),
            'isDeveloper' => $this->authorization->isDeveloper($user),
        ];
    }
}
