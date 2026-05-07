interface AjaxConfig {
    ajax_url: string;
    nonce: string;
}
declare const rsp_ajax: AjaxConfig | undefined;
(async () => {
    const isDebug: boolean = new URLSearchParams(window.location.search).has('rsp_debug');

    const recordVisit = async (): Promise<void> => {
        try {
            const pageUrl: string =
                window.location.origin + window.location.pathname;

            if (!rsp_ajax || !rsp_ajax.ajax_url || !rsp_ajax.nonce) {
                if (isDebug) {
                    console.warn('[RSP]', 'rsp_ajax config is not available');
                }
                return;
            }

            if (isDebug) {
                console.log('[RSP]', 'Recording visit for:', pageUrl);
            }

            const formData: FormData = new FormData();
            formData.append('action', 'rsp_record_visit');
            formData.append('nonce', rsp_ajax.nonce);
            formData.append('page_url', pageUrl);

            const response: Response = await fetch(rsp_ajax.ajax_url, {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                if (isDebug) {
                    console.warn('[RSP]', 'Response not OK:', response.status, response.statusText);
                }
                return;
            }

            const data = await response.json();
            if (isDebug) {
                console.log('[RSP]', 'Response:', data);
            }
        } catch (error) {
            if (isDebug) {
                console.error('[RSP]', 'Error:', error);
            }
            // Silently fail — visit tracking should never disrupt the user experience
        }
    };

    recordVisit();
})();
