<!-- Fluid Bottom Sheet: Session Slot Operational Status & Capacity Control -->
<div class="dc-sheet-backdrop" data-dc-sheet-backdrop="sessionStatusSheet-{{ $session->id }}"></div>
<aside
    class="dc-sheet-panel"
    style="max-width: 38rem;"
    data-dc-sheet="sessionStatusSheet-{{ $session->id }}"
    aria-hidden="true"
    aria-labelledby="sessionStatusTitle-{{ $session->id }}"
>
    <div class="dc-sheet-handle-area" title="Tarik ke bawah untuk menutup">
        <span class="dc-sheet-handle"></span>
    </div>

    <form
        method="POST"
        action="{{ route('schedules.sessions.status', [$schedule, $session]) }}"
        class="px-4 pb-4 overflow-auto js-session-status-form"
        data-session-id="{{ $session->id }}"
        novalidate
    >
        @csrf
        @method('PATCH')

        <div class="d-flex align-items-start justify-content-between gap-3 pb-3 mb-3 border-bottom">
            <div>
                <div class="dc-eyebrow">Kontrol Operasional Slot Sesi</div>
                <h2 id="sessionStatusTitle-{{ $session->id }}" class="dc-title-2 mb-1 mt-1">{{ $session->session_label }}</h2>
                <div class="dc-caption dc-tabular">
                    {{ $schedule->service_date->translatedFormat('d M Y') }} &middot; {{ $session->time_range_label }}
                    &middot; Terisi <strong>{{ $session->quota_booked }}/{{ $session->quota_total }}</strong>
                </div>
            </div>
            <button
                type="button"
                class="btn btn-dc-soft btn-sm flex-shrink-0"
                data-dc-close-sheet="sessionStatusSheet-{{ $session->id }}"
                aria-label="Tutup panel"
            >
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <div class="mb-3">
            <label class="form-label mb-2">Status Operasional Pelayanan <span class="text-danger">*</span></label>
            <div class="vstack gap-2">
                @php
                    $statusDescriptions = [
                        'open' => 'Slot aktif normal dan dapat dipesan oleh pendonor selama kuota kursi masih tersedia.',
                        'paused' => 'Menghentikan sementara pemesanan baru (mis. kalibrasi alat atau penyesuaian antrean kursi).',
                        'closed_emergency' => 'Menutup slot secara darurat akibat kendala teknis mendadak di lokasi layanan.',
                    ];
                @endphp
                @foreach (\App\Enums\SessionOperationalStatus::cases() as $opt)
                    <label class="p-3 rounded-3 border d-flex align-items-start gap-3 dc-pressable" style="cursor: pointer;">
                        <input
                            type="radio"
                            name="operational_status"
                            value="{{ $opt->value }}"
                            class="form-check-input mt-1 js-status-radio"
                            data-session-id="{{ $session->id }}"
                            @checked($session->operational_status === $opt)
                        >
                        <div class="flex-grow-1">
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                                <span class="fw-semibold text-dark small">{{ $opt->label() }}</span>
                                <span class="dc-pill {{ $opt->pillClass() }} py-0 px-2" style="font-size: 0.72rem;">
                                    <i class="bi {{ $opt->icon() }}"></i>
                                    {{ $opt->value }}
                                </span>
                            </div>
                            <div class="dc-caption mt-1">{{ $statusDescriptions[$opt->value] ?? '' }}</div>
                        </div>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="mb-3">
            <label for="quotaTotal-{{ $session->id }}" class="form-label">
                Penyesuaian Kapasitas Kursi Sesi (Minimal {{ max(1, $session->quota_booked) }} Kursi)
            </label>
            <input
                type="number"
                id="quotaTotal-{{ $session->id }}"
                name="quota_total"
                value="{{ $session->quota_total }}"
                min="{{ max(1, $session->quota_booked) }}"
                max="500"
                class="form-control dc-tabular"
                required
            >
            <div class="dc-caption mt-1">
                Saat ini sudah ada <strong class="dc-tabular text-dark">{{ $session->quota_booked }}</strong> pendaftar terkonfirmasi pada sesi ini.
            </div>
        </div>

        <div class="mb-3 js-note-preset-box" id="presetBox-{{ $session->id }}">
            <label class="form-label small mb-1">Pilih Cepat Alasan Kendala Teknis / Penyesuaian</label>
            <div class="d-flex flex-wrap gap-2">
                @php
                    $issuePresets = [
                        'Kalibrasi dan sterilisasi berkala peralatan flebotomi selama ±30 menit.',
                        'Penyesuaian antrean kursi donor yang sedang padat di lokasi layanan.',
                        'Gangguan sementara kelistrikan atau jaringan sistem registrasi lokal.',
                        'Keterbatasan sementara ketersediaan reagen pemeriksaan Hb pra-donor.',
                    ];
                @endphp
                @foreach ($issuePresets as $presetNote)
                    <button
                        type="button"
                        class="btn btn-dc-secondary btn-sm text-start dc-pressable js-issue-preset-btn"
                        data-target="operationalNote-{{ $session->id }}"
                        data-note="{{ $presetNote }}"
                    >
                        {{ \Illuminate\Support\Str::limit($presetNote, 46) }}
                    </button>
                @endforeach
            </div>
        </div>

        <div class="mb-4">
            <div class="d-flex align-items-center justify-content-between mb-1">
                <label for="operationalNote-{{ $session->id }}" class="form-label mb-0">
                    Catatan Informasi untuk Pendonor
                    <span class="text-danger js-note-required-badge {{ $session->operational_status->value === 'open' ? 'd-none' : '' }}" id="noteReqBadge-{{ $session->id }}">
                        (Wajib untuk Jeda/Tutup) *
                    </span>
                </label>
            </div>
            <textarea
                id="operationalNote-{{ $session->id }}"
                name="operational_note"
                rows="2"
                maxlength="255"
                class="form-control"
                placeholder="Contoh: Slot dijeda sementara karena kalibrasi alat flebotomi, silakan pilih sesi berikutnya..."
            >{{ $session->operational_note }}</textarea>
        </div>

        <div class="d-flex flex-column flex-sm-row justify-content-end gap-2">
            <button type="button" class="btn btn-dc-secondary" data-dc-close-sheet="sessionStatusSheet-{{ $session->id }}">
                Batal
            </button>
            <button type="submit" class="btn btn-dc-primary px-4">
                <i class="bi bi-check2-circle"></i>
                Simpan Status Slot
            </button>
        </div>
    </form>
</aside>
