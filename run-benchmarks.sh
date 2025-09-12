#!/bin/bash

# Laravel Benchmark Runner Script
# This script builds and runs benchmarks for different Laravel configurations

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
RESULTS_DIR="./benchmark-results"
DOCKER_NETWORK="larabench-network"
PORT_BASE=8080

# Create results directory
mkdir -p "$RESULTS_DIR"

# Create Docker network if it doesn't exist
if ! docker network ls | grep -q "$DOCKER_NETWORK"; then
    echo -e "${BLUE}Creating Docker network: $DOCKER_NETWORK${NC}"
    docker network create "$DOCKER_NETWORK"
fi

# Function to print colored output
print_header() {
    echo -e "\n${BLUE}================================================${NC}"
    echo -e "${BLUE}$1${NC}"
    echo -e "${BLUE}================================================${NC}\n"
}

print_success() {
    echo -e "${GREEN}✅ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

print_error() {
    echo -e "${RED}❌ $1${NC}"
}

# Function to build Docker image
build_image() {
    local config=$1
    local dockerfile=$2
    local image_name="larabench-$config"
    
    print_header "Building Docker image: $image_name"
    
    if docker build -f "$dockerfile" -t "$image_name" .; then
        print_success "Successfully built $image_name"
    else
        print_error "Failed to build $image_name"
        exit 1
    fi
}

# Function to run container and benchmark
run_benchmark() {
    local config=$1
    local port=$2
    local image_name="larabench-$config"
    local container_name="larabench-container-$config"
    
    print_header "Running benchmark for: $config"
    
    # Stop and remove existing container if it exists
    if docker ps -a | grep -q "$container_name"; then
        echo "Stopping and removing existing container: $container_name"
        docker stop "$container_name" >/dev/null 2>&1 || true
        docker rm "$container_name" >/dev/null 2>&1 || true
    fi
    
    # Start container
    echo "Starting container: $container_name on port $port"
    docker run -d \
        --name "$container_name" \
        --network "$DOCKER_NETWORK" \
        -p "$port:80" \
        -e "BENCHMARK_CONFIG=$config" \
        "$image_name"
    
    # Wait for container to be ready
    echo "Waiting for container to be ready..."
    local max_attempts=60
    local attempt=0
    
    while [ $attempt -lt $max_attempts ]; do
        if curl -s "http://localhost:$port/api/benchmark/health" >/dev/null 2>&1; then
            print_success "Container is ready!"
            break
        fi
        
        attempt=$((attempt + 1))
        echo "Attempt $attempt/$max_attempts - waiting for container..."
        sleep 2
    done
    
    if [ $attempt -eq $max_attempts ]; then
        print_error "Container failed to start properly"
        docker logs "$container_name"
        return 1
    fi
    
    # Run K6 benchmark
    echo "Running K6 benchmark..."
    local results_file="$RESULTS_DIR/benchmark-$config-$(date +%Y%m%d-%H%M%S).json"
    local k6_output="$RESULTS_DIR/k6-$config-$(date +%Y%m%d-%H%M%S).log"
    
    # Run K6 and capture both stdout and the results
    if k6 run \
        --env "BASE_URL=http://localhost:$port" \
        --env "BENCHMARK_CONFIG=$config" \
        --out json="$results_file" \
        bench.js 2>&1 | tee "$k6_output"; then
        
        print_success "Benchmark completed for $config"
        
        # Extract results from log and save separately
        local extracted_results="$RESULTS_DIR/results-$config-$(date +%Y%m%d-%H%M%S).json"
        
        # Extract JSON between the markers, handling log formatting
        if awk '/BENCHMARK_JSON_START/{flag=1; next} /BENCHMARK_JSON_END/{flag=0} flag' "$k6_output" | \
           sed 's/^time="[^"]*" level=info msg="//; s/" source=console$//' | \
           sed 's/\\"/"/g; s/\\n/\n/g' > "$extracted_results" 2>/dev/null && [ -s "$extracted_results" ]; then
            
            # Validate JSON format
            if python3 -m json.tool "$extracted_results" >/dev/null 2>&1; then
                print_success "Results extracted to: $extracted_results"
            else
                print_warning "Extracted results are not valid JSON, attempting to fix..."
                # Try to extract just the JSON part without log formatting
                grep -o '{.*}' "$k6_output" | tail -1 > "$extracted_results" 2>/dev/null || \
                echo '{"error": "Could not extract valid JSON results"}' > "$extracted_results"
            fi
        else
            print_warning "Could not extract structured results from output"
            echo '{"error": "No structured results found in output", "timestamp": "'$(date -u +%Y-%m-%dT%H:%M:%S.%3NZ)'"}' > "$extracted_results"
        fi
        
    else
        print_error "Benchmark failed for $config"
    fi
    
    # Stop and remove container
    echo "Cleaning up container: $container_name"
    docker stop "$container_name" >/dev/null 2>&1 || true
    docker rm "$container_name" >/dev/null 2>&1 || true
    
    # Add delay between benchmarks
    echo "Waiting 10 seconds before next benchmark..."
    sleep 10
}

# Main execution
print_header "Laravel Benchmark Suite"

# Check if K6 is installed
if ! command -v k6 &> /dev/null; then
    print_error "K6 is not installed. Please install K6 first."
    echo "Visit: https://k6.io/docs/getting-started/installation/"
    exit 1
fi

# Build all Docker images
print_header "Building Docker Images"

build_image "plain-no-opcache" "Dockerfile.plain-no-opcache"
build_image "plain-opcache" "Dockerfile.plain-opcache"  

print_success "All Docker images built successfully!"

# Run benchmarks for each configuration
print_header "Running Benchmarks"

run_benchmark "plain-no-opcache" $((PORT_BASE + 1))
run_benchmark "plain-opcache" $((PORT_BASE + 2))

# Generate summary report
print_header "Generating Summary Report"

summary_file="$RESULTS_DIR/benchmark-summary-$(date +%Y%m%d-%H%M%S).md"

cat > "$summary_file" << EOF
# Laravel Benchmark Results Summary

Generated on: $(date)

## Configurations Tested

1. **Plain Laravel (No OPcache)**: Standard Laravel installation without OPcache
2. **Plain Laravel (OPcache)**: Standard Laravel installation with OPcache enabled

## Result Files

EOF

# List all result files
find "$RESULTS_DIR" -name "*.json" -o -name "*.log" | sort >> "$summary_file"

print_success "Summary report generated: $summary_file"

print_header "Benchmark Suite Complete!"
print_success "All benchmarks completed successfully!"
print_success "Results saved in: $RESULTS_DIR"

echo -e "\n${BLUE}Next Steps:${NC}"
echo "1. Review the results in the $RESULTS_DIR directory"
echo "2. Compare performance metrics between configurations"
echo "3. Analyze the impact of OPcache on performance"

# Cleanup Docker network
echo -e "\n${YELLOW}Note: Docker network '$DOCKER_NETWORK' is still available for manual testing${NC}"
echo -e "${YELLOW}To remove it later, run: docker network rm $DOCKER_NETWORK${NC}"
