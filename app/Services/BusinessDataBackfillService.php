<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Permission;
use App\Models\Position;
use App\Models\ProductProcessingCraft;
use App\Models\ProductType;
use App\Models\SkuMatchProductType;
use Illuminate\Support\Facades\DB;

class BusinessDataBackfillService
{
    private const POSITIONS = [
        'advertising' => '广告',
        'operations' => '运营',
        'procurement' => '采购',
        'order_processing' => '订单处理',
        'artwork_processing' => '图画处理',
    ];

    private const PERMISSIONS = [
        'sku_product_types.manage' => 'SKU产品类型管理',
        'order_processing.manage' => '订单处理配置管理',
        'processing_crafts.manage' => '工艺层级管理',
        'admin_accounts.manage' => '管理员账号管理',
        'employees.manage' => '员工档案管理',
        'positions.manage' => '职位管理',
        'permissions.assign' => '权限分配',
    ];

    private const POSITION_PERMISSIONS = [
        'advertising' => ['sku_product_types.manage'],
        'operations' => ['sku_product_types.manage'],
        'procurement' => ['order_processing.manage', 'processing_crafts.manage'],
        'order_processing' => ['order_processing.manage', 'processing_crafts.manage'],
        'artwork_processing' => ['order_processing.manage', 'processing_crafts.manage'],
    ];

    /**
     * Backfill legacy business data and return stable total counts.
     *
     * Keys: product_types, employees, links, unresolved_values, permissions.
     */
    public function run(): array
    {
        return DB::transaction(function () {
            $positions = $this->seedPositions();
            $permissions = $this->seedPermissions();

            $this->assignDefaultPermissions($positions, $permissions);
            $this->assignManagerPermissions($permissions);
            $this->backfillProductTypes();

            $unresolvedValues = $this->backfillEmployees($positions);

            return [
                'product_types' => ProductType::count(),
                'employees' => Employee::count(),
                'links' => $this->countEmployeeLinks(),
                'unresolved_values' => $unresolvedValues,
                'permissions' => Permission::count(),
            ];
        });
    }

    private function seedPositions(): array
    {
        $positions = [];

        foreach (self::POSITIONS as $code => $name) {
            $position = Position::withTrashed()->firstOrCreate(
                ['code' => $code],
                ['name' => $name, 'is_active' => true]
            );

            $position->name = $name;
            $position->is_active = true;
            $position->save();

            if ($position->trashed()) {
                $position->restore();
            }

            $positions[$code] = $position;
        }

        return $positions;
    }

    private function seedPermissions(): array
    {
        $permissions = [];

        foreach (self::PERMISSIONS as $code => $name) {
            $permissions[$code] = Permission::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'is_delegable' => $code !== 'permissions.assign',
                ]
            );
        }

        return $permissions;
    }

    private function assignDefaultPermissions(array $positions, array $permissions): void
    {
        foreach (self::POSITION_PERMISSIONS as $positionCode => $permissionCodes) {
            $permissionIds = [];

            foreach ($permissionCodes as $permissionCode) {
                $permissionIds[] = $permissions[$permissionCode]->id;
            }

            $positions[$positionCode]->permissions()->syncWithoutDetaching($permissionIds);
        }
    }

    private function assignManagerPermissions(array $permissions): void
    {
        foreach ($permissions as $permission) {
            DB::table('role_permission')->updateOrInsert(
                [
                    'role' => 'manager',
                    'permission_id' => $permission->id,
                ],
                [
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    private function backfillProductTypes(): void
    {
        $names = DB::table('sku_match_product_type')
            ->select('chinese_name')
            ->union(DB::table('product_processing_craft')->select('chinese_name'))
            ->pluck('chinese_name');

        foreach ($names as $name) {
            $productType = ProductType::withTrashed()->firstOrCreate([
                'chinese_name' => $name,
            ]);

            if ($productType->trashed()) {
                $productType->restore();
            }
        }

        ProductType::query()->chunkById(100, function ($productTypes) {
            foreach ($productTypes as $productType) {
                DB::table('sku_match_product_type')
                    ->where('chinese_name', $productType->chinese_name)
                    ->update(['product_type_id' => $productType->id]);

                DB::table('product_processing_craft')
                    ->where('chinese_name', $productType->chinese_name)
                    ->update(['product_type_id' => $productType->id]);
            }
        });
    }

    private function backfillEmployees(array $positions): int
    {
        $unresolvedValues = 0;

        SkuMatchProductType::withTrashed()->chunkById(100, function ($skuMatches) use (
            $positions,
            &$unresolvedValues
        ) {
            foreach ($skuMatches as $skuMatch) {
                $employee = $this->employeeForValue(
                    $skuMatch->product_lister,
                    $positions['advertising'],
                    $unresolvedValues
                );

                $skuMatch->product_lister_employee_id = $employee ? $employee->id : null;
                $skuMatch->save();
            }
        });

        ProductProcessingCraft::withTrashed()->chunkById(100, function ($processingCrafts) use (
            $positions,
            &$unresolvedValues
        ) {
            foreach ($processingCrafts as $processingCraft) {
                $orderEmployee = $this->employeeForValue(
                    $processingCraft->order_processor,
                    $positions['order_processing'],
                    $unresolvedValues
                );
                $artworkEmployee = $this->employeeForValue(
                    $processingCraft->artwork_processor,
                    $positions['artwork_processing'],
                    $unresolvedValues
                );

                $procurementName = $processingCraft->procurement_processor;
                $settlementMethod = null;

                if (is_string($procurementName) && preg_match(
                    '/^(.+?)（([^）]+)）$/u',
                    trim($procurementName),
                    $matches
                )) {
                    $procurementName = $matches[1];
                    $settlementMethod = trim($matches[2]);
                }

                $procurementEmployee = $this->employeeForValue(
                    $procurementName,
                    $positions['procurement'],
                    $unresolvedValues
                );

                $processingCraft->order_processor_employee_id = $orderEmployee
                    ? $orderEmployee->id
                    : null;
                $processingCraft->artwork_processor_employee_id = $artworkEmployee
                    ? $artworkEmployee->id
                    : null;
                $processingCraft->procurement_processor_employee_id = $procurementEmployee
                    ? $procurementEmployee->id
                    : null;

                if ($settlementMethod !== null) {
                    $processingCraft->settlement_method = $settlementMethod;
                }

                $processingCraft->save();
            }
        });

        return $unresolvedValues;
    }

    private function employeeForValue($value, Position $position, int &$unresolvedValues)
    {
        $name = is_string($value) ? trim($value) : '';

        if ($name === '' || strpos($name, '/') !== false || $name === '预览图') {
            $unresolvedValues++;

            return null;
        }

        $employee = Employee::withTrashed()->firstOrCreate(
            ['name' => $name],
            ['is_active' => true]
        );

        $employee->is_active = true;
        $employee->save();

        if ($employee->trashed()) {
            $employee->restore();
        }

        $employee->positions()->syncWithoutDetaching([$position->id]);

        return $employee;
    }

    private function countEmployeeLinks(): int
    {
        return DB::table('sku_match_product_type')
                ->whereNotNull('product_lister_employee_id')
                ->count()
            + DB::table('product_processing_craft')
                ->whereNotNull('order_processor_employee_id')
                ->count()
            + DB::table('product_processing_craft')
                ->whereNotNull('artwork_processor_employee_id')
                ->count()
            + DB::table('product_processing_craft')
                ->whereNotNull('procurement_processor_employee_id')
                ->count();
    }
}
