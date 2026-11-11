@extends('layouts.app')

@section('title', $activeTab === 'best_match' ? 'Rekomendasi Cerdas (Best Match) Pendonor' : 'Direktori Permohonan Darah Darurat Aktif')

@push('styles')
<link
    rel="stylesheet"
    href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
    crossorigin=""
>
<style>
    .dc-map-frame {
        height: 28rem;
        width: 100%;
        border-radius: var(--dc-radius-md);
        z-index: 1;
    }
    .dc-map-pin {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 2.3rem;
        height: 2.3rem;
        padding: 0 0.45rem;
        border-radius: 999px;
        background: #dc2626;
        color: #fff;
        font-weight: 750;
        font-size: 0.78rem;
        border: 2.5px solid #fff;
        box-shadow: 0 6px 16px rgba(220, 38, 38, 0.42);
        font-variant-numeric: tabular-nums;
    }
    .dc-map-pin-origin {
        width: 1.15rem;
        height: 1.15rem;
        border-radius: 999px;
        background: #0284c7;
        border: 3px solid #fff;
        box-shadow: 0 0 0 6px rgba(2, 132, 199, 0.22);
    }
    .dc-match-score-ring {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.3rem 0.75rem;
        border-radius: var(--dc-radius-pill);
        background: linear-gradient(135deg, #fef2f2 0%, #fff1f2 100%);
        border: 1px solid rgba(220, 38, 38, 0.24);
        color: #b91c1c;
        font-size: 0.8rem;
        font-weight: 720;
        font-variant-numeric: tabular-nums;
    }
    .dc-reason-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        padding: 0.22rem 0.62rem;
        border-radius: var(--dc-radius-sm);
        background-color: var(--dc-surface-sunken);
        border: 1px solid var(--dc-border);
        color: var(--dc-ink-secondary);
        font-size: 0.76rem;
        font-weight: 560;
    }
</style>
@endpush

@section('content')
@php
    $isBestMatchTab = $activeTab === 'best_match';

    $persistedFilterParams = array_filter([
        'tab' => $isBestMatchTab ? 'best_match' : null,
        'match_mode' => (! $isBestMatchTab && $matchMode !== 'compatible') ? $matchMode : null,
        'blood_type' => ! $isBestMatchTab ? ($bloodTypeFilter ?: null) : null,
        'rhesus' => ! $isBestMatchTab ? ($rhesusFilter ?: null) : null,
        'component' => $componentFilter ?: null,
        'region_id' => $regionIdFilter,
        'q' => $searchQuery ?: null,
        'lat' => $origin['source'] === 'gps' ? $origin['latitude'] : null,
        'lng' => $origin['source'] === 'gps' ? $origin['longitude'] : null,
    ], fn ($value) => $value !== null && $value !== '');

    $sharedGeoParams = array_filter([
        'lat' => $origin['source'] === 'gps' ? $origin['latitude'] : null,
        'lng' => $origin['source'] === 'gps' ? $origin['longitude'] : null,
    ], fn ($value) => $value !== null && $value !== '');

    $authDonor = auth()->check() && auth()->user()->isDonor() ? auth()->user() : null;
    $donorHasBloodProfile = $authDonor !== null && $authDonor->blood_type !== null && $authDonor->rhesus !== null;
    $isMyBloodFilterActive = ! $isBestMatchTab
        && $donorHasBloodProfile
        && $matchMode === 'compatible'
        && $bloodTypeFilter === $authDonor->blood_type->value
        && $rhesusFilter === $authDonor->rhesus->value;
    $isMyRegionFilterActive = $authDonor !== null
        && $authDonor->region_id !== null
        && (int) $regionIdFilter === (int) $authDonor->region_id;
@endphp

