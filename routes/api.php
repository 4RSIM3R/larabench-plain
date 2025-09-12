<?php
// routes/api.php - Add these routes to your existing routes/api.php file

use App\Http\Controllers\BenchController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

// Benchmark routes group
Route::prefix('benchmark')->group(function () {
    // Health check
    Route::get('/health', [BenchController::class, 'healthCheck']);
    
    // Individual benchmark endpoints for K6 testing
    Route::get('/cpu', [BenchController::class, 'testCpuIntensiveEndpoint']);
    Route::get('/database', [BenchController::class, 'testDatabaseEndpoint']);
    Route::get('/cache', [BenchController::class, 'testCacheEndpoint']);
    Route::get('/eloquent', [BenchController::class, 'testEloquentEndpoint']);
    Route::get('/features', [BenchController::class, 'testLaravelFeaturesEndpoint']);
    
    // Full benchmark test
    Route::get('/all', [BenchController::class, 'runAllBenchmarks']);
});

// Optional: Add a route to list/view stored results
Route::get('/benchmark-results', function () {
    $files = Storage::disk('public')->files('benchmarks');
    $results = [];
    
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'json') {
            $results[] = [
                'filename' => basename($file),
                'url' => url('benchmark-results/' . basename($file)),
                'size' => Storage::disk('public')->size($file),
                'modified' => Storage::disk('public')->lastModified($file),
                'created' => date('Y-m-d H:i:s', Storage::disk('public')->lastModified($file))
            ];
        }
    }
    
    // Sort by modification time (newest first)
    usort($results, function($a, $b) {
        return $b['modified'] - $a['modified'];
    });
    
    return response()->json([
        'message' => 'Available benchmark results',
        'count' => count($results),
        'files' => $results,
        'storage_path' => storage_path('app/public/benchmarks'),
        'web_path' => url('benchmark-results/')
    ]);
});