@extends('layouts.app')

@section('title', 'Master Jadwal & Sesi Harian Gedung UDD')

@section('content')
<section class="py-4 py-lg-5">
    <div class="container">
        <!-- Header & Primary Actions -->
        <div class="d-flex flex-column flex-lg-row align-items-lg-end justify-content-between gap-3 mb-4">
            <div>
                <nav aria-label="breadcrumb" class="mb-1">
                    <ol class="breadcrumb mb-0 dc-caption">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-secondary">Ruang Kerja</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Master Jadwal Gedung UDD</li>
                    </ol>
                </nav>
                <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                    <span class="dc-eyebrow">Operasional Layanan Donor</span>
                    @if (auth()->user()->facility)
                        <span class="dc-pill dc-pill-neutral">
                            <i class="bi bi-building-fill-check text-danger"></i>
                            {{ auth()->user()->facility->name }}
                        </span>
                    @endif
                </div>
                <h1 class="dc-title-1 mb-1">Master Jadwal &amp; Sesi Gedung UDD</h1>
                <p class="dc-caption mb-0">
                    Kelola hari layanan, pembagian sesi waktu flebotomi, dan kuota pendonor harian di gedung UDD PMI.
                </p>
            </div>

            <div class="d-flex flex-wrap gap-2">
                @if (Route::has('schedules.occupancy.index'))
                    <a href="{{ route('schedules.occupancy.index') }}" class="btn btn-dc-secondary">
                        <i class="bi bi-sliders2-vertical"></i>
                        Pantau Okupansi Slot
                    </a>
                @endif

                @if (Route::has('schedules.mobile.index'))
                    <a href="{{ route('schedules.mobile.index') }}" class="btn btn-dc-secondary">
                        <i class="bi bi-truck-front"></i>
                        Agenda Mobile Unit
                    </a>
                @endif

                <a href="{{ route('schedules.create') }}" class="btn btn-dc-primary">
                    <i class="bi bi-calendar-plus-fill"></i>
                    Buat Jadwal Harian Baru
                </a>
            </div>
        </div>

        <!-- Operational Capacity KPI Cards -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="dc-card p-3 h-100">
                    <div class="dc-caption">Hari Layanan Mendatang</div>
                    <div class="dc-title-2 dc-tabular mb-0 mt-1">{{ $summary['upcoming_days'] }}</div>
                    <div class="dc-caption mt-1">Jadwal aktif terbit</div>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <div class="dc-card p-3 h-100">
                    <div class="dc-caption">Total Sesi Terjadwal</div>
                    <div class="dc-title-2 dc-tabular text-primary mb-0 mt-1">{{ $summary['active_sessions'] }}</div>
                    <div class="dc-caption mt-1">Sesi pagi, siang &amp; sore</div>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <div class="dc-card p-3 h-100">
                    <div class="dc-caption">Total Kapasitas Kuota</div>
                    <div class="dc-title-2 dc-tabular mb-0 mt-1">{{ $summary['total_capacity'] }}</div>
                    <div class="dc-caption mt-1">Kursi layanan pendonor</div>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <div class="dc-card p-3 h-100">
                    <div class="dc-caption">Slot Masih Tersedia</div>
                    <div class="dc-title-2 dc-tabular text-success mb-0 mt-1">{{ $summary['available_slots'] }}</div>
                    <div class="dc-caption mt-1">Siap dipesan pendonor</div>
                </div>
            </div>
        </div>

        <!-- Translucent Period & Facility Filter Bar -->
        <div class="dc-glass-bar p-3 mb-4 d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
            <div class="d-flex flex-wrap align-items-center gap-1">
                <a
                    href="{{ route('schedules.index', array_filter(['facility_id' => $facilityFilter ?: null])) }}"
                    class="dc-nav-pill {{ $scopeFilter === 'upcoming' ? 'active' : '' }}"
                >
                    Jadwal Mendatang
                </a>
                <a
                    href="{{ route('schedules.index', array_filter(['period' => 'all', 'facility_id' => $facilityFilter ?: null])) }}"
                    class="dc-nav-pill {{ $scopeFilter === 'all' ? 'active' : '' }}"
                >
                    Semua Riwayat Jadwal
                </a>
            </div>

            @if (! $isLockedToSingleUdd)
                <form method="GET" action="{{ route('schedules.index') }}" class="d-flex align-items-center gap-2">
                    @if ($scopeFilter !== 'upcoming')
                        <input type="hidden" name="period" value="{{ $scopeFilter }}">
                    @endif
                    <select name="facility_id" class="form-select form-select-sm" style="min-width: 15rem;" aria-label="Pilih gedung UDD">
                        <option value="">Semua Gedung UDD PMI</option>
                        @foreach ($uddFacilities as $udd)
                            <option value="{{ $udd->id }}" @selected($facilityFilter === $udd->id)>
                                {{ $udd->name }}
                            </option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-dc-secondary btn-sm">Terapkan</button>
                </form>
            @endif
        </div>

        <!-- Schedules List -->
        <div class="vstack gap-3">
            @forelse ($schedules as $schedule)
                <article class="dc-card p-4">
                    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 pb-3 mb-3 border-bottom">
                        <div>
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                <span class="dc-pill {{ $schedule->schedule_type->pillClass() }}">
                                    <i class="bi {{ $schedule->schedule_type->icon() }}"></i>
                                    {{ $schedule->schedule_type->label() }}
                                </span>
                                <span class="dc-pill dc-pill-neutral dc-tabular">
                                    <i class="bi bi-clock"></i>
                                    {{ $schedule->operating_hours_label }}
                                </span>
                                <span class="dc-caption dc-tabular fw-semibold">{{ $schedule->schedule_code }}</span>
                            </div>

                            <h2 class="dc-title-3 mb-1">
                                <a href="{{ route('schedules.show', $schedule) }}" class="text-decoration-none text-dark">
                                    {{ $schedule->service_date->translatedFormat('l, d F Y') }}
                                </a>
                                <span class="dc-caption fw-normal">&middot; {{ $schedule->facility?->name }}</span>
                            </h2>
                            <div class="dc-caption">{{ $schedule->title }}</div>
                        </div>

                        <div class="d-flex flex-wrap align-items-center gap-3">
                            <div class="text-lg-end">
                                <div class="fw-bold dc-tabular text-dark">
                                    Terisi {{ $schedule->total_booked }} / {{ $schedule->total_quota }} Kuota
                                </div>
                                <div class="dc-caption dc-tabular text-success">
                                    {{ $schedule->remaining_quota }} kursi tersedia ({{ $schedule->sessions->count() }} sesi)
                                </div>
                            </div>

                            <a href="{{ route('schedules.show', $schedule) }}" class="btn btn-dc-secondary btn-sm">
                                <i class="bi bi-sliders"></i>
                                Rincian Sesi
                            </a>
                        </div>
                    </div>

                    <!-- Per-Session Chips -->
                    <div class="row g-2">
                        @foreach ($schedule->sessions as $session)
                            <div class="col-md-4">
                                <div class="p-2 px-3 rounded-3 bg-light border d-flex align-items-center justify-content-between gap-2 h-100">
                                    <div>
                                        <div class="small fw-semibold text-dark">{{ $session->session_label }}</div>
                                        <div class="dc-caption dc-tabular">{{ $session->time_range_label }}</div>
                                    </div>
                                    <div class="text-end">
                                        <span class="dc-pill {{ $session->operational_status->pillClass() }} dc-tabular py-1 px-2" style="font-size: 0.72rem;">
                                            {{ $session->remaining_quota }}/{{ $session->quota_total }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </article>
            @empty
                <div class="dc-card p-5 text-center">
                    <div class="mx-auto mb-3 d-inline-flex align-items-center justify-content-center rounded-circle bg-light text-secondary" style="width: 3.5rem; height: 3.5rem;">
                        <i class="bi bi-calendar2-week fs-4"></i>
                    </div>
                    <h2 class="dc-title-3 mb-1">Belum Ada Master Jadwal Gedung UDD</h2>
                    <p class="dc-caption mb-3 mx-auto" style="max-width: 28rem;">
                        Terbitkan jadwal harian gedung UDD beserta pembagian kuota sesi agar pendonor dapat memesan waktu kedatangan secara tertib.
                    </p>
                    <a href="{{ route('schedules.create') }}" class="btn btn-dc-primary btn-sm">
                        <i class="bi bi-plus-circle"></i>
                        Buat Jadwal Harian Pertama
                    </a>
                </div>
            @endforelse
        </div>

        @if ($schedules->hasPages())
            <div class="mt-4">
                {{ $schedules->links() }}
            </div>
        @endif
    </div>
</section>
@endsection
