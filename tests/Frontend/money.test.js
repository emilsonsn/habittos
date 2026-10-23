import test from "node:test";
import assert from "node:assert/strict";
import "../../public/money.js";

const money = globalThis.HabittosMoney;

test("localized input and pasted currency preserve the exact amount", () => {
    assert.deepEqual(money.mask("R$ 1.234,56"), {
        display: "1.234,56",
        value: "1234.56",
    });
    assert.deepEqual(money.mask("1234.56"), {
        display: "1.234,56",
        value: "1234.56",
    });
    assert.equal(money.mask("0,01").value, "0.01");
    assert.equal(money.mask("999.999.999,99").value, "999999999.99");
});

test("empty, zero and negative inputs remain distinct for backend validation", () => {
    assert.equal(money.mask("").value, "");
    assert.equal(money.mask("0").value, "0.00");
    assert.equal(money.mask("-10,00").value, "-10.00");
    assert.equal(money.format(null), "");
    assert.equal(money.format("0"), "0,00");
});

test("typing whole values and decimal fractions does not shift cents", () => {
    assert.deepEqual(money.mask("1234"), {
        display: "1.234",
        value: "1234.00",
    });
    assert.deepEqual(money.mask("1.234,"), {
        display: "1.234,",
        value: "1234.00",
    });
    assert.deepEqual(money.mask("1.234,5"), {
        display: "1.234,5",
        value: "1234.50",
    });
    assert.equal(money.format("1234.50"), "1.234,50");
});
