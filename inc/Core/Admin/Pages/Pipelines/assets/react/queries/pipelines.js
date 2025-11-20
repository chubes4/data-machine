/**
 * Pipeline Queries and Mutations
 *
 * TanStack Query hooks for pipeline-related data operations.
 */

import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
  fetchPipelines,
  createPipeline,
  updatePipelineTitle,
  deletePipeline,
  addPipelineStep,
  deletePipelineStep,
  reorderPipelineSteps,
  updateSystemPrompt,
  fetchContextFiles,
  uploadContextFile,
  deleteContextFile,
} from '../utils/api';

// Queries
export const usePipelines = () =>
  useQuery({
    queryKey: ['pipelines'],
    queryFn: async () => {
      const response = await fetchPipelines();
      return response.success ? response.data.pipelines : [];
    },
  });

export const usePipeline = (pipelineId) =>
  useQuery({
    queryKey: ['pipelines', pipelineId],
    queryFn: async () => {
      const response = await fetchPipelines(pipelineId);
      return response.success ? response.data : null;
    },
    enabled: !!pipelineId,
  });

export const useContextFiles = (pipelineId) =>
  useQuery({
    queryKey: ['context-files', pipelineId],
    queryFn: async () => {
      const response = await fetchContextFiles(pipelineId);
      return response.success ? response.data : [];
    },
    enabled: !!pipelineId,
  });

// Mutations
export const useCreatePipeline = () => {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: createPipeline,
    onSuccess: () => {
      queryClient.invalidateQueries(['pipelines']);
    },
  });
};

export const useUpdatePipelineTitle = () => {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ pipelineId, name }) => updatePipelineTitle(pipelineId, name),
    onSuccess: () => {
      queryClient.invalidateQueries(['pipelines']);
    },
  });
};

export const useDeletePipeline = () => {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: deletePipeline,
    onSuccess: () => {
      queryClient.invalidateQueries(['pipelines']);
    },
  });
};

export const useAddPipelineStep = () => {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ pipelineId, stepType, executionOrder }) =>
      addPipelineStep(pipelineId, stepType, executionOrder),
    onSuccess: (_, { pipelineId }) => {
      queryClient.invalidateQueries(['pipelines', pipelineId]);
    },
  });
};

export const useDeletePipelineStep = () => {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ pipelineId, stepId }) => deletePipelineStep(pipelineId, stepId),
    onSuccess: (_, { pipelineId }) => {
      queryClient.invalidateQueries(['pipelines', pipelineId]);
    },
  });
};

export const useReorderPipelineSteps = () => {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ pipelineId, steps }) => reorderPipelineSteps(pipelineId, steps),
    onSuccess: (_, { pipelineId }) => {
      queryClient.invalidateQueries(['pipelines', pipelineId]);
    },
  });
};

export const useUpdateSystemPrompt = () => {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ stepId, prompt, provider, model, enabledTools, stepType, pipelineId }) =>
      updateSystemPrompt(stepId, prompt, provider, model, enabledTools, stepType, pipelineId),
    onSuccess: (_, { pipelineId }) => {
      queryClient.invalidateQueries(['pipelines', pipelineId]);
    },
  });
};

export const useUploadContextFile = () => {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ pipelineId, file }) => uploadContextFile(pipelineId, file),
    onSuccess: (_, { pipelineId }) => {
      queryClient.invalidateQueries(['context-files', pipelineId]);
    },
  });
};

export const useDeleteContextFile = () => {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: deleteContextFile,
    onSuccess: () => {
      queryClient.invalidateQueries(['context-files']);
    },
  });
};