@extends('layouts.app')

@section('title', 'Daftar Akun Baru')

@push('styles')
<style>
    .dc-choice-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 0.6rem;
    }

    .dc-choice-pill {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 2.9rem;
        border-radius: var(--dc-radius-md);
        border: 1.5px solid var(--dc-border-strong);
        background: var(--dc-surface);
        color: var(--dc-ink);
        font-weight: 700;
        font-size: 1rem;
        cursor: pointer;
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
        box-shadow: 0 6px 16px -4px rgba(220, 38, 38, 0.38);
    }

    .dc-choice-input:focus-visible + .dc-choice-pill {
        outline: 2px solid var(--dc-crimson);
        outline-offset: 2px;
    }

    .dc-donor-panel {
        overflow: hidden;
        transition: opacity 220ms ease;
    }

    .dc-donor-panel.is-collapsed {
        display: none;
    }

    .dc-rule-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        font-size: 0.78rem;
        color: var(--dc-muted);
        transition: color 150ms ease;
    }

    .dc-rule-chip.is-valid {
        color: var(--dc-success);
        font-weight: 600;
    }
</style>
@endpush

@section('content')
@php
    $selectedRole = old('role', 'pendonor');
    $selectedBlood = old('blood_type', 'O');
    $selectedRhesus = old('rhesus', '+');
@endphp

