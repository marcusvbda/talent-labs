import { FileText, Upload } from 'lucide-react';
import { useId, useRef, useState } from 'react';
import type { DragEvent, KeyboardEvent } from 'react';
import { ProgressBar } from '@/components/ui/progress-bar';
import { useT } from '@/i18n/i18n-provider';
import { cn } from '@/lib/utils';

type FileDropState = 'idle' | 'dragging' | 'uploading' | 'error';

const STATES: Record<FileDropState, string> = {
    idle: 'border-hairline bg-card hover:bg-tile',
    dragging: 'border-accent bg-accent-soft',
    uploading: 'border-hairline bg-card',
    error: 'border-danger bg-danger-bg',
};

export function FileDrop({
    accept,
    maxSizeMb,
    progress,
    error,
    onFile,
    className,
}: {
    accept: 'application/pdf';
    maxSizeMb: number;
    progress?: number;
    error?: string;
    onFile: (file: File) => void;
    className?: string;
}) {
    const { t } = useT();
    const inputRef = useRef<HTMLInputElement>(null);
    const errorId = useId();
    const [dragging, setDragging] = useState(false);
    const [localError, setLocalError] = useState<string | null>(null);

    const uploading = progress !== undefined;
    const shownError = error ?? localError;
    const state: FileDropState = uploading
        ? 'uploading'
        : shownError
          ? 'error'
          : dragging
            ? 'dragging'
            : 'idle';

    const open = () => {
        if (!uploading) {
            inputRef.current?.click();
        }
    };

    const handle = (file: File | undefined) => {
        if (!file || uploading) {
            return;
        }

        if (file.type !== accept) {
            setLocalError(t('forms.file.wrong_type'));

            return;
        }

        if (file.size > maxSizeMb * 1024 * 1024) {
            setLocalError(t('forms.file.too_large', { size: maxSizeMb }));

            return;
        }

        setLocalError(null);
        onFile(file);
    };

    const onDragOver = (event: DragEvent) => {
        event.preventDefault();

        if (!uploading) {
            setDragging(true);
        }
    };

    const onDrop = (event: DragEvent) => {
        event.preventDefault();
        setDragging(false);
        handle(event.dataTransfer.files[0]);
    };

    const onKeyDown = (event: KeyboardEvent) => {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            open();
        }
    };

    const Icon = uploading ? FileText : Upload;

    return (
        <div className={cn('flex flex-col gap-2', className)}>
            <div
                role="button"
                tabIndex={uploading ? -1 : 0}
                aria-disabled={uploading}
                aria-invalid={state === 'error'}
                aria-describedby={shownError ? errorId : undefined}
                onClick={open}
                onKeyDown={onKeyDown}
                onDragOver={onDragOver}
                onDragLeave={() => setDragging(false)}
                onDrop={onDrop}
                className={cn(
                    'flex flex-col items-center gap-3 rounded-card-sm border-2 border-dashed p-card-sm text-center transition-colors focus-visible:focus-ring',
                    uploading ? 'cursor-default' : 'cursor-pointer',
                    STATES[state],
                )}
            >
                <Icon
                    size={20}
                    strokeWidth={1.8}
                    aria-hidden="true"
                    className="text-muted"
                />
                <p className="text-body text-ink">{t('forms.file.drop')}</p>
                {uploading ? (
                    <ProgressBar
                        value={progress}
                        label={t('forms.file.drop')}
                        className="max-w-xs"
                    />
                ) : null}
                <input
                    ref={inputRef}
                    type="file"
                    accept={accept}
                    tabIndex={-1}
                    className="sr-only"
                    onClick={(event) => event.stopPropagation()}
                    onChange={(event) => {
                        handle(event.target.files?.[0]);
                        event.target.value = '';
                    }}
                />
            </div>
            {shownError ? (
                <p
                    id={errorId}
                    role="alert"
                    className="text-body text-danger-text"
                >
                    {shownError}
                </p>
            ) : null}
        </div>
    );
}
