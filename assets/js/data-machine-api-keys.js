/**
 * Data Machine Instagram API Key Management Script.
 *
 * Handles listing, authenticating (via OAuth popup), and removing Instagram accounts
 * associated with the current user for use within the Data Machine plugin.
 * It interacts with WordPress AJAX handlers defined in Data_Machine_Ajax_Instagram_Auth.
 *
 * Key Functions:
 * - listInstagramAccounts: Fetches accounts via AJAX.
 * - renderInstagramAccounts: Displays the list of accounts using jQuery.
 * - Event listener for `#instagram-authenticate-btn`: Initiates the OAuth popup flow.
 * - Event listener for `.instagram-remove-account-btn`: Removes an account via AJAX.
 *
 * @since NEXT_VERSION
 */
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
        var $list = $('<ul></ul>');
        if (accounts.length === 0) {
            $('#instagram-accounts-list').html('<p>No Instagram accounts authenticated yet.</p>');
            return;
        }

        accounts.forEach(function(acct) {
            var $listItem = $('<li>').css('margin-bottom', '10px');

            if (acct.profile_pic) {
                $('<img>').attr({
                    src: acct.profile_pic,
                    style: 'width:32px;height:32px;border-radius:50%;vertical-align:middle;margin-right:8px;'
                }).appendTo($listItem);
            }

            $('<strong>').text(acct.username).appendTo($listItem);

            if (acct.expires_at) {
                $('<span>').text(' (expires: ' + acct.expires_at + ') ').css('color', '#888').appendTo($listItem);
            }

            $('<button>').addClass('button button-small instagram-remove-account-btn')
                .attr('data-account-id', acct.id)
                .text('Remove')
                .appendTo($listItem);

            $listItem.appendTo($list);
        });

        $('#instagram-accounts-list').empty().append($list);
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