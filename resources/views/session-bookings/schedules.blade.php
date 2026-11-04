@extends('layouts.app')

@section('title', 'Jadwal Donor Darah Gedung UDD & Mobile Unit')

@section('content')
<section class="py-4 py-lg-5">
    <div class="container">
        <!-- Header & My Bookings Shortcut -->
        <div class="d-flex flex-column flex-lg-row align-items-lg-end justify-content-between gap-3 mb-4">
            <div>
                <div class="dc-eyebrow mb-1">Reservasi Waktu Pelayanan</div>
                <h1 class="dc-title-1 mb-1">Jadwal Donor Darah Tersedia</h1>
                <p class="dc-caption mb-0">
                    Pilih lokasi Gedung UDD PMI atau kegiatan Mobile Unit terdekat untuk mendapatkan kepastian jam pelayanan tanpa antre panjang.
                </p>
            </div>

            @auth
                @if (auth()->user()->isDonor())
                    <a href="{{ route('bookings.index') }}" class="btn btn-dc-secondary">
                        <i class="bi bi-ticket-detailed"></i>
                        Reservasi Saya
                    </a>
                @endif
            @endauth
        </div>

        @if ($activeBooking)
            <div class="dc-card p-3 px-4 mb-4 border-success border-opacity-50 bg-success-subtle bg-opacity-25">
                <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
                    <div class="d-flex align-items-center gap-3">
                        <i class="bi bi-calendar2-check-fill text-success fs-4"></i>
                        <div>
                            <div class="dc-eyebrow text-success-emphasis">Reservasi Jadwal Aktif Anda</div>
                            <div class="small fw-semibold text-dark">
                                <span class="dc-tabular">{{ $activeBooking->booking_code }}</span> &middot;
                                {{ $activeBooking->schedule?->venue_name }} ({{ $activeBooking->schedule?->service_date?->translatedFormat('d M Y') }}, {{ $activeBooking->session?->time_range_label }})
                            </div>
                        </div>
                    </div>
                    <a href="{{ route('bookings.show', $activeBooking) }}" class="btn btn-dc-primary btn-sm align-self-start align-self-md-center">
                        Lihat Bukti Reservasi
                    </a>
                </div>
            </div>
        @endif

        <!-- Translucent Filter Bar -->
        <div class="dc-glass-bar p-3 mb-4">
            <div class="d-flex flex-column flex-xl-row align-items-xl-center justify-content-between gap-3">
                <div class="d-flex flex-wrap align-items-center gap-1">
                    <a
                        href="{{ route('bookings.schedules', array_filter(['region_id' => $regionFilter ?: null, 'date' => $dateFilter ?: null])) }}"
                        class="dc-nav-pill {{ $typeFilter === '' ? 'active' : '' }}"
                    >
                        Semua Lokasi
                    </a>
                    @foreach ($scheduleTypes as $st)
                        <a
                            href="{{ route('bookings.schedules', array_filter(['type' => $st->value, 'region_id' => $regionFilter ?: null, 'date' => $dateFilter ?: null])) }}"
                            class="dc-nav-pill {{ $typeFilter === $st->value ? 'active' : '' }} d-inline-flex align-items-center gap-2"
                        >
                            <i class="bi {{ $st->icon() }}"></i>
                            <span>{{ $st->label() }}</span>
                        </a>
                    @endforeach
                </div>

                <form method="GET" action="{{ route('bookings.schedules') }}" class="row g-2 align-items-center justify-content-xl-end">
                    @if ($typeFilter !== '')
                        <input type="hidden" name="type" value="{{ $typeFilter }}">
                    @endif

                    <div class="col-12 col-sm-5 col-xl-auto">
                        <select name="region_id" class="form-select form-select-sm" aria-label="Pilih wilayah">
                            <option value="">Semua Kabupaten / Kota</option>
                            @foreach ($regions as $region)
                                <option value="{{ $region->id }}" @selected($regionFilter === $region->id)>
                                    {{ $region->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-8 col-sm-4 col-xl-auto">
                        <input
                            type="date"
                            name="date"
                            min="{{ today()->toDateString() }}"
                            value="{{ $dateFilter }}"
                            class="form-control form-control-sm dc-tabular"
                            aria-label="Filter tanggal jadwal"
                        >
                    </div>

                    <div class="col-4 col-sm-3 col-xl-auto d-flex gap-2">
                        <button type="submit" class="btn btn-dc-primary btn-sm flex-grow-1">Cari</button>
                        @if ($regionFilter > 0 || $dateFilter !== '' || $typeFilter !== '')
                            <a href="{{ route('bookings.schedules') }}" class="btn btn-dc-secondary btn-sm" title="Reset Filter">
                                <i class="bi bi-x-lg"></i>
                            </a>
                        @endif
                    </div>
                </form>
            </div>
        </div>

        <!-- Schedule Cards Grid -->
        <div class="row g-4">
            @forelse ($schedules as $schedule)
                <div class="col-md-6 col-xl-4">
                    <article class="dc-card p-4 h-100 d-flex flex-column justify-content-between">
                        <div>
                            <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
                                <span class="dc-pill {{ $schedule->schedule_type->pillClass() }}">
                                    <i class="bi {{ $schedule->schedule_type->icon() }}"></i>
                                    {{ $schedule->schedule_type->label() }}
                                </span>
                                <span class="dc-pill {{ $schedule->remaining_quota > 0 ? 'dc-pill-success' : 'dc-pill-neutral' }} dc-tabular">
                                    Sisa {{ $schedule->remaining_quota }} Slot
                                </span>
                            </div>

                            <div class="dc-eyebrow dc-tabular">
                                {{ $schedule->service_date->translatedFormat('l, d F Y') }}
                            </div>
                            <h2 class="dc-title-3 mt-1 mb-1">
                                <a href="{{ route('bookings.schedules.show', $schedule) }}" class="text-decoration-none text-dark">
                                    {{ $schedule->venue_name }}
                                </a>
                            </h2>
                            <p class="dc-caption mb-3">
                                <i class="bi bi-geo-alt text-danger me-1"></i>
                                {{ \Illuminate\Support\Str::limit($schedule->venue_address, 82) }}
                            </p>

                            <div class="vstack gap-2 mb-3">
                                @foreach ($schedule->sessions as $session)
                                    <div class="p-2 px-3 rounded-3 bg-light border d-flex align-items-center justify-content-between small">
                                        <div>
                                            <div class="fw-semibold text-dark">{{ $session->session_label }}</div>
                                            <div class="dc-caption dc-tabular">{{ $session->time_range_label }}</div>
                                        </div>
                                        @if ($session->isBookable())
                                            <span class="badge rounded-pill text-bg-success bg-opacity-75 dc-tabular">
                                                {{ $session->remaining_quota }} kursi
                                            </span>
                                        @else
                                            <span class="badge rounded-pill text-bg-secondary dc-tabular">Penuh / Tutup</span>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="pt-3 border-top d-flex align-items-center justify-content-between gap-2">
                            <span class="dc-caption dc-tabular">
                                <i class="bi bi-clock me-1"></i>{{ $schedule->operating_hours_label }}
                            </span>
                            <a href="{{ route('bookings.schedules.show', $schedule) }}" class="btn btn-dc-primary btn-sm">
                                Pilih Sesi
                                <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
                    </article>
                </div>
            @empty
                <div class="col-12">
                    <div class="dc-card p-5 text-center">
                        <div class="mx-auto mb-3 d-inline-flex align-items-center justify-content-center rounded-circle bg-light text-secondary" style="width: 3.5rem; height: 3.5rem;">
                            <i class="bi bi-calendar2-x fs-4"></i>
                        </div>
                        <h2 class="dc-title-3 mb-1">Tidak Ada Jadwal Donor yang Sesuai</h2>
                        <p class="dc-caption mb-3 mx-auto" style="max-width: 28rem;">
                            Coba pilih tanggal atau wilayah kabupaten/kota lain untuk melihat jadwal pelayanan gedung UDD dan Mobile Unit yang tersedia.
                        </p>
                        <a href="{{ route('bookings.schedules') }}" class="btn btn-dc-secondary btn-sm">
                            Tampilkan Semua Jadwal Mendatang
                        </a>
                    </div>
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
