<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Note;
use App\Models\NoteAttachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class NoteAttachmentController extends Controller
{
    /**
     * Upload an attachment to a note.
     */
    public function store(Request $request, Note $note): JsonResponse
    {
        // Ensure the authenticated user owns the note.
        $note = $request->user()
            ->notes()
            ->findOrFail($note->id);

        $validated = $request->validate([
            'file' => [
                'required',
                'file',
                'max:51200',
                'mimes:pdf,doc,docx,txt,jpg,jpeg,png,webp',
            ],
        ]);

        if ($note->attachments()->count() >= 5) {
            return response()->json([
                'message' => 'A note may have a maximum of 5 attachments.',
            ], 422);
        }

        $file = $validated['file'];

        $currentSize = $note->attachments()->sum('file_size');

        if (
            $currentSize + $file->getSize()
            > 100 * 1024 * 1024
        ) {
            return response()->json([
                'message' => 'The combined attachment size may not exceed 100 MB.',
            ], 422);
        }

        $path = $file->store(
            'note-attachments/' . $note->id,
            'public'
        );

        $attachment = $note->attachments()->create([
            'original_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
        ]);

        return response()->json([
            'message' => 'Attachment uploaded successfully.',
            'data' => $attachment,
        ], 201);
    }

    /**
     * List attachments belonging to a note.
     */
    public function index(Request $request, Note $note): JsonResponse
    {
        // Ensure the authenticated user owns the note.
        $note = $request->user()
            ->notes()
            ->findOrFail($note->id);

        $attachments = $note->attachments()
            ->latest()
            ->get();

        return response()->json([
            'data' => $attachments,
        ]);
    }

    /**
     * Delete an attachment and its stored file.
     */
    public function destroy(
        Request $request,
        Note $note,
        NoteAttachment $attachment
    ): JsonResponse {
        // Ensure the authenticated user owns the note.
        $note = $request->user()
            ->notes()
            ->findOrFail($note->id);

        if ((int) $attachment->note_id !== (int) $note->id) {
            return response()->json([
                'message' => 'Attachment not found for this note.',
            ], 404);
        }

        Storage::disk('public')->delete(
            $attachment->file_path
        );

        $attachment->delete();

        return response()->json([
            'message' => 'Attachment deleted successfully.',
        ]);
    }

    /**
     * Preview an image attachment belonging to a note.
     */
    public function preview(
        Request $request,
        Note $note,
        NoteAttachment $attachment
    ) {
        // Ensure the authenticated user owns the note.
        $note = $request->user()
            ->notes()
            ->findOrFail($note->id);

        // Ensure the attachment belongs to the requested note.
        if ((int) $attachment->note_id !== (int) $note->id) {
            return response()->json([
                'message' => 'Attachment not found for this note.',
            ], 404);
        }

        // Only allow image previews.
        if (!str_starts_with($attachment->mime_type, 'image/')) {
            return response()->json([
                'message' => 'Only image attachments can be previewed.',
            ], 422);
        }

        // Ensure the image exists in storage.
        if (!Storage::disk('public')->exists($attachment->file_path)) {
            return response()->json([
                'message' => 'Attachment file not found.',
            ], 404);
        }

        // Return the image directly to the browser.
        return response()->file(
            Storage::disk('public')->path($attachment->file_path),
            [
                'Content-Type' => $attachment->mime_type,
                'Content-Disposition' => 'inline',
            ]
        );
    }

    /**
     * Download an attachment belonging to a note.
     */
    public function download(
        Request $request,
        Note $note,
        NoteAttachment $attachment
    ) {
        // Ensure the authenticated user owns the note.
        $note = $request->user()
            ->notes()
            ->findOrFail($note->id);

        if ((int) $attachment->note_id !== (int) $note->id) {
            return response()->json([
                'message' => 'Attachment not found for this note.',
            ], 404);
        }

        if (!Storage::disk('public')->exists($attachment->file_path)) {
            return response()->json([
                'message' => 'Attachment file not found.',
            ], 404);
        }

        return Storage::disk('public')->download(
            $attachment->file_path,
            $attachment->original_name
        );
    }
}
