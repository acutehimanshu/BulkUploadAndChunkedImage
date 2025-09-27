<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Upload extends Model
{
    use HasFactory;

    protected $fillable = [
        'file_name','mime_type','disk','path','checksum','total_size','uploaded_size','received_chunks','is_completed'
    ];

    protected $casts = [
        'received_chunks' => 'array',
        'is_completed' => 'boolean',
    ];
}
