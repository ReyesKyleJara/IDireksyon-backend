const OUTPUT_SIZE = 800;
const MIN_SOURCE_SIZE = 300;
const MAX_SOURCE_SIDE = 2000;
const MAX_PICK_BYTES = 10 * 1024 * 1024;
const MAX_UPLOAD_BYTES = 1.9 * 1024 * 1024;
const MAX_ZOOM = 4;
const ACCEPTED_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

const canvasToBlob = (canvas, quality) => new Promise((resolve, reject) => {
    canvas.toBlob(blob => (blob ? resolve(blob) : reject(new Error('encode'))), 'image/jpeg', quality);
});

const loadImage = url => new Promise((resolve, reject) => {
    const image = new Image();
    image.onload = () => resolve(image);
    image.onerror = () => reject(new Error('decode'));
    image.src = url;
});

/** A smaller upright JPEG of the picked file, kept so the image can be re-cropped later. */
async function makeSource(image) {
    const scale = Math.min(1, MAX_SOURCE_SIDE / Math.max(image.naturalWidth, image.naturalHeight));
    const canvas = document.createElement('canvas');
    canvas.width = Math.round(image.naturalWidth * scale);
    canvas.height = Math.round(image.naturalHeight * scale);
    const context = canvas.getContext('2d');
    context.fillStyle = '#fff';
    context.fillRect(0, 0, canvas.width, canvas.height);
    context.drawImage(image, 0, 0, canvas.width, canvas.height);
    for (const quality of [0.9, 0.8, 0.65, 0.5]) {
        const blob = await canvasToBlob(canvas, quality);
        if (blob.size <= MAX_UPLOAD_BYTES) return blob;
    }
    throw new Error('too-large');
}

function uploadError(error) {
    const response = error.response;
    if (!response) return 'The connection failed. Check your internet and try again.';
    if (response.status === 419) return 'Your session expired. Reload the page and try again.';
    if (response.status === 403) return 'Your account cannot change images.';
    if (response.status === 413) return 'That image is too large to upload.';
    if (response.status === 422) {
        const errors = Object.values(response.data?.errors ?? {}).flat();
        return errors[0] ?? 'That image could not be accepted.';
    }
    return 'Something went wrong on the server. Your current image was not changed.';
}

