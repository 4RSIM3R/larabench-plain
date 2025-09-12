<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\User;
use Carbon\Carbon;

class BenchController extends Controller
{
    private $results = [];
    private $startTime;
    private $startMemory;

    public function __construct()
    {
        ini_set('memory_limit', '512M');
        set_time_limit(300); // 5 minutes
    }

    /**
     * Run all benchmarks and return results
     */
    public function runAllBenchmarks(Request $request): JsonResponse
    {
        $this->results = [];

        $systemInfo = $this->getSystemInfo();

        // Run all benchmark tests
        $this->testCpuIntensive();
        $this->testMemoryIntensive();
        $this->testStringOperations();
        $this->testArrayOperations();
        $this->testObjectOperations();
        $this->testDatabaseOperations();
        $this->testCacheOperations();
        $this->testFileOperations();
        $this->testEloquentOperations();
        $this->testLaravelFeatures();

        $response = [
            'system_info' => $systemInfo,
            'results' => $this->results,
            'summary' => $this->calculateSummary(),
            'timestamp' => now()->toISOString(),
        ];

        // Store results for comparison
        $this->storeResults($response);

        return response()->json($response);
    }

    /**
     * Individual test endpoints for K6 testing
     */
    public function testCpuIntensiveEndpoint(): JsonResponse
    {
        $result = $this->testCpuIntensive();
        return response()->json([
            'test' => 'CPU Intensive Operations',
            'result' => $this->results['CPU Intensive Operations'] ?? null,
            'timestamp' => now()->toISOString()
        ]);
    }

    public function testDatabaseEndpoint(): JsonResponse
    {
        $result = $this->testDatabaseOperations();
        return response()->json([
            'test' => 'Database Operations',
            'result' => $this->results['Database Operations'] ?? null,
            'timestamp' => now()->toISOString()
        ]);
    }

    public function testCacheEndpoint(): JsonResponse
    {
        $result = $this->testCacheOperations();
        return response()->json([
            'test' => 'Cache Operations',
            'result' => $this->results['Cache Operations'] ?? null,
            'timestamp' => now()->toISOString()
        ]);
    }

    public function testEloquentEndpoint(): JsonResponse
    {
        $result = $this->testEloquentOperations();
        return response()->json([
            'test' => 'Eloquent Operations',
            'result' => $this->results['Eloquent Operations'] ?? null,
            'timestamp' => now()->toISOString()
        ]);
    }

    public function testLaravelFeaturesEndpoint(): JsonResponse
    {
        $result = $this->testLaravelFeatures();
        return response()->json([
            'test' => 'Laravel Features',
            'result' => $this->results['Laravel Features'] ?? null,
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Health check endpoint
     */
    public function healthCheck(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toISOString(),
            'system_info' => $this->getSystemInfo()
        ]);
    }

    private function getSystemInfo(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'opcache_enabled' => extension_loaded('Zend OPcache') && opcache_get_status() !== false,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'database_driver' => config('database.default'),
            'cache_driver' => config('cache.default'),
            'queue_driver' => config('queue.default'),
            'app_env' => config('app.env'),
            'app_debug' => config('app.debug'),
        ];
    }

    private function startMeasurement(string $testName): void
    {
        $this->startTime = microtime(true);
        $this->startMemory = memory_get_usage(true);
        gc_collect_cycles();
    }

    private function endMeasurement(string $testName, int $iterations = 1): void
    {
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);

        $executionTime = ($endTime - $this->startTime) * 1000;
        $memoryUsed = max(0, $endMemory - $this->startMemory);
        $avgTimePerIteration = $executionTime / $iterations;

