import Modal from '@/Components/Modal';
import { Button } from '@/Components/Ui';

/**
 * Confirmation dialog for deleting one lead or the selected ones.
 * target: { lead } for a single lead, or { count } for a bulk delete.
 */
export default function ConfirmDelete({ target, processing, onCancel, onConfirm }) {
    const bulk = target && target.count !== undefined;

    return (
        <Modal show={target !== null} onClose={onCancel} maxWidth="md">
            <div className="p-6">
                <h2 className="text-lg font-bold text-gray-900">
                    {bulk ? `Delete ${target.count} ${target.count === 1 ? 'lead' : 'leads'}?` : 'Delete this lead?'}
                </h2>
                <p className="mt-2 text-sm text-gray-600">
                    {bulk
                        ? 'The selected leads will be removed from the list.'
                        : `${target?.lead?.name ?? 'This lead'} will be removed from the list.`}{' '}
                    This cannot be undone from the app.
                </p>

                <div className="mt-6 flex justify-end gap-3">
                    <Button variant="secondary" onClick={onCancel}>
                        Cancel
                    </Button>
                    <Button variant="destructive" onClick={onConfirm} disabled={processing}>
                        {processing ? 'Deleting…' : 'Delete'}
                    </Button>
                </div>
            </div>
        </Modal>
    );
}
