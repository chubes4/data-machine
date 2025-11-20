/**
 * Flow Queries and Mutations
 *
 * TanStack Query hooks for flow-related data operations.
 */

import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
  fetchFlows,
  fetchFlow,
  createFlow,
  updateFlowTitle,
  deleteFlow,
  duplicateFlow,
  runFlow,
  updateFlowHandler,
  updateUserMessage,
  updateFlowSchedule,
} from '../utils/api';

// Queries
export const useFlows = (pipelineId) =>
  useQuery({
    queryKey: ['flows', pipelineId],
    queryFn: async () => {
      const response = await fetchFlows(pipelineId);
      return response.success ? response.data.flows : [];
    },
    enabled: !!pipelineId,
  });

export const useFlow = (flowId) =>
  useQuery({
    queryKey: ['flows', 'single', flowId],
    queryFn: async () => {
      const response = await fetchFlow(flowId);
      return response.success ? response.data : null;
    },
    enabled: !!flowId,
  });

// Mutations
export const useCreateFlow = () => {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ pipelineId, flowName }) => createFlow(pipelineId, flowName),
    onSuccess: (_, { pipelineId }) => {
      queryClient.invalidateQueries(['flows', pipelineId]);
    },
  });
};

export const useUpdateFlowTitle = () => {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ flowId, name }) => updateFlowTitle(flowId, name),
    onSuccess: (data) => {
      queryClient.invalidateQueries(['flows', 'single', data.flow_id]);
      // Also invalidate the flows list for the pipeline
      queryClient.invalidateQueries(['flows']);
    },
  });
};

export const useDeleteFlow = () => {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: deleteFlow,
    onSuccess: () => {
      queryClient.invalidateQueries(['flows']);
    },
  });
};

export const useDuplicateFlow = () => {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: duplicateFlow,
    onSuccess: () => {
      queryClient.invalidateQueries(['flows']);
    },
  });
};

export const useRunFlow = () => {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: runFlow,
    onSuccess: (_, flowId) => {
      queryClient.invalidateQueries(['flows', 'single', flowId]);
    },
  });
};

export const useUpdateFlowHandler = () => {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ flowStepId, handlerSlug, settings, pipelineId, stepType }) =>
      updateFlowHandler(flowStepId, handlerSlug, settings, pipelineId, stepType),
    onSuccess: () => {
      // Invalidate the specific flow that contains this step
      queryClient.invalidateQueries(['flows']);
    },
  });
};

export const useUpdateUserMessage = () => {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ flowStepId, message }) => updateUserMessage(flowStepId, message),
    onSuccess: () => {
      queryClient.invalidateQueries(['flows']);
    },
  });
};

export const useUpdateFlowSchedule = () => {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ flowId, schedulingConfig }) => updateFlowSchedule(flowId, schedulingConfig),
    onSuccess: (response, { flowId }) => {
      // Update cache with the full flow object returned by the API
      if (response.success && response.data) {
        queryClient.setQueryData(['flows', 'single', flowId], response.data);
      }
    },
  });
};