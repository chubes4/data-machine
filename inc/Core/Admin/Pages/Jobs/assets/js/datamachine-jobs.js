/**
 * Data Machine Admin Jobs Page JavaScript (Vanilla JS - No jQuery)
 *
 * Handles jobs list retrieval and rendering via REST API.
 * Used by: inc/Core/Admin/Pages/Jobs/Jobs.php
 *
 * @since NEXT_VERSION
 */

( function () {
	'use strict';

	const jobsManager = {
		/**
		 * Initialize jobs page
		 */
		init: function () {
			this.loadJobs();
			this.bindEvents();
		},

		/**
		 * Bind event listeners
		 */
		bindEvents: function () {
			// Listen for jobs cleared event from modal
			document.addEventListener( 'datamachine-jobs-cleared', () => {
				this.loadJobs();
			} );

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
		 * Load jobs from REST API
		 */
		loadJobs: function () {
			// Show loading state
			const loadingEl = document.querySelector(
				'.datamachine-jobs-loading'
			);
			const emptyStateEl = document.querySelector(
				'.datamachine-jobs-empty-state'
			);
			const tableContainerEl = document.querySelector(
				'.datamachine-jobs-table-container'
			);

			if ( loadingEl ) loadingEl.style.display = 'block';
			if ( emptyStateEl ) emptyStateEl.style.display = 'none';
			if ( tableContainerEl ) tableContainerEl.style.display = 'none';

			wp.apiFetch( {
				path: '/datamachine/v1/jobs?orderby=job_id&order=DESC&per_page=50&offset=0',
				method: 'GET',
			} )
				.then( ( response ) => {
					if ( response.success && response.jobs ) {
						this.renderJobs( response.jobs );
					} else {
						this.showEmptyState();
					}
				} )
				.catch( ( error ) => {
					console.error( 'Failed to load jobs:', error );
					this.showEmptyState();
				} )
				.finally( () => {
					if ( loadingEl ) loadingEl.style.display = 'none';
				} );
		},

		/**
		 * Render jobs table
		 */
		renderJobs: function ( jobs ) {
			if ( ! jobs || jobs.length === 0 ) {
				this.showEmptyState();
				return;
			}

			const tbody = document.getElementById( 'datamachine-jobs-tbody' );
			if ( ! tbody ) return;

			tbody.innerHTML = '';

			jobs.forEach( ( job ) => {
				const row = this.renderJobRow( job );
				tbody.appendChild( row );
			} );

			const tableContainerEl = document.querySelector(
				'.datamachine-jobs-table-container'
			);
			const emptyStateEl = document.querySelector(
				'.datamachine-jobs-empty-state'
			);

			if ( tableContainerEl ) tableContainerEl.style.display = 'block';
			if ( emptyStateEl ) emptyStateEl.style.display = 'none';
		},

		/**
		 * Render individual job row
		 */
		renderJobRow: function ( job ) {
			const pipelineName = job.pipeline_name || 'Unknown Pipeline';
			const flowName = job.flow_name || 'Unknown Flow';
			const status = job.status || 'unknown';
			const statusDisplay = this.formatStatus( status );
			const statusClass = this.getStatusClass( status );
			const createdAt = this.formatDate( job.created_at );
			const completedAt = this.formatDate( job.completed_at );

			const tr = document.createElement( 'tr' );

			// Job ID column
			const td1 = document.createElement( 'td' );
			td1.innerHTML =
				'<strong>' + this.escapeHtml( job.job_id ) + '</strong>';
			tr.appendChild( td1 );

			// Pipeline → Flow column
			const td2 = document.createElement( 'td' );
			td2.textContent = pipelineName + ' → ' + flowName;
			tr.appendChild( td2 );

			// Status column
			const td3 = document.createElement( 'td' );
			td3.innerHTML =
				'<span class="datamachine-job-status--' +
				statusClass +
				'">' +
				this.escapeHtml( statusDisplay ) +
				'</span>';
			tr.appendChild( td3 );

			// Created At column
			const td4 = document.createElement( 'td' );
			td4.textContent = createdAt;
			tr.appendChild( td4 );

			// Completed At column
			const td5 = document.createElement( 'td' );
			td5.textContent = completedAt;
			tr.appendChild( td5 );

			return tr;
		},

		/**
		 * Format job status for display
		 */
		formatStatus: function ( status ) {
			return (
				status.charAt( 0 ).toUpperCase() +
				status.slice( 1 ).replace( /_/g, ' ' )
			);
		},

		/**
		 * Get CSS class for job status
		 */
		getStatusClass: function ( status ) {
			if ( status === 'failed' ) return 'failed';
			if ( status === 'completed' ) return 'completed';
			return 'other';
		},

		/**
		 * Format date for display
		 */
		formatDate: function ( dateString ) {
			if ( ! dateString ) return '';

			try {
				const date = new Date( dateString.replace( ' ', 'T' ) + 'Z' );
				const options = {
					year: 'numeric',
					month: 'short',
					day: 'numeric',
					hour: 'numeric',
					minute: '2-digit',
					hour12: true,
				};
				return date.toLocaleString( 'en-US', options );
			} catch ( e ) {
				return dateString;
			}
		},

		/**
		 * Show empty state
		 */
		showEmptyState: function () {
			const emptyStateEl = document.querySelector(
				'.datamachine-jobs-empty-state'
			);
			const tableContainerEl = document.querySelector(
				'.datamachine-jobs-table-container'
			);

			if ( emptyStateEl ) emptyStateEl.style.display = 'block';
			if ( tableContainerEl ) tableContainerEl.style.display = 'none';
		},

		/**
		 * Escape HTML for safe rendering
		 */
		escapeHtml: function ( text ) {
			const div = document.createElement( 'div' );
			div.textContent = text;
			return div.innerHTML;
		},
	};

	// Initialize when DOM is ready
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', () => {
			jobsManager.init();
		} );
	} else {
		jobsManager.init();
	}
} )();
