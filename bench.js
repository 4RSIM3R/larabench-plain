import http from 'k6/http';
import { check, sleep } from 'k6';
import { Counter, Rate, Trend } from 'k6/metrics';
import { SharedArray } from 'k6/data';
import exec from 'k6/execution';

// Custom metrics
const httpRequestDuration = new Trend('http_request_duration', true);
const httpRequestFailed = new Rate('http_request_failed');
const benchmarkErrors = new Counter('benchmark_errors');

// Results storage
let benchmarkResults = {
  timestamp: new Date().toISOString(),
  config: __ENV.BENCHMARK_CONFIG || 'unknown',
  scenarios: {},
  summary: {}
};

// Test configuration
export const options = {
  scenarios: {
    // Health check scenario - runs first
    health_check: {
      executor: 'shared-iterations',
      vus: 1,
      iterations: 1,
      maxDuration: '30s',
      tags: { scenario: 'health_check' },
      exec: 'healthCheck',
    },
    
    // Individual endpoint tests - reduced concurrency for testing
    cpu_intensive: {
      executor: 'constant-vus',
      vus: 1,
      duration: '1m',
      startTime: '30s',
      tags: { scenario: 'cpu_intensive' },
      exec: 'testCpuIntensive',
    },
    
    database_operations: {
      executor: 'constant-vus',
      vus: 1,
      duration: '1m',
      startTime: '30s',
      tags: { scenario: 'database_operations' },
      exec: 'testDatabase',
    },
    
    cache_operations: {
      executor: 'constant-vus',
      vus: 1,
      duration: '1m',
      startTime: '30s',
      tags: { scenario: 'cache_operations' },
      exec: 'testCache',
    },
    
    eloquent_operations: {
      executor: 'constant-vus',
      vus: 1,
      duration: '1m',
      startTime: '30s',
      tags: { scenario: 'eloquent_operations' },
      exec: 'testEloquent',
    },
    
    laravel_features: {
      executor: 'constant-vus',
      vus: 1,
      duration: '1m',
      startTime: '30s',
      tags: { scenario: 'laravel_features' },
      exec: 'testLaravelFeatures',
    },
    
    // Full benchmark test - single user for testing
    full_benchmark: {
      executor: 'constant-vus',
      vus: 1,
      duration: '2m',
      startTime: '2m',
      tags: { scenario: 'full_benchmark' },
      exec: 'testFullBenchmark',
    },
    
    // Light stress test - minimal load for testing
    stress_test: {
      executor: 'ramping-vus',
      startVUs: 1,
      stages: [
        { duration: '30s', target: 1 },
        { duration: '1m', target: 2 },
        { duration: '30s', target: 1 },
        { duration: '30s', target: 0 },
      ],
      startTime: '4m',
      tags: { scenario: 'stress_test' },
      exec: 'testMixedLoad',
    }
  },
  
  thresholds: {
    http_req_duration: ['p(95)<60000'], // 95% of requests under 60s - more lenient for testing
    http_req_failed: ['rate<0.3'], // Less than 30% failures - more lenient for testing
    'http_req_duration{scenario:health_check}': ['p(95)<5000'], // Health check under 5s
    'http_req_duration{scenario:cpu_intensive}': ['p(95)<45000'], // CPU test under 45s
    'http_req_duration{scenario:database_operations}': ['p(95)<30000'], // DB test under 30s
    'http_req_duration{scenario:cache_operations}': ['p(95)<15000'], // Cache test under 15s
  }
};

// Base URL - adjust this to your Laravel app URL
const BASE_URL = __ENV.BASE_URL || 'http://localhost:80';
const API_BASE = `${BASE_URL}/api/benchmark`;

// Request headers
const headers = {
  'Content-Type': 'application/json',
  'Accept': 'application/json',
  'User-Agent': 'K6-Laravel-Benchmark/1.0'
};

