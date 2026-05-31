import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
    stages: [
        { duration: '30s', target: 50 }, // ramp up to 50 users
        { duration: '1m', target: 50 }, // stay at 50 users
        { duration: '30s', target: 0 }, // ramp down
    ],
    thresholds: {
        http_req_duration: ['p(95)<500'], // 95% of requests should be below 500ms
        http_req_failed: ['rate<0.01'], // less than 1% failure rate
    },
};

const BASE_URL = __ENV.API_URL || 'http://127.0.0.1:8000/api';

export default function () {
    // 1. Fetch branch info (common hit)
    let res = http.get(`${BASE_URL}/customer/branches/1`);
    check(res, {
        'branch info status is 200': (r) => r.status === 200,
    });

    sleep(1);

    // 2. Fetch categories & menu items
    let menuRes = http.get(`${BASE_URL}/customer/menu/categories`);
    check(menuRes, {
        'menu categories status is 200': (r) => r.status === 200,
    });

    sleep(2);
}
