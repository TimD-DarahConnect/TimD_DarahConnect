@extends('layouts.app')

@section('title', 'Permohonan Darah Darurat Saya')

@section('content')
<section class="py-4 py-lg-5">
    <div class="container">
        <!-- Wayfinding & Primary CTA -->
        <div class="d-flex flex-column flex-md-row align-items-md-end justify-content-between gap-3 mb-4">
            <div>
                <nav aria-label="breadcrumb" class="mb-1">
                    <ol class="breadcrumb mb-0 dc-caption">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-secondary">Ruang Kerja</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Permohonan Darah Saya</li>
                    </ol>
                </nav>
                <h1 class="dc-title-1 mb-1">Permohonan Darah Darurat</h1>
                <p class="dc-caption mb-0">Pantau status verifikasi UDD, kelola draf, dan lacak pemenuhan kantong darah pasien.</p>
            </div>

            <a href="{{ route('emergency-requests.create') }}" class="btn btn-dc-primary">
                <i class="bi bi-plus-circle-fill"></i>
                Buat Permohonan Darurat
            </a>
        </div>

        <!-- Summary Metrics -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="dc-card p-3">
                    <div class="dc-caption">Total Pengajuan</div>
                    <div class="dc-title-2 dc-tabular mb-0 mt-1">{{ $summary['total'] }}</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="dc-card p-3">
                    <div class="dc-caption">Draf Tersimpan</div>
                    <div class="dc-title-2 dc-tabular text-secondary mb-0 mt-1">{{ $summary['draft'] }}</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="dc-card p-3">
                    <div class="dc-caption">Menunggu Verifikasi</div>
                    <div class="dc-title-2 dc-tabular text-warning mb-0 mt-1">{{ $summary['pending'] }}</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="dc-card p-3">
                    <div class="dc-caption">Aktif Dipublikasikan</div>
                    <div class="dc-title-2 dc-tabular text-success mb-0 mt-1">{{ $summary['active'] }}</div>
                </div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="dc-glass-bar p-2 mb-4 d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div class="d-flex flex-wrap align-items-center gap-1">
                @php
                    $filters = [
                        'all' => 'Semua Status',
                        'draft' => 'Draf',
                        'pending_verification' => 'Menunggu Verifikasi',
                        'need_revision' => 'Perlu Revisi',
                        'active' => 'Aktif',
                    ];
                @endphp
                @foreach ($filters as $key => $label)
                    <a
                        href="{{ route('emergency-requests.index', $key === 'all' ? [] : ['status' => $key]) }}"
                        class="dc-nav-pill {{ $statusFilter === $key ? 'active' : '' }}"
                    >
                        {{ $label }}
                    </a>
                @endforeach
            </div>
            <span class="dc-caption px-2">Menampilkan {{ $requests->count() }} dari {{ $requests->total() }} permohonan</span>
        </div>

        <!-- Requests List -->
        <div class="vstack gap-3">
            @forelse ($requests as $item)
                <div class="dc-card p-4">
                    <div class="row align-items-center g-3">
                        <div class="col-lg-7">
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                <span class="dc-pill {{ $item->status->pillClass() }}">
                                    <i class="bi {{ $item->status->icon() }}"></i>
                                    {{ $item->status->label() }}
                                </span>
                                <span class="dc-pill {{ $item->urgency_level->pillClass() }}">
                                    {{ $item->urgency_level->label() }}
                                </span>
                                <span class="dc-caption dc-tabular fw-semibold">{{ $item->request_code }}</span>
                            </div>

                            <div class="d-flex align-items-start gap-3">
                                <span class="dc-blood-badge flex-shrink-0">{{ $item->blood_group_label }}</span>
                                <div>
                                    <h2 class="dc-title-3 mb-1">
                                        <a href="{{ route('emergency-requests.show', $item) }}" class="text-decoration-none text-dark">
                                            {{ $item->patient_name }}
                                        </a>
                                    </h2>
                                    <div class="dc-caption mb-1">
                                        <i class="bi bi-hospital me-1 text-danger"></i>
                                        <strong class="text-dark">{{ $item->facility?->name }}</strong>
                                        @if ($item->ward_room)
                                            &middot; {{ $item->ward_room }}
                                        @endif
                                    </div>
                                    <div class="dc-caption">
                                        Komponen: <strong class="text-dark">{{ $item->component_type->value }}</strong>
                                        @if ($item->hemoglobin_level !== null)
                                            &middot; Hb: <strong class="text-dark dc-tabular">{{ number_format($item->hemoglobin_level, 1) }} g/dL</strong>
                                        @endif
                                        @if ($item->clinical_diagnosis)
                                            &middot; {{ \Illuminate\Support\Str::limit($item->clinical_diagnosis, 55) }}
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-5">
                            <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-lg-end gap-3">
                                <div class="flex-grow-1" style="max-width: 12rem;">
                                    <div class="d-flex justify-content-between dc-caption dc-tabular mb-1">
                                        <span>Kebutuhan Kantong</span>
                                        <strong class="text-dark">{{ $item->bags_fulfilled }} / {{ $item->bags_needed }}</strong>
                                    </div>
                                    <div class="progress rounded-pill" style="height: 0.45rem;">
                                        <div
                                            class="progress-bar bg-danger"
                                            role="progressbar"
                                            style="width: {{ $item->fulfillment_percentage }}%"
                                            aria-valuenow="{{ $item->bags_fulfilled }}"
                                            aria-valuemin="0"
                                            aria-valuemax="{{ $item->bags_needed }}"
                                        ></div>
                                    </div>
                                    <div class="dc-caption mt-1">
                                        Dibuat {{ $item->created_at->diffForHumans() }}
                                    </div>
                                </div>

                                <div class="d-flex align-items-center gap-2 flex-shrink-0">
                                    @if (Route::has('emergency-requests.edit') && $item->isEditableByRequester())
                                        <a href="{{ route('emergency-requests.edit', $item) }}" class="btn btn-dc-secondary btn-sm">
                                            <i class="bi bi-pencil"></i> Edit
                                        </a>
                                    @endif
                                    <a href="{{ route('emergency-requests.show', $item) }}" class="btn btn-dc-primary btn-sm px-3">
                                        Rincian <i class="bi bi-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="dc-card p-5 text-center">
                    <div class="rounded-circle bg-danger-subtle text-danger d-inline-flex align-items-center justify-content-center mb-3" style="width: 3.5rem; height: 3.5rem;">
                        <i class="bi bi-file-earmark-medical fs-3"></i>
                    </div>
                    <h2 class="dc-title-3 mb-1">Belum Ada Permohonan Darah</h2>
                    <p class="dc-caption mb-4 mx-auto" style="max-width: 28rem;">
                        Jika keluarga atau pasien membutuhkan transfusi darah darurat, Anda dapat membuat permohonan baru atau menyimpannya sebagai draf terlebih dahulu.
                    </p>
                    <a href="{{ route('emergency-requests.create') }}" class="btn btn-dc-primary">
                        <i class="bi bi-plus-circle-fill"></i>
                        Ajukan Permohonan Pertama
                    </a>
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
@endsection