// Helper function to record scenario results
function recordScenarioResult(scenarioName, response, success, startTime) {
  if (!benchmarkResults.scenarios[scenarioName]) {
    benchmarkResults.scenarios[scenarioName] = {
      requests: 0,
      successes: 0,
      failures: 0,
      totalDuration: 0,
      avgDuration: 0,
      minDuration: Infinity,
      maxDuration: 0,
      errors: []
    };
  }
  
  const scenario = benchmarkResults.scenarios[scenarioName];
  scenario.requests++;
  
  if (success) {
    scenario.successes++;
  } else {
    scenario.failures++;
    // Record error details
    scenario.errors.push({
      status: response.status,
      url: response.url,
      error: response.error || 'Unknown error',
      timestamp: new Date().toISOString()
    });
  }
  
  const duration = response.timings ? response.timings.duration : 0;
  scenario.totalDuration += duration;
  scenario.avgDuration = scenario.totalDuration / scenario.requests;
  scenario.minDuration = Math.min(scenario.minDuration, duration);
  scenario.maxDuration = Math.max(scenario.maxDuration, duration);
  
  // Clean up infinite values for JSON serialization
  if (scenario.minDuration === Infinity) {
    scenario.minDuration = 0;
  }
}

// Helper function to save results to console (K6 doesn't support file writing directly)
function saveResults() {
  benchmarkResults.summary = {
    totalRequests: Object.values(benchmarkResults.scenarios).reduce((sum, s) => sum + s.requests, 0),
    totalSuccesses: Object.values(benchmarkResults.scenarios).reduce((sum, s) => sum + s.successes, 0),
    totalFailures: Object.values(benchmarkResults.scenarios).reduce((sum, s) => sum + s.failures, 0),
    totalErrors: benchmarkErrors.count,
    overallSuccessRate: 0
  };
  
  if (benchmarkResults.summary.totalRequests > 0) {
    benchmarkResults.summary.overallSuccessRate = 
      (benchmarkResults.summary.totalSuccesses / benchmarkResults.summary.totalRequests * 100).toFixed(2);
  }
  
  // Output results in JSON format that can be captured
  // Using a unique marker that won't be confused with log formatting
  console.log('BENCHMARK_JSON_START');
  console.log(JSON.stringify(benchmarkResults));
  console.log('BENCHMARK_JSON_END');
}

// Health check scenario
export function healthCheck() {
  console.log('🏥 Running health check...');
  const startTime = Date.now();
  
  const response = http.get(`${API_BASE}/health`, { headers, timeout: '30s' });
  
  const success = check(response, {
    'health check status is 200': (r) => r.status === 200,
    'health check response time < 5s': (r) => r.timings && r.timings.duration < 5000,
    'health check has status ok': (r) => {
      try {
        const body = JSON.parse(r.body);
        return body.status === 'ok';
      } catch (e) {
        console.error('Health check JSON parse error:', e.message);
        return false;
      }
    }
  });
  
  if (!success) {
    benchmarkErrors.add(1);
    console.error('❌ Health check failed - Status:', response.status);
  } else {
    console.log('✅ Health check passed');
  }
  
  httpRequestDuration.add(response.timings ? response.timings.duration : 0);
  httpRequestFailed.add(response.status !== 200);
  recordScenarioResult('health_check', response, success, startTime);
  
  sleep(1);
}

// Individual test scenarios
export function testCpuIntensive() {
  const startTime = Date.now();
  const response = http.get(`${API_BASE}/cpu`, { headers, timeout: '60s' });
  
  const success = check(response, {
    'CPU test status is 200': (r) => r.status === 200,
    'CPU test has valid response': (r) => {
      try {
        const body = JSON.parse(r.body);
        return body.test === 'CPU Intensive Operations' && body.result !== null;
      } catch (e) {
        return false;
      }
    }
  });
  
  if (!success) {
    benchmarkErrors.add(1);
    console.error(`❌ CPU test failed - Status: ${response.status}`);
  }
  
  httpRequestDuration.add(response.timings ? response.timings.duration : 0);
  httpRequestFailed.add(response.status !== 200);
  recordScenarioResult('cpu_intensive', response, success, startTime);
  
  sleep(2);
}

