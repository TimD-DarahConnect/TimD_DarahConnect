@extends('layouts.app')

@section('title', 'Pilih Slot Sesi — ' . $schedule->venue_name)

@push('styles')
@if ($schedule->latitude !== null && $schedule->longitude !== null)
<link
    rel="stylesheet"
    href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
    crossorigin=""
>
@endif
@endpush

@section('content')
<section class="py-4 py-lg-5">
    <div class="container" style="max-width: 68rem;">
        <!-- Breadcrumb & Back -->
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
            <div>
                <nav aria-label="breadcrumb" class="mb-1">
                    <ol class="breadcrumb mb-0 dc-caption">
                        <li class="breadcrumb-item"><a href="{{ route('bookings.schedules') }}" class="text-decoration-none text-secondary">Jadwal Donor</a></li>
                        <li class="breadcrumb-item active dc-tabular" aria-current="page">{{ $schedule->schedule_code }}</li>
                    </ol>
                </nav>
                <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
                    <span class="dc-pill {{ $schedule->schedule_type->pillClass() }}">
                        <i class="bi {{ $schedule->schedule_type->icon() }}"></i>
                        {{ $schedule->schedule_type->label() }}
                    </span>
                    <span class="dc-pill dc-pill-neutral dc-tabular">
                        <i class="bi bi-calendar-event text-danger"></i>
                        {{ $schedule->service_date->translatedFormat('l, d F Y') }}
                    </span>
                </div>
            </div>

            <a href="{{ route('bookings.schedules') }}" class="btn btn-dc-secondary">
                <i class="bi bi-arrow-left"></i>
                Semua Jadwal
            </a>
        </div>

        @if ($activeBooking)
            <div class="dc-card p-3 px-4 mb-4 border-warning border-opacity-50 bg-warning-subtle bg-opacity-25">
                <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2">
                    <div class="small text-dark">
                        <i class="bi bi-info-circle-fill text-warning me-1"></i>
                        Anda sudah memiliki reservasi jadwal aktif (<strong class="dc-tabular">{{ $activeBooking->booking_code }}</strong>). Selesaikan atau batalkan reservasi tersebut terlebih dahulu jika ingin memesan sesi lain.
                    </div>
                    <a href="{{ route('bookings.show', $activeBooking) }}" class="btn btn-dc-secondary btn-sm flex-shrink-0">
                        Lihat Reservasi Saya
                    </a>
                </div>
            </div>
        @endif

        <div class="row g-4 align-items-start">
            <!-- Left: Venue Info & Map -->
            <div class="col-lg-5">
                <div class="dc-card p-4">
                    <div class="dc-eyebrow">Lokasi Pelayanan</div>
                    <h1 class="dc-title-2 mt-1 mb-1">{{ $schedule->venue_name }}</h1>
                    <p class="dc-caption mb-3">{{ $schedule->title }}</p>

                    <div class="p-3 rounded-4 bg-light border mb-3 small">
                        <div class="mb-2">
                            <i class="bi bi-geo-alt-fill text-danger me-1"></i>
                            <span class="text-dark fw-medium">{{ $schedule->venue_address }}</span>
                        </div>
                        <div class="dc-tabular text-secondary">
                            <i class="bi bi-clock me-1 text-danger"></i>
                            Jam Operasional: <strong class="text-dark">{{ $schedule->operating_hours_label }}</strong>
                        </div>
                        @if ($schedule->partner_institution)
                            <div class="text-secondary mt-1">
                                <i class="bi bi-people me-1 text-danger"></i>
                                Mitra Penyelenggara: <strong class="text-dark">{{ $schedule->partner_institution }}</strong>
                            </div>
                        @endif
                    </div>

                    @if ($schedule->latitude !== null && $schedule->longitude !== null)
                        <div id="scheduleVenueMap" class="rounded-4 border mb-3" style="height: 14rem; z-index: 1;" aria-label="Peta lokasi jadwal donor"></div>
                    @endif

                    @if ($schedule->notes)
                        <div class="dc-caption">
                            <strong class="text-dark">Catatan Petugas UDD:</strong> {{ $schedule->notes }}
                        </div>
                    @endif
                </div>
            </div>

            <!-- Right: Selectable Time Session Slots -->
            <div class="col-lg-7">
                <div class="dc-card p-4">
                    <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
                        <div>
                            <div class="dc-eyebrow">Pilih Waktu Kedatangan</div>
                            <h2 class="dc-title-3 mb-0 mt-1">Slot Sesi Pelayanan Tersedia</h2>
                        </div>
                        <span class="dc-pill dc-pill-success dc-tabular">
                            Total Sisa {{ $schedule->remaining_quota }} Kursi
                        </span>
                    </div>

                    <div class="vstack gap-3">
                        @foreach ($schedule->sessions as $session)
                            @php
                                $bookable = $session->isBookable();
                            @endphp
                            <div class="p-3 px-4 rounded-4 border {{ $bookable ? 'bg-white' : 'bg-light opacity-75' }}">
                                <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3">
                                    <div class="flex-grow-1">
                                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                            <span class="fw-bold text-dark">{{ $session->session_label }}</span>
                                            <span class="dc-pill {{ $session->operational_status->pillClass() }} py-0 px-2" style="font-size: 0.72rem;">
                                                {{ $session->operational_status->label() }}
                                            </span>
                                        </div>

                                        <div class="dc-caption dc-tabular mb-2">
                                            <i class="bi bi-clock text-danger me-1"></i>
                                            <strong>{{ $session->time_range_label }}</strong>
                                            &middot; Sisa <strong class="text-dark">{{ $session->remaining_quota }}</strong> dari {{ $session->quota_total }} kuota
                                        </div>

                                        <div class="progress rounded-pill" style="height: 0.42rem;">
                                            <div class="progress-bar bg-danger" style="width: {{ $session->occupancy_percentage }}%"></div>
                                        </div>

                                        @if ($session->operational_note)
                                            <div class="dc-caption text-danger mt-2">{{ $session->operational_note }}</div>
                                        @endif
                                    </div>

                                    <div class="flex-shrink-0">
                                        @auth
                                            @if (auth()->user()->isDonor())
                                                @if ($bookable && ! $activeBooking)
                                                    <button
                                                        type="button"
                                                        class="btn btn-dc-primary btn-sm js-select-session-btn"
                                                        data-dc-open-sheet="confirmSessionBookingSheet"
                                                        data-action-url="{{ route('bookings.store', $session) }}"
                                                        data-session-label="{{ $session->session_label }}"
                                                        data-session-time="{{ $session->time_range_label }}"
                                                        data-remaining="{{ $session->remaining_quota }}"
                                                    >
                                                        <i class="bi bi-calendar2-plus"></i>
                                                        Booking Sesi
                                                    </button>
                                                @elseif ($activeBooking)
                                                    <span class="dc-pill dc-pill-neutral">Sudah Ada Booking</span>
                                                @else
                                                    <button type="button" class="btn btn-dc-secondary btn-sm" disabled>
                                                        Slot Tidak Tersedia
                                                    </button>
                                                @endif
                                            @else
                                                <span class="dc-caption">Khusus Akun Pendonor</span>
                                            @endif
                                        @else
                                            <a href="{{ route('auth.login') }}" class="btn btn-dc-primary btn-sm">
                                                Masuk untuk Booking
                                            </a>
                                        @endauth
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

