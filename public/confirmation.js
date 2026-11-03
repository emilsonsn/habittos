// Confirmações assíncronas para ações Livewire e formulários HTTP, sem avaliar código.
globalThis.HabittosConfirmation = {
    create(document) {
        let pending = null;
        const bypass = new WeakSet();
        const dialog = () =>
            document.getElementById("action-confirmation-dialog");

        function finish(approved) {
            if (!pending) return;
            const request = pending;
            pending = null;
            dialog()?.close();
            if (request.source.isConnected) {
                request.source.focus({ preventScroll: true });
                if (approved) {
                    bypass.add(request.source);
                    try {
                        request.resume();
                    } finally {
                        bypass.delete(request.source);
                    }
                }
            }
        }

        function request(source, resume) {
            if (pending) return;
            const modal = dialog();
            if (!modal) return; // Falha fechada: uma ação crítica nunca segue sem confirmação.
            modal.querySelector("[data-confirmation-title]").textContent =
                source.dataset.confirmTitle || "Confirmar ação?";
            modal.querySelector("[data-confirmation-description]").textContent =
                source.dataset.confirm;
            modal.querySelector("[data-confirmation-accept]").textContent =
                source.dataset.confirmLabel || "Confirmar";
            const subject = modal.querySelector("[data-confirmation-subject]");
            subject.textContent = source.dataset.confirmSubject || "";
            subject.hidden = !subject.textContent;
            modal.dataset.confirmationTone =
                source.dataset.confirmTone || modal.dataset.defaultTone;
            pending = { source, resume };
            modal.showModal();
            modal
                .querySelector(
                    ".confirmation-actions [data-confirmation-cancel]",
                )
                .focus();
        }

        function intercept(event, source, resume) {
            if (!source || source.disabled) return;
            if (bypass.has(source)) {
                bypass.delete(source);
                return;
            }
            event.preventDefault();
            event.stopImmediatePropagation();
            request(source, resume);
        }

        document.addEventListener(
            "click",
            (event) => {
                const source = event.target.closest("[data-confirm]");
                if (source?.tagName === "FORM") return;
                intercept(event, source, () => source.click());
            },
            true,
        );
        document.addEventListener(
            "submit",
            (event) => {
                const form = event.target.closest("form[data-confirm]");
                const submitter = event.submitter;
                intercept(event, form, () =>
                    form.requestSubmit(
                        submitter?.isConnected && submitter.form === form
                            ? submitter
                            : undefined,
                    ),
                );
            },
            true,
        );
        document.addEventListener("click", (event) => {
            const modal = dialog();
            if (!pending || !modal?.contains(event.target)) return;
            if (event.target.closest("[data-confirmation-accept]"))
                finish(true);
            else if (
                event.target === modal ||
                event.target.closest("[data-confirmation-cancel]")
            )
                finish(false);
        });
        // O evento cancel de <dialog> não propaga por bubbling.
        document.addEventListener(
            "cancel",
            (event) => {
                if (pending && event.target === dialog()) {
                    event.preventDefault();
                    finish(false);
                }
            },
            true,
        );
        document.addEventListener("livewire:navigating", () => finish(false));
        document.addEventListener(
            "close",
            (event) => {
                if (pending && event.target === dialog()) finish(false);
            },
            true,
        );

        return { cancel: () => finish(false) };
    },
};

if (typeof document !== "undefined") {
    globalThis.HabittosConfirmation.create(document);
}
