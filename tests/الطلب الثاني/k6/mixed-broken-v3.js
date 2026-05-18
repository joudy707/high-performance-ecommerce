import http from 'k6/http';
import { check, sleep } from 'k6';

http.setResponseCallback(http.expectedStatuses({ min: 200, max: 299 }, 400, 404, 429));

const RATE = Number(__ENV.RATE || 5);
const DURATION = __ENV.DURATION || '60s';

export const options = {
  scenarios: {
    mixed_load: {
      executor: 'constant-arrival-rate',
      rate: RATE,
      timeUnit: '1s',
      duration: DURATION,
      preAllocatedVUs: 20,
      maxVUs: 80,
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.05'],
  },
};

const BASE_URL = __ENV.BASE_URL || 'http://127.0.0.1:8000/api';
const PRODUCT_ID = __ENV.PRODUCT_ID || '1';
const SEARCH_TERMS = ['product', 'product 1', 'product 2', 'product 9', 'p', ''];
const jsonHeaders = {
  headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
  timeout: '30s',
};

export default function () {
  const doSearch = Math.random() < 0.70;

  if (doSearch) {
    const q = SEARCH_TERMS[Math.floor(Math.random() * SEARCH_TERMS.length)];
    const res = http.get(`${BASE_URL}/products-search-broken?q=${encodeURIComponent(q)}`, jsonHeaders);
    check(res, { 'broken search handled': (r) => [200, 429].includes(r.status) });
  } else {
    const payload = JSON.stringify({ user_id: (__VU % 50) + 1, quantity: 1 });
    const res = http.post(`${BASE_URL}/cart/${PRODUCT_ID}/add-broken`, payload, jsonHeaders);
    check(res, { 'broken cart handled': (r) => [201, 400, 429].includes(r.status) });
  }

  sleep(0.05);
}
