/**
 * Jobs Modal Content JavaScript (Vanilla JS - No jQuery)
 *
 * Handles interactions WITHIN jobs modal content only.
 * Form submissions, validations, visual feedback.
 * Emits limited events for page communication.
 * Modal lifecycle managed by vanilla JS, page actions by datamachine-jobs.js.
 *
 * @package
 * @since NEXT_VERSION
 */

( function () {
	'use strict';

	/**
	 * Jobs Modal Content Handler
	 *
	 * Handles business logic for jobs-specific modal interactions.
	 * Works with buttons and content created by PHP modal templates.
	 */
	window.dmJobsModal = {
		/**
		 * Initialize jobs modal content handlers
		 */
		init() {
			this.bindEvents();
		},

		/**
		 * Bind event handlers for modal content interactions
		 */
		bindEvents() {
			// Clear processed items form handling
			document.addEventListener( 'submit', ( e ) => {
				if (
					e.target &&
					e.target.id === 'datamachine-clear-processed-items-form'
				) {
					this.handleClearProcessedItems( e );
				}
			} );

			// Clear jobs form handling
			document.addEventListener( 'submit', ( e ) => {
				if (
					e.target &&
					e.target.id === 'datamachine-clear-jobs-form'
				) {
					this.handleClearJobs( e );
				}
			} );

			// Clear type selection for processed items
			document.addEventListener( 'change', ( e ) => {
				if (
					e.target &&
					e.target.id === 'datamachine-clear-type-select'
				) {
					this.handleClearTypeChange( e );
				}
			} );

			// Pipeline selection for flow filtering
			document.addEventListener( 'change', ( e ) => {
				if (
					e.target &&
					e.target.id === 'datamachine-clear-pipeline-select'
				) {
					this.handlePipelineSelection( e );
				}
			} );
		},

		/**
		 * Handle clear type selection change
		 *
		 * @param {Event} e - The change event object
		 */
		handleClearTypeChange( e ) {
			const clearType = e.target.value;
			const pipelineWrapper = document.getElementById(
				'datamachine-pipeline-select-wrapper'
			);
			const flowWrapper = document.getElementById(
				'datamachine-flow-select-wrapper'
			);
			const flowSelect = document.getElementById(
				'datamachine-clear-flow-select'
			);

			if ( clearType === 'pipeline' ) {
				if ( pipelineWrapper ) {
					pipelineWrapper.classList.remove( 'datamachine-hidden' );
				}
				if ( flowWrapper ) {
					flowWrapper.classList.add( 'datamachine-hidden' );
				}
				if ( flowSelect ) {
					flowSelect.innerHTML =
						'<option value="">— Select a Pipeline First —</option>';
				}
			} else if ( clearType === 'flow' ) {
				if ( pipelineWrapper ) {
					pipelineWrapper.classList.remove( 'datamachine-hidden' );
				}
				if ( flowWrapper ) {
					flowWrapper.classList.remove( 'datamachine-hidden' );
				}
			} else {
				if ( pipelineWrapper ) {
					pipelineWrapper.classList.add( 'datamachine-hidden' );
				}
				if ( flowWrapper ) {
					flowWrapper.classList.add( 'datamachine-hidden' );
				}
			}
		},

		/**
		 * Handle pipeline selection for flow filtering
		 *
		 * @param {Event} e - The change event object
		 */
		handlePipelineSelection( e ) {
			const pipelineId = e.target.value;
			const flowSelect = document.getElementById(
				'datamachine-clear-flow-select'
			);
			const clearType = document.getElementById(
				'datamachine-clear-type-select'
			).value;

			if ( ! flowSelect ) {
				return;
			}

			flowSelect.innerHTML = '<option value="">— Loading... —</option>';

			if ( pipelineId && clearType === 'flow' ) {
				// Fetch flows for the selected pipeline via REST API
				wp.apiFetch( {
					path: `/datamachine/v1/pipelines/${ pipelineId }/flows`,
					method: 'GET',
				} )
					.then( ( response ) => {
						if ( response.success && response.flows ) {
							// Filter to minimal data needed for select dropdown
							const flows = response.flows.map( ( flow ) => ( {
								flow_id: flow.flow_id,
								flow_name: flow.flow_name,
							} ) );

							flowSelect.innerHTML =
								'<option value="">— Select a Flow —</option>';
							flows.forEach( ( flow ) => {
								const option =
									document.createElement( 'option' );
								option.value = flow.flow_id;
								option.textContent = flow.flow_name;
								flowSelect.appendChild( option );
							} );
						} else {
							flowSelect.innerHTML =
								'<option value="">— No flows found —</option>';
						}
					} )
					.catch( () => {
						flowSelect.innerHTML =
							'<option value="">— Error loading flows —</option>';
					} );
			} else {
				flowSelect.innerHTML =
					'<option value="">— Select a Flow —</option>';
			}
		},

		/**
		 * Handle clear processed items form submission
		 */
		handleClearProcessedItems( e ) {
			e.preventDefault();

			const form = e.target;
			const button = document.getElementById(
				'datamachine-clear-processed-btn'
			);
			const spinner = form.querySelector( '.spinner' );
			const result = document.getElementById(
				'datamachine-clear-result'
			);
			const clearType = document.getElementById(
				'datamachine-clear-type-select'
			).value;

			// Validate form
			if ( ! clearType ) {
				this.showResult(
					result,
					'warning',
					'Please select a clear type'
				);
				return;
			}

			let targetId = '';
			let confirmMessage = '';

			if ( clearType === 'pipeline' ) {
				targetId = document.getElementById(
					'datamachine-clear-pipeline-select'
				).value;
				if ( ! targetId ) {
					this.showResult(
						result,
						'warning',
						'Please select a pipeline'
					);
					return;
				}
				confirmMessage =
					'Are you sure you want to clear all processed items for ALL flows in this pipeline? This will allow all items to be reprocessed.';
			} else if ( clearType === 'flow' ) {
				targetId = document.getElementById(
					'datamachine-clear-flow-select'
				).value;
				if ( ! targetId ) {
					this.showResult(
						result,
						'warning',
						'Please select a flow'
					);
					return;
				}
				confirmMessage =
					'Are you sure you want to clear all processed items for this flow? This will allow all items to be reprocessed.';
			}

			// Confirm action
			// eslint-disable-next-line no-undef
			if ( ! confirm( confirmMessage ) ) {
				return;
			}

			// Show loading state
			this.setLoadingState( button, spinner, true );
			if ( result ) {
				result.classList.add( 'datamachine-hidden' );
			}

			// Make REST API request
			wp.apiFetch( {
				path: `/datamachine/v1/processed-items?clear_type=${ clearType }&target_id=${ targetId }`,
				method: 'DELETE',
			} )
				.then( ( response ) => {
					this.showResult( result, 'success', response.message );

					// Reset form
					form.reset();
					const pipelineWrapper = document.getElementById(
						'datamachine-pipeline-select-wrapper'
					);
					const flowWrapper = document.getElementById(
						'datamachine-flow-select-wrapper'
					);
					if ( pipelineWrapper ) {
						pipelineWrapper.classList.add( 'datamachine-hidden' );
					}
					if ( flowWrapper )
						flowWrapper.classList.add( 'datamachine-hidden' );

					// Emit event for page to update if needed
					document.dispatchEvent(
						new CustomEvent(
							'datamachine-jobs-processed-items-cleared',
							{
								detail: response,
							}
						)
					);
				} )
				.catch( ( error ) => {
					this.showResult(
						result,
						'error',
						error.message || 'An unexpected error occurred'
					);
				} )
				.finally( () => {
					this.setLoadingState( button, spinner, false );
				} );
		},

		/**
		 * Handle clear jobs form submission
		 */
		handleClearJobs( e ) {
			e.preventDefault();

			const form = e.target;
			const button = document.getElementById(
				'datamachine-clear-jobs-btn'
			);
			const spinner = form.querySelector( '.spinner' );
			const result = document.getElementById(
				'datamachine-clear-jobs-result'
			);
			const clearTypeRadio = form.querySelector(
				'input[name="clear_jobs_type"]:checked'
			);
			const clearType = clearTypeRadio ? clearTypeRadio.value : '';
			const cleanupProcessedCheckbox = form.querySelector(
				'input[name="cleanup_processed"]'
			);
			const cleanupProcessed = cleanupProcessedCheckbox
				? cleanupProcessedCheckbox.checked
				: false;

			// Validate form
			if ( ! clearType ) {
				this.showResult(
					result,
					'warning',
					'Please select which jobs to clear'
				);
				return;
			}

			// Build confirmation message
			let confirmMessage = '';
			if ( clearType === 'all' ) {
				confirmMessage =
					'Are you sure you want to delete ALL jobs? This will remove all execution history and cannot be undone.';
				if ( cleanupProcessed ) {
					confirmMessage +=
						'\n\nThis will also clear ALL processed items, allowing complete reprocessing of all content.';
				}
			} else {
				confirmMessage =
					'Are you sure you want to delete all FAILED jobs?';
				if ( cleanupProcessed ) {
					confirmMessage +=
						'\n\nThis will also clear processed items for the failed jobs, allowing them to be reprocessed.';
				}
			}

			// Confirm action
			// eslint-disable-next-line no-undef
			if ( ! confirm( confirmMessage ) ) {
				return;
			}

			// Show loading state
			this.setLoadingState( button, spinner, true );
			if ( result ) result.classList.add( 'datamachine-hidden' );

			// Make REST API request
			wp.apiFetch( {
				path: `/datamachine/v1/jobs?type=${ clearType }&cleanup_processed=${
					cleanupProcessed ? '1' : '0'
				}`,
				method: 'DELETE',
			} )
				.then( ( response ) => {
					this.showResult( result, 'success', response.message );

					// Reset form
					form.reset();

					// Emit event for page to update if needed
					document.dispatchEvent(
						new CustomEvent( 'datamachine-jobs-cleared', {
							detail: response,
						} )
					);
				} )
				.catch( ( error ) => {
					this.showResult(
						result,
						'error',
						error.message || 'An unexpected error occurred'
					);
				} )
				.finally( () => {
					this.setLoadingState( button, spinner, false );
				} );
		},

		/**
		 * Show result message with appropriate styling
		 *
		 * @param {HTMLElement} resultEl - The result element to update
		 * @param {string} type - The message type (success/error)
		 * @param {string} message - The message to display
		 */
		showResult( resultEl, type, message ) {
			if ( ! resultEl ) return;

			resultEl.classList.remove(
				'success',
				'error',
				'warning',
				'datamachine-hidden'
			);
			resultEl.classList.add( type );
			resultEl.innerHTML = message;
		},

		/**
		 * Set loading state for button and spinner
		 */
		setLoadingState( button, spinner, loading ) {
			if ( button ) button.disabled = loading;
			if ( spinner ) {
				if ( loading ) {
					spinner.classList.add( 'is-active' );
				} else {
					spinner.classList.remove( 'is-active' );
				}
			}
		},
	};

	/**
	 * Initialize when document is ready
	 */
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', () => {
			dmJobsModal.init();
		} );
	} else {
		dmJobsModal.init();
	}
} )();
