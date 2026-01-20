/**
 * LogsApp Component
 *
 * Root container for the Logs admin page.
 */

/**
 * Internal dependencies
 */
import LogsHeader from './components/LogsHeader';
import LogsTabs from './components/LogsTabs';

const LogsApp = () => {
	return (
		<div className="datamachine-logs-app">
			<LogsHeader />
			<LogsTabs />
		</div>
	);
};

export default LogsApp;
