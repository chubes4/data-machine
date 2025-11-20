/**
 * Configuration Queries
 *
 * TanStack Query hooks for configuration data (step types, global settings).
 */

import { useQuery } from '@tanstack/react-query';
import { getStepTypes, getProviders, getTools } from '../utils/api';

export const useStepTypes = () =>
  useQuery({
    queryKey: ['config', 'step-types'],
    queryFn: async () => {
      const result = await getStepTypes();
      return result.success ? result.data : {};
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
      const result = await getProviders();
      return result.success ? result.data : {};
    },
    staleTime: 30 * 60 * 1000, // 30 minutes - providers don't change often
  });

export const useTools = () =>
  useQuery({
    queryKey: ['config', 'tools'],
    queryFn: async () => {
      const result = await getTools();
      return result.success ? result.data : {};
    },
    staleTime: 30 * 60 * 1000, // 30 minutes - tools don't change often
  });