<?php
/**
 * Google Search Console AI Tool
 *
 * Provides Google Search Console analysis capabilities for SEO optimization.
 * This is a general tool available to all AI steps for analyzing search performance,
 * finding keyword opportunities, and suggesting content improvements.
 *
 * @package DataMachine\Core\Steps\AI\Tools
 * @since 1.0.0
 */

namespace DataMachine\Core\Steps\AI\Tools;

// Prevent direct access
defined('ABSPATH') || exit;

/**
 * Google Search Console Tool Implementation
 * 
 * Integrates with Google Search Console API to provide SEO analysis and optimization
 * recommendations to AI steps. Works with Local Search tool for internal linking.
 */
class GoogleSearchConsole {

    /**
     * Handle tool call from AI model
     * 
     * @param array $parameters Tool call parameters from AI model
     * @param array $tool_def Tool definition (unused but required for interface)
     * @return array Standardized tool response
     */
    public function handle_tool_call(array $parameters, array $tool_def = []): array {
        
        // Validate required parameters
        if (empty($parameters['page_url'])) {
            return [
                'success' => false,
                'error' => 'Google Search Console tool call missing required page_url parameter',
                'tool_name' => 'google_search_console'
            ];
        }

        // Get authenticated GSC client
        $all_auth = apply_filters('dm_auth_providers', []);
        $auth_service = $all_auth['google_search_console'] ?? null;
        
        if (!$auth_service) {
            return [
                'success' => false,
                'error' => 'Google Search Console authentication service not available',
                'tool_name' => 'google_search_console'
            ];
        }

        $client = $auth_service->get_connection();
        if (is_wp_error($client)) {
            return [
                'success' => false,
                'error' => 'Google Search Console authentication failed: ' . $client->get_error_message(),
                'tool_name' => 'google_search_console'
            ];
        }

        // Extract parameters with defaults
        $page_url = esc_url_raw($parameters['page_url']);
        $analysis_type = sanitize_text_field($parameters['analysis_type'] ?? 'performance');
        $date_range = sanitize_text_field($parameters['date_range'] ?? '30d');
        $include_internal_links = !empty($parameters['include_internal_links']);
        
        try {
            // Initialize Search Console service
            $service = new \Google\Service\SearchConsole($client);
            
            // Get site URL for the domain
            $site_url = $this->get_site_url_for_page($page_url, $service);
            if (is_wp_error($site_url)) {
                return [
                    'success' => false,
                    'error' => $site_url->get_error_message(),
                    'tool_name' => 'google_search_console'
                ];
            }

            // Perform analysis based on type
            switch ($analysis_type) {
                case 'performance':
                    $result = $this->analyze_page_performance($service, $site_url, $page_url, $date_range);
                    break;
                    
                case 'keywords':
                    $result = $this->get_page_keywords($service, $site_url, $page_url, $date_range);
                    break;
                    
                case 'opportunities':
                    $result = $this->find_keyword_opportunities($service, $site_url, $page_url, $date_range);
                    break;
                    
                case 'internal_links':
                    $result = $this->suggest_internal_links($service, $site_url, $page_url, $date_range);
                    break;
                    
                default:
                    return [
                        'success' => false,
                        'error' => 'Invalid analysis type. Use: performance, keywords, opportunities, or internal_links',
                        'tool_name' => 'google_search_console'
                    ];
            }

            // Add internal linking suggestions if requested and not already the analysis type
            if ($include_internal_links && $analysis_type !== 'internal_links') {
                $internal_links = $this->suggest_internal_links($service, $site_url, $page_url, $date_range);
                if (!is_wp_error($internal_links)) {
                    $result['internal_linking_suggestions'] = $internal_links['internal_linking_suggestions'] ?? [];
                }
            }

            return [
                'success' => true,
                'data' => $result,
                'tool_name' => 'google_search_console'
            ];

        } catch (\Exception $e) {
            do_action('dm_log', 'error', 'Google Search Console API error: ' . $e->getMessage(), [
                'page_url' => $page_url,
                'analysis_type' => $analysis_type
            ]);
            
            return [
                'success' => false,
                'error' => 'Google Search Console API error: ' . $e->getMessage(),
                'tool_name' => 'google_search_console'
            ];
        }
    }
    
