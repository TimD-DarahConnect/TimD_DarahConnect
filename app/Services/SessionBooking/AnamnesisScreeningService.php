<?php

namespace App\Services\SessionBooking;

use App\Models\ScreeningQuestion;
use Illuminate\Support\Collection;

class AnamnesisScreeningService
{
    /**
     * Evaluate a donor's 20-question medical anamnesis answers against clinical eligibility rules.
     *
     * @param  Collection<int, ScreeningQuestion>  $questions
     * @param  array<int|string, string>  $submittedAnswers
     * @return array{
     *     total_questions: int,
     *     passed_count: int,
     *     flagged_count: int,
     *     is_eligible: bool,
     *     responses: array<int, array<string, mixed>>,
     *     flagged_reasons: array<int, array{code: string, question: string, note: string}>,
     *     summary_note: string
     * }
     */
    public function evaluate(Collection $questions, array $submittedAnswers): array
    {
        $responses = [];
        $flaggedReasons = [];
        $passedCount = 0;

        foreach ($questions as $question) {
            $rawAnswer = strtolower(trim((string) ($submittedAnswers[$question->id] ?? '')));
            $booleanAnswer = $rawAnswer === 'yes';
            $passed = $booleanAnswer === (bool) $question->expected_answer;

            if ($passed) {
                $passedCount++;
            } else {
                $flaggedReasons[] = [
                    'code' => $question->question_code,
                    'question' => $question->question_text,
                    'note' => $question->deferral_note,
                ];
            }

            $responses[] = [
                'question_id' => $question->id,
                'question_code' => $question->question_code,
                'section_key' => $question->section_key,
                'answer' => $rawAnswer === 'yes' ? 'yes' : 'no',
                'expected' => $question->expected_answer ? 'yes' : 'no',
                'passed' => $passed,
            ];
        }

        $totalQuestions = $questions->count();
        $flaggedCount = count($flaggedReasons);
        $isEligible = $totalQuestions >= 20 && $flaggedCount === 0;

        $summaryNote = $isEligible
            ? 'Lolos penapisan mandiri anamnesis pra-donor ('.$passedCount.'/'.$totalQuestions.' kriteria medis terpenuhi).'
            : 'Penundaan sementara anamnesis pada '.$flaggedCount.' indikator kesehatan. Kuota sesi tidak dipotong demi keselamatan pendonor dan resipien.';

        return [
            'total_questions' => $totalQuestions,
            'passed_count' => $passedCount,
            'flagged_count' => $flaggedCount,
            'is_eligible' => $isEligible,
            'responses' => $responses,
            'flagged_reasons' => $flaggedReasons,
            'summary_note' => $summaryNote,
        ];
    }
}
