@extends('layouts.app')

@section('title', $mode === 'seeker' ? 'Pendonor Berkomitmen pada Permohonan Saya' : 'Komitmen Donor Darurat Saya')

@section('content')
<section class="py-4 py-lg-5">
    <div class="container">
        <!-- Header -->
        <div class="d-flex flex-column flex-md-row align-items-md-end justify-content-between gap-3 mb-4">
            <div>
                <nav aria-label="breadcrumb" class="mb-1">
                    <ol class="breadcrumb mb-0 dc-caption">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-secondary">Ruang Kerja</a></li>
                        <li class="breadcrumb-item active" aria-current="page">
                            {{ $mode === 'seeker' ? 'Respons Relawan Pendonor' : 'Komitmen Donor Saya' }}
                        </li>
                    </ol>
                </nav>

                @if ($mode === 'seeker')
                    <h1 class="dc-title-1 mb-1">Daftar Relawan Pendonor Berkomitmen</h1>
                    <p class="dc-caption mb-0">Pantau estimasi waktu kehadiran relawan pendonor pada permohonan darah darurat Anda secara aman.</p>
                @else
                    <h1 class="dc-title-1 mb-1">Komitmen Donor Darurat Saya</h1>
                    <p class="dc-caption mb-0">Kelola jadwal kehadiran donor darurat dan bukti komitmen Anda di rumah sakit rujukan.</p>
                @endif
            </div>

            @if ($mode === 'donor' && Route::has('discovery.index'))
                <a href="{{ route('discovery.index') }}" class="btn btn-dc-primary">
                    <i class="bi bi-compass"></i>
                    Cari Kebutuhan Darah Aktif
                </a>
            @elseif ($mode === 'seeker' && Route::has('emergency-requests.index'))
                <a href="{{ route('emergency-requests.index') }}" class="btn btn-dc-secondary">
                    <i class="bi bi-clipboard2-pulse"></i>
                    Permohonan Darah Saya
                </a>
            @endif
        </div>

        <!-- Summary Metrics -->
        @if ($mode === 'donor')
            <div class="row g-3 mb-4">
                <div class="col-4">
                    <div class="dc-card p-3 h-100">
                        <div class="dc-caption">Komitmen Aktif</div>
                        <div class="dc-title-2 dc-tabular text-warning mb-0 mt-1">{{ $summary['active_count'] }}</div>
                    </div>
                </div>
                <div class="col-4">
                    <div class="dc-card p-3 h-100">
                        <div class="dc-caption">Selesai Donor</div>
                        <div class="dc-title-2 dc-tabular text-success mb-0 mt-1">{{ $summary['completed_count'] }}</div>
                    </div>
                </div>
                <div class="col-4">
                    <div class="dc-card p-3 h-100">
                        <div class="dc-caption">Total Riwayat Pledge</div>
                        <div class="dc-title-2 dc-tabular mb-0 mt-1">{{ $summary['total_pledges'] }}</div>
                    </div>
                </div>
            </div>
        @else
            <div class="row g-3 mb-4">
                <div class="col-4">
                    <div class="dc-card p-3 h-100">
                        <div class="dc-caption">Relawan Menuju Lokasi / Siaga</div>
                        <div class="dc-title-2 dc-tabular text-warning mb-0 mt-1">{{ $summary['active_incoming'] }}</div>
                    </div>
                </div>
                <div class="col-4">
                    <div class="dc-card p-3 h-100">
                        <div class="dc-caption">Sudah Tiba di Faskes</div>
                        <div class="dc-title-2 dc-tabular text-success mb-0 mt-1">{{ $summary['arrived_count'] }}</div>
                    </div>
                </div>
                <div class="col-4">
                    <div class="dc-card p-3 h-100">
                        <div class="dc-caption">Total Respons Masuk</div>
                        <div class="dc-title-2 dc-tabular mb-0 mt-1">{{ $summary['total_incoming'] }}</div>
                    </div>
                </div>
            </div>
        @endif

        <!-- Commitments List -->
        <div class="vstack gap-3">
            @forelse ($commitments as $item)
                <article class="dc-card p-4 {{ $item->isActiveCommitment() ? 'border-danger border-opacity-25' : '' }}">
                    <div class="row align-items-center g-3">
                        <div class="col-lg-8">
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                <span class="dc-pill {{ $item->status->pillClass() }}">
                                    <i class="bi {{ $item->status->icon() }}"></i>
                                    {{ $item->status->label() }}
                                </span>
                                <span class="dc-caption dc-tabular fw-semibold">{{ $item->pledge_code }}</span>
                                <span class="dc-caption">
                                    &middot; Kasus <strong class="dc-tabular text-dark">{{ $item->bloodRequest?->request_code }}</strong>
                                </span>
                            </div>

                            <div class="d-flex align-items-start gap-3">
                                <span class="dc-blood-badge flex-shrink-0">
                                    {{ $item->bloodRequest?->blood_group_label }}
                                </span>
                                <div>
                                    @if ($mode === 'seeker')
                                        <h2 class="dc-title-3 mb-1">
                                            {{ $item->masked_donor_alias }}
                                            @if ($item->donor?->blood_group_label)
                                                <span class="dc-caption fw-normal">(Golongan {{ $item->donor->blood_group_label }})</span>
                                            @endif
                                        </h2>
                                        <div class="dc-caption mb-1">
                                            Untuk pasien <strong>{{ $item->bloodRequest?->patient_name }}</strong> di <strong>{{ $item->bloodRequest?->facility?->name }}</strong>
                                        </div>
                                    @else
                                        <h2 class="dc-title-3 mb-1">
                                            {{ $item->bloodRequest?->facility?->name }}
                                        </h2>
                                        <div class="dc-caption mb-1">
                                            Komponen <strong>{{ $item->bloodRequest?->component_type?->value }}</strong> &middot; {{ $item->bloodRequest?->facility?->address }}
                                        </div>
                                    @endif

                                    <div class="dc-caption dc-tabular">
                                        @if ($item->status === \App\Enums\DonorCommitmentStatus::ARRIVED && $item->arrived_at)
                                            <i class="bi bi-geo-alt-fill text-success me-1"></i>
                                            Sudah tiba di faskes: <strong class="text-success">{{ $item->arrived_at->translatedFormat('d M Y, H:i') }} WIB</strong>
                                        @elseif ($item->status === \App\Enums\DonorCommitmentStatus::CANCELLED && $item->cancelled_at)
                                            <i class="bi bi-slash-circle text-danger me-1"></i>
                                            Dibatalkan pada <strong class="text-dark">{{ $item->cancelled_at->translatedFormat('d M Y, H:i') }} WIB</strong>
                                        @else
                                            <i class="bi bi-clock text-danger me-1"></i>
                                            Estimasi Kehadiran: <strong class="text-dark">{{ $item->estimated_arrival_at->translatedFormat('d M Y, H:i') }} WIB</strong>
                                            ({{ $item->estimated_arrival_at->diffForHumans() }})
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-4 text-lg-end">
                            <a href="{{ route('commitments.show', $item) }}" class="btn btn-dc-primary btn-sm">
                                <i class="bi bi-file-earmark-check"></i>
                                Lihat Bukti &amp; Status Kedatangan
                            </a>
                        </div>
                    </div>
                </article>
            @empty
                <div class="dc-card p-5 text-center">
                    <div class="mx-auto mb-3 d-inline-flex align-items-center justify-content-center rounded-circle bg-light text-secondary" style="width: 3.5rem; height: 3.5rem;">
                        <i class="bi bi-person-heart fs-4"></i>
                    </div>
                    <h2 class="dc-title-3 mb-1">
                        {{ $mode === 'seeker' ? 'Belum Ada Relawan Berkomitmen' : 'Anda Belum Memiliki Komitmen Donor Darurat' }}
                    </h2>
                    <p class="dc-caption mb-3 mx-auto" style="max-width: 28rem;">
                        {{ $mode === 'seeker'
                            ? 'Begitu relawan pendonor mengirimkan kesediaan hadir pada permohonan aktif Anda, estimasi waktu tibanya akan langsung muncul di sini.'
                            : 'Jelajahi direktori permohonan darah darurat aktif di sekitar Anda untuk mengirimkan komitmen bantuan.' }}
                    </p>
                    @if ($mode === 'donor' && Route::has('discovery.index'))
                        <a href="{{ route('discovery.index') }}" class="btn btn-dc-primary btn-sm">
                            Eksplorasi Kebutuhan Darah Darurat
                        </a>
                    @endif
                </div>
            @endforelse
        </div>

        @if ($commitments->hasPages())
            <div class="mt-4">
                {{ $commitments->links() }}
            </div>
        @endif
    </div>
</section>
@endsection
