<?php

namespace App\Http\Requests;

use App\Models\ProcessingCraftNode;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class UpdateProcessingCraftRequest extends StoreProcessingCraftRequest
{
    public function authorize()
    {
        $actor = Auth::guard('admin')->user();
        $craft = $this->route('processing_craft');

        return $actor
            && $craft instanceof ProcessingCraftNode
            && Gate::forUser($actor)->allows('update', $craft);
    }

    public function withValidator($validator)
    {
        $craft = $this->route('processing_craft');

        $this->validateCraft($validator, $craft ? $craft->id : null, true);
    }
}
