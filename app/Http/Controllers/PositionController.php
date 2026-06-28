<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePositionRequest;
use App\Http\Requests\UpdatePositionRequest;
use App\Models\Position;
use App\Services\PermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PositionController extends Controller
{
    private $permissions;

    public function __construct(PermissionService $permissions)
    {
        $this->permissions = $permissions;
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', Position::class);

        $search = trim((string) $request->query('search', ''));
        $status = in_array($request->query('status'), ['active', 'inactive'], true)
            ? $request->query('status')
            : '';
        $query = Position::query()
            ->with('permissions')
            ->withCount('employees')
            ->orderBy('name');

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($query) use ($like) {
                $query
                    ->where('name', 'like', $like)
                    ->orWhere('code', 'like', $like)
                    ->orWhereHas('permissions', function ($permissionQuery) use ($like) {
                        $permissionQuery
                            ->where('permissions.name', 'like', $like)
                            ->orWhere('permissions.code', 'like', $like);
                    });
            });
        }

        if ($status !== '') {
            $query->where('is_active', $status === 'active');
        }

        return view('positions.index', [
            'positions' => $query->paginate(50)->withQueryString(),
            'search' => $search,
            'status' => $status,
        ]);
    }

    public function create()
    {
        $this->authorize('create', Position::class);

        return view('positions.form', array_merge([
            'position' => new Position(),
        ], $this->permissionOptions(new Position())));
    }

    public function store(StorePositionRequest $request)
    {
        $validated = $request->validated();
        $permissionIds = $validated['permission_ids'];
        unset($validated['permission_ids']);

        DB::transaction(function () use ($validated, $permissionIds) {
            $position = Position::create($validated);
            if (!empty($permissionIds)) {
                $position->permissions()->attach($permissionIds);
            }
        });

        return redirect()
            ->route('positions.index')
            ->with('success', '职位已创建。');
    }

    public function edit(Position $position)
    {
        $this->authorize('update', $position);

        return view('positions.form', array_merge([
            'position' => $position,
        ], $this->permissionOptions($position)));
    }

    public function update(UpdatePositionRequest $request, Position $position)
    {
        $validated = $request->validated();
        $submittedPermissionIds = $validated['permission_ids'];
        unset($validated['permission_ids']);
        $actor = Auth::guard('admin')->user();

        DB::transaction(function () use (
            $position,
            $validated,
            $submittedPermissionIds,
            $actor
        ) {
            $position->update($validated);

            if (Gate::forUser($actor)->allows('assignPermissions', Position::class)) {
                $delegableIds = $this->permissions
                    ->delegableFor($actor)
                    ->pluck('id')
                    ->map(function ($id) {
                        return (int) $id;
                    });
                $lockedIds = $position->permissions()
                    ->pluck('permissions.id')
                    ->map(function ($id) {
                        return (int) $id;
                    })
                    ->diff($delegableIds);

                $position->permissions()->sync(
                    $lockedIds->merge($submittedPermissionIds)->unique()->values()->all()
                );
            }
        });

        return redirect()
            ->route('positions.index')
            ->with('success', '职位已更新。');
    }

    public function destroy(Position $position)
    {
        $this->authorize('delete', $position);
        $position->delete();

        return redirect()
            ->route('positions.index')
            ->with('success', '职位已删除。');
    }

    private function permissionOptions(Position $position)
    {
        $actor = Auth::guard('admin')->user();
        $canAssignPermissions = Gate::forUser($actor)
            ->allows('assignPermissions', Position::class);
        $permissionOptions = $canAssignPermissions
            ? $this->permissions->delegableFor($actor)
            : collect();
        $permissionOptionIds = $permissionOptions->pluck('id')->map(function ($id) {
            return (int) $id;
        });
        $currentPermissions = $position->exists
            ? $position->permissions()->orderBy('code')->get()
            : collect();
        $selectedPermissionIds = $currentPermissions
            ->pluck('id')
            ->map(function ($id) {
                return (int) $id;
            })
            ->intersect($permissionOptionIds)
            ->values()
            ->all();
        $lockedPermissions = $currentPermissions->reject(function ($permission) use ($permissionOptionIds) {
            return $permissionOptionIds->contains((int) $permission->id);
        });

        return compact(
            'canAssignPermissions',
            'permissionOptions',
            'selectedPermissionIds',
            'lockedPermissions'
        );
    }
}
