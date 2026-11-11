@extends('layouts.app')

@section('title', 'Detail Permohonan ' . $bloodRequest->request_code)

@section('content')
<section class="py-4 py-lg-5">
    <div class="container">
        <!-- Header & Wayfinding -->
        <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
            <div>
                <nav aria-label="breadcrumb" class="mb-1">
                    <ol class="breadcrumb mb-0 dc-caption">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-secondary">Ruang Kerja</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('emergency-requests.index') }}" class="text-decoration-none text-secondary">Permohonan Saya</a></li>
                        <li class="breadcrumb-item active dc-tabular" aria-current="page">{{ $bloodRequest->request_code }}</li>
                    </ol>
                </nav>

                <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
                    <span class="dc-pill {{ $bloodRequest->status->pillClass() }}">
                        <i class="bi {{ $bloodRequest->status->icon() }}"></i>
                        {{ $bloodRequest->status->label() }}
                    </span>
                    <span class="dc-pill {{ $bloodRequest->urgency_level->pillClass() }}">
                        {{ $bloodRequest->urgency_level->label() }}
                    </span>
                    <span class="dc-caption dc-tabular">Kode: <strong>{{ $bloodRequest->request_code }}</strong></span>
                </div>
            </div>

            <div class="d-flex flex-wrap align-items-center gap-2">
                @if (Route::has('emergency-requests.edit') && $bloodRequest->isEditableByRequester())
                    <a href="{{ route('emergency-requests.edit', $bloodRequest) }}" class="btn btn-dc-secondary">
                        <i class="bi bi-pencil-square"></i>
                        Edit Permohonan
                    </a>
                @endif

                @if (Route::has('emergency-requests.cancel') && $bloodRequest->isCancellableByRequester())
                    <button type="button" class="btn btn-dc-soft" data-dc-open-sheet="cancelRequestSheet">
                        <i class="bi bi-slash-circle"></i>
                        Batalkan Permohonan
                    </button>
                @endif

                <a href="{{ route('emergency-requests.index') }}" class="btn btn-dc-secondary">
                    <i class="bi bi-arrow-left"></i>
                    Kembali
                </a>
            </div>
        </div>

        <!-- Official Verification / Cancellation Feedback Banner -->
        @if ($bloodRequest->verification_notes)
            <div class="dc-card p-4 mb-4 border-warning border-opacity-50 bg-warning-subtle bg-opacity-25">
                <div class="d-flex align-items-start gap-3">
                    <i class="bi bi-clipboard2-check-fill text-warning fs-4"></i>
                    <div>
                        <div class="dc-eyebrow text-warning-emphasis">Catatan Verifikasi Petugas UDD</div>
                        <p class="mb-1 small fw-medium text-dark mt-1">{{ $bloodRequest->verification_notes }}</p>
                        @if ($bloodRequest->verifier)
                            <div class="dc-caption">
                                Ditinjau oleh {{ $bloodRequest->verifier->name }}
                                @if ($bloodRequest->verified_at)
                                    pada {{ $bloodRequest->verified_at->translatedFormat('d M Y, H:i') }} WIB
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        @if ($bloodRequest->cancellation_reason)
            <div class="dc-card p-4 mb-4 border-danger border-opacity-25 bg-danger-subtle bg-opacity-25">
                <div class="d-flex align-items-start gap-3">
                    <i class="bi bi-slash-circle-fill text-danger fs-4"></i>
                    <div>
                        <div class="dc-eyebrow">Permohonan Telah Dibatalkan</div>
                        <p class="mb-1 small fw-medium text-dark mt-1">Alasan: {{ $bloodRequest->cancellation_reason }}</p>
                        @if ($bloodRequest->cancelled_at)
                            <div class="dc-caption">Dibatalkan pada {{ $bloodRequest->cancelled_at->translatedFormat('d M Y, H:i') }} WIB</div>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        <div class="row g-4 align-items-start">
            <!-- Left Column: Patient & Clinical Details -->
            <div class="col-lg-8">
                <div class="dc-card p-4 p-md-5 mb-4">
                    <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 pb-4 mb-4 border-bottom">
                        <div class="d-flex align-items-center gap-3">
                            <span class="dc-blood-badge" style="width: 3.5rem; height: 3.5rem; font-size: 1.25rem;">
                                {{ $bloodRequest->blood_group_label }}
                            </span>
                            <div>
                                <div class="dc-eyebrow">Pasien Membutuhkan Transfusi</div>
                                <h1 class="dc-title-2 mb-0 mt-1">{{ $bloodRequest->patient_name }}</h1>
                                <div class="dc-caption">
                                    @if ($bloodRequest->patient_age !== null)
                                        {{ $bloodRequest->patient_age }} tahun &middot;
                                    @endif
                                    {{ $bloodRequest->patient_gender === 'L' ? 'Laki-laki' : ($bloodRequest->patient_gender === 'P' ? 'Perempuan' : 'Belum dilengkapi') }}
                                    &middot; Diajukan oleh {{ $bloodRequest->patient_relationship }}
                                </div>
                            </div>
                        </div>

                        <div class="text-sm-end">
                            <div class="dc-caption">Target Komponen</div>
                            <div class="dc-title-3 text-danger dc-tabular mb-0">
                                {{ $bloodRequest->bags_needed }} Kantong {{ $bloodRequest->component_type->value }}
                            </div>
                            <div class="dc-caption">{{ $bloodRequest->component_type->label() }}</div>
                        </div>
                    </div>

                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="dc-caption">Diagnosis Klinis</div>
                            <div class="fw-semibold mt-1">{{ $bloodRequest->clinical_diagnosis ?: 'Belum dilengkapi (Draf)' }}</div>
                        </div>

                        <div class="col-md-6">
                            <div class="dc-caption">Kadar Hemoglobin (Hb)</div>
                            <div class="fw-semibold dc-tabular mt-1">
                                @if ($bloodRequest->hemoglobin_level !== null)
                                    {{ number_format($bloodRequest->hemoglobin_level, 1) }} g/dL
                                    <span class="dc-caption">({{ $bloodRequest->hemoglobin_severity_label }})</span>
                                @else
                                    Belum dilengkapi (Draf)
                                @endif
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="dc-caption">Rumah Sakit / Faskes Rujukan</div>
                            <div class="fw-semibold mt-1">{{ $bloodRequest->facility?->name }}</div>
                            <div class="dc-caption">{{ $bloodRequest->facility?->address }}</div>
                        </div>

                        <div class="col-md-6">
                            <div class="dc-caption">Ruang Rawat &amp; Rekam Medis</div>
                            <div class="fw-semibold mt-1">{{ $bloodRequest->ward_room ?: 'Belum dilengkapi' }}</div>
                            @if ($bloodRequest->medical_record_number)
                                <div class="dc-caption dc-tabular">No. RM: {{ $bloodRequest->medical_record_number }}</div>
                            @endif
                        </div>

                        <div class="col-md-6">
                            <div class="dc-caption">Dokter Penanggung Jawab (DPJP)</div>
                            <div class="fw-semibold mt-1">{{ $bloodRequest->doctor_name ?: 'Belum dilengkapi' }}</div>
                        </div>

                        <div class="col-md-6">
                            <div class="dc-caption">Batas Waktu Kebutuhan</div>
                            <div class="fw-semibold dc-tabular mt-1">
                                {{ $bloodRequest->needed_at ? $bloodRequest->needed_at->translatedFormat('d M Y, H:i').' WIB' : 'Belum ditetapkan' }}
                            </div>
                        </div>

                        @if ($bloodRequest->additional_notes)
                            <div class="col-12">
                                <div class="p-3 rounded-4 bg-light border">
                                    <div class="dc-caption fw-semibold text-dark mb-1">Catatan Tambahan Pemohon:</div>
                                    <div class="small text-secondary">{{ $bloodRequest->additional_notes }}</div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Protected Referral Document Card -->
                <div class="dc-card p-4">
                    <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="rounded-4 bg-danger-subtle text-danger d-inline-flex align-items-center justify-content-center flex-shrink-0" style="width: 3rem; height: 3rem;">
                                <i class="bi bi-file-earmark-lock2-fill fs-4"></i>
                            </div>
                            <div>
                                <div class="dc-eyebrow">Lampiran Verifikasi Medis</div>
                                @if ($bloodRequest->hasReferralLetter())
                                    <div class="fw-semibold small">{{ $bloodRequest->referral_letter_original_name }}</div>
                                    <div class="dc-caption dc-tabular">
                                        {{ number_format(($bloodRequest->referral_letter_size ?? 0) / 1024, 1) }} KB &middot; Akses Terproteksi (Pemohon &amp; Petugas Medis)
                                    </div>
                                @else
                                    <div class="fw-semibold small text-muted">Belum ada berkas surat rujukan dokter yang dilampirkan</div>
                                    <div class="dc-caption">Lengkapi surat rujukan sebelum mengirim permohonan ke verifikasi UDD.</div>
                                @endif
                            </div>
                        </div>

                        @if ($bloodRequest->hasReferralLetter())
                            <a
                                href="{{ route('emergency-requests.referral-letter', $bloodRequest) }}"
                                target="_blank"
                                rel="noopener"
                                class="btn btn-dc-secondary btn-sm px-3"
                            >
                                <i class="bi bi-eye"></i> Lihat Berkas Resmi
                            </a>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Right Column: Fulfillment & Verification Lifecycle Timeline -->
            <div class="col-lg-4">
                <div class="dc-card p-4 mb-4">
                    <div class="dc-eyebrow mb-1">Status Pemenuhan</div>
                    <div class="d-flex align-items-baseline justify-content-between mb-2">
                        <span class="dc-title-1 dc-tabular mb-0">{{ $bloodRequest->bags_fulfilled }} <span class="fs-6 text-secondary fw-normal">/ {{ $bloodRequest->bags_needed }} Kantong</span></span>
                        <span class="dc-caption dc-tabular fw-semibold">{{ $bloodRequest->fulfillment_percentage }}%</span>
                    </div>
                    <div class="progress rounded-pill mb-3" style="height: 0.55rem;">
                        <div class="progress-bar bg-danger" style="width: {{ $bloodRequest->fulfillment_percentage }}%"></div>
                    </div>

                    @if ($bloodRequest->expires_at && $bloodRequest->status->value === 'active')
                        <div class="dc-caption dc-tabular">
                            <i class="bi bi-broadcast text-success me-1"></i>
                            Tayang aktif hingga <strong>{{ $bloodRequest->expires_at->translatedFormat('d M Y, H:i') }} WIB</strong>
                        </div>
                    @else
                        <div class="dc-caption">
                            Permohonan baru akan tampil ke direktori pendonor setelah disetujui oleh Petugas UDD.
                        </div>
                    @endif
                </div>

                @if (\Illuminate\Support\Facades\Schema::hasTable('donor_commitments'))
                    @php
                        $incomingCommitments = $bloodRequest->commitments()
                            ->with('donor')
                            ->activeCommitment()
                            ->orderBy('estimated_arrival_at')
                            ->get();
                    @endphp
                    <div class="dc-card p-4 mb-4">
                        <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
                            <div>
                                <div class="dc-eyebrow">Respons Komunitas</div>
                                <h2 class="dc-title-3 mb-0 mt-1">Pendonor Berkomitmen</h2>
                            </div>
                            <span class="dc-pill {{ $incomingCommitments->isNotEmpty() ? 'dc-pill-success' : 'dc-pill-neutral' }} dc-tabular">
                                {{ $incomingCommitments->count() }} Siaga
                            </span>
                        </div>

                        @forelse ($incomingCommitments as $pledge)
                            <div class="p-3 rounded-3 bg-light border mb-2">
                                <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
                                    <span class="fw-semibold small text-dark">
                                        <i class="bi bi-person-heart text-danger me-1"></i>
                                        {{ $pledge->masked_donor_alias }}
                                        @if ($pledge->donor?->blood_group_label)
                                            <span class="badge text-bg-danger ms-1 dc-tabular">{{ $pledge->donor->blood_group_label }}</span>
                                        @endif
                                    </span>
                                    <span class="dc-pill {{ $pledge->status->pillClass() }} py-0 px-2" style="font-size: 0.7rem;">
                                        {{ $pledge->status->label() }}
                                    </span>
                                </div>
                                <div class="dc-caption dc-tabular">
                                    <i class="bi bi-clock me-1"></i>
                                    Estimasi Tiba: <strong class="text-dark">{{ $pledge->estimated_arrival_at->translatedFormat('d M, H:i') }} WIB</strong>
                                    ({{ $pledge->estimated_arrival_at->diffForHumans() }})
                                </div>
                                @if (Route::has('commitments.show'))
                                    <div class="mt-2">
                                        <a href="{{ route('commitments.show', $pledge) }}" class="btn btn-dc-secondary btn-sm w-100">
                                            Lihat Rincian Kedatangan
                                        </a>
                                    </div>
                                @endif
                            </div>
                        @empty
                            <p class="dc-caption mb-0">
                                Belum ada relawan pendonor yang mengirim estimasi waktu kedatangan pada permohonan ini.
                            </p>
                        @endforelse
                    </div>
                @endif

                <div class="dc-card p-4">
                    <div class="dc-eyebrow mb-3">Jejak Siklus Permohonan</div>
                    <div class="vstack gap-3 small">
                        <div class="d-flex align-items-start gap-3">
                            <i class="bi bi-check-circle-fill text-success mt-1"></i>
                            <div>
                                <div class="fw-semibold">Permohonan Tercatat</div>
                                <div class="dc-caption dc-tabular">{{ $bloodRequest->created_at->translatedFormat('d M Y, H:i') }} WIB</div>
                            </div>
                        </div>

                        <div class="d-flex align-items-start gap-3">
                            <i class="bi {{ $bloodRequest->submitted_at ? 'bi-check-circle-fill text-success' : 'bi-circle text-muted' }} mt-1"></i>
                            <div>
                                <div class="fw-semibold">Dikirim ke Antrean UDD</div>
                                <div class="dc-caption dc-tabular">
                                    {{ $bloodRequest->submitted_at ? $bloodRequest->submitted_at->translatedFormat('d M Y, H:i').' WIB' : 'Masih berstatus Draf' }}
                                </div>
                            </div>
                        </div>

                        <div class="d-flex align-items-start gap-3">
                            <i class="bi {{ $bloodRequest->verified_at && $bloodRequest->status->value === 'active' ? 'bi-check-circle-fill text-success' : 'bi-circle text-muted' }} mt-1"></i>
                            <div>
                                <div class="fw-semibold">Verifikasi &amp; Publikasi Medis</div>
                                <div class="dc-caption">
                                    @if ($bloodRequest->verified_at && $bloodRequest->status->value === 'active')
                                        Disetujui {{ $bloodRequest->verified_at->diffForHumans() }}
                                    @elseif ($bloodRequest->status->value === 'need_revision')
                                        Menunggu perbaikan data pemohon
                                    @elseif ($bloodRequest->status->value === 'rejected')
                                        Permohonan ditolak verifikasi
                                    @else
                                        Menunggu pemeriksaan Petugas UDD
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
