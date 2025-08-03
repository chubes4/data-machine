<?php
/**
 * Bluesky-specific AI directive system.
 */

namespace DataMachine\Core\Handlers\Output\Bluesky;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Provides Bluesky-specific AI directives for content generation.
 */
class BlueskyDirectives {

    public function __construct() {
        $this->register_directive_filter();
    }

    private function register_directive_filter(): void {
        add_filter('dm_get_output_directive', [$this, 'add_bluesky_directives'], 10, 3);
    }

    public function add_bluesky_directives(string $directive_block, string $output_type, array $output_config): string {
        if ($output_type !== 'bluesky') {
            return $directive_block;
        }
        
        $bluesky_config = $output_config['bluesky'] ?? [];
        $include_source = $bluesky_config['bluesky_include_source'] ?? true;
        $enable_images = $bluesky_config['bluesky_enable_images'] ?? true;
        
        // Build handler-specific directive content
        $bluesky_directives = "\n\n## Bluesky Platform Requirements\n\n";
        
        // Character limit guidance (Bluesky uses 300 characters)
        $bluesky_directives .= "8. **Character Limit Compliance**: Strictly adhere to the 300 character limit. ";
        if ($include_source) {
            $bluesky_directives .= "Reserve 24 characters for source link (URLs count as 22 characters + prefix). ";
        }
        $bluesky_directives .= "Write concisely and impactfully within this constraint.\n\n";
        
        // Community engagement focus
        $bluesky_directives .= "9. **Bluesky Community Engagement**:\n";
        $bluesky_directives .= "   - Foster thoughtful discussion and genuine conversation\n";
        $bluesky_directives .= "   - Use a more personal, authentic tone than corporate social media\n";
        $bluesky_directives .= "   - Encourage meaningful responses rather than simple reactions\n";
        $bluesky_directives .= "   - Reference community values like open dialogue and decentralization when relevant\n\n";
        
        // Content style guidelines
        $bluesky_directives .= "10. **Bluesky Content Style**:\n";
        $bluesky_directives .= "    - Prioritize substance over viral appeal\n";
        $bluesky_directives .= "    - Use clear, conversational language that invites discussion\n";
        $bluesky_directives .= "    - Avoid excessive hashtags (1-2 maximum, use sparingly)\n";
        $bluesky_directives .= "    - Focus on adding genuine value to the conversation\n";
        $bluesky_directives .= "    - Embrace longer-form thoughts within the character limit\n\n";
        
        // AT Protocol and decentralization awareness
        $bluesky_directives .= "11. **Platform Culture Alignment**:\n";
        $bluesky_directives .= "    - Respect the platform's focus on user agency and choice\n";
        $bluesky_directives .= "    - Avoid overly promotional or algorithmic-gaming language\n";
        $bluesky_directives .= "    - Embrace the platform's experimental and community-driven nature\n";
        $bluesky_directives .= "    - Consider the technical-savvy audience when appropriate\n\n";
        
        // Image handling guidance
        if ($enable_images) {
            $bluesky_directives .= "12. **Image Integration Strategy**:\n";
            $bluesky_directives .= "    - Write content that enhances and contextualizes attached images\n";
            $bluesky_directives .= "    - Ensure the post provides value even without images\n";
            $bluesky_directives .= "    - Consider accessibility by describing key visual elements in text\n";
            $bluesky_directives .= "    - Use images to support deeper conversation rather than just visual appeal\n\n";
        }
        
        // Link handling for AT Protocol
        if ($include_source) {
            $bluesky_directives .= "13. **Source Link Integration**:\n";
            $bluesky_directives .= "    - Provide clear context for why the source is worth reading\n";
            $bluesky_directives .= "    - Create genuine curiosity rather than clickbait\n";
            $bluesky_directives .= "    - Consider that Bluesky users value thoughtful curation\n";
            $bluesky_directives .= "    - Frame links as contributions to ongoing discussions\n\n";
        }
        
        // Bluesky-specific voice and tone
        $bluesky_directives .= "14. **Bluesky Voice Guidelines**:\n";
        $bluesky_directives .= "    - Adopt a thoughtful, intellectually curious tone\n";
        $bluesky_directives .= "    - Balance expertise with humility and openness to dialogue\n";
        $bluesky_directives .= "    - Use precise language that respects the audience's intelligence\n";
        $bluesky_directives .= "    - Avoid social media clichés and focus on authentic communication\n";
        $bluesky_directives .= "    - Embrace nuance and complexity when the topic warrants it\n\n";
        
        // Threading and conversation context
        $bluesky_directives .= "15. **Conversation Integration**:\n";
        $bluesky_directives .= "    - Write posts that could naturally spark follow-up discussions\n";
        $bluesky_directives .= "    - Consider how the content fits into broader ongoing conversations\n";
        $bluesky_directives .= "    - Use threading mindset even for single posts\n";
        $bluesky_directives .= "    - Include enough context for standalone understanding\n";
        
        // Return the enhanced directive block
        return $directive_block . $bluesky_directives;
    }
}

new BlueskyDirectives();