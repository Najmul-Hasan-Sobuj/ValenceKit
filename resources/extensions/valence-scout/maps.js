(() => {
    const text = (selector) =>
        document.querySelector(selector)?.textContent?.trim() || '';
    const item = (key) => document.querySelector(`[data-item-id="${key}"]`);
    globalThis.valenceScoutMaps = {
        collect() {
            if (
                /consent\.google|sorry\//.test(location.href) ||
                document.querySelector('iframe[src*="recaptcha"]')
            ) {
                throw new Error(
                    'Complete the Google consent or verification in the Maps tab, then try again.',
                );
            }
            const feed = document.querySelector('[role="feed"]');
            const urls = [
                ...new Set(
                    [
                        ...document.querySelectorAll('a[href*="/maps/place/"]'),
                    ].map((a) => a.href),
                ),
            ];
            if (feed) feed.scrollBy(0, feed.clientHeight * 2);
            return { urls, name: text('h1'), url: location.href };
        },
        read() {
            const name = text('h1');
            if (!name || !location.pathname.includes('/maps/place/'))
                throw new Error(
                    'Open a business listing in Google Maps first.',
                );
            const phone = document.querySelector('[data-item-id^="phone:"]');
            const ratingText =
                document
                    .querySelector('[role="img"][aria-label*="stars"]')
                    ?.getAttribute('aria-label') || '';
            const rating = Number(
                ratingText.match(/[\d]+(?:[.,]\d+)?/)?.[0]?.replace(',', '.'),
            );
            const coords = location.href.match(/!3d(-?[\d.]+)!4d(-?[\d.]+)/);
            const address = item('address');
            const website = item('authority')?.href;
            const reviews = [...document.querySelectorAll('button[aria-label]')]
                .map((button) => button.getAttribute('aria-label'))
                .find((label) => /^[\d,]+ reviews?$/.test(label));
            return {
                source: 'google_maps',
                name: name.slice(0, 255),
                maps_url: location.href.slice(0, 2048),
                phone:
                    (
                        phone
                            ?.getAttribute('data-item-id')
                            ?.replace(/^phone:tel:/, '') || ''
                    ).slice(0, 100) || null,
                address:
                    (
                        address
                            ?.getAttribute('aria-label')
                            ?.replace(/^Address:\s*/i, '') ||
                        address?.textContent?.trim() ||
                        ''
                    ).slice(0, 1000) || null,
                website:
                    website && /^https?:/.test(website)
                        ? website.slice(0, 2048)
                        : null,
                category:
                    (
                        document
                            .querySelector('button[jsaction*="category"]')
                            ?.textContent?.trim() || ''
                    ).slice(0, 255) || null,
                rating: rating > 0 && rating <= 5 ? rating : null,
                review_count: reviews
                    ? Number(reviews.replace(/[^\d]/g, ''))
                    : null,
                details: {
                    hours: [...document.querySelectorAll('table tr')]
                        .map((row) =>
                            [...row.querySelectorAll('th, td')]
                                .map((cell) =>
                                    cell.innerText
                                        .replace(/[\uE000-\uF8FF]/g, '')
                                        .trim(),
                                )
                                .filter(Boolean)
                                .join(' · ')
                                .slice(0, 500),
                        )
                        .filter((entry) =>
                            /^(Sunday|Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sun|Mon|Tue|Wed|Thu|Fri|Sat)\b/i.test(
                                entry,
                            ),
                        )
                        .slice(0, 7),
                    ...(coords
                        ? {
                              latitude: Number(coords[1]),
                              longitude: Number(coords[2]),
                          }
                        : {}),
                },
            };
        },
    };
})();
