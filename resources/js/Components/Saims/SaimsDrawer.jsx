import Drawer from '@/Components/Drawer';
import SaimsErrorState from '@/Components/Saims/SaimsErrorState';
import { useRef } from 'react';

/**
 * A detail drawer for one SAIMS record. `drawer` is the prop from the server:
 * null when closed, otherwise { id, record, error }. It keeps the last content
 * while it slides out, and shows a friendly error when the record could not load.
 */
export default function SaimsDrawer({ drawer, title, subtitle, onClose, width = 'sm:max-w-lg', children }) {
    const last = useRef(null);

    if (drawer) last.current = drawer;

    const shown = drawer ?? last.current;

    return (
        <Drawer
            open={drawer !== null}
            onClose={onClose}
            title={shown?.record ? title(shown.record) : 'Details'}
            subtitle={shown?.record ? subtitle?.(shown.record) : null}
            width={width}
        >
            {shown?.error && <SaimsErrorState error={shown.error} compact />}
            {shown?.record && children(shown.record)}
        </Drawer>
    );
}
