<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Note;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NoteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:120'],
            'category' => ['sometimes', 'nullable', 'string', 'max:50'],
            'is_pinned' => ['sometimes', 'boolean'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        // Retrieve only notes belonging to the authenticated user.
        $query = $request->user()->notes();

        if (!empty($filters['search'])) {
            $search = $filters['search'];

            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (isset($filters['is_pinned'])) {
            $query->where('is_pinned', $filters['is_pinned']);
        }

        $notes = $query
            ->orderByDesc('is_pinned')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return response()->json($notes);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:120'],

            'content' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    $plainText = html_entity_decode(
                        strip_tags($value),
                        ENT_QUOTES | ENT_HTML5,
                        'UTF-8'
                    );

                    $plainText = trim($plainText);

                    $length = mb_strlen($plainText);

                    if ($length < 1) {
                        $fail('The note content must contain at least one character.');
                    }

                    if ($length > 5000) {
                        $fail('The note content may not exceed 5000 characters.');
                    }
                },
            ],

            'category' => ['sometimes', 'nullable', 'string', 'max:50'],

            'color' => [
                'sometimes',
                'required',
                'string',
                'regex:/^#[0-9A-Fa-f]{6}$/',
            ],
        ]);

        //$note = Note::create($validated);
        $note = $request->user()->notes()->create([
            ...$validated,
            'is_pinned' => false,
        ]);

        return response()->json([
            'message' => 'Note created successfully.',
            'data' => $note,
        ], 201);
    }

    public function show(Request $request, Note $note): JsonResponse
    {
        $note = $this->ownedNote($request, $note);

        return response()->json([
            'data' => $note,
        ]);
    }

    public function update(Request $request, Note $note): JsonResponse
    {
        $note = $this->ownedNote($request, $note);

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'min:3', 'max:120'],

            'content' => [
                'sometimes',
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    $plainText = html_entity_decode(
                        strip_tags($value),
                        ENT_QUOTES | ENT_HTML5,
                        'UTF-8'
                    );

                    $plainText = trim($plainText);

                    $length = mb_strlen($plainText);

                    if ($length < 1) {
                        $fail('The note content must contain at least one character.');
                    }

                    if ($length > 5000) {
                        $fail('The note content may not exceed 5000 characters.');
                    }
                },
            ],

            'category' => ['sometimes', 'nullable', 'string', 'max:50'],

            'color' => [
                'sometimes',
                'required',
                'string',
                'regex:/^#[0-9A-Fa-f]{6}$/',
            ],
        ]);

        $note->update($validated);
        $note->refresh();

        return response()->json([
            'message' => 'Note updated successfully.',
            'data' => $note,
        ]);
    }

    public function destroy(Request $request, Note $note)
    {
        $note = $this->ownedNote($request, $note);

        $note->delete();

        return response()->noContent();
    }

    public function pin(Request $request, Note $note): JsonResponse
    {
        $note = $this->ownedNote($request, $note);

        $validated = $request->validate([
            'is_pinned' => ['required', 'boolean'],
        ]);

        $note->update([
            'is_pinned' => $validated['is_pinned'],
        ]);

        return response()->json([
            'message' => 'Pin status updated successfully.',
            'data' => $note->fresh(),
        ]);
    }
    private function ownedNote(Request $request, Note $note): Note
    {
        return $request->user()
            ->notes()
            ->findOrFail($note->id);
    }

}
