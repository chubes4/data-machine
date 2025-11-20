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

export const useProviders = () =>
  useQuery({
    queryKey: ['config', 'providers'],
    queryFn: async () => {
      const response = await apiFetch({ path: '/datamachine/v1/providers' });
      return response.success ? response.data : { providers: {}, defaults: { provider: '', model: '' } };
    },
    staleTime: 5 * 60 * 1000, // 5 minutes - providers don't change often
  });

export const useTools = () =>
  useQuery({
    queryKey: ['config', 'tools'],
    queryFn: async () => {
      const response = await apiFetch({ path: '/datamachine/v1/tools' });
      return response.success ? response.data : {};
    },
    staleTime: 5 * 60 * 1000, // 5 minutes - tools config doesn't change often
  });