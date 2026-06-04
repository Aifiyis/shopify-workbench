<div class="border-l-4 border-gray-300 pl-4 my-3 {{ $level > 0 ? 'ml-' . ($level * 4) : '' }}">
    <div class="flex items-center justify-between p-3 bg-gray-50 rounded hover:bg-gray-100">
        <div class="flex-1">
            <div class="flex items-center gap-2">
                <span class="inline-block px-2 py-1 bg-blue-100 text-blue-800 text-xs font-semibold rounded">
                    {{ ucfirst($admin->role) }}
                </span>
                <span class="font-semibold text-lg">{{ $admin->name }}</span>
                @if($admin->company_name)
                    <span class="text-gray-600 text-sm">({{ $admin->company_name }})</span>
                @endif
            </div>
            <div class="text-sm text-gray-600 mt-1">
                {{ $admin->email }}
                @if($admin->stores->count())
                    • {{ $admin->stores->count() }} store(s) assigned
                @endif
                @unless($admin->is_active)
                    <span class="text-red-600 font-semibold">• Inactive</span>
                @endunless
            </div>
        </div>

        <div class="flex gap-2">
            @if($currentAdmin->canManage($admin->id))
                <a href="{{ route('admins.edit', $admin->id) }}" class="bg-yellow-500 hover:bg-yellow-700 text-white font-bold py-1 px-3 rounded text-sm">
                    Edit
                </a>
                <form method="POST" action="{{ route('admins.destroy', $admin->id) }}" class="inline" onsubmit="return confirm('Are you sure?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="bg-red-500 hover:bg-red-700 text-white font-bold py-1 px-3 rounded text-sm">
                        Delete
                    </button>
                </form>
            @endif
        </div>
    </div>

    @if($admin->subordinates->isNotEmpty())
        <div class="mt-3">
            @foreach($admin->subordinates as $subordinate)
                @include('admins._tree', ['admin' => $subordinate, 'level' => $level + 1])
            @endforeach
        </div>
    @endif
</div>
