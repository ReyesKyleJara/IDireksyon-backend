export const OUTPUT_SIZE = 512;
export const MIN_IMAGE_SIZE = 300;
const MAX_SOURCE_SIDE = 2000;
const MAX_PICK_BYTES = 10 * 1024 * 1024;
const MAX_UPLOAD_BYTES = 1.9 * 1024 * 1024;
const MAX_ZOOM = 4;
const ACCEPTED_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
const JPEG_QUALITIES = [0.9, 0.8, 0.65, 0.5];

/** PNG and WebP may have transparency, so they stay PNG. Opaque ones are just as welcome. */
export const outputType = sourceType => (['image/png', 'image/webp'].includes(sourceType) ? 'image/png' : 'image/jpeg');

/** Never enlarges past what the crop really holds, within the sizes the server accepts. */
export const outputSide = cropWidth => Math.max(MIN_IMAGE_SIZE, Math.min(OUTPUT_SIZE, Math.round(cropWidth)));

const fileName = (base, type) => `${base}.${type === 'image/png' ? 'png' : 'jpg'}`;

const canvasToBlob = (canvas, type, quality) => new Promise((resolve, reject) => {
    canvas.toBlob(blob => (blob ? resolve(blob) : reject(new Error('encode'))), type, quality);
});

const loadImage = url => new Promise((resolve, reject) => {
    const image = new Image();
    image.onload = () => resolve(image);
    image.onerror = () => reject(new Error('decode'));
    image.src = url;
});

/** Tries each encoding in turn and keeps the first that fits the upload limit. */
async function firstThatFits(canvas, type) {
    for (const quality of type === 'image/png' ? [undefined] : JPEG_QUALITIES) {
        const blob = await canvasToBlob(canvas, type, quality);
        if (blob.size <= MAX_UPLOAD_BYTES) return blob;
    }
    return null;
}

/** A smaller upright copy of the picked file, kept so the image can be re-cropped later. */
async function makeSource(image, type) {
    const largest = Math.max(image.naturalWidth, image.naturalHeight);
    const smallest = Math.min(image.naturalWidth, image.naturalHeight);
    const sides = type === 'image/png' ? [MAX_SOURCE_SIDE, 1400, 1000, 700] : [MAX_SOURCE_SIDE];
    for (const side of sides) {
        const scale = Math.min(1, side / largest);
        if (smallest * scale < MIN_IMAGE_SIZE) break;
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(image.naturalWidth * scale);
        canvas.height = Math.round(image.naturalHeight * scale);
        const context = canvas.getContext('2d');
        if (type !== 'image/png') {
            context.fillStyle = '#fff';
            context.fillRect(0, 0, canvas.width, canvas.height);
        }
        context.drawImage(image, 0, 0, canvas.width, canvas.height);
        const blob = await firstThatFits(canvas, type);
        if (blob) return blob;
    }
    throw new Error('too-large');
}

/** The confirmed square. Transparent areas stay transparent; only JPEG gets a white fill. */
async function encodeCrop(cropper, type) {
    const side = outputSide(cropper.getData().width);
    const sizes = type === 'image/png' ? [side, 400, MIN_IMAGE_SIZE].filter(size => size <= side) : [side];
    for (const size of new Set(sizes)) {
        const canvas = cropper.getCroppedCanvas({
            width: size,
            height: size,
            imageSmoothingQuality: 'high',
            ...(type === 'image/png' ? {} : { fillColor: '#fff' }),
        });
        const blob = await firstThatFits(canvas, type);
        if (blob) return blob;
    }
    throw new Error('too-large');
}

const TOO_LARGE = 'This picture is too large to save. Please use a smaller or simpler picture.';

function uploadError(error) {
    if (error.message === 'too-large') return TOO_LARGE;
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

export default ({ item = {} } = {}) => {
    // Kept out of Alpine's reactive state: the cropper manages its own DOM.
    let cropper = null;
    let sourceUrl = null;
    let newSource = null;
    let cropType = 'image/jpeg';

    const coverRatio = () => {
        const image = cropper.getImageData();
        return cropper.getCropBoxData().width / Math.min(image.naturalWidth, image.naturalHeight);
    };

    return {
        item,
        step: 'manage',
        error: '',
        busy: false,
        loadingCrop: false,
        progress: 0,
        zoom: 1,
        maxZoom: MAX_ZOOM,

        showDialog() {
            if (!this.$refs.dialog.open) this.$refs.dialog.showModal();
        },

        closeDialog() {
            if (this.$refs.dialog.open) this.$refs.dialog.close();
            this.step = 'manage';
        },

        askRemove() {
            this.error = '';
            this.step = 'remove';
            this.showDialog();
        },

        /** Escape and Cancel: never interrupts a save that is already running. */
        dismiss() {
            if (this.busy) return;
            if (this.step === 'crop') this.cancelCrop();
            else this.closeDialog();
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
                if (Math.min(image.naturalWidth, image.naturalHeight) < MIN_IMAGE_SIZE) {
                    this.error = `That picture is too small. It must be at least ${MIN_IMAGE_SIZE} by ${MIN_IMAGE_SIZE} pixels.`;
                    return;
                }
                const type = outputType(file.type);
                const source = await makeSource(image, type);
                await this.startCropping(source, source, type);
            } catch (error) {
                this.error = error.message === 'too-large' ? TOO_LARGE : 'That picture could not be opened. Please try a different file.';
            } finally {
                URL.revokeObjectURL(url);
            }
        },

        async recrop() {
            this.error = '';
            this.loadingCrop = true;
            try {
                const response = await fetch(this.item.original_url, { credentials: 'same-origin' });
                if (!response.ok) throw new Error('missing');
                const blob = await response.blob();
                await this.startCropping(blob, null, outputType(blob.type));
            } catch {
                this.error = 'The original picture could not be loaded. Please upload it again.';
            } finally {
                this.loadingCrop = false;
            }
        },

        async startCropping(blob, source, type) {
            newSource = source;
            cropType = type;
            sourceUrl = URL.createObjectURL(blob);
            this.step = 'crop';
            this.zoom = 1;
            // The cropper measures its box, so the modal must be open first.
            this.showDialog();
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
            this.closeDialog();
        },

        applyChange(data) {
            Object.assign(this.item, data.item, { broken: false });
            this.$dispatch('notify', { type: 'success', message: data.message });
            this.stopCropping();
            this.closeDialog();
        },

        async saveCrop() {
            if (!cropper || this.busy) return;
            this.busy = true;
            this.error = '';
            this.progress = 0;
            try {
                const form = new FormData();
                form.append('image', await encodeCrop(cropper, cropType), fileName('image', cropType));
                if (newSource) form.append('original', newSource, fileName('original', cropType));
                const { data } = await window.axios.post(this.item.save_url, form, {
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

        async removeImage() {
            if (this.busy) return;
            this.busy = true;
            this.error = '';
            try {
                const { data } = await window.axios.delete(this.item.save_url);
                this.applyChange(data);
            } catch (error) {
                this.error = uploadError(error);
                this.closeDialog();
            } finally {
                this.busy = false;
            }
        },
    };
};
