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
    onMutate: async (name) => {
      await queryClient.cancelQueries({ queryKey: ['pipelines'] });

      const previousPipelines = queryClient.getQueryData(['pipelines']);
      const optimisticPipelineId = `optimistic_${Date.now()}`;

      queryClient.setQueryData(['pipelines'], (old = []) => [
        {
          pipeline_id: optimisticPipelineId,
          pipeline_name: name,
          pipeline_config: {},
        },
        ...old,
      ]);

      return { previousPipelines, optimisticPipelineId };
    },
    onError: (_err, _name, context) => {
      if (context?.previousPipelines) {
        queryClient.setQueryData(['pipelines'], context.previousPipelines);
      }
    },
    onSuccess: (response, _name, context) => {
      const pipeline = response?.data?.pipeline_data;

      if (pipeline && context?.optimisticPipelineId) {
        queryClient.setQueryData(['pipelines'], (old = []) =>
          old.map((p) =>
            String(p.pipeline_id) === String(context.optimisticPipelineId) ? pipeline : p
          )
        );
      }

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