# Vue frontend — local development

Vue 3/Vite, Bootstrap, Pinia, Chart.js and homepage GSAP. See the [root README](../README.md#local-development-with-xampp) for the full Windows/XAMPP setup.

```powershell
npm install
# Fresh checkout only: copy .env.example to .env.
npm run dev
```

Open **http://localhost:5173** after Laravel starts at **http://127.0.0.1:8000**. Keep `VITE_API_BASE_URL=/` and `VITE_BACKEND_PROXY_TARGET=http://127.0.0.1:8000`. Vite proxies API/CSRF/storage/placeholder requests so existing cookie authentication works without cross-host SameSite changes. Restart Vite after env changes.

```powershell
npm run test:unit -- --run
npm run lint
npm run build
```

Production output is frontend/dist. Development proxy configuration is not bundled into static output; production hosting requires its own API routing. No application routes, Pinia state or API contracts were changed for local setup.
