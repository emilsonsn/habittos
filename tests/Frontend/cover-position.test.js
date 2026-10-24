import test from "node:test";
import assert from "node:assert/strict";
import "../../public/cover-position.js";

const position = globalThis.HabittosCoverPosition;

test("dragging moves the visible crop by the pointer distance on the scaled image", () => {
    const overflow = position.overflow(400, 800, 1000, 200);
    assert.deepEqual(overflow, { x: 0, y: 1800 });
    assert.deepEqual(position.drag(50, 50, 100, 180, overflow), {
        x: 50,
        y: 40,
    });
});

test("wide images can be dragged horizontally without shifting an uncropped axis", () => {
    const overflow = position.overflow(2000, 200, 500, 200);
    assert.deepEqual(overflow, { x: 1500, y: 0 });
    assert.deepEqual(position.drag(50, 50, 150, -100, overflow), {
        x: 40,
        y: 50,
    });
});

test("crop boundaries prevent empty areas and unloaded images do not divide by zero", () => {
    assert.deepEqual(position.drag(50, 50, 10000, -10000, { x: 100, y: 100 }), {
        x: 0,
        y: 100,
    });
    const unloaded = position.overflow(0, 0, 1000, 200);
    assert.deepEqual(position.drag(25, 75, 10, 10, unloaded), { x: 25, y: 75 });
});
