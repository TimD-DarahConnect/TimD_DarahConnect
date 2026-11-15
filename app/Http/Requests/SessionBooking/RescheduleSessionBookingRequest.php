<?php

namespace App\Http\Requests\SessionBooking;

use App\Models\DonorBooking;
use Illuminate\Foundation\Http\FormRequest;

class RescheduleSessionBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        /** @var DonorBooking|null $booking */
        $booking = $this->route('booking');

        return $user !== null
            && $booking !== null
            && $user->isDonor()
            && (int) $booking->donor_id === (int) $user->id;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'target_session_id' => ['required', 'integer', 'exists:donation_sessions,id'],
            'reschedule_note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'target_session_id.required' => 'Pilih sesi jadwal tujuan penjadwalan ulang terlebih dahulu.',
            'target_session_id.exists' => 'Sesi jadwal tujuan yang dipilih tidak ditemukan.',
            'reschedule_note.max' => 'Catatan penjadwalan ulang maksimal 255 karakter.',
        ];
    }
}
