globalThis.HabittosCategoryPie = {
    sliceAt(x, y, slices) {
        if (Math.hypot(x, y) > 1) return null;
        // Conic gradients begin at the top and advance clockwise.
        const percentage = ((Math.atan2(x, -y) / (2 * Math.PI) + 1) % 1) * 100;
        return (
            slices.find((slice) => percentage < slice.end) ??
            slices.at(-1) ??
            null
        );
    },

    at(event, slices) {
        const rect = event.currentTarget.getBoundingClientRect();
        if (!rect.width || !rect.height) return null;
        return this.sliceAt(
            ((event.clientX - rect.left) / rect.width) * 2 - 1,
            ((event.clientY - rect.top) / rect.height) * 2 - 1,
            slices,
        );
    },
};
