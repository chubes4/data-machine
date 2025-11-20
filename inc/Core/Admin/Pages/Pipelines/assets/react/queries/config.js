/**
 * Configuration Queries
 *
 * TanStack Query hooks for configuration data (step types, global settings).
 */

import { useQuery } from '@tanstack/react-query';
import apiFetch from '@wordpress/api-fetch';

export const useStepTypes = () =>
  useQuery({
    queryKey: ['config', 'step-types'],
    queryFn: async () => {
      const response = await apiFetch({ path: '/datamachine/v1/step-types' });
      return response.success ? response.data : {};
    },
    staleTime: Infinity, // Never refetch - step types don't change
  });

export const useGlobalSettings = () =>
  useQuery({
    queryKey: ['config', 'global-settings'],
    queryFn: async () => {
      // Fetch from API
      return window.datamachineConfig?.globalSettings || {};
    },
    staleTime: 10 * 60 * 1000, // 10 minutes
  });