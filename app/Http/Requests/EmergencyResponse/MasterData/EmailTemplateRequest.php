<?php

declare(strict_types=1);

namespace App\Http\Requests\EmergencyResponse\MasterData;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmailTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $ignoreId = $this->route('email_template');

        return [
            'code' => ['required', 'string', 'max:100', Rule::unique('er_email_templates', 'code')->ignore($ignoreId)],
            'name' => ['required', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'body_html' => ['required', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
