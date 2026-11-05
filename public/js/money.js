// O estado enviado ao Livewire usa ponto decimal; a tela usa a convenção brasileira.
const formatWhole = (digits) => digits.replace(/\B(?=(\d{3})+(?!\d))/g, ".");

globalThis.HabittosMoney = {
    format(value) {
        if (value === null || value === undefined || value === "") return "";
        const [whole, fraction = ""] = String(value).split(".");
        return `${formatWhole(whole)},${fraction.padEnd(2, "0").slice(0, 2)}`;
    },

    mask(input) {
        let text = input.replace(/R\$/gi, "").replace(/\s/g, "");
        const negative = text.startsWith("-");
        text = text.replace(/-/g, "");
        // Também aceita valores colados com ponto decimal, como 1234.56.
        if (!text.includes(",") && /^\d+\.\d{1,2}$/.test(text)) {
            text = text.replace(".", ",");
        }
        const hasDecimal = text.includes(",");
        const [integerPart, decimalPart = ""] = text.split(",");
        let whole = integerPart.replace(/\D/g, "").replace(/^0+(?=\d)/, "");
        const fraction = decimalPart.replace(/\D/g, "").slice(0, 2);
        if (!whole && !hasDecimal)
            return { display: negative ? "-" : "", value: "" };
        whole ||= "0";
        const sign = negative ? "-" : "";
        return {
            display: `${sign}${formatWhole(whole)}${hasDecimal ? `,${fraction}` : ""}`,
            value: `${sign}${whole}.${fraction.padEnd(2, "0")}`,
        };
    },
};
