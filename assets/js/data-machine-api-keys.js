jQuery(document).ready(function($) {
    function listInstagramAccounts() {
        $.post(dmInstagramAuthParams.ajax_url, {
            action: 'dm_list_instagram_accounts',
            nonce: dmInstagramAuthParams.nonce
        }, function(response) {
            if (response.success) {
                renderInstagramAccounts(response.data.accounts);
            } else {
                $('#instagram-accounts-list').html('<div class="notice notice-error">Failed to load Instagram accounts.</div>');
            }
        });
    }

    function renderInstagramAccounts(accounts) {
        var html = '';
        if (accounts.length === 0) {
            html = '<p>No Instagram accounts authenticated yet.</p>';
        } else {
            html = '<ul>';
            accounts.forEach(function(acct) {
                html += '<li style="margin-bottom: 10px;">' +
                    (acct.profile_pic ? '<img src="' + acct.profile_pic + '" style="width:32px;height:32px;border-radius:50%;vertical-align:middle;margin-right:8px;">' : '') +
                    '<strong>' + acct.username + '</strong> ' +
                    (acct.expires_at ? '<span style="color:#888;">(expires: ' + acct.expires_at + ')</span> ' : '') +
                    '<button class="button button-small instagram-remove-account-btn" data-account-id="' + acct.id + '">Remove</button>' +
                    '</li>';
            });
            html += '</ul>';
        }
        $('#instagram-accounts-list').html(html);
    }

    // Remove account
    $('#instagram-accounts-list').on('click', '.instagram-remove-account-btn', function() {
        var accountId = $(this).data('account-id');
        if (!confirm('Remove this Instagram account?')) return;
        $.post(dmInstagramAuthParams.ajax_url, {
            action: 'dm_remove_instagram_account',
            nonce: dmInstagramAuthParams.nonce,
            account_id: accountId
        }, function(response) {
            if (response.success) {
                renderInstagramAccounts(response.data.accounts);
            } else {
                alert('Failed to remove account.');
            }
        });
    });

    // Authenticate new account (OAuth flow)
    $('#instagram-authenticate-btn').on('click', function() {
        var width = 600, height = 700;
        var left = (screen.width/2)-(width/2);
        var top = (screen.height/2)-(height/2);
        var oauthUrl = window.location.origin + '/oauth-instagram/?start=1';
        var win = window.open(oauthUrl, 'InstagramAuth', 'width=' + width + ',height=' + height + ',top=' + top + ',left=' + left);

        // Poll for completion (simple approach)
        var pollTimer = window.setInterval(function() {
            try {
                if (win.closed) {
                    window.clearInterval(pollTimer);
                    listInstagramAccounts();
                    $('#instagram-auth-feedback').text('Authentication complete. Account added.');
                }
            } catch(e) {}
        }, 1000);
    });

    // Do not fetch accounts on initial load; list is rendered server-side.
});