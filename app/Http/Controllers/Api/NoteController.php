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
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],

            'created_from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'created_to' => [
                'sometimes',
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:created_from',
            ],

            'sort_by' => [
                'sometimes',
                'string',
                'in:created_at,updated_at,title',
            ],
            'sort_direction' => [
                'sometimes',
                'string',
                'in:asc,desc',
            ],
        ]);

        // Always restrict results to the authenticated user's notes.
        $query = $request->user()->notes();

        // Search note titles and content.
        $search = trim($filters['search'] ?? '');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%");
            });
        }

        // Filter by category.
        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        // Filter by pinned status.
        if (isset($filters['is_pinned'])) {
            $query->where('is_pinned', $filters['is_pinned']);
        }

        if (!empty($filters['created_from'])) {
            $query->where(
                'created_at',
                '>=',
                $filters['created_from'] . ' 00:00:00'
            );
        }

        if (!empty($filters['created_to'])) {
            $query->where(
                'created_at',
                '<',
                \Carbon\Carbon::parse($filters['created_to'])
                    ->addDay()
                    ->startOfDay()
            );
        }

        // Allow only explicitly approved sorting fields.
        $allowedSortFields = [
            'created_at',
            'updated_at',
            'title',
        ];

        $sortBy = $filters['sort_by'] ?? null;
        $sortDirection = $filters['sort_direction'] ?? 'desc';

        if ($sortBy && in_array($sortBy, $allowedSortFields, true)) {
            $query->orderBy($sortBy, $sortDirection)
                ->orderByDesc('id');
        } else {
            // Preserve the existing default ordering.
            $query->orderByDesc('is_pinned')
                ->orderByDesc('updated_at')
                ->orderByDesc('id');
        }

        // Default to 10 records, with a maximum of 50.
        $perPage = $filters['per_page'] ?? 10;

        $notes = $query
            ->paginate($perPage)
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
