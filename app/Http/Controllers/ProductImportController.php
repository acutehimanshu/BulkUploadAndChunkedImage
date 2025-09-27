<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Jobs\ProductImportJob;
use Illuminate\Support\Facades\Storage;
use App\Models\ImportSummary;

class ProductImportController extends Controller
{
    public function showImportForm()
    {
        $summaries = ImportSummary::latest()->take(10)->get();
        return view('import', compact('summaries'));
    }

    public function import(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|mimes:csv,txt|max:20480',
        ]);

        $path = $request->file('csv_file')->store('imports');

        $summary = ImportSummary::create([
            'file_name' => basename($path),
        ]);

        ProductImportJob::dispatch($path, $summary->id);

        return back()->with('message', 'Import started. You will get the summary once completed.');
    }
}
