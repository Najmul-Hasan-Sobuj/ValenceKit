import { onMounted, onUnmounted } from 'vue';

export function useScrollReveal(selector = '.reveal-on-scroll') {
    let observer: IntersectionObserver | null = null;

    onMounted(() => {
        if (typeof window === 'undefined' || !('IntersectionObserver' in window)) {
            document.querySelectorAll(selector).forEach((el) => el.classList.add('is-revealed'));
            return;
        }

        observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-revealed');
                        observer?.unobserve(entry.target);
                    }
                });
            },
            { threshold: 0.1, rootMargin: '0px 0px -40px 0px' },
        );

        document.querySelectorAll(selector).forEach((el) => observer?.observe(el));
    });

    onUnmounted(() => {
        observer?.disconnect();
    });
}
