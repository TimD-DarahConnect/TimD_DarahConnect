<?php

namespace Database\Seeders;

use App\Models\ScreeningQuestion;
use Illuminate\Database\Seeder;

class ScreeningQuestionSeeder extends Seeder
{
    /**
     * Seed the 20 standard pre-donation medical anamnesis questions (Permenkes No. 91/2015 & UDD PMI).
     */
    public function run(): void
    {
        foreach (ScreeningQuestion::standardPmiCatalog() as $question) {
            ScreeningQuestion::updateOrCreate(
                ['question_code' => $question['question_code']],
                $question
            );
        }
    }
}
