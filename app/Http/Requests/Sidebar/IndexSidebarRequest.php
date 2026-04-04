<?php

namespace App\Http\Requests\Sidebar;

use Illuminate\Foundation\Http\FormRequest;

class IndexSidebarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }
}
