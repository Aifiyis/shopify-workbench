@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold">Admin Management</h1>
        @if(in_array(Auth::guard('admin')->user()->role, ['super', 'manager']))
            <a href="{{ route('admins.create') }}" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                Create New Admin
            </a>
        @endif
    </div>

    @if ($errors->any())
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            {{ session('error') }}
        </div>
    @endif

    <div class="bg-white rounded shadow-md p-6">
        @if($admins->isEmpty())
            <p class="text-gray-500">No admins to display</p>
        @else
            @foreach($admins as $admin)
                @include('admins._tree', ['admin' => $admin, 'level' => 0])
            @endforeach
        @endif
    </div>
</div>
@endsection
