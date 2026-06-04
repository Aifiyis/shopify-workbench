<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\ShopifyStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AdminManagementController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    public function index()
    {
        $admin = Auth::guard('admin')->user();

        // Super admin sees all admins in hierarchical view
        if ($admin->role === 'super') {
            $admins = Admin::whereNull('parent_admin_id')->with('subordinates')->get();
        } else {
            // Manager sees only their direct subordinates
            $admins = $admin->getSubordinateTree();
        }

        return view('admins.index', [
            'admins' => $admins,
            'currentAdmin' => $admin,
        ]);
    }

    public function create()
    {
        $admin = Auth::guard('admin')->user();

        // Only super and manager can create new admins
        if (!in_array($admin->role, ['super', 'manager'])) {
            abort(403, 'Unauthorized to create admins');
        }

        $stores = ShopifyStore::where('is_active', true)->get();
        $availableRoles = $admin->role === 'super' ? ['manager', 'employee'] : ['employee'];

        return view('admins.form', [
            'stores' => $stores,
            'availableRoles' => $availableRoles,
            'parentAdmin' => $admin,
        ]);
    }

    public function store(Request $request)
    {
        $admin = Auth::guard('admin')->user();

        if (!in_array($admin->role, ['super', 'manager'])) {
            return redirect()->route('admins.index')->with('error', 'Unauthorized');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:admins|max:255',
            'password' => 'required|min:8|confirmed',
            'role' => 'required|in:employee,manager',
            'company_name' => 'nullable|string|max:255',
            'store_ids' => 'nullable|array',
            'store_ids.*' => 'exists:shopify_stores,id',
            'access_levels' => 'nullable|array',
        ]);

        // Prevent manager from creating another manager
        if ($admin->role === 'manager' && $validated['role'] === 'manager') {
            return redirect()->back()->with('error', 'Managers can only create employees');
        }

        $newAdmin = Admin::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'parent_admin_id' => $admin->id,
            'company_name' => $validated['company_name'] ?? null,
            'is_active' => true,
        ]);

        // Assign stores if provided
        if ($request->filled('store_ids')) {
            foreach ($request->input('store_ids') as $key => $storeId) {
                $accessLevel = $request->input('access_levels')[$key] ?? 'view';
                $newAdmin->stores()->attach($storeId, ['access_level' => $accessLevel]);
            }
        }

        return redirect()->route('admins.index')->with('success', 'Admin created successfully');
    }

    public function edit($id)
    {
        $admin = Auth::guard('admin')->user();
        $targetAdmin = Admin::findOrFail($id);

        // Permission check
        if (!$admin->canManage($id)) {
            abort(403, 'Unauthorized to edit this admin');
        }

        $stores = ShopifyStore::where('is_active', true)->get();
        $assignedStores = $targetAdmin->stores()->pluck('store_id')->toArray();
        $assignedAccessLevels = $targetAdmin->stores()
            ->pluck('access_level', 'store_id')
            ->toArray();

        $availableRoles = $admin->role === 'super' ? ['manager', 'employee'] : ['employee'];

        return view('admins.form', [
            'admin' => $targetAdmin,
            'stores' => $stores,
            'assignedStores' => $assignedStores,
            'assignedAccessLevels' => $assignedAccessLevels,
            'availableRoles' => $availableRoles,
            'parentAdmin' => $admin,
        ]);
    }

    public function update(Request $request, $id)
    {
        $admin = Auth::guard('admin')->user();
        $targetAdmin = Admin::findOrFail($id);

        if (!$admin->canManage($id)) {
            return redirect()->route('admins.index')->with('error', 'Unauthorized');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:admins,email,' . $id,
            'password' => 'nullable|min:8|confirmed',
            'role' => 'required|in:employee,manager',
            'company_name' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'store_ids' => 'nullable|array',
            'store_ids.*' => 'exists:shopify_stores,id',
            'access_levels' => 'nullable|array',
        ]);

        if ($admin->role === 'manager' && $validated['role'] === 'manager') {
            return redirect()->back()->with('error', 'Managers can only manage employees');
        }

        $targetAdmin->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'company_name' => $validated['company_name'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        if ($request->filled('password')) {
            $targetAdmin->update(['password' => Hash::make($validated['password'])]);
        }

        // Update store permissions
        $targetAdmin->stores()->detach();
        if ($request->filled('store_ids')) {
            foreach ($request->input('store_ids') as $key => $storeId) {
                $accessLevel = $request->input('access_levels')[$key] ?? 'view';
                $targetAdmin->stores()->attach($storeId, ['access_level' => $accessLevel]);
            }
        }

        return redirect()->route('admins.index')->with('success', 'Admin updated successfully');
    }

    public function destroy($id)
    {
        $admin = Auth::guard('admin')->user();
        $targetAdmin = Admin::findOrFail($id);

        if (!$admin->canManage($id)) {
            return redirect()->route('admins.index')->with('error', 'Unauthorized');
        }

        $targetAdmin->delete();

        return redirect()->route('admins.index')->with('success', 'Admin deleted successfully');
    }
}
