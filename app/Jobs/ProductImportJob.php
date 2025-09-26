<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\Product;
use Illuminate\Support\Facades\Storage;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\ImportSummary;

class ProductImportJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;
    protected $filePath;

    // Counters
    protected $total = 0;
    protected $imported = 0;
    protected $updated = 0;
    protected $invalid = 0;
    protected $duplicates = 0;

    /**
     * Create a new job instance.
     */
    public function __construct($filePath)
    {
        $this->filePath = $filePath;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        $summary = ImportSummary::find($this->summaryId);
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

                // checking data
                if (empty($rowData['sku']) || empty($rowData['name']) || empty($rowData['price'])) {
                    $this->invalid++;
                    continue;
                }
                // checking dublicat
                if (in_array($rowData['sku'], $processedSkus)) {
                    $this->duplicates++;
                    continue;
                }
                $processedSkus[] = $rowData['sku'];

                $product = Product::updateOrCreate(
                    ['sku' => $rowData['sku']],
                    [
                        'name' => $rowData['name'],
                        'description' => $rowData['description'] ?? null,
                        'price' => $rowData['price'],
                    ]
                );

                if ($product->wasRecentlyCreated) {
                    $this->imported++;
                } elseif ($product->wasChanged()) {
                    $this->updated++;
                }
            }
            fclose($handle);
        }

        $summary->update([
            'total'      => $this->total,
            'imported'   => $this->imported,
            'updated'    => $this->updated,
            'invalid'    => $this->invalid,
            'duplicates' => $this->duplicates,
            'is_completed' => true,
        ]);
    }
}
