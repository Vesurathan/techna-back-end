<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Module;
use App\Support\MediaDisk;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class QuestionController extends Controller
{
    public function index(Request $request)
    {
        $query = Question::with(['module', 'options']);

        // Filter by source
        if ($request->has('source') && $request->source) {
            $query->where('source', $request->source);
        }

        // Filter by module_id
        if ($request->has('module_id') && $request->module_id) {
            $query->where('module_id', $request->module_id);
        }

        // Filter by category
        if ($request->has('category') && $request->category) {
            $query->where('category', $request->category);
        }

        // Filter by question_type
        if ($request->has('question_type') && $request->question_type) {
            $query->where('question_type', $request->question_type);
        }

        // Search
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('question_text', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%");
            });
        }

        $page = (int) $request->get('page', 1);
        $perPage = 10;

        $questions = $query->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'questions' => $questions->map(function ($question) {
                return $this->formatQuestion($question);
            }),
            'pagination' => [
                'current_page' => $questions->currentPage(),
                'per_page' => $questions->perPage(),
                'total' => $questions->total(),
                'last_page' => $questions->lastPage(),
                'from' => $questions->firstItem(),
                'to' => $questions->lastItem(),
                'has_more_pages' => $questions->hasMorePages(),
            ],
        ]);
    }

    public function show(Question $question)
    {
        $question->load(['module', 'options']);
        return response()->json([
            'question' => $this->formatQuestion($question),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'question_text' => 'required|string',
            'question_type' => ['required', Rule::in(['short_answer', 'long_answer', 'single_select', 'multi_select', 'true_false'])],
            'source' => ['required', Rule::in(['module', 'general'])],
            'module_id' => 'nullable|integer|exists:modules,id',
            'category' => 'required|string|max:255',
            'correct_answer' => 'nullable|string',
            'difficulty' => ['nullable', Rule::in(['easy', 'medium', 'hard'])],
            'points' => 'nullable|integer|min:0',
            'question_image' => 'nullable|image|max:2048',
            'options' => 'nullable|array',
            'options.*.text' => 'required_with:options|string',
            'options.*.is_correct' => 'nullable|boolean',
            'options.*.order' => 'nullable|integer',
            'options.*.image' => 'nullable|image|max:2048',
        ]);

        // Validate module_id is required if source is module
        if ($validated['source'] === 'module' && empty($validated['module_id'])) {
            return response()->json([
                'message' => 'Module ID is required for module questions',
            ], 422);
        }

        // Validate options for select questions
        if (in_array($validated['question_type'], ['single_select', 'multi_select', 'true_false'])) {
            if (empty($validated['options']) || count($validated['options']) < 2) {
                return response()->json([
                    'message' => 'At least 2 options are required for select questions',
                ], 422);
            }

            $hasCorrect = collect($validated['options'])->some(function ($opt) {
                return isset($opt['is_correct']) && ($opt['is_correct'] === true || $opt['is_correct'] === '1' || $opt['is_correct'] === 1);
            });

            if (!$hasCorrect) {
                return response()->json([
                    'message' => 'At least one option must be marked as correct',
                ], 422);
            }

            if ($validated['question_type'] === 'single_select') {
                $correctCount = collect($validated['options'])->filter(function ($opt) {
                    return isset($opt['is_correct']) && ($opt['is_correct'] === true || $opt['is_correct'] === '1' || $opt['is_correct'] === 1);
                })->count();

                if ($correctCount !== 1) {
                    return response()->json([
                        'message' => 'Single select questions must have exactly one correct answer',
                    ], 422);
                }
            }
        } else {
            // Validate correct_answer for short/long answer
            if (empty($validated['correct_answer'])) {
                return response()->json([
                    'message' => 'Correct answer is required for short/long answer questions',
                ], 422);
            }
        }

        DB::beginTransaction();
        try {
            // Handle question image upload
            $imageUrl = null;
            if ($request->hasFile('question_image')) {
                $imageUrl = $this->storeImage($request->file('question_image'), 'questions');
            }

            $question = Question::create([
                'question_text' => $validated['question_text'],
                'question_type' => $validated['question_type'],
                'source' => $validated['source'],
                'module_id' => $validated['module_id'] ?? null,
                'category' => $validated['category'],
                'correct_answer' => $validated['correct_answer'] ?? null,
                'image_url' => $imageUrl,
                'difficulty' => $validated['difficulty'] ?? null,
                'points' => $validated['points'] ?? null,
            ]);

            // Create options for select questions
            if (in_array($validated['question_type'], ['single_select', 'multi_select', 'true_false']) && !empty($validated['options'])) {
                foreach ($validated['options'] as $index => $optionData) {
                    $optionImageUrl = null;
                    // Handle file upload from FormData
                    $fileKey = "options.{$index}.image";
                    if ($request->hasFile($fileKey)) {
                        $optionImageUrl = $this->storeImage($request->file($fileKey), 'question-options');
                    }

                    QuestionOption::create([
                        'question_id' => $question->id,
                        'text' => $optionData['text'],
                        'is_correct' => isset($optionData['is_correct']) && ($optionData['is_correct'] === true || $optionData['is_correct'] === '1' || $optionData['is_correct'] === 1),
                        'image_url' => $optionImageUrl,
                        'order' => $optionData['order'] ?? $index,
                    ]);
                }
            }

            $question->load(['module', 'options']);

            DB::commit();

            return response()->json([
                'question' => $this->formatQuestion($question),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create question: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, Question $question)
    {
        $validated = $request->validate([
            'question_text' => 'required|string',
            'question_type' => ['required', Rule::in(['short_answer', 'long_answer', 'single_select', 'multi_select', 'true_false'])],
            'source' => ['required', Rule::in(['module', 'general'])],
            'module_id' => 'nullable|integer|exists:modules,id',
            'category' => 'required|string|max:255',
            'correct_answer' => 'nullable|string',
            'difficulty' => ['nullable', Rule::in(['easy', 'medium', 'hard'])],
            'points' => 'nullable|integer|min:0',
            'question_image' => 'nullable|image|max:2048',
            'options' => 'nullable|array',
            'options.*.text' => 'required_with:options|string',
            'options.*.is_correct' => 'nullable|boolean',
            'options.*.order' => 'nullable|integer',
            'options.*.image' => 'nullable|image|max:2048',
        ]);

        // Validate module_id is required if source is module
        if ($validated['source'] === 'module' && empty($validated['module_id'])) {
            return response()->json([
                'message' => 'Module ID is required for module questions',
            ], 422);
        }

        // Validate options for select questions
        if (in_array($validated['question_type'], ['single_select', 'multi_select', 'true_false'])) {
            if (empty($validated['options']) || count($validated['options']) < 2) {
                return response()->json([
                    'message' => 'At least 2 options are required for select questions',
                ], 422);
            }

            $hasCorrect = collect($validated['options'])->some(function ($opt) {
                return isset($opt['is_correct']) && ($opt['is_correct'] === true || $opt['is_correct'] === '1' || $opt['is_correct'] === 1);
            });

            if (!$hasCorrect) {
                return response()->json([
                    'message' => 'At least one option must be marked as correct',
                ], 422);
            }

            if ($validated['question_type'] === 'single_select') {
                $correctCount = collect($validated['options'])->filter(function ($opt) {
                    return isset($opt['is_correct']) && ($opt['is_correct'] === true || $opt['is_correct'] === '1' || $opt['is_correct'] === 1);
                })->count();

                if ($correctCount !== 1) {
                    return response()->json([
                        'message' => 'Single select questions must have exactly one correct answer',
                    ], 422);
                }
            }
        } else {
            // Validate correct_answer for short/long answer
            if (empty($validated['correct_answer'])) {
                return response()->json([
                    'message' => 'Correct answer is required for short/long answer questions',
                ], 422);
            }
        }

        DB::beginTransaction();
        try {
            // Handle question image upload
            $imageUrl = $question->image_url;
            if ($request->hasFile('question_image')) {
                // Delete old image if exists
                if ($imageUrl) {
                    $this->deleteImage($imageUrl);
                }
                $imageUrl = $this->storeImage($request->file('question_image'), 'questions');
            }

            $question->update([
                'question_text' => $validated['question_text'],
                'question_type' => $validated['question_type'],
                'source' => $validated['source'],
                'module_id' => $validated['module_id'] ?? null,
                'category' => $validated['category'],
                'correct_answer' => $validated['correct_answer'] ?? null,
                'image_url' => $imageUrl,
                'difficulty' => $validated['difficulty'] ?? null,
                'points' => $validated['points'] ?? null,
            ]);

            // Delete existing options
            $question->options()->delete();

            // Create new options for select questions
            if (in_array($validated['question_type'], ['single_select', 'multi_select', 'true_false']) && !empty($validated['options'])) {
                foreach ($validated['options'] as $index => $optionData) {
                    $optionImageUrl = null;
                    // Handle file upload from FormData
                    $fileKey = "options.{$index}.image";
                    if ($request->hasFile($fileKey)) {
                        $optionImageUrl = $this->storeImage($request->file($fileKey), 'question-options');
                    }

                    QuestionOption::create([
                        'question_id' => $question->id,
                        'text' => $optionData['text'],
                        'is_correct' => isset($optionData['is_correct']) && ($optionData['is_correct'] === true || $optionData['is_correct'] === '1' || $optionData['is_correct'] === 1),
                        'image_url' => $optionImageUrl,
                        'order' => $optionData['order'] ?? $index,
                    ]);
                }
            }

            $question->load(['module', 'options']);

            DB::commit();

            return response()->json([
                'question' => $this->formatQuestion($question),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update question: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Question $question)
    {
        DB::beginTransaction();
        try {
            // Delete question image if exists
            if ($question->image_url) {
                $this->deleteImage($question->image_url);
            }

            // Delete option images
            foreach ($question->options as $option) {
                if ($option->image_url) {
                    $this->deleteImage($option->image_url);
                }
            }

            $question->delete();

            DB::commit();

            return response()->json(['message' => 'Question deleted successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to delete question: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getCategories(Request $request)
    {
        $query = Question::select('category')
            ->distinct();

        if ($request->has('module_id') && $request->module_id) {
            $query->where('module_id', $request->module_id);
        }

        if ($request->has('source') && $request->source) {
            $query->where('source', $request->source);
        }

        $categories = $query->orderBy('category')->pluck('category');

        return response()->json([
            'categories' => $categories,
        ]);
    }

    private function formatQuestion(Question $question): array
    {
        return [
            'id' => $question->id,
            'question_text' => $question->question_text,
            'question_type' => $question->question_type,
            'source' => $question->source,
            'module_id' => $question->module_id,
            'module' => $question->module ? [
                'id' => $question->module->id,
                'name' => $question->module->name,
            ] : null,
            'category' => $question->category,
            'options' => $question->options->map(function ($option) {
                return [
                    'id' => $option->id,
                    'text' => $option->text,
                    'is_correct' => $option->is_correct,
                    'image_url' => MediaDisk::publicUrl($option->image_url),
                    'order' => $option->order,
                ];
            }),
            'correct_answer' => $question->correct_answer,
            'image_url' => MediaDisk::publicUrl($question->image_url),
            'difficulty' => $question->difficulty,
            'points' => $question->points,
            'created_at' => $question->created_at,
            'updated_at' => $question->updated_at,
        ];
    }

    private function storeImage($file, $folder = 'questions'): string
    {
        $path = MediaDisk::storeUpload($file, $folder);
        $url = MediaDisk::publicUrl($path);

        return $url ?? '';
    }

    private function deleteImage($imageUrl): void
    {
        $key = MediaDisk::keyFromStoredUrl($imageUrl);
        MediaDisk::deleteIfExists($key);
    }
}
