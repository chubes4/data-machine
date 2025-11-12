/**
 * useModal Hook
 *
 * Manage modal state for Data Machine pipelines interface.
 */

import { useState, useCallback } from '@wordpress/element';
import { MODAL_TYPES } from '../utils/constants';

/**
 * Hook for managing modal state
 *
 * @returns {Object} Modal state and control functions
 */
export const useModal = () => {
	const [ activeModal, setActiveModal ] = useState( null );
	const [ modalData, setModalData ] = useState( null );

	/**
	 * Open a modal with optional context data
	 *
	 * @param {string} modalType - Modal type from MODAL_TYPES
	 * @param {Object} data - Context data for the modal
	 */
	const openModal = useCallback( ( modalType, data = null ) => {
		setActiveModal( modalType );
		setModalData( data );
	}, [] );

	/**
	 * Close the active modal
	 */
	const closeModal = useCallback( () => {
		setActiveModal( null );
		setModalData( null );
	}, [] );

	/**
	 * Check if a specific modal is open
	 *
	 * @param {string} modalType - Modal type to check
	 * @returns {boolean} True if modal is open
	 */
	const isModalOpen = useCallback(
		( modalType ) => {
			return activeModal === modalType;
		},
		[ activeModal ]
	);

	return {
		activeModal,
		modalData,
		openModal,
		closeModal,
		isModalOpen,
		MODAL_TYPES,
	};
};
