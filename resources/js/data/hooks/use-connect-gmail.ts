import { useMutation, useQueryClient } from '@tanstack/react-query';
import { setDevState } from '@/data/fixtures/dev-state';
import { keys } from '@/data/keys';
import { fromSource } from '@/data/source';

/**
 * Starts the Gmail connection. Against the real API it leaves for the OAuth
 * route `href`; with fixtures it flips the dev-state Gmail to connected.
 */
export function useConnectGmail() {
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