export function testDatabase() {
  const startTime = Date.now();
  const response = http.get(`${API_BASE}/database`, { headers, timeout: '60s' });
  
  const success = check(response, {
    'Database test status is 200': (r) => r.status === 200,
    'Database test has valid response': (r) => {
      try {
        const body = JSON.parse(r.body);
        return body.test === 'Database Operations' && body.result !== null;
      } catch (e) {
        return false;
      }
    }
  });
  
  if (!success) {
    benchmarkErrors.add(1);
    console.error(`❌ Database test failed - Status: ${response.status}`);
  }
  
  httpRequestDuration.add(response.timings ? response.timings.duration : 0);
  httpRequestFailed.add(response.status !== 200);
  recordScenarioResult('database_operations', response, success, startTime);
  
  sleep(1);
}

export function testCache() {
  const startTime = Date.now();
  const response = http.get(`${API_BASE}/cache`, { headers, timeout: '45s' });
  
  const success = check(response, {
    'Cache test status is 200': (r) => r.status === 200,
    'Cache test has valid response': (r) => {
      try {
        const body = JSON.parse(r.body);
        return body.test === 'Cache Operations' && body.result !== null;
      } catch (e) {
        return false;
      }
    }
  });
  
  if (!success) {
    benchmarkErrors.add(1);
    console.error(`❌ Cache test failed - Status: ${response.status}`);
  }
  
  httpRequestDuration.add(response.timings ? response.timings.duration : 0);
  httpRequestFailed.add(response.status !== 200);
  recordScenarioResult('cache_operations', response, success, startTime);
  
  sleep(0.5);
}

export function testEloquent() {
  const startTime = Date.now();
  const response = http.get(`${API_BASE}/eloquent`, { headers, timeout: '60s' });
  
  const success = check(response, {
    'Eloquent test status is 200': (r) => r.status === 200,
    'Eloquent test has valid response': (r) => {
      try {
        const body = JSON.parse(r.body);
        return body.test === 'Eloquent Operations' && body.result !== null;
      } catch (e) {
        return false;
      }
    }
  });
  
  if (!success) {
    benchmarkErrors.add(1);
    console.error(`❌ Eloquent test failed - Status: ${response.status}`);
  }
  
  httpRequestDuration.add(response.timings ? response.timings.duration : 0);
  httpRequestFailed.add(response.status !== 200);
  recordScenarioResult('eloquent_operations', response, success, startTime);
  
  sleep(2);
}

export function testLaravelFeatures() {
  const startTime = Date.now();
  const response = http.get(`${API_BASE}/features`, { headers, timeout: '45s' });
  
  const success = check(response, {
    'Laravel features test status is 200': (r) => r.status === 200,
    'Laravel features test has valid response': (r) => {
      try {
        const body = JSON.parse(r.body);
        return body.test === 'Laravel Features' && body.result !== null;
      } catch (e) {
        return false;
      }
    }
  });
  
  if (!success) {
    benchmarkErrors.add(1);
    console.error(`❌ Laravel features test failed - Status: ${response.status}`);
  }
  
  httpRequestDuration.add(response.timings ? response.timings.duration : 0);
  httpRequestFailed.add(response.status !== 200);
  recordScenarioResult('laravel_features', response, success, startTime);
  
  sleep(1);
}

