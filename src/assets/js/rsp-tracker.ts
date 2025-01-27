interface AjaxConfig {
    ajax_url: string;
    nonce: string;
}
declare const rsp_ajax: AjaxConfig;
(async () => {
    const recordVisit = async (): Promise<void> => {
        try {
            const pageUrl: string =
                window.location.origin + window.location.pathname;

            if (!rsp_ajax || !rsp_ajax.ajax_url || !rsp_ajax.nonce) {
                console.error('Ajax configuration is missing.');
                return;
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
                console.error('Failed to send visit data.');
                return;
            }

            const result = await response.json();

            if (!result.success) {
                console.error('Error from server:', result.data);
            } else {
                console.log('Visit recorded successfully.');
            }
        } catch (error) {
            console.error(
                'An error occurred while recording the visit:',
                error
            );
        }
    };

    // Ensure the DOM is fully loaded before executing the script
    document.addEventListener('DOMContentLoaded', () => {
        recordVisit();
    });
})();
