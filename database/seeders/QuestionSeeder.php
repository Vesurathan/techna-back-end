<?php

namespace Database\Seeders;

use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Module;
use Illuminate\Database\Seeder;

class QuestionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $modules = Module::all();

        // Module Questions
        if ($modules->count() > 0) {
            $module1 = $modules->first();
            $module2 = $modules->skip(1)->first() ?? $modules->first();

            // Single Select Questions
            $q1 = Question::create([
                'question_text' => 'What is the primary purpose of a database management system?',
                'question_type' => 'single_select',
                'source' => 'module',
                'module_id' => $module1->id,
                'category' => 'Database Fundamentals',
                'difficulty' => 'easy',
                'points' => 5,
            ]);

            QuestionOption::create(['question_id' => $q1->id, 'text' => 'To store and retrieve data efficiently', 'is_correct' => true, 'order' => 0]);
            QuestionOption::create(['question_id' => $q1->id, 'text' => 'To create web pages', 'is_correct' => false, 'order' => 1]);
            QuestionOption::create(['question_id' => $q1->id, 'text' => 'To design user interfaces', 'is_correct' => false, 'order' => 2]);
            QuestionOption::create(['question_id' => $q1->id, 'text' => 'To manage network connections', 'is_correct' => false, 'order' => 3]);

            $q2 = Question::create([
                'question_text' => 'Which SQL command is used to retrieve data from a database?',
                'question_type' => 'single_select',
                'source' => 'module',
                'module_id' => $module1->id,
                'category' => 'SQL Basics',
                'difficulty' => 'easy',
                'points' => 5,
            ]);

            QuestionOption::create(['question_id' => $q2->id, 'text' => 'SELECT', 'is_correct' => true, 'order' => 0]);
            QuestionOption::create(['question_id' => $q2->id, 'text' => 'INSERT', 'is_correct' => false, 'order' => 1]);
            QuestionOption::create(['question_id' => $q2->id, 'text' => 'UPDATE', 'is_correct' => false, 'order' => 2]);
            QuestionOption::create(['question_id' => $q2->id, 'text' => 'DELETE', 'is_correct' => false, 'order' => 3]);

            // Multi Select Question
            $q3 = Question::create([
                'question_text' => 'Which of the following are valid data types in programming? (Select all that apply)',
                'question_type' => 'multi_select',
                'source' => 'module',
                'module_id' => $module1->id,
                'category' => 'Programming Basics',
                'difficulty' => 'medium',
                'points' => 10,
            ]);

            QuestionOption::create(['question_id' => $q3->id, 'text' => 'Integer', 'is_correct' => true, 'order' => 0]);
            QuestionOption::create(['question_id' => $q3->id, 'text' => 'String', 'is_correct' => true, 'order' => 1]);
            QuestionOption::create(['question_id' => $q3->id, 'text' => 'Boolean', 'is_correct' => true, 'order' => 2]);
            QuestionOption::create(['question_id' => $q3->id, 'text' => 'Color', 'is_correct' => false, 'order' => 3]);

            // True/False Question
            $q4 = Question::create([
                'question_text' => 'JavaScript is a compiled programming language.',
                'question_type' => 'true_false',
                'source' => 'module',
                'module_id' => $module1->id,
                'category' => 'JavaScript Basics',
                'difficulty' => 'easy',
                'points' => 3,
            ]);

            QuestionOption::create(['question_id' => $q4->id, 'text' => 'True', 'is_correct' => false, 'order' => 0]);
            QuestionOption::create(['question_id' => $q4->id, 'text' => 'False', 'is_correct' => true, 'order' => 1]);

            // Short Answer Question
            $q5 = Question::create([
                'question_text' => 'What does HTML stand for?',
                'question_type' => 'short_answer',
                'source' => 'module',
                'module_id' => $module1->id,
                'category' => 'Web Development',
                'correct_answer' => 'HyperText Markup Language',
                'difficulty' => 'easy',
                'points' => 5,
            ]);

            // Long Answer Question
            $q6 = Question::create([
                'question_text' => 'Explain the difference between GET and POST methods in HTTP. Provide examples of when to use each.',
                'question_type' => 'long_answer',
                'source' => 'module',
                'module_id' => $module2->id,
                'category' => 'Web Development',
                'correct_answer' => 'GET is used to retrieve data and parameters are visible in URL. POST is used to submit data and parameters are in request body. Use GET for searches, POST for form submissions.',
                'difficulty' => 'medium',
                'points' => 15,
            ]);

            // More module questions
            $q7 = Question::create([
                'question_text' => 'What is the time complexity of binary search?',
                'question_type' => 'single_select',
                'source' => 'module',
                'module_id' => $module2->id,
                'category' => 'Algorithms',
                'difficulty' => 'medium',
                'points' => 8,
            ]);

            QuestionOption::create(['question_id' => $q7->id, 'text' => 'O(log n)', 'is_correct' => true, 'order' => 0]);
            QuestionOption::create(['question_id' => $q7->id, 'text' => 'O(n)', 'is_correct' => false, 'order' => 1]);
            QuestionOption::create(['question_id' => $q7->id, 'text' => 'O(n log n)', 'is_correct' => false, 'order' => 2]);
            QuestionOption::create(['question_id' => $q7->id, 'text' => 'O(1)', 'is_correct' => false, 'order' => 3]);
        }

        // General Questions
        $gq1 = Question::create([
            'question_text' => 'What is the capital city of France?',
            'question_type' => 'single_select',
            'source' => 'general',
            'module_id' => null,
            'category' => 'Geography',
            'difficulty' => 'easy',
            'points' => 3,
        ]);

        QuestionOption::create(['question_id' => $gq1->id, 'text' => 'Paris', 'is_correct' => true, 'order' => 0]);
        QuestionOption::create(['question_id' => $gq1->id, 'text' => 'London', 'is_correct' => false, 'order' => 1]);
        QuestionOption::create(['question_id' => $gq1->id, 'text' => 'Berlin', 'is_correct' => false, 'order' => 2]);
        QuestionOption::create(['question_id' => $gq1->id, 'text' => 'Madrid', 'is_correct' => false, 'order' => 3]);

        $gq2 = Question::create([
            'question_text' => 'Which of the following are primary colors? (Select all that apply)',
            'question_type' => 'multi_select',
            'source' => 'general',
            'module_id' => null,
            'category' => 'Art & Design',
            'difficulty' => 'easy',
            'points' => 5,
        ]);

        QuestionOption::create(['question_id' => $gq2->id, 'text' => 'Red', 'is_correct' => true, 'order' => 0]);
        QuestionOption::create(['question_id' => $gq2->id, 'text' => 'Blue', 'is_correct' => true, 'order' => 1]);
        QuestionOption::create(['question_id' => $gq2->id, 'text' => 'Yellow', 'is_correct' => true, 'order' => 2]);
        QuestionOption::create(['question_id' => $gq2->id, 'text' => 'Green', 'is_correct' => false, 'order' => 3]);
        QuestionOption::create(['question_id' => $gq2->id, 'text' => 'Purple', 'is_correct' => false, 'order' => 4]);

        $gq3 = Question::create([
            'question_text' => 'The Earth revolves around the Sun.',
            'question_type' => 'true_false',
            'source' => 'general',
            'module_id' => null,
            'category' => 'Science',
            'difficulty' => 'easy',
            'points' => 2,
        ]);

        QuestionOption::create(['question_id' => $gq3->id, 'text' => 'True', 'is_correct' => true, 'order' => 0]);
        QuestionOption::create(['question_id' => $gq3->id, 'text' => 'False', 'is_correct' => false, 'order' => 1]);

        $gq4 = Question::create([
            'question_text' => 'Who wrote the novel "1984"?',
            'question_type' => 'short_answer',
            'source' => 'general',
            'module_id' => null,
            'category' => 'Literature',
            'correct_answer' => 'George Orwell',
            'difficulty' => 'medium',
            'points' => 5,
        ]);

        $gq5 = Question::create([
            'question_text' => 'Describe the process of photosynthesis in detail. Include the reactants, products, and the role of chlorophyll.',
            'question_type' => 'long_answer',
            'source' => 'general',
            'module_id' => null,
            'category' => 'Biology',
            'correct_answer' => 'Photosynthesis is the process by which plants convert light energy into chemical energy. Reactants: carbon dioxide and water. Products: glucose and oxygen. Chlorophyll captures light energy.',
            'difficulty' => 'hard',
            'points' => 20,
        ]);

        $gq6 = Question::create([
            'question_text' => 'What is the largest planet in our solar system?',
            'question_type' => 'single_select',
            'source' => 'general',
            'module_id' => null,
            'category' => 'Astronomy',
            'difficulty' => 'easy',
            'points' => 4,
        ]);

        QuestionOption::create(['question_id' => $gq6->id, 'text' => 'Jupiter', 'is_correct' => true, 'order' => 0]);
        QuestionOption::create(['question_id' => $gq6->id, 'text' => 'Saturn', 'is_correct' => false, 'order' => 1]);
        QuestionOption::create(['question_id' => $gq6->id, 'text' => 'Neptune', 'is_correct' => false, 'order' => 2]);
        QuestionOption::create(['question_id' => $gq6->id, 'text' => 'Earth', 'is_correct' => false, 'order' => 3]);
    }
}
