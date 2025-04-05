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
    <h2>Process PDF</h2>

    <form id="pdf-processing-form">
        <label for="pdf_file">Upload PDF:</label>
        <input type="file" id="pdf_file" name="pdf_file" accept=".pdf">
        <br><br>
        <button type="submit" class="button button-primary">Process PDF</button>
    </form>

    <div id="json-output-section" style="margin-top: 20px;">
        <h3>JSON Output:</h3>
        <pre><code id="json-output">
{
    "status": "waiting for PDF processing..."
}
        </code></pre>
        <button id="fact-check-button" class="button button-secondary">Fact Check</button>
    </div>

    <div id="fact-check-results-section" style="margin-top: 20px;">
        <h3>Fact-Check Results:</h3>
        <textarea id="fact-check-results" rows="5" cols="80" placeholder="Fact-check results will appear here..."></textarea>
    </div>

    <div id="final-json-output-section" style="margin-top: 20px;">
        <h3>Final JSON Output:</h3>
        <pre><code id="final-json-output">
{
    "status": "waiting for JSON finalization..."
}
        </code></pre>
        <button id="finalize-json-button" class="button button-secondary">Finalize JSON</button>
        <button id="copy-final-json-button" class="button button-secondary">Copy Final JSON</button><span id="copy-success-tooltip"></span>
    </div>
</div>
