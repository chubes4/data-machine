/**
 * ModalManager Component
 *
 * Centralized modal rendering logic for the pipelines page.
 * Eliminates repetitive conditional rendering in PipelinesApp.
 */
import { useUIStore } from '../../stores/uiStore';
import { MODAL_TYPES } from '../../utils/constants';
import {
  ImportExportModal,
  StepSelectionModal,
  ConfigureStepModal,
  FlowScheduleModal,
  HandlerSelectionModal,
  HandlerSettingsModal,
  OAuthAuthenticationModal,
  ContextFilesModal,
} from '../modals';

export default function ModalManager({
  pipelines,
  handlers,
  handlerDetails,
  pipelineConfig,
  flows,
  onModalSuccess,
  onHandlerSelected,
  onChangeHandler,
  onOAuthConnect,
}) {
  const { activeModal, modalData, closeModal } = useUIStore();

  if (!activeModal) return null;

  const baseProps = {
    onClose: closeModal,
    ...modalData,
  };

  switch (activeModal) {
    case MODAL_TYPES.IMPORT_EXPORT:
      return (
        <ImportExportModal
          {...baseProps}
          pipelines={pipelines}
          onSuccess={onModalSuccess}
        />
      );

    case MODAL_TYPES.STEP_SELECTION:
      return (
        <StepSelectionModal
          {...baseProps}
          onSuccess={onModalSuccess}
        />
      );

    case MODAL_TYPES.CONFIGURE_STEP:
      return (
        <ConfigureStepModal
          {...baseProps}
          onSuccess={onModalSuccess}
        />
      );

    case MODAL_TYPES.FLOW_SCHEDULE:
      return (
        <FlowScheduleModal
          {...baseProps}
          onSuccess={onModalSuccess}
        />
      );

    case MODAL_TYPES.HANDLER_SELECTION:
      return (
        <HandlerSelectionModal
          {...baseProps}
          onSelectHandler={onHandlerSelected}
          handlers={handlers}
        />
      );

    case MODAL_TYPES.HANDLER_SETTINGS:
      return (
        <HandlerSettingsModal
          {...baseProps}
          handlers={handlers}
          handlerDetails={handlerDetails}
          onSuccess={onModalSuccess}
          onChangeHandler={onChangeHandler}
          onOAuthConnect={onOAuthConnect}
        />
      );

    case MODAL_TYPES.OAUTH:
      return (
        <OAuthAuthenticationModal
          {...baseProps}
          onSuccess={onModalSuccess}
        />
      );

    case MODAL_TYPES.CONTEXT_FILES:
      return <ContextFilesModal {...baseProps} />;

    default:
      console.warn(`Unknown modal type: ${activeModal}`);
      return null;
  }
}