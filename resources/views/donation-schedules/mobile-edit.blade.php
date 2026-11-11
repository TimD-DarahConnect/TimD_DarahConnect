@extends('layouts.app')

@section('title', 'Edit Agenda Mobile Unit ' . $schedule->schedule_code)

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
                        <li class="breadcrumb-item"><a href="{{ route('schedules.show', $schedule) }}" class="text-decoration-none text-secondary dc-tabular">{{ $schedule->schedule_code }}</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Edit Agenda</li>
                    </ol>
                </nav>
                <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                    <span class="dc-pill {{ $schedule->publication_pill_class }}">
                        <i class="bi {{ $schedule->isPublished() ? 'bi-broadcast' : 'bi-file-earmark-lock2' }}"></i>
                        {{ $schedule->publication_label }}
                    </span>
                    <span class="dc-caption dc-tabular fw-semibold">{{ $schedule->schedule_code }}</span>
                </div>
                <h1 class="dc-title-1 mb-1">Perbarui Agenda Mobile Unit Mitra</h1>
                <p class="dc-caption mb-0">
                    Sesuaikan rincian instansi mitra, titik lokasi pada peta, target perolehan kantong darah, atau status publikasi acara.
                </p>
            </div>

            <a href="{{ route('schedules.show', $schedule) }}" class="btn btn-dc-secondary">
                <i class="bi bi-arrow-left"></i>
                Kembali ke Detail
            </a>
        </div>

        @if ($errors->any())
            <div class="dc-card p-3 px-4 mb-4 border-danger-subtle bg-danger-subtle text-danger-emphasis" role="alert">
                <div class="fw-semibold mb-1"><i class="bi bi-exclamation-octagon-fill me-1"></i>Terdapat kendala validasi pada pembaruan agenda:</div>
                <ul class="mb-0 small ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @php
            $currentPublication = old('publication_status', $schedule->publication_status);
        @endphp

        <form method="POST" action="{{ route('schedules.mobile.update', $schedule) }}">
            @csrf
            @method('PUT')

            <div class="row g-4 align-items-start">
                <div class="col-lg-8">
                    <!-- Partner & Event Identity -->
                    <div class="dc-card p-4 p-md-5 mb-4">
                        <div class="dc-eyebrow mb-1">Informasi Mitra &amp; Waktu Acara</div>
                        <h2 class="dc-title-3 mb-3">Identitas Kegiatan Donor Eksternal</h2>

                        <div class="row g-3">
                            <div class="col-md-7">
                                <label class="form-label">Unit UDD Penyelenggara</label>
                                <input
                                    type="text"
                                    class="form-control bg-light"
                                    value="{{ $schedule->facility?->name }}"
                                    disabled
                                >
                            </div>

                            <div class="col-md-5">
                                <label for="service_date" class="form-label">Tanggal Pelaksanaan <span class="text-danger">*</span></label>
                                <input
                                    type="date"
                                    id="service_date"
                                    name="service_date"
                                    min="{{ today()->toDateString() }}"
                                    value="{{ old('service_date', $schedule->service_date->toDateString()) }}"
                                    class="form-control dc-tabular @error('service_date') is-invalid @enderror"
                                    @readonly($hasActiveBookings)
                                    required
                                >
                                @if ($hasActiveBookings)
                                    <div class="dc-caption mt-1 text-warning-emphasis">
                                        Tanggal dikunci karena sudah terdapat pendonor terdaftar.
                                    </div>
                                @endif
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
                                    value="{{ old('partner_institution', $schedule->partner_institution) }}"
                                    class="form-control @error('partner_institution') is-invalid @enderror"
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
                                        value="{{ old('target_bags', $schedule->target_bags) }}"
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
                                    value="{{ old('title', $schedule->title) }}"
                                    class="form-control @error('title') is-invalid @enderror"
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
                                    value="{{ old('opening_time', substr((string) $schedule->opening_time, 0, 5)) }}"
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
                                    value="{{ old('closing_time', substr((string) $schedule->closing_time, 0, 5)) }}"
                                    class="form-control dc-tabular @error('closing_time') is-invalid @enderror"
                                    required
                                >
                                @error('closing_time')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Map Coordinates & Venue -->
                    <div class="dc-card p-4 p-md-5">
                        <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2 mb-3">
                            <div>
                                <div class="dc-eyebrow mb-1">Geolokasi Mitra</div>
                                <h2 class="dc-title-3 mb-0">Alamat &amp; Titik Koordinat Peta</h2>
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
                                            @selected((int) old('region_id', $schedule->region_id) === $region->id)
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
                                    value="{{ old('venue_name', $schedule->venue_name) }}"
                                    class="form-control @error('venue_name') is-invalid @enderror"
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
                                    value="{{ old('venue_address', $schedule->venue_address) }}"
                                    class="form-control @error('venue_address') is-invalid @enderror"
                                    required
                                >
                                @error('venue_address')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="p-2 rounded-4 bg-light border mb-3">
                            <div class="px-2 py-1">
                                <span class="dc-caption">
                                    <i class="bi bi-geo-alt-fill text-danger me-1"></i>
                                    Klik pada peta atau geser pin untuk memperbarui titik koordinat lokasi acara mitra.
                                </span>
                            </div>
                            <div id="mobileUnitPickerMap" class="rounded-3 border bg-white" style="height: 18.5rem; z-index: 1;" aria-label="Peta pembaruan lokasi Mobile Unit"></div>
                        </div>

                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label for="latitude" class="form-label small">Koordinat Lintang (Latitude) <span class="text-danger">*</span></label>
                                <input
                                    type="number"
                                    step="0.0000001"
                                    id="latitude"
                                    name="latitude"
                                    value="{{ old('latitude', number_format((float) ($schedule->latitude ?? -6.9024812), 7, '.', '')) }}"
                                    class="form-control form-control-sm dc-tabular @error('latitude') is-invalid @enderror"
                                    required
                                >
                            </div>

                            <div class="col-sm-6">
                                <label for="longitude" class="form-label small">Koordinat Bujur (Longitude) <span class="text-danger">*</span></label>
                                <input
                                    type="number"
                                    step="0.0000001"
                                    id="longitude"
                                    name="longitude"
                                    value="{{ old('longitude', number_format((float) ($schedule->longitude ?? 107.6188100), 7, '.', '')) }}"
                                    class="form-control form-control-sm dc-tabular @error('longitude') is-invalid @enderror"
                                    required
                                >
                            </div>

                            <div class="col-12">
                                <label for="notes" class="form-label">Catatan Teknis &amp; Informasi Peserta (Opsional)</label>
                                <textarea
                                    id="notes"
                                    name="notes"
                                    rows="2"
                                    maxlength="600"
                                    class="form-control @error('notes') is-invalid @enderror"
                                >{{ old('notes', $schedule->notes) }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Publication Control & Existing Sessions Info -->
                <div class="col-lg-4">
                    <div class="dc-card p-4" style="position: sticky; top: 5.25rem;">
                        <div class="dc-eyebrow">Kontrol Publikasi</div>
                        <h2 class="dc-title-3 mt-1 mb-3">Status Tayang &amp; Ringkasan Sesi</h2>

                        <div class="p-3 rounded-4 bg-light border mb-3 small">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-secondary">Sesi Terdaftar</span>
                                <strong class="dc-tabular text-dark">{{ $schedule->sessions->count() }} Gelombang</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-secondary">Kapasitas Kuota Sesi</span>
                                <strong class="dc-tabular text-dark">{{ $schedule->total_quota }} Kursi</strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-secondary">Pendaftar Terkonfirmasi</span>
                                <strong class="dc-tabular text-success">{{ $schedule->total_booked }} Pendonor</strong>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold">Status Publikasi Acara <span class="text-danger">*</span></label>
                            <div class="vstack gap-2">
                                <label class="p-3 rounded-4 border d-flex align-items-start gap-2 cursor-pointer dc-pressable bg-light">
                                    <input
                                        type="radio"
                                        name="publication_status"
                                        value="published"
                                        class="form-check-input mt-1"
                                        @checked($currentPublication === 'published')
                                    >
                                    <div>
                                        <div class="fw-semibold text-dark small">
                                            <i class="bi bi-broadcast text-success me-1"></i>Dipublikasikan
                                        </div>
                                        <div class="dc-caption">Tampil pada katalog jadwal &amp; peta pendonor.</div>
                                    </div>
                                </label>

                                <label class="p-3 rounded-4 border d-flex align-items-start gap-2 {{ $hasActiveBookings ? 'opacity-50' : 'cursor-pointer dc-pressable' }} bg-light">
                                    <input
                                        type="radio"
                                        name="publication_status"
                                        value="draft"
                                        class="form-check-input mt-1"
                                        @checked($currentPublication === 'draft')
                                        @disabled($hasActiveBookings)
                                    >
                                    <div>
                                        <div class="fw-semibold text-dark small">
                                            <i class="bi bi-file-earmark-lock2 text-warning-emphasis me-1"></i>Draf Internal
                                        </div>
                                        <div class="dc-caption">
                                            {{ $hasActiveBookings ? 'Tidak dapat dikembalikan ke draf karena sudah ada pendaftar aktif.' : 'Disembunyikan sementara dari publik.' }}
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-dc-primary btn-lg w-100">
                            <i class="bi bi-check2-circle"></i>
                            Simpan Perubahan Agenda
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

        map = L.map(mapEl).setView([startLat, startLng], 15);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors',
        }).addTo(map);

        marker = L.marker([startLat, startLng], { draggable: true }).addTo(map);
        marker.bindPopup('<strong>{{ addslashes($schedule->venue_name) }}</strong>').openPopup();

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

    centerRegionBtn.addEventListener('click', () => {
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
    });
});
</script>
@endpush
