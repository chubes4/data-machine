/**
 * Pipeline Checkbox Table Component
 *
 * Reusable table with checkbox selection for pipelines.
 */

import { CheckboxControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Pipeline Checkbox Table Component
 *
 * @param {Object} props - Component props
 * @param {Array} props.pipelines - All available pipelines
 * @param {Array} props.selectedIds - Currently selected pipeline IDs
 * @param {Function} props.onSelectionChange - Selection change handler
 * @returns {React.ReactElement} Pipeline checkbox table
 */
export default function PipelineCheckboxTable( {
	pipelines,
	selectedIds,
	onSelectionChange,
} ) {
	/**
	 * Toggle individual pipeline selection
	 */
	const togglePipeline = ( pipelineId ) => {
		const newSelection = selectedIds.includes( pipelineId )
			? selectedIds.filter( ( id ) => id !== pipelineId )
			: [ ...selectedIds, pipelineId ];

		onSelectionChange( newSelection );
	};

	/**
	 * Toggle all pipelines
	 */
	const toggleAll = () => {
		if ( selectedIds.length === pipelines.length ) {
			onSelectionChange( [] );
		} else {
			onSelectionChange( pipelines.map( ( p ) => p.pipeline_id ) );
		}
	};

	/**
	 * Check if all pipelines are selected
	 */
	const allSelected =
		pipelines.length > 0 && selectedIds.length === pipelines.length;

	return (
		<div
			style={ {
				border: '1px solid #dcdcde',
				borderRadius: '4px',
				maxHeight: '400px',
				overflowY: 'auto',
			} }
		>
			<table style={ { width: '100%', borderCollapse: 'collapse' } }>
				<thead>
					<tr
						style={ {
							background: '#f9f9f9',
							borderBottom: '1px solid #dcdcde',
							position: 'sticky',
							top: 0,
							zIndex: 1,
						} }
					>
						<th
							style={ {
								padding: '12px 16px',
								textAlign: 'left',
								width: '40px',
							} }
						>
							<CheckboxControl
								checked={ allSelected }
								onChange={ toggleAll }
								__nextHasNoMarginBottom
							/>
						</th>
						<th
							style={ {
								padding: '12px 16px',
								textAlign: 'left',
								fontWeight: '600',
							} }
						>
							{ __( 'Pipeline Name', 'datamachine' ) }
						</th>
						<th
							style={ {
								padding: '12px 16px',
								textAlign: 'left',
								fontWeight: '600',
								width: '100px',
							} }
						>
							{ __( 'Steps', 'datamachine' ) }
						</th>
						<th
							style={ {
								padding: '12px 16px',
								textAlign: 'left',
								fontWeight: '600',
								width: '100px',
							} }
						>
							{ __( 'Flows', 'datamachine' ) }
						</th>
					</tr>
				</thead>
				<tbody>
					{ pipelines.length === 0 && (
						<tr>
							<td
								colSpan="4"
								style={ {
									padding: '40px 20px',
									textAlign: 'center',
									color: '#757575',
								} }
							>
								{ __(
									'No pipelines available',
									'datamachine'
								) }
							</td>
						</tr>
					) }

					{ pipelines.map( ( pipeline ) => {
						const stepCount = Object.keys(
							pipeline.pipeline_config || {}
						).length;
						const flowCount = pipeline.flows?.length || 0;
						const isSelected = selectedIds.includes(
							pipeline.pipeline_id
						);

						return (
							<tr
								key={ pipeline.pipeline_id }
								style={ {
									borderBottom: '1px solid #dcdcde',
									background: isSelected
										? '#f0f6fc'
										: 'transparent',
									transition: 'background 0.2s',
								} }
								onMouseEnter={ ( e ) => {
									if ( ! isSelected ) {
										e.currentTarget.style.background =
											'#f9f9f9';
									}
								} }
								onMouseLeave={ ( e ) => {
									if ( ! isSelected ) {
										e.currentTarget.style.background =
											'transparent';
									}
								} }
							>
								<td style={ { padding: '12px 16px' } }>
									<CheckboxControl
										checked={ isSelected }
										onChange={ () =>
											togglePipeline(
												pipeline.pipeline_id
											)
										}
										__nextHasNoMarginBottom
									/>
								</td>
								<td
									style={ {
										padding: '12px 16px',
										fontWeight: '500',
									} }
								>
									{ pipeline.pipeline_name }
								</td>
								<td
									style={ {
										padding: '12px 16px',
										color: '#757575',
									} }
								>
									{ stepCount }
								</td>
								<td
									style={ {
										padding: '12px 16px',
										color: '#757575',
									} }
								>
									{ flowCount }
								</td>
							</tr>
						);
					} ) }
				</tbody>
			</table>
		</div>
	);
}
