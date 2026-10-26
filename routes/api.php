<?php

use App\Http\Controllers\Api\NoteController;
use App\Http\Controllers\Api\NoteAttachmentController;
use Illuminate\Support\Facades\Route;

Route::get('/notes', [NoteController::class, 'index']);
Route::post('/notes', [NoteController::class, 'store']);
Route::get('/notes/{note}', [NoteController::class, 'show']);
Route::patch('/notes/{note}', [NoteController::class, 'update']);
Route::delete('/notes/{note}', [NoteController::class, 'destroy']);
Route::patch('/notes/{note}/pin', [NoteController::class, 'pin']);
Route::post(
    '/notes/{note}/attachments',
    [NoteAttachmentController::class, 'store']
);
Route::get(
    '/notes/{note}/attachments',
    [NoteAttachmentController::class, 'index']
);

Route::delete(
    '/notes/{note}/attachments/{attachment}',
    [NoteAttachmentController::class, 'destroy']
);


Route::get(
    '/notes/{note}/attachments/{attachment}/download',
    [NoteAttachmentController::class, 'download']
);

