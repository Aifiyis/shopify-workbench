<?php
/**
 * 用户管理模块测试脚本
 * 使用此脚本验证用户层级结构
 */

// 在 php artisan tinker 中运行以下命令：

/*
// 1. 创建测试数据
$superAdmin = \App\Models\Admin::create([
    'name' => 'Super Admin',
    'email' => 'super@example.com',
    'password' => \Illuminate\Support\Facades\Hash::make('password123'),
    'role' => 'super',
    'company_name' => 'Head Office',
    'is_active' => true,
]);

$manager = \App\Models\Admin::create([
    'name' => 'Manager 1',
    'email' => 'manager1@example.com',
    'password' => \Illuminate\Support\Facades\Hash::make('password123'),
    'role' => 'manager',
    'company_name' => 'Branch A',
    'parent_admin_id' => $superAdmin->id,
    'is_active' => true,
]);

$employee = \App\Models\Admin::create([
    'name' => 'Employee 1',
    'email' => 'employee1@example.com',
    'password' => \Illuminate\Support\Facades\Hash::make('password123'),
    'role' => 'employee',
    'company_name' => 'Branch A',
    'parent_admin_id' => $manager->id,
    'is_active' => true,
]);

// 2. 验证层级关系
echo "Super Admin subordinates: " . $superAdmin->subordinates()->count() . "\n"; // 应该是 1 (manager)
echo "Manager subordinates: " . $manager->subordinates()->count() . "\n"; // 应该是 1 (employee)
echo "Employee parent: " . ($employee->parent()->first() ? $employee->parent()->first()->name : 'None') . "\n"; // 应该是 "Manager 1"

// 3. 验证权限检查
echo "Super admin can manage manager: " . ($superAdmin->canManage($manager->id) ? 'Yes' : 'No') . "\n"; // Yes
echo "Super admin can manage employee: " . ($superAdmin->canManage($employee->id) ? 'Yes' : 'No') . "\n"; // No (direct relation only for non-super)
echo "Manager can manage employee: " . ($manager->canManage($employee->id) ? 'Yes' : 'No') . "\n"; // Yes
echo "Manager can manage manager: " . ($manager->canManage($superAdmin->id) ? 'Yes' : 'No') . "\n"; // No
echo "Employee can manage anyone: " . ($employee->canManage($manager->id) ? 'Yes' : 'No') . "\n"; // No
*/

?>
