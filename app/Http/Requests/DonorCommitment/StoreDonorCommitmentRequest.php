<?php

namespace App\Http\Requests\DonorCommitment;

use Illuminate\Foundation\Http\FormRequest;

class StoreDonorCommitmentRequest extends FormRequest
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
            'estimated_arrival_at' => [
                'required',
                'date',
                'after:'.now()->addMinutes(5)->toDateTimeString(),
                'before_or_equal:'.now()->addHours(72)->toDateTimeString(),
            ],
            'health_readiness_confirmed' => ['required', 'accepted'],
            'pledge_notes' => ['nullable', 'string', 'max:350'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'estimated_arrival_at.required' => 'Mohon tentukan estimasi waktu kehadiran Anda di fasilitas kesehatan.',
            'estimated_arrival_at.date' => 'Format tanggal dan jam estimasi kehadiran tidak valid.',
            'estimated_arrival_at.after' => 'Estimasi waktu kehadiran harus berada di masa mendatang (minimal 5 menit dari sekarang).',
            'estimated_arrival_at.before_or_equal' => 'Estimasi kehadiran darurat maksimal 72 jam ke depan.',
            'health_readiness_confirmed.required' => 'Anda wajib menyetujui pernyataan kesiapan kondisi fisik pra-donor.',
            'health_readiness_confirmed.accepted' => 'Anda wajib menyetujui pernyataan kesiapan kondisi fisik pra-donor.',
            'pledge_notes.max' => 'Catatan singkat kedatangan maksimal 350 karakter.',
        ];
    }
}
