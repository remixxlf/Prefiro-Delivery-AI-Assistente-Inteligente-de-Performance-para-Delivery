import { createApp } from "vue";
import { createPinia } from "pinia";
import router from "./router";
import App from "./App.vue";
import axios from "axios";

// ─── Axios global config ───────────────────────────────────────────────
axios.defaults.headers.common["X-Requested-With"] = "XMLHttpRequest";
axios.defaults.headers.common["Accept"] = "application/json";
axios.defaults.baseURL = import.meta.env.VITE_API_URL ?? "/api/v1";

// Injeta token CSRF em todas as requests
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
if (csrfToken) {
    axios.defaults.headers.common["X-CSRF-TOKEN"] = csrfToken;
}

// ─── Criar app Vue ────────────────────────────────────────────────────
const app = createApp(App);
const pinia = createPinia();

app.use(pinia);
app.use(router);

// Disponibilizar axios globalmente
app.config.globalProperties.$axios = axios;

app.mount("#app");