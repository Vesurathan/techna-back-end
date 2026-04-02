<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Questionnaire;
use App\Models\Question;
use App\Models\Module;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class QuestionnaireController extends Controller
{
    public function index(Request $request)
    {
        $query = Questionnaire::with(['module', 'questions']);

        // Filter by source
        if ($request->has('source') && $request->source) {
            $query->where('source', $request->source);
        }

        // Filter by module_id
        if ($request->has('module_id') && $request->module_id) {
            $query->where('module_id', $request->module_id);
        }

        // Filter by batch
        if ($request->has('batch') && $request->batch) {
            $query->where('batch', $request->batch);
        }

        $page = (int) $request->get('page', 1);
        $perPage = 10;

        $questionnaires = $query->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'questionnaires' => $questionnaires->map(function ($questionnaire) {
                return $this->formatQuestionnaire($questionnaire);
            }),
            'pagination' => [
                'current_page' => $questionnaires->currentPage(),
                'per_page' => $questionnaires->perPage(),
                'total' => $questionnaires->total(),
                'last_page' => $questionnaires->lastPage(),
                'from' => $questionnaires->firstItem(),
                'to' => $questionnaires->lastItem(),
                'has_more_pages' => $questionnaires->hasMorePages(),
            ],
        ]);
    }

    public function show(Questionnaire $questionnaire)
    {
        $questionnaire->load(['module', 'questions.options']);
        return response()->json([
            'questionnaire' => $this->formatQuestionnaire($questionnaire, true),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'source' => ['required', Rule::in(['module', 'general'])],
            'module_id' => 'nullable|integer|exists:modules,id',
            'batch' => 'required|string|max:50',
            'description' => 'nullable|string',
            'selected_categories' => 'required|array',
            'selected_categories.*' => 'string',
            'question_counts' => 'required|array',
            'question_counts.short_answer' => 'nullable|integer|min:0',
            'question_counts.long_answer' => 'nullable|integer|min:0',
            'question_counts.single_select' => 'nullable|integer|min:0',
            'question_counts.multi_select' => 'nullable|integer|min:0',
            'question_counts.true_false' => 'nullable|integer|min:0',
        ]);

        // Validate module_id is required if source is module
        if ($validated['source'] === 'module' && empty($validated['module_id'])) {
            return response()->json([
                'message' => 'Module ID is required for module questionnaires',
            ], 422);
        }

        // Calculate total questions
        $totalQuestions = array_sum($validated['question_counts']);
        if ($totalQuestions === 0) {
            return response()->json([
                'message' => 'At least one question type must have a count greater than 0',
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Build query to get questions
            $questionQuery = Question::query();

            // Filter by source
            $questionQuery->where('source', $validated['source']);

            // Filter by module_id if module source
            if ($validated['source'] === 'module' && $validated['module_id']) {
                $questionQuery->where('module_id', $validated['module_id']);
            }

            // Filter by categories
            if (!empty($validated['selected_categories'])) {
                $questionQuery->whereIn('category', $validated['selected_categories']);
            }

            // Get questions by type
            $selectedQuestions = [];
            $order = 1;

            foreach ($validated['question_counts'] as $type => $count) {
                if ($count > 0) {
                    $questions = $questionQuery->where('question_type', $type)
                        ->inRandomOrder()
                        ->limit($count)
                        ->get();

                    if ($questions->count() < $count) {
                        DB::rollBack();
                        return response()->json([
                            'message' => "Not enough {$type} questions available. Found {$questions->count()}, required {$count}.",
                        ], 422);
                    }

                    foreach ($questions as $question) {
                        $selectedQuestions[] = [
                            'question_id' => $question->id,
                            'order' => $order++,
                        ];
                    }
                }
            }

            // Create questionnaire
            $questionnaire = Questionnaire::create([
                'title' => $validated['title'],
                'module_id' => $validated['module_id'] ?? null,
                'batch' => $validated['batch'],
                'description' => $validated['description'] ?? null,
                'question_counts' => $validated['question_counts'],
                'selected_categories' => $validated['selected_categories'],
                'total_questions' => $totalQuestions,
                'source' => $validated['source'],
            ]);

            // Attach questions
            foreach ($selectedQuestions as $sq) {
                DB::table('questionnaire_questions')->insert([
                    'questionnaire_id' => $questionnaire->id,
                    'question_id' => $sq['question_id'],
                    'order' => $sq['order'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $questionnaire->load(['module', 'questions']);

            DB::commit();

            return response()->json([
                'questionnaire' => $this->formatQuestionnaire($questionnaire),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create questionnaire: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Questionnaire $questionnaire)
    {
        $questionnaire->delete();
        return response()->json(['message' => 'Questionnaire deleted successfully']);
    }

    private function formatQuestionnaire(Questionnaire $questionnaire, $includeQuestions = false): array
    {
        $data = [
            'id' => $questionnaire->id,
            'title' => $questionnaire->title,
            'module_id' => $questionnaire->module_id,
            'module' => $questionnaire->module ? [
                'id' => $questionnaire->module->id,
                'name' => $questionnaire->module->name,
            ] : null,
            'batch' => $questionnaire->batch,
            'description' => $questionnaire->description,
            'question_counts' => $questionnaire->question_counts,
            'selected_categories' => $questionnaire->selected_categories,
            'total_questions' => $questionnaire->total_questions,
            'source' => $questionnaire->source,
            'created_at' => $questionnaire->created_at,
            'updated_at' => $questionnaire->updated_at,
        ];

        if ($includeQuestions) {
            $data['questions'] = $questionnaire->questions->map(function ($question) {
                return [
                    'id' => $question->id,
                    'question_text' => $question->question_text,
                    'question_type' => $question->question_type,
                    'category' => $question->category,
                    'options' => $question->options->map(function ($option) {
                        return [
                            'id' => $option->id,
                            'text' => $option->text,
                            'is_correct' => $option->is_correct,
                            'image_url' => $option->image_url,
                            'order' => $option->order,
                        ];
                    }),
                    'correct_answer' => $question->correct_answer,
                    'image_url' => $question->image_url,
                    'difficulty' => $question->difficulty,
                    'points' => $question->points,
                ];
            });
        }

        return $data;
    }
}
