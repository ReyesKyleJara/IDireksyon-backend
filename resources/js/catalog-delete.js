export default ({ url = '' } = {}) => ({
    loading: false,
    failed: false,
    impact: null,

    async open() {
        this.loading = true;
        this.failed = false;
        this.impact = null;
        this.$refs.dialog.showModal();
        try {
            const { data } = await window.axios.get(url);
            this.impact = data.data;
        } catch {
            this.failed = true;
        } finally {
            this.loading = false;
        }
    },

    close() {
        this.$refs.dialog.close();
    },
});
