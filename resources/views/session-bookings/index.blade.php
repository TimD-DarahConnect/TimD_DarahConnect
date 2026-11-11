@extends('layouts.app')

@section('title', 'Reservasi Jadwal Donor Saya')

@section('content')
<section class="py-4 py-lg-5">
    <div class="container" style="max-width: 66rem;">
        <!-- Header -->
        <div class="d-flex flex-column flex-md-row align-items-md-end justify-content-between gap-3 mb-4">
            <div>
                <nav aria-label="breadcrumb" class="mb-1">
                    <ol class="breadcrumb mb-0 dc-caption">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-secondary">Ruang Kerja</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Reservasi Jadwal Donor</li>
                    </ol>
                </nav>
                <h1 class="dc-title-1 mb-1">Reservasi Jadwal Donor Saya</h1>
                <p class="dc-caption mb-0">Kelola waktu kunjungan terjadwal Anda di Gedung UDD PMI maupun kegiatan Mobile Unit.</p>
            </div>

            <a href="{{ route('bookings.schedules') }}" class="btn btn-dc-primary">
                <i class="bi bi-calendar-plus"></i>
                Lihat Jadwal Tersedia
            </a>
        </div>

        <div class="vstack gap-3">
            @forelse ($bookings as $item)
                <article class="dc-card p-4 {{ $item->isActiveReservation() ? 'border-danger border-opacity-25' : '' }}">
                    <div class="row align-items-center g-3">
                        <div class="col-lg-8">
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                <span class="dc-pill {{ $item->status->pillClass() }}">
                                    <i class="bi {{ $item->status->icon() }}"></i>
                                    {{ $item->status->label() }}
                                </span>
                                <span class="dc-pill dc-pill-neutral">{{ $item->donation_type === 'APHERESIS' ? 'Apheresis' : 'Whole Blood' }}</span>
                                <span class="dc-caption dc-tabular fw-semibold">{{ $item->booking_code }}</span>
                            </div>

                            <h2 class="dc-title-3 mb-1">
                                <a href="{{ route('bookings.show', $item) }}" class="text-decoration-none text-dark">
                                    {{ $item->schedule?->venue_name }}
                                </a>
                            </h2>

                            <div class="dc-caption dc-tabular mb-1">
                                <i class="bi bi-calendar-event text-danger me-1"></i>
                                <strong class="text-dark">{{ $item->schedule?->service_date?->translatedFormat('l, d F Y') }}</strong>
                                &middot; {{ $item->session?->session_label }} ({{ $item->session?->time_range_label }})
                            </div>

                            <div class="dc-caption">
                                <i class="bi bi-geo-alt me-1"></i>{{ $item->schedule?->venue_address }}
                            </div>
                        </div>

                        <div class="col-lg-4 text-lg-end d-flex flex-wrap gap-2 justify-content-lg-end">
                            @if ($item->status->value === 'pending_screening' && Route::has('bookings.screening.show'))
                                <a href="{{ route('bookings.screening.show', $item) }}" class="btn btn-dc-primary btn-sm">
                                    <i class="bi bi-clipboard2-pulse"></i>
                                    Isi Anamnesis (20 Soal)
                                </a>
                            @else
                                <a href="{{ route('bookings.show', $item) }}" class="btn btn-dc-primary btn-sm">
                                    <i class="bi bi-ticket-perforated"></i>
                                    Buka Detail Reservasi
                                </a>
                            @endif
                        </div>
                    </div>
                </article>
            @empty
                <div class="dc-card p-5 text-center">
                    <div class="mx-auto mb-3 d-inline-flex align-items-center justify-content-center rounded-circle bg-light text-secondary" style="width: 3.5rem; height: 3.5rem;">
                        <i class="bi bi-calendar2-heart fs-4"></i>
                    </div>
                    <h2 class="dc-title-3 mb-1">Belum Ada Reservasi Sesi Donor</h2>
                    <p class="dc-caption mb-3 mx-auto" style="max-width: 28rem;">
                        Pesan slot sesi waktu di Gedung UDD PMI atau Mobile Unit terdekat agar kedatangan donor Anda terjadwal dengan nyaman.
                    </p>
                    <a href="{{ route('bookings.schedules') }}" class="btn btn-dc-primary btn-sm">
                        Pilih Jadwal Donor Sekarang
                    </a>
                </div>
            @endforelse
        </div>

        @if ($bookings->hasPages())
            <div class="mt-4">
                {{ $bookings->links() }}
            </div>
        @endif
    </div>
</section>
@endsection
