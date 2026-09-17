import http from 'k6/http';
import { check } from 'k6';

export const options = {
    vus: 1,
    duration: '1m',
};

const payload = JSON.stringify({
    event_type: 'page_view',
    user_id: 1,
    payload: { page: '/home' },
});

export default function () {
    const res = http.post('http://localhost:8080/api/events', payload, {
        headers: { 'Content-Type': 'application/json' },
    });

    check(res, {
        'status is 202': (r) => r.status === 202,
    });
}