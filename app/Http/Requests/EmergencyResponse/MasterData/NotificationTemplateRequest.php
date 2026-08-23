<?php

declare(strict_types=1);

namespace App\Http\Requests\EmergencyResponse\MasterData;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NotificationTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $ignoreId = $this->route('notification_template');

        return [
            'code' => ['required', 'string', 'max:100', Rule::unique('er_notification_templates', 'code')->ignore($ignoreId)],
            'name' => ['required', 'string', 'max:255'],
            'channel' => ['required', Rule::in(['in_app', 'email', 'both'])],
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
