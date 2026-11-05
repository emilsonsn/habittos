import test from "node:test";
import assert from "node:assert/strict";
import "../../public/js/category-pie.js";

const pie = globalThis.HabittosCategoryPie;
const slices = [
    { name: "Casa", end: 25 },
    { name: "Mercado", end: 100 },
];

test("hover follows the clockwise sectors from the top, including boundaries", () => {
    assert.equal(pie.sliceAt(0, -1, slices).name, "Casa");
    assert.equal(pie.sliceAt(0.5, -0.5, slices).name, "Casa");
    assert.equal(pie.sliceAt(1, 0, slices).name, "Mercado");
    assert.equal(pie.sliceAt(0, 1, slices).name, "Mercado");
    assert.equal(pie.sliceAt(-1, 0, slices).name, "Mercado");
    assert.equal(pie.sliceAt(0.9, 0.9, slices), null);
    assert.equal(pie.sliceAt(0, 0, []), null);
    assert.equal(pie.sliceAt(-1, 0, [slices[1]]).name, "Mercado");
});

test("hover uses the current chart size, position and updated categories", () => {
    const event = {
        clientX: 175,
        clientY: 75,
        currentTarget: {
            getBoundingClientRect: () => ({
                left: 100,
                top: 50,
                width: 100,
                height: 100,
            }),
        },
    };
    assert.equal(pie.at(event, slices).name, "Casa");
    assert.equal(
        pie.at(event, [{ name: "Transporte", end: 100 }]).name,
        "Transporte",
    );
    event.clientX = 200;
    event.clientY = 150;
    assert.equal(pie.at(event, slices), null);
});
