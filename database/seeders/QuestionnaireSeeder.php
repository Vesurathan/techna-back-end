<?php

namespace Database\Seeders;

use App\Models\Questionnaire;
use App\Models\Question;
use App\Models\Module;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class QuestionnaireSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $modules = Module::all();
        $questions = Question::all();

        if ($modules->count() > 0 && $questions->count() > 0) {
            $module1 = $modules->first();

            // Get questions for module
            $moduleQuestions = Question::where('module_id', $module1->id)
                ->where('source', 'module')
                ->get();

            if ($moduleQuestions->count() >= 5) {
                // Create Module Questionnaire
                $questionnaire1 = Questionnaire::create([
                    'title' => 'Mid-Term Examination - Engineering Science Innovation',
                    'module_id' => $module1->id,
                    'batch' => '2026',
                    'description' => 'Mid-term examination covering fundamental concepts',
                    'question_counts' => [
                        'single_select' => 3,
                        'multi_select' => 1,
                        'true_false' => 1,
                        'short_answer' => 2,
                        'long_answer' => 1,
                    ],
                    'selected_categories' => ['Database Fundamentals', 'SQL Basics', 'Programming Basics'],
                    'total_questions' => 8,
                    'source' => 'module',
                ]);

                // Attach random questions
                $selectedQuestions = $moduleQuestions->random(min(8, $moduleQuestions->count()));
                foreach ($selectedQuestions as $index => $question) {
                    DB::table('questionnaire_questions')->insert([
                        'questionnaire_id' => $questionnaire1->id,
                        'question_id' => $question->id,
                        'order' => $index + 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            // Create General Questionnaire
            $generalQuestions = Question::where('source', 'general')->get();
            if ($generalQuestions->count() >= 5) {
                $questionnaire2 = Questionnaire::create([
                    'title' => 'General Knowledge Test 2026',
                    'module_id' => null,
                    'batch' => '2026',
                    'description' => 'General knowledge assessment for all students',
                    'question_counts' => [
                        'single_select' => 2,
                        'multi_select' => 1,
                        'true_false' => 1,
                        'short_answer' => 1,
                        'long_answer' => 1,
                    ],
                    'selected_categories' => ['Geography', 'Science', 'Literature'],
                    'total_questions' => 6,
                    'source' => 'general',
                ]);

                $selectedGeneralQuestions = $generalQuestions->random(min(6, $generalQuestions->count()));
                foreach ($selectedGeneralQuestions as $index => $question) {
                    DB::table('questionnaire_questions')->insert([
                        'questionnaire_id' => $questionnaire2->id,
                        'question_id' => $question->id,
                        'order' => $index + 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }
}
