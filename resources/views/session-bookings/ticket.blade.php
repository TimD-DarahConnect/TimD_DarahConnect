@extends('layouts.app')

@section('title', 'Tiket QR Digital ' . $booking->booking_code)

@push('styles')
<style>
    .dc-wallet-pass {
        max-width: 34rem;
        margin: 0 auto;
        border-radius: 1.75rem;
        overflow: hidden;
        background: #ffffff;
        border: 1px solid rgba(15, 23, 42, 0.1);
        box-shadow: 0 24px 56px -14px rgba(15, 23, 42, 0.16), 0 8px 20px -6px rgba(220, 38, 38, 0.08);
    }
    .dc-wallet-header {
        background: linear-gradient(135deg, #dc2626 0%, #b91c1c 65%, #991b1b 100%);
        color: #ffffff;
        padding: 1.5rem 1.75rem;
    }
    .dc-wallet-divider {
        position: relative;
        border-top: 2px dashed rgba(15, 23, 42, 0.12);
        margin: 0 1.5rem;
    }
    .dc-wallet-divider::before,
    .dc-wallet-divider::after {
        content: '';
        position: absolute;
        top: -0.85rem;
        width: 1.7rem;
        height: 1.7rem;
        border-radius: 999px;
        background-color: var(--dc-canvas);
        border: 1px solid rgba(15, 23, 42, 0.09);
    }
    .dc-wallet-divider::before {
        left: -2.4rem;
    }
    .dc-wallet-divider::after {
        right: -2.4rem;
    }
    .dc-qr-stage {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 1rem;
        border-radius: 1.25rem;
        background: #ffffff;
        border: 1.5px solid rgba(15, 23, 42, 0.1);
        box-shadow: 0 6px 20px -6px rgba(15, 23, 42, 0.08);
    }
    .dc-qr-stage svg {
        display: block;
        width: 13.5rem;
        height: 13.5rem;
    }
    @media print {
        .dc-navbar,
        footer,
        .dc-no-print {
            display: none !important;
        }
        body {
            background: #ffffff !important;
        }
        .dc-wallet-pass {
            box-shadow: none !important;
            border: 1.5px solid #0f172a !important;
        }
        .dc-wallet-divider::before,
        .dc-wallet-divider::after {
            display: none !important;
        }
    }
</style>
@endpush

@section('content')
<section class="py-4 py-lg-5">
    <div class="container" style="max-width: 56rem;">
        <!-- Top Navigation (Hidden on Print) -->
        <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mb-4 dc-no-print">
            <div>
                <nav aria-label="breadcrumb" class="mb-1">
                    <ol class="breadcrumb mb-0 dc-caption">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-secondary">Ruang Kerja</a></li>
                        @if ($isOwnerDonor)
                            <li class="breadcrumb-item"><a href="{{ route('bookings.index') }}" class="text-decoration-none text-secondary">Reservasi Saya</a></li>
                        @endif
                        <li class="breadcrumb-item"><a href="{{ route('bookings.show', $booking) }}" class="text-decoration-none text-secondary dc-tabular">{{ $booking->booking_code }}</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Tiket QR Digital</li>
                    </ol>
                </nav>
                <h1 class="dc-title-2 mb-0">Tiket Booking Digital Fast-Track UDD</h1>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <button type="button" onclick="window.print()" class="btn btn-dc-secondary btn-sm">
                    <i class="bi bi-printer"></i>
                    Cetak / Simpan PDF
                </button>
                <a href="{{ route('bookings.show', $booking) }}" class="btn btn-dc-secondary btn-sm">
                    <i class="bi bi-arrow-left"></i>
                    Detail Reservasi
                </a>
            </div>
        </div>

        @if ($errors->any())
            <div class="dc-card p-3 mb-4 border-danger-subtle bg-danger-subtle text-danger-emphasis dc-no-print" role="alert">
                <div class="fw-semibold small mb-1">
                    <i class="bi bi-exclamation-circle-fill me-1"></i>Terdapat kendala pada permintaan Anda:
                </div>
                <ul class="mb-0 ps-3 small">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Apple Wallet-Style Digital Donor Pass -->
        <article class="dc-wallet-pass" aria-label="Tiket Booking Digital DarahConnect">
            <div class="dc-wallet-header">
                <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
                    <div>
                        <div class="text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 0.09em; opacity: 0.88;">
                            DarahConnect &middot; Tiket Layanan Donor
                        </div>
                        <div class="fw-bold fs-5 mt-1">
                            {{ $booking->schedule?->isMobileUnit() ? 'Event Mobile Unit PMI' : 'Pelayanan Gedung UDD PMI' }}
                        </div>
                    </div>

                    <div class="rounded-4 bg-white text-danger fw-bold dc-tabular d-inline-flex align-items-center justify-content-center px-3 py-2 shadow-sm" style="font-size: 1.15rem; min-width: 3.3rem;">
                        {{ $booking->donor?->blood_group_label ?: 'WB' }}
                    </div>
                </div>

                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 pt-2 border-top border-white border-opacity-25 small">
                    <span>
                        <i class="bi bi-person-vcard me-1"></i>
                        <strong>{{ $booking->donor?->name }}</strong>
                    </span>
                    <span class="badge rounded-pill bg-white text-danger fw-semibold">
                        <i class="bi bi-shield-fill-check me-1"></i>20/20 Anamnesis Lolos
                    </span>
                </div>
            </div>

            <!-- Pass Clinical & Schedule Metadata -->
            <div class="p-4">
                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <div class="dc-caption">Tanggal Layanan</div>
                        <div class="fw-bold text-dark dc-tabular mt-1">
                            {{ $booking->schedule?->service_date?->translatedFormat('d M Y') }}
                        </div>
                        <div class="dc-caption">{{ $booking->schedule?->service_date?->translatedFormat('l') }}</div>
                    </div>

                    <div class="col-6 text-end">
                        <div class="dc-caption">Jam Sesi Kedatangan</div>
                        <div class="fw-bold text-danger dc-tabular mt-1">
                            {{ $booking->session?->time_range_label }}
                        </div>
                        <div class="dc-caption">{{ $booking->session?->session_label }}</div>
                    </div>

                    <div class="col-12">
                        <div class="p-3 rounded-3 bg-light border">
                            <div class="dc-caption">Lokasi Registrasi Loket</div>
                            <div class="fw-bold text-dark mt-1">{{ $booking->schedule?->venue_name }}</div>
                            <div class="dc-caption mt-1">{{ $booking->schedule?->venue_address }}</div>
                        </div>
                    </div>

                    <div class="col-6">
                        <div class="dc-caption">Jenis Donasi</div>
                        <div class="fw-semibold text-dark small mt-1">
                            {{ $booking->donation_type === 'APHERESIS' ? 'Apheresis' : 'Whole Blood (WB)' }}
                        </div>
                    </div>

                    <div class="col-6 text-end">
                        <div class="dc-caption">Status Tiket</div>
                        <div class="mt-1">
                            <span class="dc-pill {{ $booking->status->pillClass() }}">
                                <i class="bi {{ $booking->status->icon() }}"></i>
                                {{ $booking->status->label() }}
                            </span>
                        </div>
                    </div>
                </div>

                @if ($booking->reschedule_count > 0)
                    <div class="p-2 px-3 rounded-3 bg-info-subtle text-info-emphasis small dc-tabular">
                        <i class="bi bi-arrow-repeat me-1"></i>
                        Tiket telah dijadwalkan ulang ({{ $booking->reschedule_count }}/{{ \App\Models\DonorBooking::MAX_RESCHEDULE_COUNT }})
                        @if ($booking->previousSession)
                            dari {{ $booking->previousSession->session_label }}
                        @endif
                    </div>
                @endif
            </div>

            <!-- Ticket Tear Perforation -->
            <div class="dc-wallet-divider" aria-hidden="true"></div>

            <!-- QR Code Fast-Track Check-In Stage -->
            <div class="p-4 text-center">
                <div class="dc-eyebrow mb-2">Pindai di Loket Registrasi UDD</div>

                <div class="dc-qr-stage mb-3" role="img" aria-label="QR Code Tiket Reservasi {{ $booking->booking_code }}">
                    {!! $qrSvg !!}
                </div>

                <div class="dc-title-3 dc-tabular text-dark mb-1" style="letter-spacing: 0.04em;">
                    {{ $booking->booking_code }}
                </div>
                <div class="dc-caption dc-tabular mb-3">
                    Kode Keamanan QR: <strong>#{{ $booking->short_verification_hash }}</strong>
                    @if ($booking->confirmed_at)
                        &middot; Terbit {{ $booking->confirmed_at->translatedFormat('d M Y, H:i') }} WIB
                    @endif
                </div>

                <div class="p-3 rounded-3 bg-light border text-start dc-caption">
                    <i class="bi bi-check2-circle text-success me-1"></i>
                    Hadir <strong>15 menit sebelum sesi dimulai</strong> dengan membawa KTP/Kartu Donor serta tunjukkan QR Code ini kepada petugas loket UDD untuk <em>fast-track check-in</em> pemeriksaan fisik pra-donor.
                </div>
            </div>
        </article>

        <!-- Action Controls Below Pass (Hidden on Print) -->
        @if ($isOwnerDonor && $booking->isActiveReservation())
            <div class="d-flex flex-wrap align-items-center justify-content-center gap-2 mt-4 dc-no-print">
                @if ($booking->canBeRescheduled())
                    <button type="button" class="btn btn-dc-secondary" data-dc-open-sheet="rescheduleBookingSheet">
                        <i class="bi bi-arrow-repeat"></i>
                        Jadwalkan Ulang Sesi
                    </button>
                @else
                    <span class="dc-pill dc-pill-neutral">
                        Batas penjadwalan ulang ({{ \App\Models\DonorBooking::MAX_RESCHEDULE_COUNT }}x) telah digunakan
                    </span>
                @endif

                @if ($booking->canBeCancelled())
                    <button type="button" class="btn btn-dc-soft" data-dc-open-sheet="cancelBookingSheet">
                        <i class="bi bi-slash-circle"></i>
                        Batalkan Tiket Reservasi
                    </button>
                @endif
            </div>
        @endif
    </div>
</section>

@include('session-bookings.partials.booking-management-sheets', [
    'booking' => $booking,
    'isOwnerDonor' => $isOwnerDonor,
    'rescheduleSchedules' => $rescheduleSchedules ?? collect(),
])
@endsection
