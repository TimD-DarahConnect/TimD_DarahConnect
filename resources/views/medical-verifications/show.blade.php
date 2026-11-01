@extends('layouts.app')

@section('title', 'Tinjauan Medis ' . $bloodRequest->request_code)

@section('content')
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

                <!-- Clinical Inspection Checklist & Verification Status Panel -->
                <div class="dc-card p-4">
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
                                <input type="checkbox" id="{{ $chkId }}" class="form-check-input mt-1 flex-shrink-0 js-sop-check">
                                <span class="small text-dark">{{ $chkLabel }}</span>
                            </label>
                        @endforeach
                    </div>

                    @if ($bloodRequest->verified_at)
                        <div class="p-3 rounded-3 bg-success-subtle border border-success-subtle">
                            <div class="fw-semibold text-success-emphasis small">
                                <i class="bi bi-patch-check-fill me-1"></i>
                                Telah Ditinjau oleh {{ $bloodRequest->verifier?->name ?? 'Petugas UDD' }}
                            </div>
                            <div class="dc-caption text-dark mt-1 dc-tabular">
                                Waktu putusan: {{ $bloodRequest->verified_at->translatedFormat('d M Y, H:i') }} WIB
                                @if ($bloodRequest->expires_at)
                                    &middot; Aktif s.d. {{ $bloodRequest->expires_at->translatedFormat('d M Y, H:i') }} WIB
                                @endif
                            </div>
                            @if ($bloodRequest->verification_notes)
                                <p class="mb-0 small text-dark mt-2">Catatan resmi: "{{ $bloodRequest->verification_notes }}"</p>
                            @endif
                        </div>
                    @else
                        <div class="p-3 rounded-3 bg-light border d-flex flex-column gap-2">
                            <div class="d-flex align-items-center gap-2 small fw-semibold text-dark">
                                <i class="bi bi-hourglass-split text-warning"></i>
                                Status Saat Ini: {{ $bloodRequest->status->label() }}
                            </div>
                            <div class="dc-caption">
                                Periksa kesesuaian data klinis di kiri dengan dokumen rujukan dokter pada panel pratinjau di sebelah kanan.
                            </div>

                            @if (Route::has('verifications.approve') || Route::has('verifications.reject'))
                                <div class="d-flex flex-wrap gap-2 mt-2">
                                    @if (Route::has('verifications.approve'))
                                        <button type="button" class="btn btn-dc-primary btn-sm" data-dc-open-sheet="approveRequestSheet">
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
                    @endif
                </div>
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
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const checks = Array.from(document.querySelectorAll('.js-sop-check'));
    const badge = document.getElementById('checklistBadge');
    if (!checks.length || !badge) return;

    const updateChecklist = () => {
        const done = checks.filter((c) => c.checked).length;
        badge.textContent = `${done} / ${checks.length} Diperiksa`;
        badge.className = 'dc-pill dc-tabular ' + (
            done === checks.length ? 'dc-pill-success' : (done > 0 ? 'dc-pill-warning' : 'dc-pill-neutral')
        );
    };

    checks.forEach((c) => c.addEventListener('change', updateChecklist));
});
</script>
@endpush
