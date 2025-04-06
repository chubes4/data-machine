<?php
/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       PLUGIN_URL
 * @since      0.1.0
 *
 * @package    Auto_Data_Collection
 * @subpackage Auto_Data_Collection/admin/partials
 */
?>
<div class="wrap">
    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
    <h2>Process Data</h2>

    <form id="file-processing-form">
        <label for="data_file">Upload File(s):</label>
        <input type="file" id="data_file" name="data_file" multiple>
        <br>
        <label for="starting-index-input" style="margin-top: 10px;">Starting Index (optional):</label>
        <input type="number" id="starting-index-input" name="starting_index" placeholder="Defaults to 1" min="1" style="width: 100px; margin-bottom: 15px;">
        <br><br>
        <button type="submit" id="process-data-button" class="button button-primary">Process Data</button>
    </form>

    <!-- NEW CONTAINER FOR BULK OUTPUT -->
    <div id="bulk-processing-output-container" style="margin-top: 20px;"></div>

    <!-- HIDDEN TEMPLATE -->
    <div id="each-file-processing-results" style="display:none;">
    <h2 class="file-name-header">File: </h2>
    <div id="results-output-section-TEMPLATE" style="margin-top: 20px;">
        <h3>Initial Output:</h3>
        <details>
            <summary>Show/Hide Output</summary>
            <pre><code id="json-output-TEMPLATE">
            {
                "status": "waiting for data processing..."
            }
            </code></pre>
        </details>
        <button id="fact-check-button" class="button button-secondary">Fact Check</button>
    </div>

    <div id="fact-check-results-section-TEMPLATE" style="margin-top: 20px;">
        <h3>Fact-Check Results:</h3>
        <details>
            <summary>Show/Hide Results</summary>
            <textarea id="fact-check-results-TEMPLATE" rows="5" cols="80" placeholder="Fact-check results will appear here..."></textarea>
        </details>
    </div>

    <div id="final-results-output-section-TEMPLATE" style="margin-top: 20px;">
        <h3>Final Output:</h3>
        <pre><code id="final-results-output-TEMPLATE">
        {
            "status": "waiting for final response..."
        }
        </code></pre>
        <button id="finalize-json-button" class="button button-secondary">Finalize</button>
        <button id="copy-final-results-button-TEMPLATE" class="button button-secondary" id="copy-final-results-button-TEMPLATE">Copy</button><span id="copy-success-tooltip-TEMPLATE"></span>
    </div>
    </div>
    <div id="copy-all-results-section" style="margin-top: 10px; display:none;">
        <button id="copy-all-final-results-button" class="button button-primary">Copy All Final Outputs</button><span id="copy-all-success-tooltip"></span>
    </div>

    <div id="error-notices" style="margin-top: 20px;"></div>
</div>
