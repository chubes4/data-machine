/**
 * Data Machine Admin Jobs Page JavaScript (Vanilla JS - No jQuery)
 *
 * Handles jobs list retrieval and rendering via REST API.
 * Used by: inc/Core/Admin/Pages/Jobs/Jobs.php
 *
 * @since 0.1.0
 */

( function () {
	'use strict';

	const jobsManager = {
		/**
		 * Initialize jobs page
		 */
		init() {
			this.loadJobs();
			this.bindEvents();
		},

		/**
		 * Bind event listeners
		 */
		bindEvents() {
			// Listen for jobs cleared event from modal
			document.addEventListener( 'datamachine-jobs-cleared', () => {
				this.loadJobs();
			} );

			// Bind modal events using shared modal manager
			if ( window.datamachineModalManager ) {
				window.datamachineModalManager.bindModalEvents();
			}
		},

		/**
		 * Load jobs from REST API
		 */
		loadJobs() {
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

			if ( loadingEl ) {
				loadingEl.style.display = 'block';
			}
			if ( emptyStateEl ) {
				emptyStateEl.style.display = 'none';
			}
			if ( tableContainerEl ) {
				tableContainerEl.style.display = 'none';
			}

			wp.apiFetch( {
				path: '/datamachine/v1/jobs?orderby=job_id&order=DESC&per_page=50&offset=0',
				method: 'GET',
			} )
				.then( ( response ) => {
					if ( response.success && response.data ) {
						this.renderJobs( response.data );
					} else {
						this.showEmptyState();
					}
				} )
				.catch( ( error ) => {
					// eslint-disable-next-line no-console
					console.error( 'Failed to load jobs:', error );
					this.showEmptyState();
				} )
				.finally( () => {
					if ( loadingEl ) {
						loadingEl.style.display = 'none';
					}
				} );
		},

		/**
		 * Render jobs table
		 *
		 * @param {Array} jobs - Array of job objects to render
		 */
		renderJobs( jobs ) {
			if ( ! jobs || jobs.length === 0 ) {
				this.showEmptyState();
				return;
			}

			const tbody = document.getElementById( 'datamachine-jobs-tbody' );
			if ( ! tbody ) {
				return;
			}

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

			if ( tableContainerEl ) {
				tableContainerEl.style.display = 'block';
			}
			if ( emptyStateEl ) {
				emptyStateEl.style.display = 'none';
			}
		},

		/**
		 * Render individual job row
		 *
		 * @param {Object} job - The job object to render
		 */
		renderJobRow( job ) {
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
		 *
		 * @param {string} status - The job status to format
		 */
		formatStatus( status ) {
			return (
				status.charAt( 0 ).toUpperCase() +
				status.slice( 1 ).replace( /_/g, ' ' )
			);
		},

		/**
		 * Get CSS class for job status
		 *
		 * @param {string} status - The job status
		 */
		getStatusClass( status ) {
			if ( status === 'failed' ) {
				return 'failed';
			}
			if ( status === 'completed' ) {
				return 'completed';
			}
			return 'other';
		},

		/**
		 * Format date for display
		 *
		 * @param {string} dateString - The date string to format
		 */
		formatDate( dateString ) {
			if ( ! dateString ) {
				return '';
			}

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
		showEmptyState() {
			const emptyStateEl = document.querySelector(
				'.datamachine-jobs-empty-state'
			);
			const tableContainerEl = document.querySelector(
				'.datamachine-jobs-table-container'
			);

			if ( emptyStateEl ) {
				emptyStateEl.style.display = 'block';
			}
			if ( tableContainerEl ) {
				tableContainerEl.style.display = 'none';
			}
		},

		/**
		 * Escape HTML for safe rendering
		 *
		 * @param {string} text - The text to escape
		 */
		escapeHtml( text ) {
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
