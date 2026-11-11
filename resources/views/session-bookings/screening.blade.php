@extends('layouts.app')

@section('title', 'Kuesioner Penapisan Mandiri Anamnesis — ' . $booking->booking_code)

@section('content')
<section class="py-4 py-lg-5">
    <div class="container" style="max-width: 66rem;">
        <!-- Header & Reservation Context -->
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
            <div>
                <nav aria-label="breadcrumb" class="mb-1">
                    <ol class="breadcrumb mb-0 dc-caption">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-secondary">Ruang Kerja</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('bookings.index') }}" class="text-decoration-none text-secondary">Reservasi Saya</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('bookings.show', $booking) }}" class="text-decoration-none text-secondary dc-tabular">{{ $booking->booking_code }}</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Skrining Anamnesis</li>
                    </ol>
                </nav>
                <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                    <span class="dc-eyebrow">Digital Self-Screening Pra-Donor</span>
                    <span class="dc-pill dc-pill-warning dc-tabular">
                        <i class="bi bi-clipboard2-pulse"></i>
                        20 Pertanyaan Standar UDD PMI
                    </span>
                </div>
                <h1 class="dc-title-1 mb-1">Kuesioner Penapisan Mandiri (Anamnesis)</h1>
                <p class="dc-caption mb-0">
                    Jawab seluruh 20 pertanyaan medis di bawah ini secara jujur sesuai kondisi Anda untuk mengonfirmasi reservasi sesi donor di <strong>{{ $booking->schedule?->venue_name }}</strong> ({{ $booking->schedule?->service_date?->translatedFormat('d M Y') }}, {{ $booking->session?->time_range_label }}).
                </p>
            </div>

            <a href="{{ route('bookings.show', $booking) }}" class="btn btn-dc-secondary flex-shrink-0">
                <i class="bi bi-arrow-left"></i>
                Detail Reservasi
            </a>
        </div>

        @if ($errors->any())
            <div class="dc-card p-3 px-4 mb-4 border-danger-subtle bg-danger-subtle text-danger-emphasis" role="alert">
                <div class="fw-semibold mb-1">
                    <i class="bi bi-exclamation-octagon-fill me-1"></i>Mohon lengkapi kuesioner anamnesis:
                </div>
                <ul class="mb-0 small ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Sticky Translucent Step & Progress Bar (§4 Interruptible Motion, §12 Translucent Materials) -->
        <div class="dc-glass-bar p-3 mb-4" style="position: sticky; top: 4.5rem; z-index: 15;">
            <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                <div class="d-flex flex-wrap align-items-center gap-1" role="tablist" aria-label="Tahapan kuesioner anamnesis">
                    @foreach ($groupedSections as $stepIndex => $section)
                        <button
                            type="button"
                            class="dc-nav-pill js-step-tab {{ $stepIndex === 0 ? 'active' : '' }}"
                            data-step-target="{{ $stepIndex }}"
                            role="tab"
                            aria-selected="{{ $stepIndex === 0 ? 'true' : 'false' }}"
                        >
                            <span class="dc-tabular fw-bold me-1">0{{ $stepIndex + 1 }}.</span>
                            <span class="d-none d-sm-inline">{{ $section['title'] }}</span>
                            <span class="d-inline d-sm-none">Tahap {{ $stepIndex + 1 }}</span>
                        </button>
                    @endforeach
                </div>

                <div class="d-flex align-items-center gap-3 flex-shrink-0">
                    <div class="flex-grow-1" style="min-width: 8rem;">
                        <div class="d-flex justify-content-between dc-caption dc-tabular mb-1">
                            <span>Progres Jawaban</span>
                            <strong><span id="liveAnsweredCount">0</span> / {{ $questions->count() }}</strong>
                        </div>
                        <div class="progress rounded-pill" style="height: 0.42rem;">
                            <div id="liveProgressBar" class="progress-bar bg-danger" style="width: 0%; transition: width 220ms cubic-bezier(0.22, 1, 0.36, 1);"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <form id="anamnesisScreeningForm" method="POST" action="{{ route('bookings.screening.store', $booking) }}" novalidate>
            @csrf

            @foreach ($groupedSections as $stepIndex => $section)
                <div
                    class="js-step-panel {{ $stepIndex === 0 ? '' : 'd-none' }}"
                    data-step-index="{{ $stepIndex }}"
                >
                    <div class="dc-card p-4 p-md-5 mb-4">
                        <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2 pb-3 mb-4 border-bottom">
                            <div>
                                <div class="dc-eyebrow">Tahap 0{{ $stepIndex + 1 }} dari 0{{ $groupedSections->count() }}</div>
                                <h2 class="dc-title-2 mb-0 mt-1">{{ $section['title'] }}</h2>
                            </div>
                            <span class="dc-pill dc-pill-neutral dc-tabular">
                                {{ $section['questions']->count() }} Pertanyaan Medis
                            </span>
                        </div>

                        <div class="vstack gap-3">
                            @foreach ($section['questions'] as $question)
                                @php
                                    $oldVal = old("answers.{$question->id}");
                                @endphp
                                <div
                                    class="p-3 px-4 rounded-4 border bg-light bg-opacity-50 js-question-card @error("answers.{$question->id}") border-danger @enderror"
                                    data-question-id="{{ $question->id }}"
                                >
                                    <div class="row g-3 align-items-center">
                                        <div class="col-lg-8">
                                            <div class="d-flex align-items-start gap-2">
                                                <span class="badge rounded-pill text-bg-secondary bg-opacity-75 dc-tabular mt-1">
                                                    {{ $question->question_code }}
                                                </span>
                                                <label class="fw-medium text-dark mb-0" style="line-height: 1.5;">
                                                    {{ $question->question_text }}
                                                </label>
                                            </div>
                                            @error("answers.{$question->id}")
                                                <div class="text-danger small mt-2">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-lg-4">
                                            <div class="d-flex gap-2 justify-content-lg-end" role="radiogroup" aria-label="Jawaban {{ $question->question_code }}">
                                                <label class="flex-fill flex-lg-grow-0">
                                                    <input
                                                        type="radio"
                                                        name="answers[{{ $question->id }}]"
                                                        value="yes"
                                                        class="btn-check js-answer-radio"
                                                        id="ans_{{ $question->id }}_yes"
                                                        @checked($oldVal === 'yes')
                                                        required
                                                    >
                                                    <span class="btn btn-outline-dark btn-sm w-100 px-3 py-2 rounded-pill fw-semibold dc-pressable">
                                                        <i class="bi bi-check2-circle me-1"></i>Ya
                                                    </span>
                                                </label>

                                                <label class="flex-fill flex-lg-grow-0">
                                                    <input
                                                        type="radio"
                                                        name="answers[{{ $question->id }}]"
                                                        value="no"
                                                        class="btn-check js-answer-radio"
                                                        id="ans_{{ $question->id }}_no"
                                                        @checked($oldVal === 'no')
                                                        required
                                                    >
                                                    <span class="btn btn-outline-dark btn-sm w-100 px-3 py-2 rounded-pill fw-semibold dc-pressable">
                                                        <i class="bi bi-x-circle me-1"></i>Tidak
                                                    </span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        @if ($loop->last)
                            <!-- Informed Consent Declaration (Permenkes No. 91 Tahun 2015) -->
                            <div class="mt-4 p-4 rounded-4 border border-danger-subtle bg-danger-subtle bg-opacity-10">
                                <div class="dc-eyebrow text-danger mb-1">Pernyataan Kejujuran &amp; Informed Consent Awal</div>
                                <div class="form-check mt-2">
                                    <input
                                        class="form-check-input @error('consent_honesty') is-invalid @enderror"
                                        type="checkbox"
                                        value="1"
                                        id="consent_honesty"
                                        name="consent_honesty"
                                        @checked(old('consent_honesty'))
                                        required
                                    >
                                    <label class="form-check-label small text-dark" for="consent_honesty" style="line-height: 1.55;">
                                        Saya menyatakan telah menjawab seluruh <strong>20 pertanyaan anamnesis</strong> di atas secara jujur dan benar demi keselamatan diri saya maupun pasien penerima transfusi, serta memahami bahwa petugas medis UDD PMI tetap akan melakukan pemeriksaan fisik akhir (tekanan darah, nadi, suhu, dan kadar Hemoglobin) di lokasi.
                                    </label>
                                    @error('consent_honesty')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        @endif

                        <!-- Step Navigation Controls -->
                        <div class="mt-4 pt-3 border-top d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <div>
                                @if (! $loop->first)
                                    <button type="button" class="btn btn-dc-secondary js-prev-step" data-prev="{{ $stepIndex - 1 }}">
                                        <i class="bi bi-arrow-left"></i>
                                        Tahap Sebelumnya
                                    </button>
                                @else
                                    <span class="dc-caption">
                                        <i class="bi bi-shield-lock text-danger me-1"></i>Jawaban bersifat rahasia medis sesuai standar Permenkes No. 91/2015.
                                    </span>
                                @endif
                            </div>

                            <div class="d-flex gap-2">
                                @if (! $loop->last)
                                    <button type="button" class="btn btn-dc-primary js-next-step" data-next="{{ $stepIndex + 1 }}">
                                        Lanjut ke Tahap 0{{ $stepIndex + 2 }}
                                        <i class="bi bi-arrow-right"></i>
                                    </button>
                                @else
                                    <button type="submit" id="submitAnamnesisBtn" class="btn btn-dc-primary btn-lg">
                                        <i class="bi bi-clipboard2-check-fill"></i>
                                        Evaluasi &amp; Konfirmasi Reservasi
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </form>
    </div>
