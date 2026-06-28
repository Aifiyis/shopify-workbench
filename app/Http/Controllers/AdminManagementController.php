<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\Employee;
use App\Models\ShopifyStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminManagementController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', Admin::class);

        $actor = Auth::guard('admin')->user();
        $search = trim((string) $request->query('search', ''));
        $query = Admin::query()
            ->with(['parent', 'employee'])
            ->withCount('stores')
            ->orderBy('name');

        if (!Gate::forUser($actor)->allows('create', [Admin::class, 'manager'])) {
            $query->where('role', 'employee');
        }

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($query) use ($like) {
                $query
                    ->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('company_name', 'like', $like)
                    ->orWhere('role', 'like', $like)
                    ->orWhereHas('employee', function ($employeeQuery) use ($like) {
                        $employeeQuery->where('name', 'like', $like);
                    });
            });
        }

        return view('admins.index', [
            'admins' => $query->paginate(50)->withQueryString(),
            'search' => $search,
        ]);
    }

    public function create()
    {
        $this->authorize('create', [Admin::class, 'employee']);

        $admin = new Admin([
            'is_active' => true,
            'parent_admin_id' => Auth::guard('admin')->id(),
        ]);

        return view('admins.form', array_merge(
            ['admin' => $admin],
            $this->formOptions($admin)
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate(
            $this->rules(),
            $this->messages(),
            $this->attributes()
        );
        $this->authorize('create', [Admin::class, $validated['role']]);
        $actor = Auth::guard('admin')->user();

        DB::transaction(function () use ($validated, $actor) {
            $admin = Admin::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => $validated['role'],
                'parent_admin_id' => $actor->id,
                'company_name' => $validated['company_name'] ?? null,
                'is_active' => (bool) $validated['is_active'],
            ]);

            $this->syncStores($admin, $validated);
            $this->syncEmployee($admin, $validated['employee_id'] ?? null);
        });

        return redirect()
            ->route('admins.index')
            ->with('success', '管理员账号已创建。');
    }

    public function edit(Admin $admin)
    {
        $this->authorize('update', $admin);

        return view('admins.form', array_merge(
            ['admin' => $admin],
            $this->formOptions($admin)
        ));
    }

    public function update(Request $request, Admin $admin)
    {
        $this->authorize('update', $admin);

        $validated = $request->validate(
            $this->rules($admin),
            $this->messages(),
            $this->attributes()
        );
        $this->authorize('update', [$admin, $validated['role']]);

        DB::transaction(function () use ($admin, $validated) {
            $attributes = [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'role' => $validated['role'],
                'company_name' => $validated['company_name'] ?? null,
                'is_active' => (bool) $validated['is_active'],
            ];

            if (!empty($validated['password'])) {
                $attributes['password'] = Hash::make($validated['password']);
            }

            $admin->update($attributes);
            $this->syncStores($admin, $validated);
            $this->syncEmployee($admin, $validated['employee_id'] ?? null);
        });

        return redirect()
            ->route('admins.index')
            ->with('success', '管理员账号已更新。');
    }

    public function destroy(Admin $admin)
    {
        $actor = Auth::guard('admin')->user();
        if ((int) $actor->id === (int) $admin->id) {
            abort(403, '不能删除当前登录账号。');
        }

        $this->authorize('delete', $admin);

        DB::transaction(function () use ($admin) {
            Employee::withTrashed()
                ->where('admin_id', $admin->id)
                ->update(['admin_id' => null]);
            $admin->delete();
        });

        return redirect()
            ->route('admins.index')
            ->with('success', '管理员账号已删除。');
    }

    private function rules(Admin $admin = null)
    {
        $emailRule = Rule::unique('admins', 'email');
        if ($admin && $admin->exists) {
            $emailRule->ignore($admin->id);
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', $emailRule],
            'password' => [$admin && $admin->exists ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::in(['super', 'manager', 'employee'])],
            'company_name' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
            'employee_id' => [
                'nullable',
                'integer',
                function ($attribute, $value, $fail) use ($admin) {
                    if ($value === null || $value === '') {
                        return;
                    }

                    $currentEmployeeId = $admin && $admin->exists
                        ? Employee::withTrashed()
                            ->where('admin_id', $admin->id)
                            ->value('id')
                        : null;
                    $eligible = Employee::withTrashed()
                        ->whereKey($value)
                        ->where(function ($query) use ($currentEmployeeId) {
                            $query->where(function ($eligibleQuery) {
                                $eligibleQuery
                                    ->where('is_active', true)
                                    ->whereNull('deleted_at')
                                    ->whereNull('admin_id');
                            });
                            if ($currentEmployeeId) {
                                $query->orWhere('id', $currentEmployeeId);
                            }
                        })
                        ->exists();

                    if (!$eligible) {
                        $fail('所选员工档案不可用或已关联其他账号。');
                    }
                },
            ],
            'store_ids' => ['nullable', 'array'],
            'store_ids.*' => ['integer', 'distinct', 'exists:shopify_stores,id'],
            'access_levels' => ['nullable', 'array'],
            'access_levels.*' => [Rule::in(['view', 'edit'])],
        ];
    }

    private function messages()
    {
        return [
            'email.unique' => '该邮箱已被使用（包括已删除账号），请更换邮箱。',
            'role.in' => '请选择允许管理的账号角色。',
            'store_ids.*.distinct' => '同一店铺不能重复选择。',
            'store_ids.*.exists' => '所选 Shopify 店铺不存在。',
            'access_levels.*.in' => '店铺权限必须为查看或编辑。',
        ];
    }

    private function attributes()
    {
        return [
            'name' => '姓名',
            'email' => '邮箱',
            'password' => '密码',
            'role' => '角色',
            'company_name' => '公司名称',
            'is_active' => '账号状态',
            'employee_id' => '员工档案',
            'store_ids' => 'Shopify 店铺',
            'access_levels' => '店铺权限',
        ];
    }

    private function formOptions(Admin $admin)
    {
        $actor = Auth::guard('admin')->user();
        $editing = $admin->exists;
        $assignedStores = $editing
            ? $admin->stores()->pluck('shopify_stores.id')->map(function ($id) {
                return (int) $id;
            })->all()
            : [];
        $assignedAccessLevels = $editing
            ? $admin->stores()->pluck('access_level', 'shopify_stores.id')->all()
            : [];
        $currentEmployeeId = $editing
            ? Employee::withTrashed()->where('admin_id', $admin->id)->value('id')
            : null;

        $stores = ShopifyStore::query()
            ->where(function ($query) use ($assignedStores) {
                $query->where('is_active', true);
                if (!empty($assignedStores)) {
                    $query->orWhereIn('id', $assignedStores);
                }
            })
            ->orderBy('shop_name')
            ->get();

        $employeeOptions = Employee::withTrashed()
            ->where(function ($query) use ($currentEmployeeId) {
                $query->where(function ($eligibleQuery) {
                    $eligibleQuery
                        ->where('is_active', true)
                        ->whereNull('deleted_at')
                        ->whereNull('admin_id');
                });
                if ($currentEmployeeId) {
                    $query->orWhere('id', $currentEmployeeId);
                }
            })
            ->orderBy('name')
            ->get(['id', 'name', 'company_name', 'admin_id', 'is_active', 'deleted_at']);

        $availableRoles = collect(['super', 'manager', 'employee'])
            ->filter(function ($role) use ($actor, $admin, $editing) {
                return $editing
                    ? Gate::forUser($actor)->allows('update', [$admin, $role])
                    : Gate::forUser($actor)->allows('create', [Admin::class, $role]);
            })
            ->values();

        return compact(
            'stores',
            'assignedStores',
            'assignedAccessLevels',
            'employeeOptions',
            'currentEmployeeId',
            'availableRoles'
        );
    }

    private function syncStores(Admin $admin, array $validated)
    {
        $storeIds = $validated['store_ids'] ?? [];
        $accessLevels = $validated['access_levels'] ?? [];
        $storeAccess = [];

        foreach ($storeIds as $index => $storeId) {
            $accessLevel = $accessLevels[$storeId]
                ?? $accessLevels[$index]
                ?? 'view';
            $storeAccess[(int) $storeId] = ['access_level' => $accessLevel];
        }

        $admin->stores()->sync($storeAccess);
    }

    private function syncEmployee(Admin $admin, $employeeId)
    {
        $selectedEmployeeId = $employeeId ? (int) $employeeId : null;

        Employee::withTrashed()
            ->where('admin_id', $admin->id)
            ->when($selectedEmployeeId, function ($query) use ($selectedEmployeeId) {
                $query->where('id', '<>', $selectedEmployeeId);
            })
            ->update(['admin_id' => null]);

        if ($selectedEmployeeId) {
            Employee::query()
                ->whereKey($selectedEmployeeId)
                ->update(['admin_id' => $admin->id]);
        }
    }
}
