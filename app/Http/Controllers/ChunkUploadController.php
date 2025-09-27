<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Upload;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Jobs\ProcessImageJob;
use Symfony\Component\HttpFoundation\Response;

class ChunkUploadController extends Controller
{
    public function initiate(Request $request)
    {
        $request->validate([
            'file_name' => 'required|string',
            'total_size' => 'required|integer|min:1',
            'mime_type' => 'nullable|string',
            'checksum' => 'nullable|string', // client-provided checksum (sha256)
        ]);

        
        $tmpPath = 'uploads/tmp/' . uniqid('up_') . '.tmp';

        
        Storage::disk('local')->put($tmpPath, '');

        $upload = Upload::create([
            'file_name' => $request->input('file_name'),
            'mime_type' => $request->input('mime_type'),
            'disk' => 'local',
            'path' => $tmpPath,
            'checksum' => $request->input('checksum'),
            'total_size' => $request->input('total_size'),
            'uploaded_size' => 0,
            'received_chunks' => [],
            'is_completed' => false,
        ]);

        return response()->json(['upload_id' => $upload->id], Response::HTTP_CREATED);
    }

    /**
     * Upload a chunk.
     * Request: upload_id, chunk_index (0-based), chunk file.
     */
    public function uploadChunk(Request $request)
    {
        $request->validate([
            'upload_id' => 'required|integer|exists:uploads,id',
            'chunk_index' => 'required|integer|min:0',
            'chunk' => 'required|file',
        ]);

        $upload = Upload::lockForUpdate()->find($request->input('upload_id'));
        if (!$upload || $upload->is_completed) {
            return response()->json(['message' => 'Upload not found or already completed'], Response::HTTP_BAD_REQUEST);
        }

        $chunkIndex = (int)$request->input('chunk_index');

        
        $received = $upload->received_chunks ?? [];
        if (in_array($chunkIndex, $received, true)) {
            return response()->json(['message' => 'Chunk already received'], Response::HTTP_OK);
        }

        
        $tmpRelative = $upload->path; // relative to storage/app
        $tmpFullPath = Storage::disk('local')->path($tmpRelative);
        $chunkFile = $request->file('chunk')->getRealPath();

        // chunk under file 
        $dest = fopen($tmpFullPath, 'ab');
        if (!$dest) {
            return response()->json(['message' => 'Unable to open temp file'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // Lock file while writing
        if (flock($dest, LOCK_EX)) {
            $src = fopen($chunkFile, 'rb');
            stream_copy_to_stream($src, $dest);
            fclose($src);
            fflush($dest);
            flock($dest, LOCK_UN);
            fclose($dest);
        } else {
            fclose($dest);
            return response()->json(['message' => 'Unable to lock temp file'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // update DB auto
        DB::transaction(function () use ($upload, $chunkIndex, $request) {
            // reload model
            $upload->refresh();
            $received = $upload->received_chunks ?? [];

            if (!in_array($chunkIndex, $received, true)) {
                $chunkSize = $request->file('chunk')->getSize() ?? 0;
                $received[] = $chunkIndex;
                $upload->received_chunks = $received;
                $upload->uploaded_size = $upload->uploaded_size + $chunkSize;
                $upload->save();
            }
        });

        return response()->json(['message' => 'Chunk received'], Response::HTTP_OK);
    }

    
    public function complete(Request $request)
    {
        
        $request->validate([
            'upload_id' => 'required|integer|exists:uploads,id',
        ]);

        $upload = Upload::find($request->input('upload_id'));
        if (!$upload || $upload->is_completed) {
            return response()->json(['message' => 'Upload not found or already completed'], Response::HTTP_BAD_REQUEST);
        }

        
        $tmpFullPath = Storage::disk('local')->path($upload->path);
        $actualSize = file_exists($tmpFullPath) ? filesize($tmpFullPath) : 0;
        if ($actualSize != $upload->total_size) {
            return response()->json([
                'message' => 'Upload incomplete. Size mismatch.',
                'expected' => $upload->total_size,
                'actual' => $actualSize,
            ], Response::HTTP_BAD_REQUEST);
        }

        
        if ($upload->checksum) {
            $actualHash = hash_file('sha256', $tmpFullPath);
            if (!hash_equals($upload->checksum, $actualHash)) {
                return response()->json(['message' => 'Checksum mismatch'], Response::HTTP_BAD_REQUEST);
            }
        }

        
        $upload->is_completed = true;
        $upload->save();
        
        ProcessImageJob::dispatch($upload->id);
        echo "here";
exit;
        return response()->json(['message' => 'Upload completed and processing started'], Response::HTTP_OK);
    }
}
