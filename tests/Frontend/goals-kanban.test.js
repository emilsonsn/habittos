import test from "node:test";
import assert from "node:assert/strict";
import "../../public/goals-kanban.js";
const event = (x, y) => ({
    button: 0,
    clientX: x,
    clientY: y,
    preventDefault() {},
});

test("drag moves the intended goal to the destination and does not open its dialog", () => {
    const calls = [];
    const board = globalThis.HabittosGoalKanban.create(
        {
            changeStatus: (...args) => calls.push(["status", ...args]),
            openDetails: (id) => calls.push(["open", id]),
        },
        () => "completed",
    );
    board.start(event(20, 30), 7, "Ler");
    board.move(event(22, 31));
    assert.equal(board.dragged, null);
    board.move(event(90, 60));
    assert.equal(board.dragged, 7);
    board.end(event(100, 60));
    board.open(7);
    assert.deepEqual(calls, [["status", 7, "completed"]]);
    assert.equal(board.dragged, null);
});

test("click opens details, and a drop outside the board or cancellation does not mutate status", () => {
    const calls = [];
    const board = globalThis.HabittosGoalKanban.create(
        {
            changeStatus: () => calls.push("status"),
            openDetails: (id) => calls.push(id),
        },
        () => null,
    );
    board.start(event(1, 1), 3, "Correr");
    board.end(event(1, 1));
    board.open(3);
    board.start(event(1, 1), 3, "Correr");
    board.end(event(90, 80));
    board.start(event(1, 1), 3, "Correr");
    board.move(event(90, 80));
    board.cancel();
    board.end(event(90, 80));
    assert.deepEqual(calls, [3]);
});
