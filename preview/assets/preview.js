const motionPreference = window.matchMedia("(prefers-reduced-motion: reduce)");
const previewVideo = document.querySelector("[data-preview-video]");

if (previewVideo instanceof HTMLVideoElement) {
    const syncPreviewVideo = () => {
        if (motionPreference.matches) {
            previewVideo.pause();
            return;
        }

        previewVideo.play().catch(() => {});
    };

    syncPreviewVideo();
    motionPreference.addEventListener("change", syncPreviewVideo);
}

document.addEventListener("keydown", (event) => {
    if (event.key !== "Escape") return;

    document.querySelectorAll("dialog[open]").forEach((dialog) => {
        if (dialog instanceof HTMLDialogElement) dialog.close();
    });
});
