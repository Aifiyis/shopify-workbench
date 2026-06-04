<?php

namespace App\Http\Controllers;

use App\Models\ShopifyStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    public function index(Request $request)
    {
        $store = $this->resolveStore($request);

        return view('reports.index', [
            'store' => $store,
            'reports' => $this->reports($store),
        ]);
    }

    public function create(Request $request)
    {
        $store = $this->resolveStore($request);

        return view('reports.form', [
            'store' => $store,
            'mode' => 'create',
            'report' => [
                'slug' => null,
                'name' => '',
                'description' => '',
                'fields' => $this->defaultSelectedFields(),
            ],
            'fieldGroups' => $this->fieldGroups(),
        ]);
    }

    public function store(Request $request)
    {
        $store = $this->resolveStore($request);

        $report = $this->reportFromRequest($request, null);
        $reports = session($this->sessionKey($store), []);
        $reports[$report['slug']] = $report;
        session([$this->sessionKey($store) => $reports]);

        return redirect()
            ->route('reports.index', ['store_id' => $store->id])
            ->with('status', 'Report saved.');
    }

    public function edit(Request $request, $report)
    {
        $store = $this->resolveStore($request);
        $reportData = $this->findReport($store, $report);

        return view('reports.form', [
            'store' => $store,
            'mode' => 'edit',
            'report' => $reportData,
            'fieldGroups' => $this->fieldGroups(),
        ]);
    }

    public function update(Request $request, $report)
    {
        $store = $this->resolveStore($request);
        $reports = session($this->sessionKey($store), []);
        $reports[$report] = $this->reportFromRequest($request, $report);
        session([$this->sessionKey($store) => $reports]);

        return redirect()
            ->route('reports.index', ['store_id' => $store->id])
            ->with('status', 'Report updated.');
    }

    public function schedule(Request $request, $report)
    {
        $store = $this->resolveStore($request);
        $reportData = $this->findReport($store, $report);
        $tab = $request->input('tab', 'create');

        if (!in_array($tab, ['create', 'scheduled', 'actual', 'history'], true)) {
            $tab = 'create';
        }

        return view('reports.schedule', [
            'store' => $store,
            'report' => $reportData,
            'reports' => $this->reports($store),
            'tab' => $tab,
            'actualSchedules' => $this->actualSchedules(),
            'reportHistory' => $this->reportHistory(),
        ]);
    }

    public function saveSchedule(Request $request, $report)
    {
        $store = $this->resolveStore($request);

        return redirect()
            ->route('reports.schedule', ['report' => $report, 'store_id' => $store->id, 'tab' => 'actual'])
            ->with('status', 'Schedule saved.');
    }

    private function resolveStore(Request $request): ShopifyStore
    {
        $storeId = $request->input('store_id');
        $store = ShopifyStore::findOrFail($storeId);
        $admin = Auth::guard('admin')->user();

        if (!$admin->canAccessStore($storeId)) {
            abort(403, 'Unauthorized access to this store');
        }

        return $store;
    }

    private function reports(ShopifyStore $store): array
    {
        $reports = [];

        foreach ($this->baseReports() as $report) {
            $reports[$report['slug']] = $report;
        }

        foreach (session($this->sessionKey($store), []) as $slug => $report) {
            $reports[$slug] = $report;
        }

        return array_values($reports);
    }

    private function baseReports(): array
    {
        return [
            [
                'slug' => 'factory',
                'name' => 'factory',
                'description' => '',
                'created_on' => 'Nov 10, 2024 01:00',
                'scheduled' => ['active' => 1, 'inactive' => 0],
                'fields' => $this->defaultSelectedFields(),
            ],
            [
                'slug' => 'shipping',
                'name' => 'shipping',
                'description' => '',
                'created_on' => 'Nov 9, 2024 23:42',
                'scheduled' => null,
                'fields' => ['Order Name', 'Order Date', 'Product Title', 'Quantity', 'SKU'],
            ],
            [
                'slug' => 'de_order_with_image',
                'name' => 'de_order_with_image',
                'description' => '',
                'created_on' => 'Nov 24, 2022 09:53',
                'scheduled' => ['active' => 1, 'inactive' => 1],
                'fields' => $this->defaultSelectedFields(),
            ],
            [
                'slug' => 'information',
                'name' => 'information',
                'description' => '',
                'created_on' => 'May 19, 2020 13:25',
                'scheduled' => null,
                'fields' => ['Order Name', 'Email', 'Product Title', 'Product Tags'],
            ],
        ];
    }

    private function findReport(ShopifyStore $store, string $slug): array
    {
        foreach ($this->reports($store) as $report) {
            if ($report['slug'] === $slug) {
                return $report;
            }
        }

        abort(404);
    }

    private function reportFromRequest(Request $request, ?string $existingSlug): array
    {
        $name = trim($request->input('name', 'Untitled report'));
        $slug = $existingSlug ?: strtolower(preg_replace('/[^A-Za-z0-9]+/', '_', $name));
        $slug = trim($slug, '_') ?: 'untitled_report';

        return [
            'slug' => $slug,
            'name' => $name ?: 'Untitled report',
            'description' => trim($request->input('description', '')),
            'created_on' => now()->format('M j, Y H:i'),
            'scheduled' => null,
            'fields' => array_values($request->input('selected_fields', [])),
        ];
    }

    private function sessionKey(ShopifyStore $store): string
    {
        return 'reports.store.' . $store->id;
    }

    private function defaultSelectedFields(): array
    {
        return [
            'Line Amount',
            'Order Date',
            'Order Name',
            'Order Line item Properties 1 Value',
            'Order Line item Properties 2 Value',
            'Order Line item Properties 3 Value',
            'Order Line item Properties 4 Value',
            'Product Title',
            'Variant Title',
            'Quantity',
            'Line Item ID',
            'Product Type',
            'Option 1',
            'Option 2',
            'Option 3',
            'Product Tags',
        ];
    }

    private function fieldGroups(): array
    {
        return [
            'Order' => [
                'Net Line Amount Estimate',
                'Line Amount',
                'Line Number',
                'Gift Card Amount Estimate',
                'Order Metafield 1 Title',
                'Order Metafield 1 Value',
                'Order Metafield 2 Title',
                'Order Metafield 2 Value',
                'Order Metafield 3 Title',
                'Order Metafield 3 Value',
                'Order Metafield 4 Title',
                'Order Metafield 4 Value',
                'Order Number',
                'Order Date',
                'Order Close Date',
                'Order Updated Date',
                'Order Cancelled Date',
                'Email',
                'Order Day',
                'Order Hour',
                'Order Month',
                'Order ID',
                'Order Name',
                'Order Number Sequence',
                'Order Processed Date',
                'Order Line item Properties 1 Title',
                'Order Line item Properties 1 Value',
                'Order Line item Properties 2 Title',
                'Order Line item Properties 2 Value',
                'Order Line item Properties 3 Title',
                'Order Line item Properties 3 Value',
                'Order Line item Properties 4 Title',
                'Order Line item Properties 4 Value',
                'Delivery Date',
            ],
            'Product' => [
                'Product Title',
                'Variant Title',
                'Product Name',
                'Product Price',
                'Product cost',
                'Quantity',
                'COGS',
                'SKU',
                'Grams',
                'Line Item ID',
                'Product ID',
                'Variant ID',
                'Vendor',
                'Product Type',
                'Net Quantity',
                'Product Description',
                'Barcode',
                'Body HTML',
                'Color',
                'Compare At price',
                'Product Created Date',
                'Custom Collections',
                'Smart Collections',
                'Deleted SKU',
                'Deleted Variant',
                'Handle',
                'Image Contents',
                'Image Url',
                'Inventory Management',
                'Inventory Policy',
                'Inventory Quantity',
                'Option 1',
                'Option 2',
                'Option 3',
                'Product Published Date',
                'Published Scope',
                'Product Size',
                'Style',
                'Order Status',
                'Product Tags',
                'Template Suffix',
                'URL',
                'Weight',
                'Weight Unit',
                'Product Metafield 1 Title',
                'Product Metafield 1 Value',
            ],
        ];
    }

    private function actualSchedules(): array
    {
        return [
            ['name' => 'factory', 'report' => 'factory', 'rule' => 'Daily', 'forever' => 'Yes', 'start' => 'Nov 30, 2025 09:00', 'end' => 'no end date', 'active' => true],
            ['name' => 'de_order_with_image', 'report' => 'de_order_with_image', 'rule' => 'Daily', 'forever' => 'Yes', 'start' => 'Dec 6, 2022 14:00', 'end' => 'no end date', 'active' => true],
            ['name' => 'de_order_with_image', 'report' => 'de_order_with_image', 'rule' => 'Daily', 'forever' => 'Yes', 'start' => 'Dec 4, 2022 14:30', 'end' => 'no end date', 'active' => false],
        ];
    }

    private function reportHistory(): array
    {
        return [
            ['name' => 'factory', 'type' => 'Scheduled', 'run' => 'May 30, 2026 09:05', 'start' => 'May 29, 2026 09:00', 'end' => 'May 30, 2026 09:00'],
            ['name' => 'de_order_with_image', 'type' => 'Scheduled', 'run' => 'May 29, 2026 14:05', 'start' => 'May 28, 2026 14:00', 'end' => 'May 29, 2026 14:00'],
            ['name' => 'factory', 'type' => 'Scheduled', 'run' => 'May 29, 2026 09:05', 'start' => 'May 28, 2026 09:00', 'end' => 'May 29, 2026 09:00'],
            ['name' => 'de_order_with_image', 'type' => 'Scheduled', 'run' => 'May 28, 2026 14:05', 'start' => 'May 27, 2026 14:00', 'end' => 'May 28, 2026 14:00'],
            ['name' => 'factory', 'type' => 'Scheduled', 'run' => 'May 28, 2026 09:05', 'start' => 'May 27, 2026 09:00', 'end' => 'May 28, 2026 09:00'],
            ['name' => 'de_order_with_image', 'type' => 'Scheduled', 'run' => 'May 27, 2026 14:05', 'start' => 'May 26, 2026 14:00', 'end' => 'May 27, 2026 14:00'],
            ['name' => 'de_order_with_image', 'type' => 'Immediate', 'run' => 'May 27, 2026 09:33', 'start' => 'May 22, 2026 09:00', 'end' => 'May 27, 2026 23:59'],
            ['name' => 'factory', 'type' => 'Scheduled', 'run' => 'May 27, 2026 09:05', 'start' => 'May 26, 2026 09:00', 'end' => 'May 27, 2026 09:00'],
            ['name' => 'de_order_with_image', 'type' => 'Scheduled', 'run' => 'May 26, 2026 14:05', 'start' => 'May 25, 2026 14:00', 'end' => 'May 26, 2026 14:00'],
            ['name' => 'factory', 'type' => 'Scheduled', 'run' => 'May 26, 2026 09:05', 'start' => 'May 25, 2026 09:00', 'end' => 'May 26, 2026 09:00'],
        ];
    }
}
