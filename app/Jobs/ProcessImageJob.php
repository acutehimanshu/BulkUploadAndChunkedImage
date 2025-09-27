<?php

namespace App\Jobs;

use App\Models\Upload;
use App\Models\Image;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable; // 👈 ADD THIS
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Intervention\Image\Facades\Image as InterventionImage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProcessImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels; // 👈 ADD Dispatchable here

    protected int $uploadId;

    public function __construct(int $uploadId)
    {
        $this->uploadId = $uploadId;
    }

    public function handle(): void
    {
        $upload = Upload::find($this->uploadId);
        if (!$upload || !$upload->is_completed) {
            return;
        }

        $tmpPath = Storage::disk('local')->path($upload->path);
        if (!file_exists($tmpPath)) {
            return;
        }

        $checksum = $upload->checksum ?? hash_file('sha256', $tmpPath);

        // If an Image with this checksum already exists, reuse it (idempotency)
        $existingImage = Image::where('checksum', $checksum)->first();
        if ($existingImage) {
            return;
        }

        $originalDir = 'images/original';
        $variantDir = 'images/variants';
        Storage::disk('public')->makeDirectory($originalDir);
        Storage::disk('public')->makeDirectory($variantDir);

        $ext = pathinfo($upload->file_name, PATHINFO_EXTENSION) ?: 'jpg';
        $basename = pathinfo($upload->file_name, PATHINFO_FILENAME);
        $finalName = $basename . '_' . Str::random(8) . '.' . $ext;
        $finalRelative = $originalDir . '/' . $finalName;
        Storage::disk('public')->put($finalRelative, fopen($tmpPath, 'rb'));

        $variants = [];
        $sizes = [1024, 512, 256];

        foreach ($sizes as $size) {
            $img = InterventionImage::make($tmpPath);
            $img->resize($size, $size, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
            $variantName = $basename . '_' . $size . '_' . Str::random(6) . '.' . $ext;
            $variantPath = $variantDir . '/' . $variantName;

            $tmpVariant = sys_get_temp_dir() . '/' . $variantName;
            $img->save($tmpVariant);
            Storage::disk('public')->put($variantPath, fopen($tmpVariant, 'rb'));
            @unlink($tmpVariant);

            $variants[$size] = 'storage/' . $variantPath;
        }

        Image::create([
            'file_path' => 'storage/' . $finalRelative,
            'checksum' => $checksum,
            'variants' => $variants,
        ]);

        @unlink($tmpPath);
    }
}
