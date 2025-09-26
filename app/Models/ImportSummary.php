<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ImportSummary extends Model
{
    use HasFactory;

    protected $fillable = [
        'file_name',
        'total',
        'imported',
        'updated',
        'invalid',
        'duplicates',
        'is_completed',
    ];
}

