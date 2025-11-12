/**
 * Data Machine Settings Page JavaScript (Vanilla JS - No jQuery, No AJAX)
 *
 * Handles settings page business logic following established architectural patterns.
 * All modals are pre-rendered in the page template.
 *
 * @package DataMachine\Core\Admin\Settings
 * @since 1.0.0
 */

( function () {
	'use strict';

	/**
	 * Settings Page Handler
	 *
	 * Handles settings page business logic including tool configuration saves.
	 */
	window.dmSettingsPage = {
		/**
		 * Initialize settings page functionality
		 */
		init: function () {
			this.bindEvents();
			this.initTabManager();
		},

		/**
		 * Bind event handlers
		 */
		bindEvents: function () {
			// Tool configuration save handler
			document.addEventListener( 'click', ( e ) => {
				if (
					e.target.classList.contains(
						'datamachine-tool-config-save'
					)
				) {
					this.handleToolConfigSave( e );
				}
			} );

			// Cache clearing handler
			const clearCacheBtn = document.getElementById(
				'datamachine-clear-cache-btn'
			);
			if ( clearCacheBtn ) {
				clearCacheBtn.addEventListener(
					'click',
					this.handleClearCache.bind( this )
				);
			}

			// Tab navigation handlers
			const tabLinks = document.querySelectorAll(
				'.datamachine-nav-tab-wrapper .nav-tab'
			);
			tabLinks.forEach( ( tab ) => {
				tab.addEventListener(
					'click',
					this.handleTabClick.bind( this )
				);
			} );

			// Form submission handler to preserve tab state
			const settingsForm = document.querySelector(
				'.datamachine-settings-form'
			);
			if ( settingsForm ) {
				settingsForm.addEventListener(
					'submit',
					this.handleFormSubmit.bind( this )
				);
			}

			// Modal open handlers
			document.addEventListener( 'click', ( e ) => {
				if (
					e.target.classList.contains( 'datamachine-open-modal' ) ||
					e.target.closest( '.datamachine-open-modal' )
				) {
					this.handleModalOpen( e );
				}
			} );

			// Modal close handlers
			document.addEventListener( 'click', ( e ) => {
				if (
					e.target.classList.contains( 'datamachine-modal-close' ) ||
					e.target.closest( '.datamachine-modal-close' )
				) {
					this.handleModalClose( e );
				}
			} );

			// Modal overlay click to close
			document.addEventListener( 'click', ( e ) => {
				if (
					e.target.classList.contains( 'datamachine-modal-overlay' )
				) {
					this.handleModalClose( e );
				}
			} );

			// Escape key to close modal
			document.addEventListener( 'keydown', ( e ) => {
				if ( e.key === 'Escape' ) {
					const activeModal = document.querySelector(
						'.datamachine-modal[aria-hidden="false"]'
					);
					if ( activeModal ) {
						this.closeModal( activeModal );
					}
				}
			} );
		},

		/**
		 * Handle modal open
		 */
		handleModalOpen: function ( e ) {
			e.preventDefault();
			const button = e.target.classList.contains(
				'datamachine-open-modal'
			)
				? e.target
				: e.target.closest( '.datamachine-open-modal' );
			const modalId = button.getAttribute( 'data-modal-id' );
			if ( modalId ) {
				const modal = document.getElementById( modalId );
				if ( modal ) {
					this.openModal( modal );
				}
			}
		},

		/**
		 * Handle modal close
		 */
		handleModalClose: function ( e ) {
			e.preventDefault();
			const modal = e.target.closest( '.datamachine-modal' );
			if ( modal ) {
				this.closeModal( modal );
			}
		},

		/**
		 * Open modal
		 */
		openModal: function ( modal ) {
			modal.setAttribute( 'aria-hidden', 'false' );
			modal.classList.add( 'datamachine-modal-active' );
			document.body.classList.add( 'datamachine-modal-active' );

			// Focus first input
			const firstInput = modal.querySelector( 'input, select, textarea' );
			if ( firstInput ) {
				firstInput.focus();
			}
		},

		/**
		 * Close modal
		 */
		closeModal: function ( modal ) {
			modal.setAttribute( 'aria-hidden', 'true' );
			modal.classList.remove( 'datamachine-modal-active' );
			document.body.classList.remove( 'datamachine-modal-active' );
		},

		/**
		 * Handle tool configuration save action
		 */
		handleToolConfigSave: function ( e ) {
			e.preventDefault();

			const button = e.target;
			const toolId = button.getAttribute( 'data-tool-id' );

			if ( ! toolId ) {
				console.error( 'Tool config save: Missing tool_id' );
				return;
			}

			// Find the form
			const form = document.getElementById(
				`datamachine-${ toolId }-config-form`
			);

			if ( ! form ) {
				console.error(
					'Tool config save: Form not found for tool:',
					toolId
				);
				return;
			}

			// Collect configuration data from form fields
			const configData = {};
			const formElements = form.querySelectorAll(
				'input, select, textarea'
			);

			formElements.forEach( ( field ) => {
				const fieldName = field.getAttribute( 'name' );
				if ( fieldName ) {
					configData[ fieldName ] = field.value;
				}
			} );

			// Basic validation - ensure required fields are filled
			const requiredFields = form.querySelectorAll( '[required]' );
			const missingFields = [];

			requiredFields.forEach( ( field ) => {
				const fieldName = field.getAttribute( 'name' );
				if (
					! configData[ fieldName ] ||
					configData[ fieldName ].trim() === ''
				) {
					const label =
						field.closest( 'tr' ).querySelector( 'label' )
							.textContent || fieldName;
					missingFields.push( label );
				}
			} );

			if ( missingFields.length > 0 ) {
				this.showError(
					`Please fill in all required fields: ${ missingFields.join(
						', '
					) }`
				);
				return;
			}

			// Show loading state
			const originalText = button.textContent;
			button.textContent =
				datamachineSettings.strings.saving || 'Saving...';
			button.disabled = true;

			// Use REST API endpoint
			const restUrl =
				wpApiSettings.root + 'datamachine/v1/settings/tools/' + toolId;

			// Send REST request
			fetch( restUrl, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': wpApiSettings.nonce,
				},
				body: JSON.stringify( {
					config_data: configData,
				} ),
			} )
				.then( ( response ) => response.json() )
				.then( ( response ) => {
					if ( response.success ) {
						// Close modal and refresh page to show updated status
						const modal = button.closest( '.datamachine-modal' );
						this.closeModal( modal );
						location.reload();
					} else {
						// Show error in modal
						const errorMessage =
							response.data && response.data.message
								? response.data.message
								: 'Configuration save failed';
						this.showError( errorMessage );
					}
				} )
				.catch( ( error ) => {
					// Show network error
					this.showError(
						'Network error: Unable to save configuration'
					);
				} )
				.finally( () => {
					// Restore button state
					button.disabled = false;
					button.textContent = originalText;
				} );
		},

		/**
		 * Handle cache clearing action
		 */
		handleClearCache: function ( e ) {
			e.preventDefault();

			const button = e.target;
			const result = document.getElementById(
				'datamachine-cache-clear-result'
			);

			// Show confirmation dialog
			if (
				! confirm(
					'Are you sure you want to clear all cache? This will force Data Machine to reload all configurations from the database.'
				)
			) {
				return;
			}

			// Show loading state
			const originalText = button.textContent;
			button.textContent = 'Clearing...';
			button.disabled = true;
			result.classList.remove(
				'datamachine-hidden',
				'notice-success',
				'notice-error'
			);
			result.textContent = '';

			// Use REST API endpoint
			const restUrl = wpApiSettings.root + 'datamachine/v1/cache';

			// Send REST request
			fetch( restUrl, {
				method: 'DELETE',
				headers: {
					'X-WP-Nonce': wpApiSettings.nonce,
				},
			} )
				.then( ( response ) => response.json() )
				.then( ( response ) => {
					if ( response.success ) {
						const message =
							response.message || 'Cache cleared successfully';
						result.classList.add( 'notice-success' );
						result.textContent = message;
						result.classList.remove( 'datamachine-hidden' );

						// Hide success message after 3 seconds
						setTimeout( () => {
							result.style.opacity = '0';
							result.style.transition = 'opacity 0.3s';
							setTimeout( () => {
								result.classList.add( 'datamachine-hidden' );
								result.style.opacity = '';
								result.style.transition = '';
							}, 300 );
						}, 3000 );
					} else {
						const errorMessage =
							response.message || 'Cache clearing failed';
						result.classList.add( 'notice-error' );
						result.textContent = errorMessage;
						result.classList.remove( 'datamachine-hidden' );
					}
				} )
				.catch( ( error ) => {
					const errorMessage = 'Network error: Unable to clear cache';
					result.classList.add( 'notice-error' );
					result.textContent = errorMessage;
					result.classList.remove( 'datamachine-hidden' );
				} )
				.finally( () => {
					// Restore button state
					button.disabled = false;
					button.textContent = originalText;
				} );
		},

		/**
		 * Show error message in modal
		 */
		showError: function ( message ) {
			const modalBody = document.querySelector(
				'.datamachine-modal[aria-hidden="false"] .datamachine-modal-body'
			);

			if ( ! modalBody ) {
				alert( message );
				return;
			}

			// Remove existing error messages
			const existingErrors =
				modalBody.querySelectorAll( '.notice-error' );
			existingErrors.forEach( ( error ) => error.remove() );

			// Add error message at top of modal body
			const errorDiv = document.createElement( 'div' );
			errorDiv.className = 'notice notice-error';
			errorDiv.innerHTML = `<p>${ message }</p>`;
			modalBody.insertBefore( errorDiv, modalBody.firstChild );
		},

		/**
		 * Initialize tab management functionality
		 */
		initTabManager: function () {
			this.tabManager = {
				/**
				 * Get active tab from URL or localStorage
				 */
				getActiveTab: function () {
					// First check URL parameter
					const urlParams = new URLSearchParams(
						window.location.search
					);
					const urlTab = urlParams.get( 'tab' );

					if (
						urlTab &&
						[ 'admin', 'agent', 'wordpress' ].includes( urlTab )
					) {
						return urlTab;
					}

					// Fallback to localStorage
					return (
						localStorage.getItem(
							'datamachine_settings_active_tab'
						) || 'admin'
					);
				},

				/**
				 * Set active tab in localStorage and URL
				 */
				setActiveTab: function ( tab ) {
					localStorage.setItem(
						'datamachine_settings_active_tab',
						tab
					);

					// Update URL without page reload
					if ( history.pushState ) {
						const newUrl = new URL( window.location );
						newUrl.searchParams.set( 'tab', tab );
						history.pushState( { tab: tab }, '', newUrl );
					}
				},

				/**
				 * Show specific tab content
				 */
				showTab: function ( tab ) {
					// Hide all tab content
					const tabContents = document.querySelectorAll(
						'.datamachine-tab-content'
					);
					tabContents.forEach( ( content ) => {
						content.classList.remove( 'active' );
						content.style.display = 'none';
					} );

					// Show selected tab
					const selectedTab = document.getElementById(
						'datamachine-tab-' + tab
					);
					if ( selectedTab ) {
						selectedTab.classList.add( 'active' );
						selectedTab.style.display = 'block';
						selectedTab.style.opacity = '0';
						setTimeout( () => {
							selectedTab.style.transition = 'opacity 0.2s';
							selectedTab.style.opacity = '1';
						}, 10 );
					}

					// Update nav tab active state
					const navTabs = document.querySelectorAll(
						'.datamachine-nav-tab-wrapper .nav-tab'
					);
					navTabs.forEach( ( navTab ) => {
						navTab.classList.remove( 'nav-tab-active' );
						if (
							navTab
								.getAttribute( 'href' )
								.includes( 'tab=' + tab )
						) {
							navTab.classList.add( 'nav-tab-active' );
						}
					} );

					// Store selection
					this.setActiveTab( tab );
				},
			};

			// Initialize correct tab on page load
			const activeTab = this.tabManager.getActiveTab();
			this.tabManager.showTab( activeTab );
		},

		/**
		 * Handle tab navigation click
		 */
		handleTabClick: function ( e ) {
			e.preventDefault();

			const tab = e.target;
			const href = tab.getAttribute( 'href' );
			const tabMatch = href.match( /tab=([^&]+)/ );

			if ( tabMatch && tabMatch[ 1 ] ) {
				this.tabManager.showTab( tabMatch[ 1 ] );
			}
		},

		/**
		 * Handle form submission to preserve tab state
		 */
		handleFormSubmit: function ( e ) {
			const activeTab = this.tabManager.getActiveTab();

			// Add hidden field with current tab to preserve state after form submission
			const form = e.target;
			const existingTabInput = form.querySelector(
				'input[name="datamachine_active_tab"]'
			);
			if ( existingTabInput ) {
				existingTabInput.remove();
			}

			const hiddenInput = document.createElement( 'input' );
			hiddenInput.type = 'hidden';
			hiddenInput.name = 'datamachine_active_tab';
			hiddenInput.value = activeTab;
			form.appendChild( hiddenInput );

			// Store in localStorage as backup
			localStorage.setItem(
				'datamachine_settings_active_tab',
				activeTab
			);
		},
	};

	/**
	 * Initialize when document is ready
	 */
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			if ( typeof window.dmSettingsPage !== 'undefined' ) {
				window.dmSettingsPage.init();
			}
		} );
	} else {
		if ( typeof window.dmSettingsPage !== 'undefined' ) {
			window.dmSettingsPage.init();
		}
	}
} )();
