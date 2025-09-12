# Laravel Benchmark Suite

This project contains a comprehensive benchmarking suite for Laravel applications with different configurations to test the performance impact of OPcache and code obfuscation.

## Overview

The benchmark suite includes:

1. **Modified K6 benchmark script** (`bench.js`) - Enhanced to capture and save detailed results
2. **4 Docker configurations**:
    - Plain Laravel without OPcache
    - Plain Laravel with OPcache
    - Obfuscated Laravel (yakpro-po) without OPcache
    - Obfuscated Laravel (yakpro-po) with OPcache
3. **Automated benchmark runner** (`run-benchmarks.sh`)

## Files Created/Modified

### Modified Files

-   `bench.js` - Enhanced K6 benchmark script with result collection

### New Docker Files

-   `Dockerfile.plain-no-opcache` - Plain Laravel without OPcache
-   `Dockerfile.plain-opcache` - Plain Laravel with OPcache enabled
-   `Dockerfile.obfuscate-no-opcache` - Obfuscated Laravel without OPcache
-   `Dockerfile.obfuscate-opcache` - Obfuscated Laravel with OPcache

### Utility Files

-   `run-benchmarks.sh` - Automated benchmark runner script
-   `BENCHMARK_README.md` - This documentation file

## Prerequisites

1. **Docker** - For building and running the different Laravel configurations
2. **K6** - For running the performance tests

    ```bash
    # Install K6 (macOS)
    brew install k6

    # Install K6 (Linux)
    sudo apt-key adv --keyserver hkp://keyserver.ubuntu.com:80 --recv-keys C5AD17C747E3415A3642D57D77C6C491D6AC1D69
    echo "deb https://dl.k6.io/deb stable main" | sudo tee /etc/apt/sources.list.d/k6.list
    sudo apt-get update
    sudo apt-get install k6
    ```

## Quick Start

### Automated Benchmark (Recommended)

Run all benchmarks automatically:

```bash
# Make the script executable
chmod +x run-benchmarks.sh

# Run all benchmarks
./run-benchmarks.sh
```

This will:

1. Build all 4 Docker images
2. Run benchmarks for each configuration
3. Save results in the `benchmark-results/` directory
4. Generate a summary report

### Manual Benchmark

If you prefer to run benchmarks manually:

#### 1. Build Docker Images

```bash
# Plain Laravel without OPcache
docker build -f Dockerfile.plain-no-opcache -t larabench-plain-no-opcache .

# Plain Laravel with OPcache
docker build -f Dockerfile.plain-opcache -t larabench-plain-opcache .

# Obfuscated Laravel without OPcache
docker build -f Dockerfile.obfuscate-no-opcache -t larabench-obfuscate-no-opcache .

# Obfuscated Laravel with OPcache
docker build -f Dockerfile.obfuscate-opcache -t larabench-obfuscate-opcache .
```

#### 2. Run Individual Containers

```bash
# Example: Run plain Laravel without OPcache
docker run -d --name larabench-test -p 8080:80 \
  -e "BENCHMARK_CONFIG=plain-no-opcache" \
  larabench-plain-no-opcache

# Wait for container to be ready, then run benchmark
k6 run --env "BASE_URL=http://localhost:8080" \
  --env "BENCHMARK_CONFIG=plain-no-opcache" \
  bench.js

# Clean up
docker stop larabench-test && docker rm larabench-test
```

## Benchmark Results

### Result Collection

The enhanced `bench.js` script collects detailed metrics including:

-   **Per-scenario statistics**: Request counts, success/failure rates, response times
-   **Overall summary**: Total requests, success rates, error counts
-   **Timing data**: Min/max/average response times per scenario
-   **Configuration identification**: Which Docker configuration was tested

### Result Files

Results are saved in the `benchmark-results/` directory:

-   `k6-{config}-{timestamp}.log` - Full K6 output logs
-   `benchmark-{config}-{timestamp}.json` - Raw K6 metrics in JSON format
-   `results-{config}-{timestamp}.json` - Structured benchmark results
-   `benchmark-summary-{timestamp}.md` - Summary report

### Result Format

The structured results include:

```json
{
    "timestamp": "2024-01-01T12:00:00.000Z",
    "config": "plain-opcache",
    "scenarios": {
        "health_check": {
            "requests": 1,
            "successes": 1,
            "failures": 0,
            "avgDuration": 150.5,
            "minDuration": 150.5,
            "maxDuration": 150.5
        },
        "cpu_intensive": {
            "requests": 60,
            "successes": 58,
            "failures": 2,
            "avgDuration": 2500.3,
            "minDuration": 1200.1,
            "maxDuration": 4500.8
        }
    },
    "summary": {
        "totalRequests": 250,
        "totalSuccesses": 240,
        "totalFailures": 10,
        "totalErrors": 5,
        "overallSuccessRate": "96.00"
    }
}
```

