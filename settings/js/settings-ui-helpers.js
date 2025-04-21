jQuery(document).ready(function($) {
	// --- Remember Active Tab ---
	var savedTab = localStorage.getItem('dmActiveSettingsTab');
	if (savedTab && (savedTab === 'general' || savedTab === 'input' || savedTab === 'output')) {
		$('.nav-tab').removeClass('nav-tab-active');
		$('.tab-content').hide().removeClass('active-tab');
		$('.nav-tab[data-tab="' + savedTab + '"]').addClass('nav-tab-active');
		$('#' + savedTab + '-tab-content').show().addClass('active-tab');
	}
	// --- End Remember Active Tab ---
    
    // --- Tab navigation ---
    $('.nav-tab-wrapper a').on('click', function(e) {
        e.preventDefault();
        var targetTab = $(this).data('tab');

        // Check if target content div exists
        var $targetContent = $('#' + targetTab + '-tab-content');

        if ($targetContent.length === 0) {
            return; // Stop if target doesn't exist
        }

        $('.nav-tab').removeClass('nav-tab-active');
        $('.tab-content').hide().removeClass('active-tab'); // Hide all first
        $(this).addClass('nav-tab-active'); // Activate clicked tab link
        $targetContent.show().addClass('active-tab'); // Show target content

        // Ensure correct sections are shown within the newly active tab
        // Call the function via the shared namespace
        if (window.dmUI && typeof window.dmUI.toggleConfigSections === 'function') {
            window.dmUI.toggleConfigSections();
        } else {
            console.error('Data Machine UI Helper: toggleConfigSections function not found!');
        }

        if (window.localStorage) localStorage.setItem('dmActiveSettingsTab', targetTab);
    });
}); 