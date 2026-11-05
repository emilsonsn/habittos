globalThis.HabittosGoalKanban = {
    create(
        wire,
        findStatus = (x, y) =>
            document.elementFromPoint(x, y)?.closest("[data-goal-status]")
                ?.dataset.goalStatus ?? null,
    ) {
        return {
            pending: null,
            dragged: null,
            over: null,
            title: "",
            x: 0,
            y: 0,
            lastDragAt: 0,
            start(event, id, title) {
                if (event.button !== 0) return;
                event.preventDefault();
                this.pending = { id, x: event.clientX, y: event.clientY };
                this.title = title;
            },
            move(event) {
                if (!this.pending) return;
                this.x = event.clientX;
                this.y = event.clientY;
                if (
                    Math.hypot(
                        this.x - this.pending.x,
                        this.y - this.pending.y,
                    ) < 8 &&
                    this.dragged === null
                )
                    return;
                this.dragged = this.pending.id;
                this.over = findStatus(this.x, this.y);
            },
            end(event) {
                if (!this.pending) return;
                this.move(event);
                if (this.dragged !== null) {
                    this.lastDragAt = Date.now();
                    if (this.over) wire.changeStatus(this.dragged, this.over);
                }
                this.cancel();
            },
            cancel() {
                this.pending = null;
                this.dragged = null;
                this.over = null;
            },
            open(id) {
                if (Date.now() - this.lastDragAt > 250) wire.openDetails(id);
            },
        };
    },
};