</section>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const totalQuestions = {{ (int) $questions->count() }};
    const form = document.getElementById('anamnesisScreeningForm');
    const stepTabs = Array.from(document.querySelectorAll('.js-step-tab'));
    const stepPanels = Array.from(document.querySelectorAll('.js-step-panel'));
    const answeredCountEl = document.getElementById('liveAnsweredCount');
    const progressBarEl = document.getElementById('liveProgressBar');

    const activateStep = (targetIdx) => {
        stepPanels.forEach((panel, idx) => {
            panel.classList.toggle('d-none', idx !== targetIdx);
        });
        stepTabs.forEach((tab, idx) => {
            const isActive = idx === targetIdx;
            tab.classList.toggle('active', isActive);
            tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    const updateProgress = () => {
        const checkedRadios = form.querySelectorAll('.js-answer-radio:checked');
        const count = checkedRadios.length;
        const pct = totalQuestions > 0 ? Math.min(100, Math.round((count / totalQuestions) * 100)) : 0;

        answeredCountEl.textContent = String(count);
        progressBarEl.style.width = `${pct}%`;

        checkedRadios.forEach((radio) => {
            const card = radio.closest('.js-question-card');
            if (card) {
                card.classList.remove('border-danger');
                card.classList.add('border-success', 'border-opacity-50', 'bg-white');
            }
        });
    };

    stepTabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            activateStep(parseInt(tab.dataset.stepTarget, 10) || 0);
        });
    });

    document.querySelectorAll('.js-next-step').forEach((btn) => {
        btn.addEventListener('click', () => {
            activateStep(parseInt(btn.dataset.next, 10) || 0);
        });
    });

    document.querySelectorAll('.js-prev-step').forEach((btn) => {
        btn.addEventListener('click', () => {
            activateStep(parseInt(btn.dataset.prev, 10) || 0);
        });
    });

    form.addEventListener('change', (e) => {
        if (e.target.classList.contains('js-answer-radio')) {
            updateProgress();
        }
    });

    form.addEventListener('submit', (e) => {
        for (let idx = 0; idx < stepPanels.length; idx++) {
            const panel = stepPanels[idx];
            const cards = panel.querySelectorAll('.js-question-card');
            for (const card of cards) {
                if (!card.querySelector('.js-answer-radio:checked')) {
                    e.preventDefault();
                    card.classList.add('border-danger');
                    activateStep(idx);
                    return;
                }
            }
        }
    });

    updateProgress();
});
</script>
@endpush
