<?php

namespace App\Http\Requests\EmergencyRequest;

use App\Enums\BloodComponent;
use App\Enums\BloodRequestStatus;
use App\Enums\BloodType;
use App\Enums\Rhesus;
use App\Enums\UrgencyLevel;
use App\Models\BloodRequest;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateEmergencyRequest extends FormRequest
{
    public function authorize(): Response|bool
    {
        $bloodRequest = $this->bloodRequestRecord();

        if (! $bloodRequest instanceof BloodRequest) {
            return false;
        }

        return Gate::inspect('update', $bloodRequest);
    }

    protected function prepareForValidation(): void
    {
        $rawAction = $this->input('submit_action');

        $this->merge([
            'submit_action' => is_string($rawAction) && $rawAction !== '' ? trim($rawAction) : 'submit',
            'patient_name' => trim((string) $this->input('patient_name', '')),
            'clinical_diagnosis' => $this->filled('clinical_diagnosis') ? trim((string) $this->input('clinical_diagnosis')) : null,
            'doctor_name' => $this->filled('doctor_name') ? trim((string) $this->input('doctor_name')) : null,
            'ward_room' => $this->filled('ward_room') ? trim((string) $this->input('ward_room')) : null,
            'medical_record_number' => $this->filled('medical_record_number') ? trim((string) $this->input('medical_record_number')) : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $bloodRequest = $this->bloodRequestRecord();
        $isCurrentlyDraft = $bloodRequest?->status === BloodRequestStatus::DRAFT;
        $allowedActions = $isCurrentlyDraft ? ['draft', 'submit'] : ['submit'];
        $isFinalSubmit = $this->input('submit_action') !== 'draft';
        $requiresNewReferralUpload = $isFinalSubmit && ! ($bloodRequest?->hasReferralLetter() ?? false);

        $neededAtRules = [
            Rule::requiredIf($isFinalSubmit),
            'nullable',
            'date',
        ];

        if ($isFinalSubmit) {
            $neededAtRules[] = 'after:now';
        }

        return [
            'submit_action' => ['required', 'string', Rule::in($allowedActions)],

            // Patient identity & clinical condition
            'patient_name' => ['required', 'string', 'min:3', 'max:120'],
            'patient_age' => [Rule::requiredIf($isFinalSubmit), 'nullable', 'integer', 'min:0', 'max:120'],
            'patient_gender' => [Rule::requiredIf($isFinalSubmit), 'nullable', Rule::in(['L', 'P'])],
            'patient_relationship' => ['required', 'string', 'max:60'],
            'medical_record_number' => ['nullable', 'string', 'max:50'],
            'ward_room' => [Rule::requiredIf($isFinalSubmit), 'nullable', 'string', 'max:100'],
            'clinical_diagnosis' => [Rule::requiredIf($isFinalSubmit), 'nullable', 'string', 'min:4', 'max:255'],
            'hemoglobin_level' => [Rule::requiredIf($isFinalSubmit), 'nullable', 'numeric', 'min:1.5', 'max:22.0'],

            // Transfusion requirements
            'blood_type' => ['required', Rule::in(BloodType::values())],
            'rhesus' => ['required', Rule::in(Rhesus::values())],
            'component_type' => ['required', Rule::in(BloodComponent::values())],
            'bags_needed' => ['required', 'integer', 'min:1', 'max:20'],
            'urgency_level' => ['required', Rule::in(UrgencyLevel::values())],
            'needed_at' => $neededAtRules,
            'facility_id' => [
                'required',
                'integer',
                Rule::exists('facilities', 'id')->where('is_active', true),
            ],
            'additional_notes' => ['nullable', 'string', 'max:1000'],

            // Medical verification attachment (optional replacement when already attached)
            'doctor_name' => [Rule::requiredIf($isFinalSubmit), 'nullable', 'string', 'min:3', 'max:120'],
            'referral_letter' => [
                Rule::requiredIf($requiresNewReferralUpload),
                'nullable',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:5120',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'submit_action.in' => 'Permohonan yang sudah masuk antrean verifikasi tidak dapat dikembalikan menjadi draf.',
            'patient_name.required' => 'Nama lengkap pasien wajib diisi.',
            'patient_name.min' => 'Nama pasien minimal terdiri dari 3 karakter.',
            'patient_age.required' => 'Usia pasien wajib diisi sebelum permohonan dikirim untuk verifikasi.',
            'patient_gender.required' => 'Jenis kelamin pasien wajib dipilih.',
            'patient_relationship.required' => 'Hubungan Anda dengan pasien wajib dipilih.',
            'ward_room.required' => 'Informasi ruang rawat / bangsal rumah sakit wajib diisi untuk mempermudah serah terima.',
            'clinical_diagnosis.required' => 'Diagnosis klinis pasien wajib dicantumkan sesuai keterangan dokter.',
            'clinical_diagnosis.min' => 'Diagnosis klinis minimal terdiri dari 4 karakter.',
            'hemoglobin_level.required' => 'Kadar hemoglobin (Hb) terakhir pasien wajib diisi.',
            'hemoglobin_level.min' => 'Nilai kadar hemoglobin tidak valid (minimal 1.5 g/dL).',
            'hemoglobin_level.max' => 'Nilai kadar hemoglobin melebihi batas wajar klinis (maksimal 22.0 g/dL).',
            'blood_type.required' => 'Golongan darah ABO pasien wajib dipilih.',
            'rhesus.required' => 'Faktor Rhesus (+ / -) pasien wajib dipilih.',
            'component_type.required' => 'Jenis komponen darah yang dibutuhkan wajib dipilih.',
            'bags_needed.required' => 'Jumlah kantong darah wajib diisi.',
            'bags_needed.min' => 'Minimal jumlah permohonan adalah 1 kantong darah.',
            'bags_needed.max' => 'Maksimal permohonan dalam satu pengajuan adalah 20 kantong darah.',
            'urgency_level.required' => 'Tingkat urgensi medis wajib dipilih.',
            'needed_at.required' => 'Batas waktu kebutuhan transfusi wajib diisi.',
            'needed_at.after' => 'Estimasi waktu kebutuhan transfusi harus berada di waktu mendatang.',
            'facility_id.required' => 'Fasilitas kesehatan / rumah sakit rujukan wajib dipilih.',
            'facility_id.exists' => 'Fasilitas kesehatan rujukan yang dipilih tidak aktif atau tidak valid.',
            'doctor_name.required' => 'Nama dokter penanggung jawab / perujuk wajib diisi.',
            'referral_letter.required' => 'Berkas surat rujukan dokter wajib dilampirkan untuk mengirim permohonan ke UDD.',
            'referral_letter.mimes' => 'Berkas surat rujukan harus berformat PDF, JPG, JPEG, atau PNG.',
            'referral_letter.max' => 'Ukuran berkas surat rujukan dokter maksimal 5 MB.',
        ];
    }

    public function isDraftSubmission(): bool
    {
        return $this->input('submit_action') === 'draft';
    }

    private function bloodRequestRecord(): ?BloodRequest
    {
        $routeModel = $this->route('bloodRequest');

        return $routeModel instanceof BloodRequest ? $routeModel : null;
    }
}
