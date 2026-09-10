<?php

declare(strict_types=1);

namespace App\Http\Requests\ControlRoom;

use App\Services\ControlRoom\ControlRoomSapPhotoResolver;
use Illuminate\Foundation\Http\FormRequest;

final class ControlRoomDashboardSapPhotosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'id' => $this->query('id', $this->input('id')),
            'kind' => $this->query('kind', $this->input('kind', ControlRoomSapPhotoResolver::KIND_PHOTOCAR)),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'id' => ['required', 'integer', 'min:1'],
            'kind' => ['sometimes', 'in:photocar,document'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'id.required' => 'ID photoCar wajib diisi.',
        ];
    }
}