        $this->results[$testName] = [
            'execution_time_ms' => round($executionTime, 4),
            'avg_time_per_iteration_ms' => round($avgTimePerIteration, 4),
            'memory_used_bytes' => $memoryUsed,
            'memory_used_mb' => round($memoryUsed / 1024 / 1024, 4),
            'peak_memory_mb' => round($peakMemory / 1024 / 1024, 4),
            'iterations' => $iterations
        ];
    }

    private function testCpuIntensive()
    {
        $this->startMeasurement('CPU Intensive Operations');

        $iterations = 50000; // Reduced for web context
        $result = 0;

        for ($i = 0; $i < $iterations; $i++) {
            $result += sqrt($i) * sin($i) * cos($i);
            $result += pow($i % 100, 2);
            $result += log($i + 1);
        }

        // Prime calculation
        $primes = [];
        for ($num = 2; $num <= 500; $num++) {
            $isPrime = true;
            for ($i = 2; $i <= sqrt($num); $i++) {
                if ($num % $i == 0) {
                    $isPrime = false;
                    break;
                }
            }
            if ($isPrime) {
                $primes[] = $num;
            }
        }

        $this->endMeasurement('CPU Intensive Operations', $iterations);
        return $result;
    }

    private function testMemoryIntensive()
    {
        $this->startMeasurement('Memory Intensive Operations');

        $iterations = 5000;
        $arrays = [];

        for ($i = 0; $i < $iterations; $i++) {
            $arrays[$i] = range(0, 50);
            $arrays[$i] = array_map(function ($x) use ($i) {
                return $x * $i * rand(1, 100);
            }, $arrays[$i]);
        }

        $merged = [];
        foreach ($arrays as $arr) {
            $merged = array_merge($merged, $arr);
        }
        sort($merged);

        $largeString = str_repeat('Lorem ipsum dolor sit amet. ', 5000);
        $parts = explode(' ', $largeString);
        $reversed = array_reverse($parts);
        $rejoined = implode(' ', $reversed);

        $this->endMeasurement('Memory Intensive Operations', $iterations);
        unset($arrays, $merged, $largeString, $parts, $reversed, $rejoined);
    }

    private function testStringOperations()
    {
        $this->startMeasurement('String Operations');

        $iterations = 25000;
        $baseString = "The quick brown fox jumps over the lazy dog";
        $results = [];

        for ($i = 0; $i < $iterations; $i++) {
            $concat = $baseString . " - " . $i;
            $upper = strtoupper($concat);
            $lower = strtolower($upper);
            $replaced = str_replace('fox', 'cat', $lower);

            preg_match_all('/\b\w{4,}\b/', $replaced, $matches);
            $hash = md5($replaced);

            if ($i % 5000 == 0) {
                $results[] = $hash;
            }
        }

        $this->endMeasurement('String Operations', $iterations);
        return count($results);
    }

    private function testArrayOperations()
    {
        $this->startMeasurement('Array Operations');

        $iterations = 5000;
        $data = range(1, 500);

        for ($i = 0; $i < $iterations; $i++) {
            $shuffled = $data;
            shuffle($shuffled);

            $sorted = $shuffled;
            sort($sorted);
            rsort($sorted);

            $filtered = array_filter($sorted, function ($x) {
                return $x % 2 == 0;
            });

            $mapped = array_map(function ($x) {
                return $x * 2;
            }, $filtered);

            $reduced = array_reduce($mapped, function ($carry, $item) {
                return $carry + $item;
            }, 0);

            $search = array_search(250, $sorted);
            $exists = in_array(375, $sorted);
        }

        $this->endMeasurement('Array Operations', $iterations);
    }

    private function testObjectOperations()
    {
        $this->startMeasurement('Object Operations');

        $iterations = 10000;
        $objects = [];

        for ($i = 0; $i < $iterations; $i++) {
            $obj = new \stdClass();
            $obj->id = $i;
            $obj->name = "Object_{$i}";
            $obj->value = rand(1, 1000);
            $obj->timestamp = time();
            $obj->data = [
                'type' => 'test',
                'category' => $i % 10,
                'active' => $i % 2 == 0
            ];

            $objects[] = $obj;
        }

        foreach ($objects as $obj) {
            $obj->processed = true;
            $obj->hash = md5($obj->name . $obj->value);
        }

        $serialized = serialize($objects);
        $unserialized = unserialize($serialized);

        $json = json_encode($objects);
        $decoded = json_decode($json);

        $this->endMeasurement('Object Operations', $iterations);
        unset($objects, $serialized, $unserialized, $json, $decoded);
    }

    private function testDatabaseOperations()
    {
        $this->startMeasurement('Database Operations');

        // Setup SQLite database and create table if needed
        $this->setupDatabase();

        $iterations = 100;

        // Raw queries - SQLite compatible
        for ($i = 0; $i < $iterations; $i++) {
            DB::select('SELECT ? as test_value, datetime("now") as current_time', [$i]);
        }

        // Multiple inserts with proper error handling
        try {
            $testData = [];
            for ($i = 0; $i < 50; $i++) {
                $testData[] = [
                    'name' => "BenchmarkUser_{$i}_" . uniqid(),
                    'email' => "benchmark_{$i}_" . uniqid() . "@example.com",
                    'password' => password_hash('password', PASSWORD_DEFAULT), // More portable than bcrypt()
                    'created_at' => date('Y-m-d H:i:s'), // SQLite compatible datetime
                    'updated_at' => date('Y-m-d H:i:s'),
                ];
            }

            // Insert in smaller batches for better SQLite performance
            $chunks = array_chunk($testData, 10);
            foreach ($chunks as $chunk) {
                DB::table('users')->insert($chunk);
            }

            // Query operations
            $users = DB::table('users')
                ->where('name', 'like', 'BenchmarkUser_%')
                ->orderBy('created_at', 'desc')
                ->limit(25)
                ->get();

            // Cleanup benchmark data
            $deletedCount = DB::table('users')
                ->where('name', 'like', 'BenchmarkUser_%')
                ->delete();

            Log::info("Database test completed. Inserted " . count($testData) . " users, retrieved " . count($users) . " users, deleted {$deletedCount} users.");
        } catch (\Exception $e) {
            Log::warning('Database operations test failed: ' . $e->getMessage());

            // Attempt cleanup even on failure
            try {
                DB::table('users')->where('name', 'like', 'BenchmarkUser_%')->delete();
                $this->cleanupTestDatabase();
            } catch (\Exception $cleanupException) {
                Log::warning('Cleanup failed: ' . $cleanupException->getMessage());
            }
        }

        $this->endMeasurement('Database Operations', $iterations);
    }

    private function setupDatabase()
    {
        try {
            // Check if users table exists
            $tableExists = DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name='users'");

            if (empty($tableExists)) {
                // Create users table with SQLite compatible schema
                DB::statement('
                CREATE TABLE users (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT NOT NULL,
                    email TEXT UNIQUE NOT NULL,
                    password TEXT NOT NULL,
                    created_at TEXT NOT NULL,
                    updated_at TEXT NOT NULL
                )
            ');

                // Create indexes for better performance
                DB::statement('CREATE INDEX idx_users_name ON users(name)');
                DB::statement('CREATE INDEX idx_users_email ON users(email)');
                DB::statement('CREATE INDEX idx_users_created_at ON users(created_at)');

                Log::info('Users table and indexes created successfully');
            }

            // Test basic connectivity
            $result = DB::select('SELECT sqlite_version() as version');
            Log::info('SQLite version: ' . $result[0]->version);
        } catch (\Exception $e) {
            Log::error('Database setup failed: ' . $e->getMessage());
            throw new \Exception('Cannot setup database for testing: ' . $e->getMessage());
        }
    }

    // Optional: Method to clean up test database
    private function cleanupTestDatabase()
    {
        try {
            // Remove any remaining benchmark data
            DB::table('users')->where('name', 'like', 'BenchmarkUser_%')->delete();

            // Vacuum to reclaim space (SQLite specific optimization)
            DB::statement('VACUUM');

            Log::info('Database cleanup completed');
        } catch (\Exception $e) {
            Log::warning('Database cleanup failed: ' . $e->getMessage());
        }
    }

    private function testCacheOperations()
    {
        $this->startMeasurement('Cache Operations');

        $iterations = 1000;

        // Cache set/get operations
        for ($i = 0; $i < $iterations; $i++) {
            $key = "benchmark_key_{$i}";
            $value = [
                'id' => $i,
                'data' => str_repeat("cache data {$i} ", 10),
                'timestamp' => now()->timestamp
            ];

            Cache::put($key, $value, 60);
            $retrieved = Cache::get($key);

            if ($i % 100 == 0) {
                Cache::forget($key);
            }
        }

        // Bulk operations
        $bulkData = [];
        for ($i = 0; $i < 100; $i++) {
            $bulkData["bulk_key_{$i}"] = "bulk_value_{$i}";
        }

        Cache::putMany($bulkData, 60);

        // Retrieve multiple
        $keys = array_keys($bulkData);
        $retrieved = Cache::many($keys);

        // Cleanup
        foreach ($keys as $key) {
            Cache::forget($key);
        }

        $this->endMeasurement('Cache Operations', $iterations);
    }

    private function testFileOperations()
    {
        $this->startMeasurement('File Operations');

        $iterations = 100;
        $files = [];

        // File operations using Laravel Storage
        for ($i = 0; $i < $iterations; $i++) {
            $filename = "benchmark/test_file_{$i}.txt";
            $content = str_repeat("Line {$i}: Laravel file operation test.\n", 50);

            Storage::put($filename, $content);
            $files[] = $filename;
        }

        // Read operations
        $totalSize = 0;
        foreach ($files as $file) {
            if (Storage::exists($file)) {
                $content = Storage::get($file);
                $totalSize += strlen($content);
                $size = Storage::size($file);
                $lastModified = Storage::lastModified($file);
            }
        }

        // Cleanup
        foreach ($files as $file) {
            Storage::delete($file);
        }

        $this->endMeasurement('File Operations', $iterations);
        return $totalSize;
    }

    private function testEloquentOperations()
    {
        $this->startMeasurement('Eloquent Operations');

        $iterations = 50;

        try {
            // Create test users
            $users = [];
            for ($i = 0; $i < $iterations; $i++) {
                $user = User::create([
                    'name' => "EloquentBench_{$i}_" . uniqid(),
                    'email' => "eloquent_{$i}_" . uniqid() . "@example.com",
                    'password' => bcrypt('password'),
                ]);
                $users[] = $user->id;
            }

            // Query operations
            $foundUsers = User::whereIn('id', $users)
                ->where('name', 'like', 'EloquentBench_%')
                ->orderBy('created_at', 'desc')
                ->get();

            // Update operations
            User::whereIn('id', $users)
                ->update(['updated_at' => now()]);

            // Individual operations
            foreach ($foundUsers->take(10) as $user) {
                $user->name = $user->name . '_updated';
                $user->save();
            }

            // Cleanup
            User::whereIn('id', $users)->delete();
        } catch (\Exception $e) {
            Log::warning('Eloquent operations test failed: ' . $e->getMessage());
        }

        $this->endMeasurement('Eloquent Operations', $iterations);
    }

    private function testLaravelFeatures()
    {
        $this->startMeasurement('Laravel Features');

        $iterations = 200;

        // Collection operations
        $collection = collect(range(1, 1000));

        for ($i = 0; $i < $iterations; $i++) {
            $filtered = $collection
                ->filter(function ($value) use ($i) {
                    return $value % ($i % 10 + 1) == 0;
                })
                ->map(function ($value) use ($i) {
                    return $value * ($i + 1);
                })
                ->sortDesc()
                ->take(10)
                ->values();

            // String helpers
            $slug = Str::slug("Laravel Benchmark Test {$i}");
            $uuid = Str::uuid();
            $random = Str::random(20);

            // Carbon operations
            $now = Carbon::now();
            $tomorrow = $now->copy()->addDay();
            $diff = $tomorrow->diffInHours($now);

            // Helper functions
            $config = config('app.name');
            $env = env('APP_ENV', 'production');
        }

        // Event/Log simulation
        for ($i = 0; $i < 10; $i++) {
            Log::info("Benchmark test log entry {$i}", [
                'iteration' => $i,
                'timestamp' => now()->toISOString()
            ]);
        }

        $this->endMeasurement('Laravel Features', $iterations);
    }

    private function calculateSummary(): array
    {
        $totalTime = 0;
        $totalMemory = 0;
        $maxPeak = 0;

        foreach ($this->results as $result) {
            $totalTime += $result['execution_time_ms'];
            $totalMemory += $result['memory_used_mb'];
            $maxPeak = max($maxPeak, $result['peak_memory_mb']);
        }

        return [
            'total_execution_time_ms' => round($totalTime, 4),
            'total_memory_used_mb' => round($totalMemory, 4),
            'peak_memory_mb' => round($maxPeak, 4),
            'test_count' => count($this->results)
        ];
    }

    private function storeResults(array $results): void
    {
        try {
            $filename = 'benchmarks/laravel_benchmark_' . date('Y-m-d_H-i-s') . '.json';
            Storage::disk('public')->put($filename, json_encode($results, JSON_PRETTY_PRINT));
        } catch (\Exception $e) {
            Log::error('Failed to store benchmark results: ' . $e->getMessage());
        }
    }
}
