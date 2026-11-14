<?php

namespace App\Http\Requests\EmergencyRequest;

use App\Models\BloodRequest;
use Illuminate\Foundation\Http\FormRequest;

class CancelEmergencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        /** @var BloodRequest|null $bloodRequest */
        $bloodRequest = $this->route('bloodRequest');

        return $user !== null
            && $user->isSeeker()
            && $bloodRequest !== null
            && (int) $bloodRequest->requester_id === (int) $user->id;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('cancellation_reason')) {
            $this->merge([
                'cancellation_reason' => trim((string) $this->input('cancellation_reason')),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cancellation_reason' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cancellation_reason.required' => 'Alasan resmi pembatalan permohonan darah wajib diisi.',
            'cancellation_reason.min' => 'Alasan pembatalan minimal terdiri dari 10 karakter agar jelas bagi petugas UDD dan pendonor.',
            'cancellation_reason.max' => 'Alasan pembatalan maksimal 500 karakter.',
        ];
    }
}
