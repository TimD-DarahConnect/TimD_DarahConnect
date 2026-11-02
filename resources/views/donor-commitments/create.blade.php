@extends('layouts.app')

@section('title', 'Pledge Kesediaan Donor — ' . $bloodRequest->request_code)

@section('content')
<section class="py-4 py-lg-5">
    <div class="container" style="max-width: 66rem;">
        <!-- Header & Breadcrumb -->
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
            <div>
                <nav aria-label="breadcrumb" class="mb-1">
                    <ol class="breadcrumb mb-0 dc-caption">
                        @if (Route::has('discovery.index'))
                            <li class="breadcrumb-item"><a href="{{ route('discovery.index') }}" class="text-decoration-none text-secondary">Kebutuhan Darurat</a></li>
                        @endif
                        @if (Route::has('discovery.show'))
                            <li class="breadcrumb-item"><a href="{{ route('discovery.show', $bloodRequest) }}" class="text-decoration-none text-secondary dc-tabular">{{ $bloodRequest->request_code }}</a></li>
                        @endif
                        <li class="breadcrumb-item active" aria-current="page">Pledge Kehadiran</li>
                    </ol>
                </nav>
                <h1 class="dc-title-1 mb-1">Pernyataan Kesediaan Donor Darurat</h1>
                <p class="dc-caption mb-0">
                    Konfirmasikan estimasi waktu tiba Anda agar keluarga pasien dan instalasi rumah sakit mengetahui bantuan sedang menuju lokasi.
                </p>
            </div>

            <a href="{{ Route::has('discovery.show') ? route('discovery.show', $bloodRequest) : route('commitments.index') }}" class="btn btn-dc-secondary">
                <i class="bi bi-arrow-left"></i>
                Kembali
            </a>
        </div>

        @if ($errors->any())
            <div class="dc-card p-3 px-4 mb-4 border-danger-subtle bg-danger-subtle text-danger-emphasis" role="alert">
                <div class="fw-semibold mb-1"><i class="bi bi-exclamation-octagon-fill me-1"></i>Mohon periksa kembali isian komitmen Anda:</div>
                <ul class="mb-0 small ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($activeOtherCommitment)
            <div class="dc-card p-4 mb-4 border-warning border-opacity-50 bg-warning-subtle bg-opacity-25">
                <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3">
                    <div>
                        <div class="dc-eyebrow text-warning-emphasis">Komitmen Aktif Terdeteksi</div>
                        <p class="mb-0 small text-dark mt-1">
                            Anda saat ini masih memiliki komitmen aktif (<strong class="dc-tabular">{{ $activeOtherCommitment->pledge_code }}</strong>). Selesaikan atau kelola komitmen tersebut terlebih dahulu sebelum membuat komitmen baru.
                        </p>
                    </div>
                    <a href="{{ route('commitments.show', $activeOtherCommitment) }}" class="btn btn-dc-secondary btn-sm flex-shrink-0">
                        Lihat Komitmen Aktif
                    </a>
                </div>
            </div>
        @endif

        <div class="row g-4 align-items-start">
            <!-- Left: Pledge Form -->
            <div class="col-lg-7">
                <form method="POST" action="{{ route('commitments.store', $bloodRequest) }}" class="dc-card p-4 p-md-5">
                    @csrf

                    <!-- Medical Compatibility Banner -->
                    <div class="p-3 rounded-4 border mb-4 {{ $compatibility['compatible'] ? 'bg-success-subtle border-success-subtle text-success-emphasis' : 'bg-danger-subtle border-danger-subtle text-danger-emphasis' }}">
                        <div class="d-flex align-items-start gap-3">
                            <i class="bi {{ $compatibility['compatible'] ? 'bi-check-circle-fill' : 'bi-x-octagon-fill' }} fs-4"></i>
                            <div>
                                <div class="fw-bold small">{{ $compatibility['label'] }}</div>
                                <div class="small mt-1">{{ $compatibility['reason'] }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick ETA Presets + Precision Datetime Input -->
                    <div class="mb-4">
                        <label for="estimated_arrival_at" class="form-label">
                            Estimasi Waktu Kehadiran di Faskes (ETA) <span class="text-danger">*</span>
                        </label>
                        <div class="dc-caption mb-2">
                            Pilih pintasan durasi perjalanan atau tentukan tanggal dan jam kedatangan secara spesifik:
                        </div>

                        <div class="d-flex flex-wrap gap-2 mb-3" role="group" aria-label="Pintasan estimasi waktu tiba">
                            @foreach ([30 => '+30 Menit', 60 => '+1 Jam', 120 => '+2 Jam', 240 => '+4 Jam'] as $minutesOffset => $presetLabel)
                                <button
                                    type="button"
                                    class="btn btn-dc-secondary btn-sm js-eta-preset"
                                    data-minutes="{{ $minutesOffset }}"
                                >
                                    <i class="bi bi-stopwatch"></i>
                                    {{ $presetLabel }}
                                </button>
                            @endforeach
                        </div>

                        <input
                            type="datetime-local"
                            id="estimated_arrival_at"
                            name="estimated_arrival_at"
                            value="{{ old('estimated_arrival_at', $defaultEta) }}"
                            min="{{ now()->addMinutes(10)->format('Y-m-d\TH:i') }}"
                            @if ($bloodRequest->expires_at)
                                max="{{ $bloodRequest->expires_at->format('Y-m-d\TH:i') }}"
                            @endif
                            class="form-control dc-tabular @error('estimated_arrival_at') is-invalid @enderror"
                            required
                        >
                        @error('estimated_arrival_at')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Optional Structured Arrival Note -->
                    <div class="mb-4">
                        <label for="pledge_notes" class="form-label">Catatan Singkat Kedatangan (Opsional)</label>
                        <textarea
                            id="pledge_notes"
                            name="pledge_notes"
                            rows="3"
                            maxlength="350"
                            class="form-control @error('pledge_notes') is-invalid @enderror"
                            placeholder="Contoh: Berangkat dari area Dago menuju loket BDRS, membawa KTP dan kartu donor PMI."
                        >{{ old('pledge_notes') }}</textarea>
                        <div class="dc-caption mt-1">
                            <i class="bi bi-shield-lock text-success me-1"></i>
                            Demi privasi Anda, jangan mencantumkan nomor telepon atau alamat rumah pribadi pada kolom catatan ini.
                        </div>
                        @error('pledge_notes')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Health Readiness Declaration -->
                    <div class="p-3 rounded-4 bg-light border mb-4">
                        <div class="form-check">
                            <input
                                type="checkbox"
                                id="health_readiness_confirmed"
                                name="health_readiness_confirmed"
                                value="1"
                                class="form-check-input @error('health_readiness_confirmed') is-invalid @enderror"
                                @checked(old('health_readiness_confirmed'))
                                required
                            >
                            <label for="health_readiness_confirmed" class="form-check-label small fw-medium text-dark">
                                Saya menyatakan dalam kondisi sehat, cukup istirahat (&ge; 5 jam), sudah makan sebelum donor, tidak sedang mengonsumsi antibiotik, dan bersedia menjalani pemeriksaan pra-donor resmi di faskes tujuan.
                            </label>
                            @error('health_readiness_confirmed')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="d-grid gap-2">
                        <button
                            type="submit"
                            class="btn btn-dc-primary btn-lg"
                            @disabled(! $compatibility['compatible'] || $activeOtherCommitment !== null)
                        >
                            <i class="bi bi-heart-pulse-fill"></i>
                            Kirim Komitmen Kehadiran Donor
                        </button>
                    </div>
                </form>
            </div>

            <!-- Right: Target Case & Hospital Summary -->
            <div class="col-lg-5">
                <div class="dc-card p-4" style="position: sticky; top: 5.25rem;">
                    <div class="dc-eyebrow">Ringkasan Kasus Tujuan</div>
                    <div class="d-flex align-items-center gap-3 mt-2 pb-3 mb-3 border-bottom">
                        <span class="dc-blood-badge" style="width: 3.3rem; height: 3.3rem; font-size: 1.15rem;">
                            {{ $bloodRequest->blood_group_label }}
                        </span>
                        <div>
                            <div class="fw-bold text-dark dc-tabular">{{ $bloodRequest->request_code }}</div>
                            <div class="small text-danger fw-semibold dc-tabular">
                                Sisa Kebutuhan: {{ $bloodRequest->remaining_bags }} Kantong {{ $bloodRequest->component_type->value }}
                            </div>
                            <div class="dc-caption">{{ $bloodRequest->urgency_level->label() }}</div>
                        </div>
                    </div>

                    <div class="vstack gap-3 small">
                        <div>
                            <div class="dc-caption">Fasilitas Kesehatan Tujuan</div>
                            <div class="fw-semibold text-dark mt-1">
                                <i class="bi bi-hospital text-danger me-1"></i>
                                {{ $bloodRequest->facility?->name }}
                            </div>
                            <div class="dc-caption mt-1">{{ $bloodRequest->facility?->address }}</div>
                        </div>

                        <div class="p-3 rounded-3 bg-light border d-flex align-items-center justify-content-between">
                            <span class="dc-caption text-dark fw-medium">Relawan Siaga Saat Ini</span>
                            <span class="dc-pill dc-pill-neutral dc-tabular">
                                {{ $activePledgesCount }} Pendonor Berkomitmen
                            </span>
                        </div>

                        @if ($bloodRequest->needed_at)
                            <div>
                                <div class="dc-caption">Tenggat Waktu Kebutuhan Pasien</div>
                                <div class="fw-semibold dc-tabular mt-1 text-dark">
                                    {{ $bloodRequest->needed_at->translatedFormat('d M Y, H:i') }} WIB
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const etaInput = document.getElementById('estimated_arrival_at');
    const presetButtons = document.querySelectorAll('.js-eta-preset');

    const toLocalIsoMinute = (date) => {
        const pad = (n) => String(n).padStart(2, '0');
        return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
    };

    presetButtons.forEach((btn) => {
        btn.addEventListener('click', () => {
            const minutes = parseInt(btn.dataset.minutes, 10) || 60;
            const target = new Date(Date.now() + minutes * 60 * 1000);
            etaInput.value = toLocalIsoMinute(target);

            presetButtons.forEach((b) => b.classList.replace('btn-dc-primary', 'btn-dc-secondary'));
            btn.classList.replace('btn-dc-secondary', 'btn-dc-primary');
        });
    });
});
</script>
@endpush
