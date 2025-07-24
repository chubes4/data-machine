=== Data Machine ===
Contributors: chubes
Tags: ai, automation, content, publishing, openai, social media, rss, reddit
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A powerful WordPress plugin that automatically collects data from various sources using OpenAI API, fact-checks it, and publishes the results to multiple platforms.

== Description ==

Data Machine is a comprehensive data processing and publishing solution for WordPress. It can automatically collect data from files, RSS feeds, Reddit, and other sources, process it through OpenAI's API for fact-checking and content generation, and publish the results to multiple platforms including WordPress, Twitter, Facebook, Threads, and Bluesky.

= How It Works =

Data Machine operates through a powerful 5-step automated pipeline:

1. **Data Collection**: Automatically gathers content from your configured sources (RSS feeds, Reddit posts, uploaded files, REST APIs)
2. **AI Processing**: Uses OpenAI to analyze, summarize, and enhance the raw content according to your prompts
3. **Fact-Checking**: Validates information accuracy using AI-powered fact-checking (optional but recommended)
4. **Content Finalization**: Polishes the content for publication with final AI refinements
5. **Multi-Platform Publishing**: Simultaneously publishes the processed content to your selected destinations

All processing happens in the background using reliable job queues, so you can set it up once and let it work automatically. The system handles failures gracefully and provides detailed logging for monitoring.

= Key Benefits =

* **Save Hours of Time**: Process dozens of content pieces in minutes instead of hours of manual work
* **Ensure Quality**: AI-powered fact-checking and content refinement maintains high standards
* **Scale Your Reach**: Publish to multiple social media platforms and websites simultaneously
* **Never Miss Content**: Automated monitoring of RSS feeds and Reddit ensures you catch everything
* **Reliable Operation**: Background processing with failure recovery means your workflows keep running
* **Complete Control**: Customize AI prompts, scheduling, and output formatting to match your needs

= Features =

* **Multi-Source Data Collection**: Import data from files, RSS feeds, Reddit, public REST APIs, and custom airdrop endpoints
* **AI-Powered Processing**: Uses OpenAI API for content generation and fact-checking
* **Multi-Platform Publishing**: Publish to WordPress, Twitter, Facebook, Threads, and Bluesky
* **Scheduled Automation**: Set up automated workflows with custom scheduling
* **Project Management**: Organize your data processing workflows into projects
* **Remote Publishing**: Publish content to remote WordPress installations
* **OAuth Integration**: Secure authentication with social media platforms
* **Import/Export**: Backup and restore your configurations

= Use Cases =

**Content Creators & Bloggers:**
Turn RSS feeds from your favorite sources into polished blog posts. Data Machine monitors feeds, processes content with AI, and publishes to your WordPress site automatically.

**Social Media Managers:**
Streamline cross-platform posting. Upload a content file, let AI optimize it for different platforms, then publish simultaneously to Twitter, Facebook, Threads, and Bluesky.

**News & Media Organizations:**
Monitor Reddit discussions for trending topics, fact-check the information using AI, and publish verified summaries to your news website and social channels.

**Business & Marketing Teams:**
Transform data reports and press releases into engaging social media content. AI processing ensures consistent messaging across all your marketing channels.

**Researchers & Analysts:**
Collect data from multiple REST APIs, process it through AI for insights, and automatically publish findings to both internal WordPress sites and external platforms.

== External Services ==

This plugin connects to several third-party services to provide its functionality. Please review the following information about data transmission:

= OpenAI API =
* **Purpose**: AI-powered content processing, generation, and fact-checking
* **Data Sent**: Text content from your data sources, system prompts, and processing instructions
* **When**: Every time content is processed through the AI pipeline (initial processing, fact-checking, finalization)
* **Service Provider**: OpenAI, Inc.
* **Terms of Service**: https://openai.com/terms
* **Privacy Policy**: https://openai.com/privacy

= Reddit API =
* **Purpose**: Collecting posts and comments from Reddit subreddits as data sources
* **Data Sent**: OAuth credentials, subreddit names, search parameters
* **When**: When Reddit is configured as an input source and jobs are executed
* **Service Provider**: Reddit, Inc.
* **Terms of Service**: https://www.redditinc.com/policies/user-agreement
* **Privacy Policy**: https://www.reddit.com/policies/privacy-policy

= Facebook Graph API =
* **Purpose**: Publishing content to Facebook pages and retrieving page information
* **Data Sent**: OAuth tokens, post content, images, page IDs
* **When**: When Facebook is configured as an output destination and content is published
* **Service Provider**: Meta Platforms, Inc.
* **Terms of Service**: https://developers.facebook.com/terms
* **Privacy Policy**: https://www.facebook.com/privacy/policy

= Threads API =
* **Purpose**: Publishing content to Threads accounts
* **Data Sent**: OAuth tokens, post content, media URLs
* **When**: When Threads is configured as an output destination and content is published
* **Service Provider**: Meta Platforms, Inc.
* **Terms of Service**: https://developers.facebook.com/terms
* **Privacy Policy**: https://www.facebook.com/privacy/policy

= Twitter/X API =
* **Purpose**: Publishing tweets to Twitter/X accounts
* **Data Sent**: OAuth credentials, tweet content, media
* **When**: When Twitter is configured as an output destination and content is published
* **Service Provider**: X Corp.
* **Terms of Service**: https://developer.twitter.com/en/developer-terms/agreement-and-policy
* **Privacy Policy**: https://twitter.com/privacy