    /**
     * Get the appropriate Search Console site URL for a given page URL
     * 
     * @param string $page_url The page URL to analyze
     * @param \Google\Service\SearchConsole $service GSC service instance
     * @return string|\WP_Error Site URL or error
     */
    private function get_site_url_for_page($page_url, $service) {
        try {
            $sites_list = $service->sites->listSites();
            $parsed_page = parse_url($page_url);
            $page_domain = $parsed_page['host'] ?? '';
            
            $best_match = null;
            $best_match_length = 0;
            
            foreach ($sites_list->getSiteEntry() as $site) {
                if ($site->getPermissionLevel() === 'siteUnverifiedUser') {
                    continue;
                }
                
                $site_url = $site->getSiteUrl();
                
                // Check for domain property match (sc-domain:example.com)
                if (strpos($site_url, 'sc-domain:') === 0) {
                    $domain = str_replace('sc-domain:', '', $site_url);
                    if ($domain === $page_domain || strpos($page_domain, $domain) !== false) {
                        return $site_url;
                    }
                }
                
                // Check for URL prefix match (https://example.com/)
                if (strpos($page_url, rtrim($site_url, '/')) === 0) {
                    $match_length = strlen($site_url);
                    if ($match_length > $best_match_length) {
                        $best_match = $site_url;
                        $best_match_length = $match_length;
                    }
                }
            }
            
            if ($best_match) {
                return $best_match;
            }
            
            return new \WP_Error('gsc_no_matching_site', sprintf(
                __('No matching Search Console property found for %s. Please verify the domain in Search Console.', 'data-machine'),
                $page_domain
            ));
            
        } catch (\Exception $e) {
            return new \WP_Error('gsc_site_lookup_error', __('Could not retrieve Search Console sites.', 'data-machine'));
        }
    }
    
    /**
     * Analyze page performance metrics
     * 
     * @param \Google\Service\SearchConsole $service GSC service instance
     * @param string $site_url Site URL from Search Console
     * @param string $page_url Page URL to analyze
     * @param string $date_range Date range for analysis
     * @return array Performance analysis data
     */
    private function analyze_page_performance($service, $site_url, $page_url, $date_range) {
        $dates = $this->get_date_range($date_range);
        
        $request = new \Google\Service\SearchConsole\SearchAnalyticsQueryRequest();
        $request->setStartDate($dates['start']);
        $request->setEndDate($dates['end']);
        $request->setDimensions(['query']);
        $request->setRowLimit(25);
        
        // Filter by specific page
        $dimension_filter = new \Google\Service\SearchConsole\SearchAnalyticsQueryRequestDimensionFilterGroups();
        $filter = new \Google\Service\SearchConsole\SearchAnalyticsQueryRequestDimensionFilter();
        $filter->setDimension('page');
        $filter->setOperator('equals');
        $filter->setExpression($page_url);
        $dimension_filter->setFilters([$filter]);
        $request->setDimensionFilterGroups([$dimension_filter]);
        
        $response = $service->searchanalytics->query($site_url, $request);
        
        $total_clicks = 0;
        $total_impressions = 0;
        $position_sum = 0;
        $keyword_count = 0;
        $top_keywords = [];
        
        if ($response->getRows()) {
            foreach ($response->getRows() as $row) {
                $clicks = $row->getClicks();
                $impressions = $row->getImpressions();
                $ctr = $row->getCtr();
                $position = $row->getPosition();
                $keyword = $row->getKeys()[0] ?? '';
                
                $total_clicks += $clicks;
                $total_impressions += $impressions;
                $position_sum += $position * $impressions; // Weight by impressions
                $keyword_count++;
                
                $top_keywords[] = [
                    'keyword' => $keyword,
                    'clicks' => $clicks,
                    'impressions' => $impressions,
                    'ctr' => round($ctr * 100, 2),
                    'position' => round($position, 1)
                ];
            }
        }
        
        $avg_ctr = $total_impressions > 0 ? ($total_clicks / $total_impressions) : 0;
        $avg_position = $total_impressions > 0 ? ($position_sum / $total_impressions) : 0;
        
        return [
            'page_analysis' => [
                'page_url' => $page_url,
                'date_range' => $date_range,
                'total_clicks' => $total_clicks,
                'total_impressions' => $total_impressions,
                'average_ctr' => round($avg_ctr * 100, 2),
                'average_position' => round($avg_position, 1),
                'keyword_count' => $keyword_count
            ],
            'top_keywords' => $top_keywords
        ];
    }
    
