@if ($isOwnerDonor && $booking->canBeRescheduled() && Route::has('bookings.reschedule'))
    <!-- Fluid Bottom Sheet: Reschedule Session Booking -->
    <div class="dc-sheet-backdrop" data-dc-sheet-backdrop="rescheduleBookingSheet"></div>
    <aside
        class="dc-sheet-panel"
        style="max-width: 39rem;"
        data-dc-sheet="rescheduleBookingSheet"
        aria-hidden="true"
        aria-labelledby="rescheduleBookingSheetTitle"
    >
        <div class="dc-sheet-handle-area" title="Tarik ke bawah untuk menutup">
            <span class="dc-sheet-handle"></span>
        </div>

        <form method="POST" action="{{ route('bookings.reschedule', $booking) }}" class="px-4 pb-4 overflow-auto" novalidate>
            @csrf
            @method('PATCH')

            <div class="d-flex align-items-start justify-content-between gap-3 pb-3 mb-3 border-bottom">
                <div>
                    <div class="dc-eyebrow">Penjadwalan Ulang Reservasi</div>
                    <h2 id="rescheduleBookingSheetTitle" class="dc-title-2 mb-1 mt-1">Pindahkan Sesi Kedatangan</h2>
                    <div class="dc-caption dc-tabular">
                        Kode <strong>{{ $booking->booking_code }}</strong> &middot;
                        Sisa kesempatan pindah jadwal: <strong>{{ $booking->remaining_reschedules }}x</strong>
                    </div>
                </div>
                <button type="button" class="btn btn-dc-soft btn-sm flex-shrink-0" data-dc-close-sheet="rescheduleBookingSheet" aria-label="Tutup panel">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <div class="p-3 rounded-3 bg-light border mb-3 small">
                <div class="dc-caption">Jadwal Saat Ini:</div>
                <div class="fw-semibold text-dark dc-tabular">
                    {{ $booking->schedule?->service_date?->translatedFormat('l, d M Y') }} &middot;
                    {{ $booking->session?->session_label }} ({{ $booking->session?->time_range_label }})
                </div>
                <div class="dc-caption">{{ $booking->schedule?->venue_name }}</div>
            </div>

            @if (($rescheduleSchedules ?? collect())->isEmpty())
                <div class="p-4 rounded-3 border bg-warning-subtle bg-opacity-25 text-center mb-3">
                    <div class="fw-semibold text-dark small mb-1">Belum Ada Slot Sesi Pengganti yang Terbuka</div>
                    <div class="dc-caption">
                        Saat ini belum terdapat sesi jadwal mendatang lain yang masih memiliki sisa kuota terbuka.
                    </div>
                </div>
            @else
                <div class="mb-3">
                    <label for="targetSessionSelect" class="form-label">
                        Pilih Tanggal &amp; Sesi Baru <span class="text-danger">*</span>
                    </label>
                    <select
                        id="targetSessionSelect"
                        name="target_session_id"
                        class="form-select @error('target_session_id') is-invalid @enderror"
                        required
                    >
                        <option value="">-- Pilih Lokasi, Tanggal &amp; Jam Sesi --</option>
                        @foreach ($rescheduleSchedules as $availSchedule)
                            <optgroup label="{{ $availSchedule->service_date->translatedFormat('D, d M Y') }} — {{ $availSchedule->venue_name }}">
                                @foreach ($availSchedule->sessions as $availSession)
                                    <option
                                        value="{{ $availSession->id }}"
                                        @selected((int) old('target_session_id') === (int) $availSession->id)
                                    >
                                        {{ $availSession->session_label }} ({{ $availSession->time_range_label }}) — Sisa {{ $availSession->remaining_quota }} kursi
                                    </option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    @error('target_session_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="rescheduleNoteInput" class="form-label">Catatan Penyesuaian (Opsional)</label>
                    <input
                        type="text"
                        id="rescheduleNoteInput"
                        name="reschedule_note"
                        value="{{ old('reschedule_note') }}"
                        maxlength="255"
                        class="form-control"
                        placeholder="Contoh: Menyesuaikan jam istirahat kantor siang hari..."
                    >
                </div>

                <div class="p-3 rounded-3 bg-success-subtle bg-opacity-50 border border-success-subtle dc-caption text-dark mb-4">
                    <i class="bi bi-shield-check text-success me-1"></i>
                    Rekam anamnesis Anda tetap tersimpan, kuota sesi lama otomatis dilepas ke publik, dan sistem akan menerbitkan <strong>Token QR baru</strong> pada tiket Anda.
                </div>
            @endif

            <div class="d-flex flex-column flex-sm-row justify-content-end gap-2">
                <button type="button" class="btn btn-dc-secondary" data-dc-close-sheet="rescheduleBookingSheet">
                    Tutup
                </button>
                @if (($rescheduleSchedules ?? collect())->isNotEmpty())
                    <button type="submit" class="btn btn-dc-primary px-4">
                        <i class="bi bi-arrow-repeat"></i>
                        Simpan Jadwal Baru
                    </button>
                @endif
            </div>
        </form>
    </aside>
@endif

@if ($isOwnerDonor && $booking->canBeCancelled() && Route::has('bookings.cancel'))
    <!-- Fluid Bottom Sheet: Cancel Session Booking -->
    <div class="dc-sheet-backdrop" data-dc-sheet-backdrop="cancelBookingSheet"></div>
    <aside
        class="dc-sheet-panel"
        style="max-width: 37rem;"
        data-dc-sheet="cancelBookingSheet"
        aria-hidden="true"
        aria-labelledby="cancelBookingSheetTitle"
    >
        <div class="dc-sheet-handle-area" title="Tarik ke bawah untuk menutup">
            <span class="dc-sheet-handle"></span>
        </div>

        <form method="POST" action="{{ route('bookings.cancel', $booking) }}" class="px-4 pb-4 overflow-auto" novalidate>
            @csrf
            @method('PATCH')

            <div class="d-flex align-items-start justify-content-between gap-3 pb-3 mb-3 border-bottom">
                <div>
                    <div class="dc-eyebrow">Pembatalan Tiket Reservasi</div>
                    <h2 id="cancelBookingSheetTitle" class="dc-title-2 mb-1 mt-1">Batalkan Jadwal Kedatangan</h2>
                    <div class="dc-caption dc-tabular">
                        Kode <strong>{{ $booking->booking_code }}</strong> &middot; {{ $booking->schedule?->venue_name }}
                    </div>
                </div>
                <button type="button" class="btn btn-dc-soft btn-sm flex-shrink-0" data-dc-close-sheet="cancelBookingSheet" aria-label="Tutup panel">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <div class="p-3 rounded-3 bg-warning-subtle bg-opacity-50 border border-warning-subtle dc-caption text-dark mb-3">
                <i class="bi bi-info-circle-fill text-warning me-1"></i>
                Pembatalan tiket terkonfirmasi akan otomatis <strong>mengembalikan 1 kuota kursi sesi</strong> ke jadwal UDD agar dapat dipesan oleh pendonor lain.
            </div>

            <div class="mb-3">
                <label class="form-label small mb-1">Pilih Cepat Alasan Berhalangan Hadir</label>
                <div class="d-flex flex-wrap gap-2">
                    @php
                        $cancelPresets = [
                            'Berhalangan hadir karena agenda pekerjaan atau perkuliahan mendadak.',
                            'Kondisi fisik hari ini sedang kurang fit / kurang tidur sebelum donor.',
                            'Terdapat keperluan keluarga mendesak yang tidak dapat ditinggalkan.',
                            'Ingin memesan ulang di lokasi UDD atau Mobile Unit lain yang lebih dekat.',
                        ];
                    @endphp
                    @foreach ($cancelPresets as $presetReason)
                        <button
                            type="button"
                            class="btn btn-dc-secondary btn-sm text-start dc-pressable js-booking-cancel-preset"
                            data-reason="{{ $presetReason }}"
                        >
                            {{ \Illuminate\Support\Str::limit($presetReason, 46) }}
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="mb-4">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <label for="bookingCancelReasonInput" class="form-label mb-0">
                        Alasan Pembatalan <span class="text-danger">*</span>
                    </label>
                    <span class="dc-caption dc-tabular" id="bookingCancelCounter">0 / 350</span>
                </div>
                <textarea
                    id="bookingCancelReasonInput"
                    name="cancellation_reason"
                    rows="3"
                    minlength="8"
                    maxlength="350"
                    class="form-control @error('cancellation_reason') is-invalid @enderror"
                    placeholder="Tuliskan alasan singkat pembatalan tiket reservasi (minimal 8 karakter)..."
                    required
                >{{ old('cancellation_reason') }}</textarea>
                @error('cancellation_reason')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="d-flex flex-column flex-sm-row justify-content-end gap-2">
                <button type="button" class="btn btn-dc-secondary" data-dc-close-sheet="cancelBookingSheet">
                    Pertahankan Tiket
                </button>
                <button type="submit" class="btn btn-dc-primary px-4">
                    <i class="bi bi-slash-circle"></i>
                    Konfirmasi Batalkan Tiket
                </button>
            </div>
        </form>
    </aside>
@endif

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const cancelInput = document.getElementById('bookingCancelReasonInput');
    const cancelCounter = document.getElementById('bookingCancelCounter');

    const syncCancelCount = () => {
        if (!cancelInput || !cancelCounter) return;
        cancelCounter.textContent = `${cancelInput.value.length} / 350`;
    };

    document.querySelectorAll('.js-booking-cancel-preset').forEach((btn) => {
        btn.addEventListener('click', () => {
            if (!cancelInput) return;
            cancelInput.value = btn.dataset.reason || '';
            cancelInput.focus();
            syncCancelCount();
        });
    });

    cancelInput?.addEventListener('input', syncCancelCount);
    syncCancelCount();

    @if ($errors->has('target_session_id') || $errors->has('reschedule_note'))
        window.requestAnimationFrame(() => {
            window.DarahSheets?.get('rescheduleBookingSheet')?.open();
        });
    @elseif ($errors->has('cancellation_reason'))
        window.requestAnimationFrame(() => {
            window.DarahSheets?.get('cancelBookingSheet')?.open();
        });
    @endif
});
</script>
@endpush
