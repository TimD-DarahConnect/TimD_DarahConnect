@extends('layouts.app')

@section('title', 'Agenda Kegiatan Mobile Unit & Event Mitra')

@section('content')
<section class="py-4 py-lg-5">
    <div class="container">
        <!-- Header & Primary Actions -->
        <div class="d-flex flex-column flex-lg-row align-items-lg-end justify-content-between gap-3 mb-4">
            <div>
                <nav aria-label="breadcrumb" class="mb-1">
                    <ol class="breadcrumb mb-0 dc-caption">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-secondary">Ruang Kerja</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('schedules.index') }}" class="text-decoration-none text-secondary">Jadwal Gedung UDD</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Agenda Mobile Unit</li>
                    </ol>
                </nav>
                <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                    <span class="dc-eyebrow">Layanan Donor Keliling Eksternal</span>
                    @if (auth()->user()->facility)
                        <span class="dc-pill dc-pill-neutral">
                            <i class="bi bi-building-fill-check text-danger"></i>
                            {{ auth()->user()->facility->name }}
                        </span>
                    @endif
                </div>
                <h1 class="dc-title-1 mb-1">Agenda Kegiatan Mobile Unit &amp; Event Mitra</h1>
                <p class="dc-caption mb-0">
                    Kelola jadwal donor darah keliling bersama kampus, korporasi, dan instansi mitra lengkap dengan target kantong, titik peta lokasi, dan status publikasi acara.
                </p>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('schedules.index') }}" class="btn btn-dc-secondary">
                    <i class="bi bi-building-fill-plus"></i>
                    Jadwal Gedung UDD
                </a>

                <a href="{{ route('schedules.mobile.create') }}" class="btn btn-dc-primary">
                    <i class="bi bi-truck-front-fill"></i>
                    Rencanakan Event Mobile Unit
                </a>
            </div>
        </div>

        <!-- Mobile Unit KPI Summary Cards -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="dc-card p-3 h-100">
                    <div class="dc-caption">Agenda Mendatang</div>
                    <div class="dc-title-2 dc-tabular mb-0 mt-1">{{ $summary['upcoming_events'] }}</div>
                    <div class="dc-caption mt-1">Total rencana &amp; publikasi</div>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <div class="dc-card p-3 h-100">
                    <div class="dc-caption">Acara Dipublikasikan</div>
                    <div class="dc-title-2 dc-tabular text-success mb-0 mt-1">{{ $summary['published_events'] }}</div>
                    <div class="dc-caption mt-1">Tampil di katalog pendonor</div>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <div class="dc-card p-3 h-100">
                    <div class="dc-caption">Rencana Draf Internal</div>
                    <div class="dc-title-2 dc-tabular text-warning-emphasis mb-0 mt-1">{{ $summary['draft_events'] }}</div>
                    <div class="dc-caption mt-1">Menunggu finalisasi mitra</div>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <div class="dc-card p-3 h-100">
                    <div class="dc-caption">Akumulasi Target Kantong</div>
                    <div class="dc-title-2 dc-tabular text-danger mb-0 mt-1">{{ $summary['target_bags_total'] }}</div>
                    <div class="dc-caption mt-1">Target kantong darah mendatang</div>
                </div>
            </div>
        </div>

        <!-- Translucent Status, Period & Facility Filter Bar -->
        <div class="dc-glass-bar p-3 mb-4">
            <div class="d-flex flex-column flex-xl-row align-items-xl-center justify-content-between gap-3">
                <div class="d-flex flex-wrap align-items-center gap-1">
                    <a
                        href="{{ route('schedules.mobile.index', array_filter(['period' => $scopeFilter !== 'upcoming' ? $scopeFilter : null, 'facility_id' => $facilityFilter ?: null])) }}"
                        class="dc-nav-pill {{ $statusFilter === 'all' ? 'active' : '' }}"
                    >
                        Semua Status
                    </a>
                    <a
                        href="{{ route('schedules.mobile.index', array_filter(['status' => 'published', 'period' => $scopeFilter !== 'upcoming' ? $scopeFilter : null, 'facility_id' => $facilityFilter ?: null])) }}"
                        class="dc-nav-pill {{ $statusFilter === 'published' ? 'active' : '' }}"
                    >
                        <i class="bi bi-broadcast me-1"></i>Dipublikasikan
                    </a>
                    <a
                        href="{{ route('schedules.mobile.index', array_filter(['status' => 'draft', 'period' => $scopeFilter !== 'upcoming' ? $scopeFilter : null, 'facility_id' => $facilityFilter ?: null])) }}"
                        class="dc-nav-pill {{ $statusFilter === 'draft' ? 'active' : '' }}"
                    >
                        <i class="bi bi-file-earmark-lock2 me-1"></i>Draf Internal
                    </a>

                    <span class="text-secondary opacity-50 mx-1 d-none d-sm-inline">|</span>

                    <a
                        href="{{ route('schedules.mobile.index', array_filter(['status' => $statusFilter !== 'all' ? $statusFilter : null, 'facility_id' => $facilityFilter ?: null])) }}"
                        class="dc-nav-pill {{ $scopeFilter === 'upcoming' ? 'active' : '' }}"
                    >
                        Mendatang
                    </a>
                    <a
                        href="{{ route('schedules.mobile.index', array_filter(['period' => 'all', 'status' => $statusFilter !== 'all' ? $statusFilter : null, 'facility_id' => $facilityFilter ?: null])) }}"
                        class="dc-nav-pill {{ $scopeFilter === 'all' ? 'active' : '' }}"
                    >
                        Semua Riwayat
                    </a>
                </div>

                @if (! $isLockedToSingleUdd)
                    <form method="GET" action="{{ route('schedules.mobile.index') }}" class="d-flex align-items-center gap-2">
                        @if ($scopeFilter !== 'upcoming')
                            <input type="hidden" name="period" value="{{ $scopeFilter }}">
                        @endif
                        @if ($statusFilter !== 'all')
                            <input type="hidden" name="status" value="{{ $statusFilter }}">
                        @endif
                        <select name="facility_id" class="form-select form-select-sm" style="min-width: 15rem;" aria-label="Pilih unit UDD penyelenggara">
                            <option value="">Semua Unit UDD PMI</option>
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
        </div>

        <!-- Mobile Unit Event Cards -->
        <div class="vstack gap-3">
            @forelse ($events as $event)
                <article class="dc-card p-4">
                    <div class="d-flex flex-column flex-lg-row align-items-lg-start justify-content-between gap-3 pb-3 mb-3 border-bottom">
                        <div>
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                <span class="dc-pill {{ $event->publication_pill_class }}">
                                    <i class="bi {{ $event->isPublished() ? 'bi-broadcast' : 'bi-file-earmark-lock2' }}"></i>
                                    {{ $event->publication_label }}
                                </span>
                                <span class="dc-pill dc-pill-neutral">
                                    <i class="bi bi-people-fill text-danger"></i>
                                    {{ $event->partner_institution }}
                                </span>
                                <span class="dc-pill dc-pill-neutral dc-tabular">
                                    <i class="bi bi-clock"></i>
                                    {{ $event->operating_hours_label }}
                                </span>
                                <span class="dc-caption dc-tabular fw-semibold">{{ $event->schedule_code }}</span>
                            </div>

                            <h2 class="dc-title-3 mb-1">
                                <a href="{{ route('schedules.show', $event) }}" class="text-decoration-none text-dark">
                                    {{ $event->title }}
                                </a>
                            </h2>

                            <div class="dc-caption text-dark fw-medium mb-1">
                                <i class="bi bi-calendar-event text-danger me-1"></i>
                                {{ $event->service_date->translatedFormat('l, d F Y') }}
                                &middot;
                                <i class="bi bi-geo-alt-fill text-danger ms-1 me-1"></i>
                                {{ $event->venue_name }}
                                @if ($event->region)
                                    ({{ $event->region->name }})
                                @endif
                            </div>

                            <div class="dc-caption">
                                {{ $event->venue_address }}
                                @if ($event->latitude !== null && $event->longitude !== null)
                                    <span class="dc-tabular text-secondary ms-1">
                                        [{{ number_format($event->latitude, 5) }}, {{ number_format($event->longitude, 5) }}]
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="d-flex flex-column align-items-lg-end gap-2 flex-shrink-0">
                            <div class="d-flex flex-wrap align-items-center gap-3">
                                <div class="text-lg-end">
                                    <div class="fw-bold dc-tabular text-danger">
                                        Target {{ $event->target_bags }} Kantong
                                    </div>
                                    <div class="dc-caption dc-tabular">
                                        Pendaftar {{ $event->total_booked }} / {{ $event->total_quota }} kursi ({{ $event->sessions->count() }} sesi)
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex flex-wrap gap-2 mt-1">
                                <form method="POST" action="{{ route('schedules.publication.update', $event) }}" class="d-inline">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="publication_status" value="{{ $event->isPublished() ? 'draft' : 'published' }}">
                                    @if ($event->isPublished())
                                        <button type="submit" class="btn btn-dc-secondary btn-sm" @disabled($event->total_booked > 0) title="{{ $event->total_booked > 0 ? 'Sudah memiliki pendaftar aktif' : 'Tarik kembali ke status draf internal' }}">
                                            <i class="bi bi-eye-slash"></i>
                                            Jadikan Draf
                                        </button>
                                    @else
                                        <button type="submit" class="btn btn-dc-soft btn-sm">
                                            <i class="bi bi-broadcast"></i>
                                            Publikasikan Acara
                                        </button>
                                    @endif
                                </form>

                                <a href="{{ route('schedules.mobile.edit', $event) }}" class="btn btn-dc-secondary btn-sm">
                                    <i class="bi bi-pencil-square"></i>
                                    Edit Agenda
                                </a>

                                <a href="{{ route('schedules.show', $event) }}" class="btn btn-dc-primary btn-sm">
                                    <i class="bi bi-sliders"></i>
                                    Detail &amp; Sesi
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Per-Session Chips -->
                    <div class="row g-2">
                        @foreach ($event->sessions as $session)
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
                        <i class="bi bi-truck-front fs-4"></i>
                    </div>
                    <h2 class="dc-title-3 mb-1">Belum Ada Agenda Kegiatan Mobile Unit</h2>
                    <p class="dc-caption mb-3 mx-auto" style="max-width: 30rem;">
                        Rencanakan kegiatan donor darah keliling bersama kampus, kantor pemerintah, atau perusahaan mitra lengkap dengan titik koordinat peta lokasi dan target kantong darah.
                    </p>
                    <a href="{{ route('schedules.mobile.create') }}" class="btn btn-dc-primary btn-sm">
                        <i class="bi bi-plus-circle"></i>
                        Rencanakan Event Mobile Unit Pertama
                    </a>
                </div>
            @endforelse
        </div>

        @if ($events->hasPages())
            <div class="mt-4">
                {{ $events->links() }}
            </div>
        @endif
    </div>
</section>
@endsection
