<?php
/**
 * Google Sheets-specific AI directive system.
 *
 * Demonstrates the standard pattern that third-party developers should use
 * to extend the Data Machine directive system. This file provides Google Sheets-specific
 * AI guidance using the universal dm_get_output_directive filter.
 *
 * THIRD-PARTY DEVELOPER REFERENCE:
 * This implementation serves as the canonical example of how external plugins
 * should integrate with the Data Machine directive system. Use this exact
 * pattern in your own handler extensions.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/core/handlers/output/googlesheets
 * @since      NEXT_VERSION
 */

namespace DataMachine\Core\Handlers\Output\GoogleSheets;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * GoogleSheetsDirectives class
 *
 * Provides Google Sheets-specific AI directives for structured data output.
 * This class demonstrates the standard extension pattern that all
 * third-party developers should follow when adding directive support
 * for custom output handlers.
 */
class GoogleSheetsDirectives {

    /**
     * Constructor - parameter-less for pure filter-based architecture
     */
    public function __construct() {
        // No dependencies initialized in constructor for pure filter-based architecture
        $this->register_directive_filter();
    }

    /**
     * Register the directive filter using the standard extension pattern.
     *
     * THIRD-PARTY DEVELOPER NOTE:
     * This is the exact method signature and pattern you should use
     * in your own directive extensions. Replace 'googlesheets' with your
     * handler's output type.
     */
    private function register_directive_filter(): void {
        add_filter('dm_get_output_directive', [$this, 'add_googlesheets_directives'], 10, 3);
    }

    /**
     * Add Google Sheets-specific AI directives when generating content for Google Sheets output.
     * 
     * THIRD-PARTY DEVELOPER REFERENCE:
     * This method demonstrates the standard pattern for extending AI directives:
     * 1. Check if the output_type matches your handler
     * 2. Extract handler-specific configuration
     * 3. Build directive content based on configuration
     * 4. Return the enhanced directive block
     * 
     * @param string $directive_block Current directive content
     * @param string $output_type The output type being processed
     * @param array $output_config Configuration for the output step
     * @return string Modified directive block with Google Sheets-specific guidance
     */
    public function add_googlesheets_directives(string $directive_block, string $output_type, array $output_config): string {
        // CRITICAL: Only act when output type matches your handler
        // Third-party developers: Replace 'googlesheets' with your handler's type
        if ($output_type !== 'googlesheets') {
            return $directive_block;
        }
        
        // Extract handler-specific configuration from output_config
        // Third-party developers: Adjust this path to match your handler's config structure
        $sheets_config = $output_config['googlesheets'] ?? [];
        $worksheet_name = $sheets_config['googlesheets_worksheet_name'] ?? 'Data Machine Output';
        $column_mapping = $sheets_config['googlesheets_column_mapping'] ?? [];
        
        // Build handler-specific directive content
        $sheets_directives = "\n\n## Google Sheets Data Structure Requirements\n\n";
        
        // Structured data formatting guidance
        $sheets_directives .= "8. **Structured Data Output**: Format your response for optimal spreadsheet storage:\n";
        $sheets_directives .= "   - Create clear, concise titles that work well as row headers\n";
        $sheets_directives .= "   - Structure content for easy scanning and analysis\n";
        $sheets_directives .= "   - Ensure consistent formatting across similar content types\n";
        $sheets_directives .= "   - Use data-friendly language (avoid special characters that break CSV)\n\n";
        
        // Content organization for analytics
        $sheets_directives .= "9. **Analytics and Reporting Focus**:\n";
        $sheets_directives .= "   - Prioritize key information that would be valuable in reports\n";
        $sheets_directives .= "   - Create content that can be easily categorized and filtered\n";
        $sheets_directives .= "   - Include actionable insights rather than just descriptions\n";
        $sheets_directives .= "   - Consider how this data will be used for decision-making\n\n";
        
        // Data consistency and quality
        $sheets_directives .= "10. **Data Quality and Consistency**:\n";
        $sheets_directives .= "    - Use consistent terminology and formatting across entries\n";
        $sheets_directives .= "    - Avoid HTML tags, excessive punctuation, or formatting markup\n";
        $sheets_directives .= "    - Create content that maintains meaning when exported to CSV\n";
        $sheets_directives .= "    - Structure information hierarchically (main point → supporting details)\n\n";
        
        // Column-specific guidance
        if (!empty($column_mapping)) {
            $sheets_directives .= "11. **Column-Specific Content Guidelines**:\n";
            
            foreach ($column_mapping as $column => $field_name) {
                switch ($field_name) {
                    case 'title':
                        $sheets_directives .= "    - **Column {$column} (Title)**: Create clear, searchable headlines (50-100 chars)\n";
                        break;
                    case 'content':
                        $sheets_directives .= "    - **Column {$column} (Content)**: Provide comprehensive but concise summaries\n";
                        break;
                    case 'source_url':
                        $sheets_directives .= "    - **Column {$column} (Source)**: Ensure URL accuracy for reference tracking\n"; 
                        break;
                    case 'source_type':
                        $sheets_directives .= "    - **Column {$column} (Type)**: Content will be automatically categorized by source\n";
                        break;
                }
            }
            $sheets_directives .= "\n";
        }
        
        // Business intelligence considerations
        $sheets_directives .= "12. **Business Intelligence Optimization**:\n";
        $sheets_directives .= "    - Write content that reveals trends and patterns\n";
        $sheets_directives .= "    - Include quantifiable elements where relevant (numbers, percentages, metrics)\n";
        $sheets_directives .= "    - Create content that can be easily grouped and compared\n";
        $sheets_directives .= "    - Consider seasonal, temporal, or categorical analysis potential\n\n";
        
        // Collaboration and sharing context
        $sheets_directives .= "13. **Team Collaboration Context**:\n";
        $sheets_directives .= "    - Write content that provides context for team members who weren't involved in the original research\n";
        $sheets_directives .= "    - Include enough detail for stakeholders to understand key points quickly\n";
        $sheets_directives .= "    - Structure information for easy discussion and decision-making\n";
        $sheets_directives .= "    - Consider how different team roles might use this data\n\n";
        
        // Data export and integration readiness
        $sheets_directives .= "14. **Export and Integration Ready Format**:\n";
        $sheets_directives .= "    - Create content that works well when imported into other business tools\n";
        $sheets_directives .= "    - Avoid formatting that depends on spreadsheet-specific features\n";
        $sheets_directives .= "    - Structure data for potential API integrations or automated processing\n";
        $sheets_directives .= "    - Maintain data integrity across different viewing contexts\n\n";
        
        // Worksheet-specific guidance
        $sheets_directives .= "15. **Worksheet Integration ('{$worksheet_name}' Target)**:\n";
        $sheets_directives .= "    - Create content that fits logically within the existing worksheet structure\n";
        $sheets_directives .= "    - Consider how new entries will relate to existing data patterns\n";
        $sheets_directives .= "    - Maintain consistency with the worksheet's intended purpose\n";
        $sheets_directives .= "    - Structure content for easy sorting, filtering, and analysis within Google Sheets\n";
        
        // Return the enhanced directive block
        return $directive_block . $sheets_directives;
    }
}

// THIRD-PARTY DEVELOPER REFERENCE:
// This is the standard instantiation pattern for directive extensions.
// Simply instantiate your directive class - the constructor handles filter registration.
new GoogleSheetsDirectives();