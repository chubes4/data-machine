/**
 * Handler Queries
 *
 * TanStack Query hooks for handler-related data operations.
 */

import { useQuery } from '@tanstack/react-query';
import apiFetch from '@wordpress/api-fetch';
import { fetchHandlerDetails } from '../utils/api';

export const useHandlers = () =>
  useQuery({
    queryKey: ['handlers'],
    queryFn: async () => {
      const response = await apiFetch({ path: '/datamachine/v1/handlers' });
      return response.success ? response.data : {};
    },
    staleTime: 30 * 60 * 1000, // 30 minutes - handlers don't change often
  });

export const useHandlerDetails = (handlerSlug) =>
  useQuery({
    queryKey: ['handlers', handlerSlug],
    queryFn: async () => {
      const response = await fetchHandlerDetails(handlerSlug);
      return response.success ? response.data : null;
    },
    enabled: !!handlerSlug,
    staleTime: 30 * 60 * 1000, // 30 minutes
  });