globalThis.HabittosGoalDescription = {
    create(wire) {
        return {
            range: null,

            init() {
                this.$refs.editor.innerHTML =
                    wire.$get("form.description") ?? "";
            },

            remember() {
                const selection = window.getSelection();
                if (
                    selection.rangeCount &&
                    this.$refs.editor.contains(selection.anchorNode) &&
                    this.$refs.editor.contains(selection.focusNode)
                ) {
                    this.range = selection.getRangeAt(0).cloneRange();
                }
            },

            sync() {
                wire.$set(
                    "form.description",
                    this.$refs.editor.innerHTML,
                    false,
                );
                this.remember();
            },

            format(command, value = null) {
                const editor = this.$refs.editor;
                editor.focus();
                if (
                    this.range &&
                    editor.contains(this.range.commonAncestorContainer)
                ) {
                    const selection = window.getSelection();
                    selection.removeAllRanges();
                    selection.addRange(this.range);
                }
                document.execCommand(command, false, value);
                this.sync();
            },

            paste(event) {
                event.preventDefault();
                this.format(
                    "insertText",
                    event.clipboardData.getData("text/plain"),
                );
            },
        };
    },
};
