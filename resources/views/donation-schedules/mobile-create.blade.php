@extends('layouts.app')

@section('title', 'Rencanakan Kegiatan Mobile Unit & Event Mitra')

@push('styles')
<link
    rel="stylesheet"
    href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
    crossorigin=""
>
@endpush

@section('content')
<section class="py-4 py-lg-5">
    <div class="container" style="max-width: 72rem;">
        <!-- Header -->
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
            <div>
                <nav aria-label="breadcrumb" class="mb-1">
                    <ol class="breadcrumb mb-0 dc-caption">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-secondary">Ruang Kerja</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('schedules.mobile.index') }}" class="text-decoration-none text-secondary">Agenda Mobile Unit</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Rencanakan Event Baru</li>
                    </ol>
                </nav>
                <h1 class="dc-title-1 mb-1">Rencanakan Event Mobile Unit Mitra</h1>
                <p class="dc-caption mb-0">
                    Tetapkan instansi mitra penyelenggara, lokasi &amp; koordinat peta kegiatan, target kantong darah, pembagian sesi, serta status publikasi acara.
                </p>
            </div>

            <a href="{{ route('schedules.mobile.index') }}" class="btn btn-dc-secondary">
                <i class="bi bi-arrow-left"></i>
                Kembali ke Agenda
            </a>
        </div>

        @if ($errors->any())
            <div class="dc-card p-3 px-4 mb-4 border-danger-subtle bg-danger-subtle text-danger-emphasis" role="alert">
                <div class="fw-semibold mb-1"><i class="bi bi-exclamation-octagon-fill me-1"></i>Periksa kembali kelengkapan data agenda Mobile Unit:</div>
                <ul class="mb-0 small ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @php
            $sessionRows = old('sessions', $defaultSessions);
            $initialPublication = old('publication_status', 'published');
        @endphp

        <form method="POST" action="{{ route('schedules.mobile.store') }}">
            @csrf

            <div class="row g-4 align-items-start">
                <!-- Left Column: Partner Metadata, Leaflet Map Pin Picker & Session Slots -->
                <div class="col-lg-8">
                    <!-- Step 1: Partner & Event Identity -->
                    <div class="dc-card p-4 p-md-5 mb-4">
                        <div class="dc-eyebrow mb-1">Langkah 01</div>
                        <h2 class="dc-title-3 mb-3">Identitas Instansi Mitra &amp; Target Kegiatan</h2>

                        <div class="row g-3">
                            <div class="col-md-7">
                                <label for="facility_id" class="form-label">Unit UDD PMI Penyelenggara <span class="text-danger">*</span></label>
                                <select
                                    id="facility_id"
                                    name="facility_id"
                                    class="form-select @error('facility_id') is-invalid @enderror"
                                    required
                                >
                                    @foreach ($uddFacilities as $udd)
                                        <option
                                            value="{{ $udd->id }}"
                                            data-lat="{{ $udd->latitude }}"
                                            data-lng="{{ $udd->longitude }}"
                                            data-region-id="{{ $udd->region_id }}"
                                            @selected((int) old('facility_id', $selectedFacilityId) === $udd->id)
                                        >
                                            {{ $udd->name }} ({{ $udd->region?->name }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('facility_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-5">
                                <label for="service_date" class="form-label">Tanggal Pelaksanaan <span class="text-danger">*</span></label>
                                <input
                                    type="date"
                                    id="service_date"
                                    name="service_date"
                                    min="{{ today()->toDateString() }}"
                                    value="{{ old('service_date', $defaultDate) }}"
                                    class="form-control dc-tabular @error('service_date') is-invalid @enderror"
                                    required
                                >
                                @error('service_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="partner_institution" class="form-label">Instansi / Organisasi Mitra <span class="text-danger">*</span></label>
                                <input
                                    type="text"
                                    id="partner_institution"
                                    name="partner_institution"
                                    maxlength="160"
                                    value="{{ old('partner_institution') }}"
                                    class="form-control @error('partner_institution') is-invalid @enderror"
                                    placeholder="Contoh: Institut Teknologi Bandung (ITB)"
                                    required
                                >
                                @error('partner_institution')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="target_bags" class="form-label">Target Perolehan Kantong Darah <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input
                                        type="number"
                                        id="target_bags"
                                        name="target_bags"
                                        min="10"
                                        max="2000"
                                        value="{{ old('target_bags', 80) }}"
                                        class="form-control dc-tabular @error('target_bags') is-invalid @enderror"
                                        required
                                    >
                                    <span class="input-group-text small">Kantong</span>
                                </div>
                                @error('target_bags')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12">
                                <label for="title" class="form-label">Nama Acara / Kegiatan Donor Darah <span class="text-danger">*</span></label>
                                <input
                                    type="text"
                                    id="title"
                                    name="title"
                                    maxlength="160"
                                    value="{{ old('title') }}"
                                    class="form-control @error('title') is-invalid @enderror"
                                    placeholder="Contoh: Bakti Sosial Donor Darah Civitas Akademika & Masyarakat Umum"
                                    required
                                >
                                @error('title')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-sm-6">
                                <label for="opening_time" class="form-label">Jam Mulai Acara <span class="text-danger">*</span></label>
                                <input
                                    type="time"
                                    id="opening_time"
                                    name="opening_time"
                                    value="{{ old('opening_time', '08:30') }}"
                                    class="form-control dc-tabular @error('opening_time') is-invalid @enderror"
                                    required
                                >
                                @error('opening_time')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-sm-6">
                                <label for="closing_time" class="form-label">Jam Selesai Acara <span class="text-danger">*</span></label>
                                <input
                                    type="time"
                                    id="closing_time"
                                    name="closing_time"
                                    value="{{ old('closing_time', '15:30') }}"
                                    class="form-control dc-tabular @error('closing_time') is-invalid @enderror"
                                    required
                                >
                                @error('closing_time')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: External Venue & Interactive Leaflet Pin Picker -->
                    <div class="dc-card p-4 p-md-5 mb-4">
                        <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2 mb-3">
                            <div>
                                <div class="dc-eyebrow mb-1">Langkah 02</div>
                                <h2 class="dc-title-3 mb-0">Lokasi Instansi Mitra &amp; Titik Koordinat Peta</h2>
                            </div>
                            <button type="button" id="centerRegionMapBtn" class="btn btn-dc-secondary btn-sm">
                                <i class="bi bi-crosshair"></i>
                                Pusatkan ke Wilayah
                            </button>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-5">
                                <label for="region_id" class="form-label">Kabupaten / Kota Lokasi <span class="text-danger">*</span></label>
                                <select
                                    id="region_id"
                                    name="region_id"
                                    class="form-select @error('region_id') is-invalid @enderror"
                                    required
                                >
                                    @foreach ($regions as $region)
                                        <option
                                            value="{{ $region->id }}"
                                            data-lat="{{ $region->latitude }}"
                                            data-lng="{{ $region->longitude }}"
                                            @selected((int) old('region_id', $selectedRegionId) === $region->id)
                                        >
                                            {{ $region->name }} — {{ $region->province_name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('region_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-7">
                                <label for="venue_name" class="form-label">Nama Gedung / Ruangan Titik Donor <span class="text-danger">*</span></label>
                                <input
                                    type="text"
                                    id="venue_name"
                                    name="venue_name"
                                    maxlength="160"
                                    value="{{ old('venue_name') }}"
                                    class="form-control @error('venue_name') is-invalid @enderror"
                                    placeholder="Contoh: Aula Barat Kampus ITB Ganesha"
                                    required
                                >
                                @error('venue_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12">
                                <label for="venue_address" class="form-label">Alamat Lengkap Lokasi Kegiatan <span class="text-danger">*</span></label>
                                <input
                                    type="text"
                                    id="venue_address"
                                    name="venue_address"
                                    maxlength="280"
                                    value="{{ old('venue_address') }}"
                                    class="form-control @error('venue_address') is-invalid @enderror"
                                    placeholder="Contoh: Jl. Ganesa No. 10, Lb. Siliwangi, Kec. Coblong, Kota Bandung"
                                    required
                                >
                                @error('venue_address')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <!-- Interactive Leaflet Map Pin Picker -->
                        <div class="p-2 rounded-4 bg-light border mb-3">
                            <div class="d-flex align-items-center justify-content-between px-2 py-1">
                                <span class="dc-caption">
                                    <i class="bi bi-geo-alt-fill text-danger me-1"></i>
                                    Klik pada peta atau geser pin merah untuk menentukan titik presisi armada Mobile Unit.
                                </span>
                            </div>
                            <div id="mobileUnitPickerMap" class="rounded-3 border bg-white" style="height: 18.5rem; z-index: 1;" aria-label="Peta pemilihan titik lokasi Mobile Unit"></div>
                        </div>

                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label for="latitude" class="form-label small">Koordinat Lintang (Latitude) <span class="text-danger">*</span></label>
                                <input
                                    type="number"
                                    step="0.0000001"
                                    id="latitude"
                                    name="latitude"
                                    value="{{ old('latitude', number_format((float) $defaultLat, 7, '.', '')) }}"
                                    class="form-control form-control-sm dc-tabular @error('latitude') is-invalid @enderror"
                                    required
                                >
                                @error('latitude')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-sm-6">
                                <label for="longitude" class="form-label small">Koordinat Bujur (Longitude) <span class="text-danger">*</span></label>
                                <input
                                    type="number"
                                    step="0.0000001"
                                    id="longitude"
                                    name="longitude"
                                    value="{{ old('longitude', number_format((float) $defaultLng, 7, '.', '')) }}"
                                    class="form-control form-control-sm dc-tabular @error('longitude') is-invalid @enderror"
                                    required
                                >
                                @error('longitude')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12">
                                <label for="notes" class="form-label">Catatan Teknis &amp; Informasi Peserta (Opsional)</label>
                                <textarea
                                    id="notes"
                                    name="notes"
                                    rows="2"
                                    maxlength="600"
                                    class="form-control @error('notes') is-invalid @enderror"
                                    placeholder="Contoh: Tersedia 1 bus Mobile Unit + 4 bed lipat di dalam aula ber-AC. Akses parkir melalui Gerbang Utama."
                                >{{ old('notes') }}</textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3: Dynamic Session Time Slots -->
                    <div class="dc-card p-4 p-md-5">
                        <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2 mb-3">
                            <div>
                                <div class="dc-eyebrow mb-1">Langkah 03</div>
                                <h2 class="dc-title-3 mb-0">Pembagian Gelombang Sesi &amp; Kuota Pendaftar</h2>
                            </div>

                            <div class="d-flex flex-wrap gap-2">
                                <button type="button" id="applyMobilePresetBtn" class="btn btn-dc-secondary btn-sm">
                                    <i class="bi bi-magic"></i>
                                    Preset 2 Gelombang
                                </button>
                                <button type="button" id="addSessionRowBtn" class="btn btn-dc-soft btn-sm">
                                    <i class="bi bi-plus-lg"></i>
                                    Tambah Sesi
                                </button>
                            </div>
                        </div>

                        <p class="dc-caption mb-3">
                            Atur gelombang kedatangan pendonor agar antrean registrasi dan skrining dokter di lokasi mitra tidak menumpuk.
                        </p>

                        <div id="sessionsContainer" class="vstack gap-3">
                            @foreach ($sessionRows as $index => $row)
                                <div class="p-3 rounded-4 bg-light border js-session-row">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <span class="dc-eyebrow js-session-heading">Sesi #{{ $index + 1 }}</span>
                                        <button type="button" class="btn btn-sm text-danger p-0 px-2 js-remove-session" title="Hapus sesi ini">
                                            <i class="bi bi-trash3"></i> Hapus
                                        </button>
                                    </div>

                                    <div class="row g-2 align-items-end">
                                        <div class="col-md-5">
                                            <label class="form-label small mb-1">Nama Sesi</label>
                                            <input
                                                type="text"
                                                name="sessions[{{ $index }}][session_label]"
                                                value="{{ $row['session_label'] ?? '' }}"
                                                class="form-control form-control-sm js-input-label"
                                                placeholder="Mis. Gelombang Pagi"
                                                required
                                            >
                                        </div>

                                        <div class="col-6 col-md-2">
                                            <label class="form-label small mb-1">Mulai</label>
                                            <input
                                                type="time"
                                                name="sessions[{{ $index }}][start_time]"
                                                value="{{ substr((string) ($row['start_time'] ?? '08:30'), 0, 5) }}"
                                                class="form-control form-control-sm dc-tabular js-input-start"
                                                required
                                            >
                                        </div>

                                        <div class="col-6 col-md-2">
                                            <label class="form-label small mb-1">Selesai</label>
                                            <input
                                                type="time"
                                                name="sessions[{{ $index }}][end_time]"
                                                value="{{ substr((string) ($row['end_time'] ?? '11:30'), 0, 5) }}"
                                                class="form-control form-control-sm dc-tabular js-input-end"
                                                required
                                            >
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label small mb-1">Kuota Pendaftar</label>
                                            <input
                                                type="number"
                                                min="5"
                                                max="500"
                                                name="sessions[{{ $index }}][quota_total]"
                                                value="{{ $row['quota_total'] ?? 40 }}"
                                                class="form-control form-control-sm dc-tabular js-input-quota"
                                                required
                                            >
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- Right Column: Live Target vs Quota Summary & Publication Control -->
                <div class="col-lg-4">
                    <div class="dc-card p-4" style="position: sticky; top: 5.25rem;">
                        <div class="dc-eyebrow">Ringkasan Perencanaan</div>
                        <h2 class="dc-title-3 mt-1 mb-3">Target &amp; Publikasi Acara</h2>

                        <div class="p-3 rounded-4 bg-danger-subtle bg-opacity-50 border border-danger-subtle mb-3">
                            <div class="d-flex align-items-center justify-content-between">
                                <span class="dc-caption text-dark">Target Kantong Mitra</span>
                                <span class="fw-bold text-danger dc-tabular"><span id="liveTargetBags">80</span> Kantong</span>
                            </div>
                            <hr class="my-2 opacity-25">
                            <div class="dc-caption text-dark">Total Kuota Sesi Dibuka</div>
                            <div class="dc-title-2 text-dark dc-tabular mb-0 mt-1">
                                <span id="liveTotalQuota">0</span> <span class="fs-6 fw-normal text-secondary">Kursi</span>
                            </div>
                            <div class="dc-caption dc-tabular mt-1">
                                Terbagi dalam <strong id="liveSessionCount">0</strong> gelombang waktu
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold">Status Publikasi Acara <span class="text-danger">*</span></label>

                            <div class="vstack gap-2">
                                <label class="p-3 rounded-4 border d-flex align-items-start gap-2 cursor-pointer dc-pressable {{ $initialPublication === 'published' ? 'border-danger bg-danger-subtle bg-opacity-10' : 'bg-light' }}">
                                    <input
                                        type="radio"
                                        name="publication_status"
                                        value="published"
                                        class="form-check-input mt-1 js-pub-radio"
                                        @checked($initialPublication === 'published')
                                    >
                                    <div>
                                        <div class="fw-semibold text-dark small">
                                            <i class="bi bi-broadcast text-success me-1"></i>Publikasikan Sekarang
                                        </div>
                                        <div class="dc-caption">
                                            Langsung tampil pada katalog jadwal &amp; peta pendonor untuk dipesan.
                                        </div>
                                    </div>
                                </label>

                                <label class="p-3 rounded-4 border d-flex align-items-start gap-2 cursor-pointer dc-pressable {{ $initialPublication === 'draft' ? 'border-danger bg-danger-subtle bg-opacity-10' : 'bg-light' }}">
                                    <input
                                        type="radio"
                                        name="publication_status"
                                        value="draft"
                                        class="form-check-input mt-1 js-pub-radio"
                                        @checked($initialPublication === 'draft')
                                    >
                                    <div>
                                        <div class="fw-semibold text-dark small">
                                            <i class="bi bi-file-earmark-lock2 text-warning-emphasis me-1"></i>Simpan sebagai Draf
                                        </div>
                                        <div class="dc-caption">
                                            Hanya terlihat oleh petugas UDD sambil menunggu konfirmasi akhir instansi mitra.
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <button type="submit" id="submitMobileEventBtn" class="btn btn-dc-primary btn-lg w-100">
                            <i class="bi bi-check2-circle"></i>
                            <span id="submitMobileEventLabel">
                                {{ $initialPublication === 'draft' ? 'Simpan Draf Agenda' : 'Terbitkan Agenda Mobile Unit' }}
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </form>
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
    // 1. Interactive Leaflet Pin Picker
    const latInput = document.getElementById('latitude');
    const lngInput = document.getElementById('longitude');
    const regionSelect = document.getElementById('region_id');
    const centerRegionBtn = document.getElementById('centerRegionMapBtn');
    const mapEl = document.getElementById('mobileUnitPickerMap');

    let map = null;
    let marker = null;

    const syncCoordsToInputs = (lat, lng) => {
        latInput.value = Number(lat).toFixed(7);
        lngInput.value = Number(lng).toFixed(7);
    };

    if (mapEl && typeof L !== 'undefined') {
        const startLat = parseFloat(latInput.value) || -6.9024812;
        const startLng = parseFloat(lngInput.value) || 107.6188100;

        map = L.map(mapEl).setView([startLat, startLng], 14);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors',
        }).addTo(map);

        marker = L.marker([startLat, startLng], { draggable: true }).addTo(map);
        marker.bindPopup('Titik lokasi armada Mobile Unit').openPopup();

        marker.on('dragend', () => {
            const pos = marker.getLatLng();
            syncCoordsToInputs(pos.lat, pos.lng);
        });

        map.on('click', (e) => {
            marker.setLatLng(e.latlng);
            syncCoordsToInputs(e.latlng.lat, e.latlng.lng);
        });

        const movePinFromInputs = () => {
            const parsedLat = parseFloat(latInput.value);
            const parsedLng = parseFloat(lngInput.value);
            if (!Number.isNaN(parsedLat) && !Number.isNaN(parsedLng)) {
                marker.setLatLng([parsedLat, parsedLng]);
                map.panTo([parsedLat, parsedLng]);
            }
        };

        latInput.addEventListener('change', movePinFromInputs);
        lngInput.addEventListener('change', movePinFromInputs);
    }

    const flyToSelectedRegion = () => {
        const selectedOption = regionSelect.options[regionSelect.selectedIndex];
        if (!selectedOption) return;
        const regLat = parseFloat(selectedOption.dataset.lat);
        const regLng = parseFloat(selectedOption.dataset.lng);
        if (Number.isNaN(regLat) || Number.isNaN(regLng)) return;

        syncCoordsToInputs(regLat, regLng);
        if (map && marker) {
            marker.setLatLng([regLat, regLng]);
            map.flyTo([regLat, regLng], 14, { duration: 0.45 });
        }
    };

    centerRegionBtn.addEventListener('click', flyToSelectedRegion);
    regionSelect.addEventListener('change', flyToSelectedRegion);

    // 2. Dynamic Sessions & Live Target vs Quota Summary
    const container = document.getElementById('sessionsContainer');
    const addBtn = document.getElementById('addSessionRowBtn');
    const presetBtn = document.getElementById('applyMobilePresetBtn');
    const targetInput = document.getElementById('target_bags');
    const liveTargetEl = document.getElementById('liveTargetBags');
    const liveTotalEl = document.getElementById('liveTotalQuota');
    const liveCountEl = document.getElementById('liveSessionCount');
    const submitLabelEl = document.getElementById('submitMobileEventLabel');

    const createRowHtml = (idx, label = '', start = '13:00', end = '15:30', quota = 30) => {
        const wrapper = document.createElement('div');
        wrapper.className = 'p-3 rounded-4 bg-light border js-session-row';
        wrapper.innerHTML = `
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="dc-eyebrow js-session-heading">Sesi #${idx + 1}</span>
                <button type="button" class="btn btn-sm text-danger p-0 px-2 js-remove-session" title="Hapus sesi ini">
                    <i class="bi bi-trash3"></i> Hapus
                </button>
            </div>
            <div class="row g-2 align-items-end">
                <div class="col-md-5">
                    <label class="form-label small mb-1">Nama Sesi</label>
                    <input type="text" class="form-control form-control-sm js-input-label" value="${label}" placeholder="Mis. Gelombang Tambahan" required>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1">Mulai</label>
                    <input type="time" class="form-control form-control-sm dc-tabular js-input-start" value="${start}" required>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1">Selesai</label>
                    <input type="time" class="form-control form-control-sm dc-tabular js-input-end" value="${end}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1">Kuota Pendaftar</label>
                    <input type="number" min="5" max="500" class="form-control form-control-sm dc-tabular js-input-quota" value="${quota}" required>
                </div>
            </div>
        `;
        return wrapper;
    };

    const reindexAndRecalculate = () => {
        const rows = Array.from(container.querySelectorAll('.js-session-row'));
        let totalQuota = 0;

        rows.forEach((row, idx) => {
            row.querySelector('.js-session-heading').textContent = `Sesi #${idx + 1}`;
            row.querySelector('.js-input-label').name = `sessions[${idx}][session_label]`;
            row.querySelector('.js-input-start').name = `sessions[${idx}][start_time]`;
            row.querySelector('.js-input-end').name = `sessions[${idx}][end_time]`;
            const quotaInput = row.querySelector('.js-input-quota');
            quotaInput.name = `sessions[${idx}][quota_total]`;
            totalQuota += Math.max(0, parseInt(quotaInput.value, 10) || 0);

            const removeBtn = row.querySelector('.js-remove-session');
            removeBtn.disabled = rows.length <= 1;
        });

        liveTotalEl.textContent = String(totalQuota);
        liveCountEl.textContent = String(rows.length);
        liveTargetEl.textContent = String(Math.max(0, parseInt(targetInput.value, 10) || 0));
    };

    addBtn.addEventListener('click', () => {
        const currentCount = container.querySelectorAll('.js-session-row').length;
        if (currentCount >= 8) return;
        container.appendChild(createRowHtml(currentCount, `Gelombang ${currentCount + 1} — Sesi Lanjutan`, '15:00', '16:30', 25));
        reindexAndRecalculate();
    });

    presetBtn.addEventListener('click', () => {
        container.innerHTML = '';
        const presets = [
            ['Gelombang 1 — Sesi Pagi Mitra', '08:30', '11:30', 45],
            ['Gelombang 2 — Sesi Siang Mitra', '12:30', '15:00', 35],
        ];
        presets.forEach((p, idx) => container.appendChild(createRowHtml(idx, p[0], p[1], p[2], p[3])));
        document.getElementById('opening_time').value = '08:30';
        document.getElementById('closing_time').value = '15:30';
        reindexAndRecalculate();
    });

    container.addEventListener('click', (e) => {
        const removeBtn = e.target.closest('.js-remove-session');
        if (!removeBtn) return;
        if (container.querySelectorAll('.js-session-row').length <= 1) return;
        removeBtn.closest('.js-session-row').remove();
        reindexAndRecalculate();
    });

    container.addEventListener('input', (e) => {
        if (e.target.classList.contains('js-input-quota')) {
            reindexAndRecalculate();
        }
    });

    targetInput.addEventListener('input', reindexAndRecalculate);

    document.querySelectorAll('.js-pub-radio').forEach((radio) => {
        radio.addEventListener('change', () => {
            submitLabelEl.textContent = radio.value === 'draft'
                ? 'Simpan Draf Agenda'
                : 'Terbitkan Agenda Mobile Unit';
        });
    });

    reindexAndRecalculate();
});
</script>
@endpush
