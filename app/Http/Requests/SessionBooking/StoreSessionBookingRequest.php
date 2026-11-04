<?php

namespace App\Http\Requests\SessionBooking;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSessionBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->isDonor();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'donation_type' => ['required', Rule::in(['WB', 'APHERESIS'])],
            'donor_notes' => ['nullable', 'string', 'max:350'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'donation_type.required' => 'Pilih jenis layanan donasi yang diinginkan.',
            'donation_type.in' => 'Jenis layanan donasi tidak valid.',
            'donor_notes.max' => 'Catatan tambahan maksimal 350 karakter.',
        ];
    }
}
