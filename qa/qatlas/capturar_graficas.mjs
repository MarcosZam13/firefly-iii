// Saca del dashboard de QAtlas las gráficas para el informe (el profesor las pide de ahí).
// Solo lee: cualquier petición que no sea GET se corta. Repetirlo después de cada ejecución nueva.
//
// Uso: QATLAS_TOKEN=... node qa/qatlas/capturar_graficas.mjs CARPETA_DE_SALIDA
// Playwright se toma de PLAYWRIGHT_DESDE (una carpeta con @playwright/test instalado).
import { createRequire } from "node:module";

const require = createRequire(process.env.PLAYWRIGHT_DESDE ?? `${process.cwd()}/`);
const { chromium } = require("@playwright/test");

const BASE = "https://geotorotur.tail052473.ts.net/qatlas";
const SALIDA = process.argv[2];
const token = process.env.QATLAS_TOKEN;
if (!SALIDA || !token) {
  console.error("Falta la carpeta de salida o QATLAS_TOKEN.");
  process.exit(2);
}

const TARJETAS = {
  "qatlas-distribucion": "Distribución de ejecución",
  "qatlas-defectos": "Defectos por severidad y prioridad",
  "qatlas-automatizacion": "Tasa de automatización",
  "qatlas-trazabilidad": "Matriz de trazabilidad",
  "qatlas-cobertura": "% Cobertura",
};

const navegador = await chromium.launch();
const contexto = await navegador.newContext({ viewport: { width: 1440, height: 2600 }, deviceScaleFactor: 2 });
await contexto.addInitScript(([t]) => {
  localStorage.setItem("qatlas-token", t);
  localStorage.setItem("qatlas-active-project-id", "6");
  localStorage.setItem("qatlas-active-project-code", "FF");
}, [token]);
const pagina = await contexto.newPage();

await pagina.route("**/qatlas/api/**", async (ruta) => {
  const pedido = ruta.request();
  if (pedido.method() !== "GET") return ruta.abort();
  // El tema oscuro viene de la preferencia de la cuenta. Para imprimir se cambia solo en esta
  // respuesta, sin tocar la cuenta.
  if (pedido.url().endsWith("/auth/me")) {
    const respuesta = await ruta.fetch();
    return ruta.fulfill({ response: respuesta, json: { ...(await respuesta.json()), theme_preference: "light" } });
  }
  return ruta.continue();
});

await pagina.goto(`${BASE}/p/FF/dashboard`, { waitUntil: "networkidle" });
await pagina.waitForTimeout(1500);
await pagina.screenshot({ path: `${SALIDA}/qatlas-dashboard.png` });
for (const [archivo, titulo] of Object.entries(TARJETAS)) {
  const tarjeta = pagina.getByText(titulo, { exact: true }).first().locator("xpath=ancestor::div[contains(@class,'rounded')][1]");
  await tarjeta.screenshot({ path: `${SALIDA}/${archivo}.png` });
  console.log("captura", archivo);
}
await pagina.goto(`${BASE}/p/FF/test-runs`, { waitUntil: "networkidle" });
await pagina.waitForTimeout(1000);
await pagina.setViewportSize({ width: 1440, height: 900 });
await pagina.screenshot({ path: `${SALIDA}/qatlas-ejecuciones.png` });
console.log("captura qatlas-ejecuciones");
await navegador.close();
