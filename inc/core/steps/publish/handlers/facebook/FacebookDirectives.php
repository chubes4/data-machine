<?php
/**
 * Facebook-specific AI directive system.
 */

namespace DataMachine\Core\Handlers\Publish\Facebook;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides Facebook-specific AI directives for content generation.
 */
class FacebookDirectives {

    public function __construct() {
        $this->register_directive_filter();
    }

    private function register_directive_filter(): void {
        add_filter('dm_get_output_directive', [$this, 'add_facebook_directives'], 10, 3);
    }

    public function add_facebook_directives(string $directive_block, string $output_type, array $output_config): string {
        if ($output_type !== 'facebook') {
            return $directive_block;
        }
        
        $facebook_config = $output_config['facebook'] ?? [];
        $include_source = $facebook_config['facebook_include_source'] ?? true;
        $enable_images = $facebook_config['facebook_enable_images'] ?? true;
        $posting_type = $facebook_config['facebook_posting_type'] ?? 'page';
        $enable_video = $facebook_config['facebook_enable_video'] ?? false;
        
        $facebook_directives = "\n\n## Facebook Platform Requirements\n\n";
        $facebook_directives .= "15. **Facebook Length Optimization**: While Facebook has no hard character limit, optimize for engagement:\n";
        $facebook_directives .= "   - Keep posts under 400 characters for maximum engagement\n";
        $facebook_directives .= "   - Use 1-2 sentences for peak performance (80-120 characters)\n";
        $facebook_directives .= "   - Longer posts are acceptable for valuable content but may see reduced reach\n";
        if ($include_source) {
            $facebook_directives .= "   - Account for link preview space when including source URLs\n";
        }
        $facebook_directives .= "\n";
        
        $facebook_directives .= "16. **Facebook Engagement Optimization**:\n";
        $facebook_directives .= "   - Write content that encourages likes, comments, and shares\n";
        $facebook_directives .= "   - Ask open-ended questions to drive comment engagement\n";
        $facebook_directives .= "   - Use emotional hooks and relatable content for shareability\n";
        $facebook_directives .= "   - Include calls-to-action that feel natural and valuable\n";
        $facebook_directives .= "   - Leverage storytelling elements to increase time spent reading\n\n";
        
        if ($enable_images) {
            $facebook_directives .= "17. **Facebook Image Integration Strategy**:\n";
            $facebook_directives .= "    - Write content that complements and enhances attached images\n";
            $facebook_directives .= "    - Ensure posts work independently of images for accessibility\n";
            $facebook_directives .= "    - Reference visual content strategically ('See the image above')\n";
            $facebook_directives .= "    - Consider that images significantly boost engagement on Facebook\n";
            $facebook_directives .= "    - Optimize for Facebook's 1200x630px recommended image dimensions\n\n";
        }
        
        $facebook_directives .= "18. **Facebook Hashtag Best Practices**:\n";
        $facebook_directives .= "    - Use 1-2 hashtags maximum (Facebook users prefer fewer hashtags)\n";
        $facebook_directives .= "    - Place hashtags naturally within the text rather than at the end\n";
        $facebook_directives .= "    - Focus on branded hashtags or highly relevant topic hashtags\n";
        $facebook_directives .= "    - Avoid over-hashtagging which appears spammy on Facebook\n";
        $facebook_directives .= "    - Research Facebook-specific hashtag performance for your niche\n\n";
        
        if ($include_source) {
            $facebook_directives .= "19. **Facebook Link Preview Optimization**:\n";
            $facebook_directives .= "    - Write content that complements the automatic link preview\n";
            $facebook_directives .= "    - Don't repeat information that will appear in the link preview card\n";
            $facebook_directives .= "    - Use the post text to provide context or additional value\n";
            $facebook_directives .= "    - Consider that link previews reduce organic reach - provide compelling reasons to click\n\n";
        }
        
        if ($posting_type === 'page') {
            $facebook_directives .= "20. **Facebook Page Posting Strategy**:\n";
            $facebook_directives .= "    - Maintain brand voice and professional tone appropriate for business pages\n";
            $facebook_directives .= "    - Focus on providing value to followers and potential customers\n";
            $facebook_directives .= "    - Include subtle calls-to-action for business objectives\n";
            $facebook_directives .= "    - Consider Facebook's business-focused algorithm preferences\n\n";
        } else {
            $facebook_directives .= "20. **Facebook Personal Profile Strategy**:\n";
            $facebook_directives .= "    - Use more personal, conversational tone appropriate for individual sharing\n";
            $facebook_directives .= "    - Focus on authentic engagement and personal connections\n";
            $facebook_directives .= "    - Share content that reflects personal interests and values\n";
            $facebook_directives .= "    - Consider privacy settings and audience when crafting content\n\n";
        }
        
        if ($enable_video) {
            $facebook_directives .= "21. **Facebook Video Content Integration**:\n";
            $facebook_directives .= "    - Write captions that work for both sound-on and sound-off viewing\n";
            $facebook_directives .= "    - Include compelling hooks in the first 3 seconds description\n";
            $facebook_directives .= "    - Consider that auto-play videos start without sound\n";
            $facebook_directives .= "    - Use text that encourages video completion and engagement\n\n";
        }
        
        $facebook_directives .= "22. **Facebook Audience Engagement Best Practices**:\n";
        $facebook_directives .= "    - Tailor content timing to when your audience is most active\n";
        $facebook_directives .= "    - Use language that encourages meaningful conversations\n";
        $facebook_directives .= "    - Share content that provides genuine value or entertainment\n";
        $facebook_directives .= "    - Balance promotional content with community-building posts\n";
        $facebook_directives .= "    - Respond to comments to boost algorithmic visibility\n\n";
        
        $facebook_directives .= "23. **Facebook Accessibility and Inclusivity**:\n";
        $facebook_directives .= "    - Use clear, inclusive language that welcomes diverse audiences\n";
        $facebook_directives .= "    - Avoid excessive abbreviations or platform-specific jargon\n";
        $facebook_directives .= "    - Consider screen reader compatibility in text formatting\n";
        $facebook_directives .= "    - Use proper capitalization for multi-word hashtags\n";
        $facebook_directives .= "    - Include alt text descriptions when referencing visual content\n\n";
        
        $facebook_directives .= "24. **Facebook Content Timing and Frequency**:\n";
        $facebook_directives .= "    - Write content suitable for Facebook's longer content lifespan\n";
        $facebook_directives .= "    - Consider that Facebook posts can generate engagement for days\n";
        $facebook_directives .= "    - Avoid time-sensitive language unless specifically relevant\n";
        $facebook_directives .= "    - Create evergreen content that remains valuable over time\n";
        
        return $directive_block . $facebook_directives;
    }
}

new FacebookDirectives();