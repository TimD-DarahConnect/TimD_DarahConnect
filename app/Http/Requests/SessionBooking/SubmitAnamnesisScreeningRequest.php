<?php

namespace App\Http\Requests\SessionBooking;

use App\Models\DonorBooking;
use App\Models\ScreeningQuestion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitAnamnesisScreeningRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        /** @var DonorBooking|null $booking */
        $booking = $this->route('booking');

        return $user !== null
            && $user->isDonor()
            && $booking !== null
            && (int) $booking->donor_id === (int) $user->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        ScreeningQuestion::ensureCatalogExists();
        $activeQuestionIds = ScreeningQuestion::active()->pluck('id');

        $rules = [
            'answers' => ['required', 'array', 'size:'.$activeQuestionIds->count()],
            'consent_honesty' => ['required', 'accepted'],
        ];

        foreach ($activeQuestionIds as $id) {
            $rules["answers.{$id}"] = ['required', 'string', Rule::in(['yes', 'no'])];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'answers.required' => 'Mohon lengkapi seluruh 20 pertanyaan kuesioner penapisan mandiri (anamnesis).',
            'answers.size' => 'Seluruh 20 pertanyaan medis anamnesis wajib dijawab tanpa terlewat.',
            'answers.*.required' => 'Pertanyaan anamnesis ini wajib dijawab (Ya atau Tidak).',
            'answers.*.in' => 'Pilihan jawaban hanya berupa Ya atau Tidak.',
            'consent_honesty.required' => 'Anda wajib menyetujui pernyataan kejujuran pengisian dan persetujuan tindakan awal (Informed Consent).',
            'consent_honesty.accepted' => 'Centang pernyataan kejujuran dan persetujuan tindakan awal untuk melanjutkan.',
        ];
    }
}
