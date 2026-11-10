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

        @if ($errors->any())
            <div class="dc-card p-3 mb-4 border-danger-subtle bg-danger-subtle text-danger-emphasis" role="alert">
                <div class="fw-semibold small mb-1">
                    <i class="bi bi-exclamation-circle-fill me-1"></i>Terdapat kendala pada data yang dikirim:
                </div>
                <ul class="mb-0 ps-3 small">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

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

                    <!-- Status-Aware Arrival / ETA Highlight Banner -->
                    @if ($commitment->status === \App\Enums\DonorCommitmentStatus::ARRIVED && $commitment->arrived_at)
                        <div class="p-3 px-4 rounded-4 bg-success-subtle border border-success-subtle mb-4">
                            <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2">
                                <div>
                                    <div class="dc-eyebrow text-success-emphasis">Kehadiran Relawan Terkonfirmasi</div>
                                    <div class="dc-title-3 dc-tabular text-dark mt-1 mb-0">
                                        Tiba pada {{ $commitment->arrived_at->translatedFormat('l, d M Y — H:i') }} WIB
                                    </div>
                                    <div class="dc-caption text-dark mt-1">
                                        Relawan berada di lokasi faskes dan sedang menjalani antrean pemeriksaan pra-donor / BDRS.
                                    </div>
                                </div>
                                <span class="dc-pill dc-pill-success dc-tabular align-self-start align-self-sm-center">
                                    <i class="bi bi-check2-circle"></i>
                                    Hadir di Lokasi
                                </span>
                            </div>
                        </div>
                    @elseif ($commitment->status === \App\Enums\DonorCommitmentStatus::CANCELLED)
                        <div class="p-3 px-4 rounded-4 bg-danger-subtle bg-opacity-50 border border-danger-subtle mb-4">
                            <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2">
                                <div>
                                    <div class="dc-eyebrow text-danger-emphasis">Status Pembatalan Komitmen</div>
                                    <div class="dc-title-3 dc-tabular text-dark mt-1 mb-0">
                                        @if ($commitment->cancelled_at)
                                            Dibatalkan {{ $commitment->cancelled_at->translatedFormat('d M Y, H:i') }} WIB
                                        @else
                                            Komitmen Dibatalkan
                                        @endif
                                    </div>
                                </div>
                                <span class="dc-pill dc-pill-critical align-self-start align-self-sm-center">
                                    <i class="bi bi-slash-circle"></i>
                                    Slot Dibuka Kembali
                                </span>
                            </div>
                        </div>
                    @else
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
                    @endif

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

                <!-- Commitment Lifecycle Timeline -->
                <div class="dc-card p-4">
                    <div class="dc-eyebrow mb-1">Jejak Waktu Komitmen</div>
                    <h2 class="dc-title-3 mb-3">Kronologi Kehadiran Relawan</h2>

                    <div class="vstack gap-3 small">
                        <div class="d-flex align-items-start gap-3">
                            <span class="rounded-circle bg-success-subtle text-success d-inline-flex align-items-center justify-content-center flex-shrink-0" style="width: 2rem; height: 2rem;">
                                <i class="bi bi-check2"></i>
                            </span>
                            <div>
                                <div class="fw-semibold text-dark">Komitmen Kesediaan Donor Tercatat</div>
                                <div class="dc-caption dc-tabular">
                                    {{ $commitment->created_at?->translatedFormat('d M Y, H:i') }} WIB &middot; ETA {{ $commitment->estimated_arrival_at->translatedFormat('d M Y, H:i') }} WIB
                                </div>
                            </div>
                        </div>

                        @if ($commitment->arrived_at)
                            <div class="d-flex align-items-start gap-3">
                                <span class="rounded-circle bg-success-subtle text-success d-inline-flex align-items-center justify-content-center flex-shrink-0" style="width: 2rem; height: 2rem;">
                                    <i class="bi bi-geo-alt-fill"></i>
                                </span>
                                <div>
                                    <div class="fw-semibold text-dark">Tiba di Lokasi Faskes / BDRS</div>
                                    <div class="dc-caption dc-tabular">
                                        {{ $commitment->arrived_at->translatedFormat('d M Y, H:i') }} WIB
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if ($commitment->completed_at)
                            <div class="d-flex align-items-start gap-3">
                                <span class="rounded-circle bg-success-subtle text-success d-inline-flex align-items-center justify-content-center flex-shrink-0" style="width: 2rem; height: 2rem;">
                                    <i class="bi bi-heart-pulse-fill"></i>
                                </span>
                                <div>
                                    <div class="fw-semibold text-dark">Pengambilan Darah Selesai</div>
                                    <div class="dc-caption dc-tabular">
                                        {{ $commitment->completed_at->translatedFormat('d M Y, H:i') }} WIB
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if ($commitment->cancelled_at)
                            <div class="d-flex align-items-start gap-3">
                                <span class="rounded-circle bg-danger-subtle text-danger d-inline-flex align-items-center justify-content-center flex-shrink-0" style="width: 2rem; height: 2rem;">
                                    <i class="bi bi-slash-circle"></i>
                                </span>
                                <div>
                                    <div class="fw-semibold text-danger-emphasis">Komitmen Dibatalkan Darurat</div>
                                    <div class="dc-caption dc-tabular">
                                        {{ $commitment->cancelled_at->translatedFormat('d M Y, H:i') }} WIB
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Right: Lifecycle Controls & Coordination Hooks -->
            <div class="col-lg-5">
                <div class="dc-card p-4 mb-4" style="position: sticky; top: 5.25rem;">
                    <div class="dc-eyebrow mb-2">Status Respons Kasus {{ $bloodRequest?->request_code }}</div>
                    <div class="d-flex align-items-baseline justify-content-between mb-2">
                        <span class="dc-title-2 dc-tabular mb-0">{{ $activePledgesCount }} Relawan Siaga</span>
                        <span class="dc-caption dc-tabular">Kebutuhan: {{ $bloodRequest?->remaining_bags }} Kantong</span>
                    </div>
                    <p class="dc-caption mb-3">
                        Sebutkan kode kasus <strong class="dc-tabular text-dark">{{ $bloodRequest?->request_code }}</strong> dan token <strong class="dc-tabular text-dark">{{ $commitment->coordination_code }}</strong> saat tiba di instalasi Bank Darah faskes tujuan.
                    </p>

                    <div class="d-grid gap-2">
                        @if ($isOwnerDonor && $commitment->canConfirmArrival() && Route::has('commitments.arrive'))
                            <form method="POST" action="{{ route('commitments.arrive', $commitment) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn btn-dc-primary w-100">
                                    <i class="bi bi-geo-alt-fill"></i>
                                    Konfirmasi Saya Sudah Tiba di Faskes
                                </button>
                            </form>
                        @elseif ($isOwnerDonor && $commitment->status === \App\Enums\DonorCommitmentStatus::ARRIVED)
                            <div class="p-3 rounded-3 bg-success-subtle border border-success-subtle small text-success-emphasis fw-semibold text-center">
                                <i class="bi bi-patch-check-fill me-1"></i>
                                Kehadiran Anda sudah tercatat di sistem faskes
                            </div>
                        @endif

                        @if (($isOwnerDonor || $isOwnerSeeker) && Route::has('coordinations.show'))
                            <a href="{{ route('coordinations.show', $commitment) }}" class="btn btn-dc-secondary">
                                <i class="bi bi-chat-square-text"></i>
                                Buka Ruang Koordinasi Titik Temu
                            </a>
                        @endif

                        @if ($isOwnerDonor && $commitment->canBeCancelledByDonor() && Route::has('commitments.cancel'))
                            <button type="button" class="btn btn-dc-soft" data-dc-open-sheet="cancelCommitmentSheet">
                                <i class="bi bi-slash-circle"></i>
                                Batalkan Komitmen Darurat
                            </button>
                        @endif

                        @if ($isOwnerDonor && $commitment->status === \App\Enums\DonorCommitmentStatus::CANCELLED && Route::has('discovery.index'))
                            <a href="{{ route('discovery.index') }}" class="btn btn-dc-secondary">
                                <i class="bi bi-compass"></i>
                                Lihat Katalog Permohonan Aktif Lain
                            </a>
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

