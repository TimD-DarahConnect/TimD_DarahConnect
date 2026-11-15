@extends('layouts.app')

@section('title', 'Koordinasi Titik Temu ' . $commitment->pledge_code)

@push('styles')
<style>
    .dc-coord-bubble {
        max-width: 88%;
        border-radius: var(--dc-radius-lg);
        padding: 0.85rem 1.05rem;
        border: 1px solid var(--dc-border);
    }
    .dc-coord-bubble-own {
        margin-left: auto;
        background: linear-gradient(145deg, #fef2f2 0%, #fff1f2 100%);
        border-color: rgba(220, 38, 38, 0.2);
    }
    .dc-coord-bubble-counterpart {
        margin-right: auto;
        background: var(--dc-surface-sunken);
    }
    .dc-coord-feed {
        max-height: 29rem;
        overflow-y: auto;
        scroll-behavior: smooth;
    }
</style>
@endpush

@section('content')
<section class="py-4 py-lg-5">
    <div class="container" style="max-width: 68rem;">
        <!-- Wayfinding & Privacy Header -->
        <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
            <div>
                <nav aria-label="breadcrumb" class="mb-1">
                    <ol class="breadcrumb mb-0 dc-caption">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-secondary">Ruang Kerja</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('commitments.index') }}" class="text-decoration-none text-secondary">Komitmen Donor</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('commitments.show', $commitment) }}" class="text-decoration-none text-secondary dc-tabular">{{ $commitment->pledge_code }}</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Koordinasi Titik Temu</li>
                    </ol>
                </nav>

                <div class="d-flex flex-wrap align-items-center gap-2 mt-2 mb-1">
                    <span class="dc-pill dc-pill-success">
                        <i class="bi bi-shield-lock-fill"></i>
                        Ruang Koordinasi Aman Privasi
                    </span>
                    <span class="dc-pill {{ $commitment->status->pillClass() }}">
                        <i class="bi {{ $commitment->status->icon() }}"></i>
                        {{ $commitment->status->label() }}
                    </span>
                    <span class="dc-pill dc-pill-neutral dc-tabular">
                        Token Loket: <strong class="text-danger">{{ $commitment->coordination_code }}</strong>
                    </span>
                </div>

                <h1 class="dc-title-1 mb-0 mt-1">Koordinasi Titik Temu Rumah Sakit</h1>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('commitments.show', $commitment) }}" class="btn btn-dc-secondary">
                    <i class="bi bi-arrow-left"></i>
                    Bukti Komitmen
                </a>
                @if ($isOwnerDonor && $commitment->canConfirmArrival() && Route::has('commitments.arrive'))
                    <form method="POST" action="{{ route('commitments.arrive', $commitment) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn btn-dc-primary">
                            <i class="bi bi-geo-alt-fill"></i>
                            Saya Sudah Tiba di Faskes
                        </button>
                    </form>
                @endif
            </div>
        </div>

        @if ($errors->any())
            <div class="dc-card p-3 mb-4 border-danger-subtle bg-danger-subtle text-danger-emphasis" role="alert">
                <div class="fw-semibold small mb-1">
                    <i class="bi bi-exclamation-circle-fill me-1"></i>Periksa kembali isian koordinasi Anda:
                </div>
                <ul class="mb-0 ps-3 small">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Privacy Protection Notice Banner -->
        <div class="dc-card p-3 p-md-4 mb-4 border-success-subtle bg-success-subtle bg-opacity-50">
            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                <div class="d-flex align-items-start gap-3">
                    <span class="rounded-circle bg-white text-success d-inline-flex align-items-center justify-content-center flex-shrink-0 border" style="width: 2.5rem; height: 2.5rem;">
                        <i class="bi bi-incognito fs-5"></i>
                    </span>
                    <div>
                        <div class="fw-semibold text-dark small">Perlindungan Identitas &amp; Nomor Ponsel Aktif</div>
                        <p class="dc-caption text-dark mb-0">
                            Nomor telepon, tautan WhatsApp, dan alamat email pribadi <strong>tidak pernah ditampilkan</strong> serta disamarkan otomatis jika tertulis di pesan. Gunakan <strong>Token Loket {{ $commitment->coordination_code }}</strong> saat bertemu di instalasi Bank Darah.
                        </p>
                    </div>
                </div>

                <div class="flex-shrink-0 dc-caption text-md-end">
                    <div>Lawan Bicara Terverifikasi:</div>
                    <strong class="text-dark">
                        {{ $isOwnerDonor ? $maskedSeekerAlias : $maskedDonorAlias . ' (' . ($commitment->donor?->blood_group_label ?? '-') . ')' }}
                    </strong>
                </div>
            </div>
        </div>

        <div class="row g-4 align-items-start">
            <!-- Left Column: Structured Hospital Landmark & Meeting Point Card -->
            <div class="col-lg-5">
                <div class="dc-card p-4 mb-4" style="position: sticky; top: 5.25rem;">
                    <div class="d-flex align-items-start justify-content-between gap-2 pb-3 mb-3 border-bottom">
                        <div>
                            <div class="dc-eyebrow">Titik Temu Resmi Faskes</div>
                            <h2 class="dc-title-3 mb-0 mt-1">{{ $bloodRequest?->facility?->name }}</h2>
                            <div class="dc-caption mt-1">
                                <i class="bi bi-geo-alt text-danger me-1"></i>{{ $bloodRequest?->facility?->address }}
                            </div>
                        </div>
                        <span class="dc-blood-badge flex-shrink-0">
                            {{ $bloodRequest?->blood_group_label }}
                        </span>
                    </div>

                    <div class="vstack gap-3 mb-4">
                        <div class="p-3 rounded-3 bg-light border">
                            <div class="dc-caption">Lokasi Loket / Ruangan Titik Temu</div>
                            <div class="fw-bold text-dark mt-1">
                                {{ $commitment->meeting_point ?: 'Instalasi Bank Darah Rumah Sakit (BDRS)' }}
                            </div>
                            @if ($commitment->building_floor)
                                <div class="small text-secondary mt-1">
                                    <i class="bi bi-building me-1 text-danger"></i>{{ $commitment->building_floor }}
                                </div>
                            @endif
                        </div>

                        <div class="row g-2">
                            <div class="col-6">
                                <div class="p-3 rounded-3 border h-100">
                                    <div class="dc-caption">Kode Rujukan Kasus</div>
                                    <div class="fw-bold dc-tabular text-dark mt-1">{{ $bloodRequest?->request_code }}</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-3 rounded-3 border h-100">
                                    <div class="dc-caption">Token Verifikasi Loket</div>
                                    <div class="fw-bold dc-tabular text-danger mt-1" style="letter-spacing: 0.06em;">
                                        {{ $commitment->coordination_code }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        @if ($commitment->companion_alias)
                            <div>
                                <div class="dc-caption">Ciri / Penanda Perwakilan di Lokasi</div>
                                <div class="small fw-semibold text-dark mt-1">
                                    <i class="bi bi-person-badge text-danger me-1"></i>{{ $commitment->companion_alias }}
                                </div>
                            </div>
                        @endif

                        @if ($commitment->meeting_instructions)
                            <div>
                                <div class="dc-caption">Panduan Arah Menuju Loket</div>
                                <div class="small text-secondary mt-1">{{ $commitment->meeting_instructions }}</div>
                            </div>
                        @endif

                        <div>
                            <div class="dc-caption">Estimasi / Status Kehadiran Relawan</div>
                            <div class="small fw-semibold dc-tabular text-dark mt-1">
                                @if ($commitment->arrived_at)
                                    <span class="text-success">
                                        <i class="bi bi-check-circle-fill me-1"></i>Sudah tiba pukul {{ $commitment->arrived_at->translatedFormat('H:i') }} WIB
                                    </span>
                                @else
                                    <i class="bi bi-clock text-danger me-1"></i>ETA {{ $commitment->estimated_arrival_at->translatedFormat('d M Y, H:i') }} WIB
                                @endif
                            </div>
                        </div>

                        <!-- Confirmation Indicator -->
                        <div class="p-3 rounded-3 {{ $commitment->meeting_point_confirmed_at ? 'bg-success-subtle border border-success-subtle' : 'bg-warning-subtle bg-opacity-50 border border-warning-subtle' }}">
                            @if ($commitment->meeting_point_confirmed_at)
                                <div class="small fw-semibold text-success-emphasis">
                                    <i class="bi bi-patch-check-fill me-1"></i>Titik Temu Dikonfirmasi Pendonor
                                </div>
                                <div class="dc-caption text-dark dc-tabular mt-1">
                                    Dikonfirmasi pada {{ $commitment->meeting_point_confirmed_at->translatedFormat('d M Y, H:i') }} WIB
                                </div>
                            @else
                                <div class="small fw-semibold text-warning-emphasis">
                                    <i class="bi bi-hourglass-split me-1"></i>Menunggu Konfirmasi Titik Temu
                                </div>
                                <div class="dc-caption text-dark mt-1">
                                    {{ $isOwnerDonor
                                        ? 'Klik tombol konfirmasi di bawah apabila Anda sudah memahami lokasi loket rumah sakit.'
                                        : 'Pendonor dapat menekan konfirmasi setelah membaca rincian titik temu ini.' }}
                                </div>
                            @endif
                        </div>
                    </div>

                    @if ($canCoordinateActively)
                        <div class="d-grid gap-2">
                            @if ($isOwnerSeeker)
                                <button type="button" class="btn btn-dc-primary w-100" data-dc-open-sheet="editMeetingPointSheet">
                                    <i class="bi bi-pin-map-fill"></i>
                                    Atur / Perbarui Titik Temu Faskes
                                </button>
                            @elseif ($isOwnerDonor && ! $commitment->meeting_point_confirmed_at)
                                <form method="POST" action="{{ route('coordinations.meeting-point.confirm', $commitment) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-dc-primary w-100">
                                        <i class="bi bi-check2-circle"></i>
                                        Konfirmasi Saya Paham Titik Temu Ini
                                    </button>
                                </form>
                            @endif
                        </div>
                    @endif
                </div>
            </div>

            <!-- Right Column: Structured Contextual Coordination Feed & 1-Tap Presets -->
            <div class="col-lg-7">
                <div class="dc-card p-4">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 pb-3 mb-3 border-bottom">
                        <div>
                            <div class="dc-eyebrow">Komunikasi Kontekstual</div>
                            <h2 class="dc-title-3 mb-0 mt-1">Log Koordinasi Kehadiran</h2>
                        </div>
                        <span class="dc-pill dc-pill-neutral dc-tabular">
                            <i class="bi bi-chat-dots text-danger"></i>
                            {{ $messages->count() }} Pembaruan
                        </span>
                    </div>

                    <!-- Message Timeline -->
                    <div class="dc-coord-feed vstack gap-3 pe-1 mb-4" id="coordinationFeed">
                        <!-- Initial System Pledge Anchor -->
                        <div class="p-3 rounded-3 border bg-light small">
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-1">
                                <span class="fw-semibold text-dark">
                                    <i class="bi bi-shield-check text-danger me-1"></i>Komitmen Donor Terhubung
                                </span>
                                <span class="dc-caption dc-tabular">{{ $commitment->created_at?->translatedFormat('d M Y, H:i') }} WIB</span>
                            </div>
                            <div class="dc-caption text-dark">
                                {{ $maskedDonorAlias }} menyatakan kesediaan membantu {{ $commitment->bags_pledged }} kantong {{ $bloodRequest?->component_type?->value }} dengan estimasi tiba {{ $commitment->estimated_arrival_at->translatedFormat('d M Y, H:i') }} WIB.
                            </div>
                        </div>

                        @forelse ($messages as $msg)
                            @php
                                $isMine = (int) $msg->sender_id === (int) auth()->id();
                                $senderLabel = $isMine
                                    ? 'Anda'
                                    : ($msg->sender_role === 'pendonor' ? $maskedDonorAlias : $maskedSeekerAlias);
                            @endphp
                            <div class="dc-coord-bubble {{ $isMine ? 'dc-coord-bubble-own' : 'dc-coord-bubble-counterpart' }}">
                                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-1">
                                    <div class="d-flex flex-wrap align-items-center gap-2">
                                        <span class="small fw-bold text-dark">{{ $senderLabel }}</span>
                                        <span class="badge rounded-pill text-bg-light border text-secondary fw-normal">
                                            {{ $msg->messageTypeLabel() }}
                                        </span>
                                    </div>
                                    <span class="dc-caption dc-tabular">
                                        {{ $msg->created_at?->translatedFormat('d M, H:i') }}
                                        @if ($isMine && $msg->read_at)
                                            &middot; <i class="bi bi-check2-all text-success" title="Sudah dibaca"></i>
                                        @endif
                                    </span>
                                </div>

                                <p class="mb-0 small text-dark">{{ $msg->body }}</p>

                                @if ($msg->contained_masked_contact)
                                    <div class="dc-caption text-warning-emphasis mt-2">
                                        <i class="bi bi-shield-exclamation me-1"></i>Kontak pribadi di dalam pesan telah disamarkan otomatis oleh sistem.
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div class="text-center py-4">
                                <div class="dc-caption">
                                    Belum ada pesan koordinasi tambahan. Gunakan tombol pesan cepat di bawah untuk memberi kabar singkat secara aman.
                                </div>
                            </div>
                        @endforelse
                    </div>

                    @if ($canCoordinateActively)
                        <!-- One-Tap Structured Quick Presets -->
                        <div class="pt-3 border-top">
                            <div class="dc-caption fw-semibold text-dark mb-2">
                                <i class="bi bi-lightning-charge-fill text-danger me-1"></i>Kirim Cepat Status Terstruktur (1-Klik):
                            </div>
                            <div class="d-flex flex-wrap gap-2 mb-3">
                                @foreach ($quickPresets as $presetKey => $presetMeta)
                                    <form method="POST" action="{{ route('coordinations.messages.store', $commitment) }}" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="preset_key" value="{{ $presetKey }}">
                                        <button type="submit" class="btn btn-dc-secondary btn-sm dc-pressable">
                                            <i class="bi bi-send"></i>
                                            {{ $presetMeta['label'] }}
                                        </button>
                                    </form>
                                @endforeach
                            </div>

                            <!-- Short Contextual Note Input -->
                            <form method="POST" action="{{ route('coordinations.messages.store', $commitment) }}" novalidate>
                                @csrf
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <label for="coordinationMessageBody" class="form-label small mb-0">
                                        Pesan Kontekstual Singkat di Faskes
                                    </label>
                                    <span class="dc-caption dc-tabular" id="coordMsgCounter">0 / 350</span>
                                </div>
                                <div class="input-group">
                                    <input
                                        type="text"
                                        id="coordinationMessageBody"
                                        name="body"
                                        value="{{ old('body') }}"
                                        minlength="4"
                                        maxlength="350"
                                        class="form-control @error('body') is-invalid @enderror"
                                        placeholder="Contoh: Saya memakai kemeja putih di ruang tunggu loket BDRS lantai 1..."
                                        required
                                    >
                                    <button type="submit" class="btn btn-dc-primary px-3">
                                        <i class="bi bi-send-fill"></i>
                                        <span>Kirim</span>
                                    </button>
                                </div>
                                <div class="dc-caption mt-2">
                                    <i class="bi bi-lock-fill text-success me-1"></i>Hindari menuliskan nomor HP/WhatsApp; sistem akan menyamarkannya secara otomatis.
                                </div>
                            </form>
                        </div>
                    @else
                        <div class="p-3 rounded-3 bg-light border dc-caption text-center">
                            <i class="bi bi-archive me-1"></i>Ruang koordinasi berada dalam mode arsip baca-saja karena status komitmen atau permohonan telah selesai/dibatalkan.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>

@if ($isOwnerSeeker && $canCoordinateActively)
    <!-- Fluid Bottom Sheet: Structured Hospital Meeting Point Editor (Seeker Only) -->
    <div class="dc-sheet-backdrop" data-dc-sheet-backdrop="editMeetingPointSheet"></div>
    <aside
        class="dc-sheet-panel"
        style="max-width: 38rem;"
        data-dc-sheet="editMeetingPointSheet"
        aria-hidden="true"
        aria-labelledby="editMeetingPointTitle"
    >
        <div class="dc-sheet-handle-area" title="Tarik ke bawah untuk menutup">
            <span class="dc-sheet-handle"></span>
        </div>

        <form method="POST" action="{{ route('coordinations.meeting-point.update', $commitment) }}" class="px-4 pb-4 overflow-auto" novalidate>
            @csrf
            @method('PATCH')

            <div class="d-flex align-items-start justify-content-between gap-3 pb-3 mb-3 border-bottom">
                <div>
                    <div class="dc-eyebrow">Pengaturan Titik Temu Faskes</div>
                    <h2 id="editMeetingPointTitle" class="dc-title-2 mb-1 mt-1">Rincian Lokasi Bertemu di RS</h2>
                    <div class="dc-caption">{{ $bloodRequest?->facility?->name }}</div>
                </div>
                <button type="button" class="btn btn-dc-soft btn-sm flex-shrink-0" data-dc-close-sheet="editMeetingPointSheet" aria-label="Tutup panel">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <div class="mb-3">
                <label class="form-label small mb-1">Pilih Cepat Area Layanan Rumah Sakit</label>
                <div class="d-flex flex-wrap gap-2">
                    @php
                        $pointPresets = [
                            'Loket Registrasi Bank Darah Rumah Sakit (BDRS)',
                            'Ruang Pelayanan Transfusi / Donor Darah RS',
                            'Meja Informasi Utama dekat Instalasi Gawat Darurat (IGD)',
                            'Loket Laboratorium Patologi Klinik / Bank Darah',
                        ];
                    @endphp
                    @foreach ($pointPresets as $pt)
                        <button type="button" class="btn btn-dc-secondary btn-sm dc-pressable js-point-preset" data-point="{{ $pt }}">
                            {{ $pt }}
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="mb-3">
                <label for="meetingPointInput" class="form-label">Lokasi Loket / Titik Temu Utama <span class="text-danger">*</span></label>
                <input
                    type="text"
                    id="meetingPointInput"
                    name="meeting_point"
                    value="{{ old('meeting_point', $commitment->meeting_point) }}"
                    maxlength="180"
                    class="form-control @error('meeting_point') is-invalid @enderror"
                    placeholder="Contoh: Loket Registrasi Bank Darah Rumah Sakit (BDRS)"
                    required
                >
            </div>

            <div class="row g-3 mb-3">
                <div class="col-sm-6">
                    <label for="buildingFloorInput" class="form-label">Gedung / Lantai</label>
                    <input
                        type="text"
                        id="buildingFloorInput"
                        name="building_floor"
                        value="{{ old('building_floor', $commitment->building_floor) }}"
                        maxlength="120"
                        class="form-control"
                        placeholder="Contoh: Gedung Utama Lantai 1 Sayap Barat"
                    >
                </div>

                <div class="col-sm-6">
                    <label for="companionAliasInput" class="form-label">Ciri Perwakilan di Lokasi (Tanpa No. HP)</label>
                    <input
                        type="text"
                        id="companionAliasInput"
                        name="companion_alias"
                        value="{{ old('companion_alias', $commitment->companion_alias) }}"
                        maxlength="120"
                        class="form-control"
                        placeholder="Contoh: Pendamping pasien berjaket biru di kursi tunggu BDRS"
                    >
                </div>
            </div>

            <div class="mb-4">
                <label for="meetingInstructionsInput" class="form-label">Panduan Arah Singkat di Rumah Sakit</label>
                <textarea
                    id="meetingInstructionsInput"
                    name="meeting_instructions"
                    rows="2"
                    maxlength="350"
                    class="form-control"
                    placeholder="Contoh: Masuk dari lobi utama, belok kanan mengikuti papan petunjuk Bank Darah / Laboratorium, lalu sebutkan Token Loket {{ $commitment->coordination_code }}."
                >{{ old('meeting_instructions', $commitment->meeting_instructions) }}</textarea>
            </div>

            <div class="d-flex flex-column flex-sm-row justify-content-end gap-2">
                <button type="button" class="btn btn-dc-secondary" data-dc-close-sheet="editMeetingPointSheet">
                    Batal
                </button>
                <button type="submit" class="btn btn-dc-primary px-4">
                    <i class="bi bi-check2-circle"></i>
                    Simpan Titik Temu
                </button>
            </div>
        </form>
    </aside>
@endif
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const feedEl = document.getElementById('coordinationFeed');
    if (feedEl) {
        feedEl.scrollTop = feedEl.scrollHeight;
    }

    const msgInput = document.getElementById('coordinationMessageBody');
    const counterEl = document.getElementById('coordMsgCounter');
    const syncCounter = () => {
        if (!msgInput || !counterEl) return;
        counterEl.textContent = `${msgInput.value.length} / 350`;
    };
    msgInput?.addEventListener('input', syncCounter);
    syncCounter();

    const pointInput = document.getElementById('meetingPointInput');
    document.querySelectorAll('.js-point-preset').forEach((btn) => {
        btn.addEventListener('click', () => {
            if (!pointInput) return;
            pointInput.value = btn.dataset.point || '';
            pointInput.focus();
        });
    });

    @if ($errors->has('meeting_point') || $errors->has('building_floor') || $errors->has('companion_alias') || $errors->has('meeting_instructions'))
        window.requestAnimationFrame(() => {
            window.DarahSheets?.get('editMeetingPointSheet')?.open();
        });
    @endif
});
</script>
@endpush
