@extends('layouts.app')

@section('title', 'Bukti Komitmen Donor ' . $commitment->pledge_code)

@section('content')
<section class="py-4 py-lg-5">
    <div class="container" style="max-width: 64rem;">
        <!-- Top Wayfinding -->
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
            <div>
                <nav aria-label="breadcrumb" class="mb-1">
                    <ol class="breadcrumb mb-0 dc-caption">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-secondary">Ruang Kerja</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('commitments.index') }}" class="text-decoration-none text-secondary">Komitmen Donor</a></li>
                        <li class="breadcrumb-item active dc-tabular" aria-current="page">{{ $commitment->pledge_code }}</li>
                    </ol>
                </nav>

                <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
                    <span class="dc-pill {{ $commitment->status->pillClass() }}">
                        <i class="bi {{ $commitment->status->icon() }}"></i>
                        {{ $commitment->status->label() }}
                    </span>
                    <span class="dc-caption dc-tabular">Kode Komitmen: <strong>{{ $commitment->pledge_code }}</strong></span>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2">
                @if ($isOwnerSeeker && Route::has('emergency-requests.show') && $bloodRequest)
                    <a href="{{ route('emergency-requests.show', $bloodRequest) }}" class="btn btn-dc-secondary">
                        <i class="bi bi-arrow-left"></i>
                        Ke Detail Permohonan
                    </a>
                @else
                    <a href="{{ route('commitments.index') }}" class="btn btn-dc-secondary">
                        <i class="bi bi-arrow-left"></i>
                        Daftar Komitmen
                    </a>
                @endif
            </div>
        </div>

        <div class="row g-4 align-items-start">
            <!-- Left: Commitment Pass & Arrival ETA Details -->
            <div class="col-lg-7">
                <div class="dc-card p-4 p-md-5 mb-4">
                    <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 pb-4 mb-4 border-bottom">
                        <div class="d-flex align-items-center gap-3">
                            <span class="dc-blood-badge" style="width: 3.6rem; height: 3.6rem; font-size: 1.25rem;">
                                {{ $bloodRequest?->blood_group_label }}
                            </span>
                            <div>
                                <div class="dc-eyebrow">Pledge Relawan Darurat</div>
                                <h1 class="dc-title-2 mb-0 mt-1">
                                    {{ $isOwnerDonor ? $commitment->donor?->name : $commitment->masked_donor_alias }}
                                </h1>
                                <div class="dc-caption">
                                    Golongan Pendonor: <strong>{{ $commitment->donor?->blood_group_label ?? '-' }}</strong>
                                    &middot; Komitmen <strong>{{ $commitment->bags_pledged }} Kantong {{ $bloodRequest?->component_type?->value }}</strong>
                                </div>
                            </div>
                        </div>

                        <div class="text-sm-end">
                            <div class="dc-caption">Token Verifikasi Loket</div>
                            <div class="dc-title-2 text-danger dc-tabular mb-0" style="letter-spacing: 0.08em;">
                                {{ $commitment->coordination_code }}
                            </div>
                        </div>
                    </div>

                    <!-- ETA Highlight Box -->
                    <div class="p-3 px-4 rounded-4 bg-danger-subtle bg-opacity-50 border border-danger-subtle mb-4">
                        <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2">
                            <div>
                                <div class="dc-eyebrow">Estimasi Waktu Tiba di Faskes (ETA)</div>
                                <div class="dc-title-3 dc-tabular text-dark mt-1 mb-0">
                                    {{ $commitment->estimated_arrival_at->translatedFormat('l, d M Y — H:i') }} WIB
                                </div>
                            </div>
                            <span class="dc-pill dc-pill-critical dc-tabular align-self-start align-self-sm-center">
                                <i class="bi bi-stopwatch-fill"></i>
                                {{ $commitment->estimated_arrival_at->diffForHumans() }}
                            </span>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-sm-6">
                            <div class="dc-caption">Rumah Sakit / Faskes Tujuan</div>
                            <div class="fw-semibold mt-1">{{ $bloodRequest?->facility?->name }}</div>
                            <div class="dc-caption">{{ $bloodRequest?->facility?->address }}</div>
                        </div>

                        <div class="col-sm-6">
                            <div class="dc-caption">Titik Layanan BDRS / Donor</div>
                            <div class="fw-semibold mt-1">{{ $commitment->meeting_point ?: 'Instalasi Bank Darah Rumah Sakit (BDRS)' }}</div>
                            <div class="dc-caption dc-tabular">Rujukan Kasus: {{ $bloodRequest?->request_code }}</div>
                        </div>

                        @if ($commitment->pledge_notes)
                            <div class="col-12">
                                <div class="p-3 rounded-3 bg-light border">
                                    <div class="dc-caption fw-semibold text-dark mb-1">Catatan Kedatangan Pendonor</div>
                                    <p class="mb-0 small text-secondary">{{ $commitment->pledge_notes }}</p>
                                </div>
                            </div>
                        @endif

                        @if ($commitment->cancellation_reason)
                            <div class="col-12">
                                <div class="p-3 rounded-3 bg-danger-subtle border border-danger-subtle">
                                    <div class="dc-caption fw-semibold text-danger-emphasis mb-1">Alasan Pembatalan Darurat</div>
                                    <p class="mb-0 small text-dark">{{ $commitment->cancellation_reason }}</p>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Right: Lifecycle & Coordination Hooks -->
            <div class="col-lg-5">
                <div class="dc-card p-4 mb-4">
                    <div class="dc-eyebrow mb-2">Status Respons Kasus {{ $bloodRequest?->request_code }}</div>
                    <div class="d-flex align-items-baseline justify-content-between mb-2">
                        <span class="dc-title-2 dc-tabular mb-0">{{ $activePledgesCount }} Relawan Siaga</span>
                        <span class="dc-caption dc-tabular">Kebutuhan: {{ $bloodRequest?->remaining_bags }} Kantong</span>
                    </div>
                    <p class="dc-caption mb-3">
                        Sebutkan kode kasus <strong class="dc-tabular text-dark">{{ $bloodRequest?->request_code }}</strong> dan token <strong class="dc-tabular text-dark">{{ $commitment->coordination_code }}</strong> saat tiba di instalasi Bank Darah faskes tujuan.
                    </p>

                    <!-- Forward-Compatible Hooks for PBI-13 (Arrival/Cancel) & PBI-14 (Structured Coordination) -->
                    <div class="d-grid gap-2">
                        @if ($isOwnerDonor && $commitment->isActiveCommitment() && Route::has('commitments.arrive'))
                            <form method="POST" action="{{ route('commitments.arrive', $commitment) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn btn-dc-primary w-100">
                                    <i class="bi bi-geo-alt-fill"></i>
                                    Konfirmasi Saya Sudah Tiba di Faskes
                                </button>
                            </form>
                        @endif

                        @if (($isOwnerDonor || $isOwnerSeeker) && Route::has('coordinations.show'))
                            <a href="{{ route('coordinations.show', $commitment) }}" class="btn btn-dc-secondary">
                                <i class="bi bi-chat-square-text"></i>
                                Buka Ruang Koordinasi Titik Temu
                            </a>
                        @endif

                        @if ($isOwnerDonor && $commitment->isActiveCommitment() && Route::has('commitments.cancel'))
                            <button type="button" class="btn btn-dc-soft" data-dc-open-sheet="cancelCommitmentSheet">
                                <i class="bi bi-slash-circle"></i>
                                Batalkan Komitmen Darurat
                            </button>
                        @endif
                    </div>

                    <div class="p-3 rounded-3 bg-light border mt-3 d-flex align-items-start gap-2 dc-caption">
                        <i class="bi bi-shield-lock-fill text-success mt-1"></i>
                        <span>Nomor ponsel pribadi pendonor dan keluarga pasien tidak ditampilkan ke publik demi keamanan privasi bersama.</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
