<?php

namespace App\Http\Requests\DonationSchedule;

use App\Enums\DonationScheduleType;
use App\Models\DonationSchedule;
use App\Models\Facility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreUddScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasRole('petugas_udd', 'admin');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'facility_id' => [
                'required',
                'integer',
                Rule::exists('facilities', 'id')->where(function ($query): void {
                    $query->where('type', Facility::TYPE_UDD)->where('is_active', true);
                }),
            ],
            'service_date' => ['required', 'date', 'after_or_equal:today'],
            'title' => ['required', 'string', 'max:160'],
            'opening_time' => ['required', 'date_format:H:i'],
            'closing_time' => ['required', 'date_format:H:i', 'after:opening_time'],
            'notes' => ['nullable', 'string', 'max:600'],
            'sessions' => ['required', 'array', 'min:1', 'max:8'],
            'sessions.*.session_label' => ['required', 'string', 'max:80'],
            'sessions.*.start_time' => ['required', 'date_format:H:i'],
            'sessions.*.end_time' => ['required', 'date_format:H:i'],
            'sessions.*.quota_total' => ['required', 'integer', 'min:5', 'max:300'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $facilityId = (int) $this->input('facility_id');
                $serviceDate = (string) $this->input('service_date');

                // Restrict UDD staff with an assigned building to manage only their own UDD building
                $user = $this->user();
                if ($user && $user->isUddStaff() && $user->facility_id && (int) $user->facility_id !== $facilityId) {
                    $validator->errors()->add('facility_id', 'Anda hanya berwenang mengelola master jadwal pada gedung UDD instansi Anda sendiri.');

                    return;
                }

                $alreadyExists = DonationSchedule::query()
                    ->where('facility_id', $facilityId)
                    ->whereDate('service_date', $serviceDate)
                    ->where('schedule_type', DonationScheduleType::UDD_BUILDING->value)
                    ->exists();

                if ($alreadyExists) {
                    $validator->errors()->add('service_date', 'Master jadwal gedung UDD untuk tanggal tersebut sudah terdaftar. Silakan pilih tanggal operasional lain.');

                    return;
                }

                $opening = (string) $this->input('opening_time');
                $closing = (string) $this->input('closing_time');
                $sessions = array_values((array) $this->input('sessions', []));
                $intervals = [];

                foreach ($sessions as $idx => $session) {
                    $start = (string) ($session['start_time'] ?? '');
                    $end = (string) ($session['end_time'] ?? '');
                    $rowNum = $idx + 1;

                    if ($end <= $start) {
                        $validator->errors()->add("sessions.{$idx}.end_time", "Jam selesai Sesi #{$rowNum} harus lebih akhir daripada jam mulai.");

                        continue;
                    }

                    if ($start < $opening || $end > $closing) {
                        $validator->errors()->add("sessions.{$idx}.start_time", "Rentang jam Sesi #{$rowNum} ({$start}–{$end}) harus berada di dalam jam operasional gedung ({$opening}–{$closing}).");
                    }

                    foreach ($intervals as $prev) {
                        if ($start < $prev['end'] && $prev['start'] < $end) {
                            $validator->errors()->add("sessions.{$idx}.start_time", "Jam Sesi #{$rowNum} ({$start}–{$end}) bertabrakan dengan {$prev['label']} ({$prev['start']}–{$prev['end']}).");
                            break;
                        }
                    }

                    $intervals[] = [
                        'label' => $session['session_label'] ?? "Sesi #{$rowNum}",
                        'start' => $start,
                        'end' => $end,
                    ];
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
            'facility_id.required' => 'Pilih gedung Unit Donor Darah (UDD) penyelenggara.',
            'facility_id.exists' => 'Fasilitas gedung UDD tidak valid atau sedang tidak aktif.',
            'service_date.required' => 'Tanggal operasional layanan wajib diisi.',
            'service_date.after_or_equal' => 'Tanggal master jadwal tidak boleh berada di masa lampau.',
            'title.required' => 'Nama jadwal layanan harian wajib diisi.',
            'opening_time.required' => 'Jam buka operasional gedung wajib diisi.',
            'closing_time.required' => 'Jam tutup operasional gedung wajib diisi.',
            'closing_time.after' => 'Jam tutup operasional harus lebih akhir dari jam buka gedung.',
            'sessions.required' => 'Tambahkan minimal 1 sesi pelayanan donor.',
            'sessions.*.session_label.required' => 'Nama sesi pelayanan wajib diisi.',
            'sessions.*.start_time.required' => 'Jam mulai sesi wajib diisi.',
            'sessions.*.end_time.required' => 'Jam selesai sesi wajib diisi.',
            'sessions.*.quota_total.required' => 'Kuota pendonor per sesi wajib diisi.',
            'sessions.*.quota_total.min' => 'Kuota minimal per sesi adalah 5 pendonor.',
            'sessions.*.quota_total.max' => 'Kuota maksimal per sesi adalah 300 pendonor.',
        ];
    }
}
