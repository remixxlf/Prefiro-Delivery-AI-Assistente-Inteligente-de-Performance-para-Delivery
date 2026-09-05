import { createRouter, createWebHistory } from "vue-router";

const routes = [
    {
        path: "/",
        name: "chat",
        component: () => import("@/views/ChatView.vue"),
        meta: { title: "Chat — Prefiro Delivery AI" },
    },
    {
        path: "/:pathMatch(.*)*",
        redirect: "/",
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach((to) => {
    document.title = to.meta?.title ?? "Prefiro Delivery AI";
});

export default router;