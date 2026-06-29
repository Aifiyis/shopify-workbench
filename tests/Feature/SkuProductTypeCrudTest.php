<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Position;
use App\Models\ProductProcessingCraft;
use App\Models\ProductType;
use App\Models\SkuMatchProductType;
use App\Services\SkuCleaningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class SkuProductTypeCrudTest extends TestCase
{
    use RefreshDatabase;

    private $positions = [];
    private $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $managePermission = Permission::create([
            'name' => '管理 SKU 与产品类型',
            'code' => 'sku_product_types.manage',
            'is_delegable' => true,
        ]);

        foreach ([
            'advertising' => '广告',
            'operations' => '运营',
            'finance' => '财务',
        ] as $code => $name) {
            $position = Position::create([
                'name' => $name,
                'code' => $code,
                'is_active' => true,
            ]);
            if (in_array($code, ['advertising', 'operations'], true)) {
                $position->permissions()->attach($managePermission->id);
            }
            $this->positions[$code] = $position;
        }
    }

    public function test_sqlite_migration_keeps_all_columns_and_indexes_without_legacy_foreign_key()
    {
        $productType = ProductType::create(['chinese_name' => '独立产品类型']);
        $lister = $this->createEmployee('独立上品人', 'advertising');
        $sku = SkuMatchProductType::create([
            'original_sku' => 'INDEPENDENT-RAW',
            'cleaned_sku' => 'INDEPENDENT-CLEAN',
            'product_type_id' => $productType->id,
            'chinese_name' => $productType->chinese_name,
            'product_lister' => $lister->name,
            'product_lister_employee_id' => $lister->id,
        ]);
        $sku->delete();

        $this->assertSame(0, ProductProcessingCraft::count());
        $this->assertDatabaseHas('sku_match_product_type', [
            'id' => $sku->id,
            'original_sku' => 'INDEPENDENT-RAW',
            'cleaned_sku' => 'INDEPENDENT-CLEAN',
            'product_type_id' => $productType->id,
            'chinese_name' => '独立产品类型',
            'product_lister' => '独立上品人',
            'product_lister_employee_id' => $lister->id,
        ]);

        $columns = collect(DB::select('PRAGMA table_info("sku_match_product_type")'))
            ->pluck('name')
            ->all();
        $this->assertSame([
            'id',
            'original_sku',
            'cleaned_sku',
            'product_type_id',
            'chinese_name',
            'product_lister',
            'product_lister_employee_id',
            'created_at',
            'updated_at',
            'deleted_at',
        ], $columns);

        $indexes = collect(DB::select('PRAGMA index_list("sku_match_product_type")'));
        $this->assertEqualsCanonicalizing([
            'sku_match_product_type_original_sku_unique',
            'sku_match_product_type_cleaned_sku_index',
            'sku_match_product_type_product_type_id_index',
            'sku_match_product_type_chinese_name_index',
            'sku_match_product_type_product_lister_employee_id_index',
        ], $indexes->pluck('name')->all());
        $this->assertSame(
            1,
            (int) $indexes->firstWhere('name', 'sku_match_product_type_original_sku_unique')->unique
        );
        $this->assertEmpty(DB::select('PRAGMA foreign_key_list("sku_match_product_type")'));

        foreach ([
            'cleaned_sku' => ['INDEPENDENT-CLEAN', 'sku_match_product_type_cleaned_sku_index'],
            'product_type_id' => [$productType->id, 'sku_match_product_type_product_type_id_index'],
            'chinese_name' => ['独立产品类型', 'sku_match_product_type_chinese_name_index'],
            'product_lister_employee_id' => [$lister->id, 'sku_match_product_type_product_lister_employee_id_index'],
        ] as $column => $expectation) {
            $plan = DB::select(
                'EXPLAIN QUERY PLAN SELECT * FROM "sku_match_product_type" WHERE "'.$column.'" = ?',
                [$expectation[0]]
            );
            $this->assertStringContainsString($expectation[1], $plan[0]->detail);
        }
    }

    public function test_active_employee_can_search_all_sku_fields_and_results_paginate_by_fifty()
    {
        $viewer = $this->createActor('finance');
        $defaultType = ProductType::create(['chinese_name' => '默认类型']);

        for ($index = 1; $index <= 51; $index++) {
            SkuMatchProductType::create([
                'original_sku' => 'PAGE-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
                'cleaned_sku' => 'PAGE-CLEAN-'.$index,
                'product_type_id' => $defaultType->id,
                'chinese_name' => $defaultType->chinese_name,
            ]);
        }

        $relatedType = ProductType::create(['chinese_name' => '关系命中类型']);
        $relatedLister = $this->createEmployee('关系命中上品人', 'advertising');
        $records = [
            '原始命中词' => SkuMatchProductType::create([
                'original_sku' => '原始命中词',
                'cleaned_sku' => 'ORIGINAL-CLEAN',
                'product_type_id' => $defaultType->id,
                'chinese_name' => $defaultType->chinese_name,
            ]),
            '清洗命中词' => SkuMatchProductType::create([
                'original_sku' => 'CLEANED-RAW',
                'cleaned_sku' => '清洗命中词',
                'product_type_id' => $defaultType->id,
                'chinese_name' => $defaultType->chinese_name,
            ]),
            '关系命中类型' => SkuMatchProductType::create([
                'original_sku' => 'TYPE-RAW',
                'cleaned_sku' => 'TYPE-CLEAN',
                'product_type_id' => $relatedType->id,
                'chinese_name' => '旧产品类型快照',
            ]),
            '关系命中上品人' => SkuMatchProductType::create([
                'original_sku' => 'LISTER-RAW',
                'cleaned_sku' => 'LISTER-CLEAN',
                'product_type_id' => $defaultType->id,
                'chinese_name' => $defaultType->chinese_name,
                'product_lister_employee_id' => $relatedLister->id,
                'product_lister' => '旧上品人快照',
            ]),
        ];

        $this->actingAs($viewer, 'admin')
            ->get(route('sku-product-types.index'))
            ->assertOk()
            ->assertViewHas('skuMatches', function ($paginator) {
                return $paginator->perPage() === 50
                    && $paginator->count() === 50
                    && $paginator->lastPage() === 2
                    && $paginator->getPageName() === 'sku_page';
            });

        foreach ($records as $search => $record) {
            $this->actingAs($viewer, 'admin')
                ->get(route('sku-product-types.index', ['search' => $search]))
                ->assertOk()
                ->assertViewHas('skuMatches', function ($paginator) use ($record) {
                    return $paginator->total() === 1
                        && $paginator->first()->is($record);
                });
        }
    }

    public function test_sku_and_product_type_lists_use_independent_configurable_pagination()
    {
        $viewer = $this->createActor('finance');
        $skuType = ProductType::create(['chinese_name' => '分页 SKU 类型']);

        for ($index = 1; $index <= 45; $index++) {
            $this->createSku(
                $skuType,
                'PAGER-SKU-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT)
            );
        }
        for ($index = 1; $index <= 25; $index++) {
            ProductType::create([
                'chinese_name' => 'TYPE-PAGER-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
            ]);
        }

        $this->actingAs($viewer, 'admin')
            ->get(route('sku-product-types.index', [
                'tab' => 'skus',
                'search' => 'PAGER-SKU',
                'sku_page' => 2,
                'sku_per_page' => 20,
            ]))
            ->assertOk()
            ->assertViewHas('skuMatches', function ($paginator) {
                return $paginator->perPage() === 20
                    && $paginator->currentPage() === 2
                    && $paginator->count() === 20
                    && str_contains($paginator->nextPageUrl(), 'search=PAGER-SKU')
                    && str_contains($paginator->nextPageUrl(), 'sku_per_page=20');
            })
            ->assertSee('name="sku_per_page"', false)
            ->assertSee('name="sku_page"', false);

        $this->actingAs($viewer, 'admin')
            ->get(route('sku-product-types.index', [
                'tab' => 'types',
                'search' => 'TYPE-PAGER',
                'type_page' => 2,
                'type_per_page' => 20,
            ]))
            ->assertOk()
            ->assertViewHas('productTypes', function ($paginator) {
                return $paginator->perPage() === 20
                    && $paginator->currentPage() === 2
                    && $paginator->count() === 5
                    && str_contains($paginator->previousPageUrl(), 'type_per_page=20');
            })
            ->assertSee('name="type_per_page"', false)
            ->assertSee('name="type_page"', false);

        $this->actingAs($viewer, 'admin')
            ->get(route('sku-product-types.index', ['sku_per_page' => 999]))
            ->assertOk()
            ->assertViewHas('skuMatches', function ($paginator) {
                return $paginator->perPage() === 50;
            });
    }

    public function test_bulk_dialog_and_create_only_clean_button_follow_management_permission()
    {
        $manager = $this->createActor('advertising');
        $viewer = $this->createActor('finance');
        $type = ProductType::create(['chinese_name' => '界面权限类型']);
        $first = $this->createSku($type, 'UI-BULK-1');
        $second = $this->createSku($type, 'UI-BULK-2');
        $first->update(['cleaned_sku' => 'UI-SHARED-CLEAN']);
        $second->update(['cleaned_sku' => 'UI-SHARED-CLEAN']);

        $this->actingAs($manager, 'admin')
            ->get(route('sku-product-types.index', [
                'search' => 'UI-SHARED-CLEAN',
                'sku_per_page' => 20,
            ]))
            ->assertOk()
            ->assertSee(route('sku-product-types.bulk-update'), false)
            ->assertSee('data-sku-bulk-checkbox', false)
            ->assertSee('data-cleaned-sku="UI-SHARED-CLEAN"', false)
            ->assertSee('return_query[search]', false)
            ->assertSee('return_query[sku_per_page]', false);

        $this->actingAs($viewer, 'admin')
            ->get(route('sku-product-types.index', ['search' => 'UI-SHARED-CLEAN']))
            ->assertOk()
            ->assertDontSee('data-sku-bulk-checkbox', false)
            ->assertDontSee(route('sku-product-types.bulk-update'), false);

        $this->actingAs($manager, 'admin')
            ->get(route('sku-product-types.create'))
            ->assertOk()
            ->assertSee('data-sku-clean-trigger', false)
            ->assertSee(route('sku-product-types.clean-sku'), false);

        $this->actingAs($manager, 'admin')
            ->get(route('sku-product-types.edit', $first))
            ->assertOk()
            ->assertDontSee('data-sku-clean-trigger', false);
    }

    public function test_advertising_and_operations_actors_create_update_and_soft_delete_sku_snapshots()
    {
        foreach (['advertising', 'operations'] as $code) {
            $actor = $this->createActor($code);
            $initialType = ProductType::create(['chinese_name' => $code.' 初始类型']);
            $updatedType = ProductType::create(['chinese_name' => $code.' 更新类型']);
            $initialLister = $this->createEmployee($code.' 初始上品人', 'advertising');
            $updatedLister = $this->createEmployee($code.' 更新上品人', 'operations');
            $originalSku = strtoupper($code).'-RAW';

            $this->actingAs($actor, 'admin')
                ->post(route('sku-product-types.store'), [
                    'original_sku' => '  '.$originalSku.'  ',
                    'cleaned_sku' => '  '.strtoupper($code).'-CLEAN  ',
                    'product_type_id' => $initialType->id,
                    'product_lister_employee_id' => $initialLister->id,
                ])
                ->assertRedirect(route('sku-product-types.index'));

            $sku = SkuMatchProductType::where('original_sku', $originalSku)->firstOrFail();
            $this->assertSame($initialType->chinese_name, $sku->chinese_name);
            $this->assertSame($initialLister->name, $sku->product_lister);

            $this->actingAs($actor, 'admin')
                ->put(route('sku-product-types.update', $sku), [
                    'original_sku' => $originalSku,
                    'cleaned_sku' => strtoupper($code).'-UPDATED',
                    'product_type_id' => $updatedType->id,
                    'product_lister_employee_id' => $updatedLister->id,
                ])
                ->assertRedirect(route('sku-product-types.index'));

            $sku->refresh();
            $this->assertEquals($updatedType->id, $sku->product_type_id);
            $this->assertSame($updatedType->chinese_name, $sku->chinese_name);
            $this->assertEquals($updatedLister->id, $sku->product_lister_employee_id);
            $this->assertSame($updatedLister->name, $sku->product_lister);

            $this->actingAs($actor, 'admin')
                ->delete(route('sku-product-types.destroy', $sku))
                ->assertRedirect(route('sku-product-types.index'));
            $this->assertSoftDeleted('sku_match_product_type', ['id' => $sku->id]);

            $this->actingAs($actor, 'admin')
                ->post(route('sku-product-types.store'), [
                    'original_sku' => $originalSku,
                    'cleaned_sku' => strtoupper($code).'-DUPLICATE',
                    'product_type_id' => $updatedType->id,
                ])
                ->assertSessionHasErrors('original_sku');
        }
    }

    public function test_employee_without_management_permission_gets_403_for_mutations()
    {
        $actor = $this->createActor('finance');
        $productType = ProductType::create(['chinese_name' => '受保护类型']);
        $sku = SkuMatchProductType::create([
            'original_sku' => 'PROTECTED-RAW',
            'cleaned_sku' => 'PROTECTED-CLEAN',
            'product_type_id' => $productType->id,
            'chinese_name' => $productType->chinese_name,
        ]);

        $this->actingAs($actor, 'admin')
            ->post(route('sku-product-types.store'), [
                'original_sku' => 'DENIED-RAW',
                'cleaned_sku' => 'DENIED-CLEAN',
                'product_type_id' => $productType->id,
            ])
            ->assertForbidden();
        $this->actingAs($actor, 'admin')
            ->put(route('sku-product-types.update', $sku), [
                'original_sku' => $sku->original_sku,
                'cleaned_sku' => 'DENIED-UPDATE',
                'product_type_id' => $productType->id,
            ])
            ->assertForbidden();
        $this->actingAs($actor, 'admin')
            ->delete(route('sku-product-types.destroy', $sku))
            ->assertForbidden();
        $this->actingAs($actor, 'admin')
            ->post(route('product-types.store'), ['chinese_name' => '拒绝创建'])
            ->assertForbidden();
        $this->actingAs($actor, 'admin')
            ->postJson(route('product-types.quick-store'), ['chinese_name' => '拒绝快捷创建'])
            ->assertForbidden();
        $this->actingAs($actor, 'admin')
            ->put(route('product-types.update', $productType), ['chinese_name' => '拒绝更新'])
            ->assertForbidden();
        $this->actingAs($actor, 'admin')
            ->delete(route('product-types.destroy', $productType))
            ->assertForbidden();
    }

    public function test_lister_must_be_active_and_hold_an_active_advertising_or_operations_position()
    {
        $actor = $this->createActor('operations');
        $productType = ProductType::create(['chinese_name' => '上品人校验类型']);
        $eligible = $this->createEmployee('合格上品人', 'operations');
        $inactiveEmployee = $this->createEmployee('离职上品人', 'advertising', false);
        $wrongPosition = $this->createEmployee('财务上品人', 'finance');
        $deletedEmployee = $this->createEmployee('已删除上品人', 'advertising');
        $deletedEmployee->delete();

        foreach ([$inactiveEmployee, $wrongPosition, $deletedEmployee] as $index => $candidate) {
            $this->actingAs($actor, 'admin')
                ->post(route('sku-product-types.store'), [
                    'original_sku' => 'INVALID-LISTER-'.$index,
                    'cleaned_sku' => 'INVALID-LISTER-'.$index,
                    'product_type_id' => $productType->id,
                    'product_lister_employee_id' => $candidate->id,
                ])
                ->assertSessionHasErrors('product_lister_employee_id');
        }

        $positionCandidate = $this->createEmployee('职位停用上品人', 'advertising');
        $this->positions['advertising']->update(['is_active' => false]);
        $this->actingAs($actor, 'admin')
            ->post(route('sku-product-types.store'), [
                'original_sku' => 'INACTIVE-POSITION',
                'cleaned_sku' => 'INACTIVE-POSITION',
                'product_type_id' => $productType->id,
                'product_lister_employee_id' => $positionCandidate->id,
            ])
            ->assertSessionHasErrors('product_lister_employee_id');

        $this->actingAs($actor, 'admin')
            ->post(route('sku-product-types.store'), [
                'original_sku' => 'VALID-LISTER',
                'cleaned_sku' => 'VALID-LISTER',
                'product_type_id' => $productType->id,
                'product_lister_employee_id' => $eligible->id,
            ])
            ->assertRedirect(route('sku-product-types.index'));
        $this->assertDatabaseHas('sku_match_product_type', [
            'original_sku' => 'VALID-LISTER',
            'product_lister_employee_id' => $eligible->id,
            'product_lister' => $eligible->name,
        ]);

        $deletedType = ProductType::create(['chinese_name' => '已删除产品类型']);
        $deletedType->delete();
        $this->actingAs($actor, 'admin')
            ->post(route('sku-product-types.store'), [
                'original_sku' => 'DELETED-TYPE',
                'cleaned_sku' => 'DELETED-TYPE',
                'product_type_id' => $deletedType->id,
            ])
            ->assertSessionHasErrors('product_type_id');
    }

    public function test_product_type_quick_create_returns_json_and_reports_active_or_deleted_duplicates()
    {
        $actor = $this->createActor('advertising');

        $response = $this->actingAs($actor, 'admin')
            ->postJson(route('product-types.quick-store'), [
                'chinese_name' => '  快捷产品类型  ',
            ])
            ->assertOk()
            ->assertJsonStructure(['id', 'chinese_name'])
            ->assertJson(['chinese_name' => '快捷产品类型']);

        $productType = ProductType::findOrFail($response->json('id'));
        $this->assertSame(0, ProductProcessingCraft::count());

        $this->actingAs($actor, 'admin')
            ->postJson(route('product-types.quick-store'), ['chinese_name' => '快捷产品类型'])
            ->assertStatus(422)
            ->assertJson([
                'message' => '该产品类型已存在，请前往编辑。',
                'edit_url' => route('product-types.edit', $productType->id),
            ]);

        $productType->delete();
        $this->actingAs($actor, 'admin')
            ->postJson(route('product-types.quick-store'), ['chinese_name' => '快捷产品类型'])
            ->assertStatus(422)
            ->assertJson([
                'message' => '该产品类型存在于已删除记录中，不能重复创建。',
            ])
            ->assertJsonMissing(['edit_url' => route('product-types.edit', $productType->id)]);
    }

    public function test_product_type_rename_updates_active_and_trashed_legacy_snapshots()
    {
        $actor = $this->createActor('operations');
        $activeType = ProductType::create(['chinese_name' => '活动旧名称']);
        $trashedType = ProductType::create(['chinese_name' => '删除旧名称']);
        $activeSku = $this->createSku($activeType, 'ACTIVE-RENAME');
        $trashedSku = $this->createSku($trashedType, 'TRASHED-RENAME');
        $trashedSku->delete();
        $activeConfig = ProductProcessingCraft::create([
            'chinese_name' => $activeType->chinese_name,
            'product_type_id' => $activeType->id,
        ]);
        $trashedConfig = ProductProcessingCraft::create([
            'chinese_name' => $trashedType->chinese_name,
            'product_type_id' => $trashedType->id,
        ]);
        $trashedConfig->delete();

        $this->actingAs($actor, 'admin')
            ->put(route('product-types.update', $activeType), ['chinese_name' => '活动新名称'])
            ->assertRedirect(route('sku-product-types.index', ['tab' => 'types']));
        $this->actingAs($actor, 'admin')
            ->put(route('product-types.update', $trashedType), ['chinese_name' => '删除新名称'])
            ->assertRedirect(route('sku-product-types.index', ['tab' => 'types']));

        $this->assertSame('活动新名称', $activeSku->fresh()->chinese_name);
        $this->assertSame('活动新名称', $activeConfig->fresh()->chinese_name);
        $this->assertSame('删除新名称', SkuMatchProductType::withTrashed()->findOrFail($trashedSku->id)->chinese_name);
        $this->assertSame('删除新名称', ProductProcessingCraft::withTrashed()->findOrFail($trashedConfig->id)->chinese_name);
    }

    public function test_product_type_delete_is_blocked_by_active_or_trashed_sku_and_config_references()
    {
        $actor = $this->createActor('advertising');
        $types = [
            '活动 SKU' => ProductType::create(['chinese_name' => '活动 SKU 引用']),
            '删除 SKU' => ProductType::create(['chinese_name' => '删除 SKU 引用']),
            '活动配置' => ProductType::create(['chinese_name' => '活动配置引用']),
            '删除配置' => ProductType::create(['chinese_name' => '删除配置引用']),
        ];

        $this->createSku($types['活动 SKU'], 'ACTIVE-SKU-REFERENCE');
        $trashedSku = $this->createSku($types['删除 SKU'], 'TRASHED-SKU-REFERENCE');
        $trashedSku->delete();
        ProductProcessingCraft::create([
            'chinese_name' => $types['活动配置']->chinese_name,
            'product_type_id' => $types['活动配置']->id,
        ]);
        $trashedConfig = ProductProcessingCraft::create([
            'chinese_name' => $types['删除配置']->chinese_name,
            'product_type_id' => $types['删除配置']->id,
        ]);
        $trashedConfig->delete();

        foreach ($types as $type) {
            $this->actingAs($actor, 'admin')
                ->delete(route('product-types.destroy', $type))
                ->assertRedirect(route('sku-product-types.index', ['tab' => 'types']))
                ->assertSessionHas('error');
            $this->assertFalse($type->fresh()->trashed());
        }

        $unreferenced = ProductType::create(['chinese_name' => '可删除类型']);
        $this->actingAs($actor, 'admin')
            ->delete(route('product-types.destroy', $unreferenced))
            ->assertRedirect(route('sku-product-types.index', ['tab' => 'types']));
        $this->assertSoftDeleted('product_types', ['id' => $unreferenced->id]);
    }

    public function test_soft_deleted_rows_are_hidden_and_navigation_routes_work()
    {
        $viewer = $this->createActor('finance');
        $activeType = ProductType::create(['chinese_name' => '列表活动类型']);
        $hiddenType = ProductType::create(['chinese_name' => '列表删除类型']);
        $activeSku = $this->createSku($activeType, 'LIST-ACTIVE-SKU');
        $hiddenSku = $this->createSku($activeType, 'LIST-HIDDEN-SKU');
        $hiddenSku->delete();
        $hiddenType->delete();

        $this->actingAs($viewer, 'admin')
            ->get(route('sku-product-types.index'))
            ->assertOk()
            ->assertSee($activeSku->original_sku)
            ->assertDontSee($hiddenSku->original_sku)
            ->assertSee('SKU 产品类型')
            ->assertSee(route('sku-product-types.index'), false);

        $this->actingAs($viewer, 'admin')
            ->get(route('sku-product-types.index', ['tab' => 'types']))
            ->assertOk()
            ->assertSee($activeType->chinese_name)
            ->assertDontSee($hiddenType->chinese_name);

        $this->actingAs($viewer, 'admin')
            ->get(route('product-types.index'))
            ->assertRedirect(route('sku-product-types.index', ['tab' => 'types']));
    }

    public function test_bulk_update_changes_same_cleaned_sku_snapshots_and_preserves_return_query()
    {
        $actor = $this->createActor('advertising');
        $initialType = ProductType::create(['chinese_name' => '批量初始类型']);
        $updatedType = ProductType::create(['chinese_name' => '批量更新类型']);
        $lister = $this->createEmployee('批量上品人', 'operations');
        $first = $this->createSku($initialType, 'BULK-RAW-1');
        $second = $this->createSku($initialType, 'BULK-RAW-2');
        $first->update(['cleaned_sku' => 'BULK-SHARED-CLEAN']);
        $second->update(['cleaned_sku' => 'BULK-SHARED-CLEAN']);
        $returnQuery = [
            'tab' => 'skus',
            'search' => 'BULK-SHARED-CLEAN',
            'product_type_id' => $initialType->id,
            'sku_page' => 2,
            'sku_per_page' => 20,
        ];

        $this->actingAs($actor, 'admin')
            ->post(route('sku-product-types.bulk-update'), [
                'sku_ids' => [$first->id, $second->id],
                'product_type_id' => $updatedType->id,
                'product_lister_employee_id' => $lister->id,
                'return_query' => $returnQuery,
            ])
            ->assertRedirect(route('sku-product-types.index', $returnQuery))
            ->assertSessionHas('success');

        foreach ([$first, $second] as $sku) {
            $sku->refresh();
            $this->assertEquals($updatedType->id, $sku->product_type_id);
            $this->assertSame($updatedType->chinese_name, $sku->chinese_name);
            $this->assertEquals($lister->id, $sku->product_lister_employee_id);
            $this->assertSame($lister->name, $sku->product_lister);
            $this->assertSame('BULK-SHARED-CLEAN', $sku->cleaned_sku);
        }
    }

    public function test_bulk_update_rejects_mixed_deleted_duplicate_ineligible_and_unauthorized_records()
    {
        $actor = $this->createActor('operations');
        $viewer = $this->createActor('finance');
        $type = ProductType::create(['chinese_name' => '批量校验类型']);
        $first = $this->createSku($type, 'VALIDATION-RAW-1');
        $second = $this->createSku($type, 'VALIDATION-RAW-2');
        $deleted = $this->createSku($type, 'VALIDATION-DELETED');
        $deleted->delete();
        $ineligible = $this->createEmployee('无效批量上品人', 'finance');

        $this->actingAs($actor, 'admin')
            ->post(route('sku-product-types.bulk-update'), [
                'sku_ids' => [$first->id, $second->id],
                'product_type_id' => $type->id,
            ])
            ->assertSessionHasErrors('sku_ids');

        $this->actingAs($actor, 'admin')
            ->post(route('sku-product-types.bulk-update'), [
                'sku_ids' => [$first->id, $deleted->id],
                'product_type_id' => $type->id,
            ])
            ->assertSessionHasErrors('sku_ids.1');

        $this->actingAs($actor, 'admin')
            ->post(route('sku-product-types.bulk-update'), [
                'sku_ids' => [$first->id, $first->id],
                'product_type_id' => $type->id,
            ])
            ->assertSessionHasErrors('sku_ids.1');

        $first->update(['cleaned_sku' => 'VALIDATION-SHARED']);
        $second->update(['cleaned_sku' => 'VALIDATION-SHARED']);
        $this->actingAs($actor, 'admin')
            ->post(route('sku-product-types.bulk-update'), [
                'sku_ids' => [$first->id, $second->id],
                'product_type_id' => $type->id,
                'product_lister_employee_id' => $ineligible->id,
            ])
            ->assertSessionHasErrors('product_lister_employee_id');

        $this->actingAs($viewer, 'admin')
            ->post(route('sku-product-types.bulk-update'), [
                'sku_ids' => [$first->id, $second->id],
                'product_type_id' => $type->id,
            ])
            ->assertForbidden();
    }

    public function test_authorized_actor_can_clean_sku_and_invalid_or_unauthorized_requests_fail()
    {
        $actor = $this->createActor('advertising');
        $viewer = $this->createActor('finance');
        $cleaner = Mockery::mock(SkuCleaningService::class);
        $cleaner->shouldReceive('cleanSkuUsingValuesAndPatterns')
            ->once()
            ->with('CS-QK1000-Blue-XL')
            ->andReturn('CS-QK1000');
        $this->app->instance(SkuCleaningService::class, $cleaner);

        $this->actingAs($actor, 'admin')
            ->postJson(route('sku-product-types.clean-sku'), [
                'original_sku' => '  CS-QK1000-Blue-XL  ',
            ])
            ->assertOk()
            ->assertJson(['cleaned_sku' => 'CS-QK1000']);

        $this->actingAs($actor, 'admin')
            ->postJson(route('sku-product-types.clean-sku'), ['original_sku' => '   '])
            ->assertStatus(422)
            ->assertJsonValidationErrors('original_sku');

        $this->actingAs($viewer, 'admin')
            ->postJson(route('sku-product-types.clean-sku'), [
                'original_sku' => 'CS-QK1000-Blue-XL',
            ])
            ->assertForbidden();
    }

    private function createActor($positionCode)
    {
        $this->sequence++;
        $admin = Admin::create([
            'name' => '测试账号 '.$this->sequence,
            'email' => 'sku-test-'.$this->sequence.'@example.test',
            'password' => 'test-password',
            'role' => 'employee',
            'is_active' => true,
        ]);
        $employee = Employee::create([
            'name' => $admin->name,
            'admin_id' => $admin->id,
            'is_active' => true,
        ]);
        $employee->positions()->attach($this->positions[$positionCode]->id);

        return $admin;
    }

    private function createEmployee($name, $positionCode, $isActive = true)
    {
        $employee = Employee::create([
            'name' => $name,
            'is_active' => $isActive,
        ]);
        $employee->positions()->attach($this->positions[$positionCode]->id);

        return $employee;
    }

    private function createSku(ProductType $productType, $originalSku)
    {
        return SkuMatchProductType::create([
            'original_sku' => $originalSku,
            'cleaned_sku' => $originalSku.'-CLEAN',
            'product_type_id' => $productType->id,
            'chinese_name' => $productType->chinese_name,
        ]);
    }
}