<section class="py-4 py-lg-5">
    <div class="container">
        <!-- Header & Geospatial Reference Controls -->
        <div class="d-flex flex-column flex-lg-row align-items-lg-end justify-content-between gap-3 mb-4">
            <div>
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <span class="dc-eyebrow">
                        {{ $isBestMatchTab ? 'Pencocokan Cerdas Berbasis Profil' : 'Direktori Tanggap Darurat' }}
                    </span>
                    <span class="dc-pill dc-pill-neutral">
                        <i class="bi bi-geo-alt-fill text-danger"></i>
                        Titik Acuan: <strong>{{ $origin['label'] }}</strong>
                    </span>
                    @if ($bestMatchReadiness['is_ready'] && $bestMatchReadiness['blood_group'])
                        <span class="dc-pill dc-pill-critical dc-tabular">
                            <i class="bi bi-droplet-fill"></i>
                            Profil Darah Anda: <strong>{{ $bestMatchReadiness['blood_group'] }}</strong>
                        </span>
                    @endif
                </div>
                <h1 class="dc-title-1 mb-1">
                    {{ $isBestMatchTab ? 'Rekomendasi Cerdas (Best Match)' : 'Kebutuhan Darah Aktif Terverifikasi' }}
                </h1>
                <p class="dc-caption mb-0">
                    @if ($isBestMatchTab)
                        Memprioritaskan pasien paling kritis yang kompatibel secara serologis dengan golongan darah dan terdekat dari lokasi Anda.
                    @else
                        Saring permohonan berdasarkan aturan medis kompatibilitas transfusi ABO/Rhesus per komponen darah, wilayah rumah sakit, serta kedekatan jarak.
                    @endif
                </p>
            </div>

            <div class="d-flex flex-wrap align-items-center gap-2">
                <button type="button" id="useGpsBtn" class="btn btn-dc-secondary btn-sm">
                    <i class="bi bi-crosshair"></i>
                    <span>Gunakan Lokasi GPS Saya</span>
                </button>

                @if ($origin['source'] === 'gps')
                    <a
                        href="{{ route('discovery.index', array_filter(array_merge($persistedFilterParams, [
                            'sort' => (! $isBestMatchTab && $sortBy !== 'urgency_nearest') ? $sortBy : null,
                            'lat' => null,
                            'lng' => null,
                        ]))) }}"
                        class="btn btn-dc-soft btn-sm"
                    >
                        <i class="bi bi-arrow-counterclockwise"></i>
                        Reset Titik Acuan
                    </a>
                @endif
            </div>
        </div>

        <div id="gpsAlertBox" class="dc-card p-3 mb-3 border-warning-subtle bg-warning-subtle text-warning-emphasis d-none" role="status"></div>

        <!-- Segmented Mode Switcher: Semua Permohonan Aktif vs Rekomendasi Cerdas (Best Match) (§3 & §4) -->
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
            <div class="dc-segmented flex-wrap" data-dc-segmented role="tablist" aria-label="Mode Eksplorasi Permohonan Darah">
                <a
                    href="{{ route('discovery.index', $sharedGeoParams) }}"
                    role="tab"
                    aria-selected="{{ $isBestMatchTab ? 'false' : 'true' }}"
                    data-value="all"
                    class="dc-segmented-btn {{ ! $isBestMatchTab ? 'active' : '' }} text-decoration-none d-inline-flex align-items-center gap-2 dc-pressable"
                >
                    <i class="bi bi-collection"></i>
                    <span>Semua Permohonan Aktif</span>
                    <span class="badge rounded-pill {{ ! $isBestMatchTab ? 'text-bg-danger' : 'text-bg-secondary bg-opacity-25 text-dark' }} dc-tabular">
                        {{ $allActiveRequestsCount }}
                    </span>
                </a>

                <a
                    href="{{ route('discovery.index', array_merge($sharedGeoParams, ['tab' => 'best_match'])) }}"
                    role="tab"
                    aria-selected="{{ $isBestMatchTab ? 'true' : 'false' }}"
                    data-value="best_match"
                    class="dc-segmented-btn {{ $isBestMatchTab ? 'active' : '' }} text-decoration-none d-inline-flex align-items-center gap-2 dc-pressable"
                >
                    <i class="bi bi-stars text-danger"></i>
                    <span>Rekomendasi Cerdas (Best Match)</span>
                    @if ($bestMatchReadiness['is_ready'])
                        <span class="badge rounded-pill {{ $isBestMatchTab ? 'text-bg-danger' : 'bg-danger-subtle text-danger' }} dc-tabular">
                            {{ $bestMatchTotalCount }}
                        </span>
                    @endif
                </a>
            </div>

            @if (! $isBestMatchTab && $bestMatchReadiness['is_ready'] && $bestMatchTotalCount > 0)
                <a
                    href="{{ route('discovery.index', array_merge($sharedGeoParams, ['tab' => 'best_match'])) }}"
                    class="dc-caption text-decoration-none fw-semibold text-danger d-inline-flex align-items-center gap-1 dc-pressable"
                >
                    <i class="bi bi-lightning-charge-fill"></i>
                    <span>{{ $bestMatchTotalCount }} pasien kritis cocok dengan profil {{ $bestMatchReadiness['blood_group'] }} Anda &rarr;</span>
                </a>
            @endif
        </div>

        @if ($isBestMatchTab && $bestMatchReadiness['is_ready'])
            <!-- Best Match Rule-Based Scoring Explanation & Clinical Cooldown Notice -->
            <div class="dc-card p-3 p-lg-4 mb-4 border-danger border-opacity-25">
                <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                    <div>
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                            <span class="dc-eyebrow">Mesin Pencocokan Klinis &amp; Geospasial</span>
                            <span class="dc-pill dc-pill-success dc-tabular">
                                <i class="bi bi-shield-check"></i>
                                Profil Aktif: Golongan {{ $bestMatchReadiness['blood_group'] }}
                            </span>
                        </div>
                        <p class="dc-caption mb-0 text-dark">
                            Setiap permohonan yang kompatibel dihitung skor kecocokannya (0–100%) berdasarkan bobot:
                            <strong>Kompatibilitas Transfusi (35%)</strong>,
                            <strong>Urgensi &amp; Kadar Hb Pasien (35%)</strong>,
                            <strong>Kedekatan Jarak Faskes (20%)</strong>, serta
                            <strong>Tenggat &amp; Defisit Kantong (10%)</strong>.
                        </p>
                    </div>

                    @if ($summary['top_match_score'] !== null)
                        <div class="flex-shrink-0 text-lg-end">
                            <div class="dc-caption">Kecocokan Tertinggi</div>
                            <div class="dc-title-2 text-danger dc-tabular mb-0">{{ $summary['top_match_score'] }}% Cocok</div>
                        </div>
                    @endif
                </div>

                @if ($bestMatchReadiness['cooldown_active'] && $bestMatchReadiness['cooldown_notice'])
                    <div class="mt-3 pt-3 border-top d-flex align-items-start gap-2 small text-warning-emphasis">
                        <i class="bi bi-clock-history text-warning mt-1"></i>
                        <div>{{ $bestMatchReadiness['cooldown_notice'] }}</div>
                    </div>
                @endif
            </div>
        @endif

        @if (! $isBestMatchTab || $bestMatchReadiness['is_ready'])
            <!-- Summary Metrics -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-lg-3">
                    <div class="dc-card p-3 h-100">
                        <div class="dc-caption">
                            {{ $isBestMatchTab ? 'Rekomendasi Kompatibel' : 'Kasus Aktif Sesuai Filter' }}
                        </div>
                        <div class="dc-title-2 dc-tabular mb-0 mt-1">{{ $summary['active_cases'] }}</div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="dc-card p-3 h-100">
                        <div class="dc-caption">Urgensi Kritis</div>
                        <div class="dc-title-2 dc-tabular text-danger mb-0 mt-1">{{ $summary['critical_cases'] }}</div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="dc-card p-3 h-100">
                        <div class="dc-caption">Kantong Dibutuhkan</div>
                        <div class="dc-title-2 dc-tabular mb-0 mt-1">{{ $summary['remaining_bags'] }}</div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="dc-card p-3 h-100">
                        <div class="dc-caption">Faskes Terdekat</div>
                        <div class="dc-title-2 dc-tabular text-success mb-0 mt-1">{{ $summary['nearest_distance_label'] }}</div>
                    </div>
                </div>
            </div>

            <!-- Multi-Parameter Clinical Compatibility, Component & Region Filter Panel (§12 Materials) -->
            <div class="dc-glass-bar p-3 p-lg-4 mb-4">
                @if ($authDonor && ($donorHasBloodProfile || $authDonor->region))
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 pb-3 mb-3 border-bottom">
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <span class="dc-caption fw-semibold text-dark">
                                <i class="bi bi-person-heart text-danger me-1"></i>Filter Cepat Profil Anda:
                            </span>

                            @if (! $isBestMatchTab && $donorHasBloodProfile)
                                @php
                                    $myBloodParams = $isMyBloodFilterActive
                                        ? array_merge($persistedFilterParams, ['blood_type' => null, 'rhesus' => null, 'match_mode' => null])
                                        : array_merge($persistedFilterParams, [
                                            'match_mode' => null,
                                            'blood_type' => $authDonor->blood_type->value,
                                            'rhesus' => $authDonor->rhesus->value,
                                        ]);
                                    if ($sortBy !== 'urgency_nearest') {
                                        $myBloodParams['sort'] = $sortBy;
                                    }
                                @endphp
                                <a
                                    href="{{ route('discovery.index', array_filter($myBloodParams, fn ($v) => $v !== null && $v !== '')) }}"
                                    class="btn {{ $isMyBloodFilterActive ? 'btn-dc-primary' : 'btn-dc-secondary' }} btn-sm dc-pressable"
                                >
                                    <i class="bi bi-droplet-fill"></i>
                                    Kompatibel Darah Saya ({{ $authDonor->blood_group_label }})
                                </a>
                            @elseif ($isBestMatchTab && $donorHasBloodProfile)
                                <span class="dc-pill dc-pill-critical dc-tabular">
                                    <i class="bi bi-check2-circle"></i>
                                    Otomatis Terkunci ke Golongan {{ $authDonor->blood_group_label }}
                                </span>
                            @endif

                            @if ($authDonor->region)
                                @php
                                    $myRegionParams = $isMyRegionFilterActive
                                        ? array_merge($persistedFilterParams, ['region_id' => null])
                                        : array_merge($persistedFilterParams, ['region_id' => $authDonor->region_id]);
                                    if (! $isBestMatchTab && $sortBy !== 'urgency_nearest') {
                                        $myRegionParams['sort'] = $sortBy;
                                    }
                                @endphp
                                <a
                                    href="{{ route('discovery.index', array_filter($myRegionParams, fn ($v) => $v !== null && $v !== '')) }}"
                                    class="btn {{ $isMyRegionFilterActive ? 'btn-dc-primary' : 'btn-dc-secondary' }} btn-sm dc-pressable"
                                >
                                    <i class="bi bi-geo-fill"></i>
                                    Domisili Saya ({{ $authDonor->region->name }})
                                </a>
                            @endif
                        </div>

                        @if ($hasActiveFilters)
                            <a
                                href="{{ route('discovery.index', array_filter([
                                    'tab' => $isBestMatchTab ? 'best_match' : null,
                                    'sort' => (! $isBestMatchTab && $sortBy !== 'urgency_nearest') ? $sortBy : null,
                                    'lat' => $origin['source'] === 'gps' ? $origin['latitude'] : null,
                                    'lng' => $origin['source'] === 'gps' ? $origin['longitude'] : null,
                                ])) }}"
                                class="btn btn-dc-soft btn-sm"
                            >
                                <i class="bi bi-x-circle"></i>
                                Reset Semua Filter
                            </a>
                        @endif
                    </div>
                @endif

                <form method="GET" action="{{ route('discovery.index') }}" id="discoveryFilterForm">
                    @if ($isBestMatchTab)
                        <input type="hidden" name="tab" value="best_match">
                    @elseif ($sortBy !== 'urgency_nearest')
                        <input type="hidden" name="sort" value="{{ $sortBy }}">
                    @endif
                    @if ($origin['source'] === 'gps')
                        <input type="hidden" name="lat" value="{{ $origin['latitude'] }}">
                        <input type="hidden" name="lng" value="{{ $origin['longitude'] }}">
                    @endif

                    <div class="row g-2 g-lg-3 align-items-end">
                        @if (! $isBestMatchTab)
                            <div class="col-12 col-md-4 col-xl-3">
                                <label for="filterMatchMode" class="form-label small mb-1">Metode Pencocokan Golongan</label>
                                <select id="filterMatchMode" name="match_mode" class="form-select form-select-sm js-auto-filter">
                                    <option value="compatible" @selected($matchMode === 'compatible')>
                                        Kompatibel Transfusi (Gol. Pendonor)
                                    </option>
                                    <option value="exact" @selected($matchMode === 'exact')>
                                        Golongan Pasien Spesifik (Identik)
                                    </option>
                                </select>
                            </div>

                            <div class="col-6 col-md-2 col-xl-2">
                                <label for="filterBloodType" class="form-label small mb-1" id="bloodTypeFilterLabel">
                                    {{ $matchMode === 'compatible' ? 'ABO Pendonor' : 'ABO Pasien' }}
                                </label>
                                <select id="filterBloodType" name="blood_type" class="form-select form-select-sm js-auto-filter">
                                    <option value="">Semua ABO</option>
                                    @foreach ($bloodTypes as $bt)
                                        <option value="{{ $bt->value }}" @selected($bloodTypeFilter === $bt->value)>
                                            Golongan {{ $bt->value }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-6 col-md-2 col-xl-2">
                                <label for="filterRhesus" class="form-label small mb-1">Faktor Rhesus</label>
                                <select id="filterRhesus" name="rhesus" class="form-select form-select-sm js-auto-filter">
                                    <option value="">Semua Rh</option>
                                    @foreach ($rhesusOptions as $rh)
                                        <option value="{{ $rh->value }}" @selected($rhesusFilter === $rh->value)>
                                            Rhesus ({{ $rh->value }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        <div class="col-12 col-sm-6 {{ $isBestMatchTab ? 'col-md-3 col-xl-3' : 'col-md-4 col-xl-2' }}">
                            <label for="filterComponent" class="form-label small mb-1">Komponen Darah</label>
                            <select id="filterComponent" name="component" class="form-select form-select-sm js-auto-filter">
                                <option value="">Semua Komponen</option>
                                @foreach ($componentOptions as $comp)
                                    <option value="{{ $comp->value }}" @selected($componentFilter === $comp->value)>
                                        {{ $comp->value }} — {{ $comp->label() }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12 col-sm-6 {{ $isBestMatchTab ? 'col-md-4 col-xl-3' : 'col-md-6 col-xl-3' }}">
                            <label for="filterRegion" class="form-label small mb-1">Wilayah Kabupaten / Kota</label>
                            <select id="filterRegion" name="region_id" class="form-select form-select-sm js-auto-filter">
                                <option value="">Seluruh Wilayah Faskes</option>
                                @foreach ($regionsByProvince as $provinceName => $provinceRegions)
                                    <optgroup label="{{ $provinceName }}">
                                        @foreach ($provinceRegions as $reg)
                                            <option value="{{ $reg->id }}" @selected((int) $regionIdFilter === (int) $reg->id)>
                                                {{ $reg->name }}
                                            </option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12 {{ $isBestMatchTab ? 'col-md-5 col-xl-6' : 'col-md-6 col-xl-6' }}">
                            <label for="filterSearchQuery" class="form-label small mb-1">Pencarian Kata Kunci</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white border-end-0 text-secondary">
                                    <i class="bi bi-search"></i>
                                </span>
                                <input
                                    type="search"
                                    id="filterSearchQuery"
                                    name="q"
                                    value="{{ $searchQuery }}"
                                    class="form-control form-control-sm border-start-0 ps-0"
                                    placeholder="Cari nama rumah sakit, kode permohonan, indikasi medis..."
                                >
                                <button type="submit" class="btn btn-dc-primary btn-sm px-3">
                                    Terapkan Filter
                                </button>
                                @if ($hasActiveFilters && ! ($authDonor && ($donorHasBloodProfile || $authDonor->region)))
                                    <a
                                        href="{{ route('discovery.index', array_filter([
                                            'tab' => $isBestMatchTab ? 'best_match' : null,
                                            'sort' => (! $isBestMatchTab && $sortBy !== 'urgency_nearest') ? $sortBy : null,
                                            'lat' => $origin['source'] === 'gps' ? $origin['latitude'] : null,
                                            'lng' => $origin['source'] === 'gps' ? $origin['longitude'] : null,
                                        ])) }}"
                                        class="btn btn-dc-secondary btn-sm"
                                        title="Reset semua filter"
                                    >
                                        Reset
                                    </a>
                                @endif
                            </div>
                        </div>

                        @if (! $isBestMatchTab)
                            <div class="col-12 col-xl-6">
                                <div class="d-flex flex-wrap align-items-center justify-content-xl-end gap-1" role="navigation" aria-label="Urutkan permohonan">
                                    @php
                                        $sortOptions = [
                                            'urgency_nearest' => ['label' => 'Urgensi & Terdekat', 'icon' => 'bi-stars'],
                                            'nearest' => ['label' => 'Jarak Terdekat', 'icon' => 'bi-signpost-split'],
                                            'urgency' => ['label' => 'Prioritas Klinis', 'icon' => 'bi-heart-pulse'],
                                            'deadline' => ['label' => 'Tenggat Terdekat', 'icon' => 'bi-alarm'],
                                        ];
                                    @endphp
                                    @foreach ($sortOptions as $sortKey => $sortMeta)
                                        <a
                                            href="{{ route('discovery.index', array_filter(array_merge($persistedFilterParams, [
                                                'sort' => $sortKey === 'urgency_nearest' ? null : $sortKey,
                                            ]), fn ($v) => $v !== null && $v !== '')) }}"
                                            class="dc-nav-pill {{ $sortBy === $sortKey ? 'active' : '' }} d-inline-flex align-items-center gap-2"
                                        >
                                            <i class="bi {{ $sortMeta['icon'] }}"></i>
                                            <span>{{ $sortMeta['label'] }}</span>
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </form>

                @if ($compatibilityReachSummary)
                    <div class="mt-3 pt-3 border-top d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 small">
                        <div>
                            <i class="bi bi-shield-plus text-danger me-1"></i>
                            <strong class="text-dark">Matriks Kompatibilitas Transfusi Aktif
                                ({{ $isBestMatchTab ? ($bestMatchReadiness['blood_group'] ?? '') : (($bloodTypeFilter ?: 'Semua ABO').($rhesusFilter ?: '')) }}):</strong>
                            <span class="text-secondary dc-tabular">{{ $compatibilityReachSummary }}</span>
                        </div>
                        @if ($selectedRegion)
                            <span class="dc-pill dc-pill-neutral flex-shrink-0">
                                <i class="bi bi-building text-danger"></i>
                                {{ $selectedRegion->full_label }}
                            </span>
                        @elseif ($isBestMatchTab)
                            <span class="dc-pill dc-pill-neutral flex-shrink-0">
                                <i class="bi bi-sort-down text-danger"></i>
                                Diurutkan: Skor Kecocokan Tertinggi
                            </span>
                        @endif
                    </div>
                @elseif ($selectedRegion)
                    <div class="mt-3 pt-3 border-top d-flex align-items-center gap-2 small">
                        <span class="dc-pill dc-pill-neutral">
                            <i class="bi bi-building text-danger"></i>
                            Wilayah Faskes: {{ $selectedRegion->full_label }}
                        </span>
                    </div>
                @endif
            </div>
        @endif

        @if ($isBestMatchTab && ! $bestMatchReadiness['is_ready'])
            <!-- Guided Onboarding / Role Explanation for Best Match Tab -->
            <div class="dc-card p-4 p-lg-5 text-center mx-auto" style="max-width: 44rem;">
                <div class="mx-auto mb-3 d-inline-flex align-items-center justify-content-center rounded-circle bg-danger-subtle text-danger" style="width: 4rem; height: 4rem;">
                    <i class="bi bi-stars fs-3"></i>
                </div>

                @if ($bestMatchReadiness['state'] === 'guest')
                    <h2 class="dc-title-2 mb-2">Masuk untuk Mengaktifkan Rekomendasi Cerdas</h2>
                    <p class="dc-caption mb-4 mx-auto" style="max-width: 34rem;">
                        Tab <strong>Best Match</strong> mencocokkan golongan darah ABO/Rhesus dan lokasi domisili akun Pendonor Anda secara otomatis dengan pasien paling kritis yang dapat Anda bantu secara medis.
                    </p>
                    <div class="d-flex flex-wrap align-items-center justify-content-center gap-2">
                        @if (Route::has('auth.login'))
                            <a href="{{ route('auth.login') }}" class="btn btn-dc-primary">
                                <i class="bi bi-box-arrow-in-right"></i>
                                Masuk sebagai Pendonor
                            </a>
                        @endif
                        @if (Route::has('auth.register'))
                            <a href="{{ route('auth.register') }}" class="btn btn-dc-secondary">
                                Daftar Akun Pendonor
                            </a>
                        @endif
                        <a href="{{ route('discovery.index', $sharedGeoParams) }}" class="btn btn-dc-soft">
                            Lihat Semua Permohonan Aktif
                        </a>
                    </div>
                @elseif ($bestMatchReadiness['state'] === 'non_donor')
                    <h2 class="dc-title-2 mb-2">Khusus Profil Relawan Pendonor</h2>
                    <p class="dc-caption mb-4 mx-auto" style="max-width: 34rem;">
                        Anda sedang masuk sebagai <strong>{{ auth()->user()->role->label() }}</strong>. Rekomendasi cerdas <strong>Best Match</strong> dirancang khusus bagi akun berperan <strong>Pendonor</strong> yang memiliki profil biologis ABO dan Rhesus.
                    </p>
                    <a href="{{ route('discovery.index', $sharedGeoParams) }}" class="btn btn-dc-primary">
                        <i class="bi bi-collection"></i>
                        Buka Semua Permohonan Aktif
                    </a>
                @else
                    <h2 class="dc-title-2 mb-2">Profil Golongan Darah Belum Lengkap</h2>
                    <p class="dc-caption mb-4 mx-auto" style="max-width: 34rem;">
                        Sistem memerlukan informasi <strong>Golongan Darah ABO</strong> dan <strong>Faktor Rhesus</strong> pada akun Pendonor Anda untuk menghitung kecocokan serologis transfusi secara akurat.
                    </p>
                    <a href="{{ route('discovery.index', $sharedGeoParams) }}" class="btn btn-dc-primary">
                        <i class="bi bi-funnel"></i>
                        Gunakan Filter Kompatibilitas Manual
                    </a>
                @endif
            </div>
        @else
            <!-- Split View: Left Active Request Directory | Right Interactive Leaflet Geospatial Map -->
            <div class="row g-4 align-items-start">
                <!-- Left Column: Active Requests Cards -->
                <div class="col-xl-7 order-2 order-xl-1">
                    <div class="vstack gap-3">
                        @forelse ($requests as $index => $item)
                            @php
                                $detailUrl = route('discovery.show', array_filter([
                                    'bloodRequest' => $item->id,
                                    'lat' => $origin['source'] === 'gps' ? $origin['latitude'] : null,
                                    'lng' => $origin['source'] === 'gps' ? $origin['longitude'] : null,
                                ]));
                                $compatMeta = $item->getAttribute('compatibility_meta');
                                $bestMatchMeta = $item->getAttribute('best_match_meta');
                                $hasEligibleScore = is_array($bestMatchMeta) && ($bestMatchMeta['eligible'] ?? false);
                                $breakdown = $hasEligibleScore ? $bestMatchMeta['breakdown'] : null;
                                $breakdownTitle = $breakdown
                                    ? sprintf(
                                        'Rincian Skor: Biologis %d/35 · Klinis %d/35 · Jarak %d/20 · Defisit/Tenggat %d/10',
                                        $breakdown['compatibility'],
                                        $breakdown['clinical'],
                                        $breakdown['proximity'],
                                        $breakdown['scarcity']
                                    )
                                    : '';
                            @endphp
                            <article
                                class="dc-card p-4 {{ ($item->urgency_level->value === 'critical' || ($isBestMatchTab && $index === 0)) ? 'border-danger border-opacity-25' : '' }}"
                                id="request-card-{{ $item->id }}"
                            >
                                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                                    <div class="d-flex flex-wrap align-items-center gap-2">
                                        @if ($isBestMatchTab && $hasEligibleScore)
                                            <span class="dc-match-score-ring" title="{{ $breakdownTitle }}">
                                                <i class="bi {{ $bestMatchMeta['icon'] }}"></i>
                                                <span>{{ $bestMatchMeta['score'] }}% Cocok</span>
                                                <span class="fw-normal text-secondary">&middot;</span>
                                                <span>{{ $index === 0 ? '#1 Prioritas Anda' : $bestMatchMeta['tier_label'] }}</span>
                                            </span>
                                        @endif

                                        <span class="dc-pill {{ $item->urgency_level->pillClass() }}">
                                            <i class="bi bi-heart-pulse-fill"></i>
                                            {{ $item->urgency_level->label() }}
                                        </span>
                                        <span class="dc-pill dc-pill-neutral dc-tabular">
                                            <i class="bi bi-geo-alt-fill text-danger"></i>
                                            {{ $item->getAttribute('distance_label') }}
                                            @if ($item->getAttribute('estimated_minutes'))
                                                &middot; &plusmn;{{ $item->getAttribute('estimated_minutes') }} mnt
                                            @endif
                                        </span>
                                        @if (is_array($compatMeta) && ($compatMeta['tier'] ?? 'unknown') !== 'unknown')
                                            <span class="dc-pill {{ $compatMeta['pill_class'] }} dc-tabular" title="{{ $compatMeta['explanation'] }}">
                                                <i class="bi {{ $compatMeta['compatible'] ? 'bi-check2-circle' : 'bi-slash-circle' }}"></i>
                                                {{ $compatMeta['badge_label'] }}
                                            </span>
                                        @endif
                                        @if (! $isBestMatchTab && $hasEligibleScore)
                                            <span class="dc-pill dc-pill-neutral dc-tabular" title="{{ $breakdownTitle }}">
                                                <i class="bi bi-stars text-danger"></i>
                                                Skor {{ $bestMatchMeta['score'] }}%
                                            </span>
                                        @endif
                                    </div>

                                    <span class="dc-caption dc-tabular fw-semibold">{{ $item->request_code }}</span>
                                </div>

                                <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                                    <div class="d-flex align-items-start gap-3">
                                        <span class="dc-blood-badge flex-shrink-0" style="width: 3.25rem; height: 3.25rem; font-size: 1.15rem;">
                                            {{ $item->blood_group_label }}
                                        </span>

                                        <div>
                                            <h2 class="dc-title-3 mb-1">
                                                <a href="{{ $detailUrl }}" class="text-decoration-none text-dark">
                                                    Pasien {{ $item->getAttribute('masked_patient_name') }}
                                                </a>
                                                <span class="dc-caption fw-normal">
                                                    &middot; Komponen <strong>{{ $item->component_type->value }}</strong>
                                                </span>
                                            </h2>

                                            <div class="dc-caption mb-1">
                                                <i class="bi bi-hospital me-1 text-danger"></i>
                                                <strong class="text-dark">{{ $item->facility?->name }}</strong>
                                                @if ($item->facility?->region)
                                                    &middot; {{ $item->facility->region->name }}
                                                @endif
                                            </div>

                                            <div class="dc-caption">
                                                @if ($item->clinical_diagnosis)
                                                    <span>Indikasi: <strong class="text-dark">{{ \Illuminate\Support\Str::limit($item->clinical_diagnosis, 58) }}</strong></span>
                                                @endif
                                                @if ($item->hemoglobin_level !== null)
                                                    &middot; <span class="dc-tabular">Hb {{ number_format($item->hemoglobin_level, 1) }} g/dL</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    <div class="text-md-end flex-shrink-0">
                                        <div class="fw-bold text-danger dc-tabular">
                                            Butuh {{ $item->remaining_bags }} Kantong
                                        </div>
                                        <div class="dc-caption dc-tabular mb-2">
                                            Terpenuhi {{ $item->bags_fulfilled }} / {{ $item->bags_needed }}
                                        </div>
                                        @if ($item->needed_at)
                                            <div class="dc-caption dc-tabular">
                                                <i class="bi bi-clock me-1"></i>Sebelum {{ $item->needed_at->translatedFormat('d M, H:i') }}
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                @if ($isBestMatchTab && $hasEligibleScore && ! empty($bestMatchMeta['reasons']))
                                    <div class="d-flex flex-wrap align-items-center gap-1 mt-3 pt-2">
                                        <span class="dc-caption me-1">Alasan Rekomendasi:</span>
                                        @foreach ($bestMatchMeta['reasons'] as $reasonText)
                                            <span class="dc-reason-chip dc-tabular">
                                                <i class="bi bi-check2 text-danger"></i>
                                                {{ $reasonText }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif

                                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 pt-3 mt-3 border-top">
                                    <div class="flex-grow-1 me-md-3" style="min-width: 10rem;">
                                        <div class="progress" style="height: 0.45rem;" role="progressbar" aria-valuenow="{{ $item->fulfillment_percentage }}" aria-valuemin="0" aria-valuemax="100">
                                            <div class="progress-bar bg-danger" style="width: {{ max(8, $item->fulfillment_percentage) }}%"></div>
                                        </div>
                                    </div>

                                    <div class="d-flex align-items-center gap-2">
                                        @if ($item->facility?->latitude !== null && $item->facility?->longitude !== null)
                                            <button
                                                type="button"
                                                class="btn btn-dc-secondary btn-sm js-focus-map-btn"
                                                data-request-id="{{ $item->id }}"
                                                data-lat="{{ $item->facility->latitude }}"
                                                data-lng="{{ $item->facility->longitude }}"
                                            >
                                                <i class="bi bi-pin-map"></i>
                                                Fokus Peta
                                            </button>
                                        @endif

                                        <a href="{{ $detailUrl }}" class="btn btn-dc-primary btn-sm">
                                            <i class="bi bi-arrow-right-circle"></i>
                                            Detail &amp; Bantu
                                        </a>
                                    </div>
                                </div>
                            </article>
                        @empty
                            <div class="dc-card p-5 text-center">
                                <div class="mx-auto mb-3 d-inline-flex align-items-center justify-content-center rounded-circle bg-light text-secondary" style="width: 3.5rem; height: 3.5rem;">
                                    <i class="bi {{ $isBestMatchTab ? 'bi-heart-pulse' : 'bi-funnel' }} fs-4"></i>
                                </div>
                                <h2 class="dc-title-3 mb-1">
                                    {{ $isBestMatchTab ? 'Belum Ada Permohonan Darurat yang Kompatibel' : 'Tidak Ada Permohonan Aktif yang Cocok' }}
                                </h2>
                                <p class="dc-caption mb-3 mx-auto" style="max-width: 30rem;">
                                    @if ($isBestMatchTab)
                                        Saat ini belum ada permohonan darah aktif yang membutuhkan golongan kompatibel dengan profil {{ $bestMatchReadiness['blood_group'] }} Anda{{ $hasActiveFilters ? ' pada filter wilayah/komponen yang dipilih' : '' }}.
                                    @else
                                        Saat ini belum ditemukan permohonan darah aktif yang memenuhi kombinasi kompatibilitas golongan, jenis komponen, atau wilayah yang Anda pilih.
                                    @endif
                                </p>
                                <div class="d-flex flex-wrap justify-content-center gap-2">
                                    @if ($hasActiveFilters)
                                        <a
                                            href="{{ route('discovery.index', array_filter([
                                                'tab' => $isBestMatchTab ? 'best_match' : null,
                                                'sort' => (! $isBestMatchTab && $sortBy !== 'urgency_nearest') ? $sortBy : null,
                                                'lat' => $origin['source'] === 'gps' ? $origin['latitude'] : null,
                                                'lng' => $origin['source'] === 'gps' ? $origin['longitude'] : null,
                                            ])) }}"
                                            class="btn btn-dc-secondary btn-sm"
                                        >
                                            Reset Filter{{ $isBestMatchTab ? ' Rekomendasi' : ' & Tampilkan Semua Kasus Aktif' }}
                                        </a>
                                    @endif
                                    @if ($isBestMatchTab)
                                        <a href="{{ route('discovery.index', $sharedGeoParams) }}" class="btn btn-dc-soft btn-sm">
                                            Lihat Seluruh Katalog Umum
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @endforelse
                    </div>
                </div>

                <!-- Right Column: Sticky Interactive Geospatial Map (Leaflet.js + OpenStreetMap) -->
                <div class="col-xl-5 order-1 order-xl-2">
                    <div class="dc-card p-3 p-md-4" style="position: sticky; top: 5.25rem;">
                        <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
                            <div>
                                <div class="dc-eyebrow">Peta Geospasial Faskes</div>
                                <h2 class="dc-title-3 mb-0 mt-1">
                                    {{ $isBestMatchTab ? 'Sebaran Faskes Kompatibel' : 'Sebaran Rumah Sakit Rujukan' }}
                                </h2>
                            </div>
                            <span class="dc-pill dc-pill-neutral dc-tabular">
                                <i class="bi bi-broadcast text-danger"></i>
                                {{ $mapMarkers->count() }} Titik Aktif
                            </span>
                        </div>

                        <div id="discoveryMap" class="dc-map-frame border" aria-label="Peta sebaran permohonan darah darurat"></div>

                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3 dc-caption">
                            <span class="d-inline-flex align-items-center gap-2">
                                <span class="d-inline-block rounded-circle" style="width: 0.65rem; height: 0.65rem; background: #0284c7;"></span>
                                Titik Acuan Anda
                            </span>
                            <span class="d-inline-flex align-items-center gap-2">
                                <span class="d-inline-block rounded-circle" style="width: 0.65rem; height: 0.65rem; background: #dc2626;"></span>
                                RS Membutuhkan Transfusi (Radius 5 km ditandai)
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</section>
@endsection

