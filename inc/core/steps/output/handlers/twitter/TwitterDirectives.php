<?php
/**
 * Twitter-specific AI directive system.
 */

namespace DataMachine\Core\Handlers\Output\Twitter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides Twitter-specific AI directives for content generation.
 */
class TwitterDirectives {

    public function __construct() {
        $this->register_directive_filter();
    }

    private function register_directive_filter(): void {
        add_filter('dm_get_output_directive', [$this, 'add_twitter_directives'], 10, 3);
    }

    public function add_twitter_directives(string $directive_block, string $output_type, array $output_config): string {
        if ($output_type !== 'twitter') {
            return $directive_block;
        }
        
        $twitter_config = $output_config['twitter'] ?? [];
        $char_limit = $twitter_config['twitter_char_limit'] ?? 280;
        $include_source = $twitter_config['twitter_include_source'] ?? true;
        $enable_images = $twitter_config['twitter_enable_images'] ?? true;
        
        $twitter_directives = "\n\n## Twitter Platform Requirements\n\n";
        $twitter_directives .= "8. **Character Limit Compliance**: Strictly adhere to the {$char_limit} character limit. ";
        if ($include_source) {
            $twitter_directives .= "Reserve 24 characters for source link (t.co shortened URLs). ";
        }
        $twitter_directives .= "Write concisely and impactfully within this constraint.\n\n";
        
        $twitter_directives .= "9. **Twitter Engagement Optimization**:\n";
        $twitter_directives .= "   - Lead with compelling hooks in the first 100 characters\n";
        $twitter_directives .= "   - Use conversational, direct language that encourages interaction\n";
        $twitter_directives .= "   - Include relevant questions or calls-to-action when appropriate\n";
        $twitter_directives .= "   - Leverage trending topics and current events when contextually relevant\n\n";
        
        $twitter_directives .= "10. **Hashtag and Mention Best Practices**:\n";
        $twitter_directives .= "    - Use 1-3 relevant hashtags maximum (avoid hashtag spam)\n";
        $twitter_directives .= "    - Place hashtags naturally within the text or at the end\n";
        $twitter_directives .= "    - Only use @mentions when directly relevant to the content\n";
        $twitter_directives .= "    - Research popular but not oversaturated hashtags for the topic\n\n";
        
        if ($enable_images) {
            $twitter_directives .= "11. **Image Integration Strategy**:\n";
            $twitter_directives .= "    - Write content that complements potential attached images\n";
            $twitter_directives .= "    - Ensure the tweet works with or without images\n";
            $twitter_directives .= "    - Consider that images may have alt text for accessibility\n";
            $twitter_directives .= "    - Reference visual content when relevant ('as shown in the image')\n\n";
        }
        
        if ($include_source) {
            $twitter_directives .= "12. **Source Link Integration**:\n";
            $twitter_directives .= "    - Write content that naturally leads to 'Read more:' or similar\n";
            $twitter_directives .= "    - Don't repeat the source URL text within the tweet content\n";
            $twitter_directives .= "    - Create curiosity that encourages click-through to source\n\n";
        }
        
        $twitter_directives .= "13. **Twitter Voice and Style Guidelines**:\n";
        $twitter_directives .= "    - Adopt a conversational, authentic tone appropriate for the brand/topic\n";
        $twitter_directives .= "    - Use Twitter-native language patterns (threads, retweets, etc. when relevant)\n";
        $twitter_directives .= "    - Balance professionalism with personality based on content type\n";
        $twitter_directives .= "    - Avoid overly promotional language; focus on value and engagement\n";
        $twitter_directives .= "    - Use emojis sparingly and only when they enhance the message\n\n";
        
        $twitter_directives .= "14. **Accessibility and Inclusivity**:\n";
        $twitter_directives .= "    - Use clear, simple language that's easily understood\n";
        $twitter_directives .= "    - Avoid excessive abbreviations or jargon\n";
        $twitter_directives .= "    - Consider screen reader compatibility in formatting choices\n";
        $twitter_directives .= "    - Use CamelCase for multi-word hashtags (#DataMachine not #datamachine)\n";
        
        return $directive_block . $twitter_directives;
    }
}

new TwitterDirectives();