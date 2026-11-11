<?php

namespace App\Http\Requests\DonationSchedule;

use App\Models\DonationSchedule;
use App\Models\Facility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreMobileUnitScheduleRequest extends FormRequest
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
            'region_id' => ['required', 'integer', 'exists:regions,id'],
            'service_date' => ['required', 'date', 'after_or_equal:today'],
            'title' => ['required', 'string', 'max:160'],
            'partner_institution' => ['required', 'string', 'max:160'],
            'venue_name' => ['required', 'string', 'max:160'],
            'venue_address' => ['required', 'string', 'max:280'],
            'latitude' => ['required', 'numeric', 'between:-11.5,6.5'],
            'longitude' => ['required', 'numeric', 'between:94.5,141.5'],
            'target_bags' => ['required', 'integer', 'min:10', 'max:2000'],
            'opening_time' => ['required', 'date_format:H:i'],
            'closing_time' => ['required', 'date_format:H:i', 'after:opening_time'],
            'publication_status' => [
                'required',
                'string',
                Rule::in([DonationSchedule::PUBLICATION_DRAFT, DonationSchedule::PUBLICATION_PUBLISHED]),
            ],
            'notes' => ['nullable', 'string', 'max:600'],
            'sessions' => ['required', 'array', 'min:1', 'max:8'],
            'sessions.*.session_label' => ['required', 'string', 'max:80'],
            'sessions.*.start_time' => ['required', 'date_format:H:i'],
            'sessions.*.end_time' => ['required', 'date_format:H:i'],
            'sessions.*.quota_total' => ['required', 'integer', 'min:5', 'max:500'],
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
                $user = $this->user();

                if ($user && $user->isUddStaff() && $user->facility_id && (int) $user->facility_id !== $facilityId) {
                    $validator->errors()->add('facility_id', 'Anda hanya berwenang menjadwalkan agenda Mobile Unit untuk unit UDD instansi Anda sendiri.');

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
                        $validator->errors()->add("sessions.{$idx}.start_time", "Rentang jam Sesi #{$rowNum} ({$start}–{$end}) harus berada di dalam jam pelaksanaan acara ({$opening}–{$closing}).");
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
            'facility_id.required' => 'Pilih unit UDD PMI penyelenggara kegiatan.',
            'facility_id.exists' => 'Fasilitas UDD penyelenggara tidak valid atau sedang tidak aktif.',
            'region_id.required' => 'Pilih wilayah kabupaten/kota lokasi kegiatan eksternal.',
            'region_id.exists' => 'Wilayah kabupaten/kota yang dipilih tidak terdaftar.',
            'service_date.required' => 'Tanggal pelaksanaan kegiatan Mobile Unit wajib diisi.',
            'service_date.after_or_equal' => 'Tanggal pelaksanaan tidak boleh berada di masa lampau.',
            'title.required' => 'Nama acara atau kegiatan donor darah eksternal wajib diisi.',
            'partner_institution.required' => 'Nama instansi mitra penyelenggara wajib diisi.',
            'venue_name.required' => 'Nama gedung atau titik lokasi acara mitra wajib diisi.',
            'venue_address.required' => 'Alamat lengkap lokasi kegiatan eksternal wajib diisi.',
            'latitude.required' => 'Titik koordinat lintang (latitude) lokasi wajib ditentukan pada peta.',
            'latitude.between' => 'Koordinat lintang lokasi berada di luar jangkauan wilayah layanan.',
            'longitude.required' => 'Titik koordinat bujur (longitude) lokasi wajib ditentukan pada peta.',
            'longitude.between' => 'Koordinat bujur lokasi berada di luar jangkauan wilayah layanan.',
            'target_bags.required' => 'Target perolehan kantong darah kegiatan wajib diisi.',
            'target_bags.min' => 'Target perolehan minimal kegiatan Mobile Unit adalah 10 kantong darah.',
            'target_bags.max' => 'Target perolehan maksimal kegiatan Mobile Unit adalah 2.000 kantong darah.',
            'opening_time.required' => 'Jam mulai pelaksanaan kegiatan wajib diisi.',
            'closing_time.required' => 'Jam selesai pelaksanaan kegiatan wajib diisi.',
            'closing_time.after' => 'Jam selesai acara harus lebih akhir dari jam mulai.',
            'publication_status.required' => 'Pilih status publikasi awal acara (Draf atau Dipublikasikan).',
            'publication_status.in' => 'Status publikasi tidak valid.',
            'sessions.required' => 'Tambahkan minimal 1 sesi pelayanan untuk kegiatan Mobile Unit.',
            'sessions.*.session_label.required' => 'Nama sesi pelayanan wajib diisi.',
            'sessions.*.start_time.required' => 'Jam mulai sesi wajib diisi.',
            'sessions.*.end_time.required' => 'Jam selesai sesi wajib diisi.',
            'sessions.*.quota_total.required' => 'Kuota kursi pendonor per sesi wajib diisi.',
            'sessions.*.quota_total.min' => 'Kuota minimal per sesi adalah 5 pendonor.',
            'sessions.*.quota_total.max' => 'Kuota maksimal per sesi adalah 500 pendonor.',
        ];
    }
}
