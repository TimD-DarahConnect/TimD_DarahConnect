@extends('layouts.app')

@section('title', 'Tinjauan Medis ' . $bloodRequest->request_code)

@section('content')
@php
    $isPendingReview = $bloodRequest->isPendingVerification();
    $canApproveCase = $isPendingReview && $bloodRequest->hasReferralLetter();
@endphp

<section class="py-4 py-lg-5">
    <div class="container">
        <!-- Top Wayfinding & Rapid Queue Navigation -->
        <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
            <div>
                <nav aria-label="breadcrumb" class="mb-1">
                    <ol class="breadcrumb mb-0 dc-caption">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-secondary">Ruang Kerja</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('verifications.index') }}" class="text-decoration-none text-secondary">Antrean Verifikasi Medis</a></li>
                        <li class="breadcrumb-item active dc-tabular" aria-current="page">{{ $bloodRequest->request_code }}</li>
                    </ol>
                </nav>

                <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
                    <span class="dc-pill {{ $bloodRequest->urgency_level->pillClass() }}">
                        <i class="bi bi-exclamation-diamond-fill"></i>
                        {{ $bloodRequest->urgency_level->label() }}
                    </span>
                    <span class="dc-pill {{ $bloodRequest->status->pillClass() }}">
                        <i class="bi {{ $bloodRequest->status->icon() }}"></i>
                        {{ $bloodRequest->status->label() }}
                    </span>
                    <span class="dc-caption dc-tabular">Kode Kasus: <strong>{{ $bloodRequest->request_code }}</strong></span>
                </div>
            </div>

            <div class="d-flex flex-wrap align-items-center gap-2">
                @if ($isPendingReview && Route::has('verifications.approve'))
                    <button
                        type="button"
                        class="btn btn-dc-primary"
                        @if ($canApproveCase)
                            data-dc-open-sheet="approveRequestSheet"
                        @else
                            disabled
                            title="Berkas surat rujukan dokter belum dilampirkan oleh pemohon"
                        @endif
                    >
                        <i class="bi bi-check2-circle"></i>
                        Setujui &amp; Publikasikan
                    </button>
                @endif

                @if ($isPendingReview && Route::has('verifications.reject'))
                    <button
                        type="button"
                        class="btn btn-dc-soft"
                        data-dc-open-sheet="rejectRequestSheet"
                    >
                        <i class="bi bi-arrow-return-left"></i>
                        Minta Revisi / Tolak
                    </button>
                @endif

                @if ($nextPendingRequest)
                    <a href="{{ route('verifications.show', $nextPendingRequest) }}" class="btn btn-dc-soft" title="Lanjut ke {{ $nextPendingRequest->patient_name }}">
                        <i class="bi bi-skip-forward-fill"></i>
                        Kasus Antrean Berikutnya
                    </a>
                @endif

                <a href="{{ route('verifications.index') }}" class="btn btn-dc-secondary">
                    <i class="bi bi-arrow-left"></i>
                    Kembali ke Antrean
                </a>
            </div>
        </div>

        @if ($errors->any())
            <div class="dc-card p-3 mb-4 border-danger-subtle bg-danger-subtle text-danger-emphasis" role="alert">
                <div class="fw-semibold small mb-1">
                    <i class="bi bi-exclamation-circle-fill me-1"></i>Validasi pengesahan verifikasi memerlukan perbaikan:
                </div>
                <ul class="mb-0 ps-3 small">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Clinical Triage Priority Banner -->
        @if ($isSevereAnemia || $bloodRequest->urgency_level->value === 'critical')
            <div class="dc-card p-3 px-4 mb-4 border-danger border-opacity-25 bg-danger-subtle bg-opacity-25">
                <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
                    <div class="d-flex align-items-center gap-3">
                        <i class="bi bi-heart-pulse-fill text-danger fs-4"></i>
                        <div>
                            <div class="dc-eyebrow">Indikator Triase Prioritas Tinggi</div>
                            <div class="small fw-semibold text-dark">
                                @if ($isSevereAnemia)
                                    Kadar Hemoglobin pasien <span class="dc-tabular text-danger">{{ number_format($bloodRequest->hemoglobin_level, 1) }} g/dL</span> ({{ $bloodRequest->hemoglobin_severity_label }})
                                @else
                                    Kasus diajukan dengan tingkat urgensi klinis tertinggi ({{ $bloodRequest->urgency_level->label() }})
                                @endif
                            </div>
                        </div>
                    </div>

                    @if ($bloodRequest->needed_at)
                        <div class="dc-pill dc-pill-critical dc-tabular align-self-start align-self-md-center">
                            <i class="bi bi-alarm"></i>
                            Target Transfusi: {{ $bloodRequest->needed_at->translatedFormat('d M Y, H:i') }} WIB
                        </div>
                    @endif
                </div>
            </div>
        @endif

        <!-- Split-Pane Workspace: Left Clinical Dossier | Right Medical Document Viewer -->
        <div class="row g-4 align-items-start">
            <!-- Left Column: Clinical Dossier & Verification Checklist -->
            <div class="col-lg-6">
                <!-- Patient & Transfusion Card -->
                <div class="dc-card p-4 mb-4">
                    <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 pb-3 mb-3 border-bottom">
                        <div class="d-flex align-items-center gap-3">
                            <span class="dc-blood-badge" style="width: 3.4rem; height: 3.4rem; font-size: 1.2rem;">
                                {{ $bloodRequest->blood_group_label }}
                            </span>
                            <div>
                                <div class="dc-eyebrow">Identitas Pasien Resipien</div>
                                <h1 class="dc-title-2 mb-0 mt-1">{{ $bloodRequest->patient_name }}</h1>
                                <div class="dc-caption">
                                    @if ($bloodRequest->patient_age !== null)
                                        {{ $bloodRequest->patient_age }} tahun &middot;
                                    @endif
                                    {{ $bloodRequest->patient_gender === 'L' ? 'Laki-laki' : ($bloodRequest->patient_gender === 'P' ? 'Perempuan' : 'Belum dicantumkan') }}
                                    @if ($bloodRequest->medical_record_number)
                                        &middot; <span class="dc-tabular">No. RM: <strong>{{ $bloodRequest->medical_record_number }}</strong></span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="text-sm-end">
                            <div class="dc-caption">Kebutuhan Transfusi</div>
                            <div class="dc-title-3 text-danger dc-tabular mb-0">
                                {{ $bloodRequest->bags_needed }} Kantong {{ $bloodRequest->component_type->value }}
                            </div>
                            <div class="dc-caption">{{ $bloodRequest->component_type->label() }}</div>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-sm-6">
                            <div class="dc-caption">Diagnosis Klinis</div>
                            <div class="fw-semibold mt-1">{{ $bloodRequest->clinical_diagnosis ?: '-' }}</div>
                        </div>

                        <div class="col-sm-6">
                            <div class="dc-caption">Kadar Hemoglobin (Hb)</div>
                            <div class="fw-semibold dc-tabular mt-1 {{ $isSevereAnemia ? 'text-danger' : '' }}">
                                @if ($bloodRequest->hemoglobin_level !== null)
                                    {{ number_format($bloodRequest->hemoglobin_level, 1) }} g/dL
                                    <div class="dc-caption">{{ $bloodRequest->hemoglobin_severity_label }}</div>
                                @else
                                    -
                                @endif
                            </div>
                        </div>

                        <div class="col-sm-6">
                            <div class="dc-caption">Dokter Penanggung Jawab (DPJP)</div>
                            <div class="fw-semibold mt-1">{{ $bloodRequest->doctor_name ?: '-' }}</div>
                        </div>

                        <div class="col-sm-6">
                            <div class="dc-caption">Batas Waktu Dibutuhkan</div>
                            <div class="fw-semibold dc-tabular mt-1">
                                {{ $bloodRequest->needed_at ? $bloodRequest->needed_at->translatedFormat('d M Y, H:i').' WIB' : '-' }}
                            </div>
                        </div>

                        @if ($bloodRequest->additional_notes)
                            <div class="col-12">
                                <div class="p-3 rounded-3 bg-light border">
                                    <div class="dc-caption fw-semibold text-dark mb-1">Catatan Tambahan Klinis / BDRS</div>
                                    <p class="mb-0 small text-secondary">{{ $bloodRequest->additional_notes }}</p>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Referring Hospital & Requester Context Card -->
                <div class="dc-card p-4 mb-4">
                    <h2 class="dc-title-3 mb-3">Fasilitas Kesehatan Rujukan &amp; Pemohon</h2>

                    <div class="row g-3">
                        <div class="col-12">
                            <div class="p-3 rounded-3 bg-light border">
                                <div class="d-flex align-items-start justify-content-between gap-2">
                                    <div>
                                        <div class="fw-semibold text-dark">
                                            <i class="bi bi-hospital text-danger me-1"></i>
                                            {{ $bloodRequest->facility?->name }}
                                        </div>
                                        <div class="dc-caption mt-1">{{ $bloodRequest->facility?->address }}</div>
                                        @if ($bloodRequest->ward_room)
                                            <div class="small fw-medium text-dark mt-2">
                                                Ruang Rawat: <span class="text-danger">{{ $bloodRequest->ward_room }}</span>
                                            </div>
                                        @endif
                                    </div>
                                    @if ($bloodRequest->facility?->phone)
                                        <span class="dc-pill dc-pill-neutral dc-tabular flex-shrink-0">
                                            <i class="bi bi-telephone"></i>
                                            {{ $bloodRequest->facility->phone }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="col-sm-6">
                            <div class="dc-caption">Diajukan Oleh</div>
                            <div class="fw-semibold mt-1">{{ $bloodRequest->requester?->name }}</div>
                            <div class="dc-caption">{{ $bloodRequest->patient_relationship }}</div>
                        </div>

                        <div class="col-sm-6">
                            <div class="dc-caption">Waktu Masuk Antrean</div>
                            <div class="fw-semibold dc-tabular mt-1">
                                {{ $bloodRequest->submitted_at ? $bloodRequest->submitted_at->translatedFormat('d M Y, H:i').' WIB' : '-' }}
                            </div>
                            @if ($bloodRequest->submitted_at)
                                <div class="dc-caption">{{ $bloodRequest->submitted_at->diffForHumans() }}</div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Clinical Inspection Checklist & Verification Decision Panel -->
                <div class="dc-card p-4 mb-4">
                    <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
                        <div>
                            <div class="dc-eyebrow">SOP Pemeriksaan Berkas UDD</div>
                            <h2 class="dc-title-3 mb-0 mt-1">Daftar Periksa Validasi Medis</h2>
                        </div>
                        <span id="checklistBadge" class="dc-pill dc-pill-neutral dc-tabular">0 / 4 Diperiksa</span>
                    </div>

                    <div class="vstack gap-2 mb-3">
                        @php
                            $checkItems = [
                                'chk_identity' => 'Identitas pasien, usia, dan ruang rawat sesuai dengan surat pengantar RS/BDRS.',
                                'chk_blood' => 'Golongan darah ABO/Rhesus (' . $bloodRequest->blood_group_label . ') dan komponen (' . $bloodRequest->component_type->value . ') tercantum jelas.',
                                'chk_clinical' => 'Diagnosis klinis dan kadar Hemoglobin mendukung tingkat urgensi yang diajukan.',
                                'chk_doctor' => 'Terdapat nama terang, tanda tangan DPJP, atau stempel resmi instalasi rumah sakit.',
                            ];
                        @endphp
                        @foreach ($checkItems as $chkId => $chkLabel)
                            <label for="{{ $chkId }}" class="d-flex align-items-start gap-3 p-2 px-3 rounded-3 border bg-white dc-pressable" style="cursor: pointer;">
                                <input
                                    type="checkbox"
                                    id="{{ $chkId }}"
                                    class="form-check-input mt-1 flex-shrink-0 js-sop-check"
                                    @checked(! $isPendingReview && $bloodRequest->status === \App\Enums\BloodRequestStatus::ACTIVE)
                                    @disabled(! $isPendingReview)
                                >
                                <span class="small text-dark">{{ $chkLabel }}</span>
                            </label>
                        @endforeach
                    </div>

                    @if ($isPendingReview)
                        <div class="p-3 rounded-3 bg-light border d-flex flex-column gap-2">
                            <div class="d-flex align-items-center gap-2 small fw-semibold text-dark">
                                <i class="bi bi-hourglass-split text-warning"></i>
                                Status Saat Ini: {{ $bloodRequest->status->label() }}
                            </div>
                            <div class="dc-caption">
                                Periksa kesesuaian data klinis di kiri dengan dokumen rujukan dokter pada panel pratinjau di sebelah kanan sebelum menetapkan masa aktif publikasi.
                            </div>

                            @if (! $bloodRequest->hasReferralLetter())
                                <div class="p-2 px-3 rounded-3 bg-warning-subtle text-warning-emphasis small fw-medium">
                                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                                    Berkas rujukan belum terlampir — permohonan belum dapat disetujui untuk publikasi publik.
                                </div>
                            @endif

                            @if (Route::has('verifications.approve') || Route::has('verifications.reject'))
                                <div class="d-flex flex-wrap gap-2 mt-2">
                                    @if (Route::has('verifications.approve'))
                                        <button
                                            type="button"
                                            class="btn btn-dc-primary btn-sm"
                                            @if ($canApproveCase)
                                                data-dc-open-sheet="approveRequestSheet"
                                            @else
                                                disabled
                                            @endif
                                        >
                                            <i class="bi bi-check2-circle"></i>
                                            Setujui &amp; Publikasikan
                                        </button>
                                    @endif
                                    @if (Route::has('verifications.reject'))
                                        <button type="button" class="btn btn-dc-soft btn-sm" data-dc-open-sheet="rejectRequestSheet">
                                            <i class="bi bi-arrow-return-left"></i>
                                            Minta Revisi / Tolak
                                        </button>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @else
                        @php
                            $boxClasses = match ($bloodRequest->status) {
                                \App\Enums\BloodRequestStatus::ACTIVE, \App\Enums\BloodRequestStatus::FULFILLED => 'bg-success-subtle border-success-subtle text-success-emphasis',
                                \App\Enums\BloodRequestStatus::NEED_REVISION => 'bg-warning-subtle border-warning-subtle text-warning-emphasis',
                                \App\Enums\BloodRequestStatus::REJECTED, \App\Enums\BloodRequestStatus::CANCELLED => 'bg-danger-subtle border-danger-subtle text-danger-emphasis',
                                default => 'bg-light border text-dark',
                            };
                        @endphp
                        <div class="p-3 rounded-3 border {{ $boxClasses }}">
                            <div class="fw-semibold small d-flex align-items-center justify-content-between gap-2">
                                <span>
                                    <i class="bi {{ $bloodRequest->status->icon() }} me-1"></i>
                                    Putusan Terkini: {{ $bloodRequest->status->label() }}
                                </span>
                                @if ($bloodRequest->verifier)
                                    <span class="dc-caption fw-medium text-dark">Oleh {{ $bloodRequest->verifier->name }}</span>
                                @endif
                            </div>

                            @if ($bloodRequest->verified_at || $bloodRequest->expires_at)
                                <div class="dc-caption text-dark mt-1 dc-tabular">
                                    @if ($bloodRequest->verified_at)
                                        Waktu putusan: <strong>{{ $bloodRequest->verified_at->translatedFormat('d M Y, H:i') }} WIB</strong>
                                    @endif
                                    @if ($bloodRequest->expires_at)
                                        &middot; Masa aktif publikasi s.d. <strong>{{ $bloodRequest->expires_at->translatedFormat('d M Y, H:i') }} WIB</strong>
                                    @endif
                                </div>
                            @endif

                            @if ($bloodRequest->verification_notes)
                                <p class="mb-0 small text-dark mt-2">
                                    <strong>Catatan Resmi UDD:</strong> "{{ $bloodRequest->verification_notes }}"
                                </p>
                            @endif
                        </div>
                    @endif
                </div>

                <!-- Official Medical Verification Audit Trail -->
                @if ($verificationLogs->isNotEmpty() || $bloodRequest->verified_at)
                    <div class="dc-card p-4">
                        <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
                            <div>
                                <div class="dc-eyebrow">Rekam Jejak Otorisasi</div>
                                <h2 class="dc-title-3 mb-0 mt-1">Riwayat Verifikasi Petugas UDD</h2>
                            </div>
                            <i class="bi bi-journal-medical text-danger fs-4"></i>
                        </div>

                        <div class="vstack gap-3">
                            @forelse ($verificationLogs as $log)
                                <div class="p-3 rounded-3 bg-light border">
                                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-1">
                                        <span class="dc-pill {{ $log->decision->pillClass() }}">
                                            <i class="bi {{ $log->decision->icon() }}"></i>
                                            {{ $log->decision->label() }}
                                        </span>
                                        <span class="dc-caption dc-tabular">
                                            {{ $log->decided_at->translatedFormat('d M Y, H:i') }} WIB
                                        </span>
                                    </div>
                                    <div class="dc-caption text-dark mt-1">
                                        Verifikator: <strong>{{ $log->reviewer?->name ?? 'Petugas UDD' }}</strong>
                                        @if ($log->expires_at)
                                            &middot; Masa aktif s.d. <strong class="dc-tabular">{{ $log->expires_at->translatedFormat('d M Y, H:i') }} WIB</strong>
                                        @endif
                                    </div>
                                    @if ($log->notes)
                                        <div class="small text-secondary mt-2">"{{ $log->notes }}"</div>
                                    @endif
                                </div>
                            @empty
                                <div class="p-3 rounded-3 bg-light border">
                                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-1">
                                        <span class="dc-pill {{ $bloodRequest->status->pillClass() }}">
                                            <i class="bi {{ $bloodRequest->status->icon() }}"></i>
                                            {{ $bloodRequest->status->label() }}
                                        </span>
                                        <span class="dc-caption dc-tabular">
                                            {{ $bloodRequest->verified_at->translatedFormat('d M Y, H:i') }} WIB
                                        </span>
                                    </div>
                                    <div class="dc-caption text-dark mt-1">
                                        Verifikator: <strong>{{ $bloodRequest->verifier?->name ?? 'Petugas UDD' }}</strong>
                                        @if ($bloodRequest->expires_at)
                                            &middot; Masa aktif s.d. <strong class="dc-tabular">{{ $bloodRequest->expires_at->translatedFormat('d M Y, H:i') }} WIB</strong>
                                        @endif
                                    </div>
                                    @if ($bloodRequest->verification_notes)
                                        <div class="small text-secondary mt-2">"{{ $bloodRequest->verification_notes }}"</div>
                                    @endif
                                </div>
                            @endforelse
                        </div>
                    </div>
                @endif
            </div>

            <!-- Right Column: Sticky Medical Referral Document Viewer -->
            <div class="col-lg-6">
                <div class="dc-card p-4" style="position: sticky; top: 5.25rem;">
                    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 pb-3 mb-3 border-bottom">
                        <div>
                            <div class="dc-eyebrow">Lampiran Verifikasi Medis</div>
                            <h2 class="dc-title-3 mb-0 mt-1">Surat Rujukan Dokter / BDRS</h2>
                            @if ($bloodRequest->hasReferralLetter())
                                <div class="dc-caption mt-1 text-break">
                                    {{ $bloodRequest->referral_letter_original_name ?: 'surat-rujukan-medis' }}
                                    @if ($fileSizeFormatted)
                                        &middot; <span class="dc-tabular">{{ $fileSizeFormatted }}</span>
                                    @endif
                                </div>
                            @endif
                        </div>

                        @if ($bloodRequest->hasReferralLetter())
                            <div class="d-flex align-items-center gap-2">
                                <a
                                    href="{{ route('verifications.document', $bloodRequest) }}"
                                    target="_blank"
                                    rel="noopener"
                                    class="btn btn-dc-secondary btn-sm"
                                >
                                    <i class="bi bi-box-arrow-up-right"></i>
                                    Layar Penuh
                                </a>
                                <a
                                    href="{{ route('verifications.document', ['bloodRequest' => $bloodRequest, 'download' => 1]) }}"
                                    class="btn btn-dc-secondary btn-sm"
                                >
                                    <i class="bi bi-download"></i>
                                    Unduh
                                </a>
                            </div>
                        @endif
                    </div>

                    @if ($bloodRequest->hasReferralLetter())
                        @if ($isPdfAttachment)
                            <div class="rounded-3 overflow-hidden border bg-light">
                                <iframe
                                    src="{{ route('verifications.document', $bloodRequest) }}"
                                    title="Pratinjau Surat Rujukan {{ $bloodRequest->patient_name }}"
                                    class="w-100 border-0"
                                    style="height: 36rem;"
                                ></iframe>
                            </div>
                        @elseif ($isImageAttachment)
                            <div class="rounded-3 overflow-auto border bg-light p-3 text-center" style="max-height: 36rem;">
                                <img
                                    src="{{ route('verifications.document', $bloodRequest) }}"
                                    alt="Surat Rujukan {{ $bloodRequest->patient_name }}"
                                    class="img-fluid rounded-2 shadow-sm"
                                >
                            </div>
                        @else
                            <div class="p-4 rounded-3 bg-light border text-center">
                                <i class="bi bi-file-earmark-medical text-danger fs-1"></i>
                                <p class="small fw-medium mt-2 mb-3">Format berkas ini paling optimal dibuka pada jendela terpisah.</p>
                                <a href="{{ route('verifications.document', $bloodRequest) }}" target="_blank" rel="noopener" class="btn btn-dc-primary btn-sm">
                                    Buka Dokumen Rujukan
                                </a>
                            </div>
                        @endif

                        <div class="d-flex align-items-center gap-2 mt-3 dc-caption">
                            <i class="bi bi-shield-lock-fill text-success"></i>
                            <span>Dokumen dienkripsi aksesnya pada penyimpanan privat dan hanya dapat dibuka oleh petugas medis berwenang.</span>
                        </div>
                    @else
                        <div class="p-5 rounded-3 bg-warning-subtle bg-opacity-50 border border-warning-subtle text-center">
                            <i class="bi bi-file-earmark-excel text-warning fs-1"></i>
                            <h3 class="dc-title-3 mt-2 mb-1">Berkas Surat Rujukan Belum Tersedia</h3>
                            <p class="dc-caption mb-0">
                                Pemohon belum melampirkan surat pengantar resmi dari dokter atau instalasi BDRS rumah sakit.
                            </p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>

@if ($canApproveCase && Route::has('verifications.approve'))
    <!-- Fluid Bottom Sheet: Medical Approval & Publication Window (§1, §2, §5, §12) -->
    <div class="dc-sheet-backdrop" data-dc-sheet-backdrop="approveRequestSheet"></div>
    <aside
        class="dc-sheet-panel"
        style="max-width: 38rem;"
        data-dc-sheet="approveRequestSheet"
        aria-hidden="true"
        aria-labelledby="approveRequestSheetTitle"
    >
        <div class="dc-sheet-handle-area" title="Tarik ke bawah untuk menutup">
            <span class="dc-sheet-handle"></span>
        </div>

        <form action="{{ route('verifications.approve', $bloodRequest) }}" method="POST" class="px-4 pb-4" novalidate>
            @csrf

            <div class="d-flex align-items-start justify-content-between gap-3 pb-3 mb-3 border-bottom">
                <div>
                    <div class="dc-eyebrow text-success">Otorisasi Publikasi Medis UDD</div>
                    <h2 id="approveRequestSheetTitle" class="dc-title-2 mb-1 mt-1">Setujui &amp; Aktifkan Permohonan</h2>
                    <div class="dc-caption">
                        Kasus <strong class="dc-tabular text-dark">{{ $bloodRequest->request_code }}</strong> &middot;
                        <strong class="text-dark">{{ $bloodRequest->patient_name }}</strong>
                        ({{ $bloodRequest->bags_needed }} Kantong {{ $bloodRequest->component_type->value }} Gol. {{ $bloodRequest->blood_group_label }})
                    </div>
                </div>
                <button type="button" class="btn btn-dc-soft btn-sm flex-shrink-0" data-dc-close-sheet="approveRequestSheet" aria-label="Tutup panel persetujuan">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <!-- Preset Duration Chips -->
            <div class="mb-3">
                <label class="form-label mb-1">Pilih Cepat Durasi Publikasi Aktif</label>
                <div class="d-flex flex-wrap gap-2">
                    @foreach ([24 => '+24 Jam', 48 => '+48 Jam (Standar Darurat)', 72 => '+72 Jam (3 Hari)', 168 => '+7 Hari'] as $hours => $presetLabel)
                        <button
                            type="button"
                            class="btn btn-dc-secondary btn-sm dc-pressable js-expiry-preset"
                            data-hours="{{ $hours }}"
                        >
                            {{ $presetLabel }}
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="mb-3">
                <label for="approveExpiresAt" class="form-label">
                    Batas Waktu Masa Aktif Publikasi <span class="text-danger">*</span>
                </label>
                <input
                    type="datetime-local"
                    id="approveExpiresAt"
                    name="expires_at"
                    value="{{ old('expires_at', $defaultExpiresAt) }}"
                    min="{{ now()->addMinutes(5)->format('Y-m-d\TH:i') }}"
                    max="{{ now()->addDays(14)->endOfDay()->format('Y-m-d\TH:i') }}"
                    class="form-control dc-tabular @error('expires_at') is-invalid @enderror"
                    required
                >
                @error('expires_at')
                    <div class="invalid-feedback">{{ $message }}</div>
                @else
                    <div class="dc-caption mt-1" id="expiryDurationPreview">
                        Permohonan akan otomatis berhenti tayang di katalog pendonor setelah melewati waktu ini.
                    </div>
                @enderror
            </div>

            <div class="mb-3">
                <label for="approveVerificationNotes" class="form-label">
                    Catatan Pengesahan Medis UDD <span class="text-muted fw-normal">(Opsional)</span>
                </label>
                <textarea
                    id="approveVerificationNotes"
                    name="verification_notes"
                    rows="3"
                    maxlength="1000"
                    class="form-control @error('verification_notes') is-invalid @enderror"
                    placeholder="Contoh: Surat rujukan DPJP dan indikasi Hb tervalidasi. Pendonor diarahkan ke loket BDRS / UDD rujukan."
                >{{ old('verification_notes') }}</textarea>
                @error('verification_notes')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="p-3 rounded-3 bg-light border mb-4 d-flex align-items-center justify-content-between gap-2 small">
                <span class="text-secondary">
                    <i class="bi bi-clipboard2-check text-danger me-1"></i>
                    Status Daftar Periksa SOP:
                </span>
                <strong class="dc-tabular text-dark" id="sheetChecklistMirror">0 / 4 butir dicentang</strong>
            </div>

            <div class="d-flex flex-column flex-sm-row justify-content-end gap-2">
                <button type="button" class="btn btn-dc-secondary" data-dc-close-sheet="approveRequestSheet">
                    Batal
                </button>
                <button type="submit" class="btn btn-dc-primary px-4">
                    <i class="bi bi-broadcast-pin"></i>
                    Setujui &amp; Publikasikan ke Pendonor
                </button>
            </div>
        </form>
    </aside>
@endif

@if ($isPendingReview && Route::has('verifications.reject'))
    @php
        $selectedDecision = old('decision', 'need_revision');
    @endphp
    <!-- Fluid Bottom Sheet: Request Official Revision or Medical Rejection (§1, §2, §5, §12, §14) -->
    <div class="dc-sheet-backdrop" data-dc-sheet-backdrop="rejectRequestSheet"></div>
    <aside
        class="dc-sheet-panel"
        style="max-width: 40rem;"
        data-dc-sheet="rejectRequestSheet"
        aria-hidden="true"
        aria-labelledby="rejectRequestSheetTitle"
    >
        <div class="dc-sheet-handle-area" title="Tarik ke bawah untuk menutup">
            <span class="dc-sheet-handle"></span>
        </div>

        <form action="{{ route('verifications.reject', $bloodRequest) }}" method="POST" class="px-4 pb-4 overflow-auto" novalidate>
            @csrf

            <div class="d-flex align-items-start justify-content-between gap-3 pb-3 mb-3 border-bottom">
                <div>
                    <div class="dc-eyebrow text-danger">Tindak Lanjut Verifikasi Medis UDD</div>
                    <h2 id="rejectRequestSheetTitle" class="dc-title-2 mb-1 mt-1">Minta Revisi atau Tolak Permohonan</h2>
                    <div class="dc-caption">
                        Kasus <strong class="dc-tabular text-dark">{{ $bloodRequest->request_code }}</strong> &middot;
                        <strong class="text-dark">{{ $bloodRequest->patient_name }}</strong>
                    </div>
                </div>
                <button type="button" class="btn btn-dc-soft btn-sm flex-shrink-0" data-dc-close-sheet="rejectRequestSheet" aria-label="Tutup panel revisi atau penolakan">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Pilih Keputusan Tindak Lanjut <span class="text-danger">*</span></label>
                <div class="vstack gap-2">
                    <label class="p-3 rounded-4 border d-flex align-items-start gap-2 cursor-pointer dc-pressable bg-light">
                        <input
                            type="radio"
                            name="decision"
                            value="need_revision"
                            class="form-check-input mt-1 js-reject-decision-radio"
                            @checked($selectedDecision === 'need_revision')
                        >
                        <div>
                            <div class="fw-semibold text-dark small">
                                <i class="bi bi-arrow-return-left text-warning-emphasis me-1"></i>Minta Revisi Data / Perbaikan Berkas
                            </div>
                            <div class="dc-caption">
                                Mengembalikan permohonan ke pemohon agar dapat memperbaiki rincian klinis atau mengunggah ulang surat rujukan dokter yang sah, lalu mengirim kembali ke antrean.
                            </div>
                        </div>
                    </label>

                    <label class="p-3 rounded-4 border d-flex align-items-start gap-2 cursor-pointer dc-pressable bg-light">
                        <input
                            type="radio"
                            name="decision"
                            value="rejected"
                            class="form-check-input mt-1 js-reject-decision-radio"
                            @checked($selectedDecision === 'rejected')
                        >
                        <div>
                            <div class="fw-semibold text-dark small">
                                <i class="bi bi-x-octagon-fill text-danger me-1"></i>Tolak Permohonan Secara Medis
                            </div>
                            <div class="dc-caption">
                                Menutup permohonan secara permanen apabila dokumen tidak sah, duplikat, atau tidak memenuhi indikasi medis pelayanan transfusi darah.
                            </div>
                        </div>
                    </label>
                </div>
                @error('decision')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <span class="dc-caption fw-semibold text-dark d-block mb-2">Template Catatan Klinis Cepat:</span>
                <div class="d-flex flex-wrap gap-2">
                    @php
                        $quickReviewNotes = [
                            'Lampiran foto/scan surat rujukan dokter buram atau terpotong, mohon unggah ulang dokumen yang terbaca jelas.',
                            'Golongan darah ABO/Rhesus atau jenis komponen pada formulir belum sesuai dengan surat pengantar DPJP/BDRS.',
                            'Surat rujukan belum mencantumkan tanda tangan dokter penanggung jawab (DPJP) atau stempel resmi instalasi RS.',
                            'Dokumen yang dilampirkan tidak valid / bukan merupakan surat pengantar permintaan transfusi darah resmi.',
                        ];
                    @endphp
                    @foreach ($quickReviewNotes as $presetNote)
                        <button
                            type="button"
                            class="btn btn-dc-secondary btn-sm text-start dc-pressable js-reject-note-chip"
                            data-note="{{ $presetNote }}"
                            style="font-size: 0.75rem;"
                        >
                            {{ $presetNote }}
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="mb-4">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <label for="reviewNotesInput" class="form-label mb-0">
                        Catatan Resmi untuk Pemohon <span class="text-danger">*</span>
                    </label>
                    <span class="dc-caption dc-tabular"><span id="reviewNotesCharCount">0</span> / 1000</span>
                </div>
                <textarea
                    id="reviewNotesInput"
                    name="review_notes"
                    rows="3"
                    minlength="15"
                    maxlength="1000"
                    class="form-control @error('review_notes') is-invalid @enderror"
                    placeholder="Tuliskan instruksi perbaikan berkas/data atau alasan penolakan medis secara jelas (minimal 15 karakter)..."
                    required
                >{{ old('review_notes') }}</textarea>
                @error('review_notes')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="d-flex flex-column flex-sm-row justify-content-end gap-2">
                <button type="button" class="btn btn-dc-secondary" data-dc-close-sheet="rejectRequestSheet">
                    Batal
                </button>
                <button type="submit" class="btn btn-dc-primary px-4">
                    <i class="bi bi-send-check"></i>
                    <span id="rejectSubmitLabel">
                        {{ $selectedDecision === 'rejected' ? 'Konfirmasi Tolak Permohonan' : 'Kirim Permintaan Revisi ke Pemohon' }}
                    </span>
                </button>
            </div>
        </form>
    </aside>
@endif
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const checks = Array.from(document.querySelectorAll('.js-sop-check'));
    const badge = document.getElementById('checklistBadge');
    const sheetMirror = document.getElementById('sheetChecklistMirror');

    const updateChecklist = () => {
        if (!checks.length) return;
        const done = checks.filter((c) => c.checked).length;
        if (badge) {
            badge.textContent = `${done} / ${checks.length} Diperiksa`;
            badge.className = 'dc-pill dc-tabular ' + (
                done === checks.length ? 'dc-pill-success' : (done > 0 ? 'dc-pill-warning' : 'dc-pill-neutral')
            );
        }
        if (sheetMirror) {
            sheetMirror.textContent = `${done} / ${checks.length} butir dicentang`;
        }
    };

    checks.forEach((c) => c.addEventListener('change', updateChecklist));
    updateChecklist();

    const expiresInput = document.getElementById('approveExpiresAt');
    const durationPreview = document.getElementById('expiryDurationPreview');

    const toLocalInputString = (dateObj) => {
        const pad = (n) => String(n).padStart(2, '0');
        return `${dateObj.getFullYear()}-${pad(dateObj.getMonth() + 1)}-${pad(dateObj.getDate())}T${pad(dateObj.getHours())}:${pad(dateObj.getMinutes())}`;
    };

    const refreshDurationHint = () => {
        if (!expiresInput || !durationPreview || !expiresInput.value) return;
        const target = new Date(expiresInput.value);
        const diffMs = target.getTime() - Date.now();
        if (Number.isNaN(diffMs)) return;

        if (diffMs <= 0) {
            durationPreview.textContent = 'Waktu yang dipilih sudah lewat — pilih waktu di masa mendatang.';
            durationPreview.className = 'dc-caption mt-1 text-danger fw-semibold';
            return;
        }

        const totalHours = Math.round(diffMs / 3600000);
        const label = totalHours >= 48
            ? `sekitar ${(totalHours / 24).toFixed(1)} hari (${totalHours} jam)`
            : `sekitar ${Math.max(1, totalHours)} jam`;

        durationPreview.textContent = `Publikasi darurat akan tayang aktif selama ${label} ke depan.`;
        durationPreview.className = 'dc-caption mt-1 text-success-emphasis fw-medium';
    };

    document.querySelectorAll('.js-expiry-preset').forEach((btn) => {
        btn.addEventListener('click', () => {
            const hours = parseInt(btn.dataset.hours || '48', 10);
            if (!expiresInput || Number.isNaN(hours)) return;
            const target = new Date(Date.now() + hours * 3600000);
            target.setSeconds(0, 0);
            expiresInput.value = toLocalInputString(target);
            refreshDurationHint();
        });
    });

    expiresInput?.addEventListener('input', refreshDurationHint);
    expiresInput?.addEventListener('change', refreshDurationHint);
    refreshDurationHint();

    @if ($errors->has('expires_at') || $errors->has('verification_notes'))
        window.requestAnimationFrame(() => {
            window.DarahSheets?.get('approveRequestSheet')?.open();
        });
    @endif

    const reviewNotesInput = document.getElementById('reviewNotesInput');
    const reviewNotesCharCount = document.getElementById('reviewNotesCharCount');
    const rejectSubmitLabel = document.getElementById('rejectSubmitLabel');

    const updateReviewCount = () => {
        if (reviewNotesInput && reviewNotesCharCount) {
            reviewNotesCharCount.textContent = String(reviewNotesInput.value.length);
        }
    };

    document.querySelectorAll('.js-reject-note-chip').forEach((chip) => {
        chip.addEventListener('click', () => {
            if (!reviewNotesInput) return;
            reviewNotesInput.value = chip.dataset.note || '';
            reviewNotesInput.focus();
            updateReviewCount();
        });
    });

    document.querySelectorAll('.js-reject-decision-radio').forEach((radio) => {
        radio.addEventListener('change', () => {
            if (!rejectSubmitLabel) return;
            rejectSubmitLabel.textContent = radio.value === 'rejected'
                ? 'Konfirmasi Tolak Permohonan'
                : 'Kirim Permintaan Revisi ke Pemohon';
        });
    });

    reviewNotesInput?.addEventListener('input', updateReviewCount);
    updateReviewCount();

    @if ($errors->has('decision') || $errors->has('review_notes'))
        window.requestAnimationFrame(() => {
            window.DarahSheets?.get('rejectRequestSheet')?.open();
        });
    @endif
});
</script>
@endpush