export default ({ items = [], open = null } = {}) => {
    // Kept out of Alpine's reactive state: the cropper manages its own DOM.
    let cropper = null;
    let sourceUrl = null;
    let newSource = null;

    const coverRatio = () => {
        const image = cropper.getImageData();
        return cropper.getCropBoxData().width / Math.min(image.naturalWidth, image.naturalHeight);
    };

    return {
        items,
        search: '',
        type: 'all',
        status: 'all',
        active: null,
        step: 'manage',
        error: '',
        busy: false,
        loadingCrop: false,
        progress: 0,
        zoom: 1,
        maxZoom: MAX_ZOOM,

        init() {
            const item = this.items.find(row => row.key === open);
            if (item) this.$nextTick(() => this.open(item));
        },

        get visible() {
            const query = this.search.trim().toLowerCase();
            return this.items.filter(item => (this.type === 'all' || item.type === this.type)
                && (this.status === 'all' || (this.status === 'with') === Boolean(item.image_url))
                && item.name.toLowerCase().includes(query));
        },

        get withImageCount() {
            return this.items.filter(item => item.image_url).length;
        },

        open(item) {
            this.active = item;
            this.step = 'manage';
            this.error = '';
            this.$refs.dialog.showModal();
        },

        close() {
            if (this.busy) return;
            this.stopCropping();
            this.$refs.dialog.close();
            this.active = null;
        },

        pickFile() {
            this.error = '';
            this.$refs.file.value = '';
            this.$refs.file.click();
        },

        async fileChosen(event) {
            const file = event.target.files[0];
            if (!file) return;
            if (!ACCEPTED_TYPES.includes(file.type)) {
                this.error = 'Please choose a JPG, PNG, or WebP picture.';
                return;
            }
            if (file.size > MAX_PICK_BYTES) {
                this.error = 'That picture is larger than 10 MB. Please choose a smaller one.';
                return;
            }
            const url = URL.createObjectURL(file);
            try {
                const image = await loadImage(url);
                if (Math.min(image.naturalWidth, image.naturalHeight) < MIN_SOURCE_SIZE) {
                    this.error = `That picture is too small. It must be at least ${MIN_SOURCE_SIZE} by ${MIN_SOURCE_SIZE} pixels.`;
                    return;
                }
                const source = await makeSource(image);
                await this.startCropping(source, source);
            } catch {
                this.error = 'That picture could not be opened. Please try a different file.';
            } finally {
                URL.revokeObjectURL(url);
            }
        },

        async recrop() {
            this.error = '';
            this.loadingCrop = true;
            try {
                const response = await fetch(this.active.original_url, { credentials: 'same-origin' });
                if (!response.ok) throw new Error('missing');
                await this.startCropping(await response.blob(), null);
            } catch {
                this.error = 'The original picture could not be loaded. Please upload it again.';
            } finally {
                this.loadingCrop = false;
            }
        },

        async startCropping(blob, source) {
            newSource = source;
            sourceUrl = URL.createObjectURL(blob);
            this.step = 'crop';
            this.zoom = 1;
            await this.$nextTick();
            const [{ default: Cropper }] = await Promise.all([
                import('cropperjs'),
                import('cropperjs/dist/cropper.css'),
            ]);
            const image = this.$refs.cropImage;
            image.src = sourceUrl;
            cropper = new Cropper(image, {
                aspectRatio: 1,
                viewMode: 1,
                dragMode: 'move',
                autoCropArea: 0.92,
                cropBoxMovable: false,
                cropBoxResizable: false,
                toggleDragModeOnDblclick: false,
                checkOrientation: false,
                restore: false,
                preview: this.$root.querySelectorAll('[data-crop-preview]'),
                ready: () => this.syncZoom(),
                zoom: event => {
                    const cover = coverRatio();
                    if (event.detail.ratio > cover * MAX_ZOOM) {
                        event.preventDefault();
                    } else if (event.detail.ratio < cover) {
                        event.preventDefault();
                        cropper.zoomTo(cover);
                    } else {
                        this.zoom = event.detail.ratio / cover;
                    }
                },
            });
        },

        stopCropping() {
            cropper?.destroy();
            cropper = null;
            newSource = null;
            if (sourceUrl) URL.revokeObjectURL(sourceUrl);
            sourceUrl = null;
        },

        syncZoom() {
            const canvas = cropper.getCanvasData();
            this.zoom = (canvas.width / canvas.naturalWidth) / coverRatio();
        },

        setZoom(value) {
            const zoom = Math.min(MAX_ZOOM, Math.max(1, Number(value)));
            cropper?.zoomTo(coverRatio() * zoom);
        },

        nudgeZoom(change) {
            this.setZoom(this.zoom + change);
        },

        resetCrop() {
            cropper?.reset();
            this.$nextTick(() => this.syncZoom());
        },

        cancelCrop() {
            if (this.busy) return;
            this.stopCropping();
            this.error = '';
            this.step = 'manage';
        },

        applyChange(data) {
            Object.assign(this.active, data.item);
            this.$dispatch('notify', { type: 'success', message: data.message });
            this.stopCropping();
            this.step = 'manage';
        },

        async saveCrop() {
            if (!cropper || this.busy) return;
            this.busy = true;
            this.error = '';
            this.progress = 0;
            try {
                const canvas = cropper.getCroppedCanvas({
                    width: OUTPUT_SIZE,
                    height: OUTPUT_SIZE,
                    fillColor: '#fff',
                    imageSmoothingQuality: 'high',
                });
                const form = new FormData();
                form.append('image', await canvasToBlob(canvas, 0.9), 'image.jpg');
                if (newSource) form.append('original', newSource, 'original.jpg');
                const { data } = await window.axios.post(this.active.save_url, form, {
                    onUploadProgress: event => {
                        if (event.total) this.progress = Math.round((event.loaded / event.total) * 100);
                    },
                });
                this.applyChange(data);
            } catch (error) {
                this.error = uploadError(error);
            } finally {
                this.busy = false;
            }
        },

        async deleteImage() {
            if (this.busy) return;
            this.busy = true;
            this.error = '';
            try {
                const { data } = await window.axios.delete(this.active.save_url);
                this.applyChange(data);
            } catch (error) {
                this.error = uploadError(error);
                this.step = 'manage';
            } finally {
                this.busy = false;
            }
        },
    };
};
