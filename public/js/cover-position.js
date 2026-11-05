globalThis.HabittosCoverPosition = {
    clamp(value) {
        return Math.round(Math.max(0, Math.min(100, value)));
    },

    overflow(imageWidth, imageHeight, width, height) {
        if (Math.min(imageWidth, imageHeight, width, height) <= 0) {
            return { x: 0, y: 0 };
        }
        const scale = Math.max(width / imageWidth, height / imageHeight);
        return {
            x: Math.max(0, imageWidth * scale - width),
            y: Math.max(0, imageHeight * scale - height),
        };
    },

    drag(startX, startY, deltaX, deltaY, overflow) {
        return {
            x: this.clamp(
                overflow.x > 0.5
                    ? startX - (deltaX / overflow.x) * 100
                    : startX,
            ),
            y: this.clamp(
                overflow.y > 0.5
                    ? startY - (deltaY / overflow.y) * 100
                    : startY,
            ),
        };
    },
};

if (typeof document !== "undefined") {
    document.addEventListener("alpine:init", () => {
        const resizePreview = function () {
            const width = document
                .querySelector(".main")
                .getBoundingClientRect().width;
            const height = parseFloat(
                getComputedStyle(this.$refs.reference).height,
            );
            if (width > 0 && height > 0) this.ratio = width / height;
        };
        Alpine.data("coverPositionPreview", () => ({
            ratio: 6,
            resize: resizePreview,
        }));
        Alpine.data("coverPositionEditor", (wire) => ({
            x: Number(wire.$get("positionX")),
            y: Number(wire.$get("positionY")),
            ratio: 6,
            dragging: false,
            origin: null,

            resize: resizePreview,

            overflow() {
                const image = this.$refs.image;
                const rect = this.$refs.viewport.getBoundingClientRect();
                return HabittosCoverPosition.overflow(
                    image.naturalWidth,
                    image.naturalHeight,
                    rect.width,
                    rect.height,
                );
            },

            start(event) {
                if (event.button !== 0) return;
                this.$refs.viewport.focus();
                this.dragging = true;
                this.origin = {
                    pointer: event.pointerId,
                    clientX: event.clientX,
                    clientY: event.clientY,
                    x: this.x,
                    y: this.y,
                    overflow: this.overflow(),
                };
                event.currentTarget.setPointerCapture(event.pointerId);
            },

            move(event) {
                if (!this.dragging || event.pointerId !== this.origin.pointer)
                    return;
                const position = HabittosCoverPosition.drag(
                    this.origin.x,
                    this.origin.y,
                    event.clientX - this.origin.clientX,
                    event.clientY - this.origin.clientY,
                    this.origin.overflow,
                );
                this.x = position.x;
                this.y = position.y;
            },

            end(event) {
                if (!this.dragging || event.pointerId !== this.origin.pointer)
                    return;
                this.dragging = false;
                if (event.currentTarget.hasPointerCapture(event.pointerId)) {
                    event.currentTarget.releasePointerCapture(event.pointerId);
                }
            },

            nudge(deltaX, deltaY) {
                const overflow = this.overflow();
                if (overflow.x > 0.5)
                    this.x = HabittosCoverPosition.clamp(this.x - deltaX);
                if (overflow.y > 0.5)
                    this.y = HabittosCoverPosition.clamp(this.y - deltaY);
            },

            save() {
                wire.$set("positionX", this.x, false);
                wire.$set("positionY", this.y, false);
                return wire.confirmCoverPosition();
            },
        }));
    });
}
