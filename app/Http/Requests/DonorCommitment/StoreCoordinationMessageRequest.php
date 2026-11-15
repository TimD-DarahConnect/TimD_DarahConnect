<?php

namespace App\Http\Requests\DonorCommitment;

use App\Models\DonorCommitment;
use App\Services\DonorCommitment\CoordinationPrivacyService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCoordinationMessageRequest extends FormRequest
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

        $isOwnerDonor = $user->isDonor() && (int) $commitment->donor_id === (int) $user->id;
        $isOwnerSeeker = $user->isSeeker() && (int) $commitment->bloodRequest?->requester_id === (int) $user->id;

        return $isOwnerDonor || $isOwnerSeeker;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $user = $this->user();
        $roleValue = $user?->isDonor() ? 'pendonor' : 'pencari_darah';
        $allowedPresets = array_keys(app(CoordinationPrivacyService::class)->presetsForRole($roleValue));

        return [
            'preset_key' => ['nullable', 'string', Rule::in($allowedPresets)],
            'body' => ['required_without:preset_key', 'nullable', 'string', 'min:4', 'max:350'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'preset_key.in' => 'Pilihan pesan cepat tidak dikenali untuk peran Anda.',
            'body.required_without' => 'Tulis pesan koordinasi singkat atau pilih salah satu tombol pesan cepat.',
            'body.min' => 'Pesan koordinasi minimal terdiri dari 4 karakter.',
            'body.max' => 'Pesan koordinasi maksimal 350 karakter.',
        ];
    }
}
