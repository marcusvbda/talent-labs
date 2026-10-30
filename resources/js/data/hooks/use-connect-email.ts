import { useMutation, useQueryClient } from '@tanstack/react-query';
import { apiFetch } from '@/data/api';
import { setDevState } from '@/data/fixtures/dev-state';
import { keys } from '@/data/keys';
import { fromSource } from '@/data/source';
import { disconnect } from '@/routes/integrations/oauth';

/**
 * Starts the email account connection. Against the real API it leaves for the OAuth
 * route `href`; with fixtures it flips the dev-state account to connected.
 */
export function useConnectEmail() {
    const queryClient = useQueryClient();
    const real = async (href: string) => {
        window.location.assign(href);
    };
    const fixture = async (_href: string) => {
        setDevState({ gmail: 'connected' });
    };

    return useMutation<void, Error, string>({
        mutationFn: fromSource({ real, fixture }),
        onSuccess: () =>
            queryClient.invalidateQueries({ queryKey: keys.account.status() }),
    });
}

/**
 * Disconnects the email account. Against the real API it calls the OAuth disconnect
 * route; with fixtures it flips the dev-state account to disconnected.
 */
export function useDisconnectEmail(providerKey: string) {
    const queryClient = useQueryClient();
    const real = async () => {
        const e = disconnect(providerKey);

        await apiFetch<void>(e.url, { method: e.method });
    };
    const fixture = async () => {
        setDevState({ gmail: 'disconnected' });
    };

    return useMutation<void, Error, void>({
        mutationFn: fromSource({ real, fixture }),
        onSuccess: () =>
            queryClient.invalidateQueries({ queryKey: keys.account.status() }),
    });
}