@push('scripts')
<script
    src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
    integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
    crossorigin=""
></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const filterForm = document.getElementById('discoveryFilterForm');
    document.querySelectorAll('.js-auto-filter').forEach((selectEl) => {
        selectEl.addEventListener('change', () => {
            filterForm?.submit();
        });
    });

    const origin = @json($origin);
    const markersData = @json($mapMarkers);
    const reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const mapEl = document.getElementById('discoveryMap');
    if (mapEl && typeof L !== 'undefined') {
        const map = L.map(mapEl, {
            scrollWheelZoom: false,
        }).setView([origin.latitude, origin.longitude], 12);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors',
        }).addTo(map);

        const originIcon = L.divIcon({
            className: 'bg-transparent border-0',
            html: '<div class="dc-map-pin-origin" title="Titik Acuan Anda"></div>',
            iconSize: [18, 18],
            iconAnchor: [9, 9],
        });

        L.marker([origin.latitude, origin.longitude], { icon: originIcon })
            .addTo(map)
            .bindPopup(`<strong>Titik Acuan Lokasi</strong><br><span class="small text-secondary">${origin.label}</span>`);

        L.circle([origin.latitude, origin.longitude], {
            radius: 5000,
            color: '#0284c7',
            weight: 1,
            fillColor: '#0284c7',
            fillOpacity: 0.06,
            dashArray: '5, 5',
        }).addTo(map);

        const markerRegistry = new Map();
        const bounds = [[origin.latitude, origin.longitude]];

        markersData.forEach((item) => {
            const icon = L.divIcon({
                className: 'bg-transparent border-0',
                html: `<div class="dc-map-pin">${item.blood_group}</div>`,
                iconSize: [38, 38],
                iconAnchor: [19, 19],
            });

            const scoreBadge = item.match_score !== null && item.match_score !== undefined
                ? `<div class="small fw-semibold text-danger mb-1">Skor Best Match: ${item.match_score}%</div>`
                : '';

            const popupHtml = `
                <div style="min-width: 13.5rem;">
                    <div class="fw-bold text-danger mb-1">${item.blood_group} &middot; Butuh ${item.remaining_bags} Kantong ${item.component}</div>
                    ${scoreBadge}
                    <div class="fw-semibold small text-dark">${item.facility_name}</div>
                    <div class="small text-secondary mb-2">${item.distance_label} &middot; ${item.urgency_label}</div>
                    <a href="${item.detail_url}" class="btn btn-danger btn-sm w-100 text-white" style="min-height: 2rem; font-size: 0.8rem;">
                        Lihat Detail Kasus
                    </a>
                </div>
            `;

            const marker = L.marker([item.latitude, item.longitude], { icon })
                .addTo(map)
                .bindPopup(popupHtml);

            markerRegistry.set(String(item.id), marker);
            bounds.push([item.latitude, item.longitude]);
        });

        if (bounds.length > 1) {
            map.fitBounds(bounds, { padding: [36, 36], maxZoom: 14 });
        }

        document.querySelectorAll('.js-focus-map-btn').forEach((btn) => {
            btn.addEventListener('click', () => {
                const id = btn.dataset.requestId;
                const lat = parseFloat(btn.dataset.lat);
                const lng = parseFloat(btn.dataset.lng);
                const marker = markerRegistry.get(id);

                if (Number.isFinite(lat) && Number.isFinite(lng)) {
                    if (reducedMotion) {
                        map.setView([lat, lng], 15);
                    } else {
                        map.flyTo([lat, lng], 15, { duration: 0.65 });
                    }
                    if (marker) {
                        marker.openPopup();
                    }
                }
            });
        });
    }

    // HTML5 Browser Geolocation Updater
    const gpsBtn = document.getElementById('useGpsBtn');
    const gpsAlert = document.getElementById('gpsAlertBox');

    if (gpsBtn) {
        gpsBtn.addEventListener('click', () => {
            if (!navigator.geolocation) {
                gpsAlert.textContent = 'Peramban Anda tidak mendukung fitur geolokasi GPS.';
                gpsAlert.classList.remove('d-none');
                return;
            }

            gpsBtn.disabled = true;
            gpsBtn.querySelector('span').textContent = 'Mendeteksi GPS...';

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    const url = new URL(window.location.href);
                    url.searchParams.set('lat', position.coords.latitude.toFixed(7));
                    url.searchParams.set('lng', position.coords.longitude.toFixed(7));
                    window.location.href = url.toString();
                },
                () => {
                    gpsBtn.disabled = false;
                    gpsBtn.querySelector('span').textContent = 'Gunakan Lokasi GPS Saya';
                    gpsAlert.textContent = 'Izin akses lokasi ditolak atau sinyal GPS tidak tersedia. Menampilkan jarak berdasarkan profil wilayah.';
                    gpsAlert.classList.remove('d-none');
                },
                { enableHighAccuracy: true, timeout: 8000 }
            );
        });
    }
});
</script>
@endpush