// Full benchmark test
export function testFullBenchmark() {
  console.log(`🚀 Running full benchmark test (VU: ${__VU}, Iter: ${__ITER})`);
  const startTime = Date.now();
  
  const response = http.get(`${API_BASE}/all`, { headers, timeout: '180s' });
  
  const success = check(response, {
    'Full benchmark status is 200': (r) => r.status === 200,
    'Full benchmark has valid response': (r) => {
      try {
        const body = JSON.parse(r.body);
        return body.system_info && body.results && body.summary;
      } catch (e) {
        return false;
      }
    },
    'Full benchmark completed all tests': (r) => {
      try {
        const body = JSON.parse(r.body);
        const expectedTests = [
          'CPU Intensive Operations',
          'Memory Intensive Operations', 
          'String Operations',
          'Array Operations',
          'Object Operations',
          'Database Operations',
          'Cache Operations',
          'File Operations',
          'Eloquent Operations',
          'Laravel Features'
        ];
        return expectedTests.every(test => body.results[test] !== undefined);
      } catch (e) {
        return false;
      }
    }
  });
  
  if (success) {
    console.log(`✅ Full benchmark completed successfully (VU: ${__VU})`);
    
    // Log some key metrics from the response
    try {
      const body = JSON.parse(response.body);
      console.log(`📊 Total execution time: ${body.summary.total_execution_time_ms}ms`);
      console.log(`💾 Peak memory usage: ${body.summary.peak_memory_mb}MB`);
      console.log(`🧪 Tests completed: ${body.summary.test_count}`);
    } catch (e) {
      console.log('Could not parse response metrics');
    }
  } else {
    console.error(`❌ Full benchmark failed (VU: ${__VU})`);
    benchmarkErrors.add(1);
  }
  
  httpRequestDuration.add(response.timings ? response.timings.duration : 0);
  httpRequestFailed.add(response.status !== 200);
  recordScenarioResult('full_benchmark', response, success, startTime);
  
  sleep(5); // Longer pause between full benchmark runs
}

// Mixed load test for stress testing
export function testMixedLoad() {
  const startTime = Date.now();
  const endpoints = [
    { path: '/cache', name: 'cache' },
    { path: '/features', name: 'features' },
    { path: '/database', name: 'database' }
  ];
  
  const randomEndpoint = endpoints[Math.floor(Math.random() * endpoints.length)];
  const response = http.get(`${API_BASE}${randomEndpoint.path}`, { 
    headers, 
    timeout: '60s',
    tags: { endpoint: randomEndpoint.name }
  });
  
  const success = check(response, {
    [`${randomEndpoint.name} stress test status is 200`]: (r) => r.status === 200,
  });
  
  if (!success) {
    benchmarkErrors.add(1);
    console.error(`❌ Stress test failed - Status: ${response.status}`);
  }
  
  httpRequestDuration.add(response.timings ? response.timings.duration : 0);
  httpRequestFailed.add(response.status !== 200);
  recordScenarioResult('stress_test', response, success, startTime);
  
  sleep(Math.random() * 2); // Random delay between 0-2 seconds
}

// Setup function - runs once before all scenarios
export function setup() {
  console.log('🔧 Setting up K6 Laravel benchmark tests...');
  console.log(`🎯 Target URL: ${BASE_URL}`);
  
  // Test basic connectivity
  const response = http.get(`${API_BASE}/health`, { headers, timeout: '30s' });
  if (response.status !== 200) {
    throw new Error(`Setup failed: Cannot connect to ${BASE_URL}. Status: ${response.status}`);
  }
  
  console.log('✅ Setup completed successfully');
  return { baseUrl: BASE_URL };
}

// Teardown function - runs once after all scenarios
export function teardown(data) {
  console.log('🧹 Running teardown...');
  console.log(`📈 Total errors: ${benchmarkErrors.count}`);
  
  // Log final scenario counts for debugging
  console.log('📊 Final scenario results:');
  for (const [name, scenario] of Object.entries(benchmarkResults.scenarios)) {
    console.log(`  ${name}: ${scenario.requests} requests, ${scenario.successes} successes, ${scenario.failures} failures`);
  }
  
  // Save benchmark results
  saveResults();
  
  console.log('🧹 Teardown completed');
}