<section class="py-4 py-lg-5">
    <div class="container">
        @if (session('registered_account'))
            @php($registered = session('registered_account'))
            <div class="dc-card p-4 mb-4 border-success border-opacity-25">
                <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                    <div class="d-flex align-items-start gap-3">
                        <div class="rounded-4 bg-success-subtle text-success d-inline-flex align-items-center justify-content-center flex-shrink-0" style="width: 3rem; height: 3rem;">
                            <i class="bi bi-patch-check-fill fs-4"></i>
                        </div>
                        <div>
                            <div class="dc-eyebrow text-success">Akun Resmi Terdaftar</div>
                            <h2 class="dc-title-3 mb-1">{{ $registered['name'] }} ({{ $registered['role_label'] }})</h2>
                            <div class="dc-caption">
                                Email: <strong class="text-dark">{{ $registered['email'] }}</strong>
                                @if (!empty($registered['region']))
                                    &middot; Domisili: <strong class="text-dark">{{ $registered['region'] }}</strong>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        @if (!empty($registered['blood_group']))
                            <span class="dc-blood-badge">{{ $registered['blood_group'] }}</span>
                        @endif
                        @if (Route::has('auth.login'))
                            <a href="{{ route('auth.login') }}" class="btn btn-dc-primary">
                                Masuk ke Akun <i class="bi bi-arrow-right"></i>
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        <div class="row g-4 g-xl-5">
            <!-- Left Column: Context, Role Summary & Privacy Guarantee -->
            <div class="col-lg-5 order-2 order-lg-1">
                <div class="dc-card p-4 position-sticky" style="top: 5.5rem;">
                    <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
                        <div>
                            <div class="dc-eyebrow">Akses Multi-Peran</div>
                            <h1 class="dc-title-2 mb-0 mt-1" id="roleContextHeading">
                                {{ $selectedRole === 'pencari_darah' ? 'Akun Pencari Darah' : 'Akun Relawan Pendonor' }}
                            </h1>
                        </div>
                        <span class="dc-blood-badge {{ $selectedRole === 'pencari_darah' ? 'd-none' : '' }}" id="liveBloodPreview">
                            {{ $selectedBlood }}{{ $selectedRhesus }}
                        </span>
                    </div>

                    <p class="dc-caption mb-4" id="roleContextDesc">
                        {{ $selectedRole === 'pencari_darah'
                            ? 'Ajukan permohonan transfusi darurat bagi keluarga/pasien dengan verifikasi UDD resmi serta pantau kedatangan pendonor secara terukur.'
                            : 'Terima rekomendasi kebutuhan darah kritis yang kompatibel di sekitar Anda, atau pesan sesi donor terjadwal di Gedung UDD PMI & Mobile Unit.' }}
                    </p>

                    <div id="roleFeatureListDonor" class="vstack gap-2 mb-4 {{ $selectedRole === 'pencari_darah' ? 'd-none' : '' }}">
                        <div class="p-3 rounded-4 bg-light border d-flex align-items-center gap-3">
                            <i class="bi bi-stars text-danger fs-5"></i>
                            <div class="small">
                                <span class="fw-semibold d-block">Pencocokan Biologis Cerdas</span>
                                <span class="dc-caption">Rekomendasi otomatis sesuai golongan ABO/Rhesus &amp; jarak faskes.</span>
                            </div>
                        </div>
                        <div class="p-3 rounded-4 bg-light border d-flex align-items-center gap-3">
                            <i class="bi bi-calendar2-check-fill text-danger fs-5"></i>
                            <div class="small">
                                <span class="fw-semibold d-block">Booking Jadwal &amp; Skrining Mandiri</span>
                                <span class="dc-caption">Pesan kuota sesi harian UDD dengan tiket digital ber-QR Code.</span>
                            </div>
                        </div>
                    </div>

                    <div id="roleFeatureListSeeker" class="vstack gap-2 mb-4 {{ $selectedRole === 'pencari_darah' ? '' : 'd-none' }}">
                        <div class="p-3 rounded-4 bg-light border d-flex align-items-center gap-3">
                            <i class="bi bi-file-medical-fill text-danger fs-5"></i>
                            <div class="small">
                                <span class="fw-semibold d-block">Pengajuan Darurat Terverifikasi</span>
                                <span class="dc-caption">Simpan draf atau kirim surat rujukan dokter langsung ke antrean UDD.</span>
                            </div>
                        </div>
                        <div class="p-3 rounded-4 bg-light border d-flex align-items-center gap-3">
                            <i class="bi bi-shield-lock-fill text-danger fs-5"></i>
                            <div class="small">
                                <span class="fw-semibold d-block">Koordinasi Titik Temu Aman</span>
                                <span class="dc-caption">Konfirmasi kehadiran pendonor di BDRS tanpa membocorkan nomor HP pribadi.</span>
                            </div>
                        </div>
                    </div>

                    <div class="p-3 rounded-4 border bg-white">
                        <div class="d-flex align-items-start gap-2">
                            <i class="bi bi-lock-fill text-secondary mt-1"></i>
                            <div class="dc-caption">
                                <strong class="text-dark">Jaminan Privasi Medis:</strong> Kata sandi dienkripsi penuh dan nomor ponsel Anda tidak pernah ditampilkan secara terbuka ke publik. Akun petugas UDD &amp; RS diterbitkan secara khusus oleh administrator mitra.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Registration Form -->
            <div class="col-lg-7 order-1 order-lg-2">
                <div class="dc-card p-4 p-md-5">
                    <div class="mb-4">
                        <div class="dc-eyebrow">Pendaftaran Resmi</div>
                        <h2 class="dc-title-1 mt-1 mb-1">Buat Akun DarahConnect</h2>
                        <p class="dc-caption mb-0">
                            Pilih peran Anda terlebih dahulu, lalu lengkapi kredensial yang valid.
                            @if (Route::has('auth.login'))
                                Sudah memiliki akun? <a href="{{ route('auth.login') }}" class="text-danger fw-semibold text-decoration-none">Masuk di sini</a>.
                            @endif
                        </p>
                    </div>

                    @if ($errors->any())
                        <div class="p-3 mb-4 rounded-4 bg-danger-subtle border border-danger-subtle text-danger-emphasis" role="alert">
                            <div class="fw-semibold small mb-1">
                                <i class="bi bi-exclamation-circle-fill me-1"></i>Mohon periksa kembali isian formulir Anda:
                            </div>
                            <ul class="mb-0 ps-3 small">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form action="{{ route('auth.register.store') }}" method="POST" id="registerForm" novalidate>
                        @csrf
                        <input type="hidden" name="role" id="roleInput" value="{{ $selectedRole }}">

                        <!-- 1. Role Switcher (Fluid Segmented Control) -->
                        <div class="mb-4">
                            <label class="form-label d-block">Daftar Sebagai</label>
                            <div class="dc-segmented w-100" data-dc-segmented id="roleSegmented" role="tablist" aria-label="Pilih Peran Pendaftaran">
                                <button
                                    type="button"
                                    class="dc-segmented-btn flex-fill py-2 {{ $selectedRole === 'pendonor' ? 'active' : '' }}"
                                    role="tab"
                                    aria-selected="{{ $selectedRole === 'pendonor' ? 'true' : 'false' }}"
                                    data-value="pendonor"
                                >
                                    <i class="bi bi-droplet-half me-1"></i> Pendonor Sukarela
                                </button>
                                <button
                                    type="button"
                                    class="dc-segmented-btn flex-fill py-2 {{ $selectedRole === 'pencari_darah' ? 'active' : '' }}"
                                    role="tab"
                                    aria-selected="{{ $selectedRole === 'pencari_darah' ? 'true' : 'false' }}"
                                    data-value="pencari_darah"
                                >
                                    <i class="bi bi-heart-pulse me-1"></i> Pencari Darah
                                </button>
                            </div>
                            @error('role')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- 2. Identity & Region -->
                        <div class="mb-4">
                            <div class="dc-eyebrow text-secondary mb-3">1. Identitas &amp; Domisili</div>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label for="name" class="form-label">Nama Lengkap (Sesuai KTP)</label>
                                    <input
                                        type="text"
                                        id="name"
                                        name="name"
                                        value="{{ old('name') }}"
                                        class="form-control @error('name') is-invalid @enderror"
                                        placeholder="Contoh: Budi Santoso"
                                        autocomplete="name"
                                        required
                                    >
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6">
                                    <label for="phone" class="form-label">Nomor Ponsel Aktif (WhatsApp/SMS)</label>
                                    <input
                                        type="tel"
                                        id="phone"
                                        name="phone"
                                        value="{{ old('phone') }}"
                                        class="form-control dc-tabular @error('phone') is-invalid @enderror"
                                        placeholder="081234567890"
                                        autocomplete="tel"
                                        required
                                    >
                                    @error('phone')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6">
                                    <label for="region_id" class="form-label">Kabupaten / Kota Domisili</label>
                                    <select
                                        id="region_id"
                                        name="region_id"
                                        class="form-select @error('region_id') is-invalid @enderror"
                                        required
                                    >
                                        <option value="">Pilih wilayah domisili...</option>
                                        @foreach ($regionsByProvince as $province => $regions)
                                            <optgroup label="{{ $province }}">
                                                @foreach ($regions as $region)
                                                    <option value="{{ $region->id }}" @selected((int) old('region_id') === $region->id)>
                                                        {{ $region->name }}
                                                    </option>
                                                @endforeach
                                            </optgroup>
                                        @endforeach
                                    </select>
                                    @error('region_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-12">
                                    <label for="address" class="form-label">Alamat Singkat Domisili <span class="text-muted fw-normal">(Opsional)</span></label>
                                    <input
                                        type="text"
                                        id="address"
                                        name="address"
                                        value="{{ old('address') }}"
                                        class="form-control @error('address') is-invalid @enderror"
                                        placeholder="Kecamatan / kelurahan tempat tinggal saat ini"
                                        autocomplete="street-address"
                                    >
                                    @error('address')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <!-- 3. Donor Biological Profile (Conditional for Pendonor) -->
                        <div id="donorProfileSection" class="dc-donor-panel mb-4 p-3 p-md-4 rounded-4 bg-light border {{ $selectedRole === 'pencari_darah' ? 'is-collapsed' : '' }}">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div class="dc-eyebrow mb-0">2. Profil Biologis Pendonor</div>
                                <span class="dc-caption">Digunakan untuk pencocokan transfusi</span>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-7">
                                    <label class="form-label">Golongan Darah ABO</label>
                                    <div class="dc-choice-grid" role="radiogroup" aria-label="Golongan Darah ABO">
                                        @foreach ($bloodTypes as $type)
                                            <div>
                                                <input
                                                    type="radio"
                                                    class="visually-hidden dc-choice-input donor-field"
                                                    name="blood_type"
                                                    id="blood_type_{{ $type->value }}"
                                                    value="{{ $type->value }}"
                                                    @checked($selectedBlood === $type->value)
                                                >
                                                <label class="dc-choice-pill dc-pressable" for="blood_type_{{ $type->value }}">
                                                    {{ $type->value }}
                                                </label>
                                            </div>
                                        @endforeach
                                    </div>
                                    @error('blood_type')
                                        <div class="text-danger small mt-1">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-5">
                                    <label class="form-label">Faktor Rhesus</label>
                                    <div class="row g-2" role="radiogroup" aria-label="Faktor Rhesus">
                                        @foreach ($rhesusTypes as $rh)
                                            <div class="col-6">
                                                <input
                                                    type="radio"
                                                    class="visually-hidden dc-choice-input donor-field"
                                                    name="rhesus"
                                                    id="rhesus_{{ $rh->name }}"
                                                    value="{{ $rh->value }}"
                                                    @checked($selectedRhesus === $rh->value)
                                                >
                                                <label class="dc-choice-pill dc-pressable" for="rhesus_{{ $rh->name }}">
                                                    Rh {{ $rh->value }}
                                                </label>
                                            </div>
                                        @endforeach
                                    </div>
                                    @error('rhesus')
                                        <div class="text-danger small mt-1">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-4">
                                    <label for="gender" class="form-label">Jenis Kelamin</label>
                                    <select id="gender" name="gender" class="form-select donor-field @error('gender') is-invalid @enderror">
                                        <option value="">Pilih...</option>
                                        <option value="L" @selected(old('gender') === 'L')>Laki-laki</option>
                                        <option value="P" @selected(old('gender') === 'P')>Perempuan</option>
                                    </select>
                                    @error('gender')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-5">
                                    <label for="birth_date" class="form-label">Tanggal Lahir</label>
                                    <input
                                        type="date"
                                        id="birth_date"
                                        name="birth_date"
                                        value="{{ old('birth_date') }}"
                                        max="{{ now()->subYears(17)->toDateString() }}"
                                        class="form-control dc-tabular donor-field @error('birth_date') is-invalid @enderror"
                                    >
                                    @error('birth_date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @else
                                        <div class="dc-caption mt-1">Minimal usia 17 tahun.</div>
                                    @enderror
                                </div>

                                <div class="col-md-3">
                                    <label for="weight_kg" class="form-label">Berat (kg)</label>
                                    <input
                                        type="number"
                                        id="weight_kg"
                                        name="weight_kg"
                                        value="{{ old('weight_kg') }}"
                                        min="45"
                                        max="250"
                                        placeholder="55"
                                        class="form-control dc-tabular donor-field @error('weight_kg') is-invalid @enderror"
                                    >
                                    @error('weight_kg')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @else
                                        <div class="dc-caption mt-1">Min. 45 kg.</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <!-- 4. Account Credentials -->
                        <div class="mb-4">
                            <div class="dc-eyebrow text-secondary mb-3" id="credentialStepLabel">
                                {{ $selectedRole === 'pencari_darah' ? '2. Kredensial Akun' : '3. Kredensial Akun' }}
                            </div>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label for="email" class="form-label">Alamat Email</label>
                                    <input
                                        type="email"
                                        id="email"
                                        name="email"
                                        value="{{ old('email') }}"
                                        class="form-control @error('email') is-invalid @enderror"
                                        placeholder="nama@email.com"
                                        autocomplete="email"
                                        required
                                    >
                                    @error('email')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6">
                                    <label for="password" class="form-label">Kata Sandi</label>
                                    <div class="input-group">
                                        <input
                                            type="password"
                                            id="password"
                                            name="password"
                                            class="form-control @error('password') is-invalid @enderror"
                                            placeholder="Min. 8 karakter huruf & angka"
                                            autocomplete="new-password"
                                            required
                                        >
                                        <button type="button" class="btn btn-dc-secondary px-3" id="togglePasswordBtn" aria-label="Tampilkan atau sembunyikan kata sandi">
                                            <i class="bi bi-eye" id="togglePasswordIcon"></i>
                                        </button>
                                    </div>
                                    @error('password')
                                        <div class="text-danger small mt-1">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6">
                                    <label for="password_confirmation" class="form-label">Konfirmasi Kata Sandi</label>
                                    <input
                                        type="password"
                                        id="password_confirmation"
                                        name="password_confirmation"
                                        class="form-control"
                                        placeholder="Ulangi kata sandi"
                                        autocomplete="new-password"
                                        required
                                    >
                                </div>

                                <!-- Inline Real-Time Validation Checklist (§16) -->
                                <div class="col-12">
                                    <div class="d-flex flex-wrap gap-3 pt-1">
                                        <span class="dc-rule-chip" id="ruleLength">
                                            <i class="bi bi-circle"></i> Min. 8 karakter
                                        </span>
                                        <span class="dc-rule-chip" id="ruleAlnum">
                                            <i class="bi bi-circle"></i> Kombinasi huruf &amp; angka
                                        </span>
                                        <span class="dc-rule-chip" id="ruleMatch">
                                            <i class="bi bi-circle"></i> Konfirmasi sandi cocok
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-grid pt-2">
                            <button type="submit" class="btn btn-dc-primary btn-lg">
                                <i class="bi bi-person-check-fill"></i>
                                <span id="submitBtnLabel">
                                    {{ $selectedRole === 'pencari_darah' ? 'Daftarkan Akun Pencari Darah' : 'Daftarkan Akun Pendonor' }}
                                </span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const roleSegmented = document.getElementById('roleSegmented');
        const roleInput = document.getElementById('roleInput');
        const donorSection = document.getElementById('donorProfileSection');
        const donorFields = document.querySelectorAll('.donor-field');
        const liveBloodPreview = document.getElementById('liveBloodPreview');
        const roleContextHeading = document.getElementById('roleContextHeading');
        const roleContextDesc = document.getElementById('roleContextDesc');
        const roleFeatureListDonor = document.getElementById('roleFeatureListDonor');
        const roleFeatureListSeeker = document.getElementById('roleFeatureListSeeker');
        const credentialStepLabel = document.getElementById('credentialStepLabel');
        const submitBtnLabel = document.getElementById('submitBtnLabel');

        const syncRoleState = (role) => {
            const isDonor = role === 'pendonor';
            roleInput.value = role;

            donorSection.classList.toggle('is-collapsed', !isDonor);
            donorFields.forEach((field) => {
                field.disabled = !isDonor;
            });

            liveBloodPreview.classList.toggle('d-none', !isDonor);
            roleFeatureListDonor.classList.toggle('d-none', !isDonor);
            roleFeatureListSeeker.classList.toggle('d-none', isDonor);

            roleContextHeading.textContent = isDonor
                ? 'Akun Relawan Pendonor'
                : 'Akun Pencari Darah';
            roleContextDesc.textContent = isDonor
                ? 'Terima rekomendasi kebutuhan darah kritis yang kompatibel di sekitar Anda, atau pesan sesi donor terjadwal di Gedung UDD PMI & Mobile Unit.'
                : 'Ajukan permohonan transfusi darurat bagi keluarga/pasien dengan verifikasi UDD resmi serta pantau kedatangan pendonor secara terukur.';
            credentialStepLabel.textContent = isDonor
                ? '3. Kredensial Akun'
                : '2. Kredensial Akun';
            submitBtnLabel.textContent = isDonor
                ? 'Daftarkan Akun Pendonor'
                : 'Daftarkan Akun Pencari Darah';
        };

        syncRoleState(roleInput.value);

        if (roleSegmented) {
            roleSegmented.addEventListener('dc:segment-change', (e) => {
                syncRoleState(e.detail.value);
            });
        }

        // Live Blood Group Badge Update
        const updateBloodPreview = () => {
            const bt = document.querySelector('input[name="blood_type"]:checked')?.value || 'O';
            const rh = document.querySelector('input[name="rhesus"]:checked')?.value || '+';
            if (liveBloodPreview) {
                liveBloodPreview.textContent = `${bt}${rh}`;
            }
        };

        document.querySelectorAll('input[name="blood_type"], input[name="rhesus"]').forEach((radio) => {
            radio.addEventListener('change', updateBloodPreview);
        });

        // Password Visibility Toggle
        const passwordInput = document.getElementById('password');
        const confirmInput = document.getElementById('password_confirmation');
        const toggleBtn = document.getElementById('togglePasswordBtn');
        const toggleIcon = document.getElementById('togglePasswordIcon');

        if (toggleBtn && passwordInput) {
            toggleBtn.addEventListener('click', () => {
                const showing = passwordInput.type === 'text';
                passwordInput.type = showing ? 'password' : 'text';
                if (confirmInput) confirmInput.type = showing ? 'password' : 'text';
                toggleIcon.className = showing ? 'bi bi-eye' : 'bi bi-eye-slash';
            });
        }

        // Inline Real-Time Password Rules (§16)
        const setRuleState = (el, valid) => {
            if (!el) return;
            el.classList.toggle('is-valid', valid);
            const icon = el.querySelector('i');
            if (icon) {
                icon.className = valid ? 'bi bi-check-circle-fill' : 'bi bi-circle';
            }
        };

        const evaluatePasswordRules = () => {
            const val = passwordInput?.value || '';
            const conf = confirmInput?.value || '';
            setRuleState(document.getElementById('ruleLength'), val.length >= 8);
            setRuleState(document.getElementById('ruleAlnum'), /[A-Za-z]/.test(val) && /[0-9]/.test(val));
            setRuleState(document.getElementById('ruleMatch'), val.length > 0 && val === conf);
        };

        passwordInput?.addEventListener('input', evaluatePasswordRules);
        confirmInput?.addEventListener('input', evaluatePasswordRules);
    });
</script>
@endpush
