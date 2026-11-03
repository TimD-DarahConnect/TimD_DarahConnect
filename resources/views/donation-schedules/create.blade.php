@extends('layouts.app')

@section('title', 'Buat Master Jadwal Harian Gedung UDD')

@section('content')
<section class="py-4 py-lg-5">
    <div class="container" style="max-width: 68rem;">
        <!-- Header -->
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
            <div>
                <nav aria-label="breadcrumb" class="mb-1">
                    <ol class="breadcrumb mb-0 dc-caption">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-secondary">Ruang Kerja</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('schedules.index') }}" class="text-decoration-none text-secondary">Master Jadwal Gedung UDD</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Buat Jadwal Baru</li>
                    </ol>
                </nav>
                <h1 class="dc-title-1 mb-1">Buat Master Jadwal &amp; Sesi Gedung UDD</h1>
                <p class="dc-caption mb-0">
                    Tentukan tanggal layanan, jam operasional gedung, serta pembagian sesi dan kuota kursi pendonor harian.
                </p>
            </div>

            <a href="{{ route('schedules.index') }}" class="btn btn-dc-secondary">
                <i class="bi bi-arrow-left"></i>
                Kembali
            </a>
        </div>

        @if ($errors->any())
            <div class="dc-card p-3 px-4 mb-4 border-danger-subtle bg-danger-subtle text-danger-emphasis" role="alert">
                <div class="fw-semibold mb-1"><i class="bi bi-exclamation-octagon-fill me-1"></i>Terdapat kesalahan pada pengaturan jadwal atau sesi:</div>
                <ul class="mb-0 small ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @php
            $sessionRows = old('sessions', $defaultSessions);
        @endphp

        <form method="POST" action="{{ route('schedules.store') }}">
            @csrf

            <div class="row g-4 align-items-start">
                <!-- Left Column: Building Operational Parameters & Dynamic Session Builder -->
                <div class="col-lg-8">
                    <div class="dc-card p-4 p-md-5 mb-4">
                        <div class="dc-eyebrow mb-1">Langkah 01</div>
                        <h2 class="dc-title-3 mb-3">Identitas Gedung &amp; Jam Operasional Harian</h2>

                        <div class="row g-3">
                            <div class="col-md-7">
                                <label for="facility_id" class="form-label">Gedung Unit Donor Darah (UDD) <span class="text-danger">*</span></label>
                                <select
                                    id="facility_id"
                                    name="facility_id"
                                    class="form-select @error('facility_id') is-invalid @enderror"
                                    required
                                >
                                    @foreach ($uddFacilities as $udd)
                                        <option value="{{ $udd->id }}" @selected((int) old('facility_id', $selectedFacilityId) === $udd->id)>
                                            {{ $udd->name }} ({{ $udd->region?->name }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('facility_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-5">
                                <label for="service_date" class="form-label">Tanggal Layanan <span class="text-danger">*</span></label>
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

                            <div class="col-12">
                                <label for="title" class="form-label">Judul Layanan Harian <span class="text-danger">*</span></label>
                                <input
                                    type="text"
                                    id="title"
                                    name="title"
                                    maxlength="160"
                                    value="{{ old('title', 'Pelayanan Donor Darah Harian Gedung UDD PMI') }}"
                                    class="form-control @error('title') is-invalid @enderror"
                                    required
                                >
                                @error('title')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-sm-6">
                                <label for="opening_time" class="form-label">Jam Buka Gedung <span class="text-danger">*</span></label>
                                <input
                                    type="time"
                                    id="opening_time"
                                    name="opening_time"
                                    value="{{ old('opening_time', '08:00') }}"
                                    class="form-control dc-tabular @error('opening_time') is-invalid @enderror"
                                    required
                                >
                                @error('opening_time')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-sm-6">
                                <label for="closing_time" class="form-label">Jam Tutup Gedung <span class="text-danger">*</span></label>
                                <input
                                    type="time"
                                    id="closing_time"
                                    name="closing_time"
                                    value="{{ old('closing_time', '18:00') }}"
                                    class="form-control dc-tabular @error('closing_time') is-invalid @enderror"
                                    required
                                >
                                @error('closing_time')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12">
                                <label for="notes" class="form-label">Catatan Persiapan Layanan (Opsional)</label>
                                <textarea
                                    id="notes"
                                    name="notes"
                                    rows="2"
                                    maxlength="600"
                                    class="form-control @error('notes') is-invalid @enderror"
                                    placeholder="Contoh: Tersedia 6 kursi flebotomi aktif. Pendonor diharapkan hadir 15 menit sebelum sesi dimulai."
                                >{{ old('notes') }}</textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Dynamic Session Slots Card -->
                    <div class="dc-card p-4 p-md-5">
                        <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2 mb-3">
                            <div>
                                <div class="dc-eyebrow mb-1">Langkah 02</div>
                                <h2 class="dc-title-3 mb-0">Pembagian Sesi Jam &amp; Kuota Pendonor</h2>
                            </div>

                            <div class="d-flex flex-wrap gap-2">
                                <button type="button" id="applyStandardPresetBtn" class="btn btn-dc-secondary btn-sm">
                                    <i class="bi bi-magic"></i>
                                    Preset 3 Sesi
                                </button>
                                <button type="button" id="addSessionRowBtn" class="btn btn-dc-soft btn-sm">
                                    <i class="bi bi-plus-lg"></i>
                                    Tambah Sesi
                                </button>
                            </div>
                        </div>

                        <p class="dc-caption mb-3">
                            Pastikan jam antar-sesi tidak saling bertumpukan dan tetap berada di dalam rentang jam operasional gedung.
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
                                                placeholder="Mis. Sesi Pagi"
                                                required
                                            >
                                        </div>

                                        <div class="col-6 col-md-2">
                                            <label class="form-label small mb-1">Mulai</label>
                                            <input
                                                type="time"
                                                name="sessions[{{ $index }}][start_time]"
                                                value="{{ substr((string) ($row['start_time'] ?? '08:00'), 0, 5) }}"
                                                class="form-control form-control-sm dc-tabular js-input-start"
                                                required
                                            >
                                        </div>

                                        <div class="col-6 col-md-2">
                                            <label class="form-label small mb-1">Selesai</label>
                                            <input
                                                type="time"
                                                name="sessions[{{ $index }}][end_time]"
                                                value="{{ substr((string) ($row['end_time'] ?? '11:00'), 0, 5) }}"
                                                class="form-control form-control-sm dc-tabular js-input-end"
                                                required
                                            >
                                        </div>

                                        <div class="col-md-3">
                                            <label class="form-label small mb-1">Kuota Pendonor</label>
                                            <input
                                                type="number"
                                                min="5"
                                                max="300"
                                                name="sessions[{{ $index }}][quota_total]"
                                                value="{{ $row['quota_total'] ?? 25 }}"
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

                <!-- Right Column: Live Summary & Publish CTA -->
                <div class="col-lg-4">
                    <div class="dc-card p-4" style="position: sticky; top: 5.25rem;">
                        <div class="dc-eyebrow">Ringkasan Kapasitas Harian</div>
                        <h2 class="dc-title-3 mt-1 mb-3">Rekapitulasi Kuota</h2>

                        <div class="p-3 rounded-4 bg-danger-subtle bg-opacity-50 border border-danger-subtle mb-3">
                            <div class="dc-caption text-dark">Total Kuota Harian Gedung</div>
                            <div class="dc-title-1 text-danger dc-tabular mb-0 mt-1">
                                <span id="liveTotalQuota">0</span> <span class="fs-6 fw-normal text-dark">Pendonor</span>
                            </div>
                            <div class="dc-caption dc-tabular mt-1">
                                Terbagi dalam <strong id="liveSessionCount">0</strong> sesi waktu pelayanan
                            </div>
                        </div>

                        <ul class="list-unstyled vstack gap-2 small text-secondary mb-4">
                            <li class="d-flex align-items-start gap-2">
                                <i class="bi bi-check2-circle text-success mt-1"></i>
                                <span>Seluruh sesi baru otomatis dibuka dengan status <strong>Dibuka Normal</strong>.</span>
                            </li>
                            <li class="d-flex align-items-start gap-2">
                                <i class="bi bi-check2-circle text-success mt-1"></i>
                                <span>Kuota sesi langsung dapat dipesan oleh akun Pendonor pada direktori jadwal.</span>
                            </li>
                        </ul>

                        <button type="submit" class="btn btn-dc-primary btn-lg w-100">
                            <i class="bi bi-calendar2-check-fill"></i>
                            Terbitkan Master Jadwal
                        </button>
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
    const container = document.getElementById('sessionsContainer');
    const addBtn = document.getElementById('addSessionRowBtn');
    const presetBtn = document.getElementById('applyStandardPresetBtn');
    const liveTotalEl = document.getElementById('liveTotalQuota');
    const liveCountEl = document.getElementById('liveSessionCount');

    const createRowHtml = (idx, label = '', start = '15:00', end = '17:30', quota = 20) => {
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
                    <input type="text" class="form-control form-control-sm js-input-label" value="${label}" placeholder="Mis. Sesi Tambahan" required>
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
                    <label class="form-label small mb-1">Kuota Pendonor</label>
                    <input type="number" min="5" max="300" class="form-control form-control-sm dc-tabular js-input-quota" value="${quota}" required>
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
    };

    addBtn.addEventListener('click', () => {
        const currentCount = container.querySelectorAll('.js-session-row').length;
        if (currentCount >= 8) return;
        container.appendChild(createRowHtml(currentCount, `Sesi ${currentCount + 1} — Pelayanan Lanjutan`, '18:15', '20:00', 15));
        reindexAndRecalculate();
    });

    presetBtn.addEventListener('click', () => {
        container.innerHTML = '';
        const presets = [
            ['Sesi 1 — Pelayanan Pagi', '08:00', '11:00', 25],
            ['Sesi 2 — Pelayanan Siang', '11:30', '14:30', 25],
            ['Sesi 3 — Pelayanan Sore', '15:00', '18:00', 20],
        ];
        presets.forEach((p, idx) => container.appendChild(createRowHtml(idx, p[0], p[1], p[2], p[3])));
        document.getElementById('opening_time').value = '08:00';
        document.getElementById('closing_time').value = '18:00';
        reindexAndRecalculate();
    });

    container.addEventListener('click', (e) => {
        const removeBtn = e.target.closest('.js-remove-session');
        if (!removeBtn) return;
        const rows = container.querySelectorAll('.js-session-row');
        if (rows.length <= 1) return;
        removeBtn.closest('.js-session-row').remove();
        reindexAndRecalculate();
    });

    container.addEventListener('input', (e) => {
        if (e.target.classList.contains('js-input-quota')) {
            reindexAndRecalculate();
        }
    });

    reindexAndRecalculate();
});
</script>
@endpush
