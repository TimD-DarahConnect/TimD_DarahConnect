@extends('layouts.app')

@section('title', 'Detail Jadwal ' . $schedule->schedule_code)

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
@php
    $isMobileUnit = $schedule->isMobileUnit();
    $parentIndexRoute = $isMobileUnit ? route('schedules.mobile.index') : route('schedules.index');
    $parentIndexLabel = $isMobileUnit ? 'Agenda Mobile Unit' : 'Master Jadwal Gedung UDD';
@endphp
<section class="py-4 py-lg-5">
    <div class="container" style="max-width: 68rem;">
        <!-- Header & Wayfinding -->
        <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
            <div>
                <nav aria-label="breadcrumb" class="mb-1">
                    <ol class="breadcrumb mb-0 dc-caption">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-secondary">Ruang Kerja</a></li>
                        <li class="breadcrumb-item"><a href="{{ $parentIndexRoute }}" class="text-decoration-none text-secondary">{{ $parentIndexLabel }}</a></li>
                        <li class="breadcrumb-item active dc-tabular" aria-current="page">{{ $schedule->schedule_code }}</li>
                    </ol>
                </nav>

                <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
                    <span class="dc-pill {{ $schedule->schedule_type->pillClass() }}">
                        <i class="bi {{ $schedule->schedule_type->icon() }}"></i>
                        {{ $schedule->schedule_type->label() }}
                    </span>
                    <span class="dc-pill {{ $schedule->publication_pill_class }}">
                        <i class="bi {{ $schedule->isPublished() ? 'bi-broadcast' : 'bi-file-earmark-lock2' }}"></i>
                        {{ $schedule->publication_label }}
                    </span>
                    <span class="dc-caption dc-tabular">Kode: <strong>{{ $schedule->schedule_code }}</strong></span>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2">
                @if ($isMobileUnit)
                    <form method="POST" action="{{ route('schedules.publication.update', $schedule) }}" class="d-inline">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="publication_status" value="{{ $schedule->isPublished() ? 'draft' : 'published' }}">
                        @if ($schedule->isPublished())
                            <button
                                type="submit"
                                class="btn btn-dc-secondary"
                                @disabled(! empty($hasActiveBookings))
                                title="{{ ! empty($hasActiveBookings) ? 'Sudah memiliki pendaftar aktif' : 'Tarik kembali ke draf internal' }}"
                            >
                                <i class="bi bi-eye-slash"></i>
                                Jadikan Draf
                            </button>
                        @else
                            <button type="submit" class="btn btn-dc-primary">
                                <i class="bi bi-broadcast"></i>
                                Publikasikan Acara
                            </button>
                        @endif
                    </form>

                    <a href="{{ route('schedules.mobile.edit', $schedule) }}" class="btn btn-dc-soft">
                        <i class="bi bi-pencil-square"></i>
                        Edit Agenda Mitra
                    </a>
                @else
                    <a href="{{ route('schedules.create') }}" class="btn btn-dc-soft">
                        <i class="bi bi-plus-circle"></i>
                        Tambah Tanggal Lain
                    </a>
                @endif

                <a href="{{ $parentIndexRoute }}" class="btn btn-dc-secondary">
                    <i class="bi bi-arrow-left"></i>
                    Kembali
                </a>
            </div>
        </div>

        <div class="row g-4 align-items-start">
            <!-- Left: Schedule / Mobile Unit Metadata -->
            <div class="col-lg-5">
                <div class="dc-card p-4 mb-4">
                    <div class="dc-eyebrow">{{ $isMobileUnit ? 'Informasi Event Donor Mitra' : 'Informasi Layanan Harian' }}</div>
                    <h1 class="dc-title-2 mt-1 mb-1">{{ $schedule->service_date->translatedFormat('l, d F Y') }}</h1>
                    <p class="dc-caption mb-3">{{ $schedule->title }}</p>

                    @if ($schedule->partner_institution)
                        <div class="p-3 rounded-4 bg-danger-subtle bg-opacity-25 border border-danger-subtle mb-3 small">
                            <div class="dc-caption text-secondary">Instansi Mitra Penyelenggara</div>
                            <div class="fw-bold text-dark mt-1">
                                <i class="bi bi-people-fill text-danger me-1"></i>
                                {{ $schedule->partner_institution }}
                            </div>
                            <div class="dc-caption mt-1">
                                Unit UDD Pengampu: <strong>{{ $schedule->facility?->name }}</strong>
                            </div>
                        </div>
                    @endif

                    <div class="p-3 rounded-4 bg-light border mb-3">
                        <div class="fw-semibold text-dark">
                            <i class="bi {{ $isMobileUnit ? 'bi-geo-alt-fill' : 'bi-building-fill-plus' }} text-danger me-1"></i>
                            {{ $schedule->venue_name }}
                        </div>
                        <div class="dc-caption mt-1">
                            {{ $schedule->venue_address }}
                            @if ($schedule->region)
                                &middot; {{ $schedule->region->name }}
                            @endif
                        </div>
                        <div class="dc-caption dc-tabular mt-2 fw-medium text-dark">
                            <i class="bi bi-clock me-1 text-danger"></i>
                            Jam Operasional: {{ $schedule->operating_hours_label }}
                        </div>
                    </div>

                    @if ($schedule->latitude !== null && $schedule->longitude !== null)
                        <div id="scheduleDetailMap" class="rounded-4 border mb-3" style="height: 13.5rem; z-index: 1;" aria-label="Peta titik lokasi kegiatan"></div>
                    @endif

                    <div class="row g-3 small">
                        @if ($isMobileUnit)
                            <div class="col-4">
                                <div class="dc-caption">Target Kantong</div>
                                <div class="dc-title-3 dc-tabular text-danger mb-0 mt-1">{{ $schedule->target_bags }}</div>
                            </div>
                            <div class="col-4">
                                <div class="dc-caption">Kuota Sesi</div>
                                <div class="dc-title-3 dc-tabular mb-0 mt-1">{{ $schedule->total_quota }}</div>
                            </div>
                            <div class="col-4">
                                <div class="dc-caption">Sisa Slot</div>
                                <div class="dc-title-3 dc-tabular text-success mb-0 mt-1">{{ $schedule->remaining_quota }}</div>
                            </div>
                        @else
                            <div class="col-6">
                                <div class="dc-caption">Total Kuota Harian</div>
                                <div class="dc-title-3 dc-tabular mb-0 mt-1">{{ $schedule->total_quota }} Kursi</div>
                            </div>
                            <div class="col-6">
                                <div class="dc-caption">Sisa Slot Tersedia</div>
                                <div class="dc-title-3 dc-tabular text-success mb-0 mt-1">{{ $schedule->remaining_quota }} Kursi</div>
                            </div>
                        @endif
                    </div>

                    @if ($schedule->notes)
                        <hr class="my-3">
                        <div class="dc-caption fw-semibold text-dark mb-1">Catatan Layanan:</div>
                        <p class="dc-caption mb-0">{{ $schedule->notes }}</p>
                    @endif
                </div>
            </div>

            <!-- Right: Ordered Session Slots -->
            <div class="col-lg-7">
                <div class="dc-card p-4">
                    <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
                        <div>
                            <div class="dc-eyebrow">Pembagian Waktu</div>
                            <h2 class="dc-title-3 mb-0 mt-1">Daftar Sesi &amp; Kuota Pelayanan ({{ $schedule->sessions->count() }} Sesi)</h2>
                        </div>
                    </div>

                    <div class="vstack gap-3">
                        @foreach ($schedule->sessions as $session)
                            <div class="p-3 px-4 rounded-4 border bg-white">
                                <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2 mb-2">
                                    <div>
                                        <div class="fw-bold text-dark">{{ $session->session_label }}</div>
                                        <div class="dc-caption dc-tabular">
                                            <i class="bi bi-clock me-1 text-danger"></i>
                                            {{ $session->time_range_label }}
                                        </div>
                                    </div>

                                    <span class="dc-pill {{ $session->operational_status->pillClass() }}">
                                        <i class="bi {{ $session->operational_status->icon() }}"></i>
                                        {{ $session->operational_status->label() }}
                                    </span>
                                </div>

                                <div class="d-flex align-items-center justify-content-between small mb-1 mt-2">
                                    <span class="dc-caption">Okupansi Pendaftar Sesi</span>
                                    <span class="dc-tabular fw-semibold text-dark">
                                        {{ $session->quota_booked }} terisi / {{ $session->quota_total }} kuota ({{ $session->remaining_quota }} tersedia)
                                    </span>
                                </div>

                                <div class="progress rounded-pill" style="height: 0.45rem;">
                                    <div class="progress-bar bg-danger" style="width: {{ $session->occupancy_percentage }}%"></div>
                                </div>

                                @if ($session->operational_note)
                                    <div class="dc-caption text-danger mt-2">
                                        <i class="bi bi-info-circle me-1"></i>{{ $session->operational_note }}
                                    </div>
                                @endif

                                @if (Route::has('schedules.sessions.status'))
                                    <div class="mt-3 pt-2 border-top d-flex justify-content-end">
                                        <button type="button" class="btn btn-dc-secondary btn-sm" data-dc-open-sheet="sessionStatusSheet-{{ $session->id }}">
                                            <i class="bi bi-toggles"></i>
                                            Atur Status Slot
                                        </button>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@push('scripts')
@if ($schedule->latitude !== null && $schedule->longitude !== null)
<script
    src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
    integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
    crossorigin=""
></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const mapEl = document.getElementById('scheduleDetailMap');
    if (mapEl && typeof L !== 'undefined') {
        const lat = {{ (float) $schedule->latitude }};
        const lng = {{ (float) $schedule->longitude }};
        const map = L.map(mapEl, { scrollWheelZoom: false }).setView([lat, lng], 15);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors',
        }).addTo(map);
        L.marker([lat, lng]).addTo(map).bindPopup('<strong>{{ addslashes($schedule->venue_name) }}</strong>').openPopup();
    }
});
</script>
@endif
@endpush
