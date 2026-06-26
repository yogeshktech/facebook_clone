const MAX_IMAGE_MB = 5;
const TARGET_IMAGE_MB = 1.2;
const MAX_DIMENSION = 1920;

window.compressImageFile = async function (file, options = {}) {
    const maxBytes = (options.maxSizeMB ?? MAX_IMAGE_MB) * 1024 * 1024;
    const targetBytes = (options.targetSizeMB ?? TARGET_IMAGE_MB) * 1024 * 1024;
    const maxWidth = options.maxWidth ?? MAX_DIMENSION;
    const maxHeight = options.maxHeight ?? MAX_DIMENSION;

    if (!file?.type?.startsWith('image/') || file.type === 'image/gif') {
        return file;
    }

    if (file.size > maxBytes) {
        throw new Error(`Photo must be under ${options.maxSizeMB ?? MAX_IMAGE_MB}MB.`);
    }

    if (file.size <= targetBytes) {
        return file;
    }

    const bitmap = await createImageBitmap(file);
    let width = bitmap.width;
    let height = bitmap.height;
    const ratio = Math.min(maxWidth / width, maxHeight / height, 1);
    width = Math.round(width * ratio);
    height = Math.round(height * ratio);

    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    canvas.getContext('2d').drawImage(bitmap, 0, 0, width, height);
    bitmap.close();

    let quality = 0.85;
    let blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', quality));

    while (blob && blob.size > targetBytes && quality > 0.45) {
        quality -= 0.08;
        blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', quality));
    }

    if (!blob) {
        return file;
    }

    const name = file.name.replace(/\.[^.]+$/, '') + '.jpg';

    return new File([blob], name, { type: 'image/jpeg', lastModified: Date.now() });
};

window.replaceInputFile = function (input, file) {
    const dataTransfer = new DataTransfer();
    dataTransfer.items.add(file);
    input.files = dataTransfer.files;
};

window.prepareMediaFile = async function (file) {
    if (!file) {
        return null;
    }

    if (file.type.startsWith('image/')) {
        return window.compressImageFile(file);
    }

    if (file.size > 50 * 1024 * 1024) {
        throw new Error('Video must be under 50MB.');
    }

    return file;
};

window.compressAndSubmitForm = async function (input) {
    const file = input.files?.[0];
    const form = input.form;
    if (!file || !form) {
        return;
    }

    const originalOnChange = input.getAttribute('onchange');
    input.removeAttribute('onchange');

    try {
        const prepared = await window.prepareMediaFile(file);
        if (prepared) {
            window.replaceInputFile(input, prepared);
        }
        form.submit();
    } catch (error) {
        alert(error.message || 'Could not upload this file.');
        input.value = '';
    } finally {
        if (originalOnChange) {
            input.setAttribute('onchange', originalOnChange);
        }
    }
};