    /**
     * Get top keywords for a specific page
     * 
     * @param \Google\Service\SearchConsole $service GSC service instance
     * @param string $site_url Site URL from Search Console
     * @param string $page_url Page URL to analyze
     * @param string $date_range Date range for analysis
     * @return array Keywords analysis data
     */
    private function get_page_keywords($service, $site_url, $page_url, $date_range) {
        $dates = $this->get_date_range($date_range);
        
        $request = new \Google\Service\SearchConsole\SearchAnalyticsQueryRequest();
        $request->setStartDate($dates['start']);
        $request->setEndDate($dates['end']);
        $request->setDimensions(['query']);
        $request->setRowLimit(50);
        
        // Filter by specific page
        $dimension_filter = new \Google\Service\SearchConsole\SearchAnalyticsQueryRequestDimensionFilterGroups();
        $filter = new \Google\Service\SearchConsole\SearchAnalyticsQueryRequestDimensionFilter();
        $filter->setDimension('page');
        $filter->setOperator('equals');
        $filter->setExpression($page_url);
        $dimension_filter->setFilters([$filter]);
        $request->setDimensionFilterGroups([$dimension_filter]);
        
        $response = $service->searchanalytics->query($site_url, $request);
        
        $keywords = [];
        $high_performing = [];
        $underperforming = [];
        
        if ($response->getRows()) {
            foreach ($response->getRows() as $row) {
                $keyword_data = [
                    'keyword' => $row->getKeys()[0] ?? '',
                    'clicks' => $row->getClicks(),
                    'impressions' => $row->getImpressions(),
                    'ctr' => round($row->getCtr() * 100, 2),
                    'position' => round($row->getPosition(), 1)
                ];
                
                $keywords[] = $keyword_data;
                
                // Categorize keywords
                if ($keyword_data['position'] <= 10 && $keyword_data['clicks'] > 0) {
                    $high_performing[] = $keyword_data;
                } elseif ($keyword_data['position'] > 10 && $keyword_data['impressions'] > 50) {
                    $underperforming[] = $keyword_data;
                }
            }
        }
        
        return [
            'all_keywords' => $keywords,
            'high_performing_keywords' => $high_performing,
            'underperforming_keywords' => $underperforming,
            'keyword_summary' => [
                'total_keywords' => count($keywords),
                'high_performing_count' => count($high_performing),
                'underperforming_count' => count($underperforming)
            ]
        ];
    }
    
    /**
     * Find keyword opportunities for content optimization
     * 
     * @param \Google\Service\SearchConsole $service GSC service instance
     * @param string $site_url Site URL from Search Console
     * @param string $page_url Page URL to analyze
     * @param string $date_range Date range for analysis
     * @return array Opportunities analysis data
     */
    private function find_keyword_opportunities($service, $site_url, $page_url, $date_range) {
        $keywords_data = $this->get_page_keywords($service, $site_url, $page_url, $date_range);
        
        $opportunities = [];
        
        foreach ($keywords_data['all_keywords'] as $keyword) {
            $opportunity_score = 0;
            $opportunity_type = '';
            $recommendation = '';
            
            // High impressions but low CTR (positions 4-20)
            if ($keyword['impressions'] > 100 && $keyword['position'] >= 4 && $keyword['position'] <= 20 && $keyword['ctr'] < 5) {
                $opportunity_score = min(90, $keyword['impressions'] / 10);
                $opportunity_type = 'content_optimization';
                $recommendation = sprintf(
                    'Keyword "%s" gets %d impressions at position %.1f but only %.1f%% CTR. Optimize content and title to improve ranking.',
                    $keyword['keyword'],
                    $keyword['impressions'],
                    $keyword['position'],
                    $keyword['ctr']
                );
            }
            
            // Good impressions, decent position but no clicks
            elseif ($keyword['impressions'] > 50 && $keyword['position'] <= 20 && $keyword['clicks'] == 0) {
                $opportunity_score = min(80, $keyword['impressions'] / 5);
                $opportunity_type = 'title_meta_optimization';
                $recommendation = sprintf(
                    'Keyword "%s" shows at position %.1f with %d impressions but zero clicks. Improve title and meta description.',
                    $keyword['keyword'],
                    $keyword['position'],
                    $keyword['impressions']
                );
            }
            
            // Keywords in positions 11-30 with good impressions
            elseif ($keyword['position'] >= 11 && $keyword['position'] <= 30 && $keyword['impressions'] > 30) {
                $opportunity_score = min(70, ($keyword['impressions'] / 2) - $keyword['position']);
                $opportunity_type = 'ranking_improvement';
                $recommendation = sprintf(
                    'Keyword "%s" at position %.1f has potential - add more relevant content and internal links.',
                    $keyword['keyword'],
                    $keyword['position']
                );
            }
            
            if ($opportunity_score > 20) {
                $opportunities[] = [
                    'keyword' => $keyword['keyword'],
                    'current_position' => $keyword['position'],
                    'impressions' => $keyword['impressions'],
                    'clicks' => $keyword['clicks'],
                    'ctr' => $keyword['ctr'],
                    'opportunity_score' => round($opportunity_score, 1),
                    'opportunity_type' => $opportunity_type,
                    'recommendation' => $recommendation
                ];
            }
        }
        
        // Sort by opportunity score descending
        usort($opportunities, function($a, $b) {
            return $b['opportunity_score'] <=> $a['opportunity_score'];
        });
        
        return [
            'optimization_opportunities' => array_slice($opportunities, 0, 10), // Top 10 opportunities
            'summary' => [
                'total_opportunities' => count($opportunities),
                'high_priority_count' => count(array_filter($opportunities, function($opp) { return $opp['opportunity_score'] > 60; })),
                'medium_priority_count' => count(array_filter($opportunities, function($opp) { return $opp['opportunity_score'] >= 40 && $opp['opportunity_score'] <= 60; }))
            ]
        ];
    }
    
