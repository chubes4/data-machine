/**
 * UI State Store
 *
 * Zustand store for managing UI state (selected pipeline, modals).
 */

import { create } from 'zustand';

export const useUIStore = create((set, get) => ({
  // Pipeline selection
  selectedPipelineId: null,
  setSelectedPipelineId: (id) => set({ selectedPipelineId: id }),

  // Modal state
  activeModal: null,
  modalData: null,
  openModal: (modalType, data = null) => set({ activeModal: modalType, modalData: data }),
  closeModal: () => set({ activeModal: null, modalData: null }),

  // Utility functions
  getSelectedPipelineId: () => get().selectedPipelineId,
  isModalOpen: (modalType) => get().activeModal === modalType,
}));