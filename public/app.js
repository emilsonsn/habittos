// Delegação mantém as confirmações funcionando após navegações Livewire.
document.addEventListener("submit", (event) => {
    const form = event.target.closest("form[data-confirm]");
    if (form && !window.confirm(form.dataset.confirm)) event.preventDefault();
});

// Registrado antes do Livewire iniciar o Alpine; reutilizado em toda navegação.
document.addEventListener("alpine:init", () => {
    Alpine.data("moneyInput", (wire, model) => ({
        display: "",

        init() {
            this.display = HabittosMoney.format(wire.$get(model));
            this.$watch(
                () => wire.$get(model),
                (value) => {
                    if (document.activeElement !== this.$el) {
                        this.display = HabittosMoney.format(value);
                    }
                },
            );
            if (this.$el.hasAttribute("autofocus")) {
                this.$nextTick(() => this.$el.focus());
            }
        },

        update(event) {
            const input = event.target;
            const distanceFromEnd =
                input.value.length -
                (input.selectionStart ?? input.value.length);
            const masked = HabittosMoney.mask(input.value);
            this.display = masked.display;
            input.value = masked.display;
            const caret = Math.max(0, input.value.length - distanceFromEnd);
            input.setSelectionRange(caret, caret);
            wire.$set(model, masked.value, false);
        },

        decimalKey(event) {
            if (event.key !== ".") return;
            event.preventDefault();
            const input = event.target;
            input.setRangeText(
                ",",
                input.selectionStart,
                input.selectionEnd,
                "end",
            );
            this.update(event);
        },

        format() {
            this.display = HabittosMoney.format(wire.$get(model));
        },
    }));
});
