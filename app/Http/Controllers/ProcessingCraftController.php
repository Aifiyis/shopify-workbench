<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProcessingCraftRequest;
use App\Http\Requests\UpdateProcessingCraftRequest;
use App\Models\ProcessingCraftNode;
use App\Models\ProductProcessingCraft;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

class ProcessingCraftController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', ProcessingCraftNode::class);

        $search = trim((string) $request->query('search', ''));
        $query = ProcessingCraftNode::query()
            ->with('parent')
            ->orderBy('path');

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($query) use ($like) {
                $query
                    ->where('name', 'like', $like)
                    ->orWhere('path', 'like', $like);
            });
        }

        return view('business.processing-crafts.index', array_merge([
            'crafts' => $query->paginate(50)->withQueryString(),
            'search' => $search,
        ], $this->returnData($request)));
    }

    public function create(Request $request)
    {
        $this->authorize('create', ProcessingCraftNode::class);

        return view('business.processing-crafts.form', array_merge([
            'craft' => new ProcessingCraftNode(),
            'parents' => $this->parentOptions(),
        ], $this->returnData($request)));
    }

    public function store(StoreProcessingCraftRequest $request)
    {
        $this->authorize('create', ProcessingCraftNode::class);

        $this->createCraft($request->validated());

        return redirect()
            ->route('processing-crafts.index', $this->returnQuery($request))
            ->with('success', '工艺已创建。');
    }

    public function quickStore(StoreProcessingCraftRequest $request)
    {
        $this->authorize('create', ProcessingCraftNode::class);

        $craft = $this->createCraft($request->validated());

        return response()->json([
            'id' => $craft->id,
            'name' => $craft->name,
            'path' => $craft->path,
            'depth' => substr_count($craft->path, '-'),
        ]);
    }

    public function edit(Request $request, ProcessingCraftNode $processingCraft)
    {
        $this->authorize('update', $processingCraft);

        return view('business.processing-crafts.form', array_merge([
            'craft' => $processingCraft,
            'parents' => $this->parentOptions($processingCraft),
        ], $this->returnData($request)));
    }

    public function update(
        UpdateProcessingCraftRequest $request,
        ProcessingCraftNode $processingCraft
    ) {
        $this->authorize('update', $processingCraft);

        $this->updateCraft($processingCraft, $request->validated());

        return redirect()
            ->route('processing-crafts.index', $this->returnQuery($request))
            ->with('success', '工艺已更新。');
    }

    public function destroy(Request $request, ProcessingCraftNode $processingCraft)
    {
        $this->authorize('delete', $processingCraft);

        $hasChildren = ProcessingCraftNode::withTrashed()
            ->where('parent_id', $processingCraft->id)
            ->exists();
        $hasReferences = ProductProcessingCraft::withTrashed()
            ->where('craft_id', $processingCraft->id)
            ->exists();

        if ($hasChildren || $hasReferences) {
            return redirect()
                ->route('processing-crafts.index', $this->returnQuery($request))
                ->with('error', '该工艺仍有下级工艺或订单处理配置引用，无法删除。');
        }

        $processingCraft->delete();

        return redirect()
            ->route('processing-crafts.index', $this->returnQuery($request))
            ->with('success', '工艺已删除。');
    }

    private function createCraft(array $validated)
    {
        $parent = !empty($validated['parent_id'])
            ? ProcessingCraftNode::query()->findOrFail($validated['parent_id'])
            : null;

        return ProcessingCraftNode::create([
            'parent_id' => $parent ? $parent->id : null,
            'name' => $validated['name'],
            'path' => $parent ? $parent->path.'-'.$validated['name'] : $validated['name'],
        ]);
    }

    private function updateCraft(ProcessingCraftNode $craft, array $validated)
    {
        $parent = !empty($validated['parent_id'])
            ? ProcessingCraftNode::query()->findOrFail($validated['parent_id'])
            : null;
        $rootPath = $parent
            ? $parent->path.'-'.$validated['name']
            : $validated['name'];

        DB::transaction(function () use ($craft, $validated, $parent, $rootPath) {
            $allCrafts = ProcessingCraftNode::withTrashed()->get();
            $children = $allCrafts->groupBy(function ($item) {
                return (string) $item->parent_id;
            });
            $paths = [$craft->id => $rootPath];
            $queue = [$craft->id];

            while (!empty($queue)) {
                $parentId = array_shift($queue);
                foreach ($children->get((string) $parentId, collect()) as $child) {
                    $paths[$child->id] = $paths[$parentId].'-'.$child->name;
                    $queue[] = $child->id;
                }
            }

            foreach ($paths as $path) {
                if (mb_strlen($path) > 255) {
                    throw ValidationException::withMessages([
                        'name' => '完整工艺路径不能超过 255 个字符。',
                    ]);
                }
            }

            if (count($paths) !== count(array_unique(array_values($paths)))
                || ProcessingCraftNode::withTrashed()
                    ->whereIn('path', array_values($paths))
                    ->whereNotIn('id', array_keys($paths))
                    ->exists()) {
                throw ValidationException::withMessages([
                    'name' => '调整后存在重复工艺路径，包括已删除的记录。',
                ]);
            }

            $craft->parent_id = $parent ? $parent->id : null;
            $craft->name = $validated['name'];
            $craft->path = $paths[$craft->id];
            $craft->save();

            foreach ($paths as $id => $path) {
                if ((int) $id === (int) $craft->id) {
                    continue;
                }

                $descendant = $allCrafts->firstWhere('id', $id);
                $descendant->path = $path;
                $descendant->save();
            }
        });
    }

    private function parentOptions(ProcessingCraftNode $craft = null)
    {
        $excluded = $craft ? $this->descendantIds($craft) : [];

        return ProcessingCraftNode::query()
            ->when($craft, function ($query) use ($craft, $excluded) {
                $query->whereNotIn('id', array_merge([$craft->id], $excluded));
            })
            ->orderBy('path')
            ->get(['id', 'name', 'path']);
    }

    private function descendantIds(ProcessingCraftNode $craft)
    {
        $children = ProcessingCraftNode::withTrashed()
            ->get(['id', 'parent_id'])
            ->groupBy(function ($item) {
                return (string) $item->parent_id;
            });
        $ids = [];
        $queue = [$craft->id];

        while (!empty($queue)) {
            $parentId = array_shift($queue);
            foreach ($children->get((string) $parentId, collect()) as $child) {
                $ids[] = $child->id;
                $queue[] = $child->id;
            }
        }

        return $ids;
    }

    private function returnData(Request $request)
    {
        list($returnTarget, $returnUrl) = $this->resolveReturnTarget($request);

        return compact('returnTarget', 'returnUrl');
    }

    private function returnQuery(Request $request)
    {
        list($returnTarget) = $this->resolveReturnTarget($request);

        return $returnTarget ? ['return_to' => $returnTarget] : [];
    }

    private function resolveReturnTarget(Request $request)
    {
        $target = trim((string) $request->query('return_to', ''));
        if ($target === '') {
            return [null, null];
        }

        if (Route::has($target) && strpos($target, 'order-processing.') === 0) {
            try {
                $url = route($target);
                $path = parse_url($url, PHP_URL_PATH) ?: '/';
                $query = parse_url($url, PHP_URL_QUERY);
                $localTarget = $path.($query ? '?'.$query : '');

                if ($this->isOrderProcessingGetPath($localTarget)) {
                    return [$target, $url];
                }
            } catch (\Throwable $exception) {
                return [null, null];
            }
        }

        if (substr($target, 0, 1) !== '/' || substr($target, 0, 2) === '//') {
            return [null, null];
        }

        $parts = parse_url($target);
        if ($parts === false || isset($parts['scheme']) || isset($parts['host'])
            || !$this->isOrderProcessingGetPath($target)) {
            return [null, null];
        }

        $path = $parts['path'] ?? '/';
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';

        return [$target, url($path).$query];
    }

    private function isOrderProcessingGetPath($target)
    {
        try {
            $route = Route::getRoutes()->match(Request::create($target, 'GET'));
            $name = (string) $route->getName();

            return strpos($name, 'order-processing.') === 0;
        } catch (\Throwable $exception) {
            return false;
        }
    }
}
