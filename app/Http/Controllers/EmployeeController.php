<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Models\Admin;
use App\Models\Employee;
use App\Models\Position;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Employee::class);

        $actor = Auth::guard('admin')->user();
        $search = trim((string) $request->query('search', ''));
        $status = in_array($request->query('status'), ['active', 'inactive'], true)
            ? $request->query('status')
            : '';
        $query = Employee::query()
            ->with(['supervisor', 'admin', 'positions'])
            ->orderBy('name');

        if ($actor->role === 'manager') {
            $query->where('supervisor_id', $this->managerEmployeeId($actor));
        }

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($query) use ($like) {
                $query
                    ->where('name', 'like', $like)
                    ->orWhere('company_name', 'like', $like)
                    ->orWhereHas('supervisor', function ($supervisorQuery) use ($like) {
                        $supervisorQuery->where('name', 'like', $like);
                    })
                    ->orWhereHas('admin', function ($adminQuery) use ($like) {
                        $adminQuery
                            ->where('name', 'like', $like)
                            ->orWhere('email', 'like', $like);
                    })
                    ->orWhereHas('positions', function ($positionQuery) use ($like) {
                        $positionQuery
                            ->where('positions.name', 'like', $like)
                            ->orWhere('positions.code', 'like', $like);
                    });
            });
        }

        if ($status !== '') {
            $query->where('is_active', $status === 'active');
        }

        return view('employees.index', [
            'employees' => $query->paginate(50)->withQueryString(),
            'search' => $search,
            'status' => $status,
            'companyNames' => Employee::query()
                ->whereNotNull('company_name')
                ->where('company_name', '<>', '')
                ->distinct()
                ->orderBy('company_name')
                ->pluck('company_name'),
        ]);
    }

    public function create()
    {
        $this->authorize('create', Employee::class);

        return view('employees.form', array_merge([
            'employee' => new Employee(),
            'assignedPositionIds' => [],
        ], $this->formOptions()));
    }

    public function store(StoreEmployeeRequest $request)
    {
        $validated = $request->validated();
        $positionIds = $validated['position_ids'];
        unset($validated['position_ids']);
        $validated['supervisor_id'] = $this->resolvedSupervisorId($validated);

        DB::transaction(function () use ($validated, $positionIds) {
            $employee = Employee::create($validated);
            $employee->positions()->sync($positionIds);
        });

        return redirect()
            ->route('employees.index')
            ->with('success', '员工档案已创建。');
    }

    public function edit(Employee $employee)
    {
        $this->authorize('update', $employee);

        $assignedPositionIds = DB::table('employee_position')
            ->where('employee_id', $employee->id)
            ->pluck('position_id')
            ->map(function ($id) {
                return (int) $id;
            })
            ->all();

        return view('employees.form', array_merge([
            'employee' => $employee,
            'assignedPositionIds' => $assignedPositionIds,
        ], $this->formOptions($employee, $assignedPositionIds)));
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee)
    {
        $validated = $request->validated();
        $positionIds = $validated['position_ids'];
        unset($validated['position_ids']);
        $validated['supervisor_id'] = $this->resolvedSupervisorId($validated);

        DB::transaction(function () use ($employee, $validated, $positionIds) {
            $employee->update($validated);
            $employee->positions()->sync($positionIds);
        });

        return redirect()
            ->route('employees.index')
            ->with('success', '员工档案已更新。');
    }

    public function destroy(Employee $employee)
    {
        $actor = Auth::guard('admin')->user();
        if ((int) $employee->admin_id === (int) $actor->id) {
            abort(403);
        }

        $this->authorize('delete', $employee);
        $employee->delete();

        return redirect()
            ->route('employees.index')
            ->with('success', '员工档案已删除。');
    }

    private function formOptions(Employee $employee = null, array $assignedPositionIds = [])
    {
        $actor = Auth::guard('admin')->user();
        $currentSupervisorId = $employee ? $employee->supervisor_id : null;
        $currentAdminId = $employee ? $employee->admin_id : null;

        if ($actor->role === 'manager') {
            $supervisorOptions = Employee::query()
                ->whereKey($this->managerEmployeeId($actor))
                ->get(['id', 'name', 'company_name', 'is_active']);
        } else {
            $supervisorOptions = Employee::query()
                ->where(function ($query) use ($currentSupervisorId) {
                    $query->where('is_active', true);
                    if ($currentSupervisorId) {
                        $query->orWhereKey($currentSupervisorId);
                    }
                })
                ->when($employee, function ($query) use ($employee) {
                    $query->whereKeyNot($employee->id);
                })
                ->orderBy('name')
                ->get(['id', 'name', 'company_name', 'is_active']);
        }

        $accountOptions = Admin::withTrashed()
            ->where(function ($query) use ($currentAdminId) {
                if ($currentAdminId) {
                    $query->whereKey($currentAdminId)->orWhere(function ($eligibleQuery) {
                        $this->eligibleAccountQuery($eligibleQuery);
                    });
                } else {
                    $this->eligibleAccountQuery($query);
                }
            })
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'is_active', 'deleted_at']);

        $positionOptions = Position::withTrashed()
            ->where(function ($query) use ($assignedPositionIds) {
                $query->where(function ($activeQuery) {
                    $activeQuery
                        ->where('is_active', true)
                        ->whereNull('deleted_at');
                });
                if (!empty($assignedPositionIds)) {
                    $query->orWhereIn('id', $assignedPositionIds);
                }
            })
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'is_active', 'deleted_at']);

        $companyNames = Employee::query()
            ->whereNotNull('company_name')
            ->where('company_name', '<>', '')
            ->distinct()
            ->orderBy('company_name')
            ->pluck('company_name');

        return compact(
            'supervisorOptions',
            'accountOptions',
            'positionOptions',
            'companyNames'
        );
    }

    private function eligibleAccountQuery($query)
    {
        $query
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->whereNotExists(function ($employeeQuery) {
                $employeeQuery
                    ->select(DB::raw(1))
                    ->from('employees')
                    ->whereColumn('employees.admin_id', 'admins.id');
            });
    }

    private function resolvedSupervisorId(array $validated)
    {
        $actor = Auth::guard('admin')->user();

        return $actor->role === 'manager'
            ? $this->managerEmployeeId($actor)
            : ($validated['supervisor_id'] ?? null);
    }

    private function managerEmployeeId(Admin $actor)
    {
        return $actor->employee()
            ->where('is_active', true)
            ->value('id');
    }
}
