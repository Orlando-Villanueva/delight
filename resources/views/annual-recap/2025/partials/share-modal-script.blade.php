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

    async function downloadShareCard() {
        const element = document.getElementById('shareCard');
        try {
            const dataUrl = await htmlToImage.toPng(element, {
                width: 1080,
                height: 1920,
                pixelRatio: 1,
                backgroundColor: '#0F1115'
            });

            const link = document.createElement('a');
            link.download = 'my-{{ $year }}-in-word.png';
            link.href = dataUrl;
            link.click();
        } catch (error) {
            console.error('Error generating image:', error);
            alert('Error generating image. Please try again.');
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
</script>
