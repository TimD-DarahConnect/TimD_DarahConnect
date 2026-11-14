<?php

namespace App\Http\Requests\MedicalVerification;

use App\Enums\VerificationDecision;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RejectOrReviseBloodRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ($user->isUddStaff() || $user->isAdmin());
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('review_notes')) {
            $this->merge([
                'review_notes' => trim((string) $this->input('review_notes')),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'decision' => [
                'required',
                'string',
                Rule::in([
                    VerificationDecision::NEED_REVISION->value,
                    VerificationDecision::REJECTED->value,
                ]),
            ],
            'review_notes' => [
                'required',
                'string',
                'min:15',
                'max:1000',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'decision.required' => 'Pilih jenis tindak lanjut verifikasi (Minta Revisi Data atau Tolak Medis).',
            'decision.in' => 'Keputusan tindak lanjut verifikasi tidak valid.',
            'review_notes.required' => 'Catatan resmi verifikasi medis wajib diisi sebagai acuan perbaikan atau alasan penolakan bagi pemohon.',
            'review_notes.min' => 'Catatan resmi verifikasi minimal terdiri dari 15 karakter agar instruksi medis jelas.',
            'review_notes.max' => 'Catatan resmi verifikasi maksimal terdiri dari 1.000 karakter.',
        ];
    }
}