    /**
     * Suggest internal links based on keyword analysis and local search
     * 
     * @param \Google\Service\SearchConsole $service GSC service instance
     * @param string $site_url Site URL from Search Console
     * @param string $page_url Page URL to analyze
     * @param string $date_range Date range for analysis
     * @return array Internal linking suggestions
     */
    private function suggest_internal_links($service, $site_url, $page_url, $date_range) {
        $keywords_data = $this->get_page_keywords($service, $site_url, $page_url, $date_range);
        
        $internal_links = [];
        
        // Get top keywords to search for related content
        $search_keywords = array_slice($keywords_data['high_performing_keywords'], 0, 5);
        
        foreach ($search_keywords as $keyword_data) {
            $keyword = $keyword_data['keyword'];
            
            // Use Local Search tool to find related content
            $local_search_tool = new LocalSearch();
            $search_result = $local_search_tool->handle_tool_call([
                'query' => $keyword
            ]);
            
            if ($search_result['success'] && !empty($search_result['data']['results'])) {
                foreach ($search_result['data']['results'] as $result) {
                    // Skip the current page
                    if ($result['link'] === $page_url) {
                        continue;
                    }
                    
                    $internal_links[] = [
                        'anchor_text' => $keyword,
                        'target_url' => $result['link'],
                        'target_title' => $result['title'],
                        'relevance_score' => 0.8, // High relevance since it's based on performing keywords
                        'reason' => sprintf('Page ranks well for "%s" - good internal link target', $keyword),
                        'source_keyword_performance' => [
                            'clicks' => $keyword_data['clicks'],
                            'impressions' => $keyword_data['impressions'],
                            'position' => $keyword_data['position']
                        ]
                    ];
                }
            }
        }
        
        // Remove duplicates and limit results
        $unique_links = [];
        $seen_urls = [];
        
        foreach ($internal_links as $link) {
            if (!in_array($link['target_url'], $seen_urls)) {
                $unique_links[] = $link;
                $seen_urls[] = $link['target_url'];
            }
        }
        
        return [
            'internal_linking_suggestions' => array_slice($unique_links, 0, 10),
            'suggestions_summary' => [
                'total_suggestions' => count($unique_links),
                'based_on_keywords' => count($search_keywords),
                'average_relevance' => count($unique_links) > 0 ? 0.8 : 0
            ]
        ];
    }
    
    /**
     * Convert date range string to start/end dates
     * 
     * @param string $date_range Date range specification
     * @return array Start and end dates
     */
    private function get_date_range($date_range) {
        $end_date = date('Y-m-d', strtotime('-3 days')); // GSC data has 3-day delay
        
        switch ($date_range) {
            case '7d':
                $start_date = date('Y-m-d', strtotime('-10 days'));
                break;
            case '30d':
                $start_date = date('Y-m-d', strtotime('-33 days'));
                break;
            case '90d':
                $start_date = date('Y-m-d', strtotime('-93 days'));
                break;
            default:
                $start_date = date('Y-m-d', strtotime('-33 days'));
        }
        
        return [
            'start' => $start_date,
            'end' => $end_date
        ];
    }
    
    /**
     * Check if Google Search Console tool is properly configured
     * 
     * @return bool True if configured, false otherwise
     */
    public static function is_configured(): bool {
        return apply_filters('dm_tool_configured', false, 'google_search_console');
    }
}