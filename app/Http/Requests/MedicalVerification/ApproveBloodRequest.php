<?php

namespace App\Http\Requests\MedicalVerification;

use Illuminate\Foundation\Http\FormRequest;

class ApproveBloodRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ($user->isUddStaff() || $user->isAdmin());
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'verification_notes' => $this->filled('verification_notes')
                ? trim((string) $this->input('verification_notes'))
                : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'expires_at' => [
                'required',
                'date',
                'after:now',
                'before_or_equal:'.now()->addDays(14)->endOfDay()->toDateTimeString(),
            ],
            'verification_notes' => [
                'nullable',
                'string',
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
            'expires_at.required' => 'Batas waktu masa aktif publikasi permohonan wajib ditetapkan.',
            'expires_at.date' => 'Format tanggal dan jam masa aktif publikasi tidak valid.',
            'expires_at.after' => 'Batas waktu aktif publikasi harus berada di waktu mendatang.',
            'expires_at.before_or_equal' => 'Masa aktif publikasi darurat maksimal 14 hari ke depan.',
            'verification_notes.max' => 'Catatan verifikasi medis maksimal terdiri dari 1.000 karakter.',
        ];
    }
}
