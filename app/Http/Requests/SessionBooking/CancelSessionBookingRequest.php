<?php

namespace App\Http\Requests\SessionBooking;

use App\Models\DonorBooking;
use Illuminate\Foundation\Http\FormRequest;

class CancelSessionBookingRequest extends FormRequest
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
            'cancellation_reason' => ['required', 'string', 'min:8', 'max:350'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cancellation_reason.required' => 'Alasan pembatalan reservasi wajib diisi.',
            'cancellation_reason.min' => 'Alasan pembatalan minimal terdiri dari 8 karakter.',
            'cancellation_reason.max' => 'Alasan pembatalan maksimal 350 karakter.',
        ];
    }
}
