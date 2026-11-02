// Equivalente en k6 de TC-P-01 (reducido a 40 s): GET /login, extrae _token y POST /login.
import http from "k6/http";
import { check } from "k6";
import { SharedArray } from "k6/data";

const usuarios = new SharedArray("usuarios", () => Array.from({ length: 50 }, (_, i) => `qa-perf-${String(i + 1).padStart(3, "0")}@firefly.test`));
const BASE = "http://host.docker.internal:8080";

export const options = {
  scenarios: { carga: { executor: "ramping-vus", stages: [{ duration: "10s", target: 50 }, { duration: "30s", target: 50 }] } },
  thresholds: { "http_req_duration{name:POST_login}": ["avg<=500"], http_req_failed: ["rate<0.01"] },
};

export default function () {
  http.cookieJar().clear(BASE);
  const pagina = http.get(`${BASE}/login`, { tags: { name: "GET_login" } });
  const token = pagina.html().find('input[name="_token"]').attr("value");
  const r = http.post(`${BASE}/login`, { email: usuarios[(__VU - 1) % 50], password: "QaRendimiento-2026!", _token: token }, {
    headers: { Accept: "application/json" }, tags: { name: "POST_login" },
  });
  check(r, { "POST /login responde 204": (x) => x.status === 204 });
}