@if ($isOwnerDonor && $commitment->canBeCancelledByDonor() && Route::has('commitments.cancel'))
    <!-- Fluid Bottom Sheet: Emergency Commitment Cancellation (§2, §5, §14) -->
    <div class="dc-sheet-backdrop" data-dc-sheet-backdrop="cancelCommitmentSheet"></div>
    <aside
        class="dc-sheet-panel"
        style="max-width: 36rem;"
        data-dc-sheet="cancelCommitmentSheet"
        aria-hidden="true"
        aria-labelledby="cancelCommitmentSheetTitle"
    >
        <div class="dc-sheet-handle-area" title="Tarik ke bawah untuk menutup">
            <span class="dc-sheet-handle"></span>
        </div>

        <form method="POST" action="{{ route('commitments.cancel', $commitment) }}" class="px-4 pb-4" novalidate>
            @csrf
            @method('PATCH')

            <div class="d-flex align-items-start justify-content-between gap-3 pb-3 mb-3 border-bottom">
                <div>
                    <div class="dc-eyebrow">Manajemen Status Komitmen</div>
                    <h2 id="cancelCommitmentSheetTitle" class="dc-title-2 mb-1 mt-1">Pembatalan Komitmen Darurat</h2>
                    <div class="dc-caption">
                        Kode <strong class="dc-tabular text-dark">{{ $commitment->pledge_code }}</strong> &middot;
                        Tujuan <strong class="text-dark">{{ $bloodRequest?->facility?->name }}</strong>
                    </div>
                </div>
                <button type="button" class="btn btn-dc-soft btn-sm flex-shrink-0" data-dc-close-sheet="cancelCommitmentSheet" aria-label="Tutup panel pembatalan">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <div class="p-3 rounded-3 bg-warning-subtle bg-opacity-50 border border-warning-subtle mb-3 dc-caption text-dark">
                <i class="bi bi-info-circle-fill text-warning me-1"></i>
                Jika Anda mengalami kendala mendadak atau kondisi kesehatan belum memungkinkan, pembatalan resmi akan langsung <strong>membuka kembali kuota relawan</strong> agar pendonor lain dapat segera menggantikan.
            </div>

            <div class="mb-3">
                <label class="form-label mb-1">Pilih Cepat Alasan Kendala Valid</label>
                <div class="d-flex flex-wrap gap-2">
                    @php
                        $reasonPresets = [
                            'Kondisi fisik mendadak kurang fit / demam sehingga belum layak mendonor hari ini.',
                            'Mengalami kendala darurat transportasi / lalu lintas menuju rumah sakit rujukan.',
                            'Hasil pengecekan awal di loket menunjukkan belum memenuhi syarat medis pra-donor.',
                            'Terdapat keperluan darurat keluarga/pekerjaan yang tidak dapat ditinggalkan.',
                        ];
                    @endphp
                    @foreach ($reasonPresets as $presetText)
                        <button
                            type="button"
                            class="btn btn-dc-secondary btn-sm text-start dc-pressable js-cancel-reason-preset"
                            data-reason="{{ $presetText }}"
                        >
                            {{ \Illuminate\Support\Str::limit($presetText, 46) }}
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="mb-4">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <label for="cancellationReasonInput" class="form-label mb-0">
                        Keterangan Alasan Pembatalan <span class="text-danger">*</span>
                    </label>
                    <span class="dc-caption dc-tabular" id="cancelReasonCounter">0 / 350</span>
                </div>
                <textarea
                    id="cancellationReasonInput"
                    name="cancellation_reason"
                    rows="3"
                    minlength="10"
                    maxlength="350"
                    class="form-control @error('cancellation_reason') is-invalid @enderror"
                    placeholder="Jelaskan kendala Anda secara singkat (minimal 10 karakter)..."
                    required
                >{{ old('cancellation_reason') }}</textarea>
                @error('cancellation_reason')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="d-flex flex-column flex-sm-row justify-content-end gap-2">
                <button type="button" class="btn btn-dc-secondary" data-dc-close-sheet="cancelCommitmentSheet">
                    Tetap Pertahankan Komitmen
                </button>
                <button type="submit" class="btn btn-dc-primary px-4">
                    <i class="bi bi-slash-circle"></i>
                    Konfirmasi Batalkan Komitmen
                </button>
            </div>
        </form>
    </aside>
@endif
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const reasonInput = document.getElementById('cancellationReasonInput');
    const counterEl = document.getElementById('cancelReasonCounter');

    const syncCounter = () => {
        if (!reasonInput || !counterEl) return;
        counterEl.textContent = `${reasonInput.value.length} / 350`;
    };

    document.querySelectorAll('.js-cancel-reason-preset').forEach((btn) => {
        btn.addEventListener('click', () => {
            if (!reasonInput) return;
            reasonInput.value = btn.dataset.reason || '';
            reasonInput.focus();
            syncCounter();
        });
    });

    reasonInput?.addEventListener('input', syncCounter);
    syncCounter();

    @if ($errors->has('cancellation_reason'))
        window.requestAnimationFrame(() => {
            window.DarahSheets?.get('cancelCommitmentSheet')?.open();
        });
    @endif
});
</script>
@endpush
