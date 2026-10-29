const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

const pesos = new Intl.NumberFormat('en-PH', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

const trim = (n) => String(Number(n.toFixed(1)));

/** "Php 4,102,000.00". The only place money is formatted. */
export function formatPhp(amount) {
    return `Php ${pesos.format(amount)}`;
}

/** "Php 710K", "Php 4.1M" for small tiles. */
export function formatPhpCompact(amount) {
    const abs = Math.abs(amount);

    if (abs >= 1e9) return `Php ${trim(amount / 1e9)}B`;
    if (abs >= 1e6) return `Php ${trim(amount / 1e6)}M`;
    if (abs >= 1e3) return `Php ${trim(amount / 1e3)}K`;

    return `Php ${trim(amount)}`;
}

/** "18 Jun 2026" from "2026-06-18". */
export function formatDate(iso) {
    const [year, month, day] = iso.split('-').map(Number);

    return `${day} ${MONTHS[month - 1]} ${year}`;
}

/** "2:40 PM" from "14:40". */
export function formatTime(time) {
    const [hours, minutes] = time.split(':').map(Number);
    const suffix = hours >= 12 ? 'PM' : 'AM';

    return `${hours % 12 || 12}:${String(minutes).padStart(2, '0')} ${suffix}`;
}

/** "+12.4%" / "-3.0%". */
export function formatPercent(value) {
    return `${value > 0 ? '+' : ''}${value.toFixed(1)}%`;
}

export function formatNumber(value) {
    return new Intl.NumberFormat('en-PH').format(value);
}

export function initials(name) {
    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0].toUpperCase())
        .join('');
}

/** "just now", "5 min ago", "3 hours ago", "yesterday", "12 days ago", then a date. */
export function formatRelative(iso, now = new Date()) {
    const then = new Date(iso);
    const seconds = Math.round((now - then) / 1000);

    if (seconds < 45) return 'just now';

    const minutes = Math.round(seconds / 60);
    if (minutes < 60) return `${minutes} min ago`;

    const hours = Math.round(minutes / 60);
    if (hours < 24) return `${hours} ${hours === 1 ? 'hour' : 'hours'} ago`;

    const days = Math.round(hours / 24);
    if (days === 1) return 'yesterday';
    if (days < 30) return `${days} days ago`;

    return formatDate(then.toISOString().slice(0, 10));
}
