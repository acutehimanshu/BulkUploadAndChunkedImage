<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Image extends Model
{
    use HasFactory;

    protected $fillable = [
        'file_path','checksum','variants','imageable_id','imageable_type'
    ];

    protected $casts = [
        'variants' => 'array',
    ];

    public function imageable()
    {
        return $this->morphTo();
    }
}
