<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Note;
use Illuminate\Http\Request;

class NoteController extends Controller
{
    public function index(Request $request)
    {
        $notes = Note::query()
            ->with(['createdBy'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'notes' => $notes->map(fn (Note $n) => $this->formatNote($n)),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'body' => 'required|string',
        ]);

        $note = Note::create([
            'created_by_user_id' => $request->user()->id,
            'title' => $validated['title'] ?? null,
            'body' => $validated['body'],
        ]);

        $note->load(['createdBy']);

        return response()->json([
            'note' => $this->formatNote($note),
        ], 201);
    }

    public function update(Request $request, Note $note)
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'body' => 'required|string',
        ]);

        $note->update([
            'title' => $validated['title'] ?? null,
            'body' => $validated['body'],
        ]);

        $note->load(['createdBy']);

        return response()->json([
            'note' => $this->formatNote($note),
        ]);
    }

    public function destroy(Note $note)
    {
        $note->delete();

        return response()->json(['message' => 'Note deleted']);
    }

    private function formatNote(Note $note): array
    {
        return [
            'id' => (string) $note->id,
            'title' => $note->title,
            'body' => $note->body,
            'createdBy' => $note->createdBy ? [
                'id' => (string) $note->createdBy->id,
                'name' => $note->createdBy->name,
                'email' => $note->createdBy->email,
            ] : null,
            'createdAt' => $note->created_at?->toISOString(),
            'updatedAt' => $note->updated_at?->toISOString(),
        ];
    }
}
