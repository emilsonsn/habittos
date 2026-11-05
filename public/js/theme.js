(() => {
    const storageKey = "habittos.theme";
    const normalize = (value) => (value === "light" ? "light" : "dark");
    let theme = "dark";

    try {
        theme = normalize(localStorage.getItem(storageKey));
    } catch {
        // O botão continua funcionando quando o navegador bloqueia o armazenamento.
    }

    function applyTheme() {
        document.documentElement.dataset.theme = theme;
        document.querySelectorAll("[data-theme-toggle]").forEach((button) => {
            button.setAttribute("aria-pressed", String(theme === "dark"));
            button.title =
                theme === "dark" ? "Ativar modo claro" : "Ativar modo escuro";
        });
        document
            .querySelector('meta[name="theme-color"]')
            ?.setAttribute("content", theme === "dark" ? "#141423" : "#1d1b4b");
    }

    applyTheme();
    document.addEventListener("DOMContentLoaded", applyTheme);
    document.addEventListener("livewire:navigated", applyTheme);
    document.addEventListener("click", (event) => {
        if (!event.target.closest("[data-theme-toggle]")) return;
        theme = theme === "dark" ? "light" : "dark";
        applyTheme();
        try {
            localStorage.setItem(storageKey, theme);
        } catch {
            // Mantém a escolha na página atual mesmo sem localStorage disponível.
        }
    });
    window.addEventListener("storage", (event) => {
        if (event.key !== storageKey && event.key !== null) return;
        theme = normalize(event.newValue);
        applyTheme();
    });
})();
