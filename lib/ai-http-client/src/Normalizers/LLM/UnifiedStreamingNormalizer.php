<?php
/**
 * AI HTTP Client - Unified Streaming Normalizer
 * 
 * Single Responsibility: Handle streaming differences across providers
 * Normalizes streaming requests and processes streaming responses
 *
 * @package AIHttpClient\Normalizers
 * @author Chris Huber <https://chubes.net>
 */

defined('ABSPATH') || exit;

class AI_HTTP_Unified_Streaming_Normalizer {

    /**
     * Normalize streaming request for any provider
     *
     * @param array $standard_request Standard streaming request
     * @param string $provider_name Target provider
     * @return array Provider-specific streaming request
     */
    public function normalize_streaming_request($standard_request, $provider_name) {
        // Use filter-based provider validation and dynamic method dispatch
        $all_providers = apply_filters('ai_providers', []);
        $provider_info = $all_providers[strtolower($provider_name)] ?? null;
        
        if (!$provider_info || $provider_info['type'] !== 'llm') {
            throw new Exception('Streaming not supported for specified provider');
        }
        
        // Dynamic method dispatch based on provider name
        $method = "normalize_" . strtolower($provider_name) . "_streaming_request";
        if (!method_exists($this, $method)) {
            throw new Exception("Streaming request normalization not implemented for provider: {$provider_name}");
        }
        
        return $this->$method($standard_request);
    }

    /**
     * Process streaming chunk from any provider
     *
     * @param string $chunk Raw streaming chunk
     * @param string $provider_name Source provider
     * @return array|null Processed chunk data or null if not processable
     */
    public function process_streaming_chunk($chunk, $provider_name) {
        // Use filter-based provider validation and dynamic method dispatch
        $all_providers = apply_filters('ai_providers', []);
        $provider_info = $all_providers[strtolower($provider_name)] ?? null;
        
        if (!$provider_info || $provider_info['type'] !== 'llm') {
            return null;
        }
        
        // Dynamic method dispatch based on provider name
        $method = "process_" . strtolower($provider_name) . "_chunk";
        if (!method_exists($this, $method)) {
            return null; // Graceful fallback if chunk processing not implemented
        }
        
        return $this->$method($chunk);
    }

    /**
     * OpenAI streaming request normalization
     */
    private function normalize_openai_streaming_request($request) {
        // Add stream parameter
        $request['stream'] = true;
        return $request;
    }

    /**
     * Anthropic streaming request normalization
     */
    private function normalize_anthropic_streaming_request($request) {
        // Add stream parameter
        $request['stream'] = true;
        return $request;
    }

    /**
     * Gemini streaming request normalization
     */
    private function normalize_gemini_streaming_request($request) {
        // Gemini uses different endpoint for streaming
        // Add any Gemini-specific streaming parameters
        return $request;
    }

    /**
     * Grok streaming request normalization
     */
    private function normalize_grok_streaming_request($request) {
        // Grok uses OpenAI-compatible streaming
        $request['stream'] = true;
        return $request;
    }

    /**
     * OpenRouter streaming request normalization
     */
    private function normalize_openrouter_streaming_request($request) {
        // OpenRouter uses OpenAI-compatible streaming
        $request['stream'] = true;
        return $request;
    }

    /**
     * Process OpenAI streaming chunk
     */
    private function process_openai_chunk($chunk) {
        $lines = explode("\n", $chunk);
        $content = '';
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (strpos($line, 'data: ') === 0) {
                $data = substr($line, 6);
                if ($data === '[DONE]') {
                    break;
                }
                
                $json = json_decode($data, true);
                if ($json && isset($json['choices'][0]['delta']['content'])) {
                    $content .= $json['choices'][0]['delta']['content'];
                }
            }
        }
        
        return array(
            'content' => $content,
            'done' => strpos($chunk, '[DONE]') !== false
        );
    }

    /**
     * Process Anthropic streaming chunk
     */
    private function process_anthropic_chunk($chunk) {
        $lines = explode("\n", $chunk);
        $content = '';
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (strpos($line, 'data: ') === 0) {
                $data = substr($line, 6);
                
                $json = json_decode($data, true);
                if ($json && isset($json['delta']['text'])) {
                    $content .= $json['delta']['text'];
                }
            }
        }
        
        return array(
            'content' => $content,
            'done' => strpos($chunk, 'event: message_stop') !== false
        );
    }

    /**
     * Process Gemini streaming chunk
     */
    private function process_gemini_chunk($chunk) {
        $json = json_decode($chunk, true);
        $content = '';
        
        if ($json && isset($json['candidates'][0]['content']['parts'])) {
            foreach ($json['candidates'][0]['content']['parts'] as $part) {
                if (isset($part['text'])) {
                    $content .= $part['text'];
                }
            }
        }
        
        return array(
            'content' => $content,
            'done' => isset($json['candidates'][0]['finishReason'])
        );
    }

    /**
     * Process Grok streaming chunk (OpenAI-compatible)
     */
    private function process_grok_chunk($chunk) {
        return $this->process_openai_chunk($chunk);
    }

    /**
     * Process OpenRouter streaming chunk (OpenAI-compatible)
     */
    private function process_openrouter_chunk($chunk) {
        return $this->process_openai_chunk($chunk);
    }
}