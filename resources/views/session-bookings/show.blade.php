@extends('layouts.app')

@section('title', 'Bukti Reservasi Jadwal ' . $booking->booking_code)

@section('content')
<section class="py-4 py-lg-5">
    <div class="container" style="max-width: 60rem;">
        <!-- Wayfinding -->
        <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mb-4">
            <div>
                <nav aria-label="breadcrumb" class="mb-1">
                    <ol class="breadcrumb mb-0 dc-caption">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-secondary">Ruang Kerja</a></li>
                        @if ($isOwnerDonor)
                            <li class="breadcrumb-item"><a href="{{ route('bookings.index') }}" class="text-decoration-none text-secondary">Reservasi Saya</a></li>
                        @endif
                        <li class="breadcrumb-item active dc-tabular" aria-current="page">{{ $booking->booking_code }}</li>
                    </ol>
                </nav>
                <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
                    <span class="dc-pill {{ $booking->status->pillClass() }}">
                        <i class="bi {{ $booking->status->icon() }}"></i>
                        {{ $booking->status->label() }}
                    </span>
                    <span class="dc-caption dc-tabular">Kode Booking: <strong>{{ $booking->booking_code }}</strong></span>
                </div>
            </div>

            <a href="{{ $isOwnerDonor ? route('bookings.index') : route('bookings.schedules') }}" class="btn btn-dc-secondary">
                <i class="bi bi-arrow-left"></i>
                Kembali
            </a>
        </div>

        <div class="dc-card p-4 p-md-5">
            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 pb-4 mb-4 border-bottom">
                <div class="d-flex align-items-center gap-3">
                    <span class="dc-blood-badge" style="width: 3.6rem; height: 3.6rem; font-size: 1.25rem;">
                        {{ $booking->donor?->blood_group_label ?: 'WB' }}
                    </span>
                    <div>
                        <div class="dc-eyebrow">Bukti Reservasi Sesi Donor</div>
                        <h1 class="dc-title-2 mb-0 mt-1">{{ $booking->donor?->name }}</h1>
                        <div class="dc-caption">
                            Layanan: <strong>{{ $booking->donation_type === 'APHERESIS' ? 'Apheresis' : 'Whole Blood (WB)' }}</strong>
                        </div>
                    </div>
                </div>

                <div class="text-md-end">
                    <div class="dc-caption">Nomor Referensi</div>
                    <div class="dc-title-3 text-danger dc-tabular mb-0">{{ $booking->booking_code }}</div>
                </div>
            </div>

            @if ($errors->any())
                <div class="p-3 mb-4 rounded-3 border border-danger-subtle bg-danger-subtle text-danger-emphasis small" role="alert">
                    <div class="fw-semibold mb-1">
                        <i class="bi bi-exclamation-circle-fill me-1"></i>Terdapat kendala pada perubahan reservasi:
                    </div>
                    <ul class="mb-0 ps-3">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- Highlighted Date & Session Window -->
            <div class="p-3 px-4 rounded-4 bg-light border mb-4">
                <div class="row g-3 align-items-center">
                    <div class="col-md-6">
                        <div class="dc-caption">Tanggal Kedatangan</div>
                        <div class="fw-bold text-dark dc-tabular mt-1">
                            <i class="bi bi-calendar-event text-danger me-1"></i>
                            {{ $booking->schedule?->service_date?->translatedFormat('l, d F Y') }}
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="dc-caption">Sesi &amp; Jam Pelayanan</div>
                        <div class="fw-bold text-dark dc-tabular mt-1">
                            <i class="bi bi-clock text-danger me-1"></i>
                            {{ $booking->session?->session_label }} ({{ $booking->session?->time_range_label }})
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-7">
                    <div class="dc-caption">Lokasi Fasilitas / Event</div>
                    <div class="fw-semibold text-dark mt-1">{{ $booking->schedule?->venue_name }}</div>
                    <div class="dc-caption">{{ $booking->schedule?->venue_address }}</div>
                </div>

                <div class="col-md-5">
                    <div class="dc-caption">Waktu Konfirmasi</div>
                    <div class="fw-semibold dc-tabular mt-1">
                        {{ $booking->confirmed_at ? $booking->confirmed_at->translatedFormat('d M Y, H:i').' WIB' : 'Menunggu Skrining Mandiri' }}
                    </div>
                </div>

                @if ($booking->donor_notes)
                    <div class="col-12">
                        <div class="p-3 rounded-3 bg-light border small">
                            <strong class="text-dark">Catatan Pendonor:</strong> {{ $booking->donor_notes }}
                        </div>
                    </div>
                @endif

                @if ($booking->reschedule_count > 0)
                    <div class="col-12">
                        <div class="p-3 rounded-3 bg-info-subtle border border-info-subtle small text-dark dc-tabular">
                            <i class="bi bi-arrow-repeat text-info-emphasis me-1"></i>
                            <strong>Riwayat Penjadwalan Ulang:</strong> Dipindahkan {{ $booking->reschedule_count }} kali
                            @if ($booking->previousSession)
                                (sebelumnya: {{ $booking->previousSession->session_label }}
                                @if ($booking->previousSession->schedule)
                                    &middot; {{ $booking->previousSession->schedule->service_date->translatedFormat('d M Y') }}
                                @endif
                                )
                            @endif
                        </div>
                    </div>
                @endif

                @if ($booking->cancellation_reason)
                    <div class="col-12">
                        <div class="p-3 rounded-3 bg-danger-subtle border border-danger-subtle small text-dark">
                            <strong>Alasan Pembatalan:</strong> {{ $booking->cancellation_reason }}
                            @if ($booking->cancelled_at)
                                <span class="dc-caption dc-tabular d-block mt-1">
                                    Dibatalkan pada {{ $booking->cancelled_at->translatedFormat('d M Y, H:i') }} WIB &middot; Kuota sesi telah dikembalikan.
                                </span>
                            @endif
                        </div>
                    </div>
                @endif
            </div>

            <!-- Anamnesis Self-Screening Clinical Status Panel -->
            @if ($booking->status->value === 'pending_screening')
                <div class="p-4 rounded-4 border border-warning bg-warning-subtle bg-opacity-25 mb-4">
                    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                        <div>
                            <div class="dc-eyebrow text-warning-emphasis">Langkah Wajib Sebelum Konfirmasi</div>
                            <h2 class="dc-title-3 mb-1 mt-1">Lengkapi Kuesioner Anamnesis (20 Pertanyaan Medis)</h2>
                            <p class="dc-caption mb-0">
                                Kuota kursi sesi baru akan dikunci atas nama Anda setelah kuesioner penapisan mandiri selesai dievaluasi dan dinyatakan memenuhi syarat awal.
                            </p>
                        </div>
                        @if ($isOwnerDonor && Route::has('bookings.screening.show'))
                            <a href="{{ route('bookings.screening.show', $booking) }}" class="btn btn-dc-primary flex-shrink-0">
                                <i class="bi bi-clipboard2-pulse"></i>
                                Mulai Skrining Mandiri
                            </a>
                        @endif
                    </div>
                </div>
            @elseif ($booking->status->value === 'deferred_screening')
                <div class="p-4 rounded-4 border border-warning bg-warning-subtle bg-opacity-25 mb-4">
                    <div class="dc-eyebrow text-warning-emphasis">Edukasi Kesehatan Pra-Donor</div>
                    <h2 class="dc-title-3 mb-1 mt-1">Penundaan Sementara Berdasarkan Penapisan Mandiri</h2>
                    <p class="dc-caption text-dark mb-3">
                        Demi keselamatan kesehatan Anda dan penerima darah, keberangkatan donor pada sesi ini ditunda sementara. <strong>Kuota sesi tidak dipotong</strong> sehingga Anda bebas memesan jadwal baru setelah kondisi pulih.
                    </p>

                    @if ($booking->screening && ! empty($booking->screening->flagged_reasons))
                        <div class="vstack gap-2 mb-3">
                            @foreach ($booking->screening->flagged_reasons as $reason)
                                <div class="p-3 rounded-3 bg-white border small">
                                    <div class="fw-semibold text-dark">
                                        <span class="badge text-bg-warning me-1 dc-tabular">{{ $reason['code'] ?? 'ANM' }}</span>
                                        {{ $reason['note'] ?? '' }}
                                    </div>
                                    @if (! empty($reason['question']))
                                        <div class="dc-caption mt-1">Pertanyaan terkait: {{ $reason['question'] }}</div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if ($isOwnerDonor)
                        <a href="{{ route('bookings.schedules') }}" class="btn btn-dc-secondary btn-sm">
                            <i class="bi bi-calendar2-week"></i>
                            Lihat Jadwal di Tanggal Lain
                        </a>
                    @endif
                </div>
            @elseif ($booking->screening_passed)
                <div class="p-3 px-4 rounded-4 border border-success-subtle bg-success-subtle bg-opacity-25 mb-4 d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2">
                    <div class="small text-dark">
                        <i class="bi bi-shield-fill-check text-success me-1"></i>
                        <strong>Lolos Penapisan Mandiri (20/20 Kriteria Anamnesis PMI)</strong>
                        @if ($booking->screening_completed_at)
                            <span class="text-secondary dc-tabular">&middot; {{ $booking->screening_completed_at->translatedFormat('d M Y, H:i') }} WIB</span>
                        @endif
                    </div>
                    <span class="dc-pill dc-pill-success">Siap Pemeriksaan Fisik Lokasi</span>
                </div>
            @endif

            <!-- Self-Screening Anamnesis, Digital QR Ticket, Reschedule, & Cancellation Controls -->
            <div class="pt-3 border-top d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div class="dc-caption">
                    <i class="bi bi-info-circle text-danger me-1"></i>
                    Harap hadir 15 menit sebelum jam sesi dimulai dengan membawa KTP atau Kartu Donor.
                </div>

                <div class="d-flex flex-wrap gap-2">
                    @if ($isOwnerDonor && $booking->status->value === 'pending_screening' && Route::has('bookings.screening.show'))
                        <a href="{{ route('bookings.screening.show', $booking) }}" class="btn btn-dc-primary btn-sm">
                            <i class="bi bi-clipboard2-pulse"></i>
                            Isi Kuesioner Anamnesis (20 Pertanyaan)
                        </a>
                    @endif

                    @if ($booking->hasDigitalTicket() && Route::has('bookings.ticket'))
                        <a href="{{ route('bookings.ticket', $booking) }}" class="btn btn-dc-primary btn-sm">
                            <i class="bi bi-qr-code"></i>
                            Tiket QR Digital
                        </a>
                    @endif

                    @if ($isOwnerDonor && $booking->canBeRescheduled() && Route::has('bookings.reschedule'))
                        <button type="button" class="btn btn-dc-secondary btn-sm" data-dc-open-sheet="rescheduleBookingSheet">
                            <i class="bi bi-arrow-repeat"></i>
                            Jadwalkan Ulang
                        </button>
                    @endif

                    @if ($isOwnerDonor && $booking->canBeCancelled() && Route::has('bookings.cancel'))
                        <button type="button" class="btn btn-dc-soft btn-sm" data-dc-open-sheet="cancelBookingSheet">
                            <i class="bi bi-slash-circle"></i>
                            Batalkan
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>

@include('session-bookings.partials.booking-management-sheets', [
    'booking' => $booking,
    'isOwnerDonor' => $isOwnerDonor,
    'rescheduleSchedules' => $rescheduleSchedules ?? collect(),
])
@endsection
