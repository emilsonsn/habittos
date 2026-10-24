import test from "node:test";
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { runInNewContext } from "node:vm";

const script = readFileSync(
    new URL("../../public/theme.js", import.meta.url),
    "utf8",
);

function browser(savedTheme, { blocked = false } = {}) {
    const events = {};
    const storageEvents = {};
    const attributes = {};
    const button = {
        setAttribute: (key, value) => (attributes[key] = value),
    };
    const root = { dataset: {} };
    const storage = new Map(savedTheme ? [["habittos.theme", savedTheme]] : []);
    const document = {
        documentElement: root,
        querySelectorAll: () => [button],
        querySelector: () => null,
        addEventListener: (name, callback) => (events[name] = callback),
    };
    runInNewContext(script, {
        document,
        window: {
            addEventListener: (name, callback) =>
                (storageEvents[name] = callback),
        },
        localStorage: {
            getItem(key) {
                if (blocked) throw new Error("Storage blocked");
                return storage.get(key) ?? null;
            },
            setItem(key, value) {
                if (blocked) throw new Error("Storage blocked");
                storage.set(key, value);
            },
        },
    });
    return {
        root,
        attributes,
        storage,
        events,
        storageEvents,
        click: () => events.click({ target: { closest: () => button } }),
    };
}

test("restores the saved theme before the page is loaded", () => {
    const page = browser("dark");
    assert.equal(page.root.dataset.theme, "dark");
    assert.equal(page.attributes["aria-pressed"], "true");
    assert.equal(browser().root.dataset.theme, "dark");
    assert.equal(browser("invalid").root.dataset.theme, "dark");
    assert.equal(browser("light").root.dataset.theme, "light");
});

test("toggles in both directions and restores the choice on reload", () => {
    const page = browser("light");
    page.click();
    assert.equal(page.root.dataset.theme, "dark");
    assert.equal(page.storage.get("habittos.theme"), "dark");
    assert.equal(
        browser(page.storage.get("habittos.theme")).root.dataset.theme,
        "dark",
    );
    page.click();
    assert.equal(page.root.dataset.theme, "light");
    assert.equal(page.storage.get("habittos.theme"), "light");
    assert.equal(page.attributes["aria-pressed"], "false");
});

test("reapplies the current theme and button state after Livewire navigation", () => {
    const page = browser("light");
    page.click();
    page.root.dataset.theme = "light";
    page.attributes["aria-pressed"] = "false";
    page.events["livewire:navigated"]();
    assert.equal(page.root.dataset.theme, "dark");
    assert.equal(page.attributes["aria-pressed"], "true");
});

test("remains usable when browser storage is unavailable", () => {
    const page = browser(null, { blocked: true });
    assert.equal(page.root.dataset.theme, "dark");
    page.click();
    assert.equal(page.root.dataset.theme, "light");
    page.events["livewire:navigated"]();
    assert.equal(page.root.dataset.theme, "light");
    page.click();
    assert.equal(page.root.dataset.theme, "dark");
});

test("synchronizes theme changes and cleared preferences across tabs", () => {
    const page = browser("light");
    page.storageEvents.storage({ key: "unrelated", newValue: "dark" });
    assert.equal(page.root.dataset.theme, "light");
    page.storageEvents.storage({ key: "habittos.theme", newValue: "dark" });
    assert.equal(page.root.dataset.theme, "dark");
    page.storageEvents.storage({ key: "habittos.theme", newValue: "light" });
    assert.equal(page.root.dataset.theme, "light");
    page.storageEvents.storage({ key: null, newValue: null });
    assert.equal(page.root.dataset.theme, "dark");
});
