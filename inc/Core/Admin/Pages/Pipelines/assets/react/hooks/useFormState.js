/**
 * Form State Hook
 *
 * Eliminates repetitive form state management boilerplate.
 * Provides consistent loading, error, and success state handling.
 */
import { useState, useCallback } from '@wordpress/element';

/**
 * Generic form state management hook
 *
 * @param {Object} options Configuration options
 * @param {any} options.initialData Initial form data
 * @param {Function} options.validate Validation function
 * @param {Function} options.onSubmit Submit handler
 * @returns {Object} Form state and handlers
 */
export const useFormState = ({
  initialData = {},
  validate,
  onSubmit
} = {}) => {
  const [data, setData] = useState(initialData);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);

  const updateField = useCallback((field, value) => {
    setData(prev => ({
      ...prev,
      [field]: value
    }));
    // Clear errors when user starts typing
    if (error) setError(null);
  }, [error]);

  const updateData = useCallback((newData) => {
    setData(prev => ({
      ...prev,
      ...newData
    }));
    // Clear errors when data changes
    if (error) setError(null);
  }, [error]);

  const reset = useCallback(() => {
    setData(initialData);
    setError(null);
    setSuccess(null);
    setIsSubmitting(false);
  }, [initialData]);

  const submit = useCallback(async () => {
    if (isSubmitting) return;

    setError(null);
    setSuccess(null);

    // Validation
    if (validate) {
      const validationError = validate(data);
      if (validationError) {
        setError(validationError);
        return;
      }
    }

    setIsSubmitting(true);

    try {
      const result = await onSubmit(data);
      setSuccess(result || true);
      return result;
    } catch (err) {
      setError(err.message || 'An error occurred');
      throw err;
    } finally {
      setIsSubmitting(false);
    }
  }, [data, isSubmitting, validate, onSubmit]);

  return {
    // State
    data,
    isSubmitting,
    error,
    success,

    // Actions
    updateField,
    updateData,
    reset,
    submit,
    setError,
    setSuccess,
  };
};

/**
 * Async Operation Hook
 *
 * For non-form async operations (API calls, file uploads, etc.)
 */
export const useAsyncOperation = () => {
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);

  const execute = useCallback(async (operation) => {
    if (isLoading) return;

    setError(null);
    setSuccess(null);
    setIsLoading(true);

    try {
      const result = await operation();
      setSuccess(result || true);
      return result;
    } catch (err) {
      setError(err.message || 'An error occurred');
      throw err;
    } finally {
      setIsLoading(false);
    }
  }, [isLoading]);

  const reset = useCallback(() => {
    setIsLoading(false);
    setError(null);
    setSuccess(null);
  }, []);

  return {
    isLoading,
    error,
    success,
    execute,
    reset,
    setError,
    setSuccess,
  };
};

/**
 * File Upload Hook
 *
 * Specialized hook for file upload operations
 */
export const useFileUpload = () => {
  const [isUploading, setIsUploading] = useState(false);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);

  const upload = useCallback(async (uploadFn) => {
    if (isUploading) return;

    setError(null);
    setSuccess(null);
    setIsUploading(true);

    try {
      const result = await uploadFn();
      setSuccess(result || true);
      return result;
    } catch (err) {
      setError(err.message || 'Upload failed');
      throw err;
    } finally {
      setIsUploading(false);
    }
  }, [isUploading]);

  const reset = useCallback(() => {
    setIsUploading(false);
    setError(null);
    setSuccess(null);
  }, []);

  return {
    isUploading,
    error,
    success,
    upload,
    reset,
    setError,
    setSuccess,
  };
};

/**
 * Drag and Drop Hook
 *
 * For drag and drop file operations
 */
export const useDragDrop = () => {
  const [isDragging, setIsDragging] = useState(false);
  const [error, setError] = useState(null);

  const handleDragEnter = useCallback((e) => {
    e.preventDefault();
    e.stopPropagation();
    setIsDragging(true);
  }, []);

  const handleDragLeave = useCallback((e) => {
    e.preventDefault();
    e.stopPropagation();
    setIsDragging(false);
  }, []);

  const handleDragOver = useCallback((e) => {
    e.preventDefault();
    e.stopPropagation();
  }, []);

  const handleDrop = useCallback((e, onFilesDropped) => {
    e.preventDefault();
    e.stopPropagation();
    setIsDragging(false);
    setError(null);

    const files = Array.from(e.dataTransfer.files);
    if (files.length === 0) return;

    try {
      onFilesDropped(files);
    } catch (err) {
      setError(err.message || 'File processing failed');
    }
  }, []);

  const reset = useCallback(() => {
    setIsDragging(false);
    setError(null);
  }, []);

  return {
    isDragging,
    error,
    handleDragEnter,
    handleDragLeave,
    handleDragOver,
    handleDrop,
    reset,
    setError,
  };
};