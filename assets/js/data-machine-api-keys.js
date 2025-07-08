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

    // --- OAuth Popup Helper ---
    function openOAuthPopup(url, windowName, width, height) {
        var left = (screen.width / 2) - (width / 2);
        var top = (screen.height / 2) - (height / 2);
        var popup = window.open(url, windowName, 'width=' + width + ',height=' + height + ',top=' + top + ',left=' + left);
        var timer = setInterval(function() {
            if (popup.closed) {
                clearInterval(timer);
                $('#instagram-auth-feedback').text('Authentication window closed. Please refresh if needed.');
                $('#reddit-auth-feedback').text('Authentication window closed. Please refresh if needed.');
                $('#twitter-auth-feedback').text('Authentication window closed. Please refresh if needed.');
            }
        }, 1000);
        return popup;
    }

    // --- Reddit Authentication ---
    $('#reddit-authenticate-btn').on('click', function() {
        var clientId = $('#reddit_oauth_client_id').val();
        var clientSecret = $('#reddit_oauth_client_secret').val();
        if (!clientId || !clientSecret) {
            alert('Please save your Reddit Client ID and Secret first.');
            return;
        }
        var redditInitUrl = dmApiKeysParams.reddit_oauth_url; // Localized URL
        redditInitUrl += '&_wpnonce=' + dmApiKeysParams.reddit_oauth_nonce;
        openOAuthPopup(redditInitUrl, 'redditOAuth', 600, 700);
    });

    // --- Twitter Authentication ---
    $('#twitter-authenticate-btn').on('click', function() {
        var button = $(this);
        button.prop('disabled', true);
        $('#twitter-auth-feedback').text('Initiating authentication...');
        $.post(dmApiKeysParams.ajax_url, { action: 'dm_generate_nonce', id: 'dm_twitter_oauth_init_nonce' }, function(response) {
            if (response.success && response.data.nonce) {
                var nonce = response.data.nonce;
                var authUrl = dmApiKeysParams.twitter_oauth_url + '&_wpnonce=' + nonce;
                $('#twitter-auth-feedback').text('Redirecting to Twitter...');
                openOAuthPopup(authUrl, 'twitterAuth', 600, 700);
            } else {
                $('#twitter-auth-feedback').text('Error: Could not generate security token.').css('color', 'red');
                button.prop('disabled', false);
            }
        }).fail(function() {
            $('#twitter-auth-feedback').text('Error: AJAX request failed.').css('color', 'red');
            button.prop('disabled', false);
        });
    });

    // --- Remove Reddit Account ---
    $('#reddit-accounts-list').on('click', '.reddit-remove-account-btn', function(e) {
        e.preventDefault();
        if (!confirm('Are you sure you want to remove this Reddit account authorization? This will remove stored tokens.')) return;
        var button = $(this);
        var accountUsername = button.data('account-username');
        var nonce = button.data('nonce');
        button.prop('disabled', true).text('Removing...');
        $.post(dmApiKeysParams.ajax_url, {
            action: 'dm_remove_reddit_account',
            account_username: accountUsername,
            _ajax_nonce: nonce
        }, function(response) {
            if (response.success) {
                button.closest('li').fadeOut(300, function() { location.reload(); });
            } else {
                alert('Error removing account: ' + (response.data ? response.data.message : 'Unknown error'));
                button.prop('disabled', false).text('Remove');
            }
        }).fail(function(jqXHR, textStatus, errorThrown) {
            console.error('AJAX Error: ', textStatus, errorThrown);
            alert('Error removing account: Request failed. Check browser console.');
            button.prop('disabled', false).text('Remove');
        });
    });

    // --- Remove Twitter Account ---
    $('#twitter-accounts-list').on('click', '.twitter-remove-account-btn', function() {
        var button = $(this);
        var nonce = button.data('nonce');
        if (!confirm('Are you sure you want to remove this Twitter account connection?')) {
            return;
        }
        button.prop('disabled', true);
        $('#twitter-auth-feedback').text('Removing account...').css('color', '');
        $.post(dmApiKeysParams.ajax_url, {
            action: 'dm_remove_twitter_account',
            _ajax_nonce: nonce
        }, function(response) {
            if (response.success) {
                window.location.reload();
            } else {
                var errorMessage = response.data && response.data.message ? response.data.message : 'Unknown error occurred.';
                $('#twitter-auth-feedback').text('Error: ' + errorMessage).css('color', 'red');
                button.prop('disabled', false);
                alert('Error removing Twitter account: ' + errorMessage);
            }
        }).fail(function(jqXHR, textStatus, errorThrown) {
            var error = 'AJAX request failed: ' + textStatus + ', ' + errorThrown;
            $('#twitter-auth-feedback').text('Error: ' + error).css('color', 'red');
            button.prop('disabled', false);
            alert('An error occurred while trying to remove the account. Please try again.');
        });
    });

    // --- Threads Authentication ---
    $('#threads-authenticate-btn').on('click', function() {
        var button = $(this);
        button.prop('disabled', true);
        $('#threads-auth-feedback').text('Initiating authentication...').css('color', '');

        // Get nonce for initiation action
        $.post(dmApiKeysParams.ajax_url, { action: 'dm_generate_nonce', id: 'dm_initiate_threads_oauth_action' }, function(nonceResponse) {
            if (nonceResponse.success && nonceResponse.data.nonce) {
                var initiateNonce = nonceResponse.data.nonce;
                // Initiate OAuth flow via AJAX
                $.post(dmApiKeysParams.ajax_url, {
                    action: 'dm_initiate_threads_oauth',
                    _ajax_nonce: initiateNonce
                }, function(response) {
                    if (response.success && response.data.authorization_url) {
                        $('#threads-auth-feedback').text('Redirecting to Threads...');
                        openOAuthPopup(response.data.authorization_url, 'threadsAuth', 600, 700);
                        // No need to re-enable button here, popup handles flow
                    } else {
                        var errorMsg = response.data && response.data.message ? response.data.message : 'Could not get authorization URL.';
                        $('#threads-auth-feedback').text('Error: ' + errorMsg).css('color', 'red');
                        button.prop('disabled', false);
                    }
                }).fail(function() {
                    $('#threads-auth-feedback').text('Error: AJAX request failed.').css('color', 'red');
                    button.prop('disabled', false);
                });
            } else {
                 $('#threads-auth-feedback').text('Error: Could not generate security token.').css('color', 'red');
                 button.prop('disabled', false);
            }
        }).fail(function() {
             $('#threads-auth-feedback').text('Error: Nonce AJAX request failed.').css('color', 'red');
             button.prop('disabled', false);
        });
    });

    // --- Remove Threads Account ---
    $('#threads-accounts-list').on('click', '.threads-remove-account-btn', function(e) {
        e.preventDefault();
        if (!confirm('Are you sure you want to remove this Threads account authorization?')) return;
        var button = $(this);
        var accountId = button.data('account-id'); // Assuming account ID is stored in data-account-id
        var nonce = button.data('nonce');
        button.prop('disabled', true).text('Removing...');
        $('#threads-auth-feedback').text('Removing account...').css('color', '');

        $.post(dmApiKeysParams.ajax_url, {
            action: 'dm_remove_threads_account',
            account_id: accountId, // Send account ID if needed by backend for nonce verification
            _ajax_nonce: nonce
        }, function(response) {
            if (response.success) {
                // Reload page to reflect removal
                location.reload();
            } else {
                var errorMsg = response.data && response.data.message ? response.data.message : 'Unknown error';
                alert('Error removing account: ' + errorMsg);
                $('#threads-auth-feedback').text('Error: ' + errorMsg).css('color', 'red');
                button.prop('disabled', false).text('Remove');
            }
        }).fail(function(jqXHR, textStatus, errorThrown) {
            console.error('AJAX Error: ', textStatus, errorThrown);
            alert('Error removing account: Request failed. Check browser console.');
             $('#threads-auth-feedback').text('Error: AJAX request failed.').css('color', 'red');
            button.prop('disabled', false).text('Remove');
        });
    });


    // --- Facebook Authentication ---
     $('#facebook-authenticate-btn').on('click', function() {
        var button = $(this);
        button.prop('disabled', true);
        $('#facebook-auth-feedback').text('Initiating authentication...').css('color', '');

        // Get nonce for initiation action
        $.post(dmApiKeysParams.ajax_url, { action: 'dm_generate_nonce', id: 'dm_initiate_facebook_oauth_action' }, function(nonceResponse) {
            if (nonceResponse.success && nonceResponse.data.nonce) {
                var initiateNonce = nonceResponse.data.nonce;
                // Initiate OAuth flow via AJAX
                $.post(dmApiKeysParams.ajax_url, {
                    action: 'dm_initiate_facebook_oauth',
                    _ajax_nonce: initiateNonce
                }, function(response) {
                    if (response.success && response.data.authorization_url) {
                        $('#facebook-auth-feedback').text('Redirecting to Facebook...');
                        openOAuthPopup(response.data.authorization_url, 'facebookAuth', 600, 700);
                        // No need to re-enable button here, popup handles flow
                    } else {
                        var errorMsg = response.data && response.data.message ? response.data.message : 'Could not get authorization URL.';
                        $('#facebook-auth-feedback').text('Error: ' + errorMsg).css('color', 'red');
                        button.prop('disabled', false);
                    }
                }).fail(function() {
                    $('#facebook-auth-feedback').text('Error: AJAX request failed.').css('color', 'red');
                    button.prop('disabled', false);
                });
            } else {
                 $('#facebook-auth-feedback').text('Error: Could not generate security token.').css('color', 'red');
                 button.prop('disabled', false);
            }
        }).fail(function() {
             $('#facebook-auth-feedback').text('Error: Nonce AJAX request failed.').css('color', 'red');
             button.prop('disabled', false);
        });
    });

    // --- Remove Facebook Account ---
    $('#facebook-accounts-list').on('click', '.facebook-remove-account-btn', function(e) {
        e.preventDefault(); // Prevent default button behavior
        if (!confirm('Are you sure you want to remove this Facebook account connection? This will attempt to deauthorize the app on Facebook as well.')) return;
        
        var button = $(this);
        var nonce = button.data('nonce'); // Get nonce directly from the button attribute
        var accountId = button.data('account-id'); // Get account ID (Facebook User ID)
        
        // Basic check for nonce
        if (!nonce) {
            alert('Error: Security token (nonce) missing. Cannot remove account.');
            return;
        }

        button.prop('disabled', true).text('Removing...');
        $('#facebook-auth-feedback').text('Removing account...').css('color', ''); // Provide feedback

        $.post(dmApiKeysParams.ajax_url, { // Use localized ajax_url
            action: 'dm_remove_facebook_account',
            account_id: accountId, // Send account ID (optional, main check is nonce)
            _ajax_nonce: nonce // Send the correct nonce
        }, function(response) {
            if (response.success) {
                // Reload the page to show updated status
                window.location.reload(); 
            } else {
                var errorMessage = response.data && response.data.message ? response.data.message : 'Unknown error occurred.';
                $('#facebook-auth-feedback').text('Error: ' + errorMessage).css('color', 'red');
                button.prop('disabled', false).text('Remove');
                alert('Error removing Facebook account: ' + errorMessage);
            }
        }).fail(function(jqXHR, textStatus, errorThrown) {
            console.error('AJAX Error removing Facebook account: ', textStatus, errorThrown, jqXHR.responseText);
            var error = 'AJAX request failed: ' + textStatus + ', ' + errorThrown;
            $('#facebook-auth-feedback').text('Error: ' + error).css('color', 'red');
            button.prop('disabled', false).text('Remove');
            alert('An error occurred while trying to remove the account. Please check the browser console and try again.');
        });
    });

    // Do not fetch accounts on initial load; list is rendered server-side.
});