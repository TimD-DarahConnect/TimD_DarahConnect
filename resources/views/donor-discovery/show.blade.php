@extends('layouts.app')

@section('title', 'Kebutuhan Darah ' . $bloodRequest->blood_group_label . ' — ' . $bloodRequest->facility?->name)

@push('styles')
<link
    rel="stylesheet"
    href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
    crossorigin=""
>
<style>
    .dc-detail-map {
        height: 17.5rem;
        width: 100%;
        border-radius: var(--dc-radius-md);
        z-index: 1;
    }
    .dc-map-pin {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 2.4rem;
        height: 2.4rem;
        padding: 0 0.5rem;
        border-radius: 999px;
        background: #dc2626;
        color: #fff;
        font-weight: 750;
        font-size: 0.8rem;
        border: 2.5px solid #fff;
        box-shadow: 0 6px 16px rgba(220, 38, 38, 0.42);
    }
    .dc-map-pin-origin {
        width: 1.1rem;
        height: 1.1rem;
        border-radius: 999px;
        background: #0284c7;
        border: 3px solid #fff;
        box-shadow: 0 0 0 6px rgba(2, 132, 199, 0.22);
    }
</style>
@endpush

@section('content')
<section class="py-4 py-lg-5">
    <div class="container">
        <!-- Breadcrumb & Back Navigation -->
        <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
            <div>
                <nav aria-label="breadcrumb" class="mb-1">
                    <ol class="breadcrumb mb-0 dc-caption">
                        <li class="breadcrumb-item"><a href="{{ route('discovery.index') }}" class="text-decoration-none text-secondary">Kebutuhan Darurat</a></li>
                        <li class="breadcrumb-item active dc-tabular" aria-current="page">{{ $bloodRequest->request_code }}</li>
                    </ol>
                </nav>

                <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
                    <span class="dc-pill {{ $bloodRequest->urgency_level->pillClass() }}">
                        <i class="bi bi-heart-pulse-fill"></i>
                        {{ $bloodRequest->urgency_level->label() }}
                    </span>
                    <span class="dc-pill dc-pill-success">
                        <i class="bi bi-patch-check-fill"></i>
                        Terverifikasi Medis UDD
                    </span>
                    <span class="dc-pill dc-pill-neutral dc-tabular">
                        <i class="bi bi-geo-alt-fill text-danger"></i>
                        {{ $bloodRequest->getAttribute('distance_label') }} dari lokasi Anda
                    </span>
                </div>
            </div>

            <a href="{{ route('discovery.index') }}" class="btn btn-dc-secondary">
                <i class="bi bi-arrow-left"></i>
                Kembali ke Katalog
            </a>
        </div>

        <div class="row g-4 align-items-start">
            <!-- Left Column: Clinical Requirement & Hospital Location -->
            <div class="col-lg-7">
                <div class="dc-card p-4 p-md-5 mb-4">
                    <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 pb-4 mb-4 border-bottom">
                        <div class="d-flex align-items-center gap-3">
                            <span class="dc-blood-badge" style="width: 3.75rem; height: 3.75rem; font-size: 1.35rem;">
                                {{ $bloodRequest->blood_group_label }}
                            </span>
                            <div>
                                <div class="dc-eyebrow">Permohonan Transfusi Pasien</div>
                                <h1 class="dc-title-2 mb-0 mt-1">Pasien {{ $patientInitials }}</h1>
                                <div class="dc-caption">
                                    Kode Kasus <strong class="dc-tabular text-dark">{{ $bloodRequest->request_code }}</strong>
                                    @if ($bloodRequest->patient_age !== null)
                                        &middot; Usia {{ $bloodRequest->patient_age }} tahun
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="text-sm-end">
                            <div class="dc-caption">Sisa Kebutuhan Saat Ini</div>
                            <div class="dc-title-2 text-danger dc-tabular mb-0">
                                {{ $bloodRequest->remaining_bags }} Kantong {{ $bloodRequest->component_type->value }}
                            </div>
                            <div class="dc-caption">{{ $bloodRequest->component_type->label() }}</div>
                        </div>
                    </div>

                    <!-- Fulfillment Progress -->
                    <div class="p-3 rounded-4 bg-light border mb-4">
                        <div class="d-flex align-items-center justify-content-between small mb-2">
                            <span class="fw-semibold text-dark">Progres Pemenuhan Kebutuhan Darah</span>
                            <span class="dc-tabular fw-bold text-danger">
                                {{ $bloodRequest->bags_fulfilled }} dari {{ $bloodRequest->bags_needed }} Kantong ({{ $bloodRequest->fulfillment_percentage }}%)
                            </span>
                        </div>
                        <div class="progress" style="height: 0.55rem;">
                            <div class="progress-bar bg-danger" style="width: {{ max(8, $bloodRequest->fulfillment_percentage) }}%"></div>
                        </div>
                    </div>

                    <div class="row g-4">
                        <div class="col-sm-6">
                            <div class="dc-caption">Indikasi Klinis Transfusi</div>
                            <div class="fw-semibold mt-1">{{ $bloodRequest->clinical_diagnosis ?: 'Indikasi Medis Rawat Inap' }}</div>
                        </div>

                        <div class="col-sm-6">
                            <div class="dc-caption">Kondisi Hemoglobin</div>
                            <div class="fw-semibold dc-tabular mt-1">
                                @if ($bloodRequest->hemoglobin_level !== null)
                                    {{ number_format($bloodRequest->hemoglobin_level, 1) }} g/dL
                                    <span class="dc-caption">({{ $bloodRequest->hemoglobin_severity_label }})</span>
                                @else
                                    Tervalidasi Petugas UDD
                                @endif
                            </div>
                        </div>

                        <div class="col-sm-6">
                            <div class="dc-caption">Target Waktu Dibutuhkan</div>
                            <div class="fw-semibold dc-tabular mt-1">
                                {{ $bloodRequest->needed_at ? $bloodRequest->needed_at->translatedFormat('d M Y, H:i').' WIB' : 'Segera' }}
                            </div>
                        </div>

                        <div class="col-sm-6">
                            <div class="dc-caption">Masa Tayang Publikasi</div>
                            <div class="fw-semibold dc-tabular mt-1">
                                {{ $bloodRequest->expires_at ? 'Aktif s.d. '.$bloodRequest->expires_at->translatedFormat('d M Y, H:i').' WIB' : 'Aktif hingga terpenuhi' }}
                            </div>
                        </div>

                        @if ($bloodRequest->additional_notes)
                            <div class="col-12">
                                <div class="p-3 rounded-3 bg-light border">
                                    <div class="dc-caption fw-semibold text-dark mb-1">Informasi Pendukung Kunjungan Faskes</div>
                                    <p class="mb-0 small text-secondary">{{ $bloodRequest->additional_notes }}</p>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Hospital & Geospatial Navigation Card -->
                <div class="dc-card p-4">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                        <div>
                            <div class="dc-eyebrow">Lokasi Donor / Rumah Sakit Rujukan</div>
                            <h2 class="dc-title-3 mb-0 mt-1">{{ $bloodRequest->facility?->name }}</h2>
                        </div>

                        @if ($bloodRequest->getAttribute('estimated_minutes'))
                            <span class="dc-pill dc-pill-neutral dc-tabular">
                                <i class="bi bi-car-front-fill text-danger"></i>
                                Estimasi &plusmn;{{ $bloodRequest->getAttribute('estimated_minutes') }} menit ({{ $bloodRequest->getAttribute('distance_label') }})
                            </span>
                        @endif
                    </div>

                    <p class="dc-caption mb-3">
                        <i class="bi bi-geo-alt me-1 text-danger"></i>
                        {{ $bloodRequest->facility?->address }}
                        @if ($bloodRequest->ward_room)
                            &middot; Instalasi terkait: <strong class="text-dark">{{ $bloodRequest->ward_room }}</strong>
                        @endif
                    </p>

                    @if ($bloodRequest->facility?->latitude !== null && $bloodRequest->facility?->longitude !== null)
                        <div id="facilityRouteMap" class="dc-detail-map border mb-3" aria-label="Peta lokasi rumah sakit rujukan"></div>
                    @endif

                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 dc-caption">
                        <span><i class="bi bi-clock me-1"></i>Layanan Bank Darah: <strong>{{ $bloodRequest->facility?->operating_hours ?? '24 Jam' }}</strong></span>
                        <span><i class="bi bi-shield-lock me-1 text-success"></i>Privasi keluarga &amp; pendonor dilindungi sistem</span>
                    </div>
                </div>
            </div>

            <!-- Right Column: Donor Readiness & Pledge Entry Point -->
            <div class="col-lg-5">
                <div class="dc-card p-4 mb-4" style="position: sticky; top: 5.25rem;">
                    <div class="dc-eyebrow">Bantuan Kemanusiaan</div>
                    <h2 class="dc-title-3 mt-1 mb-2">Kesiapan Donor Darurat</h2>
                    <p class="dc-caption mb-3">
                        Pastikan Anda memenuhi kriteria dasar kesehatan sebelum merespons kebutuhan transfusi pasien di rumah sakit atau UDD terkait.
                    </p>

                    @if (is_array($compatibilityMeta ?? null))
                        <div class="p-3 rounded-3 border bg-light mb-3">
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-1">
                                <span class="dc-eyebrow">Analisis Kecocokan Biologis</span>
                                <span class="dc-pill {{ $compatibilityMeta['pill_class'] }} dc-tabular">
                                    {{ $compatibilityMeta['badge_label'] }}
                                </span>
                            </div>
                            <div class="dc-caption text-dark mt-1">
                                {{ $compatibilityMeta['explanation'] }}
                            </div>

                            @if (is_array($bestMatchMeta ?? null) && ($bestMatchMeta['eligible'] ?? false))
                                <div class="mt-2 pt-2 border-top d-flex flex-wrap align-items-center justify-content-between gap-2">
                                    <span class="dc-pill {{ $bestMatchMeta['pill_class'] }} dc-tabular">
                                        <i class="bi {{ $bestMatchMeta['icon'] }}"></i>
                                        Best Match {{ $bestMatchMeta['score'] }}% &middot; {{ $bestMatchMeta['tier_label'] }}
                                    </span>
                                    <span class="dc-caption dc-tabular">
                                        Biologis {{ $bestMatchMeta['breakdown']['compatibility'] }}/35 &middot;
                                        Klinis {{ $bestMatchMeta['breakdown']['clinical'] }}/35 &middot;
                                        Jarak {{ $bestMatchMeta['breakdown']['proximity'] }}/20
                                    </span>
                                </div>
                            @endif
                        </div>
                    @endif

                    <div class="vstack gap-2 mb-4">
                        <div class="d-flex align-items-center gap-2 small">
                            <i class="bi bi-check-circle-fill text-success"></i>
                            <span>Usia 17–60 tahun &amp; berat badan minimal 45 kg</span>
                        </div>
                        <div class="d-flex align-items-center gap-2 small">
                            <i class="bi bi-check-circle-fill text-success"></i>
                            <span>Tidur cukup minimal 5 jam &amp; sudah makan sebelum donor</span>
                        </div>
                        <div class="d-flex align-items-center gap-2 small">
                            <i class="bi bi-check-circle-fill text-success"></i>
                            <span>Tidak sedang demam, konsumsi antibiotik, atau masa jeda &lt; 60 hari</span>
                        </div>
                        <div class="d-flex align-items-center gap-2 small">
                            <i class="bi bi-check-circle-fill text-success"></i>
                            <span>Membawa kartu identitas resmi (KTP/SIM/Kartu Donor)</span>
                        </div>
                    </div>

                    @auth
                        @if (auth()->user()->isDonor())
                            @if (Route::has('commitments.create'))
                                <a href="{{ route('commitments.create', $bloodRequest) }}" class="btn btn-dc-primary w-100">
                                    <i class="bi bi-hand-thumbs-up-fill"></i>
                                    Saya Bersedia Donor (Pledge Kehadiran)
                                </a>
                            @elseif (Route::has('commitments.store'))
                                <button type="button" class="btn btn-dc-primary w-100" data-dc-open-sheet="pledgeCommitmentSheet">
                                    <i class="bi bi-hand-thumbs-up-fill"></i>
                                    Saya Bersedia Donor (Pledge Kehadiran)
                                </button>
                            @else
                                <div class="p-3 rounded-3 bg-light border">
                                    <div class="small fw-semibold text-dark mb-1">
                                        Profil Donor Anda: Golongan {{ auth()->user()->blood_group_label ?? 'Belum diatur' }}
                                    </div>
                                    <div class="dc-caption">
                                        Kunjungi instalasi BDRS <strong>{{ $bloodRequest->facility?->name }}</strong> dengan menyebutkan kode rujukan <strong class="dc-tabular text-dark">{{ $bloodRequest->request_code }}</strong>.
                                    </div>
                                </div>
                            @endif
                        @else
                            <div class="p-3 rounded-3 bg-light border dc-caption">
                                Anda masuk sebagai <strong>{{ auth()->user()->role->label() }}</strong>. Komitmen kesediaan donor darurat dikhususkan bagi akun berperan Pendonor.
                            </div>
                        @endif
                    @else
                        <div class="d-grid gap-2">
                            <a href="{{ route('auth.login') }}" class="btn btn-dc-primary">
                                <i class="bi bi-box-arrow-in-right"></i>
                                Masuk sebagai Pendonor untuk Membantu
                            </a>
                            <a href="{{ route('auth.register') }}" class="btn btn-dc-secondary">
                                Daftar Relawan Pendonor Baru
                            </a>
                        </div>
                    @endauth

                    @if ($nearbyRequests->isNotEmpty())
                        <hr class="my-4">
                        <div class="dc-eyebrow mb-2">Kebutuhan Aktif Lain di Sekitar Anda</div>
                        <div class="vstack gap-2">
                            @foreach ($nearbyRequests as $other)
                                <a
                                    href="{{ route('discovery.show', $other) }}"
                                    class="p-2 px-3 rounded-3 border text-decoration-none d-flex align-items-center justify-content-between gap-2 dc-pressable bg-white"
                                >
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge text-bg-danger dc-tabular">{{ $other->blood_group_label }}</span>
                                        <div>
                                            <div class="small fw-semibold text-dark">{{ $other->facility?->name }}</div>
                                            <div class="dc-caption dc-tabular">Butuh {{ $other->remaining_bags }} {{ $other->component_type->value }}</div>
                                        </div>
                                    </div>
                                    <span class="dc-caption dc-tabular fw-semibold">{{ $other->getAttribute('distance_label') }}</span>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@push('scripts')
@if ($bloodRequest->facility?->latitude !== null && $bloodRequest->facility?->longitude !== null)
<script
    src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
    integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
    crossorigin=""
></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const mapEl = document.getElementById('facilityRouteMap');
    if (!mapEl || typeof L === 'undefined') return;

    const originLat = {{ (float) $origin['latitude'] }};
    const originLng = {{ (float) $origin['longitude'] }};
    const hospLat = {{ (float) $bloodRequest->facility->latitude }};
    const hospLng = {{ (float) $bloodRequest->facility->longitude }};

    const map = L.map(mapEl, { scrollWheelZoom: false });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors',
    }).addTo(map);

    const originIcon = L.divIcon({
        className: 'bg-transparent border-0',
        html: '<div class="dc-map-pin-origin"></div>',
        iconSize: [18, 18],
        iconAnchor: [9, 9],
    });

    const hospIcon = L.divIcon({
        className: 'bg-transparent border-0',
        html: '<div class="dc-map-pin">{{ $bloodRequest->blood_group_label }}</div>',
        iconSize: [40, 40],
        iconAnchor: [20, 20],
    });

    L.marker([originLat, originLng], { icon: originIcon })
        .addTo(map)
        .bindPopup('<strong>Titik Acuan Anda</strong>');

    L.marker([hospLat, hospLng], { icon: hospIcon })
        .addTo(map)
        .bindPopup('<strong>{{ addslashes($bloodRequest->facility->name) }}</strong>')
        .openPopup();

    L.polyline([[originLat, originLng], [hospLat, hospLng]], {
        color: '#dc2626',
        weight: 2.5,
        dashArray: '6, 6',
        opacity: 0.75,
    }).addTo(map);

    map.fitBounds([[originLat, originLng], [hospLat, hospLng]], { padding: [36, 36], maxZoom: 15 });
});
</script>
@endif
@endpush
