<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\Product;
use App\Models\ImportSummary;
use Illuminate\Support\Facades\Storage;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProductImportJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    protected string $filePath;
    protected int $summaryId;

    // Counters
    protected int $total = 0;
    protected int $imported = 0;
    protected int $updated = 0;
    protected int $invalid = 0;
    protected int $duplicates = 0;

    public function __construct(string $filePath, int $summaryId)
    {
        $this->filePath = $filePath;
        $this->summaryId = $summaryId;
    }

    public function handle(): void
    {
        $summary = ImportSummary::find($this->summaryId);
        if (!$summary) {
            return;
        }

        $processedSkus = [];
        $file = Storage::path($this->filePath);

        if (!file_exists($file)) {
            return;
        }

        if (($handle = fopen($file, 'r')) !== false) {
            $header = fgetcsv($handle);

            while (($row = fgetcsv($handle)) !== false) {
                $this->total++;

                $rowData = array_combine($header, $row);

                
                if (empty($rowData['sku']) || empty($rowData['name']) || empty($rowData['price'])) {
                    $this->invalid++;
                    continue;
                }

                // Duplicate 
                if (in_array($rowData['sku'], $processedSkus)) {
                    $this->duplicates++;
                    continue;
                }
                $processedSkus[] = $rowData['sku'];

                // exists
                $existing = Product::where('sku', $rowData['sku'])->first();

                if (!$existing) {
                    
                    Product::create([
                        'sku' => $rowData['sku'],
                        'name' => $rowData['name'],
                        'description' => $rowData['description'] ?? null,
                        'price' => $rowData['price'],
                    ]);
                    $this->imported++;
                } else {
                    
                    $changed = false;

                    if (
                        $existing->name !== $rowData['name'] ||
                        $existing->description !== ($rowData['description'] ?? null) ||
                        (float)$existing->price !== (float)$rowData['price']
                    ) {
                        $existing->update([
                            'name' => $rowData['name'],
                            'description' => $rowData['description'] ?? null,
                            'price' => $rowData['price'],
                        ]);
                        $this->updated++;
                        $changed = true;
                    }

                    if (!$changed) {
                        
                        $this->duplicates++;
                    }
                }
            }
            fclose($handle);
        }

        $summary->update([
            'total'        => $this->total,
            'imported'     => $this->imported,
            'updated'      => $this->updated,
            'invalid'      => $this->invalid,
            'duplicates'   => $this->duplicates,
            'is_completed' => true,
        ]);
    }

}
