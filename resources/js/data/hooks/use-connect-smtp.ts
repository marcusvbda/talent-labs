import { useMutation, useQueryClient } from '@tanstack/react-query';
import { apiFetch } from '@/data/api';
import { setDevState } from '@/data/fixtures/dev-state';
import { keys } from '@/data/keys';
import { fromSource } from '@/data/source';
import smtp from '@/routes/integrations/smtp';
import type { AccountStatus } from '@/types/contracts';

export type SmtpPayload = {
    preset: string;
    host: string;
    port: number;
    security: 'tls' | 'starttls';
    username: string;
    password: string;
    from_address: string;
    display_name: string;
};

/**
 * Tests and stores an SMTP account. A 422 surfaces as an `ApiError` whose `errors`
 * are keyed by field (`connection` holds the generic connection failure). With
 * fixtures it flips the dev-state account to a connected SMTP account; the password
 * is never kept.
 */
export function useConnectSmtp() {
    const queryClient = useQueryClient();
    const real = async (payload: SmtpPayload) => {
        const e = smtp.connect();

        await apiFetch<AccountStatus>(e.url, {
            method: e.method,
            body: payload,
        });
    };
    const fixture = async (payload: SmtpPayload) => {
        setDevState({
            gmail: 'connected',
            smtp: {
                preset: payload.preset,
                host: payload.host,
                port: payload.port,
                security: payload.security,
                fromAddress: payload.from_address,
                displayName: payload.display_name,
            },
        });
    };

    return useMutation<void, Error, SmtpPayload>({
        mutationFn: fromSource({ real, fixture }),
        // Evict on unmount so the password in the variables does not linger in the mutation cache.
        gcTime: 0,
        onSuccess: () =>
            queryClient.invalidateQueries({ queryKey: keys.account.status() }),
    });
}