@auth
    @if (auth()->user()->isDonor())
        <!-- Fluid Bottom Sheet: Confirm Slot Reservation (§2, §5, §6, §9) -->
        <div class="dc-sheet-backdrop" data-dc-sheet-backdrop="confirmSessionBookingSheet"></div>
        <aside
            class="dc-sheet-panel"
            data-dc-sheet="confirmSessionBookingSheet"
            aria-hidden="true"
            aria-labelledby="confirmSessionBookingTitle"
        >
            <div class="dc-sheet-handle-area" title="Tarik ke bawah untuk menutup">
                <span class="dc-sheet-handle"></span>
            </div>

            <form id="confirmSessionBookingForm" method="POST" action="#" class="p-4 pt-2 overflow-auto">
                @csrf
                <div class="dc-eyebrow">Konfirmasi Reservasi Slot</div>
                <h2 id="confirmSessionBookingTitle" class="dc-title-3 mt-1 mb-1">Pilih Sesi Pelayanan</h2>
                <div id="confirmSessionBookingMeta" class="dc-caption dc-tabular mb-3"></div>

                <div class="p-3 rounded-4 bg-light border mb-3 small">
                    <div class="fw-semibold text-dark">{{ $schedule->venue_name }}</div>
                    <div class="dc-caption dc-tabular">{{ $schedule->service_date->translatedFormat('l, d F Y') }}</div>
                </div>

                <div class="mb-3">
                    <label for="donation_type" class="form-label">Jenis Layanan Donasi</label>
                    <select id="donation_type" name="donation_type" class="form-select">
                        <option value="WB">Whole Blood (WB) — Donor Darah Lengkap Standar</option>
                        <option value="APHERESIS">Apheresis — Komponen Trombosit / Plasma</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label for="donor_notes" class="form-label">Catatan Kedatangan (Opsional)</label>
                    <input
                        type="text"
                        id="donor_notes"
                        name="donor_notes"
                        maxlength="350"
                        class="form-control"
                        placeholder="Contoh: Membawa kartu donor fisik PMI Kota Bandung"
                    >
                </div>

                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-dc-secondary flex-grow-1" data-dc-close-sheet="confirmSessionBookingSheet">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-dc-primary flex-grow-1">
                        <i class="bi bi-check2-circle"></i>
                        Konfirmasi Booking Slot
                    </button>
                </div>
            </form>
        </aside>
    @endif
@endauth
@endsection

@push('scripts')
@if ($schedule->latitude !== null && $schedule->longitude !== null)
<script
    src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
    integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
    crossorigin=""
></script>
@endif
<script>
document.addEventListener('DOMContentLoaded', () => {
    const mapEl = document.getElementById('scheduleVenueMap');
    if (mapEl && typeof L !== 'undefined') {
        const lat = {{ (float) ($schedule->latitude ?? 0) }};
        const lng = {{ (float) ($schedule->longitude ?? 0) }};
        const map = L.map(mapEl, { scrollWheelZoom: false }).setView([lat, lng], 15);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors',
        }).addTo(map);
        L.marker([lat, lng]).addTo(map).bindPopup('<strong>{{ addslashes($schedule->venue_name) }}</strong>').openPopup();
    }

    const bookingForm = document.getElementById('confirmSessionBookingForm');
    const titleEl = document.getElementById('confirmSessionBookingTitle');
    const metaEl = document.getElementById('confirmSessionBookingMeta');

    if (bookingForm) {
        document.querySelectorAll('.js-select-session-btn').forEach((btn) => {
            btn.addEventListener('click', () => {
                bookingForm.setAttribute('action', btn.dataset.actionUrl);
                titleEl.textContent = btn.dataset.sessionLabel;
                metaEl.textContent = `${btn.dataset.sessionTime} · Sisa ${btn.dataset.remaining} kursi tersedia`;
            });
        });
    }
});
</script>
@endpush
