import test from "node:test";
import assert from "node:assert/strict";
import "../../public/js/confirmation.js";

function setup() {
    const listeners = new Map();
    const parts = new Map();
    let modal;
    const node = (selector) => ({
        textContent: "",
        hidden: false,
        focused: false,
        closest: (query) => (query === selector ? parts.get(selector) : null),
        focus() {
            this.focused = true;
        },
    });
    for (const name of [
        "title",
        "description",
        "accept",
        "cancel",
        "subject",
    ]) {
        const selector = `[data-confirmation-${name}]`;
        parts.set(selector, node(selector));
    }
    modal = {
        open: false,
        dataset: { defaultTone: "danger" },
        querySelector: (selector) =>
            parts.get(selector.replace(".confirmation-actions ", "")),
        contains: (target) =>
            target === modal || [...parts.values()].includes(target),
        showModal() {
            this.open = true;
        },
        close() {
            this.open = false;
        },
        closest: () => null,
    };
    const document = {
        getElementById: () => modal,
        addEventListener(type, handler, capture = false) {
            const handlers = listeners.get(type) ?? [];
            handlers.push({ handler, capture });
            listeners.set(type, handlers);
        },
    };
    const fire = (type, target, extra = {}) => {
        const event = {
            target,
            stopped: false,
            prevented: false,
            preventDefault() {
                this.prevented = true;
            },
            stopImmediatePropagation() {
                this.stopped = true;
            },
            ...extra,
        };
        const handlers = [...(listeners.get(type) ?? [])].sort(
            (a, b) => Number(b.capture) - Number(a.capture),
        );
        for (const { handler } of handlers) {
            handler(event);
            if (event.stopped) break;
        }
        return event;
    };
    const source = {
        tagName: "BUTTON",
        dataset: {
            confirmTitle: "Excluir meta?",
            confirm: "A meta será excluída.",
            confirmLabel: "Excluir meta",
            confirmSubject: '<img src=x onerror="alert(1)">',
        },
        isConnected: true,
        calls: 0,
        focused: false,
        closest: (selector) => (selector === "[data-confirm]" ? source : null),
        focus() {
            this.focused = true;
        },
        click() {
            const event = fire("click", source);
            if (!event.prevented) this.calls++;
        },
    };
    const controller = globalThis.HabittosConfirmation.create(document);
    return {
        source,
        modal,
        parts,
        fire,
        controller,
        approve: () => fire("click", parts.get("[data-confirmation-accept]")),
        cancel: () => fire("click", parts.get("[data-confirmation-cancel]")),
    };
}

test("critical click is blocked until approval, then resumed exactly once", () => {
    const ui = setup();
    ui.source.click();
    assert.equal(ui.source.calls, 0);
    assert.equal(ui.modal.open, true);
    assert.equal(ui.parts.get("[data-confirmation-cancel]").focused, true);
    assert.equal(
        ui.parts.get("[data-confirmation-subject]").textContent,
        ui.source.dataset.confirmSubject,
    );
    ui.approve();
    ui.approve();
    assert.equal(ui.source.calls, 1);
    assert.equal(ui.modal.open, false);
    assert.equal(ui.source.focused, true);
});

test("cancel, Escape, backdrop, and navigation never execute the pending action", () => {
    for (const close of [
        (ui) => ui.cancel(),
        (ui) => ui.fire("cancel", ui.modal),
        (ui) => ui.fire("click", ui.modal),
        (ui) => ui.fire("livewire:navigating", {}),
        (ui) => ui.fire("close", ui.modal),
    ]) {
        const ui = setup();
        ui.source.click();
        close(ui);
        ui.approve();
        assert.equal(ui.source.calls, 0);
        assert.equal(ui.modal.open, false);
    }
});

test("disconnected triggers and repeated pending requests cannot run destructive actions", () => {
    const ui = setup();
    ui.source.click();
    ui.source.click();
    ui.source.isConnected = false;
    ui.approve();
    assert.equal(ui.source.calls, 0);
});

test("confirmed form submission retains the submitter and does not prompt twice", () => {
    const ui = setup();
    const form = ui.source;
    form.tagName = "FORM";
    form.closest = (selector) =>
        selector === "form[data-confirm]" || selector === "[data-confirm]"
            ? form
            : null;
    const submitter = { isConnected: true, form };
    form.requestSubmit = (button) => {
        assert.equal(button, submitter);
        const event = ui.fire("submit", form, { submitter: button });
        if (!event.prevented) form.calls++;
    };
    ui.fire("submit", form, { submitter });
    assert.equal(form.calls, 0);
    ui.approve();
    assert.equal(form.calls, 1);
});

test("delegated confirmation works again after cancellation and reflects new item details", () => {
    const ui = setup();
    ui.source.click();
    ui.cancel();
    ui.source.dataset.confirmTitle = "Excluir outra meta?";
    ui.source.click();
    assert.equal(
        ui.parts.get("[data-confirmation-title]").textContent,
        "Excluir outra meta?",
    );
    ui.approve();
    assert.equal(ui.source.calls, 1);
});
