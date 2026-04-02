<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Questionnaire extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'module_id',
        'batch',
        'description',
        'question_counts',
        'selected_categories',
        'total_questions',
        'source',
    ];

    protected $casts = [
        'question_counts' => 'array',
        'selected_categories' => 'array',
        'total_questions' => 'integer',
    ];

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'questionnaire_questions')
            ->withPivot('order')
            ->orderBy('questionnaire_questions.order');
    }
}
