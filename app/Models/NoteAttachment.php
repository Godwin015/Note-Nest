<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NoteAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'note_id',
        'original_name',
        'file_path',
        'mime_type',
        'file_size',
    ];

    public function note(): BelongsTo
    {
        return $this->belongsTo(Note::class);
    }
}
