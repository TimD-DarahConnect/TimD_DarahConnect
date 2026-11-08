@extends('layouts.app')

@section('title', 'Edit Permohonan ' . $bloodRequest->request_code)

@push('styles')
<style>
    .dc-choice-grid-4 {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 0.55rem;
    }

    .dc-choice-pill {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        min-height: 2.95rem;
        padding: 0.5rem 0.65rem;
        border-radius: var(--dc-radius-md);
        border: 1.5px solid var(--dc-border-strong);
        background: var(--dc-surface);
        color: var(--dc-ink);
        font-weight: 680;
        cursor: pointer;
        text-align: center;
        transition: transform 95ms ease-out, border-color 150ms ease, background-color 150ms ease, color 150ms ease, box-shadow 150ms ease;
        user-select: none;
    }

    .dc-choice-pill:hover {
        border-color: rgba(220, 38, 38, 0.45);
        background: var(--dc-crimson-soft);
    }

    .dc-choice-input:checked + .dc-choice-pill {
        border-color: var(--dc-crimson);
        background: var(--dc-crimson);
        color: #ffffff;
        box-shadow: 0 6px 16px -4px rgba(220, 38, 38, 0.35);
    }

    .dc-dropzone {
        border: 1.5px dashed rgba(15, 23, 42, 0.22);
        border-radius: var(--dc-radius-lg);
        background: var(--dc-surface-sunken);
        padding: 1.4rem;
        text-align: center;
        cursor: pointer;
        transition: border-color 160ms ease, background-color 160ms ease, transform 95ms ease-out;
    }

    .dc-dropzone:hover,
    .dc-dropzone.is-dragover {
        border-color: var(--dc-crimson);
        background: var(--dc-crimson-soft);
    }
</style>
@endpush

@section('content')
@php
    $isDraftStatus = $bloodRequest->status === \App\Enums\BloodRequestStatus::DRAFT;
    $isRevisionStatus = $bloodRequest->status === \App\Enums\BloodRequestStatus::NEED_REVISION;
    $hasExistingReferral = $bloodRequest->hasReferralLetter();

    $selectedBlood = old('blood_type', $bloodRequest->blood_type->value);
    $selectedRhesus = old('rhesus', $bloodRequest->rhesus->value);
    $selectedComponent = old('component_type', $bloodRequest->component_type->value);
    $selectedUrgency = old('urgency_level', $bloodRequest->urgency_level->value);
    $selectedRelationship = old('patient_relationship', $bloodRequest->patient_relationship);
    $selectedFacilityId = (int) old('facility_id', $bloodRequest->facility_id);

    $relationshipOptions = $relationships;
    if ($selectedRelationship && ! in_array($selectedRelationship, $relationshipOptions, true)) {
        array_unshift($relationshipOptions, $selectedRelationship);
    }

    $storedNeededAt = $bloodRequest->needed_at?->format('Y-m-d\TH:i');
    $defaultNeededAt = old('needed_at', $storedNeededAt ?? now()->addHours(12)->format('Y-m-d\TH:i'));
    $isStoredNeededAtPast = $bloodRequest->needed_at !== null && $bloodRequest->needed_at->isPast() && ! old('needed_at');
@endphp

<section class="py-4 py-lg-5">
    <div class="container">
        <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
            <div>
                <nav aria-label="breadcrumb" class="mb-1">
                    <ol class="breadcrumb mb-0 dc-caption">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-secondary">Ruang Kerja</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('emergency-requests.index') }}" class="text-decoration-none text-secondary">Permohonan Saya</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('emergency-requests.show', $bloodRequest) }}" class="text-decoration-none text-secondary dc-tabular">{{ $bloodRequest->request_code }}</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Edit Permohonan</li>
                    </ol>
                </nav>

                <div class="d-flex flex-wrap align-items-center gap-2 mt-2 mb-1">
                    <span class="dc-pill {{ $bloodRequest->status->pillClass() }}">
                        <i class="bi {{ $bloodRequest->status->icon() }}"></i>
                        {{ $bloodRequest->status->label() }}
                    </span>
                    <span class="dc-caption dc-tabular">Kode: <strong>{{ $bloodRequest->request_code }}</strong></span>
                </div>

                <h1 class="dc-title-1 mb-1">Perbarui Rincian Permohonan Darah</h1>
                <p class="dc-caption mb-0">
                    Anda dapat melengkapi draf, menyesuaikan kebutuhan kantong darah, atau mengganti berkas surat rujukan selama permohonan belum disahkan menjadi aktif oleh Petugas UDD.
                </p>
            </div>

            <div>
                <a href="{{ route('emergency-requests.show', $bloodRequest) }}" class="btn btn-dc-secondary">
                    <i class="bi bi-arrow-left"></i>
                    Kembali ke Rincian
                </a>
            </div>
        </div>

        @if ($bloodRequest->verification_notes)
            <div class="dc-card p-4 mb-4 border-warning border-opacity-50 bg-warning-subtle bg-opacity-25" role="note">
                <div class="d-flex align-items-start gap-3">
                    <i class="bi bi-clipboard2-pulse-fill text-warning fs-4"></i>
                    <div>
                        <div class="dc-eyebrow text-warning-emphasis">
                            {{ $isRevisionStatus ? 'Instruksi Perbaikan dari Petugas UDD' : 'Catatan Peninjauan Medis Terakhir' }}
                        </div>
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

        @if ($errors->any())
            <div class="dc-card p-3 mb-4 border-danger-subtle bg-danger-subtle text-danger-emphasis" role="alert">
                <div class="fw-semibold small mb-1">
                    <i class="bi bi-exclamation-circle-fill me-1"></i>Terdapat beberapa data yang perlu diperbaiki:
                </div>
                <ul class="mb-0 ps-3 small">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('emergency-requests.update', $bloodRequest) }}" method="POST" enctype="multipart/form-data" id="editEmergencyRequestForm" novalidate>
            @csrf
            @method('PUT')

            <div class="row g-4 g-xl-5 align-items-start">
                <!-- Left Column: Clinical Form Sections -->
                <div class="col-lg-8">
                    <!-- Section 1: Data Pasien & Klinis -->
                    <div class="dc-card p-4 p-md-5 mb-4">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div>
                                <div class="dc-eyebrow">Bagian 01</div>
                                <h2 class="dc-title-2 mb-0 mt-1">Identitas &amp; Kondisi Klinis Pasien</h2>
                            </div>
                            <i class="bi bi-person-vcard text-danger fs-3"></i>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-8">
                                <label for="patient_name" class="form-label">Nama Lengkap Pasien <span class="text-danger">*</span></label>
                                <input
                                    type="text"
                                    id="patient_name"
                                    name="patient_name"
                                    value="{{ old('patient_name', $bloodRequest->patient_name) }}"
                                    class="form-control @error('patient_name') is-invalid @enderror"
                                    placeholder="Contoh: Tn. Suryadi Kartawijaya"
                                    required
                                >
                                @error('patient_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4">
                                <label for="patient_age" class="form-label">Usia Pasien (Tahun) <span class="text-danger">*</span></label>
                                <input
                                    type="number"
                                    id="patient_age"
                                    name="patient_age"
                                    value="{{ old('patient_age', $bloodRequest->patient_age) }}"
                                    min="0"
                                    max="120"
                                    class="form-control dc-tabular @error('patient_age') is-invalid @enderror"
                                    placeholder="54"
                                >
                                @error('patient_age')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4">
                                <label for="patient_gender" class="form-label">Jenis Kelamin Pasien <span class="text-danger">*</span></label>
                                <select id="patient_gender" name="patient_gender" class="form-select @error('patient_gender') is-invalid @enderror">
                                    <option value="">Pilih...</option>
                                    <option value="L" @selected(old('patient_gender', $bloodRequest->patient_gender) === 'L')>Laki-laki</option>
                                    <option value="P" @selected(old('patient_gender', $bloodRequest->patient_gender) === 'P')>Perempuan</option>
                                </select>
                                @error('patient_gender')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-8">
                                <label for="patient_relationship" class="form-label">Hubungan Pemohon dengan Pasien <span class="text-danger">*</span></label>
                                <select id="patient_relationship" name="patient_relationship" class="form-select @error('patient_relationship') is-invalid @enderror">
                                    @foreach ($relationshipOptions as $rel)
                                        <option value="{{ $rel }}" @selected($selectedRelationship === $rel)>{{ $rel }}</option>
                                    @endforeach
                                </select>
                                @error('patient_relationship')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-8">
                                <label for="clinical_diagnosis" class="form-label">Diagnosis Klinis / Indikasi Transfusi <span class="text-danger">*</span></label>
                                <input
                                    type="text"
                                    id="clinical_diagnosis"
                                    name="clinical_diagnosis"
                                    value="{{ old('clinical_diagnosis', $bloodRequest->clinical_diagnosis) }}"
                                    class="form-control @error('clinical_diagnosis') is-invalid @enderror"
                                    placeholder="Contoh: Anemia Gravis e.c. Perdarahan Saluran Cerna"
                                >
                                @error('clinical_diagnosis')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4">
                                <label for="hemoglobin_level" class="form-label">Kadar Hemoglobin (g/dL) <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input
                                        type="number"
                                        step="0.1"
                                        min="1.5"
                                        max="22.0"
                                        id="hemoglobin_level"
                                        name="hemoglobin_level"
                                        value="{{ old('hemoglobin_level', $bloodRequest->hemoglobin_level) }}"
                                        class="form-control dc-tabular @error('hemoglobin_level') is-invalid @enderror"
                                        placeholder="6.2"
                                    >
                                    <span class="input-group-text bg-light dc-caption">g/dL</span>
                                </div>
                                @error('hemoglobin_level')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @else
                                    <div class="dc-caption mt-1" id="hbInlineHint">Sesuai hasil lab terakhir.</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Section 2: Kebutuhan Komponen Darah & Faskes Rujukan -->
                    <div class="dc-card p-4 p-md-5 mb-4">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div>
                                <div class="dc-eyebrow">Bagian 02</div>
                                <h2 class="dc-title-2 mb-0 mt-1">Spesifikasi Darah &amp; Rumah Sakit Rujukan</h2>
                            </div>
                            <i class="bi bi-droplet-half text-danger fs-3"></i>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-7">
                                <label class="form-label">Golongan Darah ABO Pasien <span class="text-danger">*</span></label>
                                <div class="dc-choice-grid-4" role="radiogroup" aria-label="Golongan Darah ABO Pasien">
                                    @foreach ($bloodTypes as $bt)
                                        <div>
                                            <input
                                                type="radio"
                                                class="visually-hidden dc-choice-input"
                                                name="blood_type"
                                                id="bt_{{ $bt->value }}"
                                                value="{{ $bt->value }}"
                                                @checked($selectedBlood === $bt->value)
                                            >
                                            <label class="dc-choice-pill dc-pressable" for="bt_{{ $bt->value }}">
                                                {{ $bt->value }}
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <div class="col-md-5">
                                <label class="form-label">Faktor Rhesus <span class="text-danger">*</span></label>
                                <div class="row g-2" role="radiogroup" aria-label="Faktor Rhesus Pasien">
                                    @foreach ($rhesusTypes as $rh)
                                        <div class="col-6">
                                            <input
                                                type="radio"
                                                class="visually-hidden dc-choice-input"
                                                name="rhesus"
                                                id="rh_{{ $rh->name }}"
                                                value="{{ $rh->value }}"
                                                @checked($selectedRhesus === $rh->value)
                                            >
                                            <label class="dc-choice-pill dc-pressable" for="rh_{{ $rh->name }}">
                                                Rh {{ $rh->value }}
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Jenis Komponen Darah <span class="text-danger">*</span></label>
                                <div class="row g-2" role="radiogroup" aria-label="Jenis Komponen Darah">
                                    @foreach ($components as $comp)
                                        <div class="col-6 col-md-3">
                                            <input
                                                type="radio"
                                                class="visually-hidden dc-choice-input"
                                                name="component_type"
                                                id="comp_{{ $comp->value }}"
                                                value="{{ $comp->value }}"
                                                data-hint="{{ $comp->clinicalHint() }}"
                                                @checked($selectedComponent === $comp->value)
                                            >
                                            <label class="dc-choice-pill dc-pressable py-2" for="comp_{{ $comp->value }}">
                                                <span>{{ $comp->value }}</span>
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                                <div class="dc-caption mt-2" id="componentHintText">
                                    {{ \App\Enums\BloodComponent::tryFrom($selectedComponent)?->clinicalHint() }}
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label for="bags_needed" class="form-label">Jumlah Kantong <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input
                                        type="number"
                                        id="bags_needed"
                                        name="bags_needed"
                                        value="{{ old('bags_needed', $bloodRequest->bags_needed) }}"
                                        min="1"
                                        max="20"
                                        class="form-control dc-tabular @error('bags_needed') is-invalid @enderror"
                                        required
                                    >
                                    <span class="input-group-text bg-light dc-caption">Kantong</span>
                                </div>
                                @error('bags_needed')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4">
                                <label for="urgency_level" class="form-label">Tingkat Urgensi <span class="text-danger">*</span></label>
                                <select id="urgency_level" name="urgency_level" class="form-select @error('urgency_level') is-invalid @enderror">
                                    @foreach ($urgencyLevels as $urg)
                                        <option value="{{ $urg->value }}" @selected($selectedUrgency === $urg->value)>
                                            {{ $urg->label() }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label for="needed_at" class="form-label">Batas Waktu Dibutuhkan <span class="text-danger">*</span></label>
                                <input
                                    type="datetime-local"
                                    id="needed_at"
                                    name="needed_at"
                                    value="{{ $defaultNeededAt }}"
                                    class="form-control dc-tabular @error('needed_at') is-invalid @enderror"
                                >
                                @error('needed_at')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @elseif ($isStoredNeededAtPast)
                                    <div class="dc-caption text-warning-emphasis fw-medium mt-1">
                                        <i class="bi bi-clock-history"></i> Estimasi sebelumnya telah terlewati — perbarui waktu target sebelum mengirim ke UDD.
                                    </div>
                                @enderror
                            </div>

                            <div class="col-12">
                                <label for="facility_id" class="form-label">Rumah Sakit / Fasilitas Kesehatan Rujukan <span class="text-danger">*</span></label>
                                <select id="facility_id" name="facility_id" class="form-select @error('facility_id') is-invalid @enderror" required>
                                    <option value="">Pilih Rumah Sakit tempat pasien dirawat...</option>
                                    @foreach ($facilitiesByRegion as $regionLabel => $facilities)
                                        <optgroup label="{{ $regionLabel }}">
                                            @foreach ($facilities as $facility)
                                                <option value="{{ $facility->id }}" data-name="{{ $facility->name }}" @selected($selectedFacilityId === $facility->id)>
                                                    {{ $facility->name }} ({{ $facility->type === 'udd' ? 'UDD PMI' : 'RS Rujukan' }})
                                                </option>
                                            @endforeach
                                        </optgroup>
                                    @endforeach
                                </select>
                                @error('facility_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-7">
                                <label for="ward_room" class="form-label">Ruang Rawat / Bangsal / Instalasi <span class="text-danger">*</span></label>
                                <input
                                    type="text"
                                    id="ward_room"
                                    name="ward_room"
                                    value="{{ old('ward_room', $bloodRequest->ward_room) }}"
                                    class="form-control @error('ward_room') is-invalid @enderror"
                                    placeholder="Contoh: Gedung Kemuning Lt. 3 - Ruang HCU Bed 04"
                                >
                                @error('ward_room')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-5">
                                <label for="medical_record_number" class="form-label">No. Rekam Medis <span class="text-muted fw-normal">(Opsional)</span></label>
                                <input
                                    type="text"
                                    id="medical_record_number"
                                    name="medical_record_number"
                                    value="{{ old('medical_record_number', $bloodRequest->medical_record_number) }}"
                                    class="form-control dc-tabular @error('medical_record_number') is-invalid @enderror"
                                    placeholder="RM-2026-XXXXX"
                                >
                            </div>
                        </div>
                    </div>

                    <!-- Section 3: Berkas Verifikasi Medis -->
                    <div class="dc-card p-4 p-md-5">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div>
                                <div class="dc-eyebrow">Bagian 03</div>
                                <h2 class="dc-title-2 mb-0 mt-1">Berkas Verifikasi Medis Resmi</h2>
                            </div>
                            <i class="bi bi-file-earmark-medical-fill text-danger fs-3"></i>
                        </div>

                        <div class="row g-3">
                            <div class="col-12">
                                <label for="doctor_name" class="form-label">Nama Dokter Penanggung Jawab (DPJP) / Perujuk <span class="text-danger">*</span></label>
                                <input
                                    type="text"
                                    id="doctor_name"
                                    name="doctor_name"
                                    value="{{ old('doctor_name', $bloodRequest->doctor_name) }}"
                                    class="form-control @error('doctor_name') is-invalid @enderror"
                                    placeholder="Contoh: dr. Arief Budiman, Sp.PD"
                                >
                                @error('doctor_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            @if ($hasExistingReferral)
                                <div class="col-12">
                                    <div class="p-3 rounded-4 bg-light border d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="rounded-3 bg-success-subtle text-success d-inline-flex align-items-center justify-content-center flex-shrink-0" style="width: 2.65rem; height: 2.65rem;">
                                                <i class="bi bi-file-earmark-check-fill fs-5"></i>
                                            </div>
                                            <div>
                                                <div class="dc-eyebrow text-success">Berkas Rujukan Saat Ini Tersimpan</div>
                                                <div class="fw-semibold small text-dark text-break">
                                                    {{ $bloodRequest->referral_letter_original_name ?: 'surat-rujukan-dokter.pdf' }}
                                                </div>
                                                <div class="dc-caption dc-tabular">
                                                    @if ($bloodRequest->referral_letter_size)
                                                        {{ number_format($bloodRequest->referral_letter_size / 1024, 1) }} KB &middot;
                                                    @endif
                                                    Biarkan kolom unggah di bawah kosong jika tidak ingin mengganti berkas ini.
                                                </div>
                                            </div>
                                        </div>
                                        <a
                                            href="{{ route('emergency-requests.referral-letter', $bloodRequest) }}"
                                            target="_blank"
                                            rel="noopener"
                                            class="btn btn-dc-secondary btn-sm flex-shrink-0"
                                        >
                                            <i class="bi bi-eye"></i>
                                            Lihat Berkas
                                        </a>
                                    </div>
                                </div>
                            @endif

                            <div class="col-12">
                                <label for="referral_letter" class="form-label">
                                    @if ($hasExistingReferral)
                                        Ganti Surat Rujukan Dokter / Formulir BDRS <span class="text-muted fw-normal">(Opsional)</span>
                                    @else
                                        Unggah Surat Rujukan Dokter / Formulir Permintaan BDRS <span class="text-danger">*</span>
                                    @endif
                                </label>
                                <div class="dc-dropzone dc-pressable" id="referralDropzone" role="button" tabindex="0" aria-label="Pilih berkas surat rujukan dokter">
                                    <input
                                        type="file"
                                        id="referral_letter"
                                        name="referral_letter"
                                        accept=".pdf,.jpg,.jpeg,.png"
                                        class="visually-hidden"
                                    >
                                    <div id="dropzonePrompt">
                                        <i class="bi bi-cloud-arrow-up-fill text-danger fs-2 d-block mb-2"></i>
                                        <div class="fw-semibold small">
                                            {{ $hasExistingReferral ? 'Klik atau seret berkas baru untuk menggantikan lampiran sebelumnya' : 'Klik atau seret berkas surat rujukan dokter ke area ini' }}
                                        </div>
                                        <div class="dc-caption mt-1">Format didukung: PDF, JPG, PNG &middot; Maksimal 5 MB (Terenkripsi &amp; hanya dilihat Petugas Medis)</div>
                                    </div>
                                    <div id="dropzoneSelected" class="d-none">
                                        <span class="dc-pill dc-pill-success mb-2">
                                            <i class="bi bi-check-circle-fill"></i> Berkas Pengganti Siap Diunggah
                                        </span>
                                        <div class="fw-semibold small text-dark" id="selectedFileName">-</div>
                                        <div class="dc-caption dc-tabular" id="selectedFileSize">-</div>
                                    </div>
                                </div>
                                <div class="text-danger small mt-1 d-none" id="fileClientError"></div>
                                @error('referral_letter')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12">
                                <label for="additional_notes" class="form-label">Catatan Klinis / Instruksi Tambahan <span class="text-muted fw-normal">(Opsional)</span></label>
                                <textarea
                                    id="additional_notes"
                                    name="additional_notes"
                                    rows="3"
                                    class="form-control @error('additional_notes') is-invalid @enderror"
                                    placeholder="Informasi tambahan untuk petugas UDD atau pendonor (misal: prosedur BDRS rumah sakit rujukan)"
                                >{{ old('additional_notes', $bloodRequest->additional_notes) }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Sticky Live Clinical Preview & Contextual Submit Controls -->
                <div class="col-lg-4">
                    <div class="dc-card p-4 position-sticky" style="top: 5.5rem;">
                        <div class="d-flex align-items-center justify-content-between gap-2 pb-3 mb-3 border-bottom">
                            <div>
                                <div class="dc-eyebrow">Ringkasan Pembaruan</div>
                                <div class="fw-semibold text-truncate" style="max-width: 12.5rem;" id="previewPatient">
                                    {{ old('patient_name', $bloodRequest->patient_name) ?: 'Nama Pasien' }}
                                </div>
                            </div>
                            <span class="dc-blood-badge" id="previewBloodGroup">{{ $selectedBlood }}{{ $selectedRhesus }}</span>
                        </div>

                        <div class="vstack gap-2 mb-4 small">
                            <div class="d-flex justify-content-between">
                                <span class="text-secondary">Komponen &amp; Kuantitas</span>
                                <strong class="dc-tabular" id="previewBagsComponent">{{ old('bags_needed', $bloodRequest->bags_needed) }} Kantong {{ $selectedComponent }}</strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-secondary">Kadar Hemoglobin</span>
                                <strong class="dc-tabular" id="previewHb">
                                    {{ old('hemoglobin_level', $bloodRequest->hemoglobin_level) !== null && old('hemoglobin_level', $bloodRequest->hemoglobin_level) !== '' ? old('hemoglobin_level', $bloodRequest->hemoglobin_level).' g/dL' : 'Belum diisi' }}
                                </strong>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-secondary">Prioritas Medis</span>
                                <span class="dc-pill {{ $bloodRequest->urgency_level->pillClass() }}" id="previewUrgency">
                                    {{ $bloodRequest->urgency_level->label() }}
                                </span>
                            </div>
                            <div class="pt-2 border-top">
                                <div class="dc-caption mb-1">Fasilitas Kesehatan Rujukan:</div>
                                <div class="fw-semibold" id="previewFacility">{{ $bloodRequest->facility?->name ?? 'Belum dipilih' }}</div>
                            </div>
                        </div>

                        <div class="p-3 rounded-4 bg-light border mb-4 dc-caption">
                            <i class="bi bi-lock-fill text-danger me-1"></i>
                            <strong>Aturan Kunci Verifikasi:</strong> Perubahan hanya dapat dilakukan sebelum Petugas UDD menerbitkan persetujuan aktif pada permohonan ini.
                        </div>

                        <div class="d-grid gap-2">
                            @if ($isDraftStatus)
                                <button type="submit" name="submit_action" value="submit" class="btn btn-dc-primary btn-lg">
                                    <i class="bi bi-send-check-fill"></i>
                                    Ajukan ke Verifikasi UDD
                                </button>
                                <button type="submit" name="submit_action" value="draft" class="btn btn-dc-secondary">
                                    <i class="bi bi-floppy"></i>
                                    Simpan Perubahan Draf
                                </button>
                            @elseif ($isRevisionStatus)
                                <button type="submit" name="submit_action" value="submit" class="btn btn-dc-primary btn-lg">
                                    <i class="bi bi-arrow-repeat"></i>
                                    Simpan Perbaikan &amp; Kirim Ulang
                                </button>
                            @else
                                <button type="submit" name="submit_action" value="submit" class="btn btn-dc-primary btn-lg">
                                    <i class="bi bi-check2-circle"></i>
                                    Simpan Pembaruan Rincian
                                </button>
                            @endif

                            <a href="{{ route('emergency-requests.show', $bloodRequest) }}" class="btn btn-dc-soft">
                                Batal &amp; Kembali
                            </a>
                        </div>

                        @if ($isDraftStatus)
                            <div class="dc-caption text-center mt-2">
                                Simpan perubahan draf tetap dapat digunakan walau berkas surat rujukan belum dilampirkan.
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </form>
    </div>
</section>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const patientInput = document.getElementById('patient_name');
        const bagsInput = document.getElementById('bags_needed');
        const hbInput = document.getElementById('hemoglobin_level');
        const urgencySelect = document.getElementById('urgency_level');
        const facilitySelect = document.getElementById('facility_id');

        const previewPatient = document.getElementById('previewPatient');
        const previewBloodGroup = document.getElementById('previewBloodGroup');
        const previewBagsComponent = document.getElementById('previewBagsComponent');
        const previewHb = document.getElementById('previewHb');
        const previewUrgency = document.getElementById('previewUrgency');
        const previewFacility = document.getElementById('previewFacility');
        const hbInlineHint = document.getElementById('hbInlineHint');
        const componentHintText = document.getElementById('componentHintText');

        const syncSummary = () => {
            const bt = document.querySelector('input[name="blood_type"]:checked')?.value || 'O';
            const rh = document.querySelector('input[name="rhesus"]:checked')?.value || '+';
            const compRadio = document.querySelector('input[name="component_type"]:checked');
            const comp = compRadio?.value || 'PRC';
            const bags = bagsInput?.value || '1';

            if (previewBloodGroup) previewBloodGroup.textContent = `${bt}${rh}`;
            if (previewPatient) previewPatient.textContent = patientInput?.value?.trim() || 'Nama Pasien';
            if (previewBagsComponent) previewBagsComponent.textContent = `${bags} Kantong ${comp}`;
            if (componentHintText && compRadio?.dataset.hint) {
                componentHintText.textContent = compRadio.dataset.hint;
            }

            const hbVal = parseFloat(hbInput?.value || '');
            if (!Number.isNaN(hbVal)) {
                if (previewHb) previewHb.textContent = `${hbVal.toFixed(1)} g/dL`;
                if (hbInlineHint) {
                    if (hbVal < 7.0) {
                        hbInlineHint.textContent = 'Indikasi Anemia Berat / Kritis (< 7.0 g/dL).';
                        hbInlineHint.className = 'dc-caption mt-1 text-danger fw-semibold';
                    } else if (hbVal < 10.0) {
                        hbInlineHint.textContent = 'Indikasi Anemia Sedang (7.0 – 9.9 g/dL).';
                        hbInlineHint.className = 'dc-caption mt-1 text-warning-emphasis fw-semibold';
                    } else {
                        hbInlineHint.textContent = 'Kadar pemeliharaan / persiapan tindakan medis.';
                        hbInlineHint.className = 'dc-caption mt-1 text-secondary';
                    }
                }
            } else {
                if (previewHb) previewHb.textContent = 'Belum diisi';
            }

            if (urgencySelect && previewUrgency) {
                const val = urgencySelect.value;
                previewUrgency.textContent = urgencySelect.options[urgencySelect.selectedIndex]?.text?.trim() || val;
                previewUrgency.className = 'dc-pill ' + (
                    val === 'critical' ? 'dc-pill-critical' : (val === 'urgent' ? 'dc-pill-warning' : 'dc-pill-neutral')
                );
            }

            if (facilitySelect && previewFacility) {
                const selectedOpt = facilitySelect.options[facilitySelect.selectedIndex];
                previewFacility.textContent = selectedOpt?.dataset?.name || (facilitySelect.value ? selectedOpt.text : 'Belum dipilih');
            }
        };

        [patientInput, bagsInput, hbInput, urgencySelect, facilitySelect].forEach((el) => {
            el?.addEventListener('input', syncSummary);
            el?.addEventListener('change', syncSummary);
        });

        document.querySelectorAll('input[name="blood_type"], input[name="rhesus"], input[name="component_type"]').forEach((el) => {
            el.addEventListener('change', syncSummary);
        });

        syncSummary();

        const dropzone = document.getElementById('referralDropzone');
        const fileInput = document.getElementById('referral_letter');
        const promptBox = document.getElementById('dropzonePrompt');
        const selectedBox = document.getElementById('dropzoneSelected');
        const fileNameEl = document.getElementById('selectedFileName');
        const fileSizeEl = document.getElementById('selectedFileSize');
        const fileErrorEl = document.getElementById('fileClientError');

        const handleSelectedFile = (file) => {
            if (!file) return;
            fileErrorEl?.classList.add('d-none');

            const maxBytes = 5 * 1024 * 1024;
            const allowedExt = ['pdf', 'jpg', 'jpeg', 'png'];
            const ext = (file.name.split('.').pop() || '').toLowerCase();

            if (!allowedExt.includes(ext)) {
                if (fileErrorEl) {
                    fileErrorEl.textContent = 'Format berkas harus PDF, JPG, JPEG, atau PNG.';
                    fileErrorEl.classList.remove('d-none');
                }
                fileInput.value = '';
                return;
            }

            if (file.size > maxBytes) {
                if (fileErrorEl) {
                    fileErrorEl.textContent = 'Ukuran berkas melebihi batas maksimal 5 MB.';
                    fileErrorEl.classList.remove('d-none');
                }
                fileInput.value = '';
                return;
            }

            promptBox?.classList.add('d-none');
            selectedBox?.classList.remove('d-none');
            if (fileNameEl) fileNameEl.textContent = file.name;
            if (fileSizeEl) fileSizeEl.textContent = `${(file.size / 1024).toFixed(1)} KB`;
        };

        dropzone?.addEventListener('click', (e) => {
            if (e.target !== fileInput) fileInput?.click();
        });

        dropzone?.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                fileInput?.click();
            }
        });

        fileInput?.addEventListener('change', () => {
            if (fileInput.files?.[0]) handleSelectedFile(fileInput.files[0]);
        });

        ['dragenter', 'dragover'].forEach((evt) => {
            dropzone?.addEventListener(evt, (e) => {
                e.preventDefault();
                dropzone.classList.add('is-dragover');
            });
        });

        ['dragleave', 'drop'].forEach((evt) => {
            dropzone?.addEventListener(evt, (e) => {
                e.preventDefault();
                dropzone.classList.remove('is-dragover');
            });
        });

        dropzone?.addEventListener('drop', (e) => {
            const dropped = e.dataTransfer?.files?.[0];
            if (dropped && fileInput) {
                const dt = new DataTransfer();
                dt.items.add(dropped);
                fileInput.files = dt.files;
                handleSelectedFile(dropped);
            }
        });
    });
</script>
@endpush
