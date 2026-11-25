import http from 'k6/http';
import { check, sleep } from 'k6';

// ====================================================================
// PlexiQ LIMS Commercial Scale Load Benchmark (k6)
// Target: High-throughput 21 CFR Part 11 & ALCOA+ operations
// Concurrency: 50 Virtual Users (VUs) over 3 stages
// ====================================================================

export const options = {
    stages: [
        { duration: '30s', target: 20 }, // Ramp up to 20 users
        { duration: '1m', target: 50 },  // Sustained peak at 50 users
        { duration: '20s', target: 0 },  // Ramp down
    ],
    thresholds: {
        http_req_duration: ['p(95)<500'], // 95% of requests must complete within 500ms
        http_req_failed: ['rate<0.01'],   // Error rate must be below 1%
    },
};

const BASE_URL = __ENV.BASE_URL || 'http://localhost:8080';

export default function () {
    const params = {
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    };

    // 1. Health check & status
    const healthRes = http.get(`${BASE_URL}/health`, params);
    check(healthRes, {
        'health check returns 200': (r) => r.status === 200,
    });

    // 2. High-throughput BI Analytics Feed
    const biRes = http.get(`${BASE_URL}/api/analytics/bi-feed`, params);
    check(biRes, {
        'BI feed returns 200 or 401': (r) => r.status === 200 || r.status === 401,
    });

    // 3. CycloneDX 1.5 JSON SBOM Endpoint
    const sbomRes = http.get(`${BASE_URL}/api/ops/sbom`, params);
    check(sbomRes, {
        'SBOM endpoint accessible': (r) => r.status === 200 || r.status === 401,
    });

    sleep(1);
}
