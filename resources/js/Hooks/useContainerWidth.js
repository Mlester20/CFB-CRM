import { useEffect, useRef, useState } from 'react';

/**
 * Tracks the pixel width of an element so SVG charts can draw at their real
 * size (crisp text, no squashed labels on small screens).
 */
export default function useContainerWidth(initial = 320) {
    const ref = useRef(null);
    const [width, setWidth] = useState(initial);

    useEffect(() => {
        const element = ref.current;

        if (!element) {
            return undefined;
        }

        setWidth(element.clientWidth || initial);

        if (typeof ResizeObserver === 'undefined') {
            return undefined;
        }

        const observer = new ResizeObserver(([entry]) => {
            setWidth(Math.round(entry.contentRect.width) || initial);
        });

        observer.observe(element);

        return () => observer.disconnect();
    }, [initial]);

    return [ref, width];
}
