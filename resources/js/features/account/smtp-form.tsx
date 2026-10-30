import { useState } from 'react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Modal } from '@/components/ui/modal';
import { Select } from '@/components/ui/select';
import { ApiError } from '@/data/api';
import { useConnectSmtp } from '@/data/hooks/use-connect-smtp';
import { useT } from '@/i18n/i18n-provider';
import type { AccountStatus } from '@/types/contracts';

type Defaults = AccountStatus['sendingAccount']['smtpDefaults'];
type Security = 'tls' | 'starttls';

const PRESETS: Record<
    string,
    { host: string; port: number; security: Security }
> = {
    yahoo: { host: 'smtp.mail.yahoo.com', port: 465, security: 'tls' },
    icloud: { host: 'smtp.mail.me.com', port: 587, security: 'starttls' },
    zoho: { host: 'smtp.zoho.com', port: 465, security: 'tls' },
    fastmail: { host: 'smtp.fastmail.com', port: 465, security: 'tls' },
};

const PRESET_KEYS = [...Object.keys(PRESETS), 'custom'];

const securityForPort = (port: number): Security =>
    port === 587 ? 'starttls' : 'tls';

const SmtpFields = ({
    defaults,
    onDone,
}: {
    defaults: Defaults;
    onDone: () => void;
}) => {
    const { t } = useT();
    const connect = useConnectSmtp();
    const [preset, setPreset] = useState(defaults?.preset ?? 'yahoo');
    const [host, setHost] = useState(defaults?.host ?? PRESETS.yahoo.host);
    const [port, setPort] = useState<465 | 587>(
        defaults?.port === 587 ? 587 : 465,
    );
    const [security, setSecurity] = useState<Security>(
        defaults?.security ?? PRESETS.yahoo.security,
    );
    const [username, setUsername] = useState('');
    const [password, setPassword] = useState('');
    const [fromAddress, setFromAddress] = useState(defaults?.fromAddress ?? '');
    const [displayName, setDisplayName] = useState(defaults?.displayName ?? '');

    const locked = preset !== 'custom';
    const errors =
        connect.error instanceof ApiError ? connect.error.errors : {};
    const fieldError = (name: string) => errors?.[name]?.[0];
    const formError =
        connect.error === null
            ? undefined
            : (fieldError('connection') ??
              (connect.error instanceof ApiError && !connect.error.errors
                  ? connect.error.message
                  : undefined));

    const onPreset = (key: string) => {
        setPreset(key);

        const found = PRESETS[key];

        if (found) {
            setHost(found.host);
            setPort(found.port === 587 ? 587 : 465);
            setSecurity(found.security);
        }
    };

    const onPort = (value: 465 | 587) => {
        setPort(value);
        setSecurity(securityForPort(value));
    };

    const onSecurity = (value: Security) => {
        setSecurity(value);
        setPort(value === 'starttls' ? 587 : 465);
    };

    const onSubmit = (event: FormEvent) => {
        event.preventDefault();
        connect.mutate(
            {
                preset,
                host,
                port,
                security,
                username,
                password,
                from_address: fromAddress,
                display_name: displayName,
            },
            { onSuccess: onDone },
        );
    };

    return (
        <form
            onSubmit={onSubmit}
            autoComplete="off"
            className="grid items-start gap-4 sm:grid-cols-2"
        >
            <p className="rounded-tile bg-tile p-card-sm text-body text-muted sm:col-span-2">
                {t('integrations.smtp.sent_notice')}
            </p>
            {formError && (
                <p
                    role="alert"
                    className="rounded-tile bg-danger-bg p-card-sm text-body text-danger-text sm:col-span-2"
                >
                    {formError}
                </p>
            )}
            <Field
                label={t('integrations.smtp.preset')}
                error={fieldError('preset')}
            >
                {(control) => (
                    <Select
                        {...control}
                        value={preset}
                        onChange={onPreset}
                        options={PRESET_KEYS.map((key) => ({
                            value: key,
                            label: t(`integrations.smtp.preset_${key}`),
                        }))}
                    />
                )}
            </Field>
            <Field
                label={t('integrations.smtp.host')}
                error={fieldError('host')}
            >
                {(control) => (
                    <Input
                        {...control}
                        value={host}
                        disabled={locked}
                        onChange={(e) => setHost(e.target.value)}
                    />
                )}
            </Field>
            <Field
                label={t('integrations.smtp.port')}
                error={fieldError('port')}
            >
                {(control) => (
                    <Select<465 | 587>
                        {...control}
                        value={port}
                        disabled={locked}
                        onChange={onPort}
                        options={[
                            { value: 465, label: '465' },
                            { value: 587, label: '587' },
                        ]}
                    />
                )}
            </Field>
            <Field
                label={t('integrations.smtp.security')}
                error={fieldError('security')}
            >
                {(control) => (
                    <Select<Security>
                        {...control}
                        value={security}
                        disabled={locked}
                        onChange={onSecurity}
                        options={[
                            {
                                value: 'tls',
                                label: t('integrations.smtp.security_tls'),
                            },
                            {
                                value: 'starttls',
                                label: t('integrations.smtp.security_starttls'),
                            },
                        ]}
                    />
                )}
            </Field>
            <Field
                label={t('integrations.smtp.username')}
                error={fieldError('username')}
            >
                {(control) => (
                    <Input
                        {...control}
                        type="email"
                        autoComplete="off"
                        value={username}
                        onChange={(e) => setUsername(e.target.value)}
                    />
                )}
            </Field>
            <Field
                label={t('integrations.smtp.password')}
                hint={t('integrations.smtp.password_help')}
                error={fieldError('password')}
            >
                {(control) => (
                    <Input
                        {...control}
                        type="password"
                        autoComplete="new-password"
                        value={password}
                        onChange={(e) => setPassword(e.target.value)}
                    />
                )}
            </Field>
            <Field
                label={t('integrations.smtp.from_address')}
                error={fieldError('from_address')}
            >
                {(control) => (
                    <Input
                        {...control}
                        type="email"
                        autoComplete="off"
                        value={fromAddress}
                        onChange={(e) => setFromAddress(e.target.value)}
                    />
                )}
            </Field>
            <Field
                label={t('integrations.smtp.display_name')}
                error={fieldError('display_name')}
            >
                {(control) => (
                    <Input
                        {...control}
                        autoComplete="off"
                        value={displayName}
                        onChange={(e) => setDisplayName(e.target.value)}
                    />
                )}
            </Field>
            <div className="sticky bottom-0 flex justify-end bg-card pt-2 sm:col-span-2">
                <Button type="submit" loading={connect.isPending}>
                    {t('integrations.smtp.submit')}
                </Button>
            </div>
        </form>
    );
};

export const SmtpForm = ({
    open,
    onClose,
    defaults = null,
}: {
    open: boolean;
    onClose: () => void;
    defaults?: Defaults;
}) => {
    const { t } = useT();

    return (
        <Modal
            open={open}
            onClose={onClose}
            size="lg"
            title={t('integrations.smtp.label')}
            description={t('integrations.smtp.description')}
        >
            {open && <SmtpFields defaults={defaults} onDone={onClose} />}
        </Modal>
    );
};
