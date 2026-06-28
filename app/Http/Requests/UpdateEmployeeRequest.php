<?php

namespace App\Http\Requests;

use App\Models\Employee;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class UpdateEmployeeRequest extends StoreEmployeeRequest
{
    public function authorize()
    {
        $actor = Auth::guard('admin')->user();
        $employee = $this->route('employee');

        return $actor
            && $employee instanceof Employee
            && Gate::forUser($actor)->allows('update', $employee);
    }

    protected function currentEmployee()
    {
        return $this->route('employee');
    }
}
