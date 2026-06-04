@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8 max-w-2xl">
    <h1 class="text-3xl font-bold mb-6">
        {{ isset($admin) ? 'Edit Admin' : 'Create New Admin' }}
    </h1>

    @if ($errors->any())
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ isset($admin) ? route('admins.update', $admin->id) : route('admins.store') }}" class="bg-white rounded shadow-md p-6">
        @csrf
        @if(isset($admin))
            @method('PUT')
        @endif

        <div class="mb-4">
            <label for="name" class="block text-gray-700 text-sm font-bold mb-2">Name *</label>
            <input type="text" name="name" id="name" value="{{ old('name', $admin->name ?? '') }}" required class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500">
        </div>

        <div class="mb-4">
            <label for="email" class="block text-gray-700 text-sm font-bold mb-2">Email *</label>
            <input type="email" name="email" id="email" value="{{ old('email', $admin->email ?? '') }}" required class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500">
        </div>

        <div class="mb-4">
            <label for="password" class="block text-gray-700 text-sm font-bold mb-2">
                Password {{ !isset($admin) ? '*' : '(leave blank to keep current)' }}
            </label>
            <input type="password" name="password" id="password" {{ !isset($admin) ? 'required' : '' }} class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500">
        </div>

        <div class="mb-4">
            <label for="password_confirmation" class="block text-gray-700 text-sm font-bold mb-2">Confirm Password</label>
            <input type="password" name="password_confirmation" id="password_confirmation" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500">
        </div>

        <div class="mb-4">
            <label for="company_name" class="block text-gray-700 text-sm font-bold mb-2">Company Name</label>
            <input type="text" name="company_name" id="company_name" value="{{ old('company_name', $admin->company_name ?? '') }}" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500">
        </div>

        <div class="mb-4">
            <label for="role" class="block text-gray-700 text-sm font-bold mb-2">Role *</label>
            <select name="role" id="role" required class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500">
                <option value="">Select a role</option>
                @foreach($availableRoles as $role)
                    <option value="{{ $role }}" {{ old('role', $admin->role ?? '') === $role ? 'selected' : '' }}>
                        {{ ucfirst($role) }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="mb-6">
            <label class="block text-gray-700 text-sm font-bold mb-2">Store Permissions</label>
            <div class="space-y-2 max-h-48 overflow-y-auto">
                @foreach($stores as $store)
                    @php
                        $isAssigned = isset($assignedStores) && in_array($store->id, $assignedStores);
                        $accessLevel = isset($assignedAccessLevels[$store->id]) ? $assignedAccessLevels[$store->id] : 'view';
                    @endphp
                    <div class="flex items-center gap-3 p-2 border rounded">
                        <input type="checkbox" name="store_ids[]" value="{{ $store->id }}" id="store_{{ $store->id }}" {{ $isAssigned ? 'checked' : '' }} class="store-checkbox">
                        <label for="store_{{ $store->id }}" class="flex-1 cursor-pointer">{{ $store->shop_name }}</label>
                        <select name="access_levels[]" class="px-2 py-1 border border-gray-300 rounded text-sm">
                            <option value="view" {{ $accessLevel === 'view' ? 'selected' : '' }}>View</option>
                            <option value="edit" {{ $accessLevel === 'edit' ? 'selected' : '' }}>Edit</option>
                        </select>
                    </div>
                @endforeach
            </div>
        </div>

        @if(isset($admin))
            <div class="mb-4">
                <label for="is_active" class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', $admin->is_active ?? true) ? 'checked' : '' }} class="w-4 h-4">
                    <span class="text-gray-700 text-sm font-bold">Active</span>
                </label>
            </div>
        @endif

        <div class="flex gap-4">
            <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                {{ isset($admin) ? 'Update' : 'Create' }} Admin
            </button>
            <a href="{{ route('admins.index') }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                Cancel
            </a>
        </div>
    </form>
</div>

<script>
document.querySelectorAll('.store-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        const select = this.parentElement.querySelector('select');
        select.disabled = !this.checked;
    });
    checkbox.parentElement.querySelector('select').disabled = !checkbox.checked;
});
</script>
@endsection
