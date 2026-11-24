globalThis.HabittosStudyCanvas = {
    clamp(value, min, max) {
        return Math.max(min, Math.min(max, value));
    },

    layout(nodes, edges) {
        const byId = new Map(nodes.map((node) => [Number(node.id), node]));
        const incoming = new Map(nodes.map((node) => [Number(node.id), 0]));
        const levels = new Map(nodes.map((node) => [Number(node.id), 0]));
        const outgoing = new Map(nodes.map((node) => [Number(node.id), []]));
        for (const edge of edges) {
            if (
                !byId.has(Number(edge.source)) ||
                !byId.has(Number(edge.target))
            )
                continue;
            incoming.set(
                Number(edge.target),
                incoming.get(Number(edge.target)) + 1,
            );
            outgoing.get(Number(edge.source)).push(Number(edge.target));
        }
        const queue = nodes
            .filter((node) => incoming.get(Number(node.id)) === 0)
            .map((node) => Number(node.id));
        const visited = new Set();
        while (queue.length) {
            const id = queue.shift();
            visited.add(id);
            for (const target of outgoing.get(id)) {
                levels.set(
                    target,
                    Math.max(levels.get(target), levels.get(id) + 1),
                );
                incoming.set(target, incoming.get(target) - 1);
                if (incoming.get(target) === 0) queue.push(target);
            }
        }
        // Cycles are allowed on a free board; unvisited nodes get their own row.
        let lastLevel = Math.max(0, ...levels.values());
        for (const node of nodes) {
            if (!visited.has(Number(node.id)))
                levels.set(Number(node.id), ++lastLevel);
        }
        const rows = new Map();
        for (const node of nodes) {
            const level = levels.get(Number(node.id));
            if (!rows.has(level)) rows.set(level, []);
            rows.get(level).push(node);
        }
        const maxColumns = Math.max(
            1,
            ...Array.from(rows.values(), (row) => row.length),
        );
        const positions = new Map();
        let y = 80;
        for (const [, row] of [...rows.entries()].sort((a, b) => a[0] - b[0])) {
            const offset = ((maxColumns - row.length) * 420) / 2;
            row.forEach((node, column) => {
                positions.set(Number(node.id), {
                    x: 100 + offset + column * 420,
                    y,
                });
            });
            y += Math.max(...row.map((node) => node.height || 220)) + 110;
        }
        return nodes.map((node) => ({
            id: Number(node.id),
            ...positions.get(Number(node.id)),
        }));
    },

    bounds(nodes) {
        if (!nodes.length) return { x: 0, y: 0, width: 800, height: 500 };
        const x = Math.min(...nodes.map((node) => node.x));
        const y = Math.min(...nodes.map((node) => node.y));
        return {
            x,
            y,
            width:
                Math.max(...nodes.map((node) => node.x + (node.width || 320))) -
                x,
            height:
                Math.max(
                    ...nodes.map((node) => node.y + (node.height || 220)),
                ) - y,
        };
    },

    fit(nodes, width, height) {
        const bounds = this.bounds(nodes);
        const scale = this.clamp(
            Math.min(
                (width - 100) / bounds.width,
                (height - 100) / bounds.height,
            ),
            0.2,
            1,
        );
        return {
            scale,
            x: (width - bounds.width * scale) / 2 - bounds.x * scale,
            y: (height - bounds.height * scale) / 2 - bounds.y * scale,
        };
    },

    curve(source, target) {
        const sw = source.width || 320;
        const sh = source.height || 220;
        const tw = target.width || 320;
        const th = target.height || 220;
        const dx = target.x + tw / 2 - (source.x + sw / 2);
        const dy = target.y + th / 2 - (source.y + sh / 2);
        if (Math.abs(dy) >= Math.abs(dx) * 0.65) {
            const direction = dy >= 0 ? 1 : -1;
            const x1 = source.x + sw / 2;
            const y1 = source.y + (direction === 1 ? sh : 0);
            const x2 = target.x + tw / 2;
            const y2 = target.y + (direction === 1 ? 0 : th);
            const bend = Math.max(65, Math.abs(y2 - y1) / 2);
            return `M ${x1} ${y1} C ${x1} ${y1 + bend * direction}, ${x2} ${y2 - bend * direction}, ${x2} ${y2}`;
        }
        const direction = dx >= 0 ? 1 : -1;
        const x1 = source.x + (direction === 1 ? sw : 0);
        const y1 = source.y + sh / 2;
        const x2 = target.x + (direction === 1 ? 0 : tw);
        const y2 = target.y + th / 2;
        const bend = Math.max(65, Math.abs(x2 - x1) / 2);
        return `M ${x1} ${y1} C ${x1 + bend * direction} ${y1}, ${x2 - bend * direction} ${y2}, ${x2} ${y2}`;
    },

    create(wire, initial, editable) {
        const geometry = this;
        return {
            nodes: [],
            edges: [],
            roadmapId: initial.roadmapId,
            editable,
            panX: 0,
            panY: 0,
            scale: 1,
            width: 900,
            height: 600,
            gesture: null,
            busy: false,
            notice: "",
            error: false,
            connecting: false,
            sourceId: null,
            selectedEdge: null,
            selectedTopic: null,
            expanded: false,
            bodyOverflow: null,
            observer: null,
            observed: new Set(),
            initialized: false,
            destroyed: false,

            init() {
                this.sync(initial);
                this.$watch(
                    () => wire.$get("canvasState"),
                    (state) => this.sync(state),
                );
                if (typeof ResizeObserver !== "undefined") {
                    this.observer = new ResizeObserver(() => this.measure());
                    this.observer.observe(this.$refs.viewport);
                }
                this.$nextTick(() => {
                    this.measure();
                    this.fit();
                    this.initialized = true;
                });
            },

            destroy() {
                this.destroyed = true;
                this.setExpanded(false);
                this.observer?.disconnect();
                this.observed.clear();
            },

            sync(state) {
                if (!state || state.roadmapId !== this.roadmapId) return;
                const old = new Map(
                    this.nodes.map((node) => [Number(node.id), node]),
                );
                this.nodes = state.nodes.map((node) => ({
                    ...node,
                    id: Number(node.id),
                    x: node.x ?? old.get(Number(node.id))?.x ?? 100,
                    y: node.y ?? old.get(Number(node.id))?.y ?? 80,
                    automatic: node.x === null || node.y === null,
                    width: 320,
                    height: old.get(Number(node.id))?.height || 220,
                }));
                this.edges = state.edges;
                if (
                    !this.nodes.some((node) =>
                        node.topicIds?.includes(this.selectedTopic),
                    )
                )
                    this.selectedTopic = null;
                if (!this.edges.some((edge) => edge.id === this.selectedEdge))
                    this.selectedEdge = null;
                if (this.sourceId && !this.node(this.sourceId))
                    this.cancelConnection();
                this.autoPositions();
                this.$nextTick?.(() => {
                    if (!this.destroyed) this.measure();
                });
            },

            measure() {
                if (!this.$refs?.viewport) return;
                this.width = this.$refs.viewport.clientWidth;
                this.height = this.$refs.viewport.clientHeight;
                const elements = new Set(
                    this.$refs.viewport.querySelectorAll("[data-study-node]"),
                );
                for (const element of this.observed) {
                    if (!elements.has(element)) {
                        this.observer?.unobserve(element);
                        this.observed.delete(element);
                    }
                }
                for (const element of elements) {
                    const node = this.node(Number(element.dataset.studyNode));
                    if (node) {
                        node.width = element.offsetWidth;
                        node.height = element.offsetHeight;
                    }
                    if (!this.observed.has(element)) {
                        this.observer?.observe(element);
                        this.observed.add(element);
                    }
                }
                this.autoPositions();
            },

            autoPositions() {
                const layout = geometry.layout(this.nodes, this.edges);
                for (const position of layout) {
                    const node = this.node(position.id);
                    if (node.automatic && this.gesture?.id !== node.id) {
                        node.x = position.x;
                        node.y = position.y;
                    }
                }
            },

            node(id) {
                return this.nodes.find((node) => node.id === Number(id));
            },
            style(id) {
                const node = this.node(id);
                return `left:${node?.x ?? 100}px;top:${node?.y ?? 80}px`;
            },
            worldStyle() {
                return `transform:translate(${this.panX}px,${this.panY}px) scale(${this.scale})`;
            },
            gridStyle() {
                return `background-size:${24 * this.scale}px ${24 * this.scale}px;background-position:${this.panX}px ${this.panY}px`;
            },
            edgePath(edge) {
                const source = this.node(edge.source),
                    target = this.node(edge.target);
                return source && target ? geometry.curve(source, target) : "";
            },
            edgeComplete(edge) {
                return !!this.node(edge.source)?.complete;
            },
            fit() {
                if (!this.nodes.length) {
                    this.panX = 0;
                    this.panY = 0;
                    this.scale = 1;
                    return;
                }
                const view = geometry.fit(
                    this.nodes,
                    this.width || 900,
                    this.height || 600,
                );
                this.panX = view.x;
                this.panY = view.y;
                this.scale = view.scale;
            },
            zoom(factor, x = this.width / 2, y = this.height / 2) {
                const scale = geometry.clamp(this.scale * factor, 0.2, 2);
                this.panX = x - ((x - this.panX) / this.scale) * scale;
                this.panY = y - ((y - this.panY) / this.scale) * scale;
                this.scale = scale;
            },
            wheel(event) {
                if (event.ctrlKey || event.metaKey) {
                    const rect = this.$refs.viewport.getBoundingClientRect();
                    this.zoom(
                        event.deltaY < 0 ? 1.12 : 1 / 1.12,
                        event.clientX - rect.left,
                        event.clientY - rect.top,
                    );
                } else {
                    this.panX -= event.deltaX;
                    this.panY -= event.deltaY;
                }
            },
            startPan(event) {
                if (event.button !== 0 && event.button !== 1) return;
                if (
                    event.target.closest(
                        "button,input,select,textarea,a,summary,[data-study-node],[data-canvas-edge],[data-study-inspector]",
                    )
                )
                    return;
                event.preventDefault();
                this.selectedEdge = null;
                this.gesture = {
                    type: "pan",
                    pointer: event.pointerId,
                    x: event.clientX,
                    y: event.clientY,
                    panX: this.panX,
                    panY: this.panY,
                };
                this.$refs.viewport.setPointerCapture?.(event.pointerId);
            },
            startNode(event, id) {
                if (!this.editable || this.busy || event.button !== 0) return;
                if (this.connecting) {
                    this.linkNode(id);
                    return;
                }
                event.preventDefault();
                const node = this.node(id);
                this.gesture = {
                    type: "node",
                    id: Number(id),
                    pointer: event.pointerId,
                    x: event.clientX,
                    y: event.clientY,
                    nodeX: node.x,
                    nodeY: node.y,
                    automatic: node.automatic,
                    moved: false,
                };
                this.$refs.viewport.setPointerCapture?.(event.pointerId);
            },
            move(event) {
                const gesture = this.gesture;
                if (!gesture || gesture.pointer !== event.pointerId) return;
                const dx = event.clientX - gesture.x,
                    dy = event.clientY - gesture.y;
                if (gesture.type === "pan") {
                    this.panX = gesture.panX + dx;
                    this.panY = gesture.panY + dy;
                } else if (Math.hypot(dx, dy) > 4 || gesture.moved) {
                    gesture.moved = true;
                    const node = this.node(gesture.id);
                    if (!node) {
                        this.gesture = null;
                        return;
                    }
                    node.automatic = false;
                    node.x = geometry.clamp(
                        gesture.nodeX + dx / this.scale,
                        -50000,
                        50000,
                    );
                    node.y = geometry.clamp(
                        gesture.nodeY + dy / this.scale,
                        -50000,
                        50000,
                    );
                }
            },
            async end(event) {
                if (!this.gesture || this.gesture.pointer !== event.pointerId)
                    return;
                this.move(event);
                const gesture = this.gesture;
                this.gesture = null;
                if (this.$refs?.viewport.hasPointerCapture?.(event.pointerId))
                    this.$refs.viewport.releasePointerCapture(event.pointerId);
                if (
                    gesture.type !== "node" ||
                    !gesture.moved ||
                    !this.node(gesture.id)
                )
                    return;
                const node = this.node(gesture.id);
                node.x = Math.round(node.x / 8) * 8;
                node.y = Math.round(node.y / 8) * 8;
                await this.persist(
                    [{ id: node.id, x: node.x, y: node.y }],
                    [
                        {
                            id: node.id,
                            x: gesture.nodeX,
                            y: gesture.nodeY,
                            automatic: gesture.automatic,
                        },
                    ],
                );
            },
            cancelGesture() {
                const gesture = this.gesture;
                if (gesture?.type === "node") {
                    const node = this.node(gesture.id);
                    if (node) {
                        node.x = gesture.nodeX;
                        node.y = gesture.nodeY;
                        node.automatic = gesture.automatic;
                    }
                }
                this.gesture = null;
            },
            async nudge(id, dx, dy) {
                if (!this.editable || this.busy) return;
                const node = this.node(id);
                if (!node) return;
                const previous = [
                    {
                        id: node.id,
                        x: node.x,
                        y: node.y,
                        automatic: node.automatic,
                    },
                ];
                node.x = geometry.clamp(node.x + dx, -50000, 50000);
                node.y = geometry.clamp(node.y + dy, -50000, 50000);
                node.automatic = false;
                await this.persist(
                    [{ id: node.id, x: node.x, y: node.y }],
                    previous,
                );
            },
            async persist(positions, previous) {
                this.busy = true;
                this.notice = "Salvando posições…";
                this.error = false;
                try {
                    await wire.saveCanvasPositions(this.roadmapId, positions);
                    this.notice = "Posições salvas";
                } catch {
                    for (const old of previous) {
                        const node = this.node(old.id);
                        if (node) Object.assign(node, old);
                    }
                    this.notice =
                        "Não foi possível salvar. As posições anteriores foram restauradas.";
                    this.error = true;
                } finally {
                    this.busy = false;
                }
            },
            async organize() {
                if (!this.editable || this.busy || !this.nodes.length) return;
                const previous = this.nodes.map(({ id, x, y, automatic }) => ({
                    id,
                    x,
                    y,
                    automatic,
                }));
                const positions = geometry.layout(this.nodes, this.edges);
                positions.forEach((position) =>
                    Object.assign(this.node(position.id), position, {
                        automatic: false,
                    }),
                );
                this.fit();
                await this.persist(positions, previous);
            },
            toggleConnect() {
                if (!this.editable || this.busy) return;
                this.connecting = !this.connecting;
                this.sourceId = null;
                this.selectedEdge = null;
                this.selectedTopic = null;
            },
            cancelConnection() {
                this.connecting = false;
                this.sourceId = null;
            },
            async linkNode(id) {
                if (!this.editable || this.busy) return;
                this.connecting = true;
                this.selectedTopic = null;
                this.selectedEdge = null;
                if (this.sourceId === null) {
                    this.sourceId = Number(id);
                    return;
                }
                if (this.sourceId === Number(id)) {
                    this.sourceId = null;
                    return;
                }
                this.busy = true;
                this.notice = "Salvando conexão…";
                this.error = false;
                try {
                    await wire.connectSteps(
                        this.roadmapId,
                        this.sourceId,
                        Number(id),
                    );
                    this.notice = "Conexão salva";
                    this.cancelConnection();
                } catch {
                    this.notice = "Não foi possível conectar essas etapas.";
                    this.error = true;
                } finally {
                    this.busy = false;
                }
            },
            async disconnect() {
                if (!this.editable || this.busy || this.selectedEdge === null)
                    return;
                this.busy = true;
                this.error = false;
                try {
                    await wire.disconnectSteps(
                        this.roadmapId,
                        this.selectedEdge,
                    );
                    this.selectedEdge = null;
                    this.notice = "Conexão removida";
                } catch {
                    this.notice = "Não foi possível remover a conexão.";
                    this.error = true;
                } finally {
                    this.busy = false;
                }
            },
            selectTopic(id) {
                if (!this.connecting) {
                    this.selectedTopic = Number(id);
                    this.selectedEdge = null;
                }
            },
            escape() {
                this.cancelGesture();
                if (this.connecting) this.cancelConnection();
                else if (this.selectedTopic !== null) this.selectedTopic = null;
                else if (this.selectedEdge !== null) this.selectedEdge = null;
                else this.setExpanded(false);
            },
            setExpanded(value) {
                this.expanded = value;
                if (typeof document === "undefined") return;
                if (value) {
                    if (this.bodyOverflow === null)
                        this.bodyOverflow = document.body.style.overflow;
                    document.body.style.overflow = "hidden";
                } else if (this.bodyOverflow !== null) {
                    document.body.style.overflow = this.bodyOverflow;
                    this.bodyOverflow = null;
                }
            },
            toggleExpanded() {
                this.setExpanded(!this.expanded);
                this.$nextTick?.(() => {
                    this.measure();
                    this.fit();
                });
            },
        };
    },
};
