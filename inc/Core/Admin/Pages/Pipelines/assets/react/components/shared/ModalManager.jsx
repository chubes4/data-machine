/**
 * ModalManager Component
 *
 * Centralized modal rendering logic for the pipelines page.
 * Eliminates repetitive conditional rendering in PipelinesApp.
 */
/**
 * Internal dependencies
 */
import { useUIStore } from '../../stores/uiStore';
import { MODAL_TYPES } from '../../utils/constants';
import ModalSwitch from './ModalSwitch';
import { HandlerProvider } from '../../context/HandlerProvider';

export default function ModalManager( {
	pipelines,
	handlers,
	handlerDetails,
	pipelineConfig,
	flows,
	onModalSuccess,
	onHandlerSelected,
	onChangeHandler,
	onOAuthConnect,
	onBackToSettings,
} ) {
	const { activeModal, modalData, closeModal } = useUIStore();

	if ( ! activeModal ) {return null;}

	const baseProps = {
		onClose: closeModal,
		...modalData,
		onSuccess: onModalSuccess,
		handlers,
		handlerDetails: modalData.handlerDetails ?? handlerDetails,
		onChangeHandler,
		onOAuthConnect,
		onBackToSettings,
		onSelectHandler: onHandlerSelected,
	};

	return (
		<HandlerProvider>
			<ModalSwitch
				activeModal={ activeModal }
				baseProps={ baseProps }
				modalData={ modalData }
			/>
		</HandlerProvider>
	);
}
