<?php
/**
 * WordPress-specific AI directive system.
 *
 * Demonstrates the standard pattern that third-party developers should use
 * to extend the Data Machine directive system. This file provides WordPress publishing-specific
 * AI guidance using the universal dm_get_output_directive filter.
 *
 * THIRD-PARTY DEVELOPER REFERENCE:
 * This implementation serves as the canonical example of how external plugins
 * should integrate with the Data Machine directive system. Use this exact
 * pattern in your own handler extensions.
 *
 * @package    Data_Machine
 * @subpackage Data_Machine/core/handlers/output/wordpress
 * @since      NEXT_VERSION
 */

namespace DataMachine\Core\Handlers\Output\WordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * WordPressDirectives class
 *
 * Provides WordPress publishing-specific AI directives for content generation.
 * This class demonstrates the standard extension pattern that all
 * third-party developers should follow when adding directive support
 * for custom output handlers.
 */
class WordPressDirectives {

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
     * in your own directive extensions. Replace 'publish_local' and 'publish_remote' 
     * with your handler's output types.
     */
    private function register_directive_filter(): void {
        add_filter('dm_get_output_directive', [$this, 'add_wordpress_directives'], 10, 3);
    }

    /**
     * Add WordPress publishing-specific AI directives when generating content for WordPress output.
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
     * @return string Modified directive block with WordPress publishing-specific guidance
     */
    public function add_wordpress_directives(string $directive_block, string $output_type, array $output_config): string {
        // CRITICAL: Only act when output type matches your handler
        // Third-party developers: Replace with your handler's types
        if ($output_type !== 'publish_local' && $output_type !== 'publish_remote') {
            return $directive_block;
        }
        
        // Extract handler-specific configuration from output_config
        // Third-party developers: Adjust this path to match your handler's config structure
        $wordpress_config = $output_config['wordpress'] ?? [];
        $use_gutenberg = ($wordpress_config['use_gutenberg_blocks'] ?? '1') === '1';
        $destination_type = $wordpress_config['destination_type'] ?? 'local';
        $post_type = $wordpress_config['post_type'] ?? $wordpress_config['selected_remote_post_type'] ?? 'post';
        $post_status = $wordpress_config['post_status'] ?? $wordpress_config['remote_post_status'] ?? 'draft';
        
        // Build handler-specific directive content
        $wordpress_directives = "\n\n## WordPress Publishing Platform Requirements\n\n";
        
        // Gutenberg block optimization guidance
        if ($use_gutenberg) {
            $wordpress_directives .= "8. **Gutenberg Block Markup Optimization**: Structure content using proper Gutenberg block markup. ";
            $wordpress_directives .= "Use semantic blocks like `<!-- wp:paragraph -->`, `<!-- wp:heading -->`, `<!-- wp:list -->`, and `<!-- wp:quote -->` ";
            $wordpress_directives .= "to create well-structured, accessible content that leverages WordPress's full editing capabilities.\n\n";
        } else {
            $wordpress_directives .= "8. **Classic Editor Compatibility**: Format content for the classic WordPress editor using standard HTML markup. ";
            $wordpress_directives .= "Ensure clean, semantic HTML that works well in both classic and block editors.\n\n";
        }
        
        // WordPress SEO best practices
        $wordpress_directives .= "9. **WordPress SEO Excellence**:\n";
        $wordpress_directives .= "   - Create compelling, keyword-focused titles (50-60 characters optimal)\n";
        $wordpress_directives .= "   - Use proper heading hierarchy (H2, H3, H4) for content structure\n";
        $wordpress_directives .= "   - Write scannable content with short paragraphs and bullet points\n";
        $wordpress_directives .= "   - Include semantic keywords naturally throughout the content\n";
        $wordpress_directives .= "   - Optimize for featured snippets when applicable\n\n";
        
        // Content accessibility guidelines
        $wordpress_directives .= "10. **WordPress Accessibility Standards**:\n";
        $wordpress_directives .= "    - Use descriptive heading text that accurately represents content sections\n";
        $wordpress_directives .= "    - Write clear, concise language at an appropriate reading level\n";
        $wordpress_directives .= "    - Structure content logically with proper heading hierarchy\n";
        $wordpress_directives .= "    - Use descriptive link text that makes sense out of context\n";
        $wordpress_directives .= "    - Ensure content works well with screen readers\n\n";
        
        // Post title optimization
        $wordpress_directives .= "11. **WordPress Title Optimization**:\n";
        $wordpress_directives .= "    - Craft titles that are both SEO-friendly and engaging to readers\n";
        $wordpress_directives .= "    - Include primary keywords near the beginning when natural\n";
        $wordpress_directives .= "    - Create emotional appeal or curiosity while remaining accurate\n";
        $wordpress_directives .= "    - Ensure titles work well in social media shares and search results\n";
        $wordpress_directives .= "    - Consider the target audience and content purpose\n\n";
        
        // Internal linking recommendations
        $wordpress_directives .= "12. **WordPress Internal Linking Strategy**:\n";
        $wordpress_directives .= "    - Suggest relevant internal links where contextually appropriate\n";
        $wordpress_directives .= "    - Use descriptive anchor text that indicates the linked content\n";
        $wordpress_directives .= "    - Balance user experience with SEO benefits\n";
        $wordpress_directives .= "    - Link to cornerstone content and related posts when relevant\n\n";
        
        // Content hierarchy best practices
        $wordpress_directives .= "13. **WordPress Content Hierarchy Excellence**:\n";
        $wordpress_directives .= "    - Structure content with clear introduction, body, and conclusion\n";
        $wordpress_directives .= "    - Use subheadings to break up long sections and improve readability\n";
        $wordpress_directives .= "    - Create logical flow that guides readers through the content\n";
        $wordpress_directives .= "    - Include clear takeaways and actionable insights\n";
        $wordpress_directives .= "    - End with compelling conclusions or calls-to-action\n\n";
        
        // Image accessibility guidance
        $wordpress_directives .= "14. **WordPress Image Accessibility Guidelines**:\n";
        $wordpress_directives .= "    - Reference images meaningfully within the content flow\n";
        $wordpress_directives .= "    - Describe visual elements that support the content narrative\n";
        $wordpress_directives .= "    - Consider that images may have descriptive alt text for accessibility\n";
        $wordpress_directives .= "    - Use images to break up text and enhance user engagement\n";
        $wordpress_directives .= "    - Ensure content works effectively with or without images\n\n";
        
        // WordPress-specific formatting standards
        $wordpress_directives .= "15. **WordPress Formatting Standards**:\n";
        $wordpress_directives .= "    - Use WordPress-native features like excerpts, custom fields, and taxonomies\n";
        $wordpress_directives .= "    - Format content for optimal display in various WordPress themes\n";
        $wordpress_directives .= "    - Consider mobile responsiveness and cross-device compatibility\n";
        $wordpress_directives .= "    - Use proper paragraph spacing and visual hierarchy\n";
        $wordpress_directives .= "    - Optimize content length for the post type and audience\n\n";
        
        // Schema markup considerations
        $wordpress_directives .= "16. **Schema Markup Optimization**:\n";
        $wordpress_directives .= "    - Structure content to support rich snippets and knowledge panels\n";
        $wordpress_directives .= "    - Use clear article structure with definitive sections\n";
        $wordpress_directives .= "    - Include relevant publication information and author context\n";
        $wordpress_directives .= "    - Format lists, tables, and FAQs for enhanced search visibility\n\n";
        
        // WordPress performance considerations
        $wordpress_directives .= "17. **WordPress Performance Excellence**:\n";
        $wordpress_directives .= "    - Write efficient, clean content that loads quickly\n";
        $wordpress_directives .= "    - Avoid excessive formatting or complex structures\n";
        $wordpress_directives .= "    - Consider content length appropriate for the topic and audience\n";
        $wordpress_directives .= "    - Structure content for fast scanning and comprehension\n";
        $wordpress_directives .= "    - Balance comprehensive coverage with readability\n\n";
        
        // Destination-specific guidance
        if ($destination_type === 'local') {
            $wordpress_directives .= "18. **Local WordPress Integration**:\n";
            $wordpress_directives .= "    - Leverage local site's existing content themes and style\n";
            $wordpress_directives .= "    - Consider the site's established voice and audience\n";
            $wordpress_directives .= "    - Integrate naturally with the existing content ecosystem\n\n";
        } else {
            $wordpress_directives .= "18. **Remote WordPress Publishing**:\n";
            $wordpress_directives .= "    - Create self-contained content that works across different WordPress sites\n";
            $wordpress_directives .= "    - Avoid site-specific references or assumptions\n";
            $wordpress_directives .= "    - Ensure content is universally accessible and well-formatted\n\n";
        }
        
        // Post type specific guidance
        if ($post_type === 'page') {
            $wordpress_directives .= "19. **WordPress Page Content Strategy**:\n";
            $wordpress_directives .= "    - Create evergreen content suitable for static page format\n";
            $wordpress_directives .= "    - Focus on comprehensive, authoritative information\n";
            $wordpress_directives .= "    - Structure content for long-term value and reference\n";
            $wordpress_directives .= "    - Include clear navigation and user-focused organization\n";
        } else {
            $wordpress_directives .= "19. **WordPress Post Content Strategy**:\n";
            $wordpress_directives .= "    - Create timely, engaging content appropriate for blog format\n";
            $wordpress_directives .= "    - Include current context and relevant date references\n";
            $wordpress_directives .= "    - Encourage reader engagement and social sharing\n";
            $wordpress_directives .= "    - Balance timeliness with lasting value\n";
        }
        
        // Return the enhanced directive block
        return $directive_block . $wordpress_directives;
    }
}

// THIRD-PARTY DEVELOPER REFERENCE:
// This is the standard instantiation pattern for directive extensions.
// Simply instantiate your directive class - the constructor handles filter registration.
new WordPressDirectives();