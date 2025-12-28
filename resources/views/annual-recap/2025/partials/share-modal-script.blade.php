<script src="https://cdn.jsdelivr.net/npm/html-to-image@1.11.11/dist/html-to-image.min.js"></script>
<script>
    function openShareModal() {
        document.getElementById('shareModal').classList.remove('hidden');
        document.getElementById('shareModal').classList.add('flex');
        document.body.style.overflow = 'hidden';
    }

    function closeShareModal() {
        document.getElementById('shareModal').classList.add('hidden');
        document.getElementById('shareModal').classList.remove('flex');
        document.body.style.overflow = '';
    }

    const shareFileName = 'my-{{ $year }}-in-word.png';

    function getShareCardOptions() {
        return {
            width: 1080,
            height: 1920,
            pixelRatio: 1,
            backgroundColor: '#0F1115',
            skipFonts: true
        };
    }

    async function downloadShareCard() {
        const element = document.getElementById('shareCard');
        try {
            const dataUrl = await htmlToImage.toPng(element, getShareCardOptions());

            const link = document.createElement('a');
            link.download = shareFileName;
            link.href = dataUrl;
            link.click();
        } catch (error) {
            console.error('Error generating image:', error);
            alert('Error generating image. Please try again.');
        }
    }

    async function shareShareCard() {
        const element = document.getElementById('shareCard');
        try {
            const blob = await htmlToImage.toBlob(element, getShareCardOptions());
            if (!blob) {
                throw new Error('Share image generation returned empty blob.');
            }

            const file = new File([blob], shareFileName, { type: 'image/png' });
            if (navigator.canShare && navigator.canShare({ files: [file] })) {
                await navigator.share({
                    files: [file],
                    title: 'My {{ $year }} in Word',
                    text: 'Check out my Delight annual recap.'
                });
                return;
            }

            await downloadShareCard();
        } catch (error) {
            if (error?.name === 'AbortError') {
                return;
            }
            console.error('Error sharing image:', error);
            alert('Error sharing image. Please try again.');
        }
    }

    function updateShareButtonVisibility() {
        const shareButton = document.getElementById('shareImageButton');
        if (!shareButton) return;

        if (navigator.share) {
            shareButton.classList.remove('hidden');
        } else {
            shareButton.classList.add('hidden');
        }
    }

    // Close modal on escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeShareModal();
    });

    // Close modal on backdrop click
    document.getElementById('shareModal')?.addEventListener('click', (e) => {
        if (e.target.id === 'shareModal') closeShareModal();
    });

    updateShareButtonVisibility();
</script>
