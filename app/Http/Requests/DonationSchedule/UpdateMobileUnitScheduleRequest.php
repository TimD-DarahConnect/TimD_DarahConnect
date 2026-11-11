<?php

namespace App\Http\Requests\DonationSchedule;

use App\Models\DonationSchedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateMobileUnitScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        /** @var DonationSchedule|null $schedule */
        $schedule = $this->route('schedule');

        if (! $user || ! $user->hasRole('petugas_udd', 'admin') || ! $schedule || ! $schedule->isMobileUnit()) {
            return false;
        }

        if ($user->isUddStaff() && $user->facility_id && (int) $schedule->facility_id !== (int) $user->facility_id) {
            return false;
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
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

                /** @var DonationSchedule $schedule */
                $schedule = $this->route('schedule');
                $schedule->loadMissing('sessions');

                $opening = (string) $this->input('opening_time');
                $closing = (string) $this->input('closing_time');

                foreach ($schedule->sessions as $session) {
                    $start = substr((string) $session->start_time, 0, 5);
                    $end = substr((string) $session->end_time, 0, 5);

                    if ($start < $opening || $end > $closing) {
                        $validator->errors()->add(
                            'opening_time',
                            "Jam pelaksanaan acara ({$opening}–{$closing}) harus mencakup seluruh sesi terdaftar, termasuk {$session->session_label} ({$start}–{$end})."
                        );
                        break;
                    }
                }

                $targetStatus = (string) $this->input('publication_status');
                $hasActiveBookings = $schedule->bookings()->activeReservation()->exists() || $schedule->total_booked > 0;

                if ($targetStatus === DonationSchedule::PUBLICATION_DRAFT && $hasActiveBookings) {
                    $validator->errors()->add(
                        'publication_status',
                        'Agenda Mobile Unit yang sudah memiliki reservasi pendonor aktif tidak dapat ditarik kembali ke status Draf.'
                    );
                }

                $newDate = (string) $this->input('service_date');
                if ($newDate !== $schedule->service_date->toDateString() && $hasActiveBookings) {
                    $validator->errors()->add(
                        'service_date',
                        'Tanggal pelaksanaan tidak dapat diubah karena sudah terdapat pendonor yang memesan slot pada jadwal ini.'
                    );
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
            'region_id.required' => 'Pilih wilayah kabupaten/kota lokasi kegiatan eksternal.',
            'service_date.required' => 'Tanggal pelaksanaan kegiatan Mobile Unit wajib diisi.',
            'service_date.after_or_equal' => 'Tanggal pelaksanaan tidak boleh berada di masa lampau.',
            'title.required' => 'Nama acara atau kegiatan donor darah eksternal wajib diisi.',
            'partner_institution.required' => 'Nama instansi mitra penyelenggara wajib diisi.',
            'venue_name.required' => 'Nama gedung atau titik lokasi acara mitra wajib diisi.',
            'venue_address.required' => 'Alamat lengkap lokasi kegiatan eksternal wajib diisi.',
            'latitude.required' => 'Titik koordinat lintang (latitude) wajib ditentukan pada peta.',
            'longitude.required' => 'Titik koordinat bujur (longitude) wajib ditentukan pada peta.',
            'target_bags.required' => 'Target perolehan kantong darah kegiatan wajib diisi.',
            'target_bags.min' => 'Target perolehan minimal adalah 10 kantong darah.',
            'opening_time.required' => 'Jam mulai pelaksanaan kegiatan wajib diisi.',
            'closing_time.required' => 'Jam selesai pelaksanaan kegiatan wajib diisi.',
            'closing_time.after' => 'Jam selesai acara harus lebih akhir dari jam mulai.',
            'publication_status.required' => 'Pilih status publikasi acara.',
        ];
    }
}
