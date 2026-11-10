<?php

namespace App\Http\Requests\DonorCommitment;

use App\Models\DonorCommitment;
use Illuminate\Foundation\Http\FormRequest;

class CancelDonorCommitmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $commitment = $this->route('commitment');

        return $user !== null
            && $user->isDonor()
            && $commitment instanceof DonorCommitment
            && (int) $commitment->donor_id === (int) $user->id;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'cancellation_reason' => trim((string) $this->input('cancellation_reason', '')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cancellation_reason' => [
                'required',
                'string',
                'min:10',
                'max:350',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cancellation_reason.required' => 'Mohon cantumkan alasan kendala darurat agar sistem dapat membuka kembali slot bagi relawan lain.',
            'cancellation_reason.min' => 'Alasan pembatalan komitmen minimal terdiri dari 10 karakter.',
            'cancellation_reason.max' => 'Alasan pembatalan komitmen maksimal 350 karakter.',
        ];
    }
}
