import test from "node:test";
import assert from "node:assert/strict";
import "../../public/js/study-canvas.js";
const canvas = globalThis.HabittosStudyCanvas;
const state = () => ({
    roadmapId: 9,
    nodes: [
        { id: 1, title: "Básico", x: 100, y: 80, topicIds: [4] },
        { id: 2, title: "Prática", x: 100, y: 500, topicIds: [] },
    ],
    edges: [{ id: 3, source: 1, target: 2 }],
});
const pointer = (x, y) => ({
    button: 0,
    pointerId: 1,
    clientX: x,
    clientY: y,
    preventDefault() {},
});
const board = (wire = {}, editable = true) => {
    const model = canvas.create(wire, state(), editable);
    model.sync(state());
    model.$refs = {
        viewport: {
            setPointerCapture() {},
            hasPointerCapture() {
                return false;
            },
        },
    };
    return model;
};

test("automatic layout follows branches and keeps tall stages from overlapping", () => {
    const nodes = [1, 2, 3, 4].map((id) => ({
        id,
        height: id === 2 ? 500 : 200,
    }));
    const positions = canvas.layout(nodes, [
        { source: 1, target: 2 },
        { source: 1, target: 3 },
        { source: 2, target: 4 },
        { source: 3, target: 4 },
    ]);
    assert.equal(positions[1].y, positions[2].y);
    assert.notEqual(positions[1].x, positions[2].x);
    assert.ok(positions[3].y > positions[1].y + 500);
    assert.ok(positions[1].y > positions[0].y + 200);
});

test("free-board cycles, missing nodes and disconnected stages produce finite positions", () => {
    const positions = canvas.layout(
        [{ id: 1 }, { id: 2 }, { id: 3 }],
        [
            { source: 1, target: 2 },
            { source: 2, target: 1 },
            { source: 3, target: 99 },
        ],
    );
    assert.equal(positions.length, 3);
    assert.ok(
        positions.every(
            (node) => Number.isFinite(node.x) && Number.isFinite(node.y),
        ),
    );
    assert.notEqual(positions[0].y, positions[1].y);
});

test("zoom keeps the point under the cursor fixed and respects zoom limits", () => {
    const model = board();
    model.panX = -120;
    model.panY = 50;
    model.scale = 0.5;
    const before = {
        x: (200 - model.panX) / model.scale,
        y: (300 - model.panY) / model.scale,
    };
    model.zoom(1.4, 200, 300);
    assert.ok(Math.abs((200 - model.panX) / model.scale - before.x) < 0.001);
    assert.ok(Math.abs((300 - model.panY) / model.scale - before.y) < 0.001);
    model.zoom(100);
    assert.equal(model.scale, 2);
    model.zoom(0.0001);
    assert.equal(model.scale, 0.2);
});

test("fit includes negative coordinates and connectors follow moving stages", () => {
    const nodes = [
        { x: -400, y: -100, width: 320, height: 200 },
        { x: 200, y: 350, width: 320, height: 300 },
    ];
    const view = canvas.fit(nodes, 1000, 700);
    assert.ok(nodes.every((node) => node.x * view.scale + view.x >= 49));
    assert.ok(
        nodes.every(
            (node) => (node.y + node.height) * view.scale + view.y <= 651,
        ),
    );
    const original = canvas.curve(nodes[0], nodes[1]);
    assert.notEqual(original, canvas.curve(nodes[0], { ...nodes[1], x: 400 }));
    assert.match(original, /^M .* C /);
});

test("drag converts pointer deltas through zoom and saves only at the end", async () => {
    const calls = [];
    const model = board({ saveCanvasPositions: (...args) => calls.push(args) });
    model.scale = 0.5;
    model.startNode(pointer(20, 30), 1);
    model.move(pointer(60, 50));
    assert.equal(model.node(1).x, 180);
    assert.equal(model.node(1).y, 120);
    assert.equal(calls.length, 0);
    await model.end(pointer(60, 50));
    assert.deepEqual(calls, [[9, [{ id: 1, x: 184, y: 120 }]]]);
    assert.equal(model.gesture, null);
    assert.equal(model.busy, false);
});

test("clicks and cancelled gestures do not save; a failed save restores the old position", async () => {
    let calls = 0;
    const model = board({
        saveCanvasPositions: () => {
            calls++;
            throw Error("permission revoked");
        },
    });
    model.startNode(pointer(1, 1), 1);
    await model.end(pointer(1, 1));
    assert.equal(calls, 0);
    model.startNode(pointer(1, 1), 1);
    model.move(pointer(90, 90));
    model.cancelGesture();
    assert.equal(calls, 0);
    assert.equal(model.node(1).x, 100);
    model.startNode(pointer(1, 1), 1);
    await model.end(pointer(90, 90));
    assert.equal(calls, 1);
    assert.equal(model.node(1).x, 100);
    assert.equal(model.node(1).y, 80);
    assert.equal(model.error, true);
});

test("read-only users can navigate while all editing gestures are disabled", async () => {
    const calls = [];
    const model = board(
        {
            saveCanvasPositions: () => calls.push("position"),
            connectSteps: () => calls.push("connect"),
            disconnectSteps: () => calls.push("remove"),
        },
        false,
    );
    model.startNode(pointer(1, 1), 1);
    model.move(pointer(90, 90));
    await model.end(pointer(90, 90));
    await model.nudge(1, 16, 0);
    await model.organize();
    await model.linkNode(1);
    model.selectedEdge = 3;
    await model.disconnect();
    model.zoom(1.2);
    assert.equal(model.scale, 1.2);
    assert.deepEqual(calls, []);
});

test("connections need two different stages and escape cancels the pending link", async () => {
    const calls = [];
    const model = board({
        connectSteps: (...args) => calls.push(args),
        disconnectSteps: (...args) => calls.push(args),
    });
    model.toggleConnect();
    await model.linkNode(1);
    await model.linkNode(1);
    assert.equal(model.sourceId, null);
    assert.equal(calls.length, 0);
    await model.linkNode(1);
    model.escape();
    assert.equal(model.connecting, false);
    model.toggleConnect();
    await model.linkNode(1);
    await model.linkNode(2);
    assert.deepEqual(calls, [[9, 1, 2]]);
    assert.equal(model.connecting, false);
    model.selectedEdge = 3;
    await model.disconnect();
    assert.deepEqual(calls[1], [9, 3]);
});

test("state synchronization preserves the viewport and removes deleted selections", () => {
    const model = board();
    model.panX = -40;
    model.panY = 100;
    model.scale = 0.7;
    model.selectedTopic = 4;
    model.selectedEdge = 3;
    model.sync({
        roadmapId: 9,
        nodes: [{ id: 2, title: "Prática", x: 420, y: -100, topicIds: [] }],
        edges: [],
    });
    assert.equal(model.selectedTopic, null);
    assert.equal(model.selectedEdge, null);
    assert.equal(model.panX, -40);
    assert.equal(model.panY, 100);
    assert.equal(model.scale, 0.7);
    assert.equal(model.node(2).x, 420);
    model.sync({ roadmapId: 100, nodes: [], edges: [] });
    assert.equal(model.nodes.length, 1);
});
