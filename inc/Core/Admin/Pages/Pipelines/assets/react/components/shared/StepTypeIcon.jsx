/**
 * Step Type Icon Component
 *
 * Visual indicator for pipeline step types.
 */

import { getStepTypeDisplay } from '../../utils/formatters';

/**
 * Step Type Icon Component
 *
 * @param {Object} props - Component props
 * @param {string} props.stepType - Step type (fetch, ai, publish, update)
 * @param {number} props.size - Icon size in pixels (default: 24)
 * @returns {React.ReactElement} Step type icon
 */
export default function StepTypeIcon({ stepType, size = 24 }) {
	const display = getStepTypeDisplay(stepType);

	return (
		<span
			className={`datamachine-step-icon datamachine-step-icon--${stepType}`}
			style={{
				fontSize: `${size}px`,
				color: display.color,
				lineHeight: 1,
				display: 'inline-block'
			}}
			title={display.label}
			aria-label={display.label}
		>
			{display.icon}
		</span>
	);
}
