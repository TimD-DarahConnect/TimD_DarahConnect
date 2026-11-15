<?php

namespace App\Http\Requests\DonationSchedule;

use App\Enums\SessionOperationalStatus;
use App\Models\DonationSchedule;
use App\Models\DonationSession;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateSessionOperationalStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        /** @var DonationSchedule|null $schedule */
        $schedule = $this->route('schedule');

        if (! $user || ! $schedule) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if (! $user->isUddStaff()) {
            return false;
        }

        if ($user->facility_id && (int) $schedule->facility_id !== (int) $user->facility_id) {
            return false;
        }

        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $requiresNote = in_array(
            (string) $this->input('operational_status'),
            [
                SessionOperationalStatus::PAUSED->value,
                SessionOperationalStatus::CLOSED_EMERGENCY->value,
            ],
            true
        );

        return [
            'operational_status' => ['required', Rule::enum(SessionOperationalStatus::class)],
            'operational_note' => [
                Rule::requiredIf($requiresNote),
                'nullable',
                'string',
                'min:8',
                'max:255',
            ],
            'quota_total' => ['nullable', 'integer', 'min:1', 'max:500'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var DonationSession|null $session */
                $session = $this->route('session');
                $requestedQuota = $this->input('quota_total');

                if ($session !== null && $requestedQuota !== null && $requestedQuota !== '') {
                    $newQuota = (int) $requestedQuota;
                    if ($newQuota < (int) $session->quota_booked) {
                        $validator->errors()->add(
                            'quota_total',
                            "Kapasitas kuota sesi tidak boleh lebih kecil dari jumlah pendonor yang sudah terdaftar ({$session->quota_booked} orang)."
                        );
                    }
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'operational_status.required' => 'Status operasional slot sesi wajib dipilih.',
            'operational_status.Illuminate\Validation\Rules\Enum' => 'Pilihan status operasional slot tidak valid.',
            'operational_note.required' => 'Catatan kendala teknis atau alasan penyesuaian wajib diisi saat slot dijeda sementara atau ditutup darurat.',
            'operational_note.min' => 'Catatan kendala operasional minimal terdiri dari 8 karakter agar informatif bagi pendonor.',
            'operational_note.max' => 'Catatan kendala operasional maksimal 255 karakter.',
            'quota_total.integer' => 'Kapasitas kuota sesi harus berupa angka bulat.',
            'quota_total.min' => 'Kapasitas kuota sesi minimal 1 kursi.',
            'quota_total.max' => 'Kapasitas kuota sesi maksimal 500 kursi.',
        ];
    }
}
