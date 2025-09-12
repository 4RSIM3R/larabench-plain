# Laravel Benchmark Study Summary - Plain (No OPcache)

**Test Configuration:** `plain-no-opcache`  
**Test Date:** September 12, 2025  
**Test Duration:** 6 minutes 34.9 seconds  
**Test Started:** 10:40:37 +07:00  
**Test Completed:** 10:47:12 +07:00

## Executive Summary

This benchmark evaluates Laravel application performance without OPcache optimization. The test suite executed **172 iterations** across 8 different scenarios, achieving a **100% success rate** with 315 successful checks and 0 failures.

## Test Scenarios Overview

The benchmark included the following scenarios:

1. **Health Check** - Basic application health verification
2. **CPU Intensive** - Computational workload testing
3. **Cache Operations** - Laravel caching system performance
4. **Database Operations** - Database query performance
5. **Eloquent Operations** - ORM performance testing
6. **Laravel Features** - Framework features testing
7. **Full Benchmark** - Comprehensive application testing
8. **Stress Test** - High-load performance evaluation

## Key Performance Metrics

### Overall Performance

-   **Total Requests:** 173
-   **Success Rate:** 100% (0% failures)
-   **Request Rate:** 0.44 requests/second
-   **Average Response Time:** 2.51 seconds
-   **95th Percentile Response Time:** 11.45 seconds

### Response Time Analysis

-   **Minimum:** 41.42ms
-   **Median:** 70.33ms
-   **Maximum:** 21.74 seconds
-   **90th Percentile:** 9.87 seconds
-   **95th Percentile:** 11.45 seconds

### Scenario-Specific Performance

#### 1. Health Check Scenario

-   **Average Response Time:** 52.37ms
-   **95th Percentile:** 52.37ms
-   **Status:** ✅ All thresholds met (p95 < 5000ms)
-   **Performance:** Excellent - Consistent sub-60ms response times

#### 2. CPU Intensive Scenario

-   **Average Response Time:** 49ms
-   **95th Percentile:** 60ms
-   **Range:** 41.42ms - 108.26ms
-   **Status:** ✅ All thresholds met (p95 < 45000ms)
-   **Performance:** Excellent - Low computational overhead

#### 3. Cache Operations Scenario

-   **Average Response Time:** 1.25 seconds
-   **95th Percentile:** 1.44 seconds
-   **Range:** 1.14s - 1.49s
-   **Status:** ✅ All thresholds met (p95 < 15000ms)
-   **Performance:** Good - Consistent caching performance

#### 4. Database Operations Scenario

-   **Average Response Time:** 11.06 seconds
-   **95th Percentile:** 11.71 seconds
-   **Range:** 10.51s - 11.87s
-   **Status:** ✅ All thresholds met (p95 < 30000ms)
-   **Performance:** Moderate - Database operations are the bottleneck

## Network Performance

-   **Data Received:** 95 KB (240 B/s)
-   **Data Sent:** 27 KB (68 B/s)
-   **Total Throughput:** 308 B/s

## Resource Utilization

-   **Virtual Users (VUs):** 1 active, maximum 6 configured
-   **Concurrent Load:** Low to moderate load testing
-   **Test Execution:** Sequential scenario execution

## Threshold Compliance

All performance thresholds were successfully met:

-   ✅ **Overall Response Time:** p(95) = 11.45s < 60s threshold
-   ✅ **Health Check:** p(95) = 52.37ms < 5s threshold
-   ✅ **CPU Intensive:** p(95) = 60ms < 45s threshold
-   ✅ **Cache Operations:** p(95) = 1.44s < 15s threshold
-   ✅ **Database Operations:** p(95) = 11.71s < 30s threshold
-   ✅ **Error Rate:** 0% < 30% threshold

## Performance Insights

### Strengths

1. **Reliability:** 100% success rate with zero failed requests
2. **Health Checks:** Excellent sub-60ms response times
3. **CPU Performance:** Minimal computational overhead (~49ms average)
4. **Threshold Compliance:** All performance targets achieved

### Areas for Optimization

1. **Database Operations:** Highest latency at ~11 seconds average
2. **Cache Performance:** 1.25s average suggests room for improvement
3. **Overall Throughput:** Low request rate (0.44 req/s) indicates scalability concerns

### Without OPcache Impact

Running without OPcache shows:

-   Slower overall response times due to lack of bytecode caching
-   Higher CPU usage for PHP compilation on each request
-   Increased memory usage for repeated code parsing
-   Database operations remain the primary bottleneck regardless of OPcache

## Recommendations for Study

### Performance Analysis Focus Areas

1. **Database Optimization:** Investigate slow database queries and indexing
2. **Caching Strategy:** Review cache hit/miss ratios and cache warming
3. **OPcache Comparison:** Compare with OPcache-enabled results for optimization impact
4. **Scaling Considerations:** Evaluate horizontal scaling requirements

### Further Investigation

1. **Query Analysis:** Profile database queries causing 11s+ response times
2. **Memory Usage:** Monitor PHP memory consumption patterns
3. **Concurrent Load:** Test with higher VU counts to identify breaking points
4. **Code Profiling:** Use Xdebug or similar tools for detailed code analysis

## Test Environment Notes

-   **Configuration:** Plain Laravel without OPcache optimization
-   **Test Tool:** k6 performance testing framework
-   **Monitoring:** Comprehensive metrics collection including HTTP, network, and execution data
-   **Validation:** 315 assertion checks all passed successfully

---

_This summary provides a comprehensive overview of Laravel application performance without OPcache. Use this data to compare against OPcache-enabled configurations and identify optimization opportunities._
