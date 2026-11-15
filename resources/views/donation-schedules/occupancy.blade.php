@extends('layouts.app')

@section('title', 'Pemantauan Okupansi Kuota & Status Operasional Slot')

@section('content')
<section class="py-4 py-lg-5">
    <div class="container">
        <!-- Header & Navigation -->
        <div class="d-flex flex-column flex-lg-row align-items-lg-end justify-content-between gap-3 mb-4">
            <div>
                <nav aria-label="breadcrumb" class="mb-1">
                    <ol class="breadcrumb mb-0 dc-caption">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-secondary">Ruang Kerja</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('schedules.index') }}" class="text-decoration-none text-secondary">Jadwal Gedung UDD</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Okupansi &amp; Status Operasional Slot</li>
                    </ol>
                </nav>

                <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                    <span class="dc-eyebrow">Kontrol Kapasitas Real-Time</span>
                    @if (auth()->user()->facility)
                        <span class="dc-pill dc-pill-neutral">
                            <i class="bi bi-building-fill-check text-danger"></i>
                            {{ auth()->user()->facility->name }}
                        </span>
                    @endif
                </div>
                <h1 class="dc-title-1 mb-1">Pemantauan Okupansi &amp; Status Operasional Slot</h1>
                <p class="dc-caption mb-0">
                    Pantau keterisian pendaftar per sesi secara langsung dan perbarui status slot apabila terjadi kendala teknis mendadak di gedung UDD maupun lokasi Mobile Unit.
                </p>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('schedules.index') }}" class="btn btn-dc-secondary">
                    <i class="bi bi-building-fill-plus"></i>
                    Master Gedung UDD
                </a>
                <a href="{{ route('schedules.mobile.index') }}" class="btn btn-dc-secondary">
                    <i class="bi bi-truck-front"></i>
                    Agenda Mobile Unit
                </a>
            </div>
        </div>

        @if ($errors->any())
            <div class="dc-card p-3 mb-4 border-danger-subtle bg-danger-subtle text-danger-emphasis" role="alert">
                <div class="fw-semibold small mb-1">
                    <i class="bi bi-exclamation-circle-fill me-1"></i>Pembaruan status operasional belum berhasil:
                </div>
                <ul class="mb-0 ps-3 small">
                    @foreach ($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Real-Time Operational KPI Summary -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="dc-card p-3 h-100">
                    <div class="dc-caption">Total Sesi Dipantau</div>
                    <div class="dc-title-2 dc-tabular mb-0 mt-1">{{ $summary['total_sessions'] }}</div>
                    <div class="dc-caption mt-1">Lintas jadwal terfilter</div>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <div class="dc-card p-3 h-100">
                    <div class="dc-caption">Rasio Okupansi Kursi</div>
                    <div class="dc-title-2 dc-tabular text-danger mb-0 mt-1">{{ $summary['occupancy_rate'] }}%</div>
                    <div class="dc-caption dc-tabular mt-1">
                        {{ $summary['total_booked'] }} terisi dari {{ $summary['total_quota'] }} kuota
                    </div>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <div class="dc-card p-3 h-100">
                    <div class="dc-caption">Sesi Dibuka Normal</div>
                    <div class="dc-title-2 dc-tabular text-success mb-0 mt-1">{{ $summary['open_sessions'] }}</div>
                    <div class="dc-caption dc-tabular mt-1">
                        {{ $summary['full_sessions'] }} sesi kuota penuh
                    </div>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <div class="dc-card p-3 h-100">
                    <div class="dc-caption">Sesi Jeda / Kendala Teknis</div>
                    <div class="dc-title-2 dc-tabular {{ $summary['disrupted_sessions'] > 0 ? 'text-warning-emphasis' : 'text-secondary' }} mb-0 mt-1">
                        {{ $summary['disrupted_sessions'] }}
                    </div>
                    <div class="dc-caption mt-1">Pemesanan baru terkunci</div>
                </div>
            </div>
        </div>

        <!-- Translucent Multi-Filter Control Bar -->
        <div class="dc-glass-bar p-3 mb-4">
            <form method="GET" action="{{ route('schedules.occupancy.index') }}" class="row g-2 align-items-end">
                <div class="col-6 col-md-3">
                    <label for="occupancyPeriod" class="form-label small mb-1">Rentang Hari Layanan</label>
                    <select id="occupancyPeriod" name="period" class="form-select form-select-sm">
                        <option value="today" @selected($periodFilter === 'today')>Khusus Hari Ini</option>
                        <option value="upcoming" @selected($periodFilter === 'upcoming')>Hari Ini &amp; Mendatang</option>
                        <option value="all" @selected($periodFilter === 'all')>Semua Riwayat Jadwal</option>
                    </select>
                </div>

                <div class="col-6 col-md-3">
                    <label for="occupancyType" class="form-label small mb-1">Kategori Lokasi</label>
                    <select id="occupancyType" name="type" class="form-select form-select-sm">
                        <option value="all" @selected($typeFilter === 'all')>Gedung UDD &amp; Mobile Unit</option>
                        <option value="udd_building" @selected($typeFilter === 'udd_building')>Gedung UDD PMI</option>
                        <option value="mobile_unit" @selected($typeFilter === 'mobile_unit')>Event Mobile Unit</option>
                    </select>
                </div>

                <div class="col-6 col-md-3">
                    <label for="occupancyStatus" class="form-label small mb-1">Status Operasional Slot</label>
                    <select id="occupancyStatus" name="status" class="form-select form-select-sm">
                        <option value="all" @selected($statusFilter === 'all')>Semua Status Slot</option>
                        @foreach ($statusOptions as $st)
                            <option value="{{ $st->value }}" @selected($statusFilter === $st->value)>
                                {{ $st->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                @if (! $isLockedToSingleUdd)
                    <div class="col-6 col-md-2">
                        <label for="occupancyFacility" class="form-label small mb-1">Unit UDD</label>
                        <select id="occupancyFacility" name="facility_id" class="form-select form-select-sm">
                            <option value="">Semua UDD</option>
                            @foreach ($uddFacilities as $udd)
                                <option value="{{ $udd->id }}" @selected($facilityFilter === $udd->id)>
                                    {{ $udd->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="col-12 {{ $isLockedToSingleUdd ? 'col-md-3' : 'col-md-1' }} d-grid">
                    <button type="submit" class="btn btn-dc-primary btn-sm">Terapkan</button>
                </div>
            </form>
        </div>

        <!-- Schedule & Session Cards -->
        <div class="vstack gap-4">
            @forelse ($schedules as $schedule)
                <article class="dc-card p-4 {{ $schedule->hasDisruptedSessions() ? 'border-warning' : '' }}">
                    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 pb-3 mb-3 border-bottom">
                        <div>
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                <span class="dc-pill {{ $schedule->schedule_type->pillClass() }}">
                                    <i class="bi {{ $schedule->schedule_type->icon() }}"></i>
                                    {{ $schedule->schedule_type->label() }}
                                </span>
                                <span class="dc-pill {{ $schedule->publication_pill_class }}">
                                    {{ $schedule->publication_label }}
                                </span>
                                <span class="dc-caption dc-tabular fw-semibold">{{ $schedule->schedule_code }}</span>
                            </div>
                            <h2 class="dc-title-3 mb-0 mt-1">
                                <a href="{{ route('schedules.show', $schedule) }}" class="text-decoration-none text-dark">
                                    {{ $schedule->service_date->translatedFormat('l, d F Y') }} — {{ $schedule->title }}
                                </a>
                            </h2>
                            <div class="dc-caption mt-1">
                                <i class="bi bi-geo-alt text-danger me-1"></i>{{ $schedule->venue_name }}
                                &middot; Jam {{ $schedule->operating_hours_label }}
                            </div>
                        </div>

                        <div class="d-flex flex-wrap align-items-center gap-3">
                            <div class="text-lg-end">
                                <div class="fw-bold dc-tabular text-dark">
                                    Okupansi Harian: {{ $schedule->total_booked }} / {{ $schedule->total_quota }} ({{ $schedule->occupancy_percentage }}%)
                                </div>
                                <div class="dc-caption dc-tabular text-success">
                                    {{ $schedule->remaining_quota }} kursi efektif siap dipesan
                                </div>
                            </div>
                            <a href="{{ route('schedules.show', $schedule) }}" class="btn btn-dc-secondary btn-sm">
                                Detail Jadwal
                            </a>
                        </div>
                    </div>

                    <div class="row g-3">
                        @foreach ($schedule->sessions as $session)
                            @if ($statusFilter === 'all' || $session->operational_status->value === $statusFilter)
                                @php
                                    $activeRegistrants = $session->bookings;
                                    $confirmedCount = $activeRegistrants->where('status', \App\Enums\DonorBookingStatus::CONFIRMED)->count();
                                    $pendingCount = $activeRegistrants->where('status', \App\Enums\DonorBookingStatus::PENDING_SCREENING)->count();
                                @endphp
                                <div class="col-lg-6">
                                    <div class="p-3 rounded-4 border h-100 d-flex flex-column justify-content-between {{ $session->operational_status->value !== 'open' ? 'bg-warning-subtle bg-opacity-25 border-warning-subtle' : 'bg-white' }}">
                                        <div>
                                            <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-2">
                                                <div>
                                                    <div class="fw-bold text-dark">{{ $session->session_label }}</div>
                                                    <div class="dc-caption dc-tabular">
                                                        <i class="bi bi-clock text-danger me-1"></i>{{ $session->time_range_label }}
                                                    </div>
                                                </div>

                                                <span class="dc-pill {{ $session->operational_status->pillClass() }}">
                                                    <i class="bi {{ $session->operational_status->icon() }}"></i>
                                                    {{ $session->operational_status->label() }}
                                                </span>
                                            </div>

                                            <div class="d-flex align-items-center justify-content-between small mb-1">
                                                <span class="dc-caption">Keterisian Kuota Sesi</span>
                                                <span class="fw-bold dc-tabular text-dark">
                                                    {{ $session->quota_booked }} / {{ $session->quota_total }} Kursi ({{ $session->occupancy_percentage }}%)
                                                </span>
                                            </div>
                                            <div class="progress rounded-pill mb-2" style="height: 0.48rem;">
                                                <div
                                                    class="progress-bar {{ $session->occupancy_percentage >= 90 ? 'bg-danger' : 'bg-success' }}"
                                                    style="width: {{ max(4, $session->occupancy_percentage) }}%"
                                                ></div>
                                            </div>

                                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 dc-caption dc-tabular">
                                                <span>
                                                    Terkonfirmasi: <strong class="text-dark">{{ $confirmedCount }}</strong>
                                                    @if ($pendingCount > 0)
                                                        &middot; Menunggu Anamnesis: <strong class="text-warning-emphasis">{{ $pendingCount }}</strong>
                                                    @endif
                                                </span>
                                                <span>
                                                    Sisa Slot:
                                                    <strong class="{{ $session->isBookable() ? 'text-success' : 'text-danger' }}">
                                                        {{ $session->isBookable() ? $session->remaining_quota.' tersedia' : '0 (Terkunci/Penuh)' }}
                                                    </strong>
                                                </span>
                                            </div>

                                            @if ($session->operational_note)
                                                <div class="p-2 px-3 rounded-3 bg-white border mt-2 dc-caption text-danger-emphasis">
                                                    <i class="bi bi-exclamation-triangle-fill text-danger me-1"></i>
                                                    <strong>Catatan Kendala:</strong> {{ $session->operational_note }}
                                                </div>
                                            @endif

                                            @if ($activeRegistrants->isNotEmpty())
                                                <div class="mt-2 pt-2 border-top">
                                                    <div class="dc-caption fw-semibold text-dark mb-1">Daftar Pendaftar Sesi:</div>
                                                    <div class="d-flex flex-wrap gap-1">
                                                        @foreach ($activeRegistrants->take(5) as $reg)
                                                            <span class="badge rounded-pill text-bg-light border text-dark fw-normal dc-tabular">
                                                                {{ $reg->booking_code }} &middot; {{ $reg->donor?->name }}
                                                                @if ($reg->donor?->blood_group_label)
                                                                    ({{ $reg->donor->blood_group_label }})
                                                                @endif
                                                            </span>
                                                        @endforeach
                                                        @if ($activeRegistrants->count() > 5)
                                                            <span class="dc-caption">+{{ $activeRegistrants->count() - 5 }} lainnya</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            @endif
                                        </div>

                                        <div class="mt-3 pt-2 border-top d-flex align-items-center justify-content-end gap-2">
                                            <button
                                                type="button"
                                                class="btn btn-dc-secondary btn-sm dc-pressable"
                                                data-dc-open-sheet="sessionStatusSheet-{{ $session->id }}"
                                            >
                                                <i class="bi bi-toggles"></i>
                                                Atur Status &amp; Kuota Slot
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </article>
            @empty
                <div class="dc-card p-5 text-center">
                    <div class="mx-auto mb-3 d-inline-flex align-items-center justify-content-center rounded-circle bg-light text-secondary" style="width: 3.5rem; height: 3.5rem;">
                        <i class="bi bi-sliders2-vertical fs-4"></i>
                    </div>
                    <h2 class="dc-title-3 mb-1">Tidak Ada Sesi Jadwal pada Filter Ini</h2>
                    <p class="dc-caption mb-3 mx-auto" style="max-width: 30rem;">
                        Belum ditemukan sesi pelayanan gedung UDD atau Mobile Unit yang sesuai dengan rentang tanggal dan status operasional yang dipilih.
                    </p>
                    <a href="{{ route('schedules.occupancy.index') }}" class="btn btn-dc-secondary btn-sm">
                        Tampilkan Semua Sesi Mendatang
                    </a>
                </div>
            @endforelse
        </div>
    </div>
</section>

@foreach ($schedules as $schedule)
    @foreach ($schedule->sessions as $session)
        @include('donation-schedules.partials.session-status-sheet', [
            'schedule' => $schedule,
            'session' => $session,
        ])
    @endforeach
@endforeach
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.js-status-radio').forEach((radio) => {
        radio.addEventListener('change', () => {
            const sessionId = radio.dataset.sessionId;
            const reqBadge = document.getElementById(`noteReqBadge-${sessionId}`);
            if (reqBadge) {
                reqBadge.classList.toggle('d-none', radio.value === 'open');
            }
        });
    });

    document.querySelectorAll('.js-issue-preset-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            const targetId = btn.dataset.target;
            const textarea = targetId ? document.getElementById(targetId) : null;
            if (textarea) {
                textarea.value = btn.dataset.note || '';
                textarea.focus();
            }
        });
    });
});
</script>
@endpush
