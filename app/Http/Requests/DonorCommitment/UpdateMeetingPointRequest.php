<?php

namespace App\Http\Requests\DonorCommitment;

use App\Models\DonorCommitment;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMeetingPointRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        /** @var DonorCommitment|null $commitment */
        $commitment = $this->route('commitment');

        if (! $user || ! $commitment) {
            return false;
        }

        $commitment->loadMissing('bloodRequest');

        return $user->isSeeker()
            && (int) $commitment->bloodRequest?->requester_id === (int) $user->id;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'meeting_point' => ['required', 'string', 'min:5', 'max:180'],
            'building_floor' => ['nullable', 'string', 'max:120'],
            'companion_alias' => ['nullable', 'string', 'max:120'],
            'meeting_instructions' => ['nullable', 'string', 'max:350'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'meeting_point.required' => 'Lokasi titik temu utama di rumah sakit wajib diisi.',
            'meeting_point.min' => 'Lokasi titik temu minimal terdiri dari 5 karakter.',
            'meeting_point.max' => 'Lokasi titik temu maksimal 180 karakter.',
            'building_floor.max' => 'Keterangan gedung atau lantai maksimal 120 karakter.',
            'companion_alias.max' => 'Ciri pendamping di lokasi maksimal 120 karakter.',
            'meeting_instructions.max' => 'Instruksi tambahan titik temu maksimal 350 karakter.',
        ];
    }
}
