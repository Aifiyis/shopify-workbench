<?php

namespace App\Http\Requests;

use App\Models\Position;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class UpdatePositionRequest extends StorePositionRequest
{
    public function authorize()
    {
        $actor = Auth::guard('admin')->user();
        $position = $this->route('position');

        return $actor
            && $position instanceof Position
            && Gate::forUser($actor)->allows('update', $position);
    }

    public function rules()
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
            'permission_ids' => ['array'],
            'permission_ids.*' => ['integer', 'distinct', 'exists:permissions,id'],
        ];
    }
}