## Docker Configurations

### 1. Plain Laravel (No OPcache)

-   **File**: `Dockerfile.plain-no-opcache`
-   **Features**: Standard Laravel, OPcache disabled
-   **Use case**: Baseline performance measurement

### 2. Plain Laravel (OPcache)

-   **File**: `Dockerfile.plain-opcache`
-   **Features**: Standard Laravel, OPcache optimized
-   **OPcache settings**: 256MB memory, 20K files, validation disabled
-   **Use case**: Measure OPcache performance impact

### 3. Obfuscated Laravel (No OPcache)

-   **File**: `Dockerfile.obfuscate-no-opcache`
-   **Features**: yakpro-po obfuscation, OPcache disabled
-   **Obfuscation**: Only `app/` directory obfuscated
-   **Use case**: Measure obfuscation performance impact

### 4. Obfuscated Laravel (OPcache)

-   **File**: `Dockerfile.obfuscate-opcache`
-   **Features**: yakpro-po obfuscation + OPcache
-   **Use case**: Measure combined impact of obfuscation and OPcache

## Obfuscation Details

### yakpro-po Configuration

The obfuscation process:

-   **Target**: Only the `app/` directory
-   **Preserved**: Laravel framework files, vendor dependencies, configs
-   **Obfuscated**: Class names, function names, variables, properties, methods
-   **Settings**: 6-character scrambled identifiers, comments stripped

### What Gets Obfuscated

-   ✅ Application classes and methods in `app/`
-   ✅ Variable names and properties
-   ✅ Custom function names
-   ❌ Laravel framework code
-   ❌ Vendor packages
-   ❌ Configuration files
-   ❌ Routes and views

## Performance Analysis

### Expected Results

1. **OPcache Impact**: Should show significant performance improvement
2. **Obfuscation Impact**: May show slight performance degradation due to longer identifiers
3. **Combined Effect**: OPcache should offset most obfuscation overhead

### Metrics to Compare

-   **Response Times**: Average, min, max per scenario
-   **Throughput**: Requests per second
-   **Success Rates**: Percentage of successful requests
-   **Memory Usage**: Peak memory consumption (from Laravel app metrics)
-   **CPU Usage**: From system monitoring during tests

## Troubleshooting

### Common Issues

1. **Port conflicts**: Modify `PORT_BASE` in `run-benchmarks.sh`
2. **Docker build failures**: Check Docker daemon and image dependencies
3. **K6 not found**: Install K6 following the prerequisites
4. **Container startup timeout**: Increase `max_attempts` in the script
5. **Permission errors**: Ensure script is executable (`chmod +x`)

### Debug Commands

```bash
# Check container logs
docker logs larabench-container-{config}

# Test container health manually
curl http://localhost:8080/api/benchmark/health

# Run single K6 test with verbose output
k6 run --verbose bench.js
```

## Extending the Benchmark

### Adding New Scenarios

Modify `bench.js` to add new test scenarios:

```javascript
// Add to options.scenarios
new_test: {
  executor: 'constant-vus',
  vus: 1,
  duration: '1m',
  startTime: '30s',
  tags: { scenario: 'new_test' },
  exec: 'testNewFeature',
},

// Add new test function
export function testNewFeature() {
  const startTime = Date.now();
  const response = http.get(`${API_BASE}/new-endpoint`, { headers, timeout: '60s' });

  const success = check(response, {
    'New test status is 200': (r) => r.status === 200,
  });

  if (!success) benchmarkErrors.add(1);

  httpRequestDuration.add(response.timings.duration);
  httpRequestFailed.add(response.status !== 200);
  recordScenarioResult('new_test', response, success, startTime);

  sleep(1);
}
```

### Custom Docker Configurations

Create new Dockerfile variants:

```dockerfile
# Example: Dockerfile.custom-config
FROM php:8.2-fpm-alpine
# ... your custom configuration
ENV BENCHMARK_CONFIG="custom-config"
```

Add to `run-benchmarks.sh`:

```bash
build_image "custom-config" "Dockerfile.custom-config"
run_benchmark "custom-config" $((PORT_BASE + 5))
```

## License

This benchmark suite extends the original Laravel project. Please refer to the main project license for usage terms.