= Bluesky AT Protocol =
* **Purpose**: Publishing posts to Bluesky social network
* **Data Sent**: Authentication credentials, post content, handle information
* **When**: When Bluesky is configured as an output destination and content is published
* **Service Provider**: Bluesky Social, PBC
* **Terms of Service**: https://bsky.social/about/support/tos
* **Privacy Policy**: https://bsky.social/about/support/privacy-policy

= Remote WordPress Sites =
* **Purpose**: Publishing content to remote WordPress installations
* **Data Sent**: Authentication credentials, post content, metadata, taxonomy information
* **When**: When remote WordPress publishing is configured and content is published
* **Service Provider**: Third-party WordPress site operators (configured by user)
* **Note**: Terms and privacy policies vary by individual site operator

**Important**: All external API communications use secure HTTPS connections. API keys and sensitive credentials are encrypted before storage in your WordPress database. You are responsible for ensuring compliance with each service's terms and your local data protection regulations.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/data-machine` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Use the Data Machine menu item to configure the plugin
4. Configure your API keys for the services you plan to use
5. Create projects and modules to set up your data processing workflows

= Getting Started =

**Step 1: Configure API Keys**
Go to Data Machine → API Keys and add your OpenAI API key (required). Add keys for social media platforms you want to use (Twitter, Facebook, etc.).

**Step 2: Create Your First Project**
Think of projects as containers for related workflows. Go to Data Machine → Projects and create a project like "Blog Automation" or "Social Media Manager".

**Step 3: Set Up a Module**
Modules define the actual data processing workflow. Each module has:
- **Input Source**: Where data comes from (RSS feed, file upload, Reddit, etc.)
- **AI Processing**: How OpenAI should process the content (custom prompts)
- **Output Destination**: Where to publish (WordPress, Twitter, Facebook, etc.)

**Step 4: Configure Scheduling**
Set up automated scheduling for your modules. Choose from preset intervals (hourly, daily, weekly) or use custom cron schedules.

**Step 5: Monitor & Optimize**
Use Data Machine → Jobs to monitor your automated workflows. Check logs and adjust AI prompts as needed for optimal results.

= Workflow Examples =

**Example 1: RSS to Social Media**
- **Input**: RSS feed from your favorite tech blog
- **Processing**: "Summarize this article in 2 sentences and add relevant hashtags"
- **Output**: Auto-post to Twitter and Facebook
- **Schedule**: Check every hour, post new articles

**Example 2: Reddit Monitoring to Blog**
- **Input**: Monitor specific subreddits for trending posts
- **Processing**: "Analyze this discussion, fact-check claims, and write a 300-word blog post"
- **Output**: Publish to WordPress blog with SEO optimization
- **Schedule**: Daily summary of top discussions

**Example 3: File Processing to Multi-Platform**
- **Input**: Upload weekly newsletter content as a file
- **Processing**: "Adapt this content for different social media platforms"
- **Output**: Customized posts for Twitter (280 chars), Facebook (longer form), and Bluesky
- **Schedule**: Manual trigger when file is uploaded

**Example 4: API Data to Report**
- **Input**: REST API endpoint with business metrics
- **Processing**: "Create an executive summary of this data with key insights"
- **Output**: Post to internal WordPress site and send summary to Slack
- **Schedule**: Weekly automated reports

== Frequently Asked Questions ==

= What are the system requirements? =

* WordPress 5.0 or higher
* PHP 8.0 or higher
* MySQL 5.6 or higher
* OpenAI API key (required for AI processing)

= What third-party libraries are included? =

This plugin includes Action Scheduler (by Automattic) for reliable background job processing. Action Scheduler is a widely-used, battle-tested library that provides robust task scheduling and is used by WooCommerce and many other major WordPress plugins. It ensures that background tasks run reliably even when WordPress cron is unreliable.

= Which social media platforms are supported? =

Currently supports Twitter/X, Facebook, Threads, Bluesky, and Reddit. More platforms are being added regularly.

= Is my data secure? =

Yes, all API keys and sensitive data are encrypted before storage. The plugin follows WordPress security best practices and uses secure HTTPS connections for all external API communications.

= Can I schedule automated posts? =

Yes, you can set up automated workflows with custom scheduling for each project using WordPress cron or the bundled Action Scheduler library.

= Do I need API keys for all services? =

No, you only need API keys for the services you plan to use. The plugin works with any combination of input sources and output destinations.

= Can I use this for commercial purposes? =

Yes, the plugin is licensed under GPL v2 or later. However, ensure you comply with the terms of service of any external APIs you use.

== Screenshots ==

1. Project management dashboard
2. Project configuration interface
3. Module settings and configuration
4. API key management page
5. Job monitoring and logs

== Changelog ==

= 0.1.0 =
* Initial release
* Multi-source data collection (files, RSS, Reddit, public REST APIs, airdrop endpoints)
* AI-powered content processing with OpenAI
* Multi-platform publishing (WordPress, Twitter, Facebook, Threads, Bluesky)
* OAuth integration for social media platforms
* Project and module management system
* Remote WordPress publishing
* Automated scheduling with Action Scheduler
* Import/export functionality
* Comprehensive logging and monitoring
* Job queue management with failure recovery

== Upgrade Notice ==

= 0.1.0 =
Initial release of Data Machine plugin. Requires PHP 8.0+ and WordPress 5.0+.

== Privacy Policy ==

This plugin stores configuration data, API keys (encrypted), and processing logs in your WordPress database. It does not collect or transmit any data about your website visitors. All data sent to external services is limited to what you configure for processing and publishing. See the "External Services" section for details about third-party data transmission.