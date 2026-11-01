@extends('layouts.app')

@section('title', 'Antrean Verifikasi Permohonan Medis UDD')

@section('content')
<section class="py-4 py-lg-5">
    <div class="container">
        <!-- Header & Operational Context -->
        <div class="d-flex flex-column flex-lg-row align-items-lg-end justify-content-between gap-3 mb-4">
            <div>
                <nav aria-label="breadcrumb" class="mb-1">
                    <ol class="breadcrumb mb-0 dc-caption">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-secondary">Ruang Kerja</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Antrean Verifikasi Medis</li>
                    </ol>
                </nav>
                <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                    <span class="dc-eyebrow">Triase Klinis Petugas UDD</span>
                    @if (auth()->user()->facility)
                        <span class="dc-pill dc-pill-neutral">
                            <i class="bi bi-building-check text-danger"></i>
                            {{ auth()->user()->facility->name }}
                        </span>
                    @endif
                </div>
                <h1 class="dc-title-1 mb-1">Antrean Permohonan Darah Darurat</h1>
                <p class="dc-caption mb-0">
                    Diurutkan otomatis berdasarkan tingkat urgensi klinis, kadar hemoglobin terendah, dan batas waktu transfusi pasien.
                </p>
            </div>

            <a href="{{ route('dashboard') }}" class="btn btn-dc-secondary">
                <i class="bi bi-grid-1x2"></i>
                Ruang Kerja UDD
            </a>
        </div>

        <!-- Clinical Triage KPI Cards -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="dc-card p-3 h-100">
                    <div class="d-flex align-items-center justify-content-between">
                        <span class="dc-caption">Menunggu Tinjauan</span>
                        <i class="bi bi-hourglass-split text-warning"></i>
                    </div>
                    <div class="dc-title-2 dc-tabular text-warning mb-0 mt-1">{{ $summary['pending'] }}</div>
                    <div class="dc-caption mt-1">Antrean belum diputuskan</div>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <div class="dc-card p-3 h-100 {{ $summary['critical_pending'] > 0 ? 'border-danger border-opacity-25' : '' }}">
                    <div class="d-flex align-items-center justify-content-between">
                        <span class="dc-caption">Prioritas Kritis / Hb &lt; 7</span>
                        <i class="bi bi-heart-pulse-fill text-danger"></i>
                    </div>
                    <div class="dc-title-2 dc-tabular text-danger mb-0 mt-1">{{ $summary['critical_pending'] }}</div>
                    <div class="dc-caption mt-1">Butuh triase segera</div>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <div class="dc-card p-3 h-100">
                    <div class="d-flex align-items-center justify-content-between">
                        <span class="dc-caption">Aktif Dipublikasikan</span>
                        <i class="bi bi-broadcast-pin text-success"></i>
                    </div>
                    <div class="dc-title-2 dc-tabular text-success mb-0 mt-1">{{ $summary['active_published'] }}</div>
                    <div class="dc-caption mt-1">Terbuka bagi pendonor</div>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <div class="dc-card p-3 h-100">
                    <div class="d-flex align-items-center justify-content-between">
                        <span class="dc-caption">Ditinjau Hari Ini</span>
                        <i class="bi bi-shield-check text-primary"></i>
                    </div>
                    <div class="dc-title-2 dc-tabular mb-0 mt-1">{{ $summary['reviewed_today'] }}</div>
                    <div class="dc-caption mt-1">Rekap verifikasi harian</div>
                </div>
            </div>
        </div>

        <!-- Translucent Filter & Triage Bar (§12 Materials) -->
        <div class="dc-glass-bar p-3 mb-4">
            <div class="d-flex flex-column flex-xl-row align-items-xl-center justify-content-between gap-3">
                <div class="d-flex flex-wrap align-items-center gap-1" role="tablist" aria-label="Kategori Antrean">
                    @php
                        $tabs = [
                            'pending' => ['label' => 'Menunggu Tinjauan', 'count' => $summary['pending']],
                            'reviewed' => ['label' => 'Telah Ditinjau', 'count' => null],
                            'all' => ['label' => 'Semua Masuk', 'count' => null],
                        ];
                    @endphp
                    @foreach ($tabs as $tabKey => $tabMeta)
                        <a
                            href="{{ route('verifications.index', array_filter([
                                'queue' => $tabKey === 'pending' ? null : $tabKey,
                                'urgency' => $urgencyFilter ?: null,
                                'blood_type' => $bloodTypeFilter ?: null,
                                'q' => $searchQuery ?: null,
                            ])) }}"
                            class="dc-nav-pill {{ $queueTab === $tabKey ? 'active' : '' }} d-inline-flex align-items-center gap-2"
                        >
                            <span>{{ $tabMeta['label'] }}</span>
                            @if ($tabMeta['count'] !== null)
                                <span class="badge rounded-pill {{ $queueTab === $tabKey ? 'text-bg-danger' : 'text-bg-secondary bg-opacity-25 text-dark' }} dc-tabular">
                                    {{ $tabMeta['count'] }}
                                </span>
                            @endif
                        </a>
                    @endforeach
                </div>

                <form method="GET" action="{{ route('verifications.index') }}" class="row g-2 align-items-center flex-grow-1 justify-content-xl-end">
                    @if ($queueTab !== 'pending')
                        <input type="hidden" name="queue" value="{{ $queueTab }}">
                    @endif

                    <div class="col-12 col-sm-5 col-xl-4">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white border-end-0 text-secondary">
                                <i class="bi bi-search"></i>
                            </span>
                            <input
                                type="search"
                                name="q"
                                value="{{ $searchQuery }}"
                                class="form-control form-control-sm border-start-0 ps-0"
                                placeholder="Kode, pasien, diagnosis, RS..."
                                aria-label="Cari permohonan"
                            >
                        </div>
                    </div>

                    <div class="col-6 col-sm-3 col-xl-3">
                        <select name="urgency" class="form-select form-select-sm" aria-label="Filter tingkat urgensi">
                            <option value="">Semua Urgensi</option>
                            @foreach ($urgencyOptions as $option)
                                <option value="{{ $option->value }}" @selected($urgencyFilter === $option->value)>
                                    {{ $option->label() }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-6 col-sm-2 col-xl-2">
                        <select name="blood_type" class="form-select form-select-sm" aria-label="Filter golongan darah">
                            <option value="">Gol. ABO</option>
                            @foreach ($bloodTypes as $bt)
                                <option value="{{ $bt->value }}" @selected($bloodTypeFilter === $bt->value)>
                                    Gol. {{ $bt->value }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-12 col-sm-2 col-xl-auto d-flex gap-2">
                        <button type="submit" class="btn btn-dc-primary btn-sm flex-grow-1">
                            Saring
                        </button>
                        @if ($urgencyFilter !== '' || $bloodTypeFilter !== '' || $searchQuery !== '')
                            <a href="{{ route('verifications.index', $queueTab === 'pending' ? [] : ['queue' => $queueTab]) }}" class="btn btn-dc-secondary btn-sm" title="Reset Filter">
                                <i class="bi bi-arrow-counterclockwise"></i>
                            </a>
                        @endif
                    </div>
                </form>
            </div>
        </div>

        <!-- Queue Cards List -->
        <div class="vstack gap-3">
            @forelse ($requests as $item)
                @php
                    $isSevereHb = $item->hemoglobin_level !== null && $item->hemoglobin_level < 7.0;
                    $docMime = strtolower((string) $item->referral_letter_mime);
                    $docType = str_contains($docMime, 'pdf') ? 'pdf' : (str_starts_with($docMime, 'image/') ? 'image' : 'other');
                @endphp
                <article class="dc-card p-4 {{ $item->urgency_level->value === 'critical' && $item->status->value === 'pending_verification' ? 'border-danger border-opacity-25' : '' }}">
                    <div class="row align-items-center g-3">
                        <!-- Clinical Summary -->
                        <div class="col-lg-7">
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                <span class="dc-pill {{ $item->urgency_level->pillClass() }}">
                                    <i class="bi bi-exclamation-diamond-fill"></i>
                                    {{ $item->urgency_level->label() }}
                                </span>
                                <span class="dc-pill {{ $item->status->pillClass() }}">
                                    <i class="bi {{ $item->status->icon() }}"></i>
                                    {{ $item->status->label() }}
                                </span>
                                <span class="dc-caption dc-tabular fw-semibold">{{ $item->request_code }}</span>
                                @if ($item->submitted_at)
                                    <span class="dc-caption">
                                        &middot; Masuk {{ $item->submitted_at->diffForHumans() }}
                                    </span>
                                @endif
                            </div>

                            <div class="d-flex align-items-start gap-3">
                                <span class="dc-blood-badge flex-shrink-0">{{ $item->blood_group_label }}</span>
                                <div>
                                    <h2 class="dc-title-3 mb-1">
                                        <a href="{{ route('verifications.show', $item) }}" class="text-decoration-none text-dark">
                                            {{ $item->patient_name }}
                                        </a>
                                        @if ($item->patient_age !== null)
                                            <span class="dc-caption fw-normal">({{ $item->patient_age }} thn{{ $item->patient_gender ? ', '.$item->patient_gender : '' }})</span>
                                        @endif
                                    </h2>

                                    <div class="dc-caption mb-1">
                                        <i class="bi bi-hospital me-1 text-danger"></i>
                                        <strong class="text-dark">{{ $item->facility?->name }}</strong>
                                        @if ($item->ward_room)
                                            &middot; {{ $item->ward_room }}
                                        @endif
                                    </div>

                                    <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
                                        @if ($item->hemoglobin_level !== null)
                                            <span class="dc-pill {{ $isSevereHb ? 'dc-pill-critical' : 'dc-pill-neutral' }} dc-tabular">
                                                <i class="bi bi-droplet-half"></i>
                                                Hb {{ number_format($item->hemoglobin_level, 1) }} g/dL
                                            </span>
                                        @endif

                                        @if ($item->clinical_diagnosis)
                                            <span class="dc-caption text-dark">
                                                <strong>Diagnosis:</strong> {{ \Illuminate\Support\Str::limit($item->clinical_diagnosis, 64) }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Target, Medical Attachment & Triage Actions -->
                        <div class="col-lg-5">
                            <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-lg-end gap-3">
                                <div class="text-sm-end">
                                    <div class="fw-bold text-dark dc-tabular">
                                        {{ $item->bags_needed }} Kantong {{ $item->component_type->value }}
                                    </div>
                                    <div class="dc-caption">
                                        @if ($item->needed_at)
                                            Target: <span class="dc-tabular fw-medium text-dark">{{ $item->needed_at->translatedFormat('d M, H:i') }} WIB</span>
                                        @else
                                            DPJP: {{ $item->doctor_name ?: '-' }}
                                        @endif
                                    </div>
                                    <div class="mt-1">
                                        @if ($item->hasReferralLetter())
                                            <span class="dc-pill dc-pill-success py-1 px-2" style="font-size: 0.72rem;">
                                                <i class="bi bi-file-earmark-medical-fill"></i>
                                                Surat Rujukan Terlampir
                                            </span>
                                        @else
                                            <span class="dc-pill dc-pill-warning py-1 px-2" style="font-size: 0.72rem;">
                                                <i class="bi bi-exclamation-circle"></i>
                                                Tanpa Lampiran
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                <div class="d-flex flex-wrap flex-sm-nowrap gap-2">
                                    @if ($item->hasReferralLetter())
                                        <button
                                            type="button"
                                            class="btn btn-dc-secondary btn-sm js-quick-preview-btn"
                                            data-dc-open-sheet="quickReferralSheet"
                                            data-doc-url="{{ route('verifications.document', $item) }}"
                                            data-detail-url="{{ route('verifications.show', $item) }}"
                                            data-doc-type="{{ $docType }}"
                                            data-doc-name="{{ $item->referral_letter_original_name ?: 'Surat Rujukan Dokter' }}"
                                            data-patient-label="{{ $item->request_code }} — {{ $item->patient_name }} ({{ $item->blood_group_label }})"
                                            data-doctor-label="{{ $item->doctor_name ?: 'Dokter Penanggung Jawab' }} · {{ $item->facility?->name }}"
                                        >
                                            <i class="bi bi-eye"></i>
                                            Berkas
                                        </button>
                                    @endif

                                    <a href="{{ route('verifications.show', $item) }}" class="btn btn-dc-primary btn-sm">
                                        <i class="bi bi-clipboard2-pulse"></i>
                                        Tinjau Kasus
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </article>
            @empty
                <div class="dc-card p-5 text-center">
                    <div class="mx-auto mb-3 d-inline-flex align-items-center justify-content-center rounded-circle bg-success-subtle text-success" style="width: 3.5rem; height: 3.5rem;">
                        <i class="bi bi-check2-all fs-4"></i>
                    </div>
                    <h2 class="dc-title-3 mb-1">Tidak Ada Permohonan pada Kategori Ini</h2>
                    <p class="dc-caption mb-3 mx-auto" style="max-width: 30rem;">
                        Seluruh permohonan darah darurat yang sesuai kriteria pencarian saat ini telah tertangani atau belum ada pengajuan baru.
                    </p>
                    @if ($queueTab !== 'all' || $urgencyFilter !== '' || $bloodTypeFilter !== '' || $searchQuery !== '')
                        <a href="{{ route('verifications.index', ['queue' => 'all']) }}" class="btn btn-dc-secondary btn-sm">
                            Lihat Semua Riwayat Masuk
                        </a>
                    @endif
                </div>
            @endforelse
        </div>

        @if ($requests->hasPages())
            <div class="mt-4">
                {{ $requests->links() }}
            </div>
        @endif
    </div>
</section>

<!-- Fluid Bottom Sheet: Quick Medical Referral Preview (§2, §5, §6, §9) -->
<div class="dc-sheet-backdrop" data-dc-sheet-backdrop="quickReferralSheet"></div>
<aside
    class="dc-sheet-panel"
    style="max-width: 54rem;"
    data-dc-sheet="quickReferralSheet"
    aria-hidden="true"
    aria-labelledby="quickReferralSheetTitle"
>
    <div class="dc-sheet-handle-area" title="Tarik ke bawah untuk menutup">
        <span class="dc-sheet-handle"></span>
    </div>

    <div class="px-4 pb-3 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <div class="dc-eyebrow">Pratinjau Cepat Berkas Medis</div>
            <h2 id="quickReferralSheetTitle" class="dc-title-3 mb-0 mt-1">Surat Rujukan Dokter</h2>
            <div id="quickReferralSheetSubtitle" class="dc-caption"></div>
        </div>

        <div class="d-flex align-items-center gap-2">
            <a id="quickReferralFullLink" href="#" class="btn btn-dc-primary btn-sm">
                <i class="bi bi-layout-split"></i>
                Buka Ruang Tinjau Lengkap
            </a>
            <button type="button" class="btn btn-dc-secondary btn-sm" data-dc-close-sheet="quickReferralSheet">
                Tutup
            </button>
        </div>
    </div>

    <div id="quickReferralViewerBody" class="p-3 overflow-auto" style="min-height: 24rem; max-height: 70vh;">
        <div class="text-center py-5 dc-caption">Memuat dokumen rujukan medis...</div>
    </div>
</aside>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const titleEl = document.getElementById('quickReferralSheetTitle');
    const subtitleEl = document.getElementById('quickReferralSheetSubtitle');
    const fullLinkEl = document.getElementById('quickReferralFullLink');
    const viewerBodyEl = document.getElementById('quickReferralViewerBody');

    document.querySelectorAll('.js-quick-preview-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            const docUrl = btn.dataset.docUrl;
            const detailUrl = btn.dataset.detailUrl;
            const docType = btn.dataset.docType;
            const docName = btn.dataset.docName || 'Surat Rujukan Medis';

            titleEl.textContent = btn.dataset.patientLabel || docName;
            subtitleEl.textContent = btn.dataset.doctorLabel || docName;
            fullLinkEl.setAttribute('href', detailUrl || '#');

            viewerBodyEl.innerHTML = '';

            if (docType === 'pdf') {
                const iframe = document.createElement('iframe');
                iframe.src = docUrl;
                iframe.title = docName;
                iframe.className = 'w-100 rounded-3 border bg-light';
                iframe.style.height = '60vh';
                viewerBodyEl.appendChild(iframe);
            } else if (docType === 'image') {
                const wrapper = document.createElement('div');
                wrapper.className = 'text-center bg-light rounded-3 p-3 border';
                const img = document.createElement('img');
                img.src = docUrl;
                img.alt = docName;
                img.className = 'img-fluid rounded-2 shadow-sm';
                img.style.maxHeight = '58vh';
                wrapper.appendChild(img);
                viewerBodyEl.appendChild(wrapper);
            } else {
                const link = document.createElement('a');
                link.href = docUrl;
                link.target = '_blank';
                link.rel = 'noopener';
                link.className = 'btn btn-dc-secondary';
                link.textContent = 'Buka Berkas Rujukan di Tab Baru';
                viewerBodyEl.appendChild(link);
            }
        });
    });
});
</script>
@endpush